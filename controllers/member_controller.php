<?php

// Legacy-compatible MemberController shim that leverages modern repositories

require_once __DIR__ . '/../src/bootstrap.php';

use CSIMS\Container\Container;
use CSIMS\Repositories\MemberRepository;

class MemberController
{
    private Container $container;
    private MemberRepository $memberRepository;
    private mysqli $connection;

    public function __construct()
    {
        $this->container = CSIMS\bootstrap();
        $this->memberRepository = $this->container->resolve(MemberRepository::class);
        $this->connection = $this->container->resolve(mysqli::class);
    }

    /**
     * Legacy API expected by views: return associative array for a member.
     *
     * @param int $memberId
     * @return array
     */
    public function getMemberById($memberId): array
    {
        try {
            $memberId = (int)$memberId;
            
            // Use direct SQL query to get member with membership type name
            // This ensures compatibility with legacy views that expect 'membership_type' field
            $stmt = $this->connection->prepare("
                SELECT m.*, 
                       mt.name as membership_type, 
                       mt.fee as membership_fee,
                       mt2.type_name AS member_type_label 
                FROM members m 
                LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                LEFT JOIN member_types mt2 ON mt2.type_id = m.member_type_id 
                WHERE m.member_id = ?
            ");
            
            if (!$stmt) {
                error_log('MemberController getMemberById: Failed to prepare statement');
                return [];
            }
            
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                return $result->fetch_assoc();
            } else {
                return [];
            }
        } catch (\Throwable $e) {
            error_log('MemberController shim getMemberById error: ' . $e->getMessage());
            return [];
        }
    }

    // Legacy endpoints used by admin views

    public function getMembershipTypes(): array
    {
        try {
            $sql = "SELECT * FROM membership_types ORDER BY fee ASC";
            $res = $this->connection->query($sql);
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) { $rows[] = $row; }
            }
            return $rows;
        } catch (\Throwable $e) {
            error_log('MemberController shim getMembershipTypes error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active members for selection dropdowns in admin views
     * Returns associative arrays with keys: member_id, first_name, last_name
     */
    public function getAllActiveMembers(): array
    {
        try {
            $sql = "SELECT m.member_id, m.first_name, m.last_name, m.email, m.phone
                    FROM members m
                    WHERE m.status = 'Active'
                    ORDER BY m.first_name, m.last_name";
            $res = $this->connection->query($sql);
            if (!$res) { return []; }
            $rows = [];
            while ($row = $res->fetch_assoc()) { $rows[] = $row; }
            return $rows;
        } catch (\Throwable $e) {
            error_log('MemberController shim getAllActiveMembers error: ' . $e->getMessage());
            return [];
        }
    }

    public function getPendingMembers(): array
    {
        try {
            $sql = "SELECT m.*, mt.name as membership_type FROM members m JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id WHERE m.status = 'Pending' ORDER BY m.join_date DESC";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) { $rows[] = $row; }
            }
            $stmt->close();
            return $rows;
        } catch (\Throwable $e) {
            error_log('MemberController shim getPendingMembers error: ' . $e->getMessage());
            return [];
        }
    }

    public function approveMember($memberId): array
    {
        try {
            $sql = "UPDATE members SET status = 'Active' WHERE member_id = ? AND status = 'Pending'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bind_param('i', $memberId);
            $ok = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($ok && $affected > 0) {
                return ['success' => true, 'message' => 'Member approved successfully'];
            }
            return ['success' => false, 'message' => 'Failed to approve member or member not found'];
        } catch (\Throwable $e) {
            error_log('MemberController shim approveMember error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error approving member'];
        }
    }

    public function rejectMember($memberId): array
    {
        try {
            $sql = "UPDATE members SET status = 'Rejected' WHERE member_id = ? AND status = 'Pending'";
            $stmt = $this->connection->prepare($sql);
            $stmt->bind_param('i', $memberId);
            $ok = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($ok && $affected > 0) {
                return ['success' => true, 'message' => 'Member registration rejected'];
            }
            return ['success' => false, 'message' => 'Failed to reject member or member not found'];
        } catch (\Throwable $e) {
            error_log('MemberController shim rejectMember error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error rejecting member'];
        }
    }

    public function getAllMembers(int $page = 1, ?string $search = null, ?string $status = null, ?string $membershipType = null, int $perPage = 20): array
    {
        $conn = $this->connection;
        $offset = max(0, ($page - 1) * $perPage);

        // Build WHERE clause
        $where = [];

        if (!empty($search)) {
            $like = '%' . $conn->real_escape_string($search) . '%';
            $where[] = "(m.first_name LIKE '$like' OR m.last_name LIKE '$like' OR m.email LIKE '$like' OR m.phone LIKE '$like')";
        }
        if (!empty($status)) {
            $statusEsc = $conn->real_escape_string($status);
            $where[] = "m.status = '$statusEsc'";
        }
        if (!empty($membershipType)) {
            $typeEsc = $conn->real_escape_string($membershipType);
            // Filter by membership type using the modern schema
            $where[] = "(m.membership_type_id = '$typeEsc' OR mt.membership_type_id = '$typeEsc' OR mt.name = '$typeEsc')";
        }

        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Count total
        $countSql = "SELECT COUNT(*) AS total
                     FROM members m
                     LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id
                     $whereSql";
        $total = 0;
        if ($res = $conn->query($countSql)) {
            $row = $res->fetch_assoc();
            $total = (int)($row['total'] ?? 0);
            $res->free();
        }

        // Fetch rows
        $sql = "SELECT m.member_id AS member_id, m.first_name, m.last_name, m.gender, m.email, m.phone,
                       mt.name AS member_type_label,
                       m.join_date, m.expiry_date, m.status, m.photo
                FROM members m
                LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id
                $whereSql
                ORDER BY m.created_at DESC, m.member_id DESC
                LIMIT $perPage OFFSET $offset";

        $items = [];
        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            $result->free();
        }

        return [
            'members' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
                'total_items' => $total,
            ],
        ];
    }

    public function getMemberStatistics(): array
    {
        $conn = $this->connection;
        $stats = [
            'active_members' => 0,
            'new_members_this_month' => 0,
            'total_members' => 0,
        ];

        // Total
        if ($res = $conn->query("SELECT COUNT(*) AS c FROM members")) {
            $row = $res->fetch_assoc();
            $stats['total_members'] = (int)($row['c'] ?? 0);
            $res->free();
        }
        // Active
        if ($res = $conn->query("SELECT COUNT(*) AS c FROM members WHERE status = 'Active'")) {
            $row = $res->fetch_assoc();
            $stats['active_members'] = (int)($row['c'] ?? 0);
            $res->free();
        }
        // New this month
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $msEsc = $conn->real_escape_string($monthStart);
        $meEsc = $conn->real_escape_string($monthEnd);
        if ($res = $conn->query("SELECT COUNT(*) AS c FROM members WHERE join_date BETWEEN '$msEsc' AND '$meEsc'")) {
            $row = $res->fetch_assoc();
            $stats['new_members_this_month'] = (int)($row['c'] ?? 0);
            $res->free();
        }

        return $stats;
    }

    /**
     * Check if email already exists
     */
    public function checkExistingMember(string $email): bool
    {
        try {
            $sql = "SELECT member_id FROM members WHERE email = ? LIMIT 1";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) { return false; }
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (\Throwable $e) {
            error_log('MemberController shim checkExistingMember error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if username already exists
     */
    public function checkExistingUsername(string $username): bool
    {
        try {
            $sql = "SELECT member_id FROM members WHERE username = ? LIMIT 1";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) { return false; }
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (\Throwable $e) {
            error_log('MemberController shim checkExistingUsername error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if IPPIS number already exists
     */
    public function checkExistingIppis(string $ippisNo): bool
    {
        try {
            $sql = "SELECT member_id FROM members WHERE ippis_no = ? LIMIT 1";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) { return false; }
            $stmt->bind_param('s', $ippisNo);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (\Throwable $e) {
            error_log('MemberController shim checkExistingIppis error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy-compatible registration for self-registration flow
     * Returns boolean for compatibility with existing view logic
     */
    public function registerMember(array $data): bool
    {
        try {
            // Basic validation and sanitization (view performs additional checks)
            $ippis_no = trim($data['ippis_no'] ?? '');
            $username = trim($data['username'] ?? '');
            $password = (string)($data['password'] ?? '');
            $first_name = trim($data['first_name'] ?? '');
            $last_name = trim($data['last_name'] ?? '');
            $dob = trim($data['dob'] ?? '');
            $gender = trim($data['gender'] ?? '');
            $address = trim($data['address'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $email = trim($data['email'] ?? '');
            $occupation = trim($data['occupation'] ?? '');
            $membership_type_id = (int)($data['membership_type_id'] ?? 0);
            $monthly_contribution = 0;

            // New optional extended fields
            $marital_status = trim($data['marital_status'] ?? '');
            $bank_name = trim($data['bank_name'] ?? '');
            $account_number = trim($data['account_number'] ?? '');
            $account_name = trim($data['account_name'] ?? '');
            $next_of_kin_name = trim($data['next_of_kin_name'] ?? '');
            $next_of_kin_relationship = trim($data['next_of_kin_relationship'] ?? '');
            $next_of_kin_phone = trim($data['next_of_kin_phone'] ?? '');
            $next_of_kin_address = trim($data['next_of_kin_address'] ?? '');
            if ($ippis_no === '' || !preg_match('/^[0-9]{6}$/', $ippis_no)) {
                return false;
            }
            if ($this->checkExistingIppis($ippis_no)) {
                return false;
            }
            if ($bank_name === '' || $account_number === '' || $account_name === '') {
                return false;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            if (empty($password) || strlen($password) < 8) {
                return false;
            }

            // Calculate expiry date based on membership type duration (months)
            $duration = 0;
            $stmtType = $this->connection->prepare("SELECT duration FROM membership_types WHERE membership_type_id = ?");
            if (!$stmtType) { return false; }
            $stmtType->bind_param('i', $membership_type_id);
            $stmtType->execute();
            $typeRes = $stmtType->get_result();
            if ($typeRes && $typeRes->num_rows > 0) {
                $row = $typeRes->fetch_assoc();
                $duration = (int)($row['duration'] ?? 0);
            }
            $stmtType->close();
            if ($duration <= 0) { return false; }

            $join_date = date('Y-m-d');
            $expiry_date = date('Y-m-d', strtotime("+{$duration} months"));
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Updated insert to include extended fields
            $sql = "INSERT INTO members (
                        ippis_no, username, password, first_name, last_name, dob, gender, address, phone, email, occupation,
                        membership_type_id, marital_status, bank_name, account_number, account_name,
                        next_of_kin_name, next_of_kin_relationship, next_of_kin_phone, next_of_kin_address,
                        join_date, expiry_date, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending'
                    )";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) { return false; }
            $stmt->bind_param(
                'sssssssssssisssssssssss',
                $ippis_no,
                $username,
                $password_hash,
                $first_name,
                $last_name,
                $dob,
                $gender,
                $address,
                $phone,
                $email,
                $occupation,
                $membership_type_id,
                $marital_status,
                $bank_name,
                $account_number,
                $account_name,
                $next_of_kin_name,
                $next_of_kin_relationship,
                $next_of_kin_phone,
                $next_of_kin_address,
                $join_date,
                $expiry_date
            );
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (\Throwable $e) {
            error_log('MemberController shim registerMember error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add new member (admin-side creation)
     * Accepts the same fields posted by views/admin/add_member.php
     * Returns an associative array with success/message and optional member_id
     */
    public function addMember(array $data): array
    {
        try {
            $ippis_no = trim($data['ippis_no'] ?? '');
            $first_name = trim($data['first_name'] ?? '');
            $last_name = trim($data['last_name'] ?? '');
            $dob = trim(($data['date_of_birth'] ?? $data['dob'] ?? ''));
            $gender = trim($data['gender'] ?? '');
            $address = trim($data['address'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $email = trim($data['email'] ?? '');
            $occupation = trim($data['occupation'] ?? '');
            $photo = $data['photo'] ?? null; // file path
            $membership_type_id = (int)($data['membership_type_id'] ?? 0);
            $member_type = isset($data['member_type']) ? trim($data['member_type']) : 'member';
            $member_type_id = (int)($data['member_type_id'] ?? 0);
            $join_date = trim($data['join_date'] ?? date('Y-m-d'));
            $expiry_date = trim($data['expiry_date'] ?? '');
            $status = trim($data['status'] ?? 'Active');
            $notes = trim($data['notes'] ?? '');
            $monthly_contribution = isset($data['monthly_contribution']) ? (float)$data['monthly_contribution'] : 0.0;
            $marital_status = trim($data['marital_status'] ?? '');
            $department = trim($data['department'] ?? '');
            $position = trim($data['position'] ?? '');
            $grade_level = trim($data['grade_level'] ?? '');
            $employee_rank = trim($data['employee_rank'] ?? '');
            $date_of_first_appointment = trim($data['date_of_first_appointment'] ?? '');
            $date_of_retirement = trim($data['date_of_retirement'] ?? '');
            $bank_name = trim($data['bank_name'] ?? '');
            $account_number = trim($data['account_number'] ?? '');
            $account_name = trim($data['account_name'] ?? '');
            $next_of_kin_name = trim($data['next_of_kin_name'] ?? '');
            $next_of_kin_relationship = trim($data['next_of_kin_relationship'] ?? '');
            $next_of_kin_phone = trim($data['next_of_kin_phone'] ?? '');
            $next_of_kin_address = trim($data['next_of_kin_address'] ?? '');

            if ($ippis_no === '' || !preg_match('/^[0-9]{6}$/', $ippis_no)) {
                return ['success' => false, 'message' => 'Invalid IPPIS Number'];
            }
            if ($this->checkExistingIppis($ippis_no)) {
                return ['success' => false, 'message' => 'IPPIS Number already exists'];
            }
            if ($bank_name === '' || $account_number === '' || $account_name === '') {
                return ['success' => false, 'message' => 'Bank details are required'];
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            // Duplicate email check
            if (!empty($email)) {
                $check = $this->connection->prepare('SELECT member_id FROM members WHERE email = ? LIMIT 1');
                if ($check) {
                    $check->bind_param('s', $email);
                    $check->execute();
                    $res = $check->get_result();
                    if ($res && $res->num_rows > 0) {
                        $check->close();
                        return ['success' => false, 'message' => 'Email already exists'];
                    }
                    $check->close();
                }
            }

            // Compute expiry if not provided, using membership type duration
            if (empty($expiry_date)) {
                $duration = 0;
                $stmtType = $this->connection->prepare('SELECT duration FROM membership_types WHERE membership_type_id = ?');
                if ($stmtType) {
                    $stmtType->bind_param('i', $membership_type_id);
                    $stmtType->execute();
                    $typeRes = $stmtType->get_result();
                    if ($typeRes && $row = $typeRes->fetch_assoc()) {
                        $duration = (int)($row['duration'] ?? 0);
                    }
                    $stmtType->close();
                }
                $expiry_date = $duration > 0 ? date('Y-m-d', strtotime("+{$duration} months", strtotime($join_date))) : date('Y-m-d', strtotime('+1 year', strtotime($join_date)));
            }

            // Insert member
            $sql = 'INSERT INTO members (ippis_no, first_name, last_name, dob, gender, address, phone, email, occupation, photo,
                    membership_type_id, member_type, member_type_id, join_date, expiry_date, status, notes, monthly_contribution,
                    marital_status, department, position, grade_level, employee_rank, date_of_first_appointment, date_of_retirement,
                    bank_name, account_number, account_name, next_of_kin_name, next_of_kin_relationship, next_of_kin_phone, next_of_kin_address
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )';
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'Failed to prepare statement'];
            }
            $stmt->bind_param(
                'ssssssssssisssssdssssssssssssssss',
                $ippis_no,
                $first_name,
                $last_name,
                $dob,
                $gender,
                $address,
                $phone,
                $email,
                $occupation,
                $photo,
                $membership_type_id,
                $member_type,
                $member_type_id,
                $join_date,
                $expiry_date,
                $status,
                $notes,
                $monthly_contribution,
                $marital_status,
                $department,
                $position,
                $grade_level,
                $employee_rank,
                $date_of_first_appointment,
                $date_of_retirement,
                $bank_name,
                $account_number,
                $account_name,
                $next_of_kin_name,
                $next_of_kin_relationship,
                $next_of_kin_phone,
                $next_of_kin_address
            );
            $ok = $stmt->execute();
            if ($ok) {
                $member_id = $this->connection->insert_id;
                $stmt->close();
                return ['success' => true, 'message' => 'Member added successfully', 'member_id' => (int)$member_id];
            }
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to add member: ' . $err];
        } catch (\Throwable $e) {
            error_log('MemberController shim addMember error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error adding member'];
        }
    }
}