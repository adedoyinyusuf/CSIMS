<?php
/**
 * Workflow Controller
 * 
 * Handles all workflow approval operations including loan approvals,
 * contribution withdrawals, penalty waivers, and dividend declarations.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/utilities.php';

class WorkflowController {
    private $db;
    
    /**
     * Constructor - initializes database connection
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Submit item for workflow approval
     * 
     * @param array $data Workflow submission data
     * @return int|bool The approval ID or false on failure
     */
    public function submitForApproval($data) {
        try {
            // Sanitize inputs
            $workflow_type = Utilities::sanitizeInput($data['workflow_type']);
            $reference_id = (int)$data['reference_id'];
            $member_id = isset($data['member_id']) ? (int)$data['member_id'] : null;
            $submitted_by = (int)$data['submitted_by'];
            $priority = Utilities::sanitizeInput($data['priority'] ?? 'normal');
            $deadline = isset($data['deadline']) ? $data['deadline'] : null;
            $notes = Utilities::sanitizeInput($data['notes'] ?? '');
            
            // Get approval chain based on workflow type and amount
            $approval_chain = $this->getApprovalChain($workflow_type, $data);
            $total_stages = count($approval_chain);
            
            // Insert workflow approval record
            $stmt = $this->db->prepare("INSERT INTO workflow_approvals 
                (workflow_type, reference_id, member_id, current_stage, total_stages, 
                approval_chain, status, submitted_by, priority, deadline, notes, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())");
            
            $approval_chain_json = json_encode($approval_chain);
            $stmt->bind_param("siiisissss", $workflow_type, $reference_id, $member_id, 
                            1, $total_stages, $approval_chain_json, $submitted_by, 
                            $priority, $deadline, $notes);
            
            if ($stmt->execute()) {
                $approval_id = $stmt->insert_id;
                
                // Update the reference item status to 'pending approval'
                $this->updateReferenceStatus($workflow_type, $reference_id, 'Pending');
                
                // Send notifications to approvers
                $this->notifyApprovers($approval_id, $approval_chain[0]);
                
                return $approval_id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error submitting for approval: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process approval/rejection at current stage
     * 
     * @param int $approval_id Approval ID
     * @param int $approver_id Admin ID processing the approval
     * @param string $action 'approve' or 'reject'
     * @param string $comments Optional comments
     * @return bool True on success, false on failure
     */
    public function processApproval($approval_id, $approver_id, $action, $comments = '') {
        try {
            $approval_id = (int)$approval_id;
            $approver_id = (int)$approver_id;
            $action = strtolower($action);
            $comments = Utilities::sanitizeInput($comments);
            
            // Get current approval record
            $approval = $this->getApprovalById($approval_id);
            if (!$approval) {
                return false;
            }
            
            // Parse approval chain
            $approval_chain = json_decode($approval['approval_chain'], true);
            $current_stage = (int)$approval['current_stage'];
            
            // Verify approver is authorized for current stage
            if (!$this->isAuthorizedApprover($approver_id, $approval_chain[$current_stage - 1])) {
                return false;
            }
            
            // Begin transaction
            $this->db->begin_transaction();
            
            if ($action === 'reject') {
                // Rejection - update status and reference
                $stmt = $this->db->prepare("UPDATE workflow_approvals 
                    SET status = 'rejected', approved_by = ?, approved_at = NOW(), 
                    rejection_reason = ? WHERE approval_id = ?");
                $stmt->bind_param("isi", $approver_id, $comments, $approval_id);
                $stmt->execute();
                
                // Update reference status
                $this->updateReferenceStatus($approval['workflow_type'], $approval['reference_id'], 'Rejected');
                
                // Log approval action
                $this->logApprovalAction($approval_id, $approver_id, 'rejected', $comments);
                
            } elseif ($action === 'approve') {
                if ($current_stage < $approval['total_stages']) {
                    // Move to next stage
                    $next_stage = $current_stage + 1;
                    $stmt = $this->db->prepare("UPDATE workflow_approvals 
                        SET current_stage = ?, status = 'in_progress' WHERE approval_id = ?");
                    $stmt->bind_param("ii", $next_stage, $approval_id);
                    $stmt->execute();
                    
                    // Notify next approver
                    $this->notifyApprovers($approval_id, $approval_chain[$next_stage - 1]);
                    
                } else {
                    // Final approval - complete workflow
                    $stmt = $this->db->prepare("UPDATE workflow_approvals 
                        SET status = 'approved', approved_by = ?, approved_at = NOW() 
                        WHERE approval_id = ?");
                    $stmt->bind_param("ii", $approver_id, $approval_id);
                    $stmt->execute();
                    
                    // Update reference status and perform final actions
                    $this->finalizeApproval($approval);
                }
                
                // Log approval action
                $this->logApprovalAction($approval_id, $approver_id, 'approved', $comments);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error processing approval: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get approval chain based on workflow type and data
     * 
     * @param string $workflow_type Type of workflow
     * @param array $data Additional data for determining chain
     * @return array Approval chain
     */
    private function getApprovalChain($workflow_type, $data) {
        switch ($workflow_type) {
            case 'loan_application':
                $amount = (float)($data['amount'] ?? 0);
                if ($amount <= 100000) {
                    return [['role' => 'Admin', 'min_count' => 1]];
                } elseif ($amount <= 500000) {
                    return [
                        ['role' => 'Admin', 'min_count' => 1],
                        ['role' => 'Super Admin', 'min_count' => 1]
                    ];
                } else {
                    return [
                        ['role' => 'Admin', 'min_count' => 1],
                        ['role' => 'Super Admin', 'min_count' => 2]
                    ];
                }
                
            case 'loan_disbursement':
                return [['role' => 'Super Admin', 'min_count' => 1]];
                
            case 'contribution_withdrawal':
                $amount = (float)($data['amount'] ?? 0);
                if ($amount <= 50000) {
                    return [['role' => 'Admin', 'min_count' => 1]];
                } else {
                    return [
                        ['role' => 'Admin', 'min_count' => 1],
                        ['role' => 'Super Admin', 'min_count' => 1]
                    ];
                }
                
            case 'penalty_waiver':
                return [
                    ['role' => 'Admin', 'min_count' => 1],
                    ['role' => 'Super Admin', 'min_count' => 1]
                ];
                
            case 'dividend_declaration':
                return [['role' => 'Super Admin', 'min_count' => 2]];
                
            default:
                return [['role' => 'Admin', 'min_count' => 1]];
        }
    }
    
    /**
     * Check if user is authorized approver for current stage
     * 
     * @param int $approver_id Admin ID
     * @param array $stage_config Stage configuration
     * @return bool True if authorized
     */
    private function isAuthorizedApprover($approver_id, $stage_config) {
        $stmt = $this->db->prepare("SELECT role FROM admins WHERE admin_id = ? AND status = 'Active'");
        $stmt->bind_param("i", $approver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $admin = $result->fetch_assoc();
        $required_role = $stage_config['role'];
        
        // Check role hierarchy: Super Admin > Admin > Staff
        $role_hierarchy = ['Staff' => 1, 'Admin' => 2, 'Super Admin' => 3];
        
        return $role_hierarchy[$admin['role']] >= $role_hierarchy[$required_role];
    }
    
    /**
     * Update reference item status
     * 
     * @param string $workflow_type Type of workflow
     * @param int $reference_id Reference ID
     * @param string $status New status
     */
    private function updateReferenceStatus($workflow_type, $reference_id, $status) {
        switch ($workflow_type) {
            case 'loan_application':
            case 'loan_disbursement':
                $stmt = $this->db->prepare("UPDATE loans SET status = ? WHERE loan_id = ?");
                $stmt->bind_param("si", $status, $reference_id);
                $stmt->execute();
                break;
                
            case 'contribution_withdrawal':
                $stmt = $this->db->prepare("UPDATE contribution_withdrawals SET status = ? WHERE withdrawal_id = ?");
                $stmt->bind_param("si", $status, $reference_id);
                $stmt->execute();
                break;
        }
    }
    
    /**
     * Finalize approval and perform final actions
     * 
     * @param array $approval Approval data
     */
    private function finalizeApproval($approval) {
        switch ($approval['workflow_type']) {
            case 'loan_application':
                $this->updateReferenceStatus('loan_application', $approval['reference_id'], 'Approved');
                // Generate payment schedule
                require_once __DIR__ . '/loan_controller.php';
                $loanController = new LoanController();
                $loan = $loanController->getLoanById($approval['reference_id']);
                if ($loan) {
                    $loanController->generatePaymentSchedule(
                        $loan['loan_id'], $loan['amount'], $loan['interest_rate'], 
                        $loan['term'], $loan['application_date']
                    );
                }
                break;
                
            case 'loan_disbursement':
                $this->updateReferenceStatus('loan_disbursement', $approval['reference_id'], 'Disbursed');
                break;
                
            case 'contribution_withdrawal':
                $this->updateReferenceStatus('contribution_withdrawal', $approval['reference_id'], 'Approved');
                break;
        }
    }
    
    /**
     * Send notifications to approvers
     * 
     * @param int $approval_id Approval ID
     * @param array $stage_config Stage configuration
     */
    private function notifyApprovers($approval_id, $stage_config) {
        // Get approvers for this stage
        $role = $stage_config['role'];
        $stmt = $this->db->prepare("SELECT admin_id, first_name, last_name, email 
            FROM admins WHERE role = ? AND status = 'Active' ORDER BY admin_id");
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Create notification for each approver
        require_once __DIR__ . '/notification_controller.php';
        $notificationController = new NotificationController();
        
        while ($approver = $result->fetch_assoc()) {
            $notificationController->createNotification([
                'recipient_id' => $approver['admin_id'],
                'recipient_type' => 'admin',
                'title' => 'Approval Required',
                'message' => "You have a new item requiring approval. Approval ID: {$approval_id}",
                'type' => 'approval_request',
                'reference_id' => $approval_id
            ]);
        }
    }
    
    /**
     * Log approval action
     * 
     * @param int $approval_id Approval ID
     * @param int $approver_id Approver ID
     * @param string $action Action taken
     * @param string $comments Comments
     */
    private function logApprovalAction($approval_id, $approver_id, $action, $comments) {
        // You can implement audit logging here
        error_log("Approval Action - ID: {$approval_id}, Approver: {$approver_id}, Action: {$action}, Comments: {$comments}");
    }
    
    /**
     * Get approval by ID
     * 
     * @param int $approval_id Approval ID
     * @return array|bool Approval data or false
     */
    public function getApprovalById($approval_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM workflow_approvals WHERE approval_id = ?");
            $stmt->bind_param("i", $approval_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0 ? $result->fetch_assoc() : false;
        } catch (Exception $e) {
            error_log("Error getting approval: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending approvals for admin
     * 
     * @param int $admin_id Admin ID
     * @return array Pending approvals
     */
    public function getPendingApprovalsForAdmin($admin_id) {
        try {
            // Get admin role
            $stmt = $this->db->prepare("SELECT role FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $admin_result = $stmt->get_result();
            
            if ($admin_result->num_rows === 0) {
                return [];
            }
            
            $admin = $admin_result->fetch_assoc();
            $admin_role = $admin['role'];
            
            // Get all pending/in_progress approvals
            $stmt = $this->db->prepare("SELECT wa.*, 
                CASE 
                    WHEN wa.workflow_type = 'loan_application' THEN (SELECT CONCAT(m.first_name, ' ', m.last_name) FROM loans l JOIN members m ON l.member_id = m.member_id WHERE l.loan_id = wa.reference_id)
                    ELSE 'N/A'
                END as reference_name,
                CASE 
                    WHEN wa.workflow_type = 'loan_application' THEN (SELECT amount FROM loans WHERE loan_id = wa.reference_id)
                    ELSE NULL
                END as reference_amount
                FROM workflow_approvals wa 
                WHERE wa.status IN ('pending', 'in_progress') 
                ORDER BY wa.priority DESC, wa.submitted_at ASC");
                
            $stmt->execute();
            $result = $stmt->get_result();
            
            $pending_approvals = [];
            while ($approval = $result->fetch_assoc()) {
                // Check if admin can approve current stage
                $approval_chain = json_decode($approval['approval_chain'], true);
                $current_stage = (int)$approval['current_stage'];
                
                if ($current_stage <= count($approval_chain)) {
                    $stage_config = $approval_chain[$current_stage - 1];
                    if ($this->isAuthorizedApprover($admin_id, $stage_config)) {
                        $pending_approvals[] = $approval;
                    }
                }
            }
            
            return $pending_approvals;
            
        } catch (Exception $e) {
            error_log("Error getting pending approvals: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get approval history
     * 
     * @param array $filters Filters for history
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Approval history
     */
    public function getApprovalHistory($filters = [], $limit = 50, $offset = 0) {
        try {
            $query = "SELECT wa.*, 
                CASE 
                    WHEN wa.workflow_type = 'loan_application' THEN (SELECT CONCAT(m.first_name, ' ', m.last_name) FROM loans l JOIN members m ON l.member_id = m.member_id WHERE l.loan_id = wa.reference_id)
                    ELSE 'N/A'
                END as reference_name,
                CASE 
                    WHEN wa.workflow_type = 'loan_application' THEN (SELECT amount FROM loans WHERE loan_id = wa.reference_id)
                    ELSE NULL
                END as reference_amount,
                CONCAT(a1.first_name, ' ', a1.last_name) as submitted_by_name,
                CONCAT(a2.first_name, ' ', a2.last_name) as approved_by_name
                FROM workflow_approvals wa 
                LEFT JOIN admins a1 ON wa.submitted_by = a1.admin_id
                LEFT JOIN admins a2 ON wa.approved_by = a2.admin_id
                WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Add filters
            if (!empty($filters['workflow_type'])) {
                $query .= " AND wa.workflow_type = ?";
                $params[] = $filters['workflow_type'];
                $types .= "s";
            }
            
            if (!empty($filters['status'])) {
                $query .= " AND wa.status = ?";
                $params[] = $filters['status'];
                $types .= "s";
            }
            
            if (!empty($filters['date_from'])) {
                $query .= " AND DATE(wa.submitted_at) >= ?";
                $params[] = $filters['date_from'];
                $types .= "s";
            }
            
            if (!empty($filters['date_to'])) {
                $query .= " AND DATE(wa.submitted_at) <= ?";
                $params[] = $filters['date_to'];
                $types .= "s";
            }
            
            $query .= " ORDER BY wa.submitted_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $this->db->prepare($query);
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            
            return $history;
            
        } catch (Exception $e) {
            error_log("Error getting approval history: " . $e->getMessage());
            return [];
        }
    }
}
