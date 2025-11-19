<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../controllers/auth_controller.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $member_id = isset($_POST['member_id']) ? $_POST['member_id'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($token) || empty($member_id) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['reset_error'] = 'All fields are required.';
        header('Location: member_reset_password.php?token=' . urlencode($token) . '&id=' . urlencode($member_id));
        exit();
    }
    if ($new_password !== $confirm_password) {
        $_SESSION['reset_error'] = 'Passwords do not match.';
        header('Location: member_reset_password.php?token=' . urlencode($token) . '&id=' . urlencode($member_id));
        exit();
    }
    if (strlen($new_password) < 8) {
        $_SESSION['reset_error'] = 'Password must be at least 8 characters long.';
        header('Location: member_reset_password.php?token=' . urlencode($token) . '&id=' . urlencode($member_id));
        exit();
    }
    $authController = new AuthController($conn);
    $result = $authController->resetPassword($member_id, $token, $new_password, $confirm_password, 'member');
    if ($result['success']) {
        $_SESSION['reset_success'] = 'Your password has been reset successfully. You can now log in.';
        header('Location: member_reset_password.php?token=' . urlencode($token) . '&id=' . urlencode($member_id));
        exit();
    } else {
        $_SESSION['reset_error'] = $result['message'] ?? 'Failed to reset password.';
        header('Location: member_reset_password.php?token=' . urlencode($token) . '&id=' . urlencode($member_id));
        exit();
    }
} else {
    header('Location: member_login.php');
    exit();
}