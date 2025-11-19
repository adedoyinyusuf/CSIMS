<?php
require_once '../includes/config/database.php';

class CommunicationController {
    private $pdo;
    
    public function __construct() {
        $database = new PdoDatabase();
        $this->pdo = $database->getConnection();
    }
    
    // ===================================================================
    // ANNOUNCEMENTS MANAGEMENT
    // ===================================================================
    
    /**
     * Create new announcement
     */
    public function createAnnouncement($announcement_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (
                    title, content, priority, target_audience, expiry_date, created_by, status
                ) VALUES (
                    :title, :content, :priority, :target_audience, :expiry_date, :created_by, :status
                )
            ");
            
            $stmt->bindParam(':title', $announcement_data['title']);
            $stmt->bindParam(':content', $announcement_data['content']);
            $stmt->bindParam(':priority', $announcement_data['priority']);
            $stmt->bindParam(':target_audience', $announcement_data['target_audience']);
            $stmt->bindParam(':expiry_date', $announcement_data['expiry_date']);
            $stmt->bindParam(':created_by', $announcement_data['created_by']);
            $stmt->bindParam(':status', $announcement_data['status']);
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in createAnnouncement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get announcements for specific audience
     */
    public function getAnnouncementsForMember($member_id = null, $limit = 10) {
        try {
            $member_status = 'active'; // Default
            
            if ($member_id) {
                $stmt = $this->pdo->prepare("SELECT status FROM members WHERE member_id = :member_id");
                $stmt->bindParam(':member_id', $member_id);
                $stmt->execute();
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                $member_status = strtolower($member['status'] ?? 'active');
            }
            
            $stmt = $this->pdo->prepare("
                SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as created_by_name
                FROM announcements a
                LEFT JOIN members m ON a.created_by = m.member_id
                WHERE a.status = 'active'
                AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
                AND (a.target_audience = 'all' OR a.target_audience = :member_status)
                ORDER BY a.priority DESC, a.created_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindParam(':member_status', $member_status);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getAnnouncementsForMember: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all announcements for admin
     */
    public function getAllAnnouncements($limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as created_by_name
                FROM announcements a
                LEFT JOIN members m ON a.created_by = m.member_id
                ORDER BY a.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getAllAnnouncements: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update announcement
     */
    public function updateAnnouncement($announcement_id, $announcement_data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE announcements SET
                    title = :title,
                    content = :content,
                    priority = :priority,
                    target_audience = :target_audience,
                    expiry_date = :expiry_date,
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            
            $stmt->bindParam(':title', $announcement_data['title']);
            $stmt->bindParam(':content', $announcement_data['content']);
            $stmt->bindParam(':priority', $announcement_data['priority']);
            $stmt->bindParam(':target_audience', $announcement_data['target_audience']);
            $stmt->bindParam(':expiry_date', $announcement_data['expiry_date']);
            $stmt->bindParam(':status', $announcement_data['status']);
            $stmt->bindParam(':id', $announcement_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateAnnouncement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete announcement
     */
    public function deleteAnnouncement($announcement_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM announcements WHERE id = :id");
            $stmt->bindParam(':id', $announcement_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in deleteAnnouncement: " . $e->getMessage());
            return false;
        }
    }
    
    // ===================================================================
    // MESSAGE TEMPLATES MANAGEMENT
    // ===================================================================
    
    /**
     * Create message template
     */
    public function createMessageTemplate($template_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO message_templates (
                    name, subject, content, message_type, variables, created_by, status
                ) VALUES (
                    :name, :subject, :content, :message_type, :variables, :created_by, :status
                )
            ");
            
            $stmt->bindParam(':name', $template_data['name']);
            $stmt->bindParam(':subject', $template_data['subject']);
            $stmt->bindParam(':content', $template_data['content']);
            $stmt->bindParam(':message_type', $template_data['message_type']);
            $stmt->bindParam(':variables', $template_data['variables']);
            $stmt->bindParam(':created_by', $template_data['created_by']);
            $stmt->bindParam(':status', $template_data['status']);
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in createMessageTemplate: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all message templates
     */
    public function getMessageTemplates($type = null) {
        try {
            $sql = "SELECT * FROM message_templates WHERE status = 'active'";
            $params = [];
            
            if ($type) {
                $sql .= " AND message_type = :type";
                $params[':type'] = $type;
            }
            
            $sql .= " ORDER BY name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getMessageTemplates: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get template by ID
     */
    public function getMessageTemplate($template_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM message_templates WHERE id = :id");
            $stmt->bindParam(':id', $template_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getMessageTemplate: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process template variables
     */
    public function processTemplate($template_content, $variables) {
        try {
            $processed_content = $template_content;
            
            foreach ($variables as $key => $value) {
                $processed_content = str_replace('{' . $key . '}', $value, $processed_content);
            }
            
            return $processed_content;
        } catch (Exception $e) {
            error_log("Error in processTemplate: " . $e->getMessage());
            return $template_content;
        }
    }
    
    // ===================================================================
    // SCHEDULED MESSAGES
    // ===================================================================
    
    /**
     * Create scheduled message
     */
    public function createScheduledMessage($message_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO scheduled_messages (
                    sender_id, recipient_ids, subject, message, priority, 
                    message_type, scheduled_at, status
                ) VALUES (
                    :sender_id, :recipient_ids, :subject, :message, :priority, 
                    :message_type, :scheduled_at, :status
                )
            ");
            
            $stmt->bindParam(':sender_id', $message_data['sender_id']);
            $stmt->bindParam(':recipient_ids', $message_data['recipient_ids']);
            $stmt->bindParam(':subject', $message_data['subject']);
            $stmt->bindParam(':message', $message_data['message']);
            $stmt->bindParam(':priority', $message_data['priority']);
            $stmt->bindParam(':message_type', $message_data['message_type']);
            $stmt->bindParam(':scheduled_at', $message_data['scheduled_at']);
            $stmt->bindParam(':status', $message_data['status']);
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in createScheduledMessage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending scheduled messages
     */
    public function getPendingScheduledMessages($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM scheduled_messages 
                WHERE status = 'pending' 
                AND scheduled_at <= NOW()
                ORDER BY priority DESC, scheduled_at ASC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getPendingScheduledMessages: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update scheduled message status
     */
    public function updateScheduledMessageStatus($message_id, $status, $sent_at = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE scheduled_messages SET
                    status = :status,
                    sent_at = :sent_at
                WHERE id = :id
            ");
            
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':sent_at', $sent_at);
            $stmt->bindParam(':id', $message_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateScheduledMessageStatus: " . $e->getMessage());
            return false;
        }
    }
    
    // ===================================================================
    // DIRECT MESSAGES
    // ===================================================================
    
    /**
     * Send direct message
     */
    public function sendMessage($sender_id, $recipient_id, $subject, $message, $priority = 'normal') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (
                    sender_id, recipient_id, subject, message, priority, 
                    message_type, status, created_at
                ) VALUES (
                    :sender_id, :recipient_id, :subject, :message, :priority, 
                    'general', 'sent', NOW()
                )
            ");
            
            $stmt->bindParam(':sender_id', $sender_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':priority', $priority);
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in sendMessage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send bulk message
     */
    public function sendBulkMessage($sender_id, $recipient_ids, $subject, $message, $priority = 'normal') {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (
                    sender_id, recipient_id, subject, message, priority, 
                    message_type, status, created_at
                ) VALUES (
                    :sender_id, :recipient_id, :subject, :message, :priority, 
                    'general', 'sent', NOW()
                )
            ");
            
            $message_ids = [];
            foreach ($recipient_ids as $recipient_id) {
                $stmt->bindParam(':sender_id', $sender_id);
                $stmt->bindParam(':recipient_id', $recipient_id);
                $stmt->bindParam(':subject', $subject);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':priority', $priority);
                
                $stmt->execute();
                $message_ids[] = $this->pdo->lastInsertId();
            }
            
            $this->pdo->commit();
            return $message_ids;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error in sendBulkMessage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get messages for user
     */
    public function getMessagesForUser($user_id, $user_type = 'member', $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       CASE 
                           WHEN m.sender_id = 0 THEN 'System'
                           ELSE CONCAT(sender.first_name, ' ', sender.last_name)
                       END as sender_name,
                       CONCAT(recipient.first_name, ' ', recipient.last_name) as recipient_name
                FROM messages m
                LEFT JOIN members sender ON m.sender_id = sender.member_id
                LEFT JOIN members recipient ON m.recipient_id = recipient.member_id
                WHERE m.recipient_id = :user_id
                ORDER BY m.created_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getMessagesForUser: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark message as read
     */
    public function markMessageAsRead($message_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages SET 
                    is_read = 1,
                    read_at = NOW(),
                    status = 'read'
                WHERE message_id = :message_id 
                AND recipient_id = :user_id
            ");
            
            $stmt->bindParam(':message_id', $message_id);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in markMessageAsRead: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count
     */
    public function getUnreadMessageCount($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM messages 
                WHERE recipient_id = :user_id 
                AND is_read = 0
            ");
            
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error in getUnreadMessageCount: " . $e->getMessage());
            return 0;
        }
    }
    
    // ===================================================================
    // NOTIFICATION MANAGEMENT
    // ===================================================================
    
    /**
     * Create notification
     */
    public function createNotification($notification_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_queue (
                    recipient_type, recipient_id, notification_type, title, message, 
                    data_payload, delivery_methods, priority, scheduled_at
                ) VALUES (
                    :recipient_type, :recipient_id, :notification_type, :title, :message, 
                    :data_payload, :delivery_methods, :priority, :scheduled_at
                )
            ");
            
            $stmt->bindParam(':recipient_type', $notification_data['recipient_type']);
            $stmt->bindParam(':recipient_id', $notification_data['recipient_id']);
            $stmt->bindParam(':notification_type', $notification_data['notification_type']);
            $stmt->bindParam(':title', $notification_data['title']);
            $stmt->bindParam(':message', $notification_data['message']);
            $stmt->bindParam(':data_payload', $notification_data['data_payload']);
            $stmt->bindParam(':delivery_methods', $notification_data['delivery_methods']);
            $stmt->bindParam(':priority', $notification_data['priority']);
            $stmt->bindParam(':scheduled_at', $notification_data['scheduled_at']);
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in createNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending notifications
     */
    public function getPendingNotifications($limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_queue 
                WHERE delivery_status = 'pending' 
                AND scheduled_at <= NOW()
                ORDER BY priority DESC, scheduled_at ASC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getPendingNotifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update notification status
     */
    public function updateNotificationStatus($notification_id, $status, $error_message = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notification_queue SET
                    delivery_status = :status,
                    delivery_attempts = delivery_attempts + 1,
                    last_attempt_at = NOW(),
                    delivered_at = CASE WHEN :status = 'sent' THEN NOW() ELSE delivered_at END,
                    error_message = :error_message
                WHERE notification_id = :id
            ");
            
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':error_message', $error_message);
            $stmt->bindParam(':id', $notification_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateNotificationStatus: " . $e->getMessage());
            return false;
        }
    }
    
    // ===================================================================
    // UTILITY METHODS
    // ===================================================================
    
    /**
     * Get recipient list based on criteria
     */
    public function getRecipientList($criteria) {
        try {
            $sql = "SELECT member_id, first_name, last_name, email, phone FROM members WHERE 1=1";
            $params = [];
            
            if (isset($criteria['status'])) {
                $sql .= " AND status = :status";
                $params[':status'] = $criteria['status'];
            }
            
            if (isset($criteria['membership_type'])) {
                $sql .= " AND membership_type = :membership_type";
                $params[':membership_type'] = $criteria['membership_type'];
            }
            
            if (isset($criteria['join_date_from'])) {
                $sql .= " AND join_date >= :join_date_from";
                $params[':join_date_from'] = $criteria['join_date_from'];
            }
            
            if (isset($criteria['join_date_to'])) {
                $sql .= " AND join_date <= :join_date_to";
                $params[':join_date_to'] = $criteria['join_date_to'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getRecipientList: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log message activity
     */
    public function logMessageActivity($message_id, $action, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO message_activity_log (message_id, action, user_id)
                VALUES (:message_id, :action, :user_id)
            ");
            
            $stmt->bindParam(':message_id', $message_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in logMessageActivity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get communication statistics
     */
    public function getCommunicationStats($period = '30_days') {
        try {
            $date_condition = match($period) {
                '7_days' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                '30_days' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                '90_days' => 'created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
                '1_year' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)',
                default => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
            };
            
            // Announcements stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_announcements,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_announcements,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
                FROM announcements 
                WHERE $date_condition
            ");
            $stmt->execute();
            $announcement_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Message stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_messages,
                    COUNT(DISTINCT recipient_id) as unique_recipients
                FROM messages 
                WHERE $date_condition
            ");
            $stmt->execute();
            $message_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Notification stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN delivery_status = 'sent' THEN 1 ELSE 0 END) as sent_notifications,
                    SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed_notifications
                FROM notification_queue 
                WHERE $date_condition
            ");
            $stmt->execute();
            $notification_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'period' => $period,
                'announcements' => $announcement_stats,
                'messages' => $message_stats,
                'notifications' => $notification_stats
            ];
        } catch (Exception $e) {
            error_log("Error in getCommunicationStats: " . $e->getMessage());
            return false;
        }
    }
}
