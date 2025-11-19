<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/message_controller.php';

$memberController = new MemberController();
$messageController = new MessageController();

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errors = [];
    $success = false;
    
    if ($action === 'send_message') {
        $recipientType = $_POST['recipient_type'] ?? '';
        $recipientIds = $_POST['recipient_ids'] ?? [];
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        $priority = $_POST['priority'] ?? 'normal';
        $messageType = $_POST['message_type'] ?? 'general';
        $attachments = $_FILES['attachments'] ?? [];
        
        // Validation
        if (empty($subject)) {
            $errors[] = "Subject is required";
        }
        if (empty($message)) {
            $errors[] = "Message is required";
        }
        if ($recipientType === 'selected' && empty($recipientIds)) {
            $errors[] = "Please select at least one recipient";
        }
        
        if (empty($errors)) {
            try {
                // Get recipients based on type
                $recipients = getRecipients($recipientType, $recipientIds, $messageController);
                
                if (empty($recipients)) {
                    $errors[] = "No recipients found";
                } else {
                    // Send messages using bulk method
                    $senderData = ['sender_id' => $_SESSION['user_id']];
                    $messageData = [
                        'subject' => $subject,
                        'message' => $message
                    ];
                    
                    $result = $messageController->sendBulkMessages($senderData, $recipients, $messageData);
                    $sentCount = $result['success'];
                    
                    if ($result['failed'] > 0) {
                        $errors = array_merge($errors, $result['errors']);
                    }
                    
                    if ($sentCount > 0) {
                        $success = true;
                        $successMessage = "Message sent successfully to {$sentCount} member(s)";
                    } else {
                        $errors[] = "Failed to send messages";
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error sending message: " . $e->getMessage();
            }
        }
    } elseif ($action === 'create_announcement') {
        $title = $_POST['announcement_title'] ?? '';
        $content = $_POST['announcement_content'] ?? '';
        $priority = $_POST['announcement_priority'] ?? 'normal';
        $targetAudience = $_POST['target_audience'] ?? 'all';
        $expiryDate = $_POST['expiry_date'] ?? null;
        
        if (empty($title) || empty($content)) {
            $errors[] = "Title and content are required for announcements";
        } else {
            try {
                $announcementData = [
                    'title' => $title,
                    'content' => $content,
                    'priority' => $priority,
                    'target_audience' => $targetAudience,
                    'expiry_date' => $expiryDate,
                    'created_by' => $_SESSION['user_id'],
                    'status' => 'active'
                ];
                
                if (createAnnouncement($announcementData)) {
                    $success = true;
                    $successMessage = "Announcement created successfully";
                } else {
                    $errors[] = "Failed to create announcement";
                }
            } catch (Exception $e) {
                $errors[] = "Error creating announcement: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_announcement') {
        $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
        $title = $_POST['announcement_title'] ?? '';
        $content = $_POST['announcement_content'] ?? '';
        $priority = $_POST['announcement_priority'] ?? 'normal';
        $targetAudience = $_POST['target_audience'] ?? 'all';
        $expiryDate = $_POST['expiry_date'] ?? null;
        $status = $_POST['status'] ?? 'active';

        if ($announcementId <= 0) {
            $errors[] = 'Invalid announcement ID';
        }
        if (empty($title) || empty($content)) {
            $errors[] = 'Title and content are required for announcements';
        }

        if (empty($errors)) {
            try {
                $updateData = [
                    'title' => $title,
                    'content' => $content,
                    'priority' => $priority,
                    'target_audience' => $targetAudience,
                    'expiry_date' => $expiryDate,
                    'status' => $status,
                ];
                if ($messageController->updateAnnouncement($announcementId, $updateData)) {
                    $success = true;
                    $successMessage = 'Announcement updated successfully';
                } else {
                    $errors[] = 'Failed to update announcement';
                }
            } catch (Exception $e) {
                $errors[] = 'Error updating announcement: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'archive_announcement') {
        $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
        if ($announcementId <= 0) {
            $errors[] = 'Invalid announcement ID';
        } else {
            try {
                if ($messageController->setAnnouncementStatus($announcementId, 'archived')) {
                    $success = true;
                    $successMessage = 'Announcement archived';
                } else {
                    $errors[] = 'Failed to archive announcement';
                }
            } catch (Exception $e) {
                $errors[] = 'Error archiving announcement: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_announcement') {
        $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
        if ($announcementId <= 0) {
            $errors[] = 'Invalid announcement ID';
        } else {
            try {
                if ($messageController->deleteAnnouncement($announcementId)) {
                    $success = true;
                    $successMessage = 'Announcement deleted';
                } else {
                    $errors[] = 'Failed to delete announcement';
                }
            } catch (Exception $e) {
                $errors[] = 'Error deleting announcement: ' . $e->getMessage();
            }
        }
    }
}

// Get communication statistics
$communicationStats = getCommunicationStatistics($messageController);

// Get recent messages
$recentMessages = $messageController->getRecentMessages(10);

// Get active announcements (placeholder for now)
$activeAnnouncements = getActiveAnnouncements(5);

// Get member groups for targeting
$memberGroups = getMemberGroups($messageController);

// Get all members for selection
$allMembers = $messageController->getAllActiveMembers();

// Helper functions
function getRecipients($type, $ids, $messageController) {
    switch ($type) {
        case 'all':
            return $messageController->getAllActiveMembers();
        case 'active':
            return $messageController->getMembersByStatus('Active');
        case 'expired':
            return $messageController->getMembersByStatus('Expired');
        case 'expiring':
            return $messageController->getExpiringMembers(30);
        case 'selected':
            return $messageController->getMembersByIds($ids);
        default:
            return [];
    }
}

function getCommunicationStatistics($messageController) {
    global $memberController;

    $stats = [
        'total_messages' => 0,
        'messages_today' => 0,
        'messages_this_week' => 0,
        'messages_this_month' => 0,
        'active_announcements' => 0,
        'total_members' => 0
    ];

    // Get message counts using controller
    $commStats = $messageController->getCommunicationStatistics();
    $stats['total_messages'] = $commStats['total_messages'] ?? 0;
    $stats['messages_today'] = $commStats['messages_today'] ?? 0;
    $stats['messages_this_week'] = $commStats['messages_this_week'] ?? 0;
    $stats['messages_this_month'] = $commStats['messages_this_month'] ?? 0;

    // Get announcement count via controller
    $activeAnnouncements = $messageController->getActiveAnnouncements(1);
    $stats['active_announcements'] = is_array($activeAnnouncements) ? count($activeAnnouncements) : 0;

    // Get total members
    $memberStats = $memberController->getMemberStatistics();
    $stats['total_members'] = $memberStats['total_members'] ?? ($memberStats['total'] ?? 0);

    return $stats;
}

function createAnnouncement($data) {
    global $messageController;
    return $messageController->createAnnouncement($data);
}

function getActiveAnnouncements($limit) {
    global $messageController;
    return $messageController->getActiveAnnouncements($limit);
}

function getMemberGroups($messageController) {
    $stats = [];

    // Use MemberController statistics
    $memberController = new MemberController();
    $memberStats = $memberController->getMemberStatistics();
    $stats['total'] = $memberStats['total_members'] ?? 0;
    $stats['active'] = $memberStats['active_members'] ?? 0;
    $stats['expired'] = $memberStats['expired_members'] ?? 0;

    // Expiring members
    $expiringMembers = $messageController->getExpiringMembers(30);
    $stats['expiring'] = count($expiringMembers);

    return [
        'all' => ['name' => 'All Members', 'count' => $stats['total']],
        'active' => ['name' => 'Active Members', 'count' => $stats['active']],
        'expired' => ['name' => 'Expired Members', 'count' => $stats['expired']],
        'expiring' => ['name' => 'Expiring Soon (30 days)', 'count' => $stats['expiring']]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Communication Portal - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css" rel="stylesheet">
    <style>
        .communication-card {
            transition: transform 0.2s;
        }
        .communication-card:hover {
            transform: translateY(-2px);
        }
        .stats-card {
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--secondary-500) 100%);
            color: var(--text-white);
        }
        .message-item {
            transition: border-color 0.3s;
        }
        .message-item:hover {
            border-left-color: var(--primary-500);
        }
        .recipient-count {
            background: var(--bg-muted);
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
                        <li class="breadcrumb-item active">Communication Portal</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-comments me-2"></i>Member Communication Portal</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeMessageModal">
                                <i class="fas fa-paper-plane me-1"></i>Send Message
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                <i class="fas fa-bullhorn me-1"></i>Create Announcement
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#templatesModal">
                                <i class="fas fa-file-alt me-1"></i>Templates
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                                <i class="fas fa-chart-bar me-1"></i>Analytics
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $communicationStats['total_members'] ?></h3>
                                <p class="mb-0">Total Members</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $communicationStats['total_messages'] ?></h3>
                                <p class="mb-0">Total Messages</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $communicationStats['messages_today'] ?></h3>
                                <p class="mb-0">Messages Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $communicationStats['active_announcements'] ?></h3>
                                <p class="mb-0">Active Announcements</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Quick Actions -->
                    <div class="col-lg-4">
                        <div class="card communication-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary" onclick="quickMessage('all')">
                                        <i class="fas fa-users me-1"></i>Message All Members
                                        <span class="recipient-count"><?= $memberGroups['all']['count'] ?></span>
                                    </button>
                                    <button class="btn btn-outline-success" onclick="quickMessage('active')">
                                        <i class="fas fa-user-check me-1"></i>Message Active Members
                                        <span class="recipient-count"><?= $memberGroups['active']['count'] ?></span>
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="quickMessage('expiring')">
                                        <i class="fas fa-clock me-1"></i>Message Expiring Soon
                                        <span class="recipient-count"><?= $memberGroups['expiring']['count'] ?></span>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="quickMessage('expired')">
                                        <i class="fas fa-user-times me-1"></i>Message Expired Members
                                        <span class="recipient-count"><?= $memberGroups['expired']['count'] ?></span>
                                    </button>
                                </div>
                                
                                <hr>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                        <i class="fas fa-bullhorn me-1"></i>Create Announcement
                                    </button>
                                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scheduledMessagesModal">
                                        <i class="fas fa-calendar me-1"></i>Schedule Message
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Member Groups -->
                        <div class="card communication-card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Member Groups</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($memberGroups as $key => $group): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= $group['name'] ?></span>
                                        <div>
                                            <span class="badge bg-secondary"><?= $group['count'] ?></span>
                                            <button class="btn btn-sm btn-outline-primary ms-1" onclick="quickMessage('<?= $key ?>')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Messages -->
                    <div class="col-lg-4">
                        <div class="card communication-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Messages</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentMessages)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No messages sent yet</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentMessages as $message): ?>
                                            <div class="list-group-item px-0 message-item <?php echo ($message['priority'] === 'high' ? 'border-left-error' : ($message['priority'] === 'medium' ? 'border-left-warning' : 'border-left-success')); ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($message['subject']) ?></h6>
                                                        <p class="mb-1 text-muted small">
                                                            To: <?= htmlspecialchars($message['recipient_name'] ?? 'Unknown') ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <?= date('M d, Y H:i', strtotime($message['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?= $message['priority'] === 'high' ? 'danger' : ($message['priority'] === 'medium' ? 'warning' : 'success') ?>">
                                                            <?= ucfirst($message['priority']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Announcements -->
                    <div class="col-lg-4">
                        <div class="card communication-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Active Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($activeAnnouncements)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-bullhorn fa-2x mb-2"></i>
                                        <p>No active announcements</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($activeAnnouncements as $announcement): ?>
                                        <div class="card announcement-card border-left-info mb-3">
                                            <div class="card-body py-2">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($announcement['title']) ?></h6>
                                                <p class="card-text small text-muted mb-1">
                                                    <?= substr(htmlspecialchars($announcement['content']), 0, 100) ?>...
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <?= date('M d, Y', strtotime($announcement['created_at'])) ?>
                                                    </small>
                                                    <span class="badge bg-<?= $announcement['priority'] === 'high' ? 'danger' : ($announcement['priority'] === 'medium' ? 'warning' : 'info') ?>">
                                                        <?= ucfirst($announcement['priority']) ?>
                                                    </span>
                                                    <div class="btn-group btn-group-sm ms-2" role="group">
                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editAnnouncementModal"
                                                                data-announcement-id="<?= (int)$announcement['announcement_id'] ?>"
                                                                data-title="<?= htmlspecialchars($announcement['title']) ?>"
                                                                data-content='<?= htmlspecialchars($announcement['content'], ENT_QUOTES) ?>'
                                                                data-priority="<?= htmlspecialchars($announcement['priority']) ?>"
                                                                data-audience="<?= htmlspecialchars($announcement['target_audience']) ?>"
                                                                data-expiry="<?= htmlspecialchars($announcement['expiry_date'] ?? '') ?>'
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="archive_announcement">
                                                            <input type="hidden" name="announcement_id" value="<?= (int)$announcement['announcement_id'] ?>">
                                                            <button type="submit" class="btn btn-outline-warning" title="Archive">
                                                                <i class="fas fa-archive"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                                            <input type="hidden" name="action" value="delete_announcement">
                                                            <input type="hidden" name="announcement_id" value="<?= (int)$announcement['announcement_id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Communication Analytics -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Communication Analytics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <canvas id="messageChart" width="400" height="200"></canvas>
                                    </div>
                                    <div class="col-md-6">
                                        <canvas id="priorityChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compose Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="recipient_type" class="form-label">Recipients</label>
                                <select class="form-select" id="recipient_type" name="recipient_type" onchange="toggleRecipientSelection()">
                                    <option value="all">All Members</option>
                                    <option value="active">Active Members</option>
                                    <option value="expired">Expired Members</option>
                                    <option value="expiring">Expiring Soon</option>
                                    <option value="selected">Selected Members</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-3 d-none" id="memberSelectionDiv">
                            <label class="form-label">Select Members</label>
                            <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($allMembers as $member): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recipient_ids[]" 
                                               value="<?= $member['id'] ?>" id="member_<?= $member['id'] ?>">
                                        <label class="form-check-label" for="member_<?= $member['id'] ?>">
                                            <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                            <small class="text-muted">(<?= htmlspecialchars($member['email']) ?>)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mt-3">
                            <label for="message_type" class="form-label">Message Type</label>
                            <select class="form-select" name="message_type">
                                <option value="general">General</option>
                                <option value="reminder">Reminder</option>
                                <option value="announcement">Announcement</option>
                                <option value="welcome">Welcome</option>
                                <option value="renewal">Renewal Notice</option>
                            </select>
                        </div>
                        
                        <div class="mt-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <label for="attachments" class="form-label">Attachments (Optional)</label>
                            <input type="file" class="form-control" name="attachments[]" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Create Announcement Modal -->
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_announcement">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <label for="announcement_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="announcement_title" name="announcement_title" required>
                            </div>
                            <div class="col-md-4">
                                <label for="announcement_priority" class="form-label">Priority</label>
                                <select class="form-select" name="announcement_priority">
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="target_audience" class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience">
                                    <option value="all">All Members</option>
                                    <option value="active">Active Members</option>
                                    <option value="expired">Expired Members</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                                <input type="datetime-local" class="form-control" name="expiry_date">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="announcement_content" class="form-label">Content</label>
                            <textarea class="form-control" id="announcement_content" name="announcement_content" rows="8" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-bullhorn me-1"></i>Create Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_announcement">
                    <input type="hidden" id="edit_announcement_id" name="announcement_id" value="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <label for="edit_announcement_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="edit_announcement_title" name="announcement_title" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_announcement_priority" class="form-label">Priority</label>
                                <select class="form-select" id="edit_announcement_priority" name="announcement_priority">
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="edit_target_audience" class="form-label">Target Audience</label>
                                <select class="form-select" id="edit_target_audience" name="target_audience">
                                    <option value="all">All Members</option>
                                    <option value="active">Active Members</option>
                                    <option value="expired">Expired Members</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_expiry_date" class="form-label">Expiry Date (Optional)</label>
                                <input type="datetime-local" class="form-control" id="edit_expiry_date" name="expiry_date">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="edit_announcement_content" class="form-label">Content</label>
                            <textarea class="form-control" id="edit_announcement_content" name="announcement_content" rows="8" required></textarea>
                        </div>

                        <input type="hidden" id="edit_status" name="status" value="active">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script>
        function toggleRecipientSelection() {
            const recipientType = document.getElementById('recipient_type').value;
            const memberSelectionDiv = document.getElementById('memberSelectionDiv');
            
            if (recipientType === 'selected') {
                memberSelectionDiv.classList.remove('d-none');
            } else {
                memberSelectionDiv.classList.add('d-none');
            }
        }
        
        function quickMessage(type) {
            document.getElementById('recipient_type').value = type;
            toggleRecipientSelection();
            new bootstrap.Modal(document.getElementById('composeMessageModal')).show();
        }
        
        // Initialize rich text editor
        $(document).ready(function() {
            $('#message').summernote({
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
            
            $('#announcement_content').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
        });

        // Initialize rich text editor for Edit Announcement
        $(document).ready(function() {
            $('#edit_announcement_content').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
        });

        // Populate Edit Announcement modal with selected item data
        const editModalEl = document.getElementById('editAnnouncementModal');
        if (editModalEl) {
            editModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;
                const id = button.getAttribute('data-announcement-id');
                const title = button.getAttribute('data-title') || '';
                const content = button.getAttribute('data-content') || '';
                const priority = button.getAttribute('data-priority') || 'normal';
                const audience = button.getAttribute('data-audience') || 'all';
                const expiry = button.getAttribute('data-expiry') || '';

                document.getElementById('edit_announcement_id').value = id;
                document.getElementById('edit_announcement_title').value = title;
                document.getElementById('edit_announcement_priority').value = priority;
                document.getElementById('edit_target_audience').value = audience;
                document.getElementById('edit_expiry_date').value = expiry;
                $('#edit_announcement_content').summernote('code', content);
            });
        }

        // Charts
        const messageChartCtx = document.getElementById('messageChart').getContext('2d');
        new Chart(messageChartCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Messages Sent',
                    data: [12, 19, 3, 5, 2, 3],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Messages Sent Over Time'
                    }
                }
            }
        });
        
        const priorityChartCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityChartCtx, {
            type: 'doughnut',
            data: {
                labels: ['Normal', 'Medium', 'High'],
                datasets: [{
                    data: [<?= $communicationStats['total_messages'] * 0.7 ?>, <?= $communicationStats['total_messages'] * 0.2 ?>, <?= $communicationStats['total_messages'] * 0.1 ?>],
                    backgroundColor: [successColor, warningColor, errorColor]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Message Priority Distribution'
                    }
                }
            }
        });
    </script>
</body>
</html>
