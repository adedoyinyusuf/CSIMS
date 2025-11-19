<?php
require_once __DIR__ . '/../config/database.php';

class MembershipController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function getAllMembershipTypes($page = 1, $limit = 10, $search = '') {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        // Add search conditions
        if (!empty($search)) {
            $where_clause .= " AND (name LIKE ? OR description LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param]);
            $types .= "ss";
        }
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total FROM membership_types $where_clause";
        $count_stmt = $this->conn->prepare($count_sql);
        
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
        
        // Get paginated results
        $sql = "SELECT mt.*, 
                COUNT(m.member_id) as member_count
                FROM membership_types mt 
                LEFT JOIN members m ON mt.membership_type_id = m.membership_type_id
                $where_clause 
                GROUP BY mt.membership_type_id
                ORDER BY mt.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $membership_types = [];
        while ($row = $result->fetch_assoc()) {
            $membership_types[] = $row;
        }
        
        $stmt->close();
        
        return [
            'membership_types' => $membership_types,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit),
            'current_page' => $page
        ];
    }
    
    public function getMembershipTypeById($membership_type_id) {
        $sql = "SELECT mt.*, 
                COUNT(m.member_id) as member_count
                FROM membership_types mt 
                LEFT JOIN members m ON mt.membership_type_id = m.membership_type_id
                WHERE mt.membership_type_id = ?
                GROUP BY mt.membership_type_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $membership_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $membership_type = $result->fetch_assoc();
        $stmt->close();
        
        return $membership_type;
    }
    
    public function createMembershipType($data) {
        $sql = "INSERT INTO membership_types (name, description, duration, fee, benefits) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssids", 
            $data['name'],
            $data['description'],
            $data['duration'],
            $data['fee'],
            $data['benefits']
        );
        
        $result = $stmt->execute();
        $membership_type_id = $this->conn->insert_id;
        $stmt->close();
        
        return $result ? $membership_type_id : false;
    }
    
    public function updateMembershipType($membership_type_id, $data) {
        $sql = "UPDATE membership_types SET 
                name = ?, description = ?, duration = ?, fee = ?, benefits = ? 
                WHERE membership_type_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssidsi", 
            $data['name'],
            $data['description'],
            $data['duration'],
            $data['fee'],
            $data['benefits'],
            $membership_type_id
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function deleteMembershipType($membership_type_id) {
        // Check if any members are using this membership type
        $check_sql = "SELECT COUNT(*) as count FROM members WHERE membership_type_id = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("i", $membership_type_id);
        $check_stmt->execute();
        $count = $check_stmt->get_result()->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($count > 0) {
            return false; // Cannot delete if members are using this type
        }
        
        $sql = "DELETE FROM membership_types WHERE membership_type_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $membership_type_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function getMembershipStats() {
        $sql = "SELECT 
                COUNT(*) as total_types,
                AVG(fee) as average_fee,
                MAX(fee) as highest_fee,
                MIN(fee) as lowest_fee
                FROM membership_types";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    public function getMembersByType() {
        $sql = "SELECT mt.name, COUNT(m.member_id) as member_count
                FROM membership_types mt 
                LEFT JOIN members m ON mt.membership_type_id = m.membership_type_id
                GROUP BY mt.membership_type_id, mt.name
                ORDER BY member_count DESC";
        
        $result = $this->conn->query($sql);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function getExpiringMemberships($days = 30) {
        $sql = "SELECT m.member_id, CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.expiry_date, mt.name as membership_type
                FROM members m
                JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id
                WHERE m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND m.status = 'Active'
                ORDER BY m.expiry_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $expiring = [];
        while ($row = $result->fetch_assoc()) {
            $expiring[] = $row;
        }
        
        $stmt->close();
        return $expiring;
    }
}