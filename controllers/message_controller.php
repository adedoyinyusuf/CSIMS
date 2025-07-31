<?php
require_once '../config/database.php';

class MessageController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function getAllMessages($page = 1, $limit = 10, $search = '', $filter = 'all') {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        // Add search conditions
        if (!empty($search)) {
            $where_clause .= " AND (m.subject LIKE ? OR m.message LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param]);
            $types .= "ss";
        }
        
        // Add filter conditions
        if ($filter !== 'all') {
            if ($filter === 'unread') {
                $where_clause .= " AND m.is_read = 0";
            } elseif ($filter === 'read') {
                $where_clause .= " AND m.is_read = 1";
            }
        }
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total FROM messages m $where_clause";
        $count_stmt = $this->conn->prepare($count_sql);
        
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
        
        // Get paginated results
        $sql = "SELECT m.*, 
                CASE 
                    WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                    WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                END as sender_name,
                CASE 
                    WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                    WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                END as recipient_name
                FROM messages m 
                LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                $where_clause 
                ORDER BY m.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        $stmt->close();
        
        return [
            'messages' => $messages,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit),
            'current_page' => $page
        ];
    }
    
    public function getMessageById($message_id) {
        $sql = "SELECT m.*, 
                CASE 
                    WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                    WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                END as sender_name,
                CASE 
                    WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                    WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                END as recipient_name
                FROM messages m 
                LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                WHERE m.message_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $message = $result->fetch_assoc();
        $stmt->close();
        
        return $message;
    }
    
    public function createMessage($data) {
        $sql = "INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, message) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sisiss", 
            $data['sender_type'],
            $data['sender_id'],
            $data['recipient_type'],
            $data['recipient_id'],
            $data['subject'],
            $data['message']
        );
        
        $result = $stmt->execute();
        $message_id = $this->conn->insert_id;
        $stmt->close();
        
        return $result ? $message_id : false;
    }
    
    public function markAsRead($message_id) {
        $sql = "UPDATE messages SET is_read = 1 WHERE message_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $message_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function deleteMessage($message_id) {
        $sql = "DELETE FROM messages WHERE message_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $message_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function getMessageStats() {
        $sql = "SELECT 
                COUNT(*) as total_messages,
                COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) as unread_messages,
                COALESCE(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END), 0) as read_messages
                FROM messages";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    public function getMembers() {
        $sql = "SELECT member_id, CONCAT(first_name, ' ', last_name) as name FROM members WHERE status = 'Active' ORDER BY first_name";
        $result = $this->conn->query($sql);
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    
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