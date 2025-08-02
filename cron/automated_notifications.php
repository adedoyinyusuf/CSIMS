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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/notification_controller.php';
require_once __DIR__ . '/../controllers/member_controller.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/sms_service.php';

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
                    if (!hasReminderBeenSent($member['id'], 'membership_expiry', $days)) {
                        sendMembershipExpiryReminder($member, $days);
                        markReminderSent($member['id'], 'membership_expiry', $days);
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
    global $pdo;
    
    $targetDate = date('Y-m-d', strtotime("+$days days"));
    
    $stmt = $pdo->prepare("
        SELECT m.*, ms.expiry_date 
        FROM members m 
        JOIN memberships ms ON m.id = ms.member_id 
        WHERE DATE(ms.expiry_date) = ? 
        AND m.status = 'Active'
        AND m.email IS NOT NULL
        ORDER BY m.first_name, m.last_name
    ");
    
    $stmt->execute([$targetDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        logMessage("Error sending expiry reminder to member {$member['id']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get membership expiry email template
 */
function getMembershipExpiryEmailTemplate($member, $days) {
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    $expiryDate = date('F j, Y', strtotime($member['expiry_date']));
    
    $template = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .alert { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>CSIMS - Membership Expiry Reminder</h1>
            </div>
            <div class='content'>
                <h2>Dear $memberName,</h2>
                
                <div class='alert'>
                    <strong>Important Notice:</strong> Your membership will expire in <strong>$days days</strong> on <strong>$expiryDate</strong>.
                </div>
                
                <p>To ensure uninterrupted access to our services, please renew your membership before the expiry date.</p>
                
                <h3>Membership Details:</h3>
                <ul>
                    <li><strong>Member ID:</strong> {$member['member_id']}</li>
                    <li><strong>Current Status:</strong> {$member['status']}</li>
                    <li><strong>Expiry Date:</strong> $expiryDate</li>
                </ul>
                
                <h3>How to Renew:</h3>
                <ol>
                    <li>Visit our office during business hours</li>
                    <li>Contact us at [PHONE_NUMBER] or [EMAIL]</li>
                    <li>Use our online renewal system (if available)</li>
                </ol>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='#' class='btn'>Renew Membership</a>
                </p>
                
                <p>If you have any questions or need assistance, please don't hesitate to contact us.</p>
                
                <p>Thank you for being a valued member!</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from CSIMS. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " CSIMS. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $template;
}

/**
 * Get membership expiry SMS template
 */
function getMembershipExpirySMSTemplate($member, $days) {
    $memberName = $member['first_name'];
    $expiryDate = date('M j, Y', strtotime($member['expiry_date']));
    
    return "CSIMS Alert: Hi $memberName, your membership expires in $days days ($expiryDate). Please renew to continue services. Contact us for assistance.";
}

/**
 * Check payment due reminders
 */
function checkPaymentDueReminders() {
    logMessage('Checking payment due reminders');
    
    try {
        // Get members with overdue payments
        $overdueMembers = getOverduePayments();
        
        if (!empty($overdueMembers)) {
            logMessage("Found " . count($overdueMembers) . " members with overdue payments");
            
            foreach ($overdueMembers as $member) {
                if (!hasReminderBeenSent($member['id'], 'payment_overdue', 0)) {
                    sendPaymentDueReminder($member);
                    markReminderSent($member['id'], 'payment_overdue', 0);
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
    global $pdo;
    
    // This is a simplified query - adjust based on your payment system
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.*, ms.expiry_date
        FROM members m 
        JOIN memberships ms ON m.id = ms.member_id 
        WHERE ms.expiry_date < CURDATE()
        AND m.status = 'Expired'
        AND m.email IS NOT NULL
        ORDER BY ms.expiry_date DESC
        LIMIT 50
    ");
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send payment due reminder
 */
function sendPaymentDueReminder($member) {
    global $emailService, $smsService;
    
    try {
        $memberName = $member['first_name'] . ' ' . $member['last_name'];
        
        // Email reminder
        $emailSubject = "Payment Overdue - Immediate Action Required";
        $emailMessage = getPaymentDueEmailTemplate($member);
        
        if ($member['email']) {
            $emailSent = $emailService->send(
                $member['email'],
                $emailSubject,
                $emailMessage,
                $memberName
            );
            
            if ($emailSent) {
                logMessage("Payment due email sent to $memberName ({$member['email']})");
            }
        }
        
        // SMS reminder
        if ($member['phone']) {
            $smsMessage = "CSIMS: Hi {$member['first_name']}, your membership payment is overdue. Please contact us immediately to avoid service interruption.";
            $smsSent = $smsService->send($member['phone'], $smsMessage);
            
            if ($smsSent) {
                logMessage("Payment due SMS sent to $memberName ({$member['phone']})");
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending payment due reminder to member {$member['id']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get payment due email template
 */
function getPaymentDueEmailTemplate($member) {
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    $expiredDate = date('F j, Y', strtotime($member['expiry_date']));
    
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .alert { background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>CSIMS - Payment Overdue Notice</h1>
            </div>
            <div class='content'>
                <h2>Dear $memberName,</h2>
                
                <div class='alert'>
                    <strong>URGENT:</strong> Your membership payment is overdue. Your membership expired on <strong>$expiredDate</strong>.
                </div>
                
                <p>Your membership services have been suspended due to non-payment. To restore your membership and avoid further complications, please make your payment immediately.</p>
                
                <h3>Account Details:</h3>
                <ul>
                    <li><strong>Member ID:</strong> {$member['member_id']}</li>
                    <li><strong>Current Status:</strong> {$member['status']}</li>
                    <li><strong>Expired Date:</strong> $expiredDate</li>
                </ul>
                
                <h3>Payment Options:</h3>
                <ol>
                    <li>Visit our office immediately</li>
                    <li>Call us at [PHONE_NUMBER]</li>
                    <li>Online payment (if available)</li>
                </ol>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='#' class='btn'>Make Payment Now</a>
                </p>
                
                <p><strong>Note:</strong> Continued non-payment may result in additional fees and permanent suspension of membership privileges.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from CSIMS. Please contact us immediately.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send welcome messages to new members
 */
function sendWelcomeMessages() {
    logMessage('Checking for new members to send welcome messages');
    
    try {
        $newMembers = getNewMembers();
        
        if (!empty($newMembers)) {
            logMessage("Found " . count($newMembers) . " new members for welcome messages");
            
            foreach ($newMembers as $member) {
                if (!hasReminderBeenSent($member['id'], 'welcome', 0)) {
                    sendWelcomeMessage($member);
                    markReminderSent($member['id'], 'welcome', 0);
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
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM members m 
        WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND m.email IS NOT NULL
        ORDER BY m.created_at DESC
    ");
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send welcome message
 */
function sendWelcomeMessage($member) {
    global $emailService, $smsService;
    
    try {
        $memberName = $member['first_name'] . ' ' . $member['last_name'];
        
        // Welcome email
        $emailSubject = "Welcome to CSIMS - Your Membership is Active!";
        $emailMessage = getWelcomeEmailTemplate($member);
        
        if ($member['email']) {
            $emailSent = $emailService->send(
                $member['email'],
                $emailSubject,
                $emailMessage,
                $memberName
            );
            
            if ($emailSent) {
                logMessage("Welcome email sent to $memberName ({$member['email']})");
            }
        }
        
        // Welcome SMS
        if ($member['phone']) {
            $smsMessage = "Welcome to CSIMS, {$member['first_name']}! Your membership is now active. We're excited to have you as part of our community!";
            $smsSent = $smsService->send($member['phone'], $smsMessage);
            
            if ($smsSent) {
                logMessage("Welcome SMS sent to $memberName ({$member['phone']})");
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error sending welcome message to member {$member['id']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get welcome email template
 */
function getWelcomeEmailTemplate($member) {
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to CSIMS!</h1>
            </div>
            <div class='content'>
                <h2>Dear $memberName,</h2>
                
                <div class='highlight'>
                    <strong>Congratulations!</strong> Your membership application has been approved and your account is now active.
                </div>
                
                <p>We're thrilled to welcome you to our community! As a member, you now have access to all our services and benefits.</p>
                
                <h3>Your Membership Details:</h3>
                <ul>
                    <li><strong>Member ID:</strong> {$member['member_id']}</li>
                    <li><strong>Status:</strong> Active</li>
                    <li><strong>Join Date:</strong> " . date('F j, Y', strtotime($member['created_at'])) . "</li>
                </ul>
                
                <h3>What's Next?</h3>
                <ol>
                    <li>Familiarize yourself with our services</li>
                    <li>Contact us if you have any questions</li>
                    <li>Keep your contact information updated</li>
                    <li>Enjoy your membership benefits!</li>
                </ol>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='#' class='btn'>Access Member Portal</a>
                </p>
                
                <p>If you have any questions or need assistance, please don't hesitate to contact us. We're here to help!</p>
                
                <p>Welcome aboard!</p>
            </div>
            <div class='footer'>
                <p>Thank you for choosing CSIMS!</p>
                <p>&copy; " . date('Y') . " CSIMS. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
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
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT email, CONCAT(first_name, ' ', last_name) as name
        FROM users 
        WHERE role = 'admin' 
        AND email IS NOT NULL
        AND status = 'active'
    ");
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate weekly report data
 */
function generateWeeklyReportData() {
    global $pdo;
    
    $data = [];
    
    // New members this week
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)");
    $stmt->execute();
    $data['new_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Expired members this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM memberships 
        WHERE expiry_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK) 
        AND expiry_date < NOW()
    ");
    $stmt->execute();
    $data['expired_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total active members
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM members WHERE status = 'Active'");
    $stmt->execute();
    $data['active_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $data;
}

/**
 * Generate monthly report data
 */
function generateMonthlyReportData() {
    global $pdo;
    
    $data = [];
    
    // New members this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $data['new_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Expired members this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM memberships 
        WHERE expiry_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) 
        AND expiry_date < NOW()
    ");
    $stmt->execute();
    $data['expired_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Revenue this month (if you have payment tracking)
    $data['revenue'] = 0; // Placeholder
    
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
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notification_log 
        WHERE member_id = ? 
        AND notification_type = ? 
        AND reminder_days = ?
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->execute([$memberId, $type, $days]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Mark reminder as sent
 */
function markReminderSent($memberId, $type, $days) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO notification_log 
        (member_id, notification_type, reminder_days, status, created_at) 
        VALUES (?, ?, ?, 'sent', NOW())
    ");
    
    $stmt->execute([$memberId, $type, $days]);
}

// Run the automated notifications
runAutomatedNotifications();

?>