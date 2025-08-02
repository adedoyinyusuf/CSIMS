<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';

// Create auth controller instance
$auth = new AuthController();

// Process logout
$auth->logout();

// Redirect to login page
$session->setFlash('success', 'You have been successfully logged out.');
header("Location: ../../index.php");
exit();