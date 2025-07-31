<?php
// cron/monthly_bonus_processor.php - Completely fixed
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

    // Get eligible packages
    $stmt = $pdo->prepare("
        SELECT up.*, p.price, p.name, u.username
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        JOIN users u ON up.user_id = u.id
        WHERE up.status = 'active' 
        AND up.current_cycle <= up.total_cycles
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

        echo "Processing: {$package['username']} - {$package['name']} (Cycle {$package['current_cycle']}) - Bonus: $bonus_amount\n";

        $pdo->beginTransaction();

        try {
            // 1. Add bonus record
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

            // 2. Update ewallet balance directly (no nested calls)
            $stmt = $pdo->prepare("
                UPDATE ewallet 
                SET balance = balance + ?, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$bonus_amount, $package['user_id']]);

            // 3. Add transaction record
            $stmt = $pdo->prepare("
                INSERT INTO ewallet_transactions (user_id, type, amount, description, status, is_withdrawable) 
                VALUES (?, 'bonus', ?, ?, 'completed', 1)
            ");
            $stmt->execute([
                $package['user_id'],
                $bonus_amount,
                "Monthly bonus for {$package['name']} - Cycle {$package['current_cycle']}"
            ]);

            // 4. Update cycle
            $new_cycle = $package['current_cycle'] + 1;
            $stmt = $pdo->prepare("
                UPDATE user_packages 
                SET current_cycle = ?,
                status = CASE WHEN ? > ? THEN 'completed' ELSE 'active' END
                WHERE id = ?
            ");
            $stmt->execute([$new_cycle, $new_cycle, BONUS_MONTHS, $package['id']]);

            $pdo->commit();
            $processed++;
            echo "✅ Processed successfully\n";

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "SQL Error: " . implode(" - ", $pdo->errorInfo()) . "\n";
        }
    }

    echo "Processed $processed monthly bonuses\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Monthly bonus processing completed\n";
?>