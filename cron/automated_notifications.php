<?php
/**
 * Automated Notifications Cron Job
 * 
 * This script handles automated notification triggers:
 * - Membership expiry reminders (30, 15, 7 days before expiry)
 * - Payment due reminders
 * - Welcome messages for new members
 * - Monthly/weekly reports
 * 
 * Should be run daily via cron job
 */

// Include required files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/notification_controller.php';
require_once __DIR__ . '/../controllers/member_controller.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/sms_service.php';

// Load notification config only if constants aren't already defined
if (!defined('EMAIL_ENABLED')) {
    require_once __DIR__ . '/../config/notification_config.php';
}

// Initialize database connection
$database = Database::getInstance();
$db = $database->getConnection();

if (!$db) {
    die('Failed to connect to database');
}

// Initialize services
$notificationController = new NotificationController();
$memberController = new MemberController();
$emailService = new EmailService();
$smsService = new SMSService();

// Log file for cron execution
$logFile = __DIR__ . '/../logs/automated_notifications.log';

// Ensure log directory exists
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

/**
 * Log function
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry; // Also output to console
}

/**
 * Main execution function
 */
function runAutomatedNotifications() {
    logMessage('Starting automated notifications cron job');
    
    try {
        // Check membership expiry reminders
        checkMembershipExpiryReminders();
        
        // Check payment due reminders
        checkPaymentDueReminders();
        
        // Send welcome messages to new members
        sendWelcomeMessages();
        
        // Send weekly reports (if Monday)
        if (date('N') == 1) { // Monday
            sendWeeklyReports();
        }
        
        // Send monthly reports (if 1st of month)
        if (date('j') == 1) { // 1st day of month
            sendMonthlyReports();
        }
        
        logMessage('Automated notifications cron job completed successfully');
        
    } catch (Exception $e) {
        logMessage('Error in automated notifications: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * Check and send membership expiry reminders
 */
function checkMembershipExpiryReminders() {
    global $memberController, $notificationController;
    
    logMessage('Checking membership expiry reminders');
    
    // Get members expiring in 30, 15, and 7 days
    $reminderDays = [30, 15, 7];
    
    foreach ($reminderDays as $days) {
        try {
            $expiringMembers = getExpiringMembers($days);
            
            if (!empty($expiringMembers)) {
                logMessage("Found " . count($expiringMembers) . " members expiring in $days days");
                
                foreach ($expiringMembers as $member) {
                    // Check if reminder already sent for this period
                    if (!hasReminderBeenSent($member['member_id'], 'membership_expiry', $days)) {
                        sendMembershipExpiryReminder($member, $days);
                        markReminderSent($member['member_id'], 'membership_expiry', $days);
                    }
                }
            } else {
                logMessage("No members expiring in $days days");
            }
            
        } catch (Exception $e) {
            logMessage("Error checking $days day expiry reminders: " . $e->getMessage(), 'ERROR');
        }
    }
}

/**
 * Get members expiring in specified days
 */
function getExpiringMembers($days) {
    global $db;
    
    $targetDate = date('Y-m-d', strtotime("+$days days"));
    
    $stmt = $db->prepare("
        SELECT * 
        FROM members 
        WHERE DATE(expiry_date) = ? 
        AND status = 'Active'
        AND email IS NOT NULL
        ORDER BY first_name, last_name
    ");
    
    $stmt->bind_param('s', $targetDate);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check and send payment due reminders
 */
function checkPaymentDueReminders() {
    logMessage('Checking payment due reminders');
    
    try {
        $overdueMembers = getOverduePayments();
        
        if (!empty($overdueMembers)) {
            logMessage("Found " . count($overdueMembers) . " members with overdue payments");
            
            foreach ($overdueMembers as $member) {
                if (!hasReminderBeenSent($member['member_id'], 'payment_overdue', 0)) {
                    sendPaymentDueReminder($member);
                    markReminderSent($member['member_id'], 'payment_overdue', 0);
                }
            }
        } else {
            logMessage("No overdue payments found");
        }
        
    } catch (Exception $e) {
        logMessage("Error checking payment due reminders: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get members with overdue payments
 */
function getOverduePayments() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT * 
        FROM members 
        WHERE expiry_date < CURDATE() 
        AND status = 'Expired'
        AND email IS NOT NULL
        ORDER BY expiry_date ASC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Send payment due reminder
 */
function sendPaymentDueReminder($member) {
    global $emailService, $smsService;
    
    try {
        // Email reminder
        $emailSubject = "Payment Overdue - Immediate Action Required";
        $emailMessage = getPaymentDueEmailTemplate($member);
        
        if ($member['email']) {
            $emailSent = $emailService->send(
                $member['email'],
                $emailSubject,
                $emailMessage,
                $member['first_name'] . ' ' . $member['last_name']
            );
            
            if ($emailSent) {
                logMessage("Payment due email sent to {$member['first_name']} {$member['last_name']} ({$member['email']})");
            } else {
                logMessage("Failed to send payment due email to {$member['email']}", 'ERROR');
            }
        }
        
        // SMS reminder (if phone number available)
        if ($member['phone']) {
            $smsMessage = getPaymentDueSMSTemplate($member);
            $smsSent = $smsService->send($member['phone'], $smsMessage);
            
            if ($smsSent) {
                logMessage("Payment due SMS sent to {$member['first_name']} {$member['last_name']} ({$member['phone']})");
            } else {
                logMessage("Failed to send payment due SMS to {$member['phone']}", 'ERROR');
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending payment due reminder to member {$member['member_id']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send membership expiry reminder
 */
function sendMembershipExpiryReminder($member, $days) {
    global $emailService, $smsService;
    
    try {
        // Email reminder
        $emailSubject = "Membership Expiry Reminder - $days Days Remaining";
        $emailMessage = getMembershipExpiryEmailTemplate($member, $days);
        
        if ($member['email']) {
            $emailSent = $emailService->send(
                $member['email'],
                $emailSubject,
                $emailMessage,
                $member['first_name'] . ' ' . $member['last_name']
            );
            
            if ($emailSent) {
                logMessage("Expiry reminder email sent to {$member['first_name']} {$member['last_name']} ({$member['email']})");
            } else {
                logMessage("Failed to send expiry reminder email to {$member['email']}", 'ERROR');
            }
        }
        
        // SMS reminder (if phone number available)
        if ($member['phone']) {
            $smsMessage = getMembershipExpirySMSTemplate($member, $days);
            $smsSent = $smsService->send($member['phone'], $smsMessage);
            
            if ($smsSent) {
                logMessage("Expiry reminder SMS sent to {$member['first_name']} {$member['last_name']} ({$member['phone']})");
            } else {
                logMessage("Failed to send expiry reminder SMS to {$member['phone']}", 'ERROR');
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending expiry reminder to member {$member['member_id']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send welcome messages
 */
function sendWelcomeMessages() {
    logMessage('Checking for new members to send welcome messages');
    
    try {
        $newMembers = getNewMembers();
        
        if (!empty($newMembers)) {
            logMessage("Found " . count($newMembers) . " new members for welcome messages");
            
            foreach ($newMembers as $member) {
                if (!hasReminderBeenSent($member['member_id'], 'welcome_message', 0)) {
                    sendWelcomeMessage($member);
                    markReminderSent($member['member_id'], 'welcome_message', 0);
                }
            }
        } else {
            logMessage("No new members found for welcome messages");
        }
        
    } catch (Exception $e) {
        logMessage("Error sending welcome messages: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get new members (joined in last 24 hours)
 */
function getNewMembers() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT * FROM members 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND status = 'Active'
        AND email IS NOT NULL
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Send welcome message
 */
function sendWelcomeMessage($member) {
    global $emailService, $smsService;
    
    try {
        // Welcome email
        $emailSubject = "Welcome to CSIMS!";
        $emailMessage = getWelcomeEmailTemplate($member);
        
        if ($member['email']) {
            $emailSent = $emailService->send(
                $member['email'],
                $emailSubject,
                $emailMessage,
                $member['first_name'] . ' ' . $member['last_name']
            );
            
            if ($emailSent) {
                logMessage("Welcome email sent to {$member['first_name']} {$member['last_name']} ({$member['email']})");
            } else {
                logMessage("Failed to send welcome email to {$member['email']}", 'ERROR');
            }
        }
        
        // Welcome SMS (if phone number available)
        if ($member['phone']) {
            $smsMessage = getWelcomeSMSTemplate($member);
            $smsSent = $smsService->send($member['phone'], $smsMessage);
            
            if ($smsSent) {
                logMessage("Welcome SMS sent to {$member['first_name']} {$member['last_name']} ({$member['phone']})");
            } else {
                logMessage("Failed to send welcome SMS to {$member['phone']}", 'ERROR');
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending welcome message to member {$member['member_id']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get welcome email template
 */
function getWelcomeEmailTemplate($member) {
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Welcome to CSIMS!</h2>
        <p>Dear $memberName,</p>
        
        <p>Welcome to our cooperative society! We're excited to have you as a member.</p>
        
        <p>Your membership details:</p>
        <ul>
            <li>Member ID: {$member['member_id']}</li>
            <li>Join Date: " . date('F j, Y', strtotime($member['created_at'])) . "</li>
        </ul>
        
        <p>You can now access all member services and benefits.</p>
        
        <p>If you have any questions, please don't hesitate to contact us.</p>
        
        <p>Welcome aboard!<br>CSIMS Team</p>
    </body>
    </html>
    ";
}

/**
 * Get welcome SMS template
 */
function getWelcomeSMSTemplate($member) {
    $memberName = $member['first_name'];
    return "CSIMS: Welcome $memberName! Your membership is now active. Member ID: {$member['member_id']}. Contact us for any assistance.";
}

/**
 * Send weekly reports
 */
function sendWeeklyReports() {
    global $emailService;
    logMessage('Sending weekly reports');
    
    try {
        // Get admin emails
        $admins = getAdminEmails();
        
        if (!empty($admins)) {
            $reportData = generateWeeklyReportData();
            $reportEmail = generateWeeklyReportEmail($reportData);
            
            foreach ($admins as $admin) {
                $emailSent = $emailService->send(
                    $admin['email'],
                    'CSIMS Weekly Report - ' . date('F j, Y'),
                    $reportEmail,
                    $admin['name']
                );
                
                if ($emailSent) {
                    logMessage("Weekly report sent to {$admin['name']} ({$admin['email']})");
                }
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending weekly reports: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send monthly reports
 */
function sendMonthlyReports() {
    global $emailService;
    logMessage('Sending monthly reports');
    
    try {
        // Get admin emails
        $admins = getAdminEmails();
        
        if (!empty($admins)) {
            $reportData = generateMonthlyReportData();
            $reportEmail = generateMonthlyReportEmail($reportData);
            
            foreach ($admins as $admin) {
                $emailSent = $emailService->send(
                    $admin['email'],
                    'CSIMS Monthly Report - ' . date('F Y'),
                    $reportEmail,
                    $admin['name']
                );
                
                if ($emailSent) {
                    logMessage("Monthly report sent to {$admin['name']} ({$admin['email']})");
                }
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending monthly reports: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get admin emails
 */
function getAdminEmails() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT email, CONCAT(first_name, ' ', last_name) as name
        FROM admins 
        WHERE email IS NOT NULL
        AND status = 'Active'
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Generate weekly report data
 */
function generateWeeklyReportData() {
    global $db;
    
    $data = [];
    
    // New members this week
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['new_members'] = $result->fetch_assoc()['count'];
    
    // Expired members this week
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM members 
        WHERE expiry_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK) 
        AND expiry_date < NOW()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['expired_members'] = $result->fetch_assoc()['count'];
    
    // Total active members
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE status = 'Active'");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['active_members'] = $result->fetch_assoc()['count'];
    
    return $data;
}

/**
 * Generate monthly report data
 */
function generateMonthlyReportData() {
    global $db;
    
    $data = [];
    
    // New members this month
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['new_members'] = $result->fetch_assoc()['count'];
    
    // Expired members this month
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM members 
        WHERE expiry_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) 
        AND expiry_date < NOW()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['expired_members'] = $result->fetch_assoc()['count'];
    
    // Revenue this month (placeholder)
    $data['revenue'] = 0;
    
    return $data;
}

/**
 * Generate weekly report email
 */
function generateWeeklyReportEmail($data) {
    $weekStart = date('F j', strtotime('last monday'));
    $weekEnd = date('F j, Y');
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>CSIMS Weekly Report</h2>
        <p><strong>Period:</strong> $weekStart - $weekEnd</p>
        
        <h3>Summary:</h3>
        <ul>
            <li>New Members: {$data['new_members']}</li>
            <li>Expired Members: {$data['expired_members']}</li>
            <li>Total Active Members: {$data['active_members']}</li>
        </ul>
        
        <p>This is an automated weekly report from CSIMS.</p>
    </body>
    </html>
    ";
}

/**
 * Generate monthly report email
 */
function generateMonthlyReportEmail($data) {
    $month = date('F Y');
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>CSIMS Monthly Report</h2>
        <p><strong>Month:</strong> $month</p>
        
        <h3>Summary:</h3>
        <ul>
            <li>New Members: {$data['new_members']}</li>
            <li>Expired Members: {$data['expired_members']}</li>
            <li>Revenue: $" . number_format($data['revenue'], 2) . "</li>
        </ul>
        
        <p>This is an automated monthly report from CSIMS.</p>
    </body>
    </html>
    ";
}

/**
 * Check if reminder has been sent
 */
function hasReminderBeenSent($memberId, $type, $days) {
    global $db;
    
    // Create notification_log table if it doesn't exist
    $createTable = "
        CREATE TABLE IF NOT EXISTS notification_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            reminder_days INT DEFAULT 0,
            status ENUM('sent', 'failed') DEFAULT 'sent',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_member_type_date (member_id, notification_type, created_at)
        )
    ";
    $db->query($createTable);
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notification_log 
        WHERE member_id = ? 
        AND notification_type = ? 
        AND reminder_days = ?
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->bind_param('isi', $memberId, $type, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

/**
 * Mark reminder as sent
 */
function markReminderSent($memberId, $type, $days) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO notification_log 
        (member_id, notification_type, reminder_days, status, created_at) 
        VALUES (?, ?, ?, 'sent', NOW())
    ");
    
    $stmt->bind_param('isi', $memberId, $type, $days);
    $stmt->execute();
}

/**
 * Get membership expiry email template
 */
function getMembershipExpiryEmailTemplate($member, $days) {
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    $expiryDate = date('F j, Y', strtotime($member['expiry_date']));
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Membership Expiry Reminder</h2>
        <p>Dear $memberName,</p>
        
        <p>Your membership will expire in <strong>$days days</strong> on <strong>$expiryDate</strong>.</p>
        
        <p>Please renew your membership to continue enjoying our services.</p>
        
        <h3>Membership Details:</h3>
        <ul>
            <li>Member ID: {$member['member_id']}</li>
            <li>Current Status: {$member['status']}</li>
            <li>Expiry Date: $expiryDate</li>
        </ul>
        
        <p>Contact us for renewal assistance.</p>
        
        <p>Thank you!<br>CSIMS Team</p>
    </body>
    </html>
    ";
}

/**
 * Get membership expiry SMS template
 */
function getMembershipExpirySMSTemplate($member, $days) {
    $firstName = $member['first_name'];
    $expiryDate = date('M j, Y', strtotime($member['expiry_date']));
    return "CSIMS Alert: Hi $firstName, your membership expires in $days days ($expiryDate). Please renew to continue services.";
}

/**
 * Get payment due email template
 */
function getPaymentDueEmailTemplate($member) {
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    $expiryDate = date('F j, Y', strtotime($member['expiry_date']));
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Payment Overdue Notice</h2>
        <p>Dear $memberName,</p>
        
        <p><strong>URGENT:</strong> Your membership payment is overdue.</p>
        
        <p>Your membership expired on <strong>$expiryDate</strong>.</p>
        
        <p>Please make your payment immediately to restore your membership.</p>
        
        <h3>Account Details:</h3>
        <ul>
            <li>Member ID: {$member['member_id']}</li>
            <li>Current Status: {$member['status']}</li>
            <li>Expired Date: $expiryDate</li>
        </ul>
        
        <p>Contact us immediately to resolve this issue.</p>
        
        <p>CSIMS Team</p>
    </body>
    </html>
    ";
}

/**
 * Get payment due SMS template
 */
function getPaymentDueSMSTemplate($member) {
    $firstName = $member['first_name'];
    return "CSIMS: Hi $firstName, your membership payment is overdue. Contact us immediately to avoid service interruption.";
}

/**
 * Get welcome email template
 */
// ... existing code up to line 750 ...

// Run the automated notifications
runAutomatedNotifications();

?>