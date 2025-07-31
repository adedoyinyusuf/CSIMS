<?php
/**
 * Contribution Controller
 * 
 * Handles all contribution-related operations including adding, updating,
 * retrieving, and deleting contribution records.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utilities.php';

class ContributionController {
    private $db;
    
    /**
     * Constructor - initializes database connection
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
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
            $contribution_type = Utilities::sanitizeInput($data['contribution_type']);
            $payment_method = Utilities::sanitizeInput($data['payment_method']);
            $receipt_number = Utilities::sanitizeInput($data['receipt_number'] ?? '');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Prepare SQL statement
            $stmt = $this->db->prepare("INSERT INTO contributions 
                (member_id, amount, contribution_date, contribution_type, payment_method, receipt_number, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idssss", $member_id, $amount, $contribution_date, $contribution_type, $payment_method, $receipt_number, $notes);
            
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
            $contribution_type = Utilities::sanitizeInput($data['contribution_type']);
            $payment_method = Utilities::sanitizeInput($data['payment_method']);
            $receipt_number = Utilities::sanitizeInput($data['receipt_number'] ?? '');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Prepare SQL statement
            $stmt = $this->db->prepare("UPDATE contributions 
                SET amount = ?, contribution_date = ?, contribution_type = ?, 
                payment_method = ?, receipt_number = ?, notes = ?, updated_at = NOW() 
                WHERE contribution_id = ?");
            
            $stmt->bind_param("dssssi", $amount, $contribution_date, $contribution_type, $payment_method, $receipt_number, $notes, $contribution_id);
            
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
            error_log("Error getting contribution: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all contributions with pagination
     * 
     * @param int $page Current page number
     * @param int $limit Records per page
     * @param string $search Search term
     * @param string $sort_by Column to sort by
     * @param string $sort_order Sort order (ASC or DESC)
     * @param string $filter_type Filter by contribution type
     * @param string $date_from Filter by start date
     * @param string $date_to Filter by end date
     * @return array Contributions and pagination data
     */
    public function getAllContributions($page = 1, $limit = 10, $search = '', $sort_by = 'contribution_date', 
                                      $sort_order = 'DESC', $filter_type = '', $date_from = '', $date_to = '') {
        try {
            $page = (int)$page;
            $limit = (int)$limit;
            $offset = ($page - 1) * $limit;
            
            // Base query
            $query = "SELECT c.*, m.first_name, m.last_name 
                     FROM contributions c 
                     JOIN members m ON c.member_id = m.member_id 
                     WHERE 1=1";
            $count_query = "SELECT COUNT(*) as total 
                           FROM contributions c 
                           JOIN members m ON c.member_id = m.member_id 
                           WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Add search condition if provided
            if (!empty($search)) {
                $search_term = "%" . $search . "%";
                $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR 
                         c.receipt_number LIKE ? OR c.notes LIKE ?)"; 
                $count_query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR 
                               c.receipt_number LIKE ? OR c.notes LIKE ?)"; 
                $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
                $types .= "ssss";
            }
            
            // Add filter by contribution type if provided
            if (!empty($filter_type)) {
                $query .= " AND c.contribution_type = ?";
                $count_query .= " AND c.contribution_type = ?";
                $params[] = $filter_type;
                $types .= "s";
            }
            
            // Add date range filter if provided
            if (!empty($date_from)) {
                $query .= " AND c.contribution_date >= ?";
                $count_query .= " AND c.contribution_date >= ?";
                $params[] = $date_from;
                $types .= "s";
            }
            
            if (!empty($date_to)) {
                $query .= " AND c.contribution_date <= ?";
                $count_query .= " AND c.contribution_date <= ?";
                $params[] = $date_to;
                $types .= "s";
            }
            
            // Add sorting
            $allowed_sort_columns = ['contribution_date', 'amount', 'contribution_type', 'payment_method'];
            $sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'contribution_date';
            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
            
            $query .= " ORDER BY c.$sort_by $sort_order";
            
            // Add pagination
            $query .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";
            
            // Get total count (without pagination parameters)
            $count_params = array_slice($params, 0, -2); // Remove offset and limit
            $count_types = substr($types, 0, -2); // Remove "ii" for offset and limit
            
            $count_stmt = $this->db->prepare($count_query);
            if (!empty($count_types)) {
                $count_stmt->bind_param($count_types, ...$count_params);
            }
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_records = $count_result->fetch_assoc()['total'];
            
            // Get paginated results
            $stmt = $this->db->prepare($query);
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $contributions = [];
            while ($row = $result->fetch_assoc()) {
                $contributions[] = $row;
            }
            
            // Calculate pagination data
            $total_pages = ceil($total_records / $limit);
            
            return [
                'contributions' => $contributions,
                'pagination' => [
                    'total_records' => $total_records,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ];
        } catch (Exception $e) {
            // Log error
            error_log("Error getting all contributions: " . $e->getMessage());
            return [
                'contributions' => [],
                'pagination' => [
                    'total_records' => 0,
                    'total_pages' => 0,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ];
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
     * Delete a contribution record
     * 
     * @param int $contribution_id Contribution ID
     * @return bool True on success, false on failure
     */
    public function deleteContribution($contribution_id) {
        try {
            $contribution_id = (int)$contribution_id;
            
            $stmt = $this->db->prepare("DELETE FROM contributions WHERE contribution_id = ?");
            $stmt->bind_param("i", $contribution_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error deleting contribution: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get contribution statistics
     * 
     * @return array Statistics data
     */
    public function getContributionStatistics() {
        try {
            // Total contributions amount
            $total_query = "SELECT SUM(amount) as total_amount FROM contributions";
            $total_result = $this->db->query($total_query);
            $total_amount = $total_result->fetch_assoc()['total_amount'] ?? 0;
            
            // Contributions this month
            $month_query = "SELECT SUM(amount) as month_amount FROM contributions 
                           WHERE MONTH(contribution_date) = MONTH(CURRENT_DATE()) 
                           AND YEAR(contribution_date) = YEAR(CURRENT_DATE())"; 
            $month_result = $this->db->query($month_query);
            $month_amount = $month_result->fetch_assoc()['month_amount'] ?? 0;
            
            // Contributions by type
            $type_query = "SELECT contribution_type, SUM(amount) as type_amount 
                          FROM contributions 
                          GROUP BY contribution_type 
                          ORDER BY type_amount DESC";
            $type_result = $this->db->query($type_query);
            
            $contributions_by_type = [];
            while ($row = $type_result->fetch_assoc()) {
                $contributions_by_type[] = $row;
            }
            
            // Recent contributions
            $recent_query = "SELECT c.*, m.first_name, m.last_name 
                           FROM contributions c 
                           JOIN members m ON c.member_id = m.member_id 
                           ORDER BY c.contribution_date DESC 
                           LIMIT 5";
            $recent_result = $this->db->query($recent_query);
            
            $recent_contributions = [];
            while ($row = $recent_result->fetch_assoc()) {
                $recent_contributions[] = $row;
            }
            
            // Monthly contributions for the past 12 months
            $monthly_query = "SELECT 
                              DATE_FORMAT(contribution_date, '%Y-%m') as month,
                              SUM(amount) as monthly_amount 
                              FROM contributions 
                              WHERE contribution_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) 
                              GROUP BY DATE_FORMAT(contribution_date, '%Y-%m') 
                              ORDER BY month ASC";
            $monthly_result = $this->db->query($monthly_query);
            
            $monthly_contributions = [];
            while ($row = $monthly_result->fetch_assoc()) {
                $monthly_contributions[] = $row;
            }
            
            return [
                'total_amount' => $total_amount,
                'month_amount' => $month_amount,
                'contributions_by_type' => $contributions_by_type,
                'recent_contributions' => $recent_contributions,
                'monthly_contributions' => $monthly_contributions
            ];
        } catch (Exception $e) {
            // Log error
            error_log("Error getting contribution statistics: " . $e->getMessage());
            return [
                'total_amount' => 0,
                'month_amount' => 0,
                'contributions_by_type' => [],
                'recent_contributions' => [],
                'monthly_contributions' => []
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
            'Membership Fee',
            'Renewal Fee',
            'Monthly Dues',
            'Special Assessment',
            'Donation',
            'Investment',
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
            'Check',
            'Bank Transfer',
            'Credit Card',
            'Debit Card',
            'Mobile Money',
            'Other'
        ];
    }
}
?>