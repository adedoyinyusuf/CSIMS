<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/utilities.php';

class MessageController {
    public $conn;
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
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
    
    /**
     * Get recent messages with enhanced details for communication portal
     */
    public function getRecentMessages($limit = 10) {
        try {
            $sql = "SELECT m.*, 
                           CASE 
                               WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                               WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                           END as sender_name,
                           CASE 
                               WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                               WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                           END as recipient_name,
                           'normal' as priority
                    FROM messages m
                    LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                    LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                    LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                    LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                    ORDER BY m.created_at DESC
                    LIMIT ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching recent messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get messages for a specific member (as sender or recipient)
     */
    // Fix SQL typo: ensure selecting all columns with m.* in paginated member messages
    public function getMessagesForMemberPaginated($member_id, $page = 1, $limit = 20, $search = '', $filter = 'all') {
        try {
            $offset = ($page - 1) * $limit;
            $where = "WHERE (m.sender_type = 'Member' AND m.sender_id = ?) OR (m.recipient_type = 'Member' AND m.recipient_id = ?)";
            $params = [$member_id, $member_id];
            $types = 'ii';
    
            if (!empty($search)) {
                $where .= " AND (m.subject LIKE ? OR m.message LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ss';
            }
    
            if ($filter === 'unread') {
                $where .= " AND m.recipient_type = 'Member' AND m.recipient_id = ? AND m.is_read = 0";
                $params[] = $member_id;
                $types .= 'i';
            } elseif ($filter === 'read') {
                $where .= " AND m.recipient_type = 'Member' AND m.recipient_id = ? AND m.is_read = 1";
                $params[] = $member_id;
                $types .= 'i';
            }
    
            $countSql = "SELECT COUNT(*) as total FROM messages m $where";
            $countStmt = $this->conn->prepare($countSql);
            if (!empty($params)) {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
            $countStmt->close();
    
            $sql = "SELECT m.*, 
                            CASE WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                                     WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                            END as sender_name,
                            CASE WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                                     WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                            END as recipient_name,
                            CASE WHEN m.sender_type = 'Member' AND m.sender_id = ? THEN 'sent'
                                     WHEN m.recipient_type = 'Member' AND m.recipient_id = ? THEN 'received'
                            END as message_direction
                            FROM messages m
                            LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                            LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                            LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                            LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                            $where
                            ORDER BY m.created_at DESC
                            LIMIT ? OFFSET ?";
            $stmt = $this->conn->prepare($sql);
            // Bind all params plus the two for message_direction and limit/offset
            $bindTypes = $types . 'ii' . 'ii';
            $bindParams = array_merge($params, [$member_id, $member_id, $limit, $offset]);
            $stmt->bind_param($bindTypes, ...$bindParams);
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
    
            $pagination = Utilities::paginate((int)$total, (int)$limit, (int)$page);
            return ['messages' => $messages, 'pagination' => $pagination];
        } catch (Exception $e) {
            error_log("Error fetching paginated member messages: " . $e->getMessage());
            $pagination = Utilities::paginate(0, (int)$limit, (int)$page);
            return ['messages' => [], 'pagination' => $pagination];
        }
    }
    
    // Backward-compatible non-paginated method
    public function getMessagesForMember($member_id, $limit = 20) {
        try {
            $where = "WHERE (m.sender_type = 'Member' AND m.sender_id = ?) OR (m.recipient_type = 'Member' AND m.recipient_id = ?)";
            $sql = "SELECT m.*, 
                            CASE 
                                WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                                WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                            END as sender_name,
                            CASE 
                                WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                                WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                            END as recipient_name,
                            CASE 
                                WHEN m.sender_type = 'Member' AND m.sender_id = ? THEN 'sent'
                                WHEN m.recipient_type = 'Member' AND m.recipient_id = ? THEN 'received'
                            END as message_direction
                     FROM messages m
                     LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                     LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                     LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                     LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                     $where
                     ORDER BY m.created_at DESC
                     LIMIT ?";
            $stmt = $this->conn->prepare($sql);
            $types = 'iiiii';
            $stmt->bind_param($types, $member_id, $member_id, $member_id, $member_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $messages;
        } catch (Exception $e) {
            error_log("Error fetching member messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread message count for a specific member
     */
    public function getUnreadCountForMember($member_id) {
        try {
            $sql = "SELECT COUNT(*) as unread_count FROM messages 
                     WHERE recipient_type = 'Member' AND recipient_id = ? AND is_read = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc();
            $stmt->close();
            return $count['unread_count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error fetching unread count for member: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create announcement
     */
    public function createAnnouncement($data) {
        try {
            $sql = "INSERT INTO announcements (title, content, priority, target_audience, expiry_date, created_by, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('sssssis', 
                $data['title'], 
                $data['content'], 
                $data['priority'], 
                $data['target_audience'], 
                $data['expiry_date'], 
                $data['created_by'], 
                $data['status']
            );
            
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Error creating announcement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active announcements
     */
    public function getActiveAnnouncements($limit = 10) {
        try {
            $sql = "SELECT a.*, u.username as created_by_name 
                    FROM announcements a 
                    LEFT JOIN users u ON a.created_by = u.id 
                    WHERE a.status = 'active' AND (a.expiry_date IS NULL OR a.expiry_date > NOW()) 
                    ORDER BY a.priority DESC, a.created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $announcements = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $announcements;
        } catch (Exception $e) {
            error_log("Error fetching active announcements: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send bulk messages to multiple recipients
     */
    public function sendBulkMessages($senderData, $recipients, $messageData) {
        try {
            $successCount = 0;
            $failedCount = 0;
            $errors = [];
            
            foreach ($recipients as $recipient) {
                 $data = [
                     'sender_type' => 'Admin',
                     'sender_id' => $senderData['sender_id'],
                     'recipient_type' => 'Member',
                     'recipient_id' => $recipient['id'],
                     'subject' => $messageData['subject'],
                     'message' => $messageData['message']
                 ];
                
                if ($this->createMessage($data)) {
                    $successCount++;
                } else {
                    $failedCount++;
                    $errors[] = "Failed to send message to {$recipient['first_name']} {$recipient['last_name']}";
                }
            }
            
            return [
                'success' => $successCount,
                'failed' => $failedCount,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            error_log("Error sending bulk messages: " . $e->getMessage());
            return [
                'success' => 0,
                'failed' => count($recipients),
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Get enhanced message statistics for communication portal
     */
    public function getCommunicationStatistics() {
        try {
            $stats = [];
            
            // Get basic message stats
            $basicStats = $this->getMessageStats();
            $stats['total_messages'] = $basicStats['total_messages'];
            $stats['unread_messages'] = $basicStats['unread_messages'];
            $stats['read_messages'] = $basicStats['read_messages'];
            
            // Messages today
            $sql = "SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()";
            $result = $this->conn->query($sql);
            $stats['messages_today'] = $result->fetch_assoc()['count'];
            
            // Messages this week
            $sql = "SELECT COUNT(*) as count FROM messages WHERE YEARWEEK(created_at) = YEARWEEK(NOW())";
            $result = $this->conn->query($sql);
            $stats['messages_this_week'] = $result->fetch_assoc()['count'];
            
            // Messages this month
            $sql = "SELECT COUNT(*) as count FROM messages WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
            $result = $this->conn->query($sql);
            $stats['messages_this_month'] = $result->fetch_assoc()['count'];
            
            // Active announcements (placeholder - would need announcements table)
            $stats['active_announcements'] = 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error fetching communication statistics: " . $e->getMessage());
            return [
                'total_messages' => 0,
                'messages_today' => 0,
                'messages_this_week' => 0,
                'messages_this_month' => 0,
                'active_announcements' => 0
            ];
        }
    }
    
    /**
     * Get all active members for messaging
     */
    public function getAllActiveMembers() {
        try {
            $sql = "SELECT member_id as id, first_name, last_name, email, phone 
                    FROM members 
                    WHERE status = 'Active' 
                    ORDER BY first_name, last_name";
            
            $result = $this->conn->query($sql);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching active members: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get members by status for targeted messaging
     */
    public function getMembersByStatus($status) {
        try {
            $sql = "SELECT member_id as id, first_name, last_name, email, phone 
                    FROM members 
                    WHERE status = ? 
                    ORDER BY first_name, last_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('s', $status);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching members by status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get members with expiring memberships
     */
    public function getExpiringMembers($days = 30) {
        try {
            $sql = "SELECT member_id as id, first_name, last_name, email, phone, membership_expiry 
                    FROM members 
                    WHERE status = 'Active' 
                    AND membership_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY membership_expiry, first_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $days);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching expiring members: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get members by IDs for bulk operations
     */
    public function getMembersByIds($ids) {
        try {
            if (empty($ids) || !is_array($ids)) {
                return [];
            }
            
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "SELECT member_id as id, first_name, last_name, email, phone 
                    FROM members 
                    WHERE member_id IN ($placeholders) 
                    ORDER BY first_name, last_name";
            
            $stmt = $this->conn->prepare($sql);
            $types = str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching members by IDs: " . $e->getMessage());
            return [];
        }
    }
}