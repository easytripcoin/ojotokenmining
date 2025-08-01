<?php
// user/package_action.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$action = $_GET['action'] ?? '';
$packageId = (int) ($_GET['id'] ?? 0);

if (!in_array($action, ['withdraw', 'remine'])) {
    redirectWithMessage('dashboard.php', 'Invalid action.', 'error');
}

$result = processWithdrawRemine($user_id, $packageId, $action);

redirectWithMessage('dashboard.php', $result['message'], $result['success'] ? 'success' : 'error');