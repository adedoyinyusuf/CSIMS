<?php
// Authentication check for admin pages
require_once __DIR__ . '/config.php';

if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Check if session is still valid (optional: add session timeout)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    // Session expired after 1 hour
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?expired=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Optional: Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>