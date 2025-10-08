<?php
/**
 * Contribution Controller
 * 
 * Handles all contribution-related operations including adding, updating,
 * retrieving, and deleting contribution records.
 */

require_once __DIR__ . '/../config/database.php';
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
     * Create contribution target for member
     * 
     * @param array $data Target data
     * @return int|bool Target ID or false on failure
     */
    public function createContributionTarget($data) {
        try {
            $stmt = $this->db->prepare("INSERT INTO contribution_targets 
                (member_id, target_type, target_amount, target_period_start, target_period_end, 
                description, priority, auto_deduct, reminder_enabled, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
            $stmt->bind_param("issdsssiiii", 
                $data['member_id'], $data['target_type'], $data['target_amount'],
                $data['target_period_start'], $data['target_period_end'], $data['description'],
                $data['priority'], $data['auto_deduct'], $data['reminder_enabled'], $data['created_by']
            );
            
            return $stmt->execute() ? $stmt->insert_id : false;
        } catch (Exception $e) {
            error_log("Error creating contribution target: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get contribution targets for member
     * 
     * @param int $member_id Member ID
     * @param string $status Status filter
     * @return array Contribution targets
     */
    public function getMemberContributionTargets($member_id, $status = 'active') {
        try {
            $query = "SELECT ct.*, m.first_name, m.last_name 
                     FROM contribution_targets ct 
                     JOIN members m ON ct.member_id = m.member_id 
                     WHERE ct.member_id = ?";
            $params = [$member_id];
            $types = "i";
            
            if ($status !== 'all') {
                $query .= " AND ct.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            $query .= " ORDER BY ct.target_period_start DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting member contribution targets: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update contribution target achievement
     * 
     * @param int $target_id Target ID
     * @param float $amount_achieved Amount achieved
     * @return bool Success status
     */
    public function updateTargetAchievement($target_id, $amount_achieved) {
        try {
            // Get target details
            $stmt = $this->db->prepare("SELECT target_amount FROM contribution_targets WHERE target_id = ?");
            $stmt->bind_param("i", $target_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }
            
            $target = $result->fetch_assoc();
            $achievement_percentage = ($amount_achieved / $target['target_amount']) * 100;
            
            // Determine achievement status
            $achievement_status = 'in_progress';
            if ($achievement_percentage >= 100) {
                $achievement_status = 'achieved';
            } elseif ($amount_achieved > 0) {
                $achievement_status = 'in_progress';
            }
            
            // Update target
            $stmt = $this->db->prepare("UPDATE contribution_targets 
                SET amount_achieved = ?, achievement_percentage = ?, achievement_status = ?, updated_at = NOW() 
                WHERE target_id = ?");
            $stmt->bind_param("ddsi", $amount_achieved, $achievement_percentage, $achievement_status, $target_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error updating target achievement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Submit withdrawal request
     * 
     * @param array $data Withdrawal data
     * @return int|bool Withdrawal ID or false on failure
     */
    public function submitWithdrawalRequest($data) {
        try {
            $this->db->begin_transaction();
            
            // Calculate net amount (after fees)
            $withdrawal_fee = $this->calculateWithdrawalFee($data['amount'], $data['withdrawal_type']);
            $net_amount = $data['amount'] - $withdrawal_fee;
            
            $stmt = $this->db->prepare("INSERT INTO contribution_withdrawals 
                (member_id, withdrawal_type, amount, contribution_types, withdrawal_date, 
                reason, supporting_documents, withdrawal_fee, net_amount, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
            $stmt->bind_param("issdsstdd", 
                $data['member_id'], $data['withdrawal_type'], $data['amount'],
                $data['contribution_types'], $data['withdrawal_date'], $data['reason'],
                $data['supporting_documents'], $withdrawal_fee, $net_amount
            );
            
            if ($stmt->execute()) {
                $withdrawal_id = $stmt->insert_id;
                
                // Submit for approval workflow
                require_once __DIR__ . '/workflow_controller.php';
                $workflowController = new WorkflowController();
                
                $workflow_data = [
                    'workflow_type' => 'contribution_withdrawal',
                    'reference_id' => $withdrawal_id,
                    'member_id' => $data['member_id'],
                    'submitted_by' => $data['member_id'], // Member submits their own request
                    'amount' => $data['amount'],
                    'priority' => $data['withdrawal_type'] === 'emergency' ? 'high' : 'normal',
                    'notes' => "Withdrawal request: {$data['withdrawal_type']} - {$data['reason']}"
                ];
                
                $approval_id = $workflowController->submitForApproval($workflow_data);
                
                if ($approval_id) {
                    $this->db->commit();
                    return $withdrawal_id;
                } else {
                    $this->db->rollback();
                    return false;
                }
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error submitting withdrawal request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate withdrawal fee
     * 
     * @param float $amount Withdrawal amount
     * @param string $withdrawal_type Type of withdrawal
     * @return float Fee amount
     */
    private function calculateWithdrawalFee($amount, $withdrawal_type) {
        // Fee structure based on withdrawal type
        $fee_rates = [
            'partial' => 0.02,      // 2%
            'emergency' => 0.01,    // 1%
            'resignation' => 0.05,  // 5%
            'loan_offset' => 0.00,  // 0%
            'investment' => 0.01    // 1%
        ];
        
        $fee_rate = $fee_rates[$withdrawal_type] ?? 0.02;
        $fee = $amount * $fee_rate;
        
        // Maximum fee cap
        $max_fee = 50000; // ₦50,000
        return min($fee, $max_fee);
    }
    
    /**
     * Get member withdrawal requests
     * 
     * @param int $member_id Member ID
     * @param string $status Status filter
     * @return array Withdrawal requests
     */
    public function getMemberWithdrawals($member_id, $status = 'all') {
        try {
            $query = "SELECT cw.*, m.first_name, m.last_name,
                     a1.first_name as approved_by_first_name, a1.last_name as approved_by_last_name,
                     a2.first_name as processed_by_first_name, a2.last_name as processed_by_last_name
                     FROM contribution_withdrawals cw 
                     JOIN members m ON cw.member_id = m.member_id
                     LEFT JOIN admins a1 ON cw.approved_by = a1.admin_id
                     LEFT JOIN admins a2 ON cw.processed_by = a2.admin_id
                     WHERE cw.member_id = ?";
            $params = [$member_id];
            $types = "i";
            
            if ($status !== 'all') {
                $query .= " AND cw.approval_status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            $query .= " ORDER BY cw.withdrawal_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting member withdrawals: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Purchase shares for member
     * 
     * @param array $data Share purchase data
     * @return int|bool Share ID or false on failure
     */
    public function purchaseShares($data) {
        try {
            $this->db->begin_transaction();
            
            // Generate certificate number
            $certificate_number = $this->generateCertificateNumber($data['member_id']);
            
            // Calculate total value
            $total_value = $data['number_of_shares'] * $data['par_value'];
            
            $stmt = $this->db->prepare("INSERT INTO share_capital 
                (member_id, share_type, number_of_shares, par_value, total_value, 
                purchase_date, certificate_number, payment_status, amount_paid, 
                dividend_eligible, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
            $stmt->bind_param("isiddssddi", 
                $data['member_id'], $data['share_type'], $data['number_of_shares'],
                $data['par_value'], $total_value, $data['purchase_date'],
                $certificate_number, $data['payment_status'], $data['amount_paid'],
                $data['dividend_eligible']
            );
            
            if ($stmt->execute()) {
                $share_id = $stmt->insert_id;
                
                // Record as contribution if fully paid
                if ($data['payment_status'] === 'paid') {
                    $contribution_data = [
                        'member_id' => $data['member_id'],
                        'amount' => $total_value,
                        'contribution_date' => $data['purchase_date'],
                        'contribution_type' => 'Investment',
                        'description' => "Share capital purchase - {$data['number_of_shares']} {$data['share_type']} shares",
                        'payment_method' => $data['payment_method'] ?? 'Bank Transfer',
                        'receipt_number' => $certificate_number
                    ];
                    
                    $this->addContribution($contribution_data);
                }
                
                $this->db->commit();
                return $share_id;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error purchasing shares: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate certificate number
     * 
     * @param int $member_id Member ID
     * @return string Certificate number
     */
    private function generateCertificateNumber($member_id) {
        $prefix = "SHARE";
        $year = date('Y');
        $sequence = str_pad($member_id, 4, '0', STR_PAD_LEFT);
        $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return "{$prefix}{$year}{$sequence}{$random}";
    }
    
    /**
     * Get member share portfolio
     * 
     * @param int $member_id Member ID
     * @param string $status Status filter
     * @return array Share portfolio
     */
    public function getMemberShares($member_id, $status = 'active') {
        try {
            $query = "SELECT sc.*, m.first_name, m.last_name 
                     FROM share_capital sc 
                     JOIN members m ON sc.member_id = m.member_id 
                     WHERE sc.member_id = ?";
            $params = [$member_id];
            $types = "i";
            
            if ($status !== 'all') {
                $query .= " AND sc.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            $query .= " ORDER BY sc.purchase_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting member shares: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get member contribution statistics
     * 
     * @param int $member_id Member ID
     * @return array Statistics
     */
    public function getMemberContributionStats($member_id) {
        try {
            $stats = [];
            
            // Total contributions
            $stmt = $this->db->prepare("SELECT SUM(amount) as total_contributions, COUNT(*) as contribution_count 
                FROM contributions WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats['total_contributions'] = $result['total_contributions'] ?? 0;
            $stats['contribution_count'] = $result['contribution_count'] ?? 0;
            
            // Active targets
            $stmt = $this->db->prepare("SELECT COUNT(*) as active_targets 
                FROM contribution_targets WHERE member_id = ? AND status = 'active'");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats['active_targets'] = $result['active_targets'] ?? 0;
            
            // Withdrawals
            $stmt = $this->db->prepare("SELECT SUM(amount) as total_withdrawals, COUNT(*) as withdrawal_count 
                FROM contribution_withdrawals WHERE member_id = ? AND approval_status = 'processed'");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats['total_withdrawals'] = $result['total_withdrawals'] ?? 0;
            $stats['withdrawal_count'] = $result['withdrawal_count'] ?? 0;
            
            // Share capital
            $stmt = $this->db->prepare("SELECT SUM(total_value) as total_share_value, SUM(number_of_shares) as total_shares 
                FROM share_capital WHERE member_id = ? AND status = 'active'");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats['total_share_value'] = $result['total_share_value'] ?? 0;
            $stats['total_shares'] = $result['total_shares'] ?? 0;
            
            // Net contributions (contributions - withdrawals)
            $stats['net_contributions'] = $stats['total_contributions'] - $stats['total_withdrawals'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting member contribution stats: " . $e->getMessage());
            return [];
        }
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
            'Check',
            'Bank Transfer',
            'Credit Card',
            'Debit Card',
            'Mobile Money',
            'Other'
        ];
    }
    
    /**
     * Get member contributions with pagination
     * 
     * @param int $member_id Member ID
     * @param int $page Current page number
     * @param int $limit Records per page
     * @return array Contributions and pagination data
     */
    public function getMemberContributions($member_id, $page = 1, $limit = 10) {
        try {
            $member_id = (int)$member_id;
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $count_stmt = $this->db->prepare("SELECT COUNT(*) as total FROM contributions WHERE member_id = ?");
            $count_stmt->bind_param("i", $member_id);
            $count_stmt->execute();
            $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
            
            // Get contributions
            $stmt = $this->db->prepare("SELECT * FROM contributions WHERE member_id = ? ORDER BY contribution_date DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("iii", $member_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $contributions = [];
            while ($row = $result->fetch_assoc()) {
                $contributions[] = $row;
            }
            
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
            error_log("Error getting member contributions: " . $e->getMessage());
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
     * Get member total contributions
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
            $result = $stmt->get_result()->fetch_assoc();
            
            return (float)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getting member total contributions: " . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Get member contribution count
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
            $result = $stmt->get_result()->fetch_assoc();
            
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getting member contribution count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get member last contribution
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
    
    /**
     * Get contributions for a specific member (wrapper method for compatibility)
     * 
     * @param int $member_id Member ID
     * @param int $limit Number of records to return (0 for all)
     * @return array|bool Contribution data or false on failure
     */
    public function getContributionsByMember($member_id, $limit = 0) {
        return $this->getContributionsByMemberId($member_id, $limit);
    }
}
?>