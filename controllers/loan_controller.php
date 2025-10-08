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
            
            // Combine guarantor info with notes if guarantor is provided
            if (!empty($guarantor)) {
                $notes = "Guarantor: " . $guarantor . (!empty($notes) ? "\n\nNotes: " . $notes : "");
            }
            
            // Calculate monthly payment (principal + interest)
            $monthly_payment = isset($data['monthly_payment']) ? (float)$data['monthly_payment'] : $this->calculateMonthlyPayment($amount, $interest_rate, $term_months);
            
            // Extract additional member-submitted fields
            $savings = isset($data['savings']) ? (float)$data['savings'] : null;
            $month_deduction_started = Utilities::sanitizeInput($data['month_deduction_started'] ?? '');
            $month_deduction_end = Utilities::sanitizeInput($data['month_deduction_end'] ?? '');
            $other_payment_plans = Utilities::sanitizeInput($data['other_payment_plans'] ?? '');
            $remarks = Utilities::sanitizeInput($data['remarks'] ?? '');
            
            // Prepare SQL statement - using actual table columns including new fields
            $stmt = $this->db->prepare("INSERT INTO loans 
                (member_id, amount, purpose, term, interest_rate, monthly_payment, 
                application_date, status, collateral, notes, savings, month_deduction_started, 
                month_deduction_end, other_payment_plans, remarks, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idsiddssssdssss", $member_id, $amount, $purpose, $term_months, 
                            $interest_rate, $monthly_payment, $application_date, $status, $collateral, $notes,
                            $savings, $month_deduction_started, $month_deduction_end, $other_payment_plans, $remarks);
            
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
            
            // Extract additional member-submitted fields for update
            $savings = isset($data['savings']) && $data['savings'] !== '' ? (float)$data['savings'] : null;
            $month_deduction_started = Utilities::sanitizeInput($data['month_deduction_started'] ?? '');
            $month_deduction_end = Utilities::sanitizeInput($data['month_deduction_end'] ?? '');
            $other_payment_plans = Utilities::sanitizeInput($data['other_payment_plans'] ?? '');
            $remarks = Utilities::sanitizeInput($data['remarks'] ?? '');
            
            // Calculate monthly payment (principal + interest)
            $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $term_months);
            
            // Combine guarantor info with notes if guarantor is provided (for backward compatibility)
            if (!empty($guarantor)) {
                $combined_notes = "Guarantor: " . $guarantor;
                if (!empty($notes)) {
                    $combined_notes .= "\n\nAdmin Notes: " . $notes;
                }
                $notes = $combined_notes;
            }
            
            // Prepare SQL statement - using actual table structure
            $stmt = $this->db->prepare("UPDATE loans 
                SET amount = ?, purpose = ?, term = ?, interest_rate = ?, 
                application_date = ?, status = ?, collateral = ?, notes = ?,
                savings = ?, month_deduction_started = ?, month_deduction_end = ?, 
                other_payment_plans = ?, remarks = ?, monthly_payment = ?, updated_at = NOW() 
                WHERE loan_id = ?");
            
            $stmt->bind_param("dsisssssdssssdi", $amount, $purpose, $term_months, $interest_rate, 
                            $application_date, $status, $collateral, $notes,
                            $savings, $month_deduction_started, $month_deduction_end, 
                            $other_payment_plans, $remarks, $monthly_payment, $loan_id);
            
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
            $approval_date = ($status === 'Approved') ? date('Y-m-d') : null;
            $disbursement_date = ($status === 'Disbursed') ? date('Y-m-d') : null;
            
            // Prepare SQL statement
            if ($status === 'Approved') {
                $stmt = $this->db->prepare("UPDATE loans 
                    SET status = ?, approval_date = ?, notes = CONCAT(notes, '\n', ?), updated_at = NOW() 
                    WHERE loan_id = ?");
                $stmt->bind_param("sssi", $status, $approval_date, $notes, $loan_id);
            } elseif ($status === 'Disbursed') {
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
            $limit = (int)$limit;
            
            $query = "SELECT l.*, 
                m.first_name, m.last_name, m.email, m.phone 
                FROM loans l 
                JOIN members m ON l.member_id = m.member_id 
                WHERE l.member_id = ? 
                ORDER BY l.application_date DESC";
            
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
            error_log("Error getting loans by member ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add enhanced loan application with guarantors and collateral
     * 
     * @param array $data Enhanced loan application data including guarantors and collaterals
     * @return int|bool The ID of the newly created loan or false on failure
     */
    public function addEnhancedLoanApplication($data) {
        try {
            // Begin transaction
            $this->db->begin_transaction();
            
            // Sanitize basic loan data
            $member_id = (int)$data['member_id'];
            $amount = (float)$data['amount'];
            $purpose = Utilities::sanitizeInput($data['purpose']);
            $term_months = (int)$data['term_months'];
            $interest_rate = (float)$data['interest_rate'];
            $application_date = $data['application_date'] ?? date('Y-m-d');
            $status = Utilities::sanitizeInput($data['status'] ?? 'Pending');
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Calculate monthly payment
            $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $term_months);
            
            // Insert main loan record
            $stmt = $this->db->prepare("INSERT INTO loans 
                (member_id, amount, purpose, term, interest_rate, monthly_payment,
                application_date, status, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idsidfsss", $member_id, $amount, $purpose, $term_months, 
                            $interest_rate, $monthly_payment, $application_date, $status, $notes);
            
            if (!$stmt->execute()) {
                $this->db->rollback();
                return false;
            }
            
            $loan_id = $stmt->insert_id;
            
            // Insert guarantors
            if (!empty($data['guarantors'])) {
                $guarantor_stmt = $this->db->prepare("INSERT INTO loan_guarantors 
                    (loan_id, guarantor_member_id, guarantee_amount, guarantee_percentage, 
                    guarantee_type, relationship_to_borrower, guarantee_date, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                
                foreach ($data['guarantors'] as $guarantor) {
                    $guarantor_member_id = (int)$guarantor['member_id'];
                    $guarantee_amount = (float)$guarantor['guarantee_amount'];
                    $guarantee_percentage = (float)($guarantor['guarantee_percentage'] ?? 100);
                    $guarantee_type = $guarantor['guarantee_type'] ?? 'full';
                    $relationship = Utilities::sanitizeInput($guarantor['relationship'] ?? '');
                    $guarantee_date = $application_date;
                    
                    $guarantor_stmt->bind_param("iidfssss", $loan_id, $guarantor_member_id, 
                                              $guarantee_amount, $guarantee_percentage, $guarantee_type, 
                                              $relationship, $guarantee_date);
                    
                    if (!$guarantor_stmt->execute()) {
                        $this->db->rollback();
                        return false;
                    }
                }
            }
            
            // Insert collaterals
            if (!empty($data['collaterals'])) {
                $collateral_stmt = $this->db->prepare("INSERT INTO loan_collateral 
                    (loan_id, collateral_type, description, estimated_value, location, 
                    document_reference, insurance_details, status, pledge_date, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pledged', ?, NOW())");
                
                foreach ($data['collaterals'] as $collateral) {
                    $collateral_type = $collateral['type'];
                    $description = Utilities::sanitizeInput($collateral['description']);
                    $estimated_value = (float)$collateral['estimated_value'];
                    $location = Utilities::sanitizeInput($collateral['location'] ?? '');
                    $document_reference = Utilities::sanitizeInput($collateral['document_reference'] ?? '');
                    $insurance_details = Utilities::sanitizeInput($collateral['insurance_details'] ?? '');
                    $pledge_date = $application_date;
                    
                    $collateral_stmt->bind_param("issdssss", $loan_id, $collateral_type, 
                                                $description, $estimated_value, $location, 
                                                $document_reference, $insurance_details, $pledge_date);
                    
                    if (!$collateral_stmt->execute()) {
                        $this->db->rollback();
                        return false;
                    }
                }
            }
            
            // Generate payment schedule if loan is approved
            if (strtolower($status) === 'approved') {
                $this->generatePaymentSchedule($loan_id, $amount, $interest_rate, $term_months, $application_date);
            }
            
            // Commit transaction
            $this->db->commit();
            return $loan_id;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error adding enhanced loan application: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate payment schedule for a loan
     * 
     * @param int $loan_id Loan ID
     * @param float $amount Loan amount
     * @param float $interest_rate Annual interest rate
     * @param int $term_months Loan term in months
     * @param string $start_date Start date for payments
     * @return bool True on success, false on failure
     */
    public function generatePaymentSchedule($loan_id, $amount, $interest_rate, $term_months, $start_date) {
        try {
            $monthly_payment = $this->calculateMonthlyPayment($amount, $interest_rate, $term_months);
            $monthly_interest_rate = $interest_rate / 100 / 12;
            $remaining_balance = $amount;
            $payment_date = new DateTime($start_date);
            $payment_date->add(new DateInterval('P1M')); // First payment due next month
            
            $stmt = $this->db->prepare("INSERT INTO loan_payment_schedule 
                (loan_id, payment_number, due_date, opening_balance, principal_amount, 
                interest_amount, total_amount, closing_balance, payment_status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
            for ($payment_num = 1; $payment_num <= $term_months; $payment_num++) {
                $opening_balance = $remaining_balance;
                $interest_amount = $remaining_balance * $monthly_interest_rate;
                $principal_amount = $monthly_payment - $interest_amount;
                $total_amount = $monthly_payment;
                $closing_balance = max(0, $remaining_balance - $principal_amount);
                
                // Adjust last payment to ensure loan is fully paid
                if ($payment_num === $term_months) {
                    $principal_amount = $remaining_balance;
                    $total_amount = $principal_amount + $interest_amount;
                    $closing_balance = 0;
                }
                
                $due_date = $payment_date->format('Y-m-d');
                
                $stmt->bind_param("iisddddds", $loan_id, $payment_num, $due_date, 
                                $opening_balance, $principal_amount, $interest_amount, 
                                $total_amount, $closing_balance);
                
                if (!$stmt->execute()) {
                    return false;
                }
                
                $remaining_balance = $closing_balance;
                $payment_date->add(new DateInterval('P1M'));
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error generating payment schedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get loan guarantors
     * 
     * @param int $loan_id Loan ID
     * @return array|bool Guarantors data or false on failure
     */
    public function getLoanGuarantors($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            
            $stmt = $this->db->prepare("SELECT lg.*, 
                m.first_name, m.last_name, m.email, m.phone 
                FROM loan_guarantors lg 
                JOIN members m ON lg.guarantor_member_id = m.member_id 
                WHERE lg.loan_id = ? 
                ORDER BY lg.created_at ASC");
            
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $guarantors = [];
            while ($row = $result->fetch_assoc()) {
                $guarantors[] = $row;
            }
            
            return $guarantors;
        } catch (Exception $e) {
            error_log("Error getting loan guarantors: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get loan collateral
     * 
     * @param int $loan_id Loan ID
     * @return array|bool Collateral data or false on failure
     */
    public function getLoanCollateral($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            
            $stmt = $this->db->prepare("SELECT * FROM loan_collateral 
                WHERE loan_id = ? 
                ORDER BY created_at ASC");
            
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $collaterals = [];
            while ($row = $result->fetch_assoc()) {
                $collaterals[] = $row;
            }
            
            return $collaterals;
        } catch (Exception $e) {
            error_log("Error getting loan collateral: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get loan payment schedule
     * 
     * @param int $loan_id Loan ID
     * @return array|bool Payment schedule data or false on failure
     */
    public function getLoanPaymentSchedule($loan_id) {
        try {
            $loan_id = (int)$loan_id;
            
            $stmt = $this->db->prepare("SELECT * FROM loan_payment_schedule 
                WHERE loan_id = ? 
                ORDER BY payment_number ASC");
            
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $schedule = [];
            while ($row = $result->fetch_assoc()) {
                $schedule[] = $row;
            }
            
            return $schedule;
        } catch (Exception $e) {
            error_log("Error getting loan payment schedule: " . $e->getMessage());
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
                    WHEN (amount_paid + ?) >= amount THEN 'Paid' 
                    WHEN status = 'Disbursed' THEN 'Disbursed' 
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
            
            if ($loan_status !== 'Pending' && $loan_status !== 'Rejected') {
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
                            WHERE status IN ('Approved', 'Disbursed')"; 
            $active_result = $this->db->query($active_query);
            $active_stats = $active_result->fetch_assoc();
            
            // Total pending loans
            $pending_query = "SELECT COUNT(*) as pending_count, SUM(amount) as pending_amount 
                             FROM loans 
                             WHERE status = 'Pending'"; 
            $pending_result = $this->db->query($pending_query);
            $pending_stats = $pending_result->fetch_assoc();
            
            // Total paid loans
            $paid_query = "SELECT COUNT(*) as paid_count, SUM(amount) as paid_amount 
                          FROM loans 
                          WHERE status = 'Paid'";
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
     * Get interest rate based on loan amount and term
     * 
     * @param float $amount Loan amount
     * @param int $term_months Loan term in months
     * @return float Interest rate percentage
     */
    public function getInterestRate($amount, $term_months) {
        // Default interest rate structure based on loan characteristics
        $base_rate = 5.0; // 5% base rate
        
        // Adjust rate based on loan amount (higher amounts get better rates)
        if ($amount >= 500000) {
            $amount_adjustment = -0.5; // 0.5% discount for large loans
        } elseif ($amount >= 100000) {
            $amount_adjustment = 0; // No adjustment for medium loans
        } else {
            $amount_adjustment = 1.0; // 1% premium for small loans
        }
        
        // Adjust rate based on term (longer terms have higher rates)
        if ($term_months <= 6) {
            $term_adjustment = 0; // No adjustment for short term
        } elseif ($term_months <= 12) {
            $term_adjustment = 0.5; // Small premium for medium term
        } elseif ($term_months <= 24) {
            $term_adjustment = 1.0; // Higher rate for longer term
        } else {
            $term_adjustment = 1.5; // Highest rate for very long term
        }
        
        $final_rate = $base_rate + $amount_adjustment + $term_adjustment;
        
        // Ensure rate is within reasonable bounds (3% - 15%)
        return max(3.0, min(15.0, $final_rate));
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
        // Validate inputs to prevent division by zero
        if ($principal <= 0 || $term_months <= 0) {
            return 0;
        }
        
        // Convert annual interest rate to monthly decimal rate
        $monthly_interest_rate = ($annual_interest_rate / 100) / 12;
        
        // If interest rate is 0, simple division
        if ($monthly_interest_rate == 0) {
            return round($principal / $term_months, 2);
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
            
            if ($loan_status !== 'Pending') {
                return false; // Can only approve pending loans
            }
            
            // Update loan status to approved
            $approval_date = date('Y-m-d');
            $status = 'Approved';
            
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
            
            if ($loan_status !== 'Pending') {
                return false; // Can only reject pending loans
            }
            
            // Update loan status to rejected
            $status = 'Rejected';
            
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
            
            if ($loan_status !== 'Approved') {
                return false; // Can only disburse approved loans
            }
            
            // Update loan status to disbursed
            $status = 'Disbursed';
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
            $status = 'Paid';
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
            'Pending' => 'warning',
            'Approved' => 'info',
            'Rejected' => 'danger',
            'Disbursed' => 'primary',
            'Paid' => 'secondary'
        ];
        
        return $classes[$status] ?? 'secondary';
    }
    
    /**
     * Add a loan (wrapper method for compatibility)
     * 
     * @param array $data Loan application data
     * @return int|bool The ID of the newly created loan or false on failure
     */
    public function addLoan($data) {
        return $this->addLoanApplication($data);
    }
    
    /**
     * Get loans for a specific member (wrapper method for compatibility)
     * 
     * @param int $member_id Member ID
     * @param int $limit Number of records to return (0 for all)
     * @return array|bool Loan data or false on failure
     */
    public function getMemberLoans($member_id, $limit = 0) {
        return $this->getLoansByMemberId($member_id, $limit);
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $loanController = new LoanController();
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'delete':
            if (!isset($_POST['loan_id']) || empty($_POST['loan_id'])) {
                $response['message'] = 'Loan ID is required';
                break;
            }
            
            $loan_id = (int)$_POST['loan_id'];
            
            // Get loan details first to check status
            $loan = $loanController->getLoanById($loan_id);
            if (!$loan) {
                $response['message'] = 'Loan not found';
                break;
            }
            
            // Check if loan can be deleted (only pending or rejected)
            if (!in_array($loan['status'], ['Pending', 'Rejected'])) {
                $response['message'] = 'Only pending or rejected loans can be deleted';
                break;
            }
            
            // Attempt to delete
            $result = $loanController->deleteLoan($loan_id);
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Loan application deleted successfully';
            } else {
                $response['message'] = 'Failed to delete loan application';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
    
    echo json_encode($response);
    exit();
}
?>
