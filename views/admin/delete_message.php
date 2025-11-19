<?php
require_once '../../config/config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../controllers/message_controller.php';

$messageController = new MessageController();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid message ID.';
    header('Location: messages.php');
    exit();
}

$ok = $messageController->deleteMessage($id);
$_SESSION['flash_message'] = $ok ? 'Message deleted successfully.' : 'Failed to delete message.';
$_SESSION['flash_type'] = $ok ? 'success' : 'error';
header('Location: messages.php');
exit();
?>