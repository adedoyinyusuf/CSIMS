<?php
/**
 * Cron Job Script for Processing Email and SMS Notifications
 * 
 * This script should be run periodically (every 5-10 minutes) to process
 * queued email and SMS notifications.
 * 
 * Usage: php process_notifications.php
 * Or set up as a scheduled task in Windows Task Scheduler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/notification_controller.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/sms_service.php';

// Set execution time limit
set_time_limit(300); // 5 minutes

// Log file for cron job execution
$logFile = __DIR__ . '/logs/notification_cron.log';

// Ensure log directory exists
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

/**
 * Log message with timestamp
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Process email queue
 */
function processEmailQueue($conn) {
    try {
        $limit = 50; // Process 50 emails per run
        
        $sql = "SELECT * FROM email_queue 
                WHERE status = 'pending' AND scheduled_at <= NOW() 
                ORDER BY priority DESC, scheduled_at ASC 
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $processed = 0;
        $failed = 0;
        
        while ($email = $result->fetch_assoc()) {
            // Update status to 'sending'
            updateEmailStatus($conn, $email['id'], 'sending');
            
            // Attempt to send email
            if (sendEmail($email)) {
                updateEmailStatus($conn, $email['id'], 'sent');
                $processed++;
                logMessage("Email sent successfully to {$email['to_email']} - Subject: {$email['subject']}");
            } else {
                $email['attempts']++;
                if ($email['attempts'] >= $email['max_attempts']) {
                    updateEmailStatus($conn, $email['id'], 'failed', 'Max attempts reached');
                    $failed++;
                    logMessage("Email failed permanently to {$email['to_email']} - Max attempts reached");
                } else {
                    updateEmailStatus($conn, $email['id'], 'pending', 'Retry attempt ' . $email['attempts']);
                    incrementEmailAttempts($conn, $email['id']);
                    logMessage("Email failed to {$email['to_email']} - Will retry (Attempt {$email['attempts']})");
                }
            }
        }
        
        $stmt->close();
        
        if ($processed > 0 || $failed > 0) {
            logMessage("Email processing completed: $processed sent, $failed failed");
        }
        
        return ['processed' => $processed, 'failed' => $failed];
        
    } catch (Exception $e) {
        logMessage("Error processing email queue: " . $e->getMessage());
        return ['processed' => 0, 'failed' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Process SMS queue
 */
function processSMSQueue($conn) {
    try {
        $limit = 50; // Process 50 SMS per run
        
        $sql = "SELECT * FROM sms_queue 
                WHERE status = 'pending' AND scheduled_at <= NOW() 
                ORDER BY priority DESC, scheduled_at ASC 
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $processed = 0;
        $failed = 0;
        
        while ($sms = $result->fetch_assoc()) {
            // Update status to 'sending'
            updateSMSStatus($conn, $sms['id'], 'sending');
            
            // Attempt to send SMS
            if (sendSMS($sms)) {
                updateSMSStatus($conn, $sms['id'], 'sent');
                $processed++;
                logMessage("SMS sent successfully to {$sms['to_phone']} - Message: " . substr($sms['message'], 0, 50) . "...");
            } else {
                $sms['attempts']++;
                if ($sms['attempts'] >= $sms['max_attempts']) {
                    updateSMSStatus($conn, $sms['id'], 'failed', 'Max attempts reached');
                    $failed++;
                    logMessage("SMS failed permanently to {$sms['to_phone']} - Max attempts reached");
                } else {
                    updateSMSStatus($conn, $sms['id'], 'pending', 'Retry attempt ' . $sms['attempts']);
                    incrementSMSAttempts($conn, $sms['id']);
                    logMessage("SMS failed to {$sms['to_phone']} - Will retry (Attempt {$sms['attempts']})");
                }
            }
        }
        
        $stmt->close();
        
        if ($processed > 0 || $failed > 0) {
            logMessage("SMS processing completed: $processed sent, $failed failed");
        }
        
        return ['processed' => $processed, 'failed' => $failed];
        
    } catch (Exception $e) {
        logMessage("Error processing SMS queue: " . $e->getMessage());
        return ['processed' => 0, 'failed' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Update email status
 */
function updateEmailStatus($conn, $id, $status, $errorMessage = null) {
    $sql = "UPDATE email_queue SET status = ?, sent_at = ?, error_message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
    $stmt->bind_param('sssi', $status, $sentAt, $errorMessage, $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Update SMS status
 */
function updateSMSStatus($conn, $id, $status, $errorMessage = null) {
    $sql = "UPDATE sms_queue SET status = ?, sent_at = ?, error_message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
    $stmt->bind_param('sssi', $status, $sentAt, $errorMessage, $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Increment email attempts
 */
function incrementEmailAttempts($conn, $id) {
    $sql = "UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Increment SMS attempts
 */
function incrementSMSAttempts($conn, $id) {
    $sql = "UPDATE sms_queue SET attempts = attempts + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Send email using email service
 */
function sendEmail($emailData) {
    try {
        $emailService = new EmailService();
        return $emailService->send(
            $emailData['to_email'],
            $emailData['to_name'],
            $emailData['subject'],
            $emailData['body']
        );
    } catch (Exception $e) {
        logMessage("Email sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS using SMS service
 */
function sendSMS($smsData) {
    try {
        $smsService = new SMSService();
        return $smsService->send(
            $smsData['to_phone'],
            $smsData['message']
        );
    } catch (Exception $e) {
        logMessage("SMS sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up old processed records
 */
function cleanupOldRecords($conn) {
    try {
        // Delete email records older than 30 days
        $sql = "DELETE FROM email_queue WHERE status IN ('sent', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $conn->query($sql);
        $emailDeleted = $conn->affected_rows;
        
        // Delete SMS records older than 30 days
        $sql = "DELETE FROM sms_queue WHERE status IN ('sent', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $conn->query($sql);
        $smsDeleted = $conn->affected_rows;
        
        if ($emailDeleted > 0 || $smsDeleted > 0) {
            logMessage("Cleanup completed: $emailDeleted email records, $smsDeleted SMS records deleted");
        }
        
        return ['email_deleted' => $emailDeleted, 'sms_deleted' => $smsDeleted];
        
    } catch (Exception $e) {
        logMessage("Error during cleanup: " . $e->getMessage());
        return ['email_deleted' => 0, 'sms_deleted' => 0, 'error' => $e->getMessage()];
    }
}

// Main execution
try {
    logMessage("Starting notification processing cron job");
    
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Process email queue
    $emailResults = processEmailQueue($conn);
    
    // Process SMS queue
    $smsResults = processSMSQueue($conn);
    
    // Clean up old records (run once per day)
    $currentHour = (int)date('H');
    if ($currentHour === 2) { // Run cleanup at 2 AM
        $cleanupResults = cleanupOldRecords($conn);
    }
    
    // Log summary
    $totalProcessed = $emailResults['processed'] + $smsResults['processed'];
    $totalFailed = $emailResults['failed'] + $smsResults['failed'];
    
    if ($totalProcessed > 0 || $totalFailed > 0) {
        logMessage("Cron job completed: Total processed: $totalProcessed, Total failed: $totalFailed");
    }
    
    // Close database connection
    $conn->close();
    
} catch (Exception $e) {
    logMessage("Fatal error in cron job: " . $e->getMessage());
    exit(1);
}

logMessage("Notification processing cron job finished");
exit(0);
?>