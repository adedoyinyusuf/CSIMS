<?php
/**
 * Notification Controller
 * 
 * Handles all notification-related operations including creating, updating,
 * retrieving, and deleting notification records.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utilities.php';

class NotificationController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Get all notifications with pagination and search
     * 
     * @param int $page Current page number
     * @param int $limit Records per page
     * @param string $search Search term
     * @param string $filter Filter by type
     * @return array Notifications data with pagination info
     */
    public function getAllNotifications($page = 1, $limit = 10, $search = '', $filter = 'all') {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        // Add search conditions
        if (!empty($search)) {
            $where_clause .= " AND (n.title LIKE ? OR n.message LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param]);
            $types .= "ss";
        }
        
        // Add filter conditions
        if ($filter !== 'all') {
            $where_clause .= " AND n.notification_type = ?";
            $params[] = $filter;
            $types .= "s";
        }
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total FROM notifications n $where_clause";
        $count_stmt = $this->conn->prepare($count_sql);
        
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
        
        // Get paginated results
        $sql = "SELECT n.*, 
                CONCAT(a.first_name, ' ', a.last_name) as created_by_name
                FROM notifications n 
                LEFT JOIN admins a ON n.created_by = a.admin_id 
                $where_clause 
                ORDER BY n.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
        
        return [
            'notifications' => $notifications,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Get notification by ID
     * 
     * @param int $notification_id Notification ID
     * @return array|null Notification data
     */
    public function getNotificationById($notification_id) {
        $sql = "SELECT n.*, 
                CONCAT(a.first_name, ' ', a.last_name) as created_by_name
                FROM notifications n 
                LEFT JOIN admins a ON n.created_by = a.admin_id 
                WHERE n.notification_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notification = $result->fetch_assoc();
        $stmt->close();
        
        return $notification;
    }
    
    /**
     * Create a new notification
     * 
     * @param array $data Notification data
     * @return int|false Notification ID on success, false on failure
     */
    public function createNotification($data) {
        $sql = "INSERT INTO notifications (title, message, recipient_type, recipient_id, notification_type, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssisi", 
            $data['title'],
            $data['message'],
            $data['recipient_type'],
            $data['recipient_id'],
            $data['notification_type'],
            $data['created_by']
        );
        
        $result = $stmt->execute();
        $notification_id = $this->conn->insert_id;
        $stmt->close();
        
        return $result ? $notification_id : false;
    }
    
    /**
     * Update an existing notification
     * 
     * @param int $notification_id Notification ID
     * @param array $data Updated notification data
     * @return bool True on success, false on failure
     */
    public function updateNotification($notification_id, $data) {
        $sql = "UPDATE notifications SET 
                title = ?, message = ?, recipient_type = ?, recipient_id = ?, notification_type = ? 
                WHERE notification_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssisi", 
            $data['title'],
            $data['message'],
            $data['recipient_type'],
            $data['recipient_id'],
            $data['notification_type'],
            $notification_id
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Delete a notification
     * 
     * @param int $notification_id Notification ID
     * @return bool True on success, false on failure
     */
    public function deleteNotification($notification_id) {
        $sql = "DELETE FROM notifications WHERE notification_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $notification_id Notification ID
     * @return bool True on success, false on failure
     */
    public function markAsRead($notification_id) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get notification statistics
     * 
     * @return array Statistics data
     */
    public function getNotificationStats() {
        $sql = "SELECT 
                COUNT(*) as total_notifications,
                COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) as unread_notifications,
                COALESCE(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END), 0) as read_notifications,
                COALESCE(SUM(CASE WHEN recipient_type = 'All' THEN 1 ELSE 0 END), 0) as broadcast_notifications
                FROM notifications";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    /**
     * Get notification types
     * 
     * @return array List of notification types
     */
    public function getNotificationTypes() {
        return [
            'Payment' => 'Payment Related',
            'Meeting' => 'Meeting Announcement',
            'Policy' => 'Policy Update',
            'General' => 'General Information'
        ];
    }
    
    /**
     * Get recipient types
     * 
     * @return array List of recipient types
     */
    public function getRecipientTypes() {
        return [
            'All' => 'All Users',
            'Member' => 'Specific Member',
            'Admin' => 'Specific Admin'
        ];
    }
    
    /**
     * Get members for notification recipients
     * 
     * @return array List of active members
     */
    public function getMembers() {
        $sql = "SELECT member_id, CONCAT(first_name, ' ', last_name) as name FROM members WHERE status = 'Active' ORDER BY first_name";
        $result = $this->conn->query($sql);
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    /**
     * Get admins for notification recipients
     * 
     * @return array List of active admins
     */
    public function getAdmins() {
        $sql = "SELECT admin_id, CONCAT(first_name, ' ', last_name) as name FROM admins WHERE status = 'Active' ORDER BY first_name";
        $result = $this->conn->query($sql);
        
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
        
        return $admins;
    }
    
    /**
     * Send email notification to members
     */
    public function sendEmailNotification($data) {
        try {
            $recipients = $this->getRecipients($data['recipient_type'], $data['recipient_ids'] ?? []);
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($recipients as $recipient) {
                $emailData = [
                    'to_email' => $recipient['email'],
                    'to_name' => $recipient['first_name'] . ' ' . $recipient['last_name'],
                    'subject' => $this->replacePlaceholders($data['subject'], $recipient),
                    'body' => $this->replacePlaceholders($data['message'], $recipient),
                    'priority' => $data['priority'] ?? 'normal'
                ];
                
                if ($this->queueEmail($emailData)) {
                    $successCount++;
                    $this->logNotification([
                        'recipient_type' => 'member',
                        'recipient_id' => $recipient['member_id'],
                        'notification_type' => 'email',
                        'subject' => $emailData['subject'],
                        'message' => $emailData['body'],
                        'status' => 'sent',
                        'sent_by' => $data['sent_by']
                    ]);
                } else {
                    $failureCount++;
                }
            }
            
            return [
                'success' => true,
                'message' => "Email queued successfully. Sent: {$successCount}, Failed: {$failureCount}",
                'sent_count' => $successCount,
                'failed_count' => $failureCount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send SMS notification to members
     */
    public function sendSMSNotification($data) {
        try {
            $recipients = $this->getRecipients($data['recipient_type'], $data['recipient_ids'] ?? []);
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($recipients as $recipient) {
                if (empty($recipient['phone'])) {
                    $failureCount++;
                    continue;
                }
                
                $smsData = [
                    'to_phone' => $recipient['phone'],
                    'to_name' => $recipient['first_name'] . ' ' . $recipient['last_name'],
                    'message' => $this->replacePlaceholders($data['message'], $recipient),
                    'priority' => $data['priority'] ?? 'normal'
                ];
                
                if ($this->queueSMS($smsData)) {
                    $successCount++;
                    $this->logNotification([
                        'recipient_type' => 'member',
                        'recipient_id' => $recipient['member_id'],
                        'notification_type' => 'sms',
                        'subject' => 'SMS Notification',
                        'message' => $smsData['message'],
                        'status' => 'sent',
                        'sent_by' => $data['sent_by']
                    ]);
                } else {
                    $failureCount++;
                }
            }
            
            return [
                'success' => true,
                'message' => "SMS queued successfully. Sent: {$successCount}, Failed: {$failureCount}",
                'sent_count' => $successCount,
                'failed_count' => $failureCount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending SMS: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send both email and SMS notifications
     */
    public function sendBulkNotification($data) {
        $results = [];
        
        if (in_array('email', $data['notification_methods'])) {
            $results['email'] = $this->sendEmailNotification($data);
        }
        
        if (in_array('sms', $data['notification_methods'])) {
            $results['sms'] = $this->sendSMSNotification($data);
        }
        
        return $results;
    }
    
    /**
     * Get recipients based on type and criteria
     */
    private function getRecipients($type, $ids = []) {
        switch ($type) {
            case 'all':
                return $this->getAllActiveMembers();
            case 'active':
                return $this->getMembersByStatus('Active');
            case 'expired':
                return $this->getMembersByStatus('Expired');
            case 'expiring':
                return $this->getExpiringMembers(30); // 30 days
            case 'selected':
                return $this->getMembersByIds($ids);
            default:
                return [];
        }
    }
    
    /**
     * Get all active members
     */
    private function getAllActiveMembers() {
        $sql = "SELECT member_id, first_name, last_name, email, phone, membership_type, 
                       join_date, expiry_date, status 
                FROM members 
                WHERE status != 'Deleted'
                ORDER BY first_name, last_name";
        
        $result = $this->conn->query($sql);
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        return $members;
    }
    
    /**
     * Get members by status
     */
    private function getMembersByStatus($status) {
        $sql = "SELECT member_id, first_name, last_name, email, phone, membership_type, 
                       join_date, expiry_date, status 
                FROM members 
                WHERE status = ?
                ORDER BY first_name, last_name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        return $members;
    }
    
    /**
     * Get expiring members
     */
    private function getExpiringMembers($days = 30) {
        $sql = "SELECT member_id, first_name, last_name, email, phone, membership_type, 
                       join_date, expiry_date, status 
                FROM members 
                WHERE status = 'Active' 
                  AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY expiry_date, first_name, last_name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        return $members;
    }
    
    /**
     * Get members by IDs
     */
    private function getMembersByIds($ids) {
        if (empty($ids)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT member_id, first_name, last_name, email, phone, membership_type, 
                       join_date, expiry_date, status 
                FROM members 
                WHERE member_id IN ($placeholders) AND status != 'Deleted'
                ORDER BY first_name, last_name";
        
        $stmt = $this->conn->prepare($sql);
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        return $members;
    }
    
    /**
     * Replace placeholders in message content
     */
    private function replacePlaceholders($content, $member) {
        $placeholders = [
            '{first_name}' => $member['first_name'],
            '{last_name}' => $member['last_name'],
            '{full_name}' => $member['first_name'] . ' ' . $member['last_name'],
            '{email}' => $member['email'],
            '{phone}' => $member['phone'],
            '{member_id}' => $member['member_id'],
            '{membership_type}' => $member['membership_type'],
            '{join_date}' => date('F j, Y', strtotime($member['join_date'])),
            '{expiry_date}' => date('F j, Y', strtotime($member['expiry_date'])),
            '{status}' => $member['status'],
            '{current_date}' => date('F j, Y'),
            '{current_year}' => date('Y')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }
    
    /**
     * Queue email for sending
     */
    private function queueEmail($data) {
        $sql = "INSERT INTO email_queue (to_email, to_name, subject, body, priority, scheduled_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sssss', 
            $data['to_email'],
            $data['to_name'],
            $data['subject'],
            $data['body'],
            $data['priority']
        );
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Queue SMS for sending
     */
    private function queueSMS($data) {
        $sql = "INSERT INTO sms_queue (to_phone, to_name, message, priority, scheduled_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssss', 
            $data['to_phone'],
            $data['to_name'],
            $data['message'],
            $data['priority']
        );
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Log notification activity
     */
    private function logNotification($data) {
        $sql = "INSERT INTO notification_log 
                (recipient_type, recipient_id, notification_type, subject, message, status, sent_by, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sissssi', 
            $data['recipient_type'],
            $data['recipient_id'],
            $data['notification_type'],
            $data['subject'],
            $data['message'],
            $data['status'],
            $data['sent_by']
        );
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get message templates
     */
    public function getMessageTemplates() {
        $sql = "SELECT * FROM message_templates WHERE status = 'active' ORDER BY name";
        $result = $this->conn->query($sql);
        
        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        return $templates;
    }
    
    /**
     * Get recent notifications from log
     */
    public function getRecentNotificationLogs($limit = 10) {
        $sql = "SELECT nl.*, m.first_name, m.last_name 
                FROM notification_log nl
                LEFT JOIN members m ON nl.recipient_id = m.member_id
                ORDER BY nl.created_at DESC 
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        return $logs;
    }
    
    /**
     * Get notifications for a specific member
     * 
     * @param int $member_id Member ID
     * @param int $limit Number of notifications to return
     * @return array Member notifications
     */
    public function getMemberNotifications($member_id, $limit = 10) {
        $sql = "SELECT n.*, 
                CONCAT(a.first_name, ' ', a.last_name) as created_by_name
                FROM notifications n 
                LEFT JOIN admins a ON n.created_by = a.admin_id 
                WHERE (n.recipient_type = 'All' OR (n.recipient_type = 'Member' AND n.recipient_id = ?))
                ORDER BY n.created_at DESC 
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $member_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
        return $notifications;
    }
    
    /**
     * Get enhanced notification statistics
     */
    public function getEnhancedNotificationStats() {
        $stats = [];
        
        // Total notifications sent today
        $sql = "SELECT COUNT(*) as count FROM notification_log WHERE DATE(sent_at) = CURDATE()";
        $result = $this->conn->query($sql);
        $stats['today'] = $result->fetch_assoc()['count'];
        
        // Total notifications sent this week
        $sql = "SELECT COUNT(*) as count FROM notification_log WHERE WEEK(sent_at) = WEEK(CURDATE())";
        $result = $this->conn->query($sql);
        $stats['this_week'] = $result->fetch_assoc()['count'];
        
        // Total notifications sent this month
        $sql = "SELECT COUNT(*) as count FROM notification_log WHERE MONTH(sent_at) = MONTH(CURDATE())";
        $result = $this->conn->query($sql);
        $stats['this_month'] = $result->fetch_assoc()['count'];
        
        // Pending emails
        $sql = "SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'";
        $result = $this->conn->query($sql);
        $stats['pending_emails'] = $result->fetch_assoc()['count'];
        
        // Pending SMS
        $sql = "SELECT COUNT(*) as count FROM sms_queue WHERE status = 'pending'";
        $result = $this->conn->query($sql);
        $stats['pending_sms'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
}
?>