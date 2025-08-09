<?php
require_once '../../config/config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/message_controller.php';

$auth = new AuthController();
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    header('Location: ../auth/login.php');
    exit();
}

$messageController = new MessageController();

// Get message ID from URL
$message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$message_id) {
    $_SESSION['error_message'] = 'Invalid message ID.';
    header('Location: messages.php');
    exit();
}

// Mark message as read
$result = $messageController->markAsRead($message_id);

if ($result) {
    $_SESSION['success_message'] = 'Message marked as read successfully.';
} else {
    $_SESSION['error_message'] = 'Failed to mark message as read.';
}

// Redirect back to messages or the specific message
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'messages.php';
header('Location: ' . $redirect);
exit();
?>