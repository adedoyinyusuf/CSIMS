<?php
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $admin_id = isset($_POST['admin_id']) ? $_POST['admin_id'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate input
    if (empty($token) || empty($admin_id) || empty($new_password) || empty($confirm_password)) {
        $session->setFlash('error', 'All fields are required');
        header("Location: " . BASE_URL . "auth/reset_password.php?token=$token&id=$admin_id");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $session->setFlash('error', 'Passwords do not match');
        header("Location: " . BASE_URL . "auth/reset_password.php?token=$token&id=$admin_id");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $session->setFlash('error', 'Password must be at least 8 characters long');
        header("Location: " . BASE_URL . "auth/reset_password.php?token=$token&id=$admin_id");
        exit();
    }
    
    // Process password reset
    $auth = new AuthController();
    $result = $auth->resetPassword($admin_id, $token, $new_password, $confirm_password);
    
    if ($result['success']) {
        $session->setFlash('success', 'Your password has been reset successfully. You can now login with your new password.');
        header("Location: " . BASE_URL . "index.php");
        exit();
    } else {
        $session->setFlash('error', $result['message']);
        header("Location: " . BASE_URL . "auth/reset_password.php?token=$token&id=$admin_id");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: " . BASE_URL . "index.php");
    exit();
}
?>