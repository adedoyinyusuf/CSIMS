<?php
require_once __DIR__ . '/../includes/db.php';

class MessageController
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Get all messages (backed by notifications) with pagination and filters
     */
    public function getAllMessages(int $page = 1, int $limit = 10, string $search = '', string $filter = 'all'): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $where = 'WHERE 1=1';
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= ' AND (n.title LIKE ? OR n.message LIKE ?)';
            $searchLike = "%$search%";
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        if ($filter === 'read') {
            $where .= ' AND n.is_read = 1';
        } elseif ($filter === 'unread') {
            $where .= ' AND n.is_read = 0';
        }

        // Count total records
        $countSql = "SELECT COUNT(*) AS total FROM notifications n $where";
        $countStmt = $this->conn->prepare($countSql);
        if (!$countStmt) {
            return $this->emptyResult();
        }
        if ($types) { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalRecords = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        // Main query: map notifications to messages shape
        $sql = "SELECT 
                    n.notification_id AS message_id,
                    COALESCE(n.title, '') AS subject,
                    COALESCE(n.message, '') AS message,
                    n.is_read,
                    n.created_at,
                    n.recipient_type,
                    n.recipient_id,
                    CONCAT(a.first_name, ' ', a.last_name) AS sender_name,
                    'Admin' AS sender_type,
                    CASE 
                        WHEN n.recipient_type = 'Member' THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN n.recipient_type = 'Admin' THEN CONCAT(ad.first_name, ' ', ad.last_name)
                        ELSE 'All Users'
                    END AS recipient_name
                FROM notifications n
                LEFT JOIN admins a ON n.created_by = a.admin_id
                LEFT JOIN members m ON n.recipient_type = 'Member' AND n.recipient_id = m.member_id
                LEFT JOIN admins ad ON n.recipient_type = 'Admin' AND n.recipient_id = ad.admin_id
                $where 
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return $this->emptyResult();
        }
        $listTypes = $types . 'ii';
        $listParams = $params;
        $listParams[] = $limit;
        $listParams[] = $offset;
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $res = $stmt->get_result();
        $messages = [];
        while ($row = $res->fetch_assoc()) {
            // Normalize booleans
            $row['is_read'] = (int)($row['is_read'] ?? 0) === 1;
            $row['sender_type'] = 'Admin';
            $row['recipient_type'] = $row['recipient_type'] ?? 'All';
            $messages[] = $row;
        }
        $stmt->close();

        return [
            'messages' => $messages,
            'total_records' => $totalRecords,
            'total_pages' => (int)ceil($totalRecords / $limit),
            'current_page' => $page,
        ];
    }

    /**
     * Message statistics based on notifications table
     */
    public function getMessageStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total_messages,
                    COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_messages,
                    COALESCE(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END), 0) AS read_messages
                FROM notifications";
        $res = $this->conn->query($sql);
        if (!$res) {
            return [
                'total_messages' => 0,
                'unread_messages' => 0,
                'read_messages' => 0,
            ];
        }
        $row = $res->fetch_assoc() ?: [];
        return [
            'total_messages' => (int)($row['total_messages'] ?? 0),
            'unread_messages' => (int)($row['unread_messages'] ?? 0),
            'read_messages' => (int)($row['read_messages'] ?? 0),
        ];
    }

    private function emptyResult(): array
    {
        return [
            'messages' => [],
            'total_records' => 0,
            'total_pages' => 0,
            'current_page' => 1,
        ];
    }

    /**
     * Get a single message by ID.
     * Supports both messages (direct) and notifications (admin broadcasts),
     * returning a normalized shape compatible with admin view_message.php.
     */
    public function getMessageById(int $id): ?array
    {
        // Try notifications first (admin messages list maps notifications -> messages)
        $sqlNotif = "SELECT 
                n.notification_id AS message_id,
                COALESCE(n.title, '') AS subject,
                COALESCE(n.message, '') AS message,
                n.is_read,
                n.created_at,
                'Admin' AS sender_type,
                n.created_by AS sender_id,
                n.recipient_type,
                n.recipient_id,
                CONCAT(a.first_name, ' ', a.last_name) AS sender_name,
                CASE 
                    WHEN n.recipient_type = 'Member' THEN CONCAT(m.first_name, ' ', m.last_name)
                    WHEN n.recipient_type = 'Admin' THEN CONCAT(ad.first_name, ' ', ad.last_name)
                    ELSE 'All Users'
                END AS recipient_name
            FROM notifications n
            LEFT JOIN admins a ON n.created_by = a.admin_id
            LEFT JOIN members m ON n.recipient_type = 'Member' AND n.recipient_id = m.member_id
            LEFT JOIN admins ad ON n.recipient_type = 'Admin' AND n.recipient_id = ad.admin_id
            WHERE n.notification_id = ?";

        $stmt = $this->conn->prepare($sqlNotif);
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                $row['is_read'] = (int)($row['is_read'] ?? 0) === 1;
                return $row;
            }
            $stmt->close();
        }

        // Fallback: direct messages
        $sqlMsg = "SELECT m.*,
                CASE 
                    WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                    WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                END AS sender_name,
                CASE 
                    WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                    WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                END AS recipient_name
            FROM messages m
            LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
            LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
            LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
            LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
            WHERE m.message_id = ?";
        $stmt2 = $this->conn->prepare($sqlMsg);
        if (!$stmt2) { return null; }
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $row2 = $res2->fetch_assoc();
        $stmt2->close();
        if (!$row2) { return null; }
        $row2['is_read'] = (int)($row2['is_read'] ?? 0) === 1;
        return $row2;
    }

    /**
     * Create a new message (member/admin to member/admin)
     */
    public function createMessage(array $data)
    {
        $sql = "INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, message, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param(
            'sisiss',
            $data['sender_type'],
            $data['sender_id'],
            $data['recipient_type'],
            $data['recipient_id'],
            $data['subject'],
            $data['message']
        );
        $ok = $stmt->execute();
        $insertId = $ok ? $this->conn->insert_id : false;
        $stmt->close();
        return $insertId;
    }

    /**
     * Mark as read (supports notifications and messages)
     */
    public function markAsRead(int $message_id): bool
    {
        // Try notifications first (admin list)
        $sqlN = 'UPDATE notifications SET is_read = 1 WHERE notification_id = ?';
        $stmtN = $this->conn->prepare($sqlN);
        if ($stmtN) {
            $stmtN->bind_param('i', $message_id);
            $stmtN->execute();
            $affected = $stmtN->affected_rows;
            $stmtN->close();
            if ($affected > 0) { return true; }
        }

        // Fallback to messages (member inbox)
        $sqlM = 'UPDATE messages SET is_read = 1, read_at = NOW() WHERE message_id = ?';
        $stmtM = $this->conn->prepare($sqlM);
        if (!$stmtM) { return false; }
        $stmtM->bind_param('i', $message_id);
        $ok = $stmtM->execute();
        $stmtM->close();
        return $ok;
    }

    /**
     * Get all active members for recipient selection
     */
    public function getAllActiveMembers(): array
    {
        $sql = "SELECT m.member_id AS id, m.first_name, m.last_name, m.email, m.phone
                FROM members m
                WHERE m.status = 'Active'
                ORDER BY m.first_name, m.last_name";
        $res = $this->conn->query($sql);
        if (!$res) { return []; }
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        return $rows;
    }

    /**
     * Get members by status (e.g., Active, Expired)
     */
    public function getMembersByStatus(string $status): array
    {
        $sql = "SELECT m.member_id AS id, m.first_name, m.last_name, m.email, m.phone
                FROM members m
                WHERE m.status = ?
                ORDER BY m.first_name, m.last_name";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
        return $rows;
    }

    /**
     * Get members by IDs (for targeted selection)
     */
    public function getMembersByIds(array $ids): array
    {
        if (empty($ids)) { return []; }
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT m.member_id AS id, m.first_name, m.last_name, m.email, m.phone
                FROM members m
                WHERE m.member_id IN ($placeholders)
                ORDER BY m.first_name, m.last_name";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
        return $rows;
    }

    /**
     * Get members whose expiry_date is within the next N days
     */
    public function getExpiringMembers(int $days): array
    {
        $threshold = (new DateTime())->modify("+{$days} days")->format('Y-m-d H:i:s');
        $sql = "SELECT m.member_id AS id, m.first_name, m.last_name, m.email, m.phone, m.expiry_date
                FROM members m
                WHERE m.expiry_date IS NOT NULL AND m.expiry_date <= ? AND m.status != 'Deleted'
                ORDER BY m.expiry_date ASC, m.first_name, m.last_name";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('s', $threshold);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
        return $rows;
    }

    /**
     * Send bulk messages to a list of recipients.
     * $sender = ['sender_id' => int, 'sender_type' => 'Admin'|'Member']
     * $recipients = array of ['id' => int, 'first_name' => ..., 'last_name' => ...]
     * $message = ['subject' => string, 'message' => string]
     * Returns ['success' => n, 'failed' => m, 'errors' => []]
     */
    public function sendBulkMessages(array $sender, array $recipients, array $message): array
    {
        $senderType = $sender['sender_type'] ?? 'Admin';
        $senderId = (int)($sender['sender_id'] ?? 0);
        $subject = trim($message['subject'] ?? '');
        $body = trim($message['message'] ?? '');

        $result = ['success' => 0, 'failed' => 0, 'errors' => []];
        if ($senderId <= 0 || $subject === '' || $body === '' || empty($recipients)) {
            $result['errors'][] = 'Invalid sender, message, or recipients.';
            $result['failed'] = count($recipients);
            return $result;
        }

        $sql = "INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, message, is_read, created_at)
                VALUES (?, ?, 'Member', ?, ?, ?, 0, NOW())";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $result['errors'][] = 'Failed to prepare bulk insert statement.';
            $result['failed'] = count($recipients);
            return $result;
        }

        foreach ($recipients as $rec) {
            $recipientId = (int)($rec['id'] ?? 0);
            if ($recipientId <= 0) {
                $result['failed']++;
                $result['errors'][] = 'Invalid recipient record.';
                continue;
            }
            $stmt->bind_param('siiss', $senderType, $senderId, $recipientId, $subject, $body);
            if ($stmt->execute()) {
                $result['success']++;
            } else {
                $result['failed']++;
                $result['errors'][] = 'DB insert failed for recipient ID ' . $recipientId;
            }
        }

        $stmt->close();
        return $result;
    }

    /**
     * Communication statistics for portal widgets (messages table)
     */
    public function getCommunicationStatistics(): array
    {
        $stats = [
            'total_messages' => 0,
            'messages_today' => 0,
            'messages_this_week' => 0,
            'messages_this_month' => 0,
        ];

        // Total messages
        $res = $this->conn->query('SELECT COUNT(*) AS c FROM messages');
        if ($res) { $stats['total_messages'] = (int)($res->fetch_assoc()['c'] ?? 0); }

        // Today
        $res = $this->conn->query("SELECT COUNT(*) AS c FROM messages WHERE DATE(created_at) = CURDATE()");
        if ($res) { $stats['messages_today'] = (int)($res->fetch_assoc()['c'] ?? 0); }

        // This week (Monday as start)
        $res = $this->conn->query("SELECT COUNT(*) AS c FROM messages WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
        if ($res) { $stats['messages_this_week'] = (int)($res->fetch_assoc()['c'] ?? 0); }

        // This month
        $res = $this->conn->query("SELECT COUNT(*) AS c FROM messages WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
        if ($res) { $stats['messages_this_month'] = (int)($res->fetch_assoc()['c'] ?? 0); }

        return $stats;
    }

    /**
     * Recent messages for portal (messages only)
     */
    public function getRecentMessages(int $limit = 10): array
    {
        $limit = max(1, $limit);
        $sql = "SELECT m.message_id, m.subject, m.message, m.created_at,
                       COALESCE(m.priority, 'normal') AS priority,
                       CASE WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                            WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                            ELSE 'Unknown'
                       END AS recipient_name
                FROM messages m
                LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                ORDER BY m.created_at DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
        return $rows;
    }

    /**
     * Create announcement (if table exists)
     */
    public function createAnnouncement(array $data): bool
    {
        // Detect table existence quickly
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) {
            return false;
        }

        $sql = "INSERT INTO announcements (title, content, priority, target_audience, expiry_date, created_by, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return false; }
        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $priority = $data['priority'] ?? 'normal';
        $audience = $data['target_audience'] ?? 'all';
        $expiry = $data['expiry_date'] ?? null;
        $createdBy = (int)($data['created_by'] ?? 0);
        $status = $data['status'] ?? 'active';
        $stmt->bind_param('sssssis', $title, $content, $priority, $audience, $expiry, $createdBy, $status);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Get active announcements (if table exists)
     */
    public function getActiveAnnouncements(int $limit = 10): array
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) { return []; }

        $sql = "SELECT a.*, 
                       CASE WHEN a.created_by IS NOT NULL THEN a.created_by ELSE NULL END AS created_by_id
                FROM announcements a
                WHERE a.status = 'active' AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
                ORDER BY a.priority DESC, a.created_at DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
        return $rows;
    }

    /**
     * Search announcements with filters and pagination for admin listing.
     * Filters: status, priority, target_audience, expiry_from, expiry_to, search, page, per_page
     * Returns: [rows, total, pages, page, per_page]
     */
    public function searchAnnouncementsPaginated(array $filters = []): array
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) {
            return [
                'rows' => [],
                'total' => 0,
                'pages' => 0,
                'page' => 1,
                'per_page' => 10,
            ];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        $types = '';

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'a.status = ?';
            $params[] = (string)$filters['status'];
            $types .= 's';
        }
        // Priority filter
        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $where[] = 'a.priority = ?';
            $params[] = (string)$filters['priority'];
            $types .= 's';
        }
        // Audience filter
        if (!empty($filters['target_audience']) && $filters['target_audience'] !== 'all') {
            $where[] = 'a.target_audience = ?';
            $params[] = (string)$filters['target_audience'];
            $types .= 's';
        }
        // Expiry range
        if (!empty($filters['expiry_from'])) {
            $where[] = 'a.expiry_date IS NOT NULL AND a.expiry_date >= ?';
            $params[] = (string)$filters['expiry_from'];
            $types .= 's';
        }
        if (!empty($filters['expiry_to'])) {
            $where[] = 'a.expiry_date IS NOT NULL AND a.expiry_date <= ?';
            $params[] = (string)$filters['expiry_to'];
            $types .= 's';
        }
        // Search on title/content
        if (!empty($filters['search'])) {
            $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
            $q = '%' . $this->conn->real_escape_string($filters['search']) . '%';
            $params[] = $q;
            $params[] = $q;
            $types .= 'ss';
        }

        $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        // Count total
        $sqlCount = "SELECT COUNT(*) AS cnt FROM announcements a $whereSql";
        $stmtCount = $this->conn->prepare($sqlCount);
        if ($stmtCount) {
            if (!empty($params)) { $stmtCount->bind_param($types, ...$params); }
            $stmtCount->execute();
            $resCount = $stmtCount->get_result();
            $rowCount = $resCount->fetch_assoc();
            $total = (int)($rowCount['cnt'] ?? 0);
            $stmtCount->close();
        } else {
            $total = 0;
        }

        $pages = $perPage > 0 ? (int)ceil($total / $perPage) : 0;

        // Fetch rows
        $sql = "SELECT a.*
                FROM announcements a
                $whereSql
                ORDER BY a.priority DESC, a.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [ 'rows' => [], 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage ];
        }
        $typesRows = $types . 'ii';
        $paramsRows = $params;
        $paramsRows[] = $perPage;
        $paramsRows[] = $offset;
        $stmt->bind_param($typesRows, ...$paramsRows);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get announcements with optional status filter (admin view)
     */
    public function getAnnouncements(int $limit = 10, string $status = 'all'): array
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) { return []; }

        $limit = max(1, $limit);
        $where = '';
        if ($status !== 'all') {
            $where = "WHERE a.status = ?";
        }

        $sql = "SELECT a.* FROM announcements a $where ORDER BY a.priority DESC, a.created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        if ($status !== 'all') {
            $stmt->bind_param('si', $status, $limit);
        } else {
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
        return $rows;
    }

    /**
     * Get a single announcement by ID
     */
    public function getAnnouncementById(int $id): ?array
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) { return null; }

        $sql = "SELECT a.* FROM announcements a WHERE a.announcement_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return null; }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Update announcement fields
     */
    public function updateAnnouncement(int $id, array $data): bool
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) { return false; }

        $sql = "UPDATE announcements
                SET title = ?, content = ?, priority = ?, target_audience = ?, expiry_date = ?, status = ?, updated_at = NOW()
                WHERE announcement_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return false; }
        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $priority = $data['priority'] ?? 'normal';
        $audience = $data['target_audience'] ?? 'all';
        $expiry = $data['expiry_date'] ?? null;
        $status = $data['status'] ?? 'active';
        $stmt->bind_param('ssssssi', $title, $content, $priority, $audience, $expiry, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Set announcement status (e.g., archived, inactive)
     */
    public function setAnnouncementStatus(int $id, string $status): bool
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) { return false; }

        $sql = "UPDATE announcements SET status = ?, updated_at = NOW() WHERE announcement_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Delete announcement by ID
     */
    public function deleteAnnouncement(int $id): bool
    {
        $check = $this->conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$check || $check->num_rows === 0) { return false; }

        $sql = "DELETE FROM announcements WHERE announcement_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Admin: List direct messages only (exclude notifications)
     */
    public function getAllDirectMessages(int $page = 1, int $limit = 10, string $search = '', string $filter = 'all'): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $where = 'WHERE 1=1';
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= ' AND (m.subject LIKE ? OR m.message LIKE ?)';
            $searchLike = "%$search%";
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        if ($filter === 'read') {
            $where .= ' AND m.is_read = 1';
        } elseif ($filter === 'unread') {
            $where .= ' AND m.is_read = 0';
        }

        // Count
        $countSql = "SELECT COUNT(*) AS total FROM messages m $where";
        $countStmt = $this->conn->prepare($countSql);
        if (!$countStmt) { return $this->emptyResult(); }
        if ($types) { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $totalRecords = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        // Rows
        $sql = "SELECT m.message_id AS message_id,
                       m.subject, m.message, m.is_read, m.created_at,
                       m.sender_type, m.sender_id, m.recipient_type, m.recipient_id,
                       CASE WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                            WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                            ELSE 'Unknown' END AS sender_name,
                       CASE WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                            WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                            ELSE 'Unknown' END AS recipient_name
                FROM messages m
                LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                $where
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return $this->emptyResult(); }
        $listTypes = $types . 'ii';
        $listParams = $params;
        $listParams[] = $limit;
        $listParams[] = $offset;
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $row['is_read'] = (int)($row['is_read'] ?? 0) === 1;
            $rows[] = $row;
        }
        $stmt->close();

        return [
            'messages' => $rows,
            'total_records' => $totalRecords,
            'total_pages' => (int)ceil($totalRecords / $limit),
            'current_page' => $page,
        ];
    }

    /**
     * Admin: statistics for direct messages only
     */
    public function getDirectMessageStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total_messages,
                    COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_messages,
                    COALESCE(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END), 0) AS read_messages
                FROM messages";
        $res = $this->conn->query($sql);
        if (!$res) {
            return [
                'total_messages' => 0,
                'unread_messages' => 0,
                'read_messages' => 0,
            ];
        }
        $row = $res->fetch_assoc() ?: [];
        return [
            'total_messages' => (int)($row['total_messages'] ?? 0),
            'unread_messages' => (int)($row['unread_messages'] ?? 0),
            'read_messages' => (int)($row['read_messages'] ?? 0),
        ];
    }

    /**
     * Get unread message count for a member
     */
    public function getUnreadCountForMember(int $member_id): int
    {
        $sql = "SELECT COUNT(*) AS unread_count FROM messages
                WHERE recipient_type = 'Member' AND recipient_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return 0; }
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['unread_count' => 0];
        $stmt->close();
        return (int)($row['unread_count'] ?? 0);
    }

    /**
     * Get messages for a specific member with pagination, search and filter
     */
    public function getMessagesForMemberPaginated(int $member_id, int $page = 1, int $limit = 20, string $search = '', string $filter = 'all'): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        // Base where: messages where member is sender or recipient
        $where = "WHERE (m.sender_type = 'Member' AND m.sender_id = ?) OR (m.recipient_type = 'Member' AND m.recipient_id = ?)";
        $params = [$member_id, $member_id];
        $types = 'ii';

        // Optional search
        if ($search !== '') {
            $where .= " AND (m.subject LIKE ? OR m.message LIKE ?)";
            $searchLike = "%$search%";
            $params[] = $searchLike;
            $params[] = $searchLike;
            $types .= 'ss';
        }

        // Optional filter on read/unread (applies only when member is recipient)
        if ($filter === 'unread') {
            $where .= " AND m.recipient_type = 'Member' AND m.recipient_id = ? AND m.is_read = 0";
            $params[] = $member_id;
            $types .= 'i';
        } elseif ($filter === 'read') {
            $where .= " AND m.recipient_type = 'Member' AND m.recipient_id = ? AND m.is_read = 1";
            $params[] = $member_id;
            $types .= 'i';
        }

        // Count total
        $countSql = "SELECT COUNT(*) AS total FROM messages m $where";
        $countStmt = $this->conn->prepare($countSql);
        if (!$countStmt) {
            return ['messages' => [], 'pagination' => $this->buildPagination(0, $limit, $page)];
        }
        if ($types) { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $total = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        // Fetch page
        $sql = "SELECT m.*,
                        CASE 
                            WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
                            WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
                        END AS sender_name,
                        CASE 
                            WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
                            WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
                        END AS recipient_name
                 FROM messages m
                 LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
                 LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
                 LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
                 LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
                 $where
                 ORDER BY m.created_at DESC
                 LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['messages' => [], 'pagination' => $this->buildPagination($total, $limit, $page)];
        }
        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $res = $stmt->get_result();
        $messages = [];
        while ($row = $res->fetch_assoc()) {
            // Normalize and enhance
            $row['is_read'] = (int)($row['is_read'] ?? 0) === 1;
            // Compute message direction
            if (($row['sender_type'] === 'Member') && ((int)$row['sender_id'] === $member_id)) {
                $row['message_direction'] = 'sent';
            } elseif (($row['recipient_type'] === 'Member') && ((int)$row['recipient_id'] === $member_id)) {
                $row['message_direction'] = 'received';
            } else {
                $row['message_direction'] = 'received';
            }
            $messages[] = $row;
        }
        $stmt->close();

        return [
            'messages' => $messages,
            'pagination' => $this->buildPagination($total, $limit, $page),
        ];
    }

    private function buildPagination(int $total, int $limit, int $page): array
    {
        $total_pages = $limit > 0 ? (int)ceil($total / $limit) : 0;
        $offset = ($page - 1) * $limit;
        return [
            'total_items' => $total,
            'items_per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'offset' => $offset,
        ];
    }

    /**
     * Delete message/notification by ID (tries notifications first)
     */
    public function deleteMessage(int $id): bool
    {
        $sqlN = 'DELETE FROM notifications WHERE notification_id = ?';
        $stmtN = $this->conn->prepare($sqlN);
        if ($stmtN) {
            $stmtN->bind_param('i', $id);
            $stmtN->execute();
            $affected = $stmtN->affected_rows;
            $stmtN->close();
            if ($affected > 0) { return true; }
        }

        $sqlM = 'DELETE FROM messages WHERE message_id = ?';
        $stmtM = $this->conn->prepare($sqlM);
        if (!$stmtM) { return false; }
        $stmtM->bind_param('i', $id);
        $ok = $stmtM->execute();
        $stmtM->close();
        return $ok;
    }

    /**
     * Get all admins (for compose dropdown)
     */
    public function getAdmins(): array
    {
        $sql = 'SELECT admin_id, first_name, last_name, username FROM admins ORDER BY first_name, last_name';
        $res = $this->conn->query($sql);
        if (!$res) { return []; }
        $admins = [];
        while ($row = $res->fetch_assoc()) {
            $row['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $admins[] = $row;
        }
        return $admins;
    }
}
?>