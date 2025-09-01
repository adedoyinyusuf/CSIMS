<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/auth_controller.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    if (empty($email)) {
        $_SESSION['forgot_error'] = 'Please enter your email address.';
        header('Location: member_forgot_password.php');
        exit();
    }
    $memberController = new MemberController($conn);
    $member = $memberController->getMemberByEmail($email);
    if (!$member) {
        $_SESSION['forgot_error'] = 'No member found with that email address.';
        header('Location: member_forgot_password.php');
        exit();
    }
    $authController = new AuthController($conn);
    $result = $authController->requestPasswordReset($email, 'member');
    if ($result['success']) {
        $_SESSION['forgot_success'] = 'A password reset link has been sent to your email address.';
    } else {
        $_SESSION['forgot_error'] = $result['message'] ?? 'Failed to send password reset link.';
    }
    header('Location: member_forgot_password.php');
    exit();
} else {
    header('Location: member_forgot_password.php');
    exit();
}