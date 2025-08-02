<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/contribution_controller.php';

$memberController = new MemberController();
$contributionController = new ContributionController();

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationType = $_POST['notification_type'] ?? '';
    $recipients = $_POST['recipients'] ?? 'all';
    $memberIds = $_POST['member_ids'] ?? [];
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $sendEmail = isset($_POST['send_email']);
    $sendSMS = isset($_POST['send_sms']);
    $scheduleDate = $_POST['schedule_date'] ?? '';
    $scheduleTime = $_POST['schedule_time'] ?? '';
    
    $errors = [];
    $success = false;
    
    // Validation
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    if (!$sendEmail && !$sendSMS) {
        $errors[] = "Please select at least one notification method";
    }
    
    if (empty($errors)) {
        try {
            // Get recipient members
            $targetMembers = [];
            
            if ($recipients === 'all') {
                $targetMembers = $memberController->getAllMembers();
            } elseif ($recipients === 'active') {
                $targetMembers = $memberController->getMembersByStatus('active');
            } elseif ($recipients === 'expired') {
                $targetMembers = $memberController->getMembersByStatus('expired');
            } elseif ($recipients === 'expiring') {
                // Members expiring in next 30 days
                $targetMembers = $memberController->getExpiringMembers(30);
            } elseif ($recipients === 'selected' && !empty($memberIds)) {
                $targetMembers = $memberController->getMembersByIds($memberIds);
            }
            
            if (empty($targetMembers)) {
                $errors[] = "No members found for the selected criteria";
            } else {
                // Process notifications
                $emailCount = 0;
                $smsCount = 0;
                $failedCount = 0;
                
                foreach ($targetMembers as $member) {
                    $memberSuccess = false;
                    
                    // Send Email
                    if ($sendEmail && !empty($member['email'])) {
                        if (sendEmailNotification($member, $subject, $message)) {
                            $emailCount++;
                            $memberSuccess = true;
                        }
                    }
                    
                    // Send SMS
                    if ($sendSMS && !empty($member['phone'])) {
                        if (sendSMSNotification($member, $message)) {
                            $smsCount++;
                            $memberSuccess = true;
                        }
                    }
                    
                    if (!$memberSuccess) {
                        $failedCount++;
                    }
                    
                    // Log notification
                    logNotification($member['id'], $notificationType, $subject, $message, $sendEmail, $sendSMS);
                }
                
                $success = true;
                $successMessage = "Notifications sent successfully! ";
                if ($emailCount > 0) $successMessage .= "Emails: $emailCount ";
                if ($smsCount > 0) $successMessage .= "SMS: $smsCount ";
                if ($failedCount > 0) $successMessage .= "Failed: $failedCount";
            }
            
        } catch (Exception $e) {
            $errors[] = "Error sending notifications: " . $e->getMessage();
        }
    }
}

// Get notification templates
$templates = getNotificationTemplates();

// Get member statistics for dashboard
$memberStats = $memberController->getMemberStatistics();

// Get recent notifications
$recentNotifications = getRecentNotifications(10);

// Helper functions
function sendEmailNotification($member, $subject, $message) {
    // Placeholder for email sending logic
    // In a real implementation, you would use PHPMailer or similar
    
    // Replace placeholders in message
    $personalizedMessage = str_replace(
        ['{{first_name}}', '{{last_name}}', '{{email}}', '{{membership_type}}', '{{expiry_date}}'],
        [$member['first_name'], $member['last_name'], $member['email'], $member['membership_type'], $member['membership_expiry']],
        $message
    );
    
    // Simulate email sending (replace with actual email service)
    return true; // Return true for success, false for failure
}

function sendSMSNotification($member, $message) {
    // Placeholder for SMS sending logic
    // In a real implementation, you would use Twilio, AWS SNS, or similar
    
    // Replace placeholders in message
    $personalizedMessage = str_replace(
        ['{{first_name}}', '{{last_name}}', '{{membership_type}}', '{{expiry_date}}'],
        [$member['first_name'], $member['last_name'], $member['membership_type'], $member['membership_expiry']],
        $message
    );
    
    // Simulate SMS sending (replace with actual SMS service)
    return true; // Return true for success, false for failure
}

function logNotification($memberId, $type, $subject, $message, $email, $sms) {
    // Log notification to database
    global $memberController;
    
    $sql = "INSERT INTO notification_logs (member_id, notification_type, subject, message, email_sent, sms_sent, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $memberController->conn->prepare($sql);
    $stmt->bind_param('isssii', $memberId, $type, $subject, $message, $email, $sms);
    $stmt->execute();
}

function getNotificationTemplates() {
    return [
        'welcome' => [
            'name' => 'Welcome Message',
            'subject' => 'Welcome to Our Organization!',
            'message' => 'Dear {{first_name}},\n\nWelcome to our organization! We are excited to have you as a member.\n\nYour membership details:\n- Type: {{membership_type}}\n- Expiry Date: {{expiry_date}}\n\nBest regards,\nThe Team'
        ],
        'renewal_reminder' => [
            'name' => 'Membership Renewal Reminder',
            'subject' => 'Membership Renewal Reminder',
            'message' => 'Dear {{first_name}},\n\nThis is a friendly reminder that your membership will expire on {{expiry_date}}.\n\nPlease renew your membership to continue enjoying our services.\n\nBest regards,\nThe Team'
        ],
        'expiry_notice' => [
            'name' => 'Membership Expired Notice',
            'subject' => 'Membership Expired',
            'message' => 'Dear {{first_name}},\n\nYour membership has expired on {{expiry_date}}.\n\nPlease contact us to renew your membership.\n\nBest regards,\nThe Team'
        ],
        'payment_confirmation' => [
            'name' => 'Payment Confirmation',
            'subject' => 'Payment Received - Thank You!',
            'message' => 'Dear {{first_name}},\n\nWe have received your payment. Thank you for your contribution!\n\nBest regards,\nThe Team'
        ],
        'general_announcement' => [
            'name' => 'General Announcement',
            'subject' => 'Important Announcement',
            'message' => 'Dear {{first_name}},\n\n[Your announcement message here]\n\nBest regards,\nThe Team'
        ]
    ];
}

function getRecentNotifications($limit = 10) {
    global $memberController;
    
    $sql = "SELECT nl.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
            FROM notification_logs nl 
            JOIN members m ON nl.member_id = m.id 
            ORDER BY nl.sent_at DESC 
            LIMIT ?";
    
    $stmt = $memberController->conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Notifications - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .notification-card {
            transition: transform 0.2s;
        }
        .notification-card:hover {
            transform: translateY(-2px);
        }
        .template-card {
            cursor: pointer;
            border: 2px solid transparent;
        }
        .template-card:hover {
            border-color: #0d6efd;
        }
        .template-card.selected {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
                        <li class="breadcrumb-item active">Notifications</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bell me-2"></i>Member Notifications</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduledNotificationsModal">
                                <i class="fas fa-clock me-1"></i>Scheduled
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#notificationHistoryModal">
                                <i class="fas fa-history me-1"></i>History
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
                                <h3 class="mb-0"><?= $memberStats['total'] ?></h3>
                                <p class="mb-0">Total Members</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $memberStats['active'] ?></h3>
                                <p class="mb-0">Active Members</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= count($memberController->getExpiringMembers(30)) ?></h3>
                                <p class="mb-0">Expiring Soon</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $memberStats['expired'] ?></h3>
                                <p class="mb-0">Expired Members</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Notification Form -->
                    <div class="col-lg-8">
                        <div class="card notification-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Send Notification</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <!-- Template Selection -->
                                    <div class="mb-4">
                                        <label class="form-label">Quick Templates</label>
                                        <div class="row">
                                            <?php foreach ($templates as $key => $template): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="card template-card" onclick="selectTemplate('<?= $key ?>')">
                                                        <div class="card-body py-2">
                                                            <small class="fw-bold"><?= htmlspecialchars($template['name']) ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Notification Type -->
                                    <div class="mb-3">
                                        <label for="notification_type" class="form-label">Notification Type</label>
                                        <select class="form-select" id="notification_type" name="notification_type" required>
                                            <option value="general">General Announcement</option>
                                            <option value="reminder">Reminder</option>
                                            <option value="welcome">Welcome Message</option>
                                            <option value="renewal">Renewal Notice</option>
                                            <option value="payment">Payment Confirmation</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Recipients -->
                                    <div class="mb-3">
                                        <label for="recipients" class="form-label">Recipients</label>
                                        <select class="form-select" id="recipients" name="recipients" onchange="toggleMemberSelection()">
                                            <option value="all">All Members</option>
                                            <option value="active">Active Members Only</option>
                                            <option value="expired">Expired Members Only</option>
                                            <option value="expiring">Members Expiring Soon (30 days)</option>
                                            <option value="selected">Selected Members</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Member Selection (hidden by default) -->
                                    <div class="mb-3 d-none" id="memberSelectionDiv">
                                        <label class="form-label">Select Members</label>
                                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($memberController->getAllMembers() as $member): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="member_ids[]" 
                                                           value="<?= $member['id'] ?>" id="member_<?= $member['id'] ?>">
                                                    <label class="form-check-label" for="member_<?= $member['id'] ?>">
                                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                                        <small class="text-muted">(<?= htmlspecialchars($member['email']) ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Subject -->
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject" required>
                                    </div>
                                    
                                    <!-- Message -->
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                                        <div class="form-text">
                                            Available placeholders: {{first_name}}, {{last_name}}, {{email}}, {{membership_type}}, {{expiry_date}}
                                        </div>
                                    </div>
                                    
                                    <!-- Notification Methods -->
                                    <div class="mb-3">
                                        <label class="form-label">Notification Methods</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="send_email" name="send_email" checked>
                                            <label class="form-check-label" for="send_email">
                                                <i class="fas fa-envelope me-1"></i>Email
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="send_sms" name="send_sms">
                                            <label class="form-check-label" for="send_sms">
                                                <i class="fas fa-sms me-1"></i>SMS
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Schedule Options -->
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="schedule_notification" onchange="toggleSchedule()">
                                            <label class="form-check-label" for="schedule_notification">
                                                Schedule for later
                                            </label>
                                        </div>
                                        <div class="row mt-2 d-none" id="scheduleDiv">
                                            <div class="col-md-6">
                                                <input type="date" class="form-control" name="schedule_date">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="time" class="form-control" name="schedule_time">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Submit Buttons -->
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>Send Notification
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewNotification()">
                                            <i class="fas fa-eye me-1"></i>Preview
                                        </button>
                                        <button type="reset" class="btn btn-outline-danger">
                                            <i class="fas fa-times me-1"></i>Clear
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Notifications -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Notifications</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentNotifications)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                        <p>No notifications sent yet</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentNotifications as $notification): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($notification['subject']) ?></h6>
                                                        <p class="mb-1 text-muted small">
                                                            To: <?= htmlspecialchars($notification['member_name']) ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <?= date('M d, Y H:i', strtotime($notification['sent_at'])) ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php if ($notification['email_sent']): ?>
                                                            <i class="fas fa-envelope text-primary" title="Email sent"></i>
                                                        <?php endif; ?>
                                                        <?php if ($notification['sms_sent']): ?>
                                                            <i class="fas fa-sms text-success" title="SMS sent"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notification Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Subject:</strong>
                        <div id="previewSubject" class="border rounded p-2 bg-light"></div>
                    </div>
                    <div class="mb-3">
                        <strong>Message:</strong>
                        <div id="previewMessage" class="border rounded p-2 bg-light" style="white-space: pre-wrap;"></div>
                    </div>
                    <div class="mb-3">
                        <strong>Recipients:</strong>
                        <div id="previewRecipients" class="border rounded p-2 bg-light"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const templates = <?= json_encode($templates) ?>;
        
        function selectTemplate(templateKey) {
            const template = templates[templateKey];
            document.getElementById('subject').value = template.subject;
            document.getElementById('message').value = template.message;
            
            // Visual feedback
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        function toggleMemberSelection() {
            const recipients = document.getElementById('recipients').value;
            const memberSelectionDiv = document.getElementById('memberSelectionDiv');
            
            if (recipients === 'selected') {
                memberSelectionDiv.classList.remove('d-none');
            } else {
                memberSelectionDiv.classList.add('d-none');
            }
        }
        
        function toggleSchedule() {
            const scheduleDiv = document.getElementById('scheduleDiv');
            const checkbox = document.getElementById('schedule_notification');
            
            if (checkbox.checked) {
                scheduleDiv.classList.remove('d-none');
            } else {
                scheduleDiv.classList.add('d-none');
            }
        }
        
        function previewNotification() {
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            const recipients = document.getElementById('recipients').value;
            
            document.getElementById('previewSubject').textContent = subject;
            document.getElementById('previewMessage').textContent = message;
            
            let recipientText = '';
            switch(recipients) {
                case 'all': recipientText = 'All Members'; break;
                case 'active': recipientText = 'Active Members Only'; break;
                case 'expired': recipientText = 'Expired Members Only'; break;
                case 'expiring': recipientText = 'Members Expiring Soon'; break;
                case 'selected': 
                    const selectedCount = document.querySelectorAll('input[name="member_ids[]"]:checked').length;
                    recipientText = `${selectedCount} Selected Members`;
                    break;
            }
            document.getElementById('previewRecipients').textContent = recipientText;
            
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }
    </script>
</body>
</html>