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

// Delete message
$result = $messageController->deleteMessage($message_id);

if ($result) {
    $_SESSION['success_message'] = 'Message deleted successfully.';
} else {
    $_SESSION['error_message'] = 'Failed to delete message.';
}

// Redirect back to messages
header('Location: messages.php');
exit();
?>