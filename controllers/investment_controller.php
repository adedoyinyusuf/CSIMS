<?php
require_once '../config/database.php';

class InvestmentController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function getAllInvestments($page = 1, $limit = 10, $search = '') {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        // Add search conditions
        if (!empty($search)) {
            $where_clause .= " AND (i.name LIKE ? OR i.description LIKE ? OR i.investment_type LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
            $types .= "sss";
        }
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total FROM investments i $where_clause";
        $count_stmt = $this->conn->prepare($count_sql);
        
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
        
        // Get paginated results
        $sql = "SELECT i.*, CONCAT(a.first_name, ' ', a.last_name) as created_by_name 
                FROM investments i 
                LEFT JOIN admins a ON i.created_by = a.admin_id 
                $where_clause 
                ORDER BY i.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $investments = [];
        while ($row = $result->fetch_assoc()) {
            $investments[] = $row;
        }
        
        $stmt->close();
        
        return [
            'investments' => $investments,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit),
            'current_page' => $page
        ];
    }
    
    public function getInvestmentById($investment_id) {
        $sql = "SELECT i.*, CONCAT(a.first_name, ' ', a.last_name) as created_by_name 
                FROM investments i 
                LEFT JOIN admins a ON i.created_by = a.admin_id 
                WHERE i.investment_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $investment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $investment = $result->fetch_assoc();
        $stmt->close();
        
        return $investment;
    }
    
    public function createInvestment($data) {
        $sql = "INSERT INTO investments (name, description, amount, investment_date, investment_type, expected_return, maturity_date, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssdssdssi", 
            $data['name'],
            $data['description'],
            $data['amount'],
            $data['investment_date'],
            $data['investment_type'],
            $data['expected_return'],
            $data['maturity_date'],
            $data['status'],
            $data['created_by']
        );
        
        $result = $stmt->execute();
        $investment_id = $this->conn->insert_id;
        $stmt->close();
        
        return $result ? $investment_id : false;
    }
    
    public function updateInvestment($investment_id, $data) {
        $sql = "UPDATE investments SET 
                name = ?, description = ?, amount = ?, investment_date = ?, 
                investment_type = ?, expected_return = ?, maturity_date = ?, status = ? 
                WHERE investment_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssdssdssi", 
            $data['name'],
            $data['description'],
            $data['amount'],
            $data['investment_date'],
            $data['investment_type'],
            $data['expected_return'],
            $data['maturity_date'],
            $data['status'],
            $investment_id
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function deleteInvestment($investment_id) {
        $sql = "DELETE FROM investments WHERE investment_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $investment_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function getInvestmentStats() {
        $sql = "SELECT 
                COUNT(*) as total_investments,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status = 'Active' THEN amount ELSE 0 END), 0) as active_amount,
                COALESCE(SUM(expected_return), 0) as total_expected_return
                FROM investments";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
}
?>