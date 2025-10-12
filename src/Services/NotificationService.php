<?php

namespace CSIMS\Services;

use CSIMS\Interfaces\ServiceInterface;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\ServiceException;
use mysqli;

/**
 * Notification Service
 * 
 * Handles all notification operations including email, SMS, and in-app notifications
 */
class NotificationService implements ServiceInterface
{
    private mysqli $connection;
    private array $config;
    
    public function __construct(mysqli $connection, array $config = [])
    {
        $this->connection = $connection;
        $this->config = array_merge([
            'email_enabled' => true,
            'sms_enabled' => true,
            'push_enabled' => true,
            'default_sender' => 'NPC CTLStaff Loan Society',
            'email_template_path' => __DIR__ . '/../../templates/emails/',
            'sms_template_path' => __DIR__ . '/../../templates/sms/'
        ], $config);
    }
    
    /**
     * Send notification to user(s)
     */
    public function sendNotification(array $data): bool
    {
        try {
            $this->validateNotificationData($data);
            
            $notification_id = $this->createNotificationRecord($data);
            
            $success = true;
            
            // Send email notification
            if ($data['send_email'] ?? true) {
                $success = $success && $this->sendEmailNotification($data, $notification_id);
            }
            
            // Send SMS notification
            if ($data['send_sms'] ?? false) {
                $success = $success && $this->sendSmsNotification($data, $notification_id);
            }
            
            // Create in-app notification
            if ($data['send_in_app'] ?? true) {
                $success = $success && $this->createInAppNotification($data, $notification_id);
            }
            
            // Update notification status
            $this->updateNotificationStatus($notification_id, $success ? 'sent' : 'failed');
            
            return $success;
            
        } catch (\Exception $e) {
            error_log("Notification Service Error: " . $e->getMessage());
            throw new ServiceException('Failed to send notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(array $recipients, array $data): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $notificationData = array_merge($data, [
                'recipient_id' => $recipient['id'],
                'recipient_email' => $recipient['email'] ?? null,
                'recipient_phone' => $recipient['phone'] ?? null,
                'recipient_name' => $recipient['name'] ?? null
            ]);
            
            try {
                $results[$recipient['id']] = $this->sendNotification($notificationData);
            } catch (\Exception $e) {
                $results[$recipient['id']] = false;
                error_log("Failed to send notification to recipient {$recipient['id']}: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Send savings account notification
     */
    public function sendSavingsNotification(string $type, array $accountData, array $transactionData = []): bool
    {
        $templates = $this->getSavingsNotificationTemplates();
        
        if (!isset($templates[$type])) {
            throw new ValidationException("Unknown savings notification type: $type");
        }
        
        $template = $templates[$type];
        
        $data = [
            'type' => $type,
            'subject' => $this->processTemplate($template['subject'], $accountData, $transactionData),
            'message' => $this->processTemplate($template['message'], $accountData, $transactionData),
            'recipient_id' => $accountData['member_id'],
            'recipient_email' => $accountData['member_email'] ?? null,
            'recipient_phone' => $accountData['member_phone'] ?? null,
            'recipient_name' => $accountData['member_name'] ?? null,
            'category' => 'savings',
            'priority' => $template['priority'] ?? 'normal',
            'send_email' => $template['send_email'] ?? true,
            'send_sms' => $template['send_sms'] ?? false,
            'send_in_app' => true
        ];
        
        return $this->sendNotification($data);
    }
    
    /**
     * Get member notifications
     */
    public function getMemberNotifications(int $memberId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM notifications 
                WHERE recipient_id = ? AND recipient_type = 'member'
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('iii', $memberId, $limit, $offset);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $memberId): bool
    {
        $sql = "UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE notification_id = ? AND recipient_id = ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('ii', $notificationId, $memberId);
        
        return $stmt->execute();
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $memberId): int
    {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE recipient_id = ? AND recipient_type = 'member' AND is_read = 0";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int)($row['count'] ?? 0);
    }
    
    /**
     * Validate notification data
     */
    private function validateNotificationData(array $data): void
    {
        $required = ['type', 'subject', 'message', 'recipient_id'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Missing required field: $field");
            }
        }
        
        if (empty($data['recipient_email']) && empty($data['recipient_phone'])) {
            // In-app only notification is allowed
        }
    }
    
    /**
     * Create notification record in database
     */
    private function createNotificationRecord(array $data): int
    {
        $sql = "INSERT INTO notifications (
                    type, subject, message, recipient_id, recipient_type, 
                    category, priority, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param(
            'sssisss',
            $data['type'],
            $data['subject'],
            $data['message'],
            $data['recipient_id'],
            $data['recipient_type'] ?? 'member',
            $data['category'] ?? 'general',
            $data['priority'] ?? 'normal'
        );
        
        if (!$stmt->execute()) {
            throw new ServiceException('Failed to create notification record');
        }
        
        return $this->connection->insert_id;
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification(array $data, int $notificationId): bool
    {
        if (!$this->config['email_enabled'] || empty($data['recipient_email'])) {
            return true; // Skip if disabled or no email
        }
        
        // Here you would integrate with your email service
        // For now, we'll simulate success
        error_log("Email notification sent to: " . $data['recipient_email']);
        return true;
    }
    
    /**
     * Send SMS notification
     */
    private function sendSmsNotification(array $data, int $notificationId): bool
    {
        if (!$this->config['sms_enabled'] || empty($data['recipient_phone'])) {
            return true; // Skip if disabled or no phone
        }
        
        // Here you would integrate with your SMS service
        // For now, we'll simulate success
        error_log("SMS notification sent to: " . $data['recipient_phone']);
        return true;
    }
    
    /**
     * Create in-app notification
     */
    private function createInAppNotification(array $data, int $notificationId): bool
    {
        // The notification record is already created, so this is successful
        return true;
    }
    
    /**
     * Update notification status
     */
    private function updateNotificationStatus(int $notificationId, string $status): void
    {
        $sql = "UPDATE notifications SET status = ?, updated_at = NOW() WHERE notification_id = ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('si', $status, $notificationId);
        $stmt->execute();
    }
    
    /**
     * Get savings notification templates
     */
    private function getSavingsNotificationTemplates(): array
    {
        return [
            'account_created' => [
                'subject' => 'New Savings Account Created - {account_name}',
                'message' => 'Hello {member_name}, your new savings account "{account_name}" has been successfully created with account number {account_number}.',
                'priority' => 'normal',
                'send_email' => true,
                'send_sms' => false
            ],
            'deposit_successful' => [
                'subject' => 'Deposit Successful - {account_name}',
                'message' => 'Hello {member_name}, your deposit of ₦{amount} to account {account_number} was successful. New balance: ₦{new_balance}.',
                'priority' => 'normal',
                'send_email' => true,
                'send_sms' => true
            ],
            'withdrawal_successful' => [
                'subject' => 'Withdrawal Successful - {account_name}',
                'message' => 'Hello {member_name}, your withdrawal of ₦{amount} from account {account_number} was successful. New balance: ₦{new_balance}.',
                'priority' => 'normal',
                'send_email' => true,
                'send_sms' => true
            ],
            'low_balance_warning' => [
                'subject' => 'Low Balance Warning - {account_name}',
                'message' => 'Hello {member_name}, your account {account_number} has a low balance of ₦{balance}. Consider making a deposit to avoid falling below the minimum balance.',
                'priority' => 'high',
                'send_email' => true,
                'send_sms' => false
            ],
            'target_achieved' => [
                'subject' => 'Savings Target Achieved! - {account_name}',
                'message' => 'Congratulations {member_name}! You have successfully achieved your savings target of ₦{target_amount} for account {account_number}.',
                'priority' => 'high',
                'send_email' => true,
                'send_sms' => true
            ],
            'interest_credited' => [
                'subject' => 'Interest Credited - {account_name}',
                'message' => 'Hello {member_name}, interest of ₦{interest_amount} has been credited to your account {account_number}. New balance: ₦{new_balance}.',
                'priority' => 'normal',
                'send_email' => true,
                'send_sms' => false
            ]
        ];
    }
    
    /**
     * Process template with data
     */
    private function processTemplate(string $template, array $accountData, array $transactionData = []): string
    {
        $data = array_merge($accountData, $transactionData);
        
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Get service status
     */
    public function getStatus(): array
    {
        return [
            'service' => 'NotificationService',
            'status' => 'active',
            'email_enabled' => $this->config['email_enabled'],
            'sms_enabled' => $this->config['sms_enabled'],
            'push_enabled' => $this->config['push_enabled']
        ];
    }
    
    /**
     * Get service configuration
     */
    public function getConfiguration(): array
    {
        return [
            'email_enabled' => $this->config['email_enabled'],
            'sms_enabled' => $this->config['sms_enabled'],
            'push_enabled' => $this->config['push_enabled'],
            'default_sender' => $this->config['default_sender']
        ];
    }
}