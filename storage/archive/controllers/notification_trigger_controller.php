<?php
/**
 * Notification Trigger Controller
 * 
 * Manages automated notification triggers and scheduling
 * Handles creation, modification, and execution of notification triggers
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notification_config.php';
require_once __DIR__ . '/notification_controller.php';
require_once __DIR__ . '/member_controller.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/sms_service.php';

class NotificationTriggerController {
    private $pdo;
    private $notificationController;
    private $memberController;
    private $emailService;
    private $smsService;
    private $config;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->notificationController = new NotificationController();
        $this->memberController = new MemberController();
        $this->emailService = new EmailService();
        $this->smsService = new SMSService();
        $this->config = require __DIR__ . '/../config/notification_config.php';
    }
    
    /**
     * Get all notification triggers
     */
    public function getAllTriggers($page = 1, $limit = 20, $search = '', $status = '') {
        try {
            $offset = ($page - 1) * $limit;
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (name LIKE ? OR description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if (!empty($status)) {
                $whereClause .= " AND status = ?";
                $params[] = $status;
            }
            
            // Get total count
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_triggers $whereClause");
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();
            
            // Get triggers
            $stmt = $this->pdo->prepare("
                SELECT *, 
                       CASE 
                           WHEN next_run <= NOW() AND status = 'active' THEN 'due'
                           WHEN status = 'active' THEN 'scheduled'
                           ELSE status
                       END as current_status
                FROM notification_triggers 
                $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'triggers' => $triggers,
                'total' => $totalRecords,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalRecords / $limit)
            ];
            
        } catch (Exception $e) {
            error_log("Error getting triggers: " . $e->getMessage());
            return ['triggers' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }
    }
    
    /**
     * Get trigger by ID
     */
    public function getTriggerById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM notification_triggers WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting trigger: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new notification trigger
     */
    public function createTrigger($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_triggers 
                (name, description, trigger_type, trigger_condition, recipient_group, 
                 notification_template, schedule_pattern, next_run, status, 
                 email_enabled, sms_enabled, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $nextRun = $this->calculateNextRun($data['schedule_pattern']);
            
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['trigger_type'],
                json_encode($data['trigger_condition']),
                $data['recipient_group'],
                $data['notification_template'],
                $data['schedule_pattern'],
                $nextRun,
                $data['status'] ?? 'active',
                $data['email_enabled'] ?? 1,
                $data['sms_enabled'] ?? 0,
                $data['created_by']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Error creating trigger: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update notification trigger
     */
    public function updateTrigger($id, $data) {
        try {
            $nextRun = $this->calculateNextRun($data['schedule_pattern']);
            
            $stmt = $this->pdo->prepare("
                UPDATE notification_triggers SET 
                name = ?, description = ?, trigger_type = ?, trigger_condition = ?, 
                recipient_group = ?, notification_template = ?, schedule_pattern = ?, 
                next_run = ?, status = ?, email_enabled = ?, sms_enabled = ?, 
                updated_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['trigger_type'],
                json_encode($data['trigger_condition']),
                $data['recipient_group'],
                $data['notification_template'],
                $data['schedule_pattern'],
                $nextRun,
                $data['status'],
                $data['email_enabled'] ?? 1,
                $data['sms_enabled'] ?? 0,
                $id
            ]);
            
        } catch (Exception $e) {
            error_log("Error updating trigger: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete notification trigger
     */
    public function deleteTrigger($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notification_triggers WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Error deleting trigger: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get due triggers (ready to execute)
     */
    public function getDueTriggers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_triggers 
                WHERE status = 'active' 
                AND next_run <= NOW()
                ORDER BY next_run ASC
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting due triggers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute a specific trigger
     */
    public function executeTrigger($triggerId) {
        try {
            $trigger = $this->getTriggerById($triggerId);
            if (!$trigger) {
                throw new Exception("Trigger not found");
            }
            
            // Get recipients based on trigger configuration
            $recipients = $this->getRecipientsByGroup($trigger['recipient_group']);
            
            if (empty($recipients)) {
                $this->logTriggerExecution($triggerId, 'completed', 'No recipients found');
                return true;
            }
            
            // Filter recipients based on trigger condition
            $filteredRecipients = $this->filterRecipientsByCondition(
                $recipients, 
                json_decode($trigger['trigger_condition'], true)
            );
            
            if (empty($filteredRecipients)) {
                $this->logTriggerExecution($triggerId, 'completed', 'No recipients match condition');
                return true;
            }
            
            // Get notification template
            $template = $this->getNotificationTemplate($trigger['notification_template']);
            if (!$template) {
                throw new Exception("Template not found");
            }
            
            // Send notifications
            $sentCount = 0;
            $errorCount = 0;
            
            foreach ($filteredRecipients as $recipient) {
                try {
                    $personalizedTemplate = $this->personalizeTemplate($template, $recipient);
                    
                    $success = false;
                    
                    // Send email if enabled
                    if ($trigger['email_enabled'] && !empty($recipient['email'])) {
                        $emailSent = $this->emailService->send(
                            $recipient['email'],
                            $personalizedTemplate['subject'],
                            $personalizedTemplate['content'],
                            $recipient['name'] ?? $recipient['first_name'] . ' ' . $recipient['last_name']
                        );
                        
                        if ($emailSent) {
                            $success = true;
                        }
                    }
                    
                    // Send SMS if enabled
                    if ($trigger['sms_enabled'] && !empty($recipient['phone'])) {
                        $smsSent = $this->smsService->send(
                            $recipient['phone'],
                            $personalizedTemplate['sms_content'] ?? strip_tags($personalizedTemplate['content'])
                        );
                        
                        if ($smsSent) {
                            $success = true;
                        }
                    }
                    
                    if ($success) {
                        $sentCount++;
                    } else {
                        $errorCount++;
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    error_log("Error sending notification to recipient: " . $e->getMessage());
                }
            }
            
            // Update trigger's next run time
            $this->updateTriggerNextRun($triggerId);
            
            // Log execution
            $message = "Sent: $sentCount, Errors: $errorCount, Total Recipients: " . count($filteredRecipients);
            $this->logTriggerExecution($triggerId, 'completed', $message);
            
            return true;
            
        } catch (Exception $e) {
            $this->logTriggerExecution($triggerId, 'failed', $e->getMessage());
            error_log("Error executing trigger $triggerId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate next run time based on schedule pattern
     */
    private function calculateNextRun($schedulePattern) {
        $pattern = json_decode($schedulePattern, true);
        
        switch ($pattern['type']) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day'));
                
            case 'weekly':
                $dayOfWeek = $pattern['day_of_week'] ?? 1; // Monday
                $time = $pattern['time'] ?? '09:00';
                return date('Y-m-d H:i:s', strtotime("next " . $this->getDayName($dayOfWeek) . " $time"));
                
            case 'monthly':
                $dayOfMonth = $pattern['day_of_month'] ?? 1;
                $time = $pattern['time'] ?? '09:00';
                $nextMonth = date('Y-m-01', strtotime('+1 month'));
                return date('Y-m-d H:i:s', strtotime($nextMonth . ' +' . ($dayOfMonth - 1) . ' days ' . $time));
                
            case 'custom':
                return $pattern['next_run'] ?? date('Y-m-d H:i:s', strtotime('+1 hour'));
                
            default:
                return date('Y-m-d H:i:s', strtotime('+1 hour'));
        }
    }
    
    /**
     * Get day name from number
     */
    private function getDayName($dayNumber) {
        $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        return $days[$dayNumber] ?? 'Monday';
    }
    
    /**
     * Update trigger's next run time
     */
    private function updateTriggerNextRun($triggerId) {
        try {
            $trigger = $this->getTriggerById($triggerId);
            $nextRun = $this->calculateNextRun($trigger['schedule_pattern']);
            
            $stmt = $this->pdo->prepare("
                UPDATE notification_triggers 
                SET next_run = ?, last_run = NOW(), run_count = run_count + 1 
                WHERE id = ?
            ");
            
            $stmt->execute([$nextRun, $triggerId]);
            
        } catch (Exception $e) {
            error_log("Error updating trigger next run: " . $e->getMessage());
        }
    }
    
    /**
     * Get recipients by group
     */
    private function getRecipientsByGroup($group) {
        try {
            $recipientGroups = $this->config['recipients'];
            
            if (!isset($recipientGroups[$group])) {
                return [];
            }
            
            $stmt = $this->pdo->prepare($recipientGroups[$group]['query']);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting recipients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Filter recipients by condition
     */
    private function filterRecipientsByCondition($recipients, $condition) {
        if (empty($condition)) {
            return $recipients;
        }
        
        return array_filter($recipients, function($recipient) use ($condition) {
            foreach ($condition as $field => $value) {
                if (!isset($recipient[$field]) || $recipient[$field] != $value) {
                    return false;
                }
            }
            return true;
        });
    }
    
    /**
     * Get notification template
     */
    private function getNotificationTemplate($templateName) {
        $templates = $this->config['templates'];
        return $templates[$templateName] ?? null;
    }
    
    /**
     * Personalize template with recipient data
     */
    private function personalizeTemplate($template, $recipient) {
        $placeholders = $this->config['placeholders'];
        $orgInfo = $this->config['organization'];
        
        $content = $template['content'];
        $subject = $template['subject'] ?? '';
        
        // Replace recipient placeholders
        foreach ($recipient as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
            $subject = str_replace('{' . $key . '}', $value, $subject);
        }
        
        // Replace common placeholders
        $replacements = [
            '{name}' => ($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''),
            '{current_date}' => date('F j, Y'),
            '{current_time}' => date('g:i A'),
            '{current_year}' => date('Y'),
            '{organization_name}' => $orgInfo['name'],
            '{contact_email}' => $orgInfo['contact_email'],
            '{contact_phone}' => $orgInfo['contact_phone'],
            '{website_url}' => $orgInfo['website_url']
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
            $subject = str_replace($placeholder, $value, $subject);
        }
        
        return [
            'subject' => $subject,
            'content' => $content,
            'sms_content' => strip_tags($content)
        ];
    }
    
    /**
     * Log trigger execution
     */
    private function logTriggerExecution($triggerId, $status, $message = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_trigger_log 
                (trigger_id, execution_status, message, executed_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([$triggerId, $status, $message]);
            
        } catch (Exception $e) {
            error_log("Error logging trigger execution: " . $e->getMessage());
        }
    }
    
    /**
     * Get trigger execution history with pagination and filtering
     */
    public function getTriggerHistory($triggerId, $page = 1, $limit = 20, $status = '', $dateFrom = '', $dateTo = '') {
        try {
            $offset = ($page - 1) * $limit;
            $whereClause = "WHERE trigger_id = ?";
            $params = [$triggerId];
            
            if (!empty($status)) {
                $whereClause .= " AND execution_status = ?";
                $params[] = $status;
            }
            
            if (!empty($dateFrom)) {
                $whereClause .= " AND DATE(executed_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if (!empty($dateTo)) {
                $whereClause .= " AND DATE(executed_at) <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_trigger_log $whereClause");
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();
            
            // Get history records
            $stmt = $this->pdo->prepare("
                SELECT *, 
                       execution_status as status,
                       0 as recipients_count,
                       0 as sent_count,
                       0 as failed_count,
                       0 as email_sent,
                       0 as sms_sent,
                       0 as email_failed,
                       0 as sms_failed,
                       0 as duration
                FROM notification_trigger_log 
                $whereClause 
                ORDER BY executed_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'history' => $history,
                'total' => $totalRecords,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalRecords / $limit)
            ];
            
        } catch (Exception $e) {
            error_log("Error getting trigger history: " . $e->getMessage());
            return [
                'history' => [],
                'total' => 0,
                'page' => 1,
                'limit' => $limit,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Get trigger execution statistics
     */
    public function getTriggerExecutionStats($triggerId) {
        try {
            $stats = [];
            
            // Total executions
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_trigger_log WHERE trigger_id = ?");
            $stmt->execute([$triggerId]);
            $stats['total_executions'] = $stmt->fetchColumn();
            
            // Successful executions
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_trigger_log WHERE trigger_id = ? AND execution_status = 'completed'");
            $stmt->execute([$triggerId]);
            $stats['successful_executions'] = $stmt->fetchColumn();
            
            // Failed executions
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_trigger_log WHERE trigger_id = ? AND execution_status = 'failed'");
            $stmt->execute([$triggerId]);
            $stats['failed_executions'] = $stmt->fetchColumn();
            
            // Total recipients (estimated from recent executions)
            $stmt = $this->pdo->prepare("
                SELECT SUM(
                    CASE 
                        WHEN message LIKE '%Total Recipients:%' THEN 
                            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(message, 'Total Recipients: ', -1), ',', 1) AS UNSIGNED)
                        ELSE 0
                    END
                ) as total_recipients
                FROM notification_trigger_log 
                WHERE trigger_id = ? AND execution_status = 'completed'
            ");
            $stmt->execute([$triggerId]);
            $result = $stmt->fetchColumn();
            $stats['total_recipients'] = $result ?: 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting trigger execution stats: " . $e->getMessage());
            return [
                'total_executions' => 0,
                'successful_executions' => 0,
                'failed_executions' => 0,
                'total_recipients' => 0
            ];
        }
    }
    
    /**
     * Get execution log details by execution ID
     */
    public function getExecutionLogDetails($executionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ntl.*, nt.name as trigger_name, nt.description as trigger_description,
                       nt.trigger_type, nt.recipient_group, nt.notification_template
                FROM notification_trigger_log ntl
                JOIN notification_triggers nt ON ntl.trigger_id = nt.id
                WHERE ntl.id = ?
            ");
            
            $stmt->execute([$executionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting execution log details: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get trigger statistics
     */
    public function getTriggerStats() {
        try {
            $stats = [];
            
            // Total triggers
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_triggers");
            $stmt->execute();
            $stats['total_triggers'] = $stmt->fetchColumn();
            
            // Active triggers
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_triggers WHERE status = 'active'");
            $stmt->execute();
            $stats['active_triggers'] = $stmt->fetchColumn();
            
            // Due triggers
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_triggers WHERE status = 'active' AND next_run <= NOW()");
            $stmt->execute();
            $stats['due_triggers'] = $stmt->fetchColumn();
            
            // Executions today
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notification_trigger_log WHERE DATE(executed_at) = CURDATE()");
            $stmt->execute();
            $stats['executions_today'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting trigger stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Test trigger (dry run)
     */
    public function testTrigger($triggerId) {
        try {
            $trigger = $this->getTriggerById($triggerId);
            if (!$trigger) {
                return ['success' => false, 'message' => 'Trigger not found'];
            }
            
            $recipients = $this->getRecipientsByGroup($trigger['recipient_group']);
            $filteredRecipients = $this->filterRecipientsByCondition(
                $recipients, 
                json_decode($trigger['trigger_condition'], true)
            );
            
            return [
                'success' => true,
                'total_recipients' => count($recipients),
                'filtered_recipients' => count($filteredRecipients),
                'sample_recipients' => array_slice($filteredRecipients, 0, 5)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}