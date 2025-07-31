<?php
// cron/monthly_bonus_processor.php
// Run via cron: php monthly_bonus_processor.php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die("This script must be run via CLI\n");
}

echo "Starting monthly bonus processing...\n";

try {
    $pdo = getConnection();

    // Get active packages eligible for monthly bonus
    $stmt = $pdo->prepare("
        SELECT up.*, p.price, p.name, u.username
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        JOIN users u ON up.user_id = u.id
        WHERE up.status = 'active' 
        AND up.current_cycle <= up.total_cycles
        AND MONTH(up.purchase_date) < MONTH(NOW())
        AND NOT EXISTS (
            SELECT 1 FROM monthly_bonuses mb 
            WHERE mb.user_package_id = up.id 
            AND mb.month_number = up.current_cycle
        )
    ");
    $stmt->execute();
    $eligible_packages = $stmt->fetchAll();

    echo "Found " . count($eligible_packages) . " eligible packages\n";

    $processed = 0;

    foreach ($eligible_packages as $package) {
        $bonus_amount = ($package['price'] * MONTHLY_BONUS_PERCENTAGE) / 100;

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Add monthly bonus record
            $stmt = $pdo->prepare("
                INSERT INTO monthly_bonuses (user_id, package_id, user_package_id, month_number, amount) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $package['user_id'],
                $package['package_id'],
                $package['id'],
                $package['current_cycle'],
                $bonus_amount
            ]);

            $bonus_id = $pdo->lastInsertId();

            // Add ewallet transaction
            // processEwalletTransaction(
            //     $package['user_id'],
            //     'bonus',
            //     $bonus_amount,
            //     "Monthly bonus for {$package['name']} - Cycle {$package['current_cycle']}",
            //     $bonus_id
            // );

            // Add to ewallet (withdrawable)
            addEwalletTransaction(
                /* $user_id */ $package['user_id'],
                'bonus',
                $bonus_amount,
                "Monthly bonus for {$package['name']} - Cycle {$package['current_cycle']}",
                $bonus_id,
                true // Mark as withdrawable
            );

            // Update current cycle
            $stmt = $pdo->prepare("
                UPDATE user_packages 
                SET current_cycle = current_cycle + 1,
                status = CASE WHEN current_cycle >= total_cycles THEN 'completed' ELSE 'active' END
                WHERE id = ?
            ");
            $stmt->execute([$package['id']]);

            // Handle 4th month withdraw/remine
            if ($package['current_cycle'] >= BONUS_MONTHS) {
                logEvent("Package {$package['id']} reached 4th month - withdraw/remine available", 'info');
            }

            $pdo->commit();
            $processed++;
            echo "Processed package {$package['id']} for user {$package['username']}\n";

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error processing package {$package['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "Processed $processed monthly bonuses\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Monthly bonus processing completed\n";
?>