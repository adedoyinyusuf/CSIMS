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
}
?>