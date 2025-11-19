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

// Get message details
$message = $messageController->getMessageById($message_id);

if (!$message) {
    $_SESSION['error_message'] = 'Message not found.';
    header('Location: messages.php');
    exit();
}

// Mark message as read if it's unread
if (!$message['is_read']) {
    $messageController->markAsRead($message_id);
    $message['is_read'] = true;
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply_message = trim($_POST['reply_message'] ?? '');
    
    if (!empty($reply_message)) {
        $reply_subject = 'Re: ' . $message['subject'];
        
        $data = [
            'sender_type' => 'Admin',
            'sender_id' => $current_user['admin_id'],
            'recipient_type' => $message['sender_type'],
            'recipient_id' => $message['sender_id'],
            'subject' => $reply_subject,
            'message' => $reply_message
        ];
        
        $result = $messageController->createMessage($data);
        
        if ($result) {
            $_SESSION['success_message'] = 'Reply sent successfully!';
            header('Location: view_message.php?id=' . $message_id);
            exit();
        } else {
            $reply_error = 'Failed to send reply. Please try again.';
        }
    } else {
        $reply_error = 'Reply message cannot be empty.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">View Message</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="messages.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Messages
                        </a>
                        <a href="compose_message.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Compose New
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($reply_error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($reply_error); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Message Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($message['subject']); ?></h5>
                                    <span class="badge bg-<?php echo $message['is_read'] ? 'success' : 'warning'; ?>">
                                        <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>From:</strong>
                                        <span class="badge bg-<?php echo $message['sender_type'] === 'Admin' ? 'primary' : 'info'; ?> ms-2">
                                            <?php echo $message['sender_type']; ?>
                                        </span>
                                        <br><?php echo htmlspecialchars($message['sender_name']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>To:</strong>
                                        <span class="badge bg-<?php echo $message['recipient_type'] === 'Admin' ? 'primary' : 'info'; ?> ms-2">
                                            <?php echo $message['recipient_type']; ?>
                                        </span>
                                        <br><?php echo htmlspecialchars($message['recipient_name']); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <strong>Date:</strong> <?php echo date('F d, Y \a\t g:i A', strtotime($message['created_at'])); ?>
                                </div>
                                <hr>
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Reply Form -->
                        <?php if ($message['sender_type'] === 'Member'): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Reply to this Message</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="reply_message" class="form-label">Your Reply</label>
                                            <textarea class="form-control" id="reply_message" name="reply_message" rows="6" 
                                                      placeholder="Type your reply here..." required></textarea>
                                        </div>
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="submit" name="reply" class="btn btn-primary">
                                                <i class="fas fa-reply"></i> Send Reply
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <!-- Message Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($message['sender_type'] === 'Member'): ?>
                                        <a href="compose_message.php?reply_to=<?php echo $message_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-reply"></i> Reply
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$message['is_read']): ?>
                                        <a href="mark_read.php?id=<?php echo $message_id; ?>" class="btn btn-outline-success">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="delete_message.php?id=<?php echo $message_id; ?>" class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to delete this message?')">
                                        <i class="fas fa-trash"></i> Delete Message
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Message Info -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Message Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Message ID:</strong></td>
                                        <td><?php echo $message['message_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $message['is_read'] ? 'success' : 'warning'; ?>">
                                                <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($message['created_at'])); ?></td>
                                    </tr>
                                    <?php if ($message['read_at']): ?>
                                        <tr>
                                            <td><strong>Read:</strong></td>
                                            <td><?php echo date('M d, Y', strtotime($message['read_at'])); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>