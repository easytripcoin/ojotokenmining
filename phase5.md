# 🚀 **Phase 5: Monthly Bonus System**
> Automated 50% monthly bonus processing for 3 months, then withdraw/remine options

---

## 📋 **Phase 5 Files Overview**

| File                               | Purpose                     | Status       |
| ---------------------------------- | --------------------------- | ------------ |
| `cron/monthly_bonus_processor.php` | Automated bonus calculation | **New**      |
| `api/monthly_bonus.php`            | API endpoint for bonus data | **New**      |
| Updated `user/dashboard.php`       | Bonus tracking & buttons    | **Enhanced** |
| Updated `functions.php`            | Bonus calculation logic     | **Enhanced** |

---

## 📁 **File 1: `cron/monthly_bonus_processor.php`**

```php
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
            processEwalletTransaction(
                $package['user_id'],
                'bonus',
                $bonus_amount,
                "Monthly bonus for {$package['name']} - Cycle {$package['current_cycle']}",
                $bonus_id
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
```

---

## 📁 **File 2: `api/monthly_bonus.php`**

```php
<?php
// api/monthly_bonus.php - JSON API for monthly bonus data
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();

try {
    $pdo = getConnection();
    
    // Get user's monthly bonuses
    $stmt = $pdo->prepare("
        SELECT mb.*, p.name as package_name, p.price
        FROM monthly_bonuses mb
        JOIN packages p ON mb.package_id = p.id
        WHERE mb.user_id = ?
        ORDER BY mb.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active packages with bonus status
    $stmt = $pdo->prepare("
        SELECT up.*, p.name, p.price, p.id as package_id,
               (p.price * ? / 100) as monthly_bonus_amount,
               CASE 
                   WHEN up.current_cycle > ? THEN 'withdraw_remine'
                   WHEN up.current_cycle = ? THEN 'last_month'
                   ELSE 'earning'
               END as status
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ? AND up.status = 'active'
    ");
    $stmt->execute([MONTHLY_BONUS_PERCENTAGE, BONUS_MONTHS, BONUS_MONTHS, $user_id]);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bonuses' => $bonuses,
        'packages' => $packages,
        'bonus_percentage' => MONTHLY_BONUS_PERCENTAGE,
        'bonus_months' => BONUS_MONTHS
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
```

---

## 📁 **File 3: Updated `user/dashboard.php` - Add Bonus Section**

```php
<!-- Add this section in user/dashboard.php after stats cards -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-gift"></i> Monthly Bonus Tracking</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $pdo = getConnection();
                    $stmt = $pdo->prepare("
                        SELECT up.*, p.name, p.price,
                               (p.price * ? / 100) as monthly_bonus,
                               CASE 
                                   WHEN up.current_cycle > ? THEN 'withdraw_remine'
                                   WHEN up.current_cycle = ? THEN 'last_month'
                                   ELSE 'earning'
                               END as status
                        FROM user_packages up
                        JOIN packages p ON up.package_id = p.id
                        WHERE up.user_id = ? AND up.status = 'active'
                        ORDER BY up.created_at DESC
                    ");
                    $stmt->execute([MONTHLY_BONUS_PERCENTAGE, BONUS_MONTHS, BONUS_MONTHS, $user_id]);
                    $bonus_packages = $stmt->fetchAll();
                    
                    if (empty($bonus_packages)):
                ?>
                    <p class="text-muted">No active packages for monthly bonus</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($bonus_packages as $pkg): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6><?= htmlspecialchars($pkg['name']) ?></h6>
                                    <small class="text-muted">Cycle <?= $pkg['current_cycle'] ?>/<?= $pkg['total_cycles'] ?></small>
                                    <div class="mt-2">
                                        <strong>Monthly Bonus: <?= formatCurrency($pkg['monthly_bonus']) ?></strong>
                                    </div>
                                    
                                    <?php if ($pkg['status'] === 'earning'): ?>
                                        <span class="badge bg-success">Earning Bonus</span>
                                    <?php elseif ($pkg['status'] === 'last_month'): ?>
                                        <span class="badge bg-warning">Last Month</span>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-primary" onclick="showWithdrawRemine(<?= $pkg['id'] ?>)">
                                                Withdraw/Remine
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showWithdrawRemine(packageId) {
    // Show withdraw/remine options
    window.location.href = `package_action.php?id=${packageId}&action=withdraw_remine`;
}
</script>
```

---

## 📁 **File 4: Updated `functions.php` - Bonus Functions**

```php
// Add these functions to includes/functions.php

/**
 * Get user's monthly bonus history
 * @param int $user_id User ID
 * @return array Bonus history
 */
function getUserMonthlyBonuses($user_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT mb.*, p.name as package_name, p.price
            FROM monthly_bonuses mb
            JOIN packages p ON mb.package_id = p.id
            WHERE mb.user_id = ?
            ORDER BY mb.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get user bonuses error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Check if package is eligible for withdraw/remine
 * @param int $package_id Package ID
 * @return bool Eligible status
 */
function isPackageEligibleForWithdrawRemine($package_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM user_packages 
            WHERE id = ? AND current_cycle > ? AND status = 'active'
        ");
        $stmt->execute([$package_id, BONUS_MONTHS]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        logEvent("Check withdraw eligibility error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Process withdraw/remine for completed package
 * @param int $user_id User ID
 * @param int $package_id Package ID
 * @param string $action 'withdraw' or 'remine'
 * @return array Result
 */
function processWithdrawRemine($user_id, $package_id, $action) {
    try {
        $pdo = getConnection();
        $pdo->beginTransaction();

        // Get package details
        $stmt = $pdo->prepare("
            SELECT up.*, p.price, p.name
            FROM user_packages up
            JOIN packages p ON up.package_id = p.id
            WHERE up.id = ? AND up.user_id = ? AND up.status = 'active'
            AND up.current_cycle > ?
        ");
        $stmt->execute([$package_id, $user_id, BONUS_MONTHS]);
        $package = $stmt->fetch();

        if (!$package) {
            return ['success' => false, 'message' => 'Package not eligible for withdraw/remine'];
        }

        if ($action === 'withdraw') {
            // Return original package price
            processEwalletTransaction(
                $user_id,
                'refund',
                $package['price'],
                "Withdraw completed package: {$package['name']}",
                $package['id']
            );
            
            // Mark package as withdrawn
            $stmt = $pdo->prepare("UPDATE user_packages SET status = 'withdrawn' WHERE id = ?");
            $stmt->execute([$package_id]);
            
        } elseif ($action === 'remine') {
            // Reset for new cycle
            $stmt = $pdo->prepare("
                UPDATE user_packages 
                SET current_cycle = 1, status = 'active' 
                WHERE id = ?
            ");
            $stmt->execute([$package_id]);
            
            // Deduct from ewallet for new purchase
            if (processEwalletTransaction($user_id, 'purchase', -$package['price'], "Remine package: {$package['name']}", $package['id'])) {
                // Start new cycle
                $stmt = $pdo->prepare("
                    INSERT INTO user_packages (user_id, package_id, current_cycle, total_cycles) 
                    VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$user_id, $package['package_id'], BONUS_MONTHS]);
            }
        }

        $pdo->commit();
        return ['success' => true, 'message' => ucfirst($action) . ' processed successfully'];
        
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        logEvent("Withdraw/remine error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => 'Processing failed'];
    }
}
```

---

## 📋 **Setup Instructions**

### **1. Create Monthly Bonus Table**
```sql
-- Add if not exists
CREATE TABLE IF NOT EXISTS monthly_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    user_package_id INT NOT NULL,
    month_number INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_package_id) REFERENCES user_packages (id) ON DELETE CASCADE
);
```

### **2. Setup Cron Job**
```bash
# Add to crontab (runs 1st of every month)
0 0 1 * * /usr/bin/php /path/to/ojotokenmining/cron/monthly_bonus_processor.php >> /path/to/logs/monthly_bonus.log 2>&1
```

### **3. Test the System**
1. **Run cron manually**: `php cron/monthly_bonus_processor.php`
2. **Check dashboard** for bonus tracking
3. **Test withdraw/remine** after 3 months

## ✅ **Phase 5 Complete Features**

- **Automated monthly bonus** (50% for 3 months)
- **Admin dashboard** with bonus tracking
- **User dashboard** with cycle status
- **Withdraw/Remine** options after 3rd month
- **API endpoints** for bonus data
- **Cron automation** for monthly processing