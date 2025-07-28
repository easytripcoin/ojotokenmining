<?php
// System Configuration
// config/config.php

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Site configuration
define('SITE_NAME', 'OjoTokenMining');
define('SITE_URL', 'http://ojotokenmining.test');
define('BASE_PATH', __DIR__ . '/../');

// Package configuration
define('PACKAGES', [
    1 => ['name' => 'Starter Plan', 'price' => 20.00],
    2 => ['name' => 'Bronze Plan', 'price' => 100.00],
    3 => ['name' => 'Silver Plan', 'price' => 500.00],
    4 => ['name' => 'Gold Plan', 'price' => 1000.00],
    5 => ['name' => 'Platinum Plan', 'price' => 2000.00],
    6 => ['name' => 'Diamond Plan', 'price' => 10000.00]
]);

// Bonus configuration
define('MONTHLY_BONUS_PERCENTAGE', 50); // 50% of package price
define('BONUS_MONTHS', 3); // Number of months to receive bonus
define('REFERRAL_BONUSES', [
    2 => 10, // Level 2: 10%
    3 => 1,  // Level 3: 1%
    4 => 1,  // Level 4: 1%
    5 => 1   // Level 5: 1%
]);

// Security configuration
define('PASSWORD_MIN_LENGTH', 6);
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 50);
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Pagination
define('RECORDS_PER_PAGE', 20);

// File upload limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Email configuration (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@ojotokenmining.com');
define('FROM_NAME', 'OjoTokenMining');

// Currency settings
define('DEFAULT_CURRENCY', 'USDT');
define('CURRENCY_SYMBOL', '$');
define('DECIMAL_PLACES', 2);

// Admin settings
define('ADMIN_EMAIL', 'admin@ojotokenmining.com');

/**
 * Get package information by ID
 * @param int $package_id Package ID
 * @return array|null Package information
 */
function getPackageInfo($package_id)
{
    $packages = PACKAGES;
    return isset($packages[$package_id]) ? $packages[$package_id] : null;
}

/**
 * Format currency amount
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function formatCurrency($amount)
{
    return CURRENCY_SYMBOL . number_format($amount, DECIMAL_PLACES);
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info')
{
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 * @return array|null Message array with 'message' and 'type' keys
 */
function getFlashMessage()
{
    if (isset($_SESSION['message'])) {
        $message = [
            'message' => $_SESSION['message'],
            'type' => $_SESSION['message_type'] ?? 'info'
        ];
        unset($_SESSION['message'], $_SESSION['message_type']);
        return $message;
    }
    return null;
}

/**
 * Log system events
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 */
function logEvent($message, $level = 'info')
{
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(BASE_PATH . 'logs/system.log', $log_message, FILE_APPEND | LOCK_EX);
}

// Create logs directory if it doesn't exist
$logs_dir = BASE_PATH . 'logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}
?>