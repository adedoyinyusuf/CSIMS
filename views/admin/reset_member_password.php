<?php
// Debug: Log that the script is being accessed
error_log('DEBUG: reset_member_password.php accessed at ' . date('Y-m-d H:i:s'));

require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/email_service.php';
require_once '../../config/security.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit();
}

// Verify CSRF token
if (!CSRFProtection::validateRequest()) {
    $_SESSION['error'] = 'Invalid security token. Please try again.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Validate required fields
if (!isset($_POST['member_id']) || empty($_POST['member_id'])) {
    $_SESSION['error'] = 'Member ID is required.';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

$member_id = (int)$_POST['member_id'];
$generate_password = isset($_POST['generate_password']) && $_POST['generate_password'] === '1';
$send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';

// Debug: Check if we're in generate password mode
if ($generate_password) {
    error_log('DEBUG: Generate password mode activated');
} else {
    error_log('DEBUG: Manual password mode activated');
}

try {
    $memberController = new MemberController();
    
    // Get member details for email
    $member = $memberController->getMemberById($member_id);
    if (!$member) {
        $_SESSION['error'] = 'Member not found.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $new_password = '';
    
    if ($generate_password) {
        // Generate a secure random password
        $new_password = generateSecurePassword();
    } else {
        // Use admin-provided password
        if (empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
            $_SESSION['error'] = 'Password and confirmation are required.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['error'] = 'Passwords do not match.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
        if (strlen($_POST['new_password']) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters long.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
        $new_password = $_POST['new_password'];
    }
    
    // Reset the password
    $result = $memberController->adminResetPassword($member_id, $new_password);
    
    if ($result) {
        $email_sent = false;
        
        // Send email if requested
        if ($send_email && !empty($member['email'])) {
            $emailService = new EmailService();
            $subject = 'Password Reset - ' . APP_NAME;
            $message = "Dear {$member['first_name']} {$member['last_name']},\n\n";
            $message .= "Your password has been reset by an administrator.\n\n";
            $message .= "Your new login credentials are:\n";
            $message .= "Username: {$member['username']}\n";
            $message .= "Password: {$new_password}\n\n";
            $message .= "Please log in and change your password immediately.\n\n";
            $message .= "Best regards,\n" . APP_NAME . " Team";
            
            try {
                $emailService->send($member['email'], $subject, $message, $member['first_name'] . ' ' . $member['last_name']);
                $email_sent = true;
            } catch (Exception $e) {
                // Email failed, but password reset was successful
                error_log('Failed to send password reset email: ' . $e->getMessage());
            }
        }
        
        if ($generate_password) {
            // Store data in session for the success page
            $_SESSION['reset_password_data'] = [
                'member_id' => $member_id,
                'new_password' => $new_password,
                'member_name' => $member['first_name'] . ' ' . $member['last_name'],
                'email_sent' => $email_sent
            ];
            
            // Debug: Log the session data
            error_log('Password reset data stored in session: ' . print_r($_SESSION['reset_password_data'], true));
            
            // Redirect to success page to display the password
            header('Location: ' . BASE_URL . '/views/admin/password_reset_success.php');
            exit();
        } else {
            $_SESSION['success'] = 'Password reset successfully!';
            
            if ($send_email && !empty($member['email'])) {
                if ($email_sent) {
                    $_SESSION['success'] .= ' Password has been sent to the member\'s email address.';
                } else {
                    $_SESSION['success'] .= ' However, failed to send email notification.';
                }
            }
        }
    } else {
        $_SESSION['error'] = 'Failed to reset password. Please try again.';
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
}

// Redirect back to member view
header('Location: ' . BASE_URL . '/views/admin/view_member.php?id=' . $member_id);
exit();

/**
 * Generate a secure random password
 */
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*';
    
    $all = $uppercase . $lowercase . $numbers . $special;
    
    $password = '';
    
    // Ensure at least one character from each set
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest randomly
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}
?>