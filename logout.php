<?php
// Simple logout - destroy session and redirect
session_start();

// Log the logout for security
if (isset($_SESSION['username'])) {
    error_log("User logout: " . $_SESSION['username'] . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Destroy all session data
session_unset();
session_destroy();

// Prevent browser caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Set a success message in a new session for the login page
session_start();
$_SESSION['success_message'] = 'You have been successfully logged out.';

// Redirect to login page
header("Location: index.php");
exit();
?>