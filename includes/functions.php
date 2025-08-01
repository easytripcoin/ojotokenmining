<?php
// Utility Functions
// includes/functions.php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

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
 * Get user's withdrawable balance
 * @param int $user_id User ID
 * @return float Withdrawable balance
 */
function getWithdrawableBalance($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as withdrawable_balance
            FROM ewallet_transactions
            WHERE user_id = ? AND is_withdrawable = 1
        ");
        $stmt->execute([$user_id]);
        return floatval($stmt->fetchColumn());
    } catch (Exception $e) {
        logEvent("Get withdrawable balance error: " . $e->getMessage(), 'error');
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

function addEwalletTransaction($user_id, $type, $amount, $description, $reference_id = null, $is_withdrawable = 0)
{
    try {
        error_log("Adding ewallet transaction: user=$user_id, type=$type, amount=$amount, description=$description, reference_id=$reference_id, is_withdrawable=$is_withdrawable");

        $pdo = getConnection();
        $pdo->beginTransaction(); // Start a transaction

        $current_balance = getEwalletBalance($user_id);
        $new_balance = $current_balance + $amount;

        // Update balance
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        if (!$stmt->execute([$new_balance, $user_id])) {
            error_log("Failed to update balance for user $user_id");
            $pdo->rollBack(); // Rollback transaction
            return false;
        }

        // Determine the status based on the type
        $status = in_array($type, ['referral', 'bonus', 'transfer', 'transfer_charge', 'purchase', 'refund']) ? 'completed' : 'pending';

        // Ensure is_withdrawable is a valid integer (0 or 1)
        $is_withdrawable = (int) $is_withdrawable;

        // Add transaction
        $stmt = $pdo->prepare("
            INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id, status, is_withdrawable) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt->execute([$user_id, $type, $amount, $description, $reference_id, $status, $is_withdrawable])) {
            error_log("Failed to add transaction");
            $pdo->rollBack(); // Rollback transaction
            return false;
        }

        $pdo->commit(); // Commit transaction
        error_log("Transaction added successfully for user $user_id");
        return true;

    } catch (Exception $e) {
        error_log("Transaction error: " . $e->getMessage());
        $pdo->rollBack(); // Rollback transaction on error
        return false;
    }
}

function processEwalletTransaction($user_id, $type, $amount, $description, $reference_id = null)
{
    try {
        $pdo = getConnection();

        // Check if already in transaction
        $inTransaction = $pdo->inTransaction();
        $shouldBegin = !$inTransaction;

        if ($shouldBegin) {
            $pdo->beginTransaction();
        }

        $current_balance = getEwalletBalance($user_id);
        $new_balance = $current_balance + $amount;

        if ($amount < 0 && $new_balance < 0) {
            if ($shouldBegin)
                $pdo->rollBack();
            return false;
        }

        // Update balance
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        if (!$stmt->execute([$new_balance, $user_id])) {
            if ($shouldBegin)
                $pdo->rollBack();
            return false;
        }

        // Add transaction
        $status = in_array($type, ['referral', 'bonus', 'transfer', 'transfer_charge', 'purchase', 'refund']) ? 'completed' : 'pending';
        $is_withdrawable = ($type === 'transfer' || $type === 'purchase') ? 0 : 1;

        $stmt = $pdo->prepare("
            INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id, status, is_withdrawable) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt->execute([$user_id, $type, $amount, $description, $reference_id, $status, $is_withdrawable])) {
            if ($shouldBegin)
                $pdo->rollBack();
            return false;
        }

        if ($shouldBegin) {
            $pdo->commit();
        }

        return true;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
 * Purchase a package for a user
 * @param int   $user_id
 * @param int   $package_id
 * @return array  ['success'=>bool,'message'=>string]
 */
function purchasePackage($user_id, $package_id)
{
    try {
        $pdo = getConnection();

        // 1️⃣  Fetch package details
        $package = getPackageById($package_id);
        if (!$package) {
            return ['success' => false, 'message' => 'Package not found.'];
        }

        // 2️⃣  Check balance
        $balance = getEwalletBalance($user_id);
        if ($balance < $package['price']) {
            return ['success' => false, 'message' => 'Insufficient e-wallet balance.'];
        }

        // 3️⃣  Transaction block
        $pdo->beginTransaction();

        // 3-a. Debit the buyer
        $debitOk = processEwalletTransaction(
            $user_id,
            'purchase',
            -$package['price'],
            "Package purchase: {$package['name']}",
            $package_id
        );
        if (!$debitOk) {
            throw new Exception('Could not debit e-wallet.');
        }

        // 3-b. Insert user_packages row
        $stmt = $pdo->prepare(
            "INSERT INTO user_packages 
             (user_id, package_id, purchase_date, current_cycle, total_cycles, status, next_bonus_date)
             VALUES (?, ?, NOW(), 1, ?, 'active', ?)"
        );
        $stmt->execute([
            $user_id,
            $package_id,
            BONUS_MONTHS,          // = 3
            (new DateTime('now'))->modify('+30 days')->format('Y-m-d H:i:s')
        ]);
        // $userPackageId = $pdo->lastInsertId();

        // 3-c. Commit
        $pdo->commit();

        // 4️⃣  Referral bonuses (outside the main transaction)
        processReferralBonuses($user_id, $package['price'], $package_id);

        return [
            'success' => true,
            'message' => "Package '{$package['name']}' purchased successfully!"
        ];

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logEvent("purchasePackage error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => 'Purchase failed. Please try again.'];
    }
}

// for debugging purposes
// function purchasePackage($user_id, $package_id)
// {
//     try {
//         $pdo = getConnection();

//         // Get package details
//         $package = getPackageById($package_id);
//         if (!$package) {
//             return ['success' => false, 'message' => 'Package not found.'];
//         }

//         // Check ewallet balance
//         $balance = getEwalletBalance($user_id);
//         if ($balance < $package['price']) {
//             return ['success' => false, 'message' => 'Insufficient ewallet balance.'];
//         }

//         // Begin transaction
//         $pdo->beginTransaction();

//         try {
//             // Deduct amount from ewallet
//             $deduct_success = processEwalletTransaction($user_id, 'purchase', -$package['price'], "Package purchase: {$package['name']}", $package_id);

//             if (!$deduct_success) {
//                 $pdo->rollBack();
//                 return ['success' => false, 'message' => 'Failed to process ewallet transaction'];
//             }

//             // Add user package record
//             $stmt = $pdo->prepare("INSERT INTO user_packages (user_id, package_id) VALUES (?, ?)");
//             if (!$stmt->execute([$user_id, $package_id])) {
//                 $pdo->rollBack();
//                 return ['success' => false, 'message' => 'Failed to add package record'];
//             }

//             // Process referral bonuses (outside transaction)
//             $pdo->commit();
//             processReferralBonuses($user_id, $package['price'], $package_id);

//             return ['success' => true, 'message' => "Package '{$package['name']}' purchased successfully!"];

//         } catch (Exception $e) {
//             if ($pdo->inTransaction()) {
//                 $pdo->rollBack();
//             }
//             return ['success' => false, 'message' => 'Package purchase failed: ' . $e->getMessage()];
//         }

//     } catch (Exception $e) {
//         return ['success' => false, 'message' => 'Package purchase failed. Please try again.'];
//     }
// }

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

function processWithdrawRemine($user_id, $package_id, $action)
{
    try {
        $pdo = getConnection();

        // Check transaction state
        $inTransaction = $pdo->inTransaction();
        $shouldBegin = !$inTransaction;

        if ($shouldBegin) {
            $pdo->beginTransaction();
        }

        try {
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
                if ($shouldBegin)
                    $pdo->rollBack();
                return ['success' => false, 'message' => 'Package not eligible'];
            }

            if ($action === 'withdraw') {
                // Use processEwalletTransaction (which handles its own transactions)
                $success = processEwalletTransaction(
                    $user_id,
                    'refund',
                    $package['price'],
                    "Withdraw completed package: {$package['name']}",
                    $package['id']
                );

                if ($success) {
                    $stmt = $pdo->prepare("UPDATE user_packages SET status = 'withdrawn' WHERE id = ?");
                    $stmt->execute([$package_id]);
                }

            } elseif ($action === 'remine') {
                $success = processEwalletTransaction(
                    $user_id,
                    'purchase',
                    -$package['price'],
                    "Remine package: {$package['name']}",
                    $package['id']
                );

                if ($success) {
                    $stmt = $pdo->prepare("
                        UPDATE user_packages 
                        SET current_cycle = 1, status = 'active' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$package_id]);
                }
            }

            if ($shouldBegin) {
                $pdo->commit();
            }

            return ['success' => $success, 'message' => ucfirst($action) . ' processed successfully'];

        } catch (Exception $e) {
            if ($shouldBegin && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (Exception $e) {
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

                // // Add to sponsor's ewallet
                // processEwalletTransaction(
                //     $sponsor_id,
                //     'referral',
                //     $bonus_amount,
                //     "Level $level referral bonus from user ID: $buyer_id",
                //     $buyer_id
                // );

                // Add to sponsor's ewallet (withdrawable)
                addEwalletTransaction(
                    $sponsor_id,
                    'referral',
                    $bonus_amount,
                    "Level $level referral bonus from user ID: $buyer_id",
                    $buyer_id,
                    true // Mark as withdrawable
                );
            }
        }

        return true;

    } catch (Exception $e) {
        logEvent("Process referral bonuses error: " . $e->getMessage(), 'error');

        return false;
    }
}

/* Add these functions to includes/functions.php */

// /**
//  * Update user profile
//  * @param int $user_id User ID
//  * @param array $data Profile data
//  * @return bool Success
//  */
// function updateUserProfile($user_id, $data)
// {
//     try {
//         $pdo = getConnection();
//         $fields = [];
//         $values = [];

//         foreach ($data as $key => $value) {
//             $fields[] = "$key = ?";
//             $values[] = $value;
//         }
//         $values[] = $user_id;

//         $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
//         return $stmt->execute($values);
//     } catch (Exception $e) {
//         logEvent("Update profile error: " . $e->getMessage(), 'error');
//         return false;
//     }
// }

// /**
//  * Update user password
//  * @param int $user_id User ID
//  * @param string $hashed_password New hashed password
//  * @return bool Success
//  */
// function updateUserPassword($user_id, $hashed_password)
// {
//     try {
//         $pdo = getConnection();
//         $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
//         return $stmt->execute([$hashed_password, $user_id]);
//     } catch (Exception $e) {
//         logEvent("Update password error: " . $e->getMessage(), 'error');
//         return false;
//     }
// }

/**
 * Generate identicon avatar from username
 * @param string $username Username to generate identicon for
 * @return string Base64 data URL of identicon
 */
function generateIdenticon($username)
{
    // Simple deterministic hash from username
    $hash = 0;
    for ($i = 0; $i < strlen($username); $i++) {
        $hash = $username[$i] . (($hash << 5) - $hash);
    }

    // Create canvas
    $canvas = imagecreatetruecolor(50, 50);

    // Background
    $bg = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $bg);

    // Color from hash
    $hue = abs($hash) % 360;
    $color = imagecolorallocate(
        $canvas,
        ($hue * 2) % 255,
        ($hue * 3) % 255,
        ($hue * 5) % 255
    );

    // 5x5 symmetric pattern
    $grid = 5;
    $cell = 10;
    for ($x = 0; $x < 5; $x++) {
        for ($y = 0; $y < 5; $y++) {
            if ((abs($hash) >> ($x * 5 + $y)) & 1) {
                // Mirror for symmetry
                imagefilledrectangle(
                    $canvas,
                    $x * $cell,
                    $y * $cell,
                    $x * $cell + $cell - 1,
                    $y * $cell + $cell - 1,
                    $color
                );
                imagefilledrectangle(
                    $canvas,
                    (4 - $x) * $cell,
                    $y * $cell,
                    (4 - $x) * $cell + $cell - 1,
                    $y * $cell + $cell - 1,
                    $color
                );
            }
        }
    }

    // Convert to base64
    ob_start();
    imagepng($canvas);
    $data = ob_get_clean();
    imagedestroy($canvas);

    return 'data:image/png;base64,' . base64_encode($data);
}

function debugLog($message)
{
    $file = __DIR__ . '/../logs/debug_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($file, "[$time] $message\n", FILE_APPEND | LOCK_EX);
}

/**
 * Execute code within a transaction context
 * @param callable $callback Function to execute
 * @return mixed Result of callback
 */
function executeInTransaction($callback)
{
    // Clean usage pattern
    // $result = executeInTransaction(function ($pdo) use ($user_id, $amount) {
    //     // Your transactional code here
    //     $stmt = $pdo->prepare("UPDATE ewallet SET balance = balance + ? WHERE user_id = ?");
    //     $stmt->execute([$amount, $user_id]);
    //     return true;
    // });

    try {
        $pdo = getConnection();
        $inTransaction = $pdo->inTransaction();
        $shouldBegin = !$inTransaction;

        if ($shouldBegin) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if ($shouldBegin && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;

        } catch (Exception $e) {
            if ($shouldBegin && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (Exception $e) {
        throw $e;
    }
}

?>