<?php
/**
 * Loan Controller
 * 
 * Handles all loan-related operations including adding, updating,
 * retrieving, and managing loan applications and repayments.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utilities.php';

class LoanController {
    private $db;
    
    /**
     * Constructor - initializes database connection
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Add a new loan application
     * 
     * @param array $data Loan application data
     * @return int|bool The ID of the newly created loan or false on failure
     */
    public function addLoanApplication($data) {
        try {
            // Sanitize inputs
            $member_id = (int)$data['member_id'];
            $amount = (float)$data['amount'];
            $purpose = Utilities::sanitizeInput($data['purpose']);
            $term_months = (int)$data['term_months'];
            $interest_rate = (float)$data['interest_rate'];
            $application_date = $data['application_date'] ?? date('Y-m-d');
            $status = Utilities::sanitizeInput($data['status'] ?? 'pending');
            $collateral = Utilities::sanitizeInput($data['collateral'] ?? '');
            $guarantor = Utilities::sanitizeInput($data['guarantor'] ?? '');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Calculate monthly payment (principal + interest)
            $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $term_months);
            
            // Prepare SQL statement
            $stmt = $this->db->prepare("INSERT INTO loans 
                (member_id, amount, purpose, term_months, interest_rate, monthly_payment, 
                application_date, status, collateral, guarantor, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idsifdssss", $member_id, $amount, $purpose, $term_months, 
                            $interest_rate, $monthly_payment, $application_date, $status, 
                            $collateral, $guarantor, $notes);
            
            if ($stmt->execute()) {
                return $stmt->insert_id;
            }
            
            return false;
        } catch (Exception $e) {
            // Log error
            error_log("Error adding loan application: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an existing loan application
     * 
     * @param int $loan_id Loan ID
     * @param array $data Updated loan data
     * @return bool True on success, false on failure
     */
    public function updateLoanApplication($loan_id, $data) {
        try {
            // Sanitize inputs
            $loan_id = (int)$loan_id;
            $amount = (float)$data['amount'];
            $purpose = Utilities::sanitizeInput($data['purpose']);
            $term_months = (int)$data['term_months'];
            $interest_rate = (float)$data['interest_rate'];
            $application_date = $data['application_date'];
            $status = Utilities::sanitizeInput($data['status']);
            $collateral = Utilities::sanitizeInput($data['collateral'] ?? '');
            $guarantor = Utilities::sanitizeInput($data['guarantor'] ?? '');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Calculate monthly payment (principal + interest)
            $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $term_months);
            
            // Prepare SQL statement
            $stmt = $this->db->prepare("UPDATE loans 
                SET amount = ?, purpose = ?, term_months = ?, interest_rate = ?, 
                monthly_payment = ?, application_date = ?, status = ?, collateral = ?, 
                guarantor = ?, notes = ?, updated_at = NOW() 
                WHERE loan_id = ?");
            
            $stmt->bind_param("dsidfssssi", $amount, $purpose, $term_months, $interest_rate, 
                            $monthly_payment, $application_date, $status, $collateral, 
                            $guarantor, $notes, $loan_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error updating loan application: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update loan status
     * 
     * @param int $loan_id Loan ID
     * @param string $status New status
     * @param string $notes Optional notes about the status change
     * @return bool True on success, false on failure
     */
    public function updateLoanStatus($loan_id, $status, $notes = '') {
        try {
            $loan_id = (int)$loan_id;
            $status = Utilities::sanitizeInput($status);
            $notes = Utilities::sanitizeInput($notes);
            
            // If approving the loan, set approval date
            $approval_date = ($status === 'approved') ? date('Y-m-d') : null;
            $disbursement_date = ($status === 'disbursed') ? date('Y-m-d') : null;
            
            // Prepare SQL statement
            if ($status === 'approved') {
                $stmt = $this->db->prepare("UPDATE loans 
                    SET status = ?, approval_date = ?, notes = CONCAT(notes, '\n', ?), updated_at = NOW() 
                    WHERE loan_id = ?");
                $stmt->bind_param("sssi", $status, $approval_date, $notes, $loan_id);
            } elseif ($status === 'disbursed') {
                $stmt = $this->db->prepare("UPDATE loans 
                    SET status = ?, disbursement_date = ?, notes = CONCAT(notes, '\n', ?), updated_at = NOW() 
                    WHERE loan_id = ?");
                $stmt->bind_param("sssi", $status, $disbursement_date, $notes, $loan_id);
            } else {
                $stmt = $this->db->prepare("UPDATE loans 
                    SET status = ?, notes = CONCAT(notes, '\n', ?), updated_at = NOW() 
                    WHERE loan_id = ?");
                $stmt->bind_param("ssi", $status, $notes, $loan_id);
            }
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error updating loan status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a loan by ID
     * 
     * @param int $loan_id Loan ID
     * @return array|bool Loan data or false if not found
     */
    public function getLoanById($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            
            $stmt = $this->db->prepare("SELECT l.*, 
                m.first_name, m.last_name, m.email, m.phone 
                FROM loans l 
                JOIN members m ON l.member_id = m.member_id 
                WHERE l.loan_id = ?");
            
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            
            return false;
        } catch (Exception $e) {
            // Log error
            error_log("Error getting loan: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all loans with pagination
     * 
     * @param int $page Current page number
     * @param int $limit Records per page
     * @param string $search Search term
     * @param string $sort_by Column to sort by
     * @param string $sort_order Sort order (ASC or DESC)
     * @param string $status_filter Filter by loan status
     * @return array Loans and pagination data
     */
    public function getAllLoans($page = 1, $limit = 10, $search = '', $sort_by = 'application_date', 
                              $sort_order = 'DESC', $status_filter = '') {
        try {
            $page = (int)$page;
            $limit = (int)$limit;
            $offset = ($page - 1) * $limit;
            
            // Base query
            $query = "SELECT l.*, m.first_name, m.last_name 
                     FROM loans l 
                     JOIN members m ON l.member_id = m.member_id 
                     WHERE 1=1";
            $count_query = "SELECT COUNT(*) as total 
                           FROM loans l 
                           JOIN members m ON l.member_id = m.member_id 
                           WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Add search condition if provided
            if (!empty($search)) {
                $search_term = "%" . $search . "%";
                $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR 
                         l.purpose LIKE ? OR l.notes LIKE ?)"; 
                $count_query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR 
                               l.purpose LIKE ? OR l.notes LIKE ?)"; 
                $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
                $types .= "ssss";
            }
            
            // Add status filter if provided
            if (!empty($status_filter)) {
                $query .= " AND l.status = ?";
                $count_query .= " AND l.status = ?";
                $params[] = $status_filter;
                $types .= "s";
            }
            
            // Add sorting
            $allowed_sort_columns = ['application_date', 'amount', 'status', 'term_months', 'interest_rate'];
            $sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'application_date';
            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
            
            $query .= " ORDER BY l.$sort_by $sort_order";
            
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
            
            $loans = [];
            while ($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
            
            // Calculate pagination data
            $total_pages = ceil($total_records / $limit);
            
            return [
                'loans' => $loans,
                'pagination' => [
                    'total_records' => $total_records,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ];
        } catch (Exception $e) {
            // Log error
            error_log("Error getting all loans: " . $e->getMessage());
            return [
                'loans' => [],
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
     * Get loans by member ID
     * 
     * @param int $member_id Member ID
     * @param int $limit Number of records to return (0 for all)
     * @return array|bool Loan data or false on failure
     */
    public function getLoansByMemberId($member_id, $limit = 0) {
        try {
            $member_id = (int)$member_id;
            
            $query = "SELECT * FROM loans WHERE member_id = ? ORDER BY application_date DESC";
            
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
            
            $loans = [];
            while ($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
            
            return $loans;
        } catch (Exception $e) {
            // Log error
            error_log("Error getting member loans: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record a loan repayment
     * 
     * @param array $data Repayment data
     * @return int|bool The ID of the newly created repayment or false on failure
     */
    public function addLoanRepayment($data) {
        try {
            // Sanitize inputs
            $loan_id = (int)$data['loan_id'];
            $amount = (float)$data['amount'];
            $payment_date = $data['payment_date'] ?? date('Y-m-d');
            $payment_method = Utilities::sanitizeInput($data['payment_method']);
            $receipt_number = Utilities::sanitizeInput($data['receipt_number'] ?? '');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Begin transaction
            $this->db->begin_transaction();
            
            // Insert repayment record
            $stmt = $this->db->prepare("INSERT INTO loan_repayments 
                (loan_id, amount, payment_date, payment_method, receipt_number, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idssss", $loan_id, $amount, $payment_date, $payment_method, $receipt_number, $notes);
            
            $repayment_success = $stmt->execute();
            $repayment_id = $stmt->insert_id;
            
            if (!$repayment_success) {
                $this->db->rollback();
                return false;
            }
            
            // Update loan's paid amount and check if fully paid
            $update_stmt = $this->db->prepare("UPDATE loans 
                SET amount_paid = amount_paid + ?, 
                last_payment_date = ?, 
                status = CASE 
                    WHEN (amount_paid + ?) >= amount THEN 'paid' 
                    WHEN status = 'disbursed' THEN 'active' 
                    ELSE status 
                END, 
                updated_at = NOW() 
                WHERE loan_id = ?");
            
            $update_stmt->bind_param("dsdi", $amount, $payment_date, $amount, $loan_id);
            $update_success = $update_stmt->execute();
            
            if (!$update_success) {
                $this->db->rollback();
                return false;
            }
            
            // Commit transaction
            $this->db->commit();
            
            return $repayment_id;
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollback();
            // Log error
            error_log("Error adding loan repayment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get repayments for a specific loan
     * 
     * @param int $loan_id Loan ID
     * @return array|bool Repayment data or false on failure
     */
    public function getLoanRepayments($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            
            $stmt = $this->db->prepare("SELECT * FROM loan_repayments 
                                       WHERE loan_id = ? 
                                       ORDER BY payment_date DESC");
            
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $repayments = [];
            while ($row = $result->fetch_assoc()) {
                $repayments[] = $row;
            }
            
            return $repayments;
        } catch (Exception $e) {
            // Log error
            error_log("Error getting loan repayments: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a loan (only if status is 'pending' or 'rejected')
     * 
     * @param int $loan_id Loan ID
     * @return bool True on success, false on failure
     */
    public function deleteLoan($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            
            // Check if loan can be deleted (only pending or rejected loans)
            $check_stmt = $this->db->prepare("SELECT status FROM loans WHERE loan_id = ?");
            $check_stmt->bind_param("i", $loan_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false; // Loan not found
            }
            
            $loan_status = $result->fetch_assoc()['status'];
            
            if ($loan_status !== 'pending' && $loan_status !== 'rejected') {
                return false; // Cannot delete loans that are approved, active, or paid
            }
            
            // Delete the loan
            $stmt = $this->db->prepare("DELETE FROM loans WHERE loan_id = ?");
            $stmt->bind_param("i", $loan_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error deleting loan: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get loan statistics
     * 
     * @return array Statistics data
     */
    public function getLoanStatistics() {
        try {
            // Total active loans amount
            $active_query = "SELECT COUNT(*) as active_count, SUM(amount) as active_amount 
                            FROM loans 
                            WHERE status IN ('active', 'disbursed')"; 
            $active_result = $this->db->query($active_query);
            $active_stats = $active_result->fetch_assoc();
            
            // Total pending loans
            $pending_query = "SELECT COUNT(*) as pending_count, SUM(amount) as pending_amount 
                             FROM loans 
                             WHERE status = 'pending'"; 
            $pending_result = $this->db->query($pending_query);
            $pending_stats = $pending_result->fetch_assoc();
            
            // Total paid loans
            $paid_query = "SELECT COUNT(*) as paid_count, SUM(amount) as paid_amount 
                          FROM loans 
                          WHERE status = 'paid'"; 
            $paid_result = $this->db->query($paid_query);
            $paid_stats = $paid_result->fetch_assoc();
            
            // Total repayments this month
            $month_query = "SELECT SUM(amount) as month_amount 
                           FROM loan_repayments 
                           WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
                           AND YEAR(payment_date) = YEAR(CURRENT_DATE())"; 
            $month_result = $this->db->query($month_query);
            $month_amount = $month_result->fetch_assoc()['month_amount'] ?? 0;
            
            // Recent loans
            $recent_query = "SELECT l.*, m.first_name, m.last_name 
                           FROM loans l 
                           JOIN members m ON l.member_id = m.member_id 
                           ORDER BY l.application_date DESC 
                           LIMIT 5";
            $recent_result = $this->db->query($recent_query);
            
            $recent_loans = [];
            while ($row = $recent_result->fetch_assoc()) {
                $recent_loans[] = $row;
            }
            
            // Loans by status
            $status_query = "SELECT status, COUNT(*) as count, SUM(amount) as total_amount 
                            FROM loans 
                            GROUP BY status";
            $status_result = $this->db->query($status_query);
            
            $loans_by_status = [];
            while ($row = $status_result->fetch_assoc()) {
                $loans_by_status[] = $row;
            }
            
            return [
                'active_loans' => [
                    'count' => $active_stats['active_count'] ?? 0,
                    'amount' => $active_stats['active_amount'] ?? 0
                ],
                'pending_loans' => [
                    'count' => $pending_stats['pending_count'] ?? 0,
                    'amount' => $pending_stats['pending_amount'] ?? 0
                ],
                'paid_loans' => [
                    'count' => $paid_stats['paid_count'] ?? 0,
                    'amount' => $paid_stats['paid_amount'] ?? 0
                ],
                'month_repayment_amount' => $month_amount,
                'recent_loans' => $recent_loans,
                'loans_by_status' => $loans_by_status
            ];
        } catch (Exception $e) {
            // Log error
            error_log("Error getting loan statistics: " . $e->getMessage());
            return [
                'active_loans' => ['count' => 0, 'amount' => 0],
                'pending_loans' => ['count' => 0, 'amount' => 0],
                'paid_loans' => ['count' => 0, 'amount' => 0],
                'month_repayment_amount' => 0,
                'recent_loans' => [],
                'loans_by_status' => []
            ];
        }
    }
    
    /**
     * Calculate monthly payment for a loan
     * 
     * @param float $principal Loan amount
     * @param float $annual_interest_rate Annual interest rate (percentage)
     * @param int $term_months Loan term in months
     * @return float Monthly payment amount
     */
    public function calculateMonthlyPayment($principal, $annual_interest_rate, $term_months) {
        // Convert annual interest rate to monthly decimal rate
        $monthly_interest_rate = ($annual_interest_rate / 100) / 12;
        
        // If interest rate is 0, simple division
        if ($monthly_interest_rate == 0) {
            return $principal / $term_months;
        }
        
        // Calculate monthly payment using amortization formula
        $monthly_payment = $principal * $monthly_interest_rate * 
                          pow(1 + $monthly_interest_rate, $term_months) / 
                          (pow(1 + $monthly_interest_rate, $term_months) - 1);
        
        return round($monthly_payment, 2);
    }
    
    /**
     * Get loan statuses
     * 
     * @return array List of loan statuses
     */
    public function getLoanStatuses() {
        return [
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'disbursed' => 'Disbursed',
            'active' => 'Active (Repaying)',
            'defaulted' => 'Defaulted',
            'paid' => 'Fully Paid'
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
     * Approve a loan application
     * 
     * @param int $loan_id Loan ID
     * @param string $notes Optional notes about the approval
     * @return bool True on success, false on failure
     */
    public function approveLoan($loan_id, $notes = '') {
        try {
            $loan_id = (int)$loan_id;
            $notes = Utilities::sanitizeInput($notes);
            
            // Check if loan is in pending status
            $checkStmt = $this->db->prepare("SELECT status FROM loans WHERE loan_id = ?");
            $checkStmt->bind_param("i", $loan_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                return false; // Loan not found
            }
            
            $loan_status = $result->fetch_assoc()['status'];
            
            if ($loan_status !== 'pending') {
                return false; // Can only approve pending loans
            }
            
            // Update loan status to approved
            $approval_date = date('Y-m-d');
            $status = 'approved';
            
            $stmt = $this->db->prepare("UPDATE loans 
                SET status = ?, approval_date = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW() 
                WHERE loan_id = ?");
            $stmt->bind_param("sssi", $status, $approval_date, $notes, $loan_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error approving loan: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject a loan application
     * 
     * @param int $loan_id Loan ID
     * @param string $notes Optional notes about the rejection
     * @return bool True on success, false on failure
     */
    public function rejectLoan($loan_id, $notes = '') {
        try {
            $loan_id = (int)$loan_id;
            $notes = Utilities::sanitizeInput($notes);
            
            // Check if loan is in pending status
            $checkStmt = $this->db->prepare("SELECT status FROM loans WHERE loan_id = ?");
            $checkStmt->bind_param("i", $loan_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                return false; // Loan not found
            }
            
            $loan_status = $result->fetch_assoc()['status'];
            
            if ($loan_status !== 'pending') {
                return false; // Can only reject pending loans
            }
            
            // Update loan status to rejected
            $status = 'rejected';
            
            $stmt = $this->db->prepare("UPDATE loans 
                SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW() 
                WHERE loan_id = ?");
            $stmt->bind_param("ssi", $status, $notes, $loan_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error rejecting loan: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disburse an approved loan
     * 
     * @param int $loan_id Loan ID
     * @param array $data Disbursement data
     * @return bool True on success, false on failure
     */
    public function disburseLoan($loan_id, $data) {
        try {
            $loan_id = (int)$loan_id;
            $disbursement_date = $data['disbursement_date'] ?? date('Y-m-d');
            $payment_method = Utilities::sanitizeInput($data['payment_method'] ?? 'Cash');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Check if loan is in approved status
            $checkStmt = $this->db->prepare("SELECT status FROM loans WHERE loan_id = ?");
            $checkStmt->bind_param("i", $loan_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                return false; // Loan not found
            }
            
            $loan_status = $result->fetch_assoc()['status'];
            
            if ($loan_status !== 'approved') {
                return false; // Can only disburse approved loans
            }
            
            // Update loan status to disbursed
            $status = 'disbursed';
            $disbursement_note = "Loan disbursed on $disbursement_date via $payment_method. " . $notes;
            
            $stmt = $this->db->prepare("UPDATE loans 
                SET status = ?, disbursement_date = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW() 
                WHERE loan_id = ?");
            $stmt->bind_param("sssi", $status, $disbursement_date, $disbursement_note, $loan_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error disbursing loan: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a repayment to a loan
     * 
     * @param array $data Repayment data
     * @return int|bool The ID of the newly created repayment or false on failure
     */
    public function addRepayment($data) {
        return $this->addLoanRepayment($data);
    }
    
    /**
     * Mark a loan as fully paid
     * 
     * @param int $loan_id Loan ID
     * @return bool True on success, false on failure
     */
    public function markLoanAsPaid($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            $status = 'paid';
            $notes = "Loan marked as fully paid on " . date('Y-m-d');
            
            $stmt = $this->db->prepare("UPDATE loans 
                SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW() 
                WHERE loan_id = ?");
            $stmt->bind_param("ssi", $status, $notes, $loan_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log error
            error_log("Error marking loan as paid: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get CSS class for loan status badge
     * 
     * @param string $status Loan status
     * @return string CSS class name
     */
    public function getStatusBadgeClass($status) {
        $classes = [
            'pending' => 'warning',
            'approved' => 'info',
            'rejected' => 'danger',
            'disbursed' => 'primary',
            'active' => 'success',
            'defaulted' => 'danger',
            'paid' => 'secondary'
        ];
        
        return $classes[$status] ?? 'secondary';
    }
}
?>