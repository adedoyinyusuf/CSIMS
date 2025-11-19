<?php

// Legacy-compatible NotificationController shim using modern bootstrap/container

require_once __DIR__ . '/../src/bootstrap.php';

use CSIMS\Container\Container;

class NotificationController
{
    private Container $container;
    private mysqli $conn;

    // Accept optional legacy $conn; fallback to container-managed mysqli
    public function __construct($conn = null)
    {
        $this->container = CSIMS\bootstrap();
        $this->conn = $conn instanceof mysqli ? $conn : $this->container->resolve(mysqli::class);
    }

    /**
     * Get unread notifications count for a specific member (includes broadcasts)
     *
     * @param int $member_id Member ID
     * @return int Unread count
     */
    public function getMemberUnreadCount($member_id)
    {
        try {
            $member_id = (int)$member_id;
            $sql = "SELECT COALESCE(COUNT(*), 0) AS cnt FROM notifications n
                    WHERE n.is_read = 0 AND (n.recipient_type = 'All' OR (n.recipient_type = 'Member' AND n.recipient_id = ?))";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new \RuntimeException('Prepare failed: ' . $this->conn->error);
            }
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            return (int)($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            error_log('NotificationController shim getMemberUnreadCount error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get notifications for a specific member (legacy-compatible shape)
     *
     * @param int $member_id Member ID
     * @param int $limit Number of notifications to return
     * @return array|false Member notifications or false on error
     */
    public function getMemberNotifications($member_id, $limit = 10)
    {
        try {
            $member_id = (int)$member_id;
            $limit = (int)$limit;

            $sql = "SELECT n.*, 
                    CONCAT(a.first_name, ' ', a.last_name) as created_by_name
                    FROM notifications n 
                    LEFT JOIN admins a ON n.created_by = a.admin_id 
                    WHERE (n.recipient_type = 'All' OR (n.recipient_type = 'Member' AND n.recipient_id = ?))
                    ORDER BY n.created_at DESC 
                    LIMIT ?";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new \RuntimeException('Prepare failed: ' . $this->conn->error);
            }
            $stmt->bind_param('ii', $member_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();

            return $notifications;
        } catch (\Throwable $e) {
            error_log('NotificationController shim getMemberNotifications error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin: Get all notifications with pagination and search
     * Matches legacy return shape used by admin/views
     */
    public function getAllNotifications($page = 1, $limit = 10, $search = '', $filter = 'all')
    {
        try {
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;

            $where = 'WHERE 1=1';
            $params = [];
            $types = '';

            if (!empty($search)) {
                $where .= ' AND (n.title LIKE ? OR n.message LIKE ?)';
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'ss';
            }

            if (!empty($filter) && $filter !== 'all') {
                $where .= ' AND n.notification_type = ?';
                $params[] = $filter;
                $types .= 's';
            }

            // Count total
            $countSql = "SELECT COUNT(*) AS total FROM notifications n $where";
            $countStmt = $this->conn->prepare($countSql);
            if (!$countStmt) {
                throw new \RuntimeException('Prepare failed (count): ' . $this->conn->error);
            }
            if (!empty($params)) {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $totalRecords = (int)($countRes->fetch_assoc()['total'] ?? 0);
            $countStmt->close();

            // Fetch page
            $sql = "SELECT n.*, CONCAT(a.first_name, ' ', a.last_name) AS created_by_name
                    FROM notifications n
                    LEFT JOIN admins a ON n.created_by = a.admin_id
                    $where
                    ORDER BY n.created_at DESC
                    LIMIT ? OFFSET ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new \RuntimeException('Prepare failed (list): ' . $this->conn->error);
            }
            $listParams = $params;
            $listTypes = $types . 'ii';
            $listParams[] = $limit;
            $listParams[] = $offset;
            $stmt->bind_param($listTypes, ...$listParams);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();

            return [
                'notifications' => $notifications,
                'total_records' => $totalRecords,
                'total_pages' => (int)ceil($totalRecords / $limit),
                'current_page' => $page,
            ];
        } catch (\Throwable $e) {
            error_log('NotificationController shim getAllNotifications error: ' . $e->getMessage());
            return [
                'notifications' => [],
                'total_records' => 0,
                'total_pages' => 0,
                'current_page' => 1,
            ];
        }
    }

    /**
     * Admin: Get notification stats (total, unread, read, broadcast)
     */
    public function getNotificationStats()
    {
        try {
            $sql = "SELECT 
                        COUNT(*) AS total_notifications,
                        COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_notifications,
                        COALESCE(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END), 0) AS read_notifications,
                        COALESCE(SUM(CASE WHEN recipient_type = 'All' THEN 1 ELSE 0 END), 0) AS broadcast_notifications
                    FROM notifications";
            $res = $this->conn->query($sql);
            return $res ? ($res->fetch_assoc() ?: []) : [];
        } catch (\Throwable $e) {
            error_log('NotificationController shim getNotificationStats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Admin: Get notification types (legacy hardcoded mapping)
     */
    public function getNotificationTypes()
    {
        return [
            'Payment' => 'Payment Related',
            'Meeting' => 'Meeting Announcement',
            'Policy' => 'Policy Update',
            'General' => 'General Information',
        ];
    }

    /**
     * Admin: Recipient types for dropdown
     */
    public function getRecipientTypes()
    {
        return [
            'All' => 'All Users',
            'Member' => 'Specific Member',
            'Admin' => 'Specific Admin',
        ];
    }

    /**
     * Admin: List active members for recipient selection
     */
    public function getMembers()
    {
        try {
            $sql = "SELECT member_id, CONCAT(first_name, ' ', last_name) AS name
                    FROM members WHERE status = 'Active' ORDER BY first_name";
            $res = $this->conn->query($sql);
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) { $rows[] = $row; }
            }
            return $rows;
        } catch (\Throwable $e) {
            error_log('NotificationController shim getMembers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Admin: List active admins for recipient selection
     */
    public function getAdmins()
    {
        try {
            $sql = "SELECT admin_id, CONCAT(first_name, ' ', last_name) AS name
                    FROM admins WHERE status = 'Active' ORDER BY first_name";
            $res = $this->conn->query($sql);
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) { $rows[] = $row; }
            }
            return $rows;
        } catch (\Throwable $e) {
            error_log('NotificationController shim getAdmins error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Admin: Get notification by ID
     */
    public function getNotificationById($notification_id)
    {
        try {
            $sql = "SELECT n.*, CONCAT(a.first_name, ' ', a.last_name) AS created_by_name
                    FROM notifications n
                    LEFT JOIN admins a ON n.created_by = a.admin_id
                    WHERE n.notification_id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->conn->error); }
            $nid = (int)$notification_id;
            $stmt->bind_param('i', $nid);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('NotificationController shim getNotificationById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Admin: Create a new notification
     */
    public function createNotification($data)
    {
        try {
            $sql = "INSERT INTO notifications
                        (title, message, recipient_type, recipient_id, notification_type, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->conn->error); }
            $title = (string)($data['title'] ?? '');
            $message = (string)($data['message'] ?? '');
            $recipient_type = (string)($data['recipient_type'] ?? 'All');
            $recipient_id = isset($data['recipient_id']) ? (int)$data['recipient_id'] : null;
            $notification_type = (string)($data['notification_type'] ?? 'General');
            $created_by = isset($data['created_by']) ? (int)$data['created_by'] : null;
            // bind_param requires an int for recipient_id; use 0 when null to maintain legacy compatibility
            $rid = $recipient_id ?? 0;
            $cb = $created_by ?? 0;
            $stmt->bind_param('sssisi', $title, $message, $recipient_type, $rid, $notification_type, $cb);
            $ok = $stmt->execute();
            $id = $this->conn->insert_id;
            $stmt->close();
            return $ok ? $id : false;
        } catch (\Throwable $e) {
            error_log('NotificationController shim createNotification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin: Update existing notification
     */
    public function updateNotification($notification_id, $data)
    {
        try {
            $sql = "UPDATE notifications SET
                        title = ?, message = ?, recipient_type = ?, recipient_id = ?, notification_type = ?
                    WHERE notification_id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->conn->error); }
            $title = (string)($data['title'] ?? '');
            $message = (string)($data['message'] ?? '');
            $recipient_type = (string)($data['recipient_type'] ?? 'All');
            $recipient_id = isset($data['recipient_id']) ? (int)$data['recipient_id'] : 0;
            $notification_type = (string)($data['notification_type'] ?? 'General');
            $nid = (int)$notification_id;
            $stmt->bind_param('sssisi', $title, $message, $recipient_type, $recipient_id, $notification_type, $nid);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (\Throwable $e) {
            error_log('NotificationController shim updateNotification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin: Delete a notification
     */
    public function deleteNotification($notification_id)
    {
        try {
            $sql = 'DELETE FROM notifications WHERE notification_id = ?';
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->conn->error); }
            $nid = (int)$notification_id;
            $stmt->bind_param('i', $nid);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (\Throwable $e) {
            error_log('NotificationController shim deleteNotification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin: Mark notification as read
     */
    public function markAsRead($notification_id)
    {
        try {
            $sql = 'UPDATE notifications SET is_read = 1 WHERE notification_id = ?';
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->conn->error); }
            $nid = (int)$notification_id;
            $stmt->bind_param('i', $nid);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (\Throwable $e) {
            error_log('NotificationController shim markAsRead error: ' . $e->getMessage());
            return false;
        }
    }
}