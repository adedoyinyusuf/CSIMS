<?php
session_start();
require_once '../config/auth_check.php';
require_once '../../controllers/notification_controller.php';

// Initialize notification controller
$notificationController = new NotificationController();

// Get notification ID from URL
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$notification_id) {
    $_SESSION['flash_message'] = 'Invalid notification ID.';
    $_SESSION['flash_type'] = 'error';
    header('Location: notifications.php');
    exit;
}

// Get notification details
$notification = $notificationController->getNotificationById($notification_id);

if (!$notification) {
    $_SESSION['flash_message'] = 'Notification not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: notifications.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                if ($notificationController->markAsRead($notification_id)) {
                    $_SESSION['flash_message'] = 'Notification marked as read successfully.';
                    $_SESSION['flash_type'] = 'success';
                    $notification['is_read'] = 1; // Update local data
                } else {
                    $_SESSION['flash_message'] = 'Failed to mark notification as read.';
                    $_SESSION['flash_type'] = 'error';
                }
                break;
                
            case 'delete':
                if ($notificationController->deleteNotification($notification_id)) {
                    $_SESSION['flash_message'] = 'Notification deleted successfully.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: notifications.php');
                    exit;
                } else {
                    $_SESSION['flash_message'] = 'Failed to delete notification.';
                    $_SESSION['flash_type'] = 'error';
                }
                break;
        }
    }
}

$page_title = 'View Notification';
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">View Notification</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="notifications.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Notifications
                        </a>
                        <a href="edit_notification.php?id=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php if (!$notification['is_read']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_read">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <?php require_once '../includes/flash_messages.php'; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Notification Content -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Notification Details</h6>
                            <?php if (!$notification['is_read']): ?>
                                <span class="badge bg-warning">Unread</span>
                            <?php else: ?>
                                <span class="badge bg-success">Read</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="notification-content">
                                <h3 class="mb-3"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                
                                <div class="notification-meta mb-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong>Type:</strong> 
                                                <span class="badge bg-info"><?php echo htmlspecialchars($notification['notification_type']); ?></span>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Recipient:</strong> 
                                                <?php 
                                                if ($notification['recipient_type'] === 'All') {
                                                    echo '<span class="badge bg-primary">All Users</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($notification['recipient_type']) . '</span>';
                                                    if ($notification['recipient_id']) {
                                                        echo ' (ID: ' . $notification['recipient_id'] . ')';
                                                    }
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong>Created By:</strong> 
                                                <?php echo htmlspecialchars($notification['created_by_name'] ?? 'System'); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Created Date:</strong> 
                                                <?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="notification-message">
                                    <h5>Message:</h5>
                                    <div class="message-content p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Notification Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit_notification.php?id=<?php echo $notification['notification_id']; ?>" 
                                   class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Notification
                                </a>
                                
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="mark_read">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print Notification
                                </button>
                                
                                <hr>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this notification? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-trash"></i> Delete Notification
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Info -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Notification Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td><?php echo $notification['notification_id']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?php echo htmlspecialchars($notification['notification_type']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php if ($notification['is_read']): ?>
                                            <span class="badge bg-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Recipient Type:</strong></td>
                                    <td><?php echo htmlspecialchars($notification['recipient_type']); ?></td>
                                </tr>
                                <?php if ($notification['recipient_id']): ?>
                                <tr>
                                    <td><strong>Recipient ID:</strong></td>
                                    <td><?php echo $notification['recipient_id']; ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td><?php echo date('M j, Y', strtotime($notification['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Time:</strong></td>
                                    <td><?php echo date('g:i A', strtotime($notification['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Related Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Related Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="create_notification.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-plus"></i> Create New Notification
                                </a>
                                <a href="notifications.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-list"></i> View All Notifications
                                </a>
                                <?php if ($notification['recipient_type'] !== 'All'): ?>
                                    <a href="create_notification.php?copy=<?php echo $notification['notification_id']; ?>" 
                                       class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-copy"></i> Duplicate Notification
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.notification-content h3 {
    color: #2c3e50;
    font-weight: 600;
}

.notification-meta {
    border-bottom: 1px solid #e3e6f0;
    padding-bottom: 1rem;
}

.message-content {
    font-size: 1.1rem;
    line-height: 1.6;
    min-height: 100px;
}

.table-borderless td {
    border: none;
    padding: 0.25rem 0;
}

.badge {
    font-size: 0.875em;
}

@media print {
    .btn-toolbar,
    .card:not(.card:first-child),
    .col-lg-4 {
        display: none !important;
    }
    
    .col-lg-8 {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background: none !important;
        border: none !important;
    }
}
</style>
