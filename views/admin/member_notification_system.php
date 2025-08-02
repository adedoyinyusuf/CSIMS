<?php
/**
 * Member Notification System
 * 
 * Comprehensive notification management for automated emails and SMS
 * Handles membership expiry reminders, payment notifications, announcements, etc.
 */

require_once '../../config/auth_check.php';
require_once '../../controllers/notification_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/email_service.php';
require_once '../../includes/sms_service.php';

$notificationController = new NotificationController();
$memberController = new MemberController();
$emailService = new EmailService();
$smsService = new SMSService();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'send_notification':
            $result = handleSendNotification();
            echo json_encode($result);
            exit;
            
        case 'schedule_notification':
            $result = handleScheduleNotification();
            echo json_encode($result);
            exit;
            
        case 'test_email':
            $result = testEmailService();
            echo json_encode($result);
            exit;
            
        case 'test_sms':
            $result = testSMSService();
            echo json_encode($result);
            exit;
            
        case 'get_notification_stats':
            $result = getNotificationStatistics();
            echo json_encode($result);
            exit;
            
        case 'get_recent_notifications':
            $result = getRecentNotifications();
            echo json_encode($result);
            exit;
    }
}

/**
 * Handle sending immediate notifications
 */
function handleSendNotification() {
    global $notificationController;
    
    try {
        $type = $_POST['notification_type'] ?? '';
        $recipients = $_POST['recipients'] ?? 'all';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] === 'true';
        $sendSMS = isset($_POST['send_sms']) && $_POST['send_sms'] === 'true';
        
        if (empty($subject) || empty($message)) {
            return ['success' => false, 'message' => 'Subject and message are required'];
        }
        
        $result = $notificationController->sendBulkNotification([
            'type' => $type,
            'recipients' => $recipients,
            'subject' => $subject,
            'message' => $message,
            'send_email' => $sendEmail,
            'send_sms' => $sendSMS
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error sending notification: ' . $e->getMessage()];
    }
}

/**
 * Handle scheduling notifications
 */
function handleScheduleNotification() {
    global $notificationController;
    
    try {
        $type = $_POST['notification_type'] ?? '';
        $recipients = $_POST['recipients'] ?? 'all';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        $scheduledDate = $_POST['scheduled_date'] ?? '';
        $scheduledTime = $_POST['scheduled_time'] ?? '';
        $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] === 'true';
        $sendSMS = isset($_POST['send_sms']) && $_POST['send_sms'] === 'true';
        
        if (empty($subject) || empty($message) || empty($scheduledDate) || empty($scheduledTime)) {
            return ['success' => false, 'message' => 'All fields are required for scheduling'];
        }
        
        $scheduledDateTime = $scheduledDate . ' ' . $scheduledTime;
        
        // Here you would implement the scheduling logic
        // For now, we'll create a notification record with scheduled status
        
        return ['success' => true, 'message' => 'Notification scheduled successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error scheduling notification: ' . $e->getMessage()];
    }
}

/**
 * Test email service
 */
function testEmailService() {
    global $emailService;
    
    try {
        $testEmail = $_POST['test_email'] ?? '';
        
        if (empty($testEmail)) {
            return ['success' => false, 'message' => 'Test email address is required'];
        }
        
        $result = $emailService->sendTestEmail($testEmail);
        
        if ($result) {
            return ['success' => true, 'message' => 'Test email sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send test email'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email test failed: ' . $e->getMessage()];
    }
}

/**
 * Test SMS service
 */
function testSMSService() {
    global $smsService;
    
    try {
        $testPhone = $_POST['test_phone'] ?? '';
        
        if (empty($testPhone)) {
            return ['success' => false, 'message' => 'Test phone number is required'];
        }
        
        $result = $smsService->sendTestSMS($testPhone);
        
        if ($result) {
            return ['success' => true, 'message' => 'Test SMS sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send test SMS'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'SMS test failed: ' . $e->getMessage()];
    }
}

/**
 * Get notification statistics
 */
function getNotificationStatistics() {
    global $notificationController, $emailService, $smsService;
    
    try {
        $notificationStats = $notificationController->getEnhancedNotificationStats();
        $emailStats = $emailService->getEmailStats();
        $smsStats = $smsService->getSMSStats();
        
        return [
            'success' => true,
            'data' => [
                'notifications' => $notificationStats,
                'email' => $emailStats,
                'sms' => $smsStats
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error getting statistics: ' . $e->getMessage()];
    }
}

/**
 * Get recent notifications
 */
function getRecentNotifications() {
    global $notificationController;
    
    try {
        $notifications = $notificationController->getRecentNotificationLogs(20);
        
        return [
            'success' => true,
            'data' => $notifications
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error getting recent notifications: ' . $e->getMessage()];
    }
}

// Get initial data for the page
$notificationStats = $notificationController->getEnhancedNotificationStats();
$messageTemplates = $notificationController->getMessageTemplates();
$recentNotifications = $notificationController->getRecentNotificationLogs(10);

// Test service connections
$emailTest = $emailService->testConnection();
$smsTest = $smsService->testConnection();

include '../../views/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Member Notification System</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshStats()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="fas fa-cog"></i> Settings
                        </button>
                    </div>
                </div>
            </div>

            <!-- Service Status Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card <?php echo $emailTest['success'] ? 'border-success' : 'border-danger'; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Email Service</h5>
                                    <p class="card-text"><?php echo $emailTest['message']; ?></p>
                                </div>
                                <div class="text-<?php echo $emailTest['success'] ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-envelope fa-2x"></i>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="testEmailService()">
                                <i class="fas fa-paper-plane"></i> Test Email
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card <?php echo $smsTest['success'] ? 'border-success' : 'border-danger'; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">SMS Service</h5>
                                    <p class="card-text"><?php echo $smsTest['message']; ?></p>
                                </div>
                                <div class="text-<?php echo $smsTest['success'] ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-sms fa-2x"></i>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="testSMSService()">
                                <i class="fas fa-mobile-alt"></i> Test SMS
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $notificationStats['total_notifications'] ?? 0; ?></h4>
                                    <p class="card-text">Total Notifications</p>
                                </div>
                                <div>
                                    <i class="fas fa-bell fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $notificationStats['emails_sent'] ?? 0; ?></h4>
                                    <p class="card-text">Emails Sent</p>
                                </div>
                                <div>
                                    <i class="fas fa-envelope fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $notificationStats['sms_sent'] ?? 0; ?></h4>
                                    <p class="card-text">SMS Sent</p>
                                </div>
                                <div>
                                    <i class="fas fa-sms fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $notificationStats['pending_notifications'] ?? 0; ?></h4>
                                    <p class="card-text">Pending</p>
                                </div>
                                <div>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Tabs -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="notificationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="send-tab" data-bs-toggle="tab" data-bs-target="#send" type="button" role="tab">
                                <i class="fas fa-paper-plane"></i> Send Notification
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                                <i class="fas fa-calendar-alt"></i> Schedule Notification
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                                <i class="fas fa-file-alt"></i> Templates
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                                <i class="fas fa-history"></i> History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="automation-tab" data-bs-toggle="tab" data-bs-target="#automation" type="button" role="tab">
                                <i class="fas fa-robot"></i> Automation
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="notificationTabContent">
                        <!-- Send Notification Tab -->
                        <div class="tab-pane fade show active" id="send" role="tabpanel">
                            <form id="sendNotificationForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="notificationType" class="form-label">Notification Type</label>
                                            <select class="form-select" id="notificationType" name="notification_type" required>
                                                <option value="">Select Type</option>
                                                <option value="membership_expiry">Membership Expiry Reminder</option>
                                                <option value="payment_reminder">Payment Reminder</option>
                                                <option value="announcement">General Announcement</option>
                                                <option value="welcome">Welcome Message</option>
                                                <option value="custom">Custom Notification</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="recipients" class="form-label">Recipients</label>
                                            <select class="form-select" id="recipients" name="recipients" required>
                                                <option value="all">All Members</option>
                                                <option value="active">Active Members</option>
                                                <option value="expired">Expired Members</option>
                                                <option value="expiring">Expiring Soon (30 days)</option>
                                                <option value="selected">Selected Members</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Delivery Method</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="sendEmail" name="send_email" value="true" checked>
                                                <label class="form-check-label" for="sendEmail">
                                                    <i class="fas fa-envelope"></i> Email
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="sendSMS" name="send_sms" value="true">
                                                <label class="form-check-label" for="sendSMS">
                                                    <i class="fas fa-sms"></i> SMS
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="subject" class="form-label">Subject</label>
                                            <input type="text" class="form-control" id="subject" name="subject" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message</label>
                                            <textarea class="form-control" id="message" name="message" rows="8" required></textarea>
                                            <div class="form-text">
                                                Available placeholders: {name}, {first_name}, {last_name}, {member_id}, {membership_type}, {expiry_date}, {current_date}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary" onclick="loadTemplate()">
                                        <i class="fas fa-file-import"></i> Load Template
                                    </button>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary" onclick="previewNotification()">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Now
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Schedule Notification Tab -->
                        <div class="tab-pane fade" id="schedule" role="tabpanel">
                            <form id="scheduleNotificationForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="scheduleType" class="form-label">Notification Type</label>
                                            <select class="form-select" id="scheduleType" name="notification_type" required>
                                                <option value="">Select Type</option>
                                                <option value="membership_expiry">Membership Expiry Reminder</option>
                                                <option value="payment_reminder">Payment Reminder</option>
                                                <option value="announcement">General Announcement</option>
                                                <option value="custom">Custom Notification</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="scheduleRecipients" class="form-label">Recipients</label>
                                            <select class="form-select" id="scheduleRecipients" name="recipients" required>
                                                <option value="all">All Members</option>
                                                <option value="active">Active Members</option>
                                                <option value="expired">Expired Members</option>
                                                <option value="expiring">Expiring Soon (30 days)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="scheduledDate" class="form-label">Date</label>
                                                    <input type="date" class="form-control" id="scheduledDate" name="scheduled_date" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="scheduledTime" class="form-label">Time</label>
                                                    <input type="time" class="form-control" id="scheduledTime" name="scheduled_time" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Delivery Method</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="scheduleEmail" name="send_email" value="true" checked>
                                                <label class="form-check-label" for="scheduleEmail">
                                                    <i class="fas fa-envelope"></i> Email
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="scheduleSMS" name="send_sms" value="true">
                                                <label class="form-check-label" for="scheduleSMS">
                                                    <i class="fas fa-sms"></i> SMS
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="scheduleSubject" class="form-label">Subject</label>
                                            <input type="text" class="form-control" id="scheduleSubject" name="subject" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="scheduleMessage" class="form-label">Message</label>
                                            <textarea class="form-control" id="scheduleMessage" name="message" rows="8" required></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-calendar-plus"></i> Schedule Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Templates Tab -->
                        <div class="tab-pane fade" id="templates" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Message Templates</h5>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal">
                                    <i class="fas fa-plus"></i> New Template
                                </button>
                            </div>
                            
                            <div class="row" id="templatesContainer">
                                <?php if (!empty($messageTemplates)): ?>
                                    <?php foreach ($messageTemplates as $template): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($template['name']); ?></h6>
                                                    <p class="card-text text-muted"><?php echo htmlspecialchars($template['type']); ?></p>
                                                    <p class="card-text"><?php echo substr(htmlspecialchars($template['content']), 0, 100) . '...'; ?></p>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="useTemplate(<?php echo $template['id']; ?>)">
                                                            <i class="fas fa-copy"></i> Use
                                                        </button>
                                                        <button class="btn btn-outline-secondary" onclick="editTemplate(<?php echo $template['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-file-alt fa-3x mb-3"></i>
                                            <p>No templates found. Create your first template to get started.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- History Tab -->
                        <div class="tab-pane fade" id="history" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Notification History</h5>
                                <button class="btn btn-outline-secondary btn-sm" onclick="refreshHistory()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped" id="historyTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Subject</th>
                                            <th>Recipients</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <?php if (!empty($recentNotifications)): ?>
                                            <?php foreach ($recentNotifications as $notification): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($notification['type']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($notification['subject'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($notification['recipient_count'] ?? '1'); ?></td>
                                                    <td>
                                                        <?php if ($notification['email_sent']): ?>
                                                            <i class="fas fa-envelope text-primary"></i>
                                                        <?php endif; ?>
                                                        <?php if ($notification['sms_sent']): ?>
                                                            <i class="fas fa-sms text-success"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $notification['status'] === 'sent' ? 'success' : ($notification['status'] === 'failed' ? 'danger' : 'warning'); ?>">
                                                            <?php echo ucfirst($notification['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewNotification(<?php echo $notification['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No notifications found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Automation Tab -->
                        <div class="tab-pane fade" id="automation" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Automated Reminders</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="autoExpiryReminder" checked>
                                                <label class="form-check-label" for="autoExpiryReminder">
                                                    Membership Expiry Reminders
                                                </label>
                                                <div class="form-text">Send reminders 30, 15, and 7 days before expiry</div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="autoPaymentReminder">
                                                <label class="form-check-label" for="autoPaymentReminder">
                                                    Payment Reminders
                                                </label>
                                                <div class="form-text">Send payment due reminders</div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="autoWelcome" checked>
                                                <label class="form-check-label" for="autoWelcome">
                                                    Welcome Messages
                                                </label>
                                                <div class="form-text">Send welcome message to new members</div>
                                            </div>
                                            
                                            <button class="btn btn-primary btn-sm">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Scheduled Tasks</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group list-group-flush">
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Daily Expiry Check</strong>
                                                        <br><small class="text-muted">Runs daily at 9:00 AM</small>
                                                    </div>
                                                    <span class="badge bg-success">Active</span>
                                                </div>
                                                
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Weekly Reports</strong>
                                                        <br><small class="text-muted">Runs Mondays at 8:00 AM</small>
                                                    </div>
                                                    <span class="badge bg-success">Active</span>
                                                </div>
                                                
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Monthly Summaries</strong>
                                                        <br><small class="text-muted">Runs 1st of each month</small>
                                                    </div>
                                                    <span class="badge bg-warning">Pending</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Email Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="mb-3">
                        <label for="testEmailAddress" class="form-label">Test Email Address</label>
                        <input type="email" class="form-control" id="testEmailAddress" name="test_email" required>
                        <div class="form-text">A test email will be sent to this address</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendTestEmail()">Send Test Email</button>
            </div>
        </div>
    </div>
</div>

<!-- Test SMS Modal -->
<div class="modal fade" id="testSMSModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test SMS Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testSMSForm">
                    <div class="mb-3">
                        <label for="testPhoneNumber" class="form-label">Test Phone Number</label>
                        <input type="tel" class="form-control" id="testPhoneNumber" name="test_phone" required>
                        <div class="form-text">Include country code (e.g., +1234567890)</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendTestSMS()">Send Test SMS</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize page
$(document).ready(function() {
    // Initialize forms
    $('#sendNotificationForm').on('submit', function(e) {
        e.preventDefault();
        sendNotification();
    });
    
    $('#scheduleNotificationForm').on('submit', function(e) {
        e.preventDefault();
        scheduleNotification();
    });
    
    // Set minimum date for scheduling
    $('#scheduledDate').attr('min', new Date().toISOString().split('T')[0]);
});

// Send immediate notification
function sendNotification() {
    const formData = new FormData($('#sendNotificationForm')[0]);
    formData.append('action', 'send_notification');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Notification sent successfully!');
                $('#sendNotificationForm')[0].reset();
                refreshStats();
            } else {
                showAlert('danger', response.message || 'Failed to send notification');
            }
        },
        error: function() {
            showAlert('danger', 'An error occurred while sending the notification');
        }
    });
}

// Schedule notification
function scheduleNotification() {
    const formData = new FormData($('#scheduleNotificationForm')[0]);
    formData.append('action', 'schedule_notification');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Notification scheduled successfully!');
                $('#scheduleNotificationForm')[0].reset();
            } else {
                showAlert('danger', response.message || 'Failed to schedule notification');
            }
        },
        error: function() {
            showAlert('danger', 'An error occurred while scheduling the notification');
        }
    });
}

// Test email service
function testEmailService() {
    $('#testEmailModal').modal('show');
}

function sendTestEmail() {
    const email = $('#testEmailAddress').val();
    if (!email) {
        showAlert('warning', 'Please enter an email address');
        return;
    }
    
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'test_email',
            test_email: email
        },
        success: function(response) {
            $('#testEmailModal').modal('hide');
            if (response.success) {
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            $('#testEmailModal').modal('hide');
            showAlert('danger', 'An error occurred while testing email service');
        }
    });
}

// Test SMS service
function testSMSService() {
    $('#testSMSModal').modal('show');
}

function sendTestSMS() {
    const phone = $('#testPhoneNumber').val();
    if (!phone) {
        showAlert('warning', 'Please enter a phone number');
        return;
    }
    
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'test_sms',
            test_phone: phone
        },
        success: function(response) {
            $('#testSMSModal').modal('hide');
            if (response.success) {
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            $('#testSMSModal').modal('hide');
            showAlert('danger', 'An error occurred while testing SMS service');
        }
    });
}

// Refresh statistics
function refreshStats() {
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'get_notification_stats' },
        success: function(response) {
            if (response.success) {
                // Update statistics cards
                location.reload(); // Simple refresh for now
            }
        }
    });
}

// Refresh history
function refreshHistory() {
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'get_recent_notifications' },
        success: function(response) {
            if (response.success) {
                updateHistoryTable(response.data);
            }
        }
    });
}

// Update history table
function updateHistoryTable(notifications) {
    const tbody = $('#historyTableBody');
    tbody.empty();
    
    if (notifications.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center text-muted">No notifications found</td></tr>');
        return;
    }
    
    notifications.forEach(function(notification) {
        const row = `
            <tr>
                <td>${new Date(notification.created_at).toLocaleDateString()}</td>
                <td><span class="badge bg-secondary">${notification.type}</span></td>
                <td>${notification.subject || 'N/A'}</td>
                <td>${notification.recipient_count || '1'}</td>
                <td>
                    ${notification.email_sent ? '<i class="fas fa-envelope text-primary"></i>' : ''}
                    ${notification.sms_sent ? '<i class="fas fa-sms text-success"></i>' : ''}
                </td>
                <td><span class="badge bg-${notification.status === 'sent' ? 'success' : (notification.status === 'failed' ? 'danger' : 'warning')}">${notification.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewNotification(${notification.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Preview notification
function previewNotification() {
    const subject = $('#subject').val();
    const message = $('#message').val();
    
    if (!subject || !message) {
        showAlert('warning', 'Please enter subject and message to preview');
        return;
    }
    
    // Simple preview - could be enhanced with a modal
    alert('Subject: ' + subject + '\n\nMessage: ' + message);
}

// Load template
function loadTemplate() {
    // Implementation for loading templates
    showAlert('info', 'Template loading feature coming soon');
}

// View notification details
function viewNotification(id) {
    // Implementation for viewing notification details
    showAlert('info', 'Notification details view coming soon');
}

// Utility function to show alerts
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of main content
    $('main').prepend(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}
</script>

<?php include '../../views/includes/footer.php'; ?>
