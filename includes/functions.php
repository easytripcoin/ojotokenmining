<?php
// Utility Functions
// includes/functions.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Get all packages
 * @return array Array of packages
 */
function getAllPackages()
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("SELECT * FROM packages WHERE status = 'active' ORDER BY price ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get packages error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get package by ID
 * @param int $package_id Package ID
 * @return array|false Package data or false if not found
 */
function getPackageById($package_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? AND status = 'active'");
        $stmt->execute([$package_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logEvent("Get package error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Get user's ewallet balance
 * @param int $user_id User ID
 * @return float Ewallet balance
 */
function getEwalletBalance($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT balance FROM ewallet WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();
        return $result !== false ? floatval($result) : 0.00;
    } catch (Exception $e) {
        logEvent("Get ewallet balance error: " . $e->getMessage(), 'error');
        return 0.00;
    }
}

/**
 * Update ewallet balance
 * @param int $user_id User ID
 * @param float $new_balance New balance
 * @return bool Success status
 */
function updateEwalletBalance($user_id, $new_balance)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        return $stmt->execute([$new_balance, $user_id]);
    } catch (Exception $e) {
        logEvent("Update ewallet balance error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Add ewallet transaction
 * @param int $user_id User ID
 * @param string $type Transaction type
 * @param float $amount Transaction amount
 * @param string $description Transaction description
 * @param int|null $reference_id Reference ID for related records
 * @return bool Success status
 */
function addEwalletTransaction($user_id, $type, $amount, $description, $reference_id = null)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $amount, $description, $reference_id]);
    } catch (Exception $e) {
        logEvent("Add ewallet transaction error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Process ewallet transaction (update balance and add transaction record)
 * @param int $user_id User ID
 * @param string $type Transaction type
 * @param float $amount Transaction amount (positive for credit, negative for debit)
 * @param string $description Transaction description
 * @param int|null $reference_id Reference ID
 * @return bool Success status
 */
// Temporary debug in functions.php
function processEwalletTransaction($user_id, $type, $amount, $description, $reference_id = null)
{
    try {
        error_log("Processing transaction: user=$user_id, type=$type, amount=$amount");

        $pdo = getConnection();
        $current_balance = getEwalletBalance($user_id);
        $new_balance = $current_balance + $amount;

        if ($amount < 0 && $new_balance < 0) {
            error_log("Insufficient funds: current=$current_balance, needed=$amount");
            return false;
        }

        // Update balance
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        if (!$stmt->execute([$new_balance, $user_id])) {
            error_log("Failed to update balance");
            return false;
        }

        // Add transaction
        $stmt = $pdo->prepare("
            INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $result = $stmt->execute([$user_id, $type, $amount, $description, $reference_id]);

        if ($result) {
            error_log("Transaction processed successfully");
        }

        return $result;

    } catch (Exception $e) {
        error_log("Transaction error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's transaction history
 * @param int $user_id User ID
 * @param int $limit Number of records to return
 * @param int $offset Offset for pagination
 * @return array Transaction history
 */
function getTransactionHistory($user_id, $limit = 20, $offset = 0)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ewallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get transaction history error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Purchase package for user
 * @param int $user_id User ID
 * @param int $package_id Package ID
 * @return array Result with success status and message
 */
function purchasePackage($user_id, $package_id)
{
    try {
        $pdo = getConnection();

        // Get package details
        $package = getPackageById($package_id);
        if (!$package) {
            return ['success' => false, 'message' => 'Package not found.'];
        }

        // Check ewallet balance
        $balance = getEwalletBalance($user_id);
        if ($balance < $package['price']) {
            return ['success' => false, 'message' => 'Insufficient ewallet balance.'];
        }

        $pdo->beginTransaction();

        try {
            // Deduct amount from ewallet
            if (!processEwalletTransaction($user_id, 'purchase', -$package['price'], "Package purchase: {$package['name']}", $package_id)) {
                throw new Exception("Failed to process ewallet transaction");
            }

            // Add user package record
            $stmt = $pdo->prepare("INSERT INTO user_packages (user_id, package_id) VALUES (?, ?)");
            if (!$stmt->execute([$user_id, $package_id])) {
                throw new Exception("Failed to add user package record");
            }

            $user_package_id = $pdo->lastInsertId();

            // Process referral bonuses
            processReferralBonuses($user_id, $package['price'], $package_id);

            $pdo->commit();

            logEvent("Package purchased: User $user_id bought package $package_id", 'info');
            return ['success' => true, 'message' => "Package '{$package['name']}' purchased successfully!"];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        logEvent("Package purchase error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => 'Package purchase failed. Please try again.'];
    }
}

// /**
//  * Process referral bonuses for a package purchase
//  * @param int $user_id User ID who made the purchase
//  * @param float $package_price Package price
//  * @param int $package_id Package ID
//  */
// function processReferralBonuses($user_id, $package_price, $package_id)
// {
//     try {
//         $pdo = getConnection();

//         // Get user's sponsor chain
//         $sponsor_chain = getSponsorChain($user_id, 5); // Get up to 5 levels

//         foreach ($sponsor_chain as $level => $sponsor_id) {
//             if (isset(REFERRAL_BONUSES[$level])) {
//                 $percentage = REFERRAL_BONUSES[$level];
//                 $bonus_amount = ($package_price * $percentage) / 100;

//                 // Add referral bonus to sponsor's ewallet
//                 processEwalletTransaction(
//                     $sponsor_id,
//                     'referral',
//                     $bonus_amount,
//                     "Level $level referral bonus from user ID: $user_id",
//                     $user_id
//                 );

//                 // Record referral bonus
//                 $stmt = $pdo->prepare("INSERT INTO referral_bonuses (user_id, referred_user_id, level, amount, percentage, package_id) VALUES (?, ?, ?, ?, ?, ?)");
//                 $stmt->execute([$sponsor_id, $user_id, $level, $bonus_amount, $percentage, $package_id]);
//             }
//         }

//     } catch (Exception $e) {
//         logEvent("Process referral bonuses error: " . $e->getMessage(), 'error');
//     }
// }

/**
 * Get sponsor chain for a user
 * @param int $user_id User ID
 * @param int $max_levels Maximum levels to retrieve
 * @return array Sponsor chain [level => sponsor_id]
 */
function getSponsorChain($user_id, $max_levels = 5)
{
    try {
        $pdo = getConnection();
        $chain = [];
        $current_user_id = $user_id;
        $level = 2; // Start from level 2 (level 1 is direct purchase)

        while ($level <= ($max_levels + 1) && $current_user_id) {
            $stmt = $pdo->prepare("SELECT sponsor_id FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $sponsor_id = $stmt->fetchColumn();

            if ($sponsor_id) {
                $chain[$level] = $sponsor_id;
                $current_user_id = $sponsor_id;
                $level++;
            } else {
                break;
            }
        }

        return $chain;

    } catch (Exception $e) {
        logEvent("Get sponsor chain error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's active packages
 * @param int $user_id User ID
 * @return array Active packages
 */
function getUserActivePackages($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT up.*, p.name, p.price 
            FROM user_packages up 
            JOIN packages p ON up.package_id = p.id 
            WHERE up.user_id = ? AND up.status = 'active' 
            ORDER BY up.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get user active packages error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's package history
 * @param int $user_id User ID
 * @return array Package history
 */
function getUserPackageHistory($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT up.*, p.name, p.price 
            FROM user_packages up 
            JOIN packages p ON up.package_id = p.id 
            WHERE up.user_id = ? 
            ORDER BY up.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get user package history error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y g:i A')
{
    return date($format, strtotime($date));
}

/**
 * Get time ago string
 * @param string $date Date string
 * @return string Time ago string
 */
function timeAgo($date)
{
    $time = time() - strtotime($date);

    if ($time < 60)
        return 'just now';
    if ($time < 3600)
        return floor($time / 60) . ' minutes ago';
    if ($time < 86400)
        return floor($time / 3600) . ' hours ago';
    if ($time < 2592000)
        return floor($time / 86400) . ' days ago';
    if ($time < 31104000)
        return floor($time / 2592000) . ' months ago';
    return floor($time / 31104000) . ' years ago';
}

/**
 * Truncate text
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to append
 * @return string Truncated text
 */
function truncateText($text, $length = 50, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate random string
 * @param int $length String length
 * @return string Random string
 */
function generateRandomString($length = 10)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get default admin sponsor ID
 * @return int|null Default admin sponsor ID
 */
function getDefaultSponsorId()
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result ? intval($result) : null;
    } catch (Exception $e) {
        logEvent("Get default sponsor error: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Assign sponsor to user (with fallback to admin)
 * @param string $sponsor_username Sponsor username (optional)
 * @return array Result with sponsor_id and message
 */
function assignSponsor($sponsor_username = null)
{
    try {
        $pdo = getConnection();

        if (!empty($sponsor_username)) {
            // Try to find the specified sponsor
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$sponsor_username]);
            $sponsor = $stmt->fetch();

            if ($sponsor) {
                return [
                    'success' => true,
                    'sponsor_id' => $sponsor['id'],
                    'sponsor_username' => $sponsor['username'],
                    'message' => "Sponsor assigned: {$sponsor['username']}"
                ];
            } else {
                // Sponsor not found, check if we should fallback to admin
                if (getAdminSetting('default_sponsor_enabled') === '1') {
                    $admin_sponsor = getDefaultSponsorId();
                    if ($admin_sponsor) {
                        return [
                            'success' => true,
                            'sponsor_id' => $admin_sponsor,
                            'sponsor_username' => 'admin',
                            'message' => "Specified sponsor not found. Assigned to admin as default sponsor."
                        ];
                    }
                }
                return [
                    'success' => false,
                    'sponsor_id' => null,
                    'message' => "Sponsor username '$sponsor_username' not found."
                ];
            }
        } else {
            // No sponsor specified, use admin if enabled
            if (getAdminSetting('orphan_prevention') === '1') {
                $admin_sponsor = getDefaultSponsorId();
                if ($admin_sponsor) {
                    return [
                        'success' => true,
                        'sponsor_id' => $admin_sponsor,
                        'sponsor_username' => 'admin',
                        'message' => "No sponsor specified. Assigned to admin as default sponsor."
                    ];
                }
            }

            // No sponsor assignment
            return [
                'success' => true,
                'sponsor_id' => null,
                'sponsor_username' => null,
                'message' => "No sponsor assigned."
            ];
        }

    } catch (Exception $e) {
        logEvent("Assign sponsor error: " . $e->getMessage(), 'error');
        return [
            'success' => false,
            'sponsor_id' => null,
            'message' => "Error assigning sponsor."
        ];
    }
}

/**
 * Check if admin setting exists and get its value
 * @param string $setting_name Setting name
 * @return mixed Setting value or null if not found
 */
function getAdminSetting($setting_name)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = ?");
        $stmt->execute([$setting_name]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        logEvent("Get admin setting error: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Update admin setting
 * @param string $setting_name Setting name
 * @param mixed $setting_value Setting value
 * @return bool Success status
 */
function updateAdminSetting($setting_name, $setting_value)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        return $stmt->execute([$setting_name, $setting_value, $setting_value]);
    } catch (Exception $e) {
        logEvent("Update admin setting error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Get withdrawal requests for user
 * @param int $user_id User ID
 * @return array Withdrawal requests
 */
function getUserWithdrawalRequests($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM withdrawal_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get withdrawal requests error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get refill requests for user
 * @param int $user_id User ID
 * @return array Refill requests
 */
function getUserRefillRequests($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM refill_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get refill requests error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's monthly bonus history
 * @param int $user_id User ID
 * @return array Bonus history
 */
function getUserMonthlyBonuses($user_id)
{
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
function isPackageEligibleForWithdrawRemine($package_id)
{
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
function processWithdrawRemine($user_id, $package_id, $action)
{
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
        if (isset($pdo))
            $pdo->rollBack();
        logEvent("Withdraw/remine error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => 'Processing failed'];
    }
}

/**
 * Get user's referral tree with levels
 * @param int $user_id User ID
 * @param int $max_levels Maximum levels to retrieve
 * @return array Referral tree
 */
function getUserReferralTree($user_id, $max_levels = 5)
{
    try {
        $pdo = getConnection();
        $tree = [];

        function buildReferralTree($pdo, $sponsor_id, $level = 1, $max_levels = 5)
        {
            if ($level > $max_levels)
                return [];

            $stmt = $pdo->prepare("
                SELECT id, username, email, created_at
                FROM users
                WHERE sponsor_id = ? AND status = 'active'
                ORDER BY created_at ASC
            ");
            $stmt->execute([$sponsor_id]);
            $referrals = $stmt->fetchAll();

            foreach ($referrals as &$ref) {
                $ref['level'] = $level;
                $ref['children'] = buildReferralTree($pdo, $ref['id'], $level + 1, $max_levels);
                $ref['total_bonus'] = getUserReferralBonus($ref['id']);
            }

            return $referrals;
        }

        return buildReferralTree($pdo, $user_id);

    } catch (Exception $e) {
        logEvent("Get referral tree error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's referral statistics
 * @param int $user_id User ID
 * @return array Referral stats
 */
function getUserReferralStats($user_id)
{
    try {
        $pdo = getConnection();

        // Get total referrals by level
        $stats = [
            'total_referrals' => 0,
            'level_stats' => [
                2 => ['count' => 0, 'bonus' => 0],
                3 => ['count' => 0, 'bonus' => 0],
                4 => ['count' => 0, 'bonus' => 0],
                5 => ['count' => 0, 'bonus' => 0]
            ]
        ];

        // Get counts
        $stmt = $pdo->prepare("
            SELECT level, COUNT(*) as count, SUM(amount) as total_bonus
            FROM referral_bonuses
            WHERE user_id = ?
            GROUP BY level
        ");
        $stmt->execute([$user_id]);

        while ($row = $stmt->fetch()) {
            if ($row['level'] >= 2 && $row['level'] <= 5) {
                $stats['level_stats'][$row['level']] = [
                    'count' => $row['count'],
                    'bonus' => $row['total_bonus']
                ];
                $stats['total_referrals'] += $row['count'];
            }
        }

        return $stats;

    } catch (Exception $e) {
        logEvent("Get referral stats error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get referral bonus for a user
 * @param int $user_id User ID
 * @return float Total bonus
 */
function getUserReferralBonus($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_bonus
            FROM referral_bonuses
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();

    } catch (Exception $e) {
        logEvent("Get referral bonus error: " . $e->getMessage(), 'error');
        return 0;
    }
}

/**
 * Process referral bonuses for a purchase
 * @param int $buyer_id User who made purchase
 * @param float $amount Package price
 * @param int $package_id Package ID
 */
function processReferralBonuses($buyer_id, $amount, $package_id)
{
    try {
        $pdo = getConnection();

        // Get sponsor chain
        $sponsor_chain = [];
        $current_id = $buyer_id;
        $level = 2;

        while ($level <= 5 && $current_id) {
            $stmt = $pdo->prepare("SELECT sponsor_id FROM users WHERE id = ?");
            $stmt->execute([$current_id]);
            $sponsor_id = $stmt->fetchColumn();

            if ($sponsor_id) {
                $sponsor_chain[$level] = $sponsor_id;
                $current_id = $sponsor_id;
                $level++;
            } else {
                break;
            }
        }

        // Process bonuses
        foreach ($sponsor_chain as $level => $sponsor_id) {
            if (isset(REFERRAL_BONUSES[$level])) {
                $percentage = REFERRAL_BONUSES[$level];
                $bonus_amount = ($amount * $percentage) / 100;

                // Add referral bonus
                $stmt = $pdo->prepare("
                    INSERT INTO referral_bonuses (user_id, referred_user_id, level, amount, percentage, package_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sponsor_id,
                    $buyer_id,
                    $level,
                    $bonus_amount,
                    $percentage,
                    $package_id
                ]);

                // Add to sponsor's ewallet
                processEwalletTransaction(
                    $sponsor_id,
                    'referral',
                    $bonus_amount,
                    "Level $level referral bonus from user ID: $buyer_id",
                    $buyer_id
                );
            }
        }

    } catch (Exception $e) {
        logEvent("Process referral bonuses error: " . $e->getMessage(), 'error');
    }
}

?>