<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';

// Create auth controller instance
$auth = new AuthController();

// Process logout
$auth->logout();

// Add cache-busting headers to prevent browser caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect to login page with cache-busting parameter
$session->setFlash('success', 'You have been successfully logged out.');
header("Location: ../../index.php?t=" . time());
exit();