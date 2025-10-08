<?php
/**
 * Contribution Controller
 * 
 * Handles all contribution-related operations including adding, updating,
 * retrieving, and deleting contribution records.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

class ContributionController {
    private $db;
    
    /**
     * Constructor - initializes database connection
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all contributions with pagination and filtering
     * 
     * @param int $page Page number
     * @param int $limit Items per page
     * @param string $search Search term
     * @param string $sort_by Sort column
     * @param string $sort_order Sort order
     * @param string $filter_type Type filter
     * @param string $date_from Date from filter
     * @param string $date_to Date to filter
     * @return array Result with contributions and pagination info
     */
    public function getAllContributions($page = 1, $limit = 10, $search = '', $sort_by = 'contribution_date', $sort_order = 'DESC', $filter_type = '', $date_from = '', $date_to = '') {
        try {
            $offset = ($page - 1) * $limit;
            
            // Base query
            $query = "SELECT c.*, m.first_name, m.last_name, m.email 
                     FROM contributions c 
                     JOIN members m ON c.member_id = m.member_id 
                     WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Add search filter
            if (!empty($search)) {
                $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR c.receipt_number LIKE ? OR c.notes LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                $types .= "ssss";
            }
            
            // Add type filter
            if (!empty($filter_type)) {
                $query .= " AND c.contribution_type = ?";
                $params[] = $filter_type;
                $types .= "s";
            }
            
            // Add date range filter
            if (!empty($date_from)) {
                $query .= " AND c.contribution_date >= ?";
                $params[] = $date_from;
                $types .= "s";
            }
            
            if (!empty($date_to)) {
                $query .= " AND c.contribution_date <= ?";
                $params[] = $date_to;
                $types .= "s";
            }
            
            // Count total records
            $countQuery = str_replace("SELECT c.*, m.first_name, m.last_name, m.email", "SELECT COUNT(*)", $query);
            $stmt = $this->db->prepare($countQuery);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $totalRecords = $stmt->get_result()->fetch_row()[0];
            
            // Add sorting and pagination
            $validSortColumns = ['contribution_date', 'amount', 'contribution_type', 'payment_method', 'created_at'];
            if (!in_array($sort_by, $validSortColumns)) {
                $sort_by = 'contribution_date';
            }
            
            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
            $query .= " ORDER BY c.{$sort_by} {$sort_order} LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $this->db->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $contributions = $result->fetch_all(MYSQLI_ASSOC);
            
            // Calculate pagination
            $totalPages = ceil($totalRecords / $limit);
            
            return [
                'contributions' => $contributions,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'limit' => $limit
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting all contributions: " . $e->getMessage());
            return [
                'contributions' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_records' => 0,
                    'limit' => $limit
                ]
            ];
        }
    }
    
    /**
     * Get contribution types
     * 
     * @return array List of contribution types
     */
    public function getContributionTypes() {
        return [
            'Regular Contribution',
            'Share Capital',
            'Loan Repayment',
            'Special Assessment',
            'Entrance Fee',
            'Registration Fee',
            'Development Fund',
            'Welfare Fund',
            'Other'
        ];
    }
    
    /**
     * Get payment methods
     * 
     * @return array List of payment methods
     */
    public function getPaymentMethods() {
        return [
            'Cash',
            'Bank Transfer',
            'Check',
            'Credit Card',
            'Debit Card',
            'Mobile Money',
            'Direct Debit',
            'Online Payment',
            'Other'
        ];
    }
    
    /**
     * Delete contribution
     * 
     * @param int $contribution_id Contribution ID
     * @return bool Success status
     */
    public function deleteContribution($contribution_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM contributions WHERE contribution_id = ?");
            $stmt->bind_param("i", $contribution_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error deleting contribution: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a new contribution record
     * 
     * @param array $data Contribution data
     * @return int|bool The ID of the newly created contribution or false on failure
     */
    public function addContribution($data) {
        try {
            // Sanitize inputs
            $member_id = (int)$data['member_id'];
            $amount = (float)$data['amount'];
            $contribution_date = $data['contribution_date'] ?? date('Y-m-d');
            $contribution_type = $data['contribution_type'] ?? 'Regular Contribution';
            $payment_method = $data['payment_method'] ?? 'Cash';
            $receipt_number = $data['receipt_number'] ?? '';
            $notes = $data['notes'] ?? '';
            
            // Prepare SQL statement
            $stmt = $this->db->prepare("INSERT INTO contributions 
                (member_id, amount, contribution_date, contribution_type, payment_method, receipt_number, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idsssss", $member_id, $amount, $contribution_date, $contribution_type, $payment_method, $receipt_number, $notes);
            
            if ($stmt->execute()) {
                return $stmt->insert_id;
            }
            
            return false;
        } catch (Exception $e) {
            // Log error
            error_log("Error adding contribution: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an existing contribution record
     * 
     * @param int $contribution_id Contribution ID
     * @param array $data Updated contribution data
     * @return bool True on success, false on failure
     */
    public function updateContribution($contribution_id, $data) {
        try {
            // Sanitize inputs
            $contribution_id = (int)$contribution_id;
            $amount = (float)$data['amount'];
            $contribution_date = $data['contribution_date'];
            $contribution_type = $data['contribution_type'];
            $payment_method = $data['payment_method'];
            $receipt_number = $data['receipt_number'] ?? '';
            $notes = $data['notes'] ?? '';
            
            // Prepare SQL statement
            $stmt = $this->db->prepare("UPDATE contributions 
                SET amount = ?, contribution_date = ?, contribution_type = ?, 
                payment_method = ?, receipt_number = ?, notes = ?, updated_at = NOW() 
                WHERE contribution_id = ?");
            
            $stmt->bind_param("dsssssi", $amount, $contribution_date, $contribution_type, $payment_method, $receipt_number, $notes, $contribution_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error updating contribution: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a contribution by ID
     * 
     * @param int $contribution_id Contribution ID
     * @return array|bool Contribution data or false if not found
     */
    public function getContributionById($contribution_id) {
        try {
            $contribution_id = (int)$contribution_id;
            
            $stmt = $this->db->prepare("SELECT c.*, 
                m.first_name, m.last_name, m.email, m.phone 
                FROM contributions c 
                JOIN members m ON c.member_id = m.member_id 
                WHERE c.contribution_id = ?");
            
            $stmt->bind_param("i", $contribution_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            
            return false;
        } catch (Exception $e) {
            // Log error
            error_log("Error getting contribution by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get contributions by member ID
     * 
     * @param int $member_id Member ID
     * @param int $limit Number of records to return (0 for all)
     * @return array|bool Contribution data or false on failure
     */
    public function getContributionsByMemberId($member_id, $limit = 0) {
        try {
            $member_id = (int)$member_id;
            
            $query = "SELECT * FROM contributions WHERE member_id = ? ORDER BY contribution_date DESC";
            
            if ($limit > 0) {
                $query .= " LIMIT ?";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("ii", $member_id, $limit);
            } else {
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $member_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $contributions = [];
            while ($row = $result->fetch_assoc()) {
                $contributions[] = $row;
            }
            
            return $contributions;
        } catch (Exception $e) {
            // Log error
            error_log("Error getting member contributions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get total contributions for a member
     * 
     * @param int $member_id Member ID
     * @return float Total contribution amount
     */
    public function getMemberTotalContributions($member_id) {
        try {
            $member_id = (int)$member_id;
            
            $stmt = $this->db->prepare("SELECT SUM(amount) as total FROM contributions WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return (float)$row['total'] ?? 0;
            }
            
            return 0;
        } catch (Exception $e) {
            error_log("Error getting member total contributions: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get contribution count for a member
     * 
     * @param int $member_id Member ID
     * @return int Number of contributions
     */
    public function getMemberContributionCount($member_id) {
        try {
            $member_id = (int)$member_id;
            
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM contributions WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return (int)$row['count'];
            }
            
            return 0;
        } catch (Exception $e) {
            error_log("Error getting member contribution count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get last contribution for a member
     * 
     * @param int $member_id Member ID
     * @return array|null Last contribution data or null if none found
     */
    public function getMemberLastContribution($member_id) {
        try {
            $member_id = (int)$member_id;
            
            $stmt = $this->db->prepare("SELECT * FROM contributions WHERE member_id = ? ORDER BY contribution_date DESC LIMIT 1");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting member last contribution: " . $e->getMessage());
            return null;
        }
    }
}
?>
