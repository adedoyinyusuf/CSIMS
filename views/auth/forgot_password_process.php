<?php
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    
    // Validate input
    if (empty($email) || !Utilities::validateEmail($email)) {
        $session->setFlash('error', 'Please enter a valid email address');
        header("Location: " . BASE_URL . "auth/forgot_password.php");
        exit();
    }
    
    // Process password reset request
    $auth = new AuthController();
    $result = $auth->requestPasswordReset($email);
    
    // Always show success message even if email doesn't exist (security best practice)
    $session->setFlash('success', 'If your email is registered, you will receive a password reset link shortly.');
    
    // In a real application, an email would be sent with the reset link
    // For development purposes, we'll store the token in the session
    if (isset($result['token']) && isset($result['admin_id'])) {
        $session->set('reset_token', $result['token']);
        $session->set('reset_admin_id', $result['admin_id']);
        
        // Redirect to reset password page (for development only)
        header("Location: " . BASE_URL . "auth/reset_password.php?token=" . $result['token'] . "&id=" . $result['admin_id']);
        exit();
    } else {
        // Redirect back to forgot password page
        header("Location: " . BASE_URL . "auth/forgot_password.php");
        exit();
    }
} else {
    // If not POST request, redirect to forgot password page
    header("Location: " . BASE_URL . "auth/forgot_password.php");
    exit();
}
?>