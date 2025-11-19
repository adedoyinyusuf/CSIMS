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

$ok = $messageController->markAsRead($id);
$_SESSION['flash_message'] = $ok ? 'Marked as read.' : 'Failed to mark as read.';
$_SESSION['flash_type'] = $ok ? 'success' : 'error';
header('Location: messages.php');
exit();
?>