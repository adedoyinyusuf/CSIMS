<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/notification_controller.php';
require_once '../../includes/session.php';
$session = Session::getInstance();

// Auth check
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access member notifications');
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Current user
$current_user = $auth->getCurrentUser();

// Validate member context
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $session->setFlash('error', 'Member ID is required');
    header('Location: ' . BASE_URL . '/views/admin/members.php');
    exit();
}
$member_id = (int)$_GET['id'];

// Controllers
$memberController = new MemberController();
$notificationController = new NotificationController();

// Member details
$member = $memberController->getMemberById($member_id);
if (!$member) {
    $session->setFlash('error', 'Member not found');
    header('Location: ' . BASE_URL . '/views/admin/members.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'mark_read':
            $nid = (int)($_POST['notification_id'] ?? 0);
            if ($nid > 0 && $notificationController->markAsRead($nid)) {
                $session->setFlash('success', 'Notification marked as read.');
            } else {
                $session->setFlash('error', 'Failed to mark notification as read.');
            }
            header('Location: ' . BASE_URL . '/views/admin/member_notifications.php?id=' . $member_id);
            exit();
        case 'delete':
            $nid = (int)($_POST['notification_id'] ?? 0);
            if ($nid > 0 && $notificationController->deleteNotification($nid)) {
                $session->setFlash('success', 'Notification deleted.');
            } else {
                $session->setFlash('error', 'Failed to delete notification.');
            }
            header('Location: ' . BASE_URL . '/views/admin/member_notifications.php?id=' . $member_id);
            exit();
    }
}

// Listing controls
$limit = isset($_GET['limit']) ? max(5, (int)$_GET['limit']) : 20;
$recentNotifications = $notificationController->getMemberNotifications($member_id, $limit);
$recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
$unreadCount = (int)$notificationController->getMemberUnreadCount($member_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Notifications - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Member Notifications</h1>
                    <div>
                        <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-user"></i> Back to Member
                        </a>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if ($session->hasFlash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('success'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($session->hasFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('error'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Member Summary -->
                <div class="card mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                            <p class="mb-0 text-muted">Member ID: <?php echo (int)$member['member_id']; ?> • Unread: <?php echo $unreadCount; ?></p>
                        </div>
                        <div>
                            <div class="btn-group">
                                <a href="<?php echo BASE_URL; ?>/views/admin/create_notification.php?recipient_type=Member&recipient_id=<?php echo $member_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> New Notification
                                </a>
                                <a href="<?php echo BASE_URL; ?>/views/admin/member_notifications.php?id=<?php echo $member_id; ?>&limit=50" class="btn btn-outline-secondary">
                                    Show More
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Notifications (<?php echo count($recentNotifications); ?>)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bell fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted">No notifications for this member.</p>
                                <a href="<?php echo BASE_URL; ?>/views/admin/create_notification.php?recipient_type=Member&recipient_id=<?php echo $member_id; ?>" class="btn btn-primary">Create Notification</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentNotifications as $n): ?>
                                            <?php 
                                                $isUnread = (int)($n['is_read'] ?? 0) === 0;
                                            ?>
                                            <tr class="<?php echo $isUnread ? 'table-warning' : ''; ?>">
                                                <td><?php echo (int)($n['notification_id'] ?? 0); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($n['title'] ?? ($n['notification_type'] ?? 'Notification')); ?></strong>
                                                    <?php if ($isUnread): ?>
                                                        <span class="badge bg-warning ms-1">New</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($n['message'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($n['message'], 0, 100)) . (strlen($n['message']) > 100 ? '...' : ''); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo htmlspecialchars($n['notification_type'] ?? 'General'); ?></span></td>
                                                <td>
                                                    <?php if ($isUnread): ?>
                                                        <span class="badge bg-warning">Unread</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Read</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($n['created_by_name'] ?? 'System'); ?></td>
                                                <td><?php echo !empty($n['created_at']) ? date('M j, Y g:i A', strtotime($n['created_at'])) : ''; ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="<?php echo BASE_URL; ?>/views/admin/view_notification.php?id=<?php echo (int)($n['notification_id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($isUnread): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="mark_read">
                                                                <input type="hidden" name="notification_id" value="<?php echo (int)($n['notification_id'] ?? 0); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as Read">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this notification?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="notification_id" value="<?php echo (int)($n['notification_id'] ?? 0); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>

    <style>
    .table-warning { background-color: rgba(255, 193, 7, 0.1) !important; }
    .card { box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,0.15) !important; border: 1px solid #e3e6f0 !important; }
    .card-header { background-color: #f8f9fc !important; border-bottom: 1px solid #e3e6f0 !important; }
    </style>
</body>
</html>