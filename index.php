<?php
// Index Page - Main Entry Point
// index.php

require_once 'config/config.php';
require_once 'config/session.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect based on user role
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
} else {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit;
}
?>