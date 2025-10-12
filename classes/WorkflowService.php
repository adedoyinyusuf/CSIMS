<?php
require_once 'DatabaseConnection.php';
require_once 'LogService.php';
require_once 'NotificationService.php';

class WorkflowService {
    private $db;
    private $logService;
    private $notificationService;
    
    public function __construct() {
        $this->db = DatabaseConnection::getInstance()->getConnection();
        $this->logService = new LogService();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Start a new approval workflow
     */
    public function startWorkflow($entityType, $entityId, $amount = null, $requestedBy = null) {
        try {
            $this->db->beginTransaction();
            
            // Determine appropriate workflow template
            $template = $this->getWorkflowTemplate($entityType, $amount);
            if (!$template) {
                throw new Exception("No workflow template found for {$entityType}");
            }
            
            // Create main workflow record
            $sql = "INSERT INTO workflow_approvals (entity_type, entity_id, template_id, 
                    current_level, total_levels, status, requested_by, amount, created_at) 
                    VALUES (?, ?, ?, 1, ?, 'pending', ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $entityType, 
                $entityId, 
                $template['id'], 
                $template['total_levels'],
                $requestedBy,
                $amount
            ]);
            
            $workflowId = $this->db->lastInsertId();
            
            // Start at first approval level
            $this->processLevel($workflowId, 1);
            
            $this->db->commit();
            
            $this->logService->log("workflow_started", [
                'workflow_id' => $workflowId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'template' => $template['template_name']
            ]);
            
            return $workflowId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logService->log("workflow_start_failed", [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Process approval action
     */
    public function processApproval($workflowId, $approverId, $action, $comments = null) {
        try {
            $this->db->beginTransaction();
            
            // Get current workflow state
            $workflow = $this->getWorkflowById($workflowId);
            if (!$workflow || $workflow['status'] !== 'pending') {
                throw new Exception("Invalid or completed workflow");
            }
            
            // Verify approver has permission for current level
            $hasPermission = $this->verifyApproverPermission($workflowId, $approverId, $workflow['current_level']);
            if (!$hasPermission) {
                throw new Exception("Approver does not have permission for this level");
            }
            
            // Record the approval action
            $this->recordApprovalAction($workflowId, $approverId, $action, $comments, $workflow['current_level']);
            
            if ($action === 'approve') {
                $this->handleApproval($workflow);
            } elseif ($action === 'reject') {
                $this->handleRejection($workflow, $comments);
            } elseif ($action === 'request_changes') {
                $this->handleChangeRequest($workflow, $comments);
            }
            
            $this->db->commit();
            
            return $this->getWorkflowById($workflowId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get workflow template based on entity type and amount
     */
    private function getWorkflowTemplate($entityType, $amount = null) {
        $sql = "SELECT wt.*, COUNT(al.id) as total_levels
                FROM workflow_templates wt
                LEFT JOIN approval_levels al ON wt.id = al.template_id
                WHERE wt.entity_type = ? AND wt.is_active = 1";
        
        $params = [$entityType];
        
        if ($amount !== null && $entityType === 'loan') {
            $sql .= " AND (wt.min_amount IS NULL OR wt.min_amount <= ?) 
                      AND (wt.max_amount IS NULL OR wt.max_amount >= ?)";
            $params[] = $amount;
            $params[] = $amount;
        }
        
        $sql .= " GROUP BY wt.id ORDER BY wt.min_amount ASC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process specific approval level
     */
    private function processLevel($workflowId, $level) {
        // Get approvers for this level
        $sql = "SELECT al.*, u.id as user_id, u.username, u.email, r.role_name
                FROM approval_levels al
                JOIN workflow_approvals wa ON al.template_id = wa.template_id
                JOIN users u ON FIND_IN_SET(u.role, al.required_roles)
                JOIN roles r ON u.role = r.id
                WHERE wa.id = ? AND al.level_number = ? AND u.is_active = 1
                ORDER BY al.priority ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId, $level]);
        $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($approvers)) {
            throw new Exception("No approvers found for level {$level}");
        }
        
        // Create approval assignments
        foreach ($approvers as $approver) {
            $this->createApprovalAssignment($workflowId, $approver['user_id'], $level);
        }
        
        // Send notifications
        $this->sendLevelNotifications($workflowId, $level, $approvers);
        
        // Set timeout if specified
        $levelInfo = $approvers[0]; // Get level info from first approver
        if ($levelInfo['timeout_hours']) {
            $this->scheduleTimeout($workflowId, $level, $levelInfo['timeout_hours']);
        }
    }
    
    /**
     * Handle approval action
     */
    private function handleApproval($workflow) {
        $workflowId = $workflow['id'];
        $currentLevel = $workflow['current_level'];
        $totalLevels = $workflow['total_levels'];
        
        if ($currentLevel >= $totalLevels) {
            // Final approval - complete workflow
            $this->completeWorkflow($workflowId, 'approved');
        } else {
            // Move to next level
            $nextLevel = $currentLevel + 1;
            $this->updateWorkflowLevel($workflowId, $nextLevel);
            $this->processLevel($workflowId, $nextLevel);
        }
    }
    
    /**
     * Handle rejection action
     */
    private function handleRejection($workflow, $comments) {
        $this->completeWorkflow($workflow['id'], 'rejected', $comments);
    }
    
    /**
     * Handle change request action
     */
    private function handleChangeRequest($workflow, $comments) {
        $this->completeWorkflow($workflow['id'], 'changes_requested', $comments);
    }
    
    /**
     * Complete workflow with final status
     */
    private function completeWorkflow($workflowId, $status, $comments = null) {
        $sql = "UPDATE workflow_approvals 
                SET status = ?, completed_at = NOW(), final_comments = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $comments, $workflowId]);
        
        // Clear pending assignments
        $sql = "UPDATE approval_assignments 
                SET status = 'cancelled' 
                WHERE workflow_id = ? AND status = 'pending'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId]);
        
        // Trigger entity-specific completion logic
        $this->triggerCompletionActions($workflowId, $status);
        
        $this->logService->log("workflow_completed", [
            'workflow_id' => $workflowId,
            'final_status' => $status,
            'comments' => $comments
        ]);
    }
    
    /**
     * Record approval action in history
     */
    private function recordApprovalAction($workflowId, $approverId, $action, $comments, $level) {
        $sql = "INSERT INTO approval_actions (workflow_id, approver_id, action, 
                comments, level_number, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId, $approverId, $action, $comments, $level]);
        
        // Update assignment status
        $sql = "UPDATE approval_assignments 
                SET status = ?, action_taken_at = NOW(), comments = ?
                WHERE workflow_id = ? AND approver_id = ? AND level_number = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$action, $comments, $workflowId, $approverId, $level]);
    }
    
    /**
     * Create approval assignment
     */
    private function createApprovalAssignment($workflowId, $approverId, $level) {
        $sql = "INSERT INTO approval_assignments (workflow_id, approver_id, level_number, 
                assigned_at, status) VALUES (?, ?, ?, NOW(), 'pending')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId, $approverId, $level]);
    }
    
    /**
     * Verify approver has permission for level
     */
    private function verifyApproverPermission($workflowId, $approverId, $level) {
        $sql = "SELECT COUNT(*) 
                FROM approval_assignments 
                WHERE workflow_id = ? AND approver_id = ? AND level_number = ? 
                AND status = 'pending'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId, $approverId, $level]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Update workflow current level
     */
    private function updateWorkflowLevel($workflowId, $newLevel) {
        $sql = "UPDATE workflow_approvals SET current_level = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$newLevel, $workflowId]);
    }
    
    /**
     * Send notifications for level approval requests
     */
    private function sendLevelNotifications($workflowId, $level, $approvers) {
        $workflow = $this->getWorkflowById($workflowId);
        
        foreach ($approvers as $approver) {
            $this->notificationService->sendApprovalRequest(
                $approver['email'],
                $approver['username'],
                $workflow,
                $level
            );
            
            // Record notification in database
            $sql = "INSERT INTO workflow_notifications (workflow_id, recipient_id, 
                    notification_type, level_number, sent_at) 
                    VALUES (?, ?, 'approval_request', ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$workflowId, $approver['user_id'], $level]);
        }
    }
    
    /**
     * Schedule timeout for level
     */
    private function scheduleTimeout($workflowId, $level, $timeoutHours) {
        $sql = "INSERT INTO system_jobs (job_type, entity_id, scheduled_at, status, parameters)
                VALUES ('workflow_timeout', ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 'pending', ?)";
        
        $parameters = json_encode([
            'workflow_id' => $workflowId,
            'level' => $level,
            'timeout_hours' => $timeoutHours
        ]);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId, $timeoutHours, $parameters]);
    }
    
    /**
     * Process workflow timeout
     */
    public function processTimeout($workflowId, $level) {
        try {
            $this->db->beginTransaction();
            
            $workflow = $this->getWorkflowById($workflowId);
            if (!$workflow || $workflow['status'] !== 'pending' || $workflow['current_level'] != $level) {
                // Workflow already processed or moved to different level
                $this->db->commit();
                return;
            }
            
            // Mark as timed out
            $this->completeWorkflow($workflowId, 'timeout', 'Approval timeout exceeded');
            
            // Notify relevant parties
            $this->sendTimeoutNotifications($workflowId, $level);
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Send timeout notifications
     */
    private function sendTimeoutNotifications($workflowId, $level) {
        $workflow = $this->getWorkflowById($workflowId);
        
        // Notify requester
        if ($workflow['requested_by']) {
            $this->notificationService->sendTimeoutNotification($workflow['requested_by'], $workflow);
        }
        
        // Notify administrators
        $this->notificationService->sendAdminTimeoutAlert($workflow, $level);
    }
    
    /**
     * Trigger entity-specific completion actions
     */
    private function triggerCompletionActions($workflowId, $status) {
        $workflow = $this->getWorkflowById($workflowId);
        
        switch ($workflow['entity_type']) {
            case 'loan':
                $this->triggerLoanCompletionActions($workflow, $status);
                break;
            case 'member_registration':
                $this->triggerMemberRegistrationActions($workflow, $status);
                break;
            case 'withdrawal':
                $this->triggerWithdrawalActions($workflow, $status);
                break;
        }
    }
    
    /**
     * Handle loan approval completion
     */
    private function triggerLoanCompletionActions($workflow, $status) {
        $loanId = $workflow['entity_id'];
        
        if ($status === 'approved') {
            // Update loan status to approved
            $sql = "UPDATE loans SET status = 'approved', approved_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanId]);
            
            // Schedule disbursement if auto-disbursement is enabled
            $this->scheduleAutoDisbursement($loanId);
            
        } elseif ($status === 'rejected') {
            // Update loan status to rejected
            $sql = "UPDATE loans SET status = 'rejected', rejected_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanId]);
            
        } elseif ($status === 'changes_requested') {
            // Update loan status to revision requested
            $sql = "UPDATE loans SET status = 'revision_requested' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanId]);
        }
    }
    
    /**
     * Schedule auto-disbursement if enabled
     */
    private function scheduleAutoDisbursement($loanId) {
        // Check if auto-disbursement is enabled for this loan type
        $sql = "SELECT lt.auto_disburse, lt.disburse_delay_hours
                FROM loans l
                JOIN loan_types lt ON l.loan_type_id = lt.id
                WHERE l.id = ? AND lt.auto_disburse = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loanId]);
        $loanType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($loanType) {
            $delayHours = $loanType['disburse_delay_hours'] ?: 0;
            
            $sql = "INSERT INTO system_jobs (job_type, entity_id, scheduled_at, status, parameters)
                    VALUES ('auto_disburse', ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 'pending', ?)";
            
            $parameters = json_encode(['loan_id' => $loanId]);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanId, $delayHours, $parameters]);
        }
    }
    
    /**
     * Handle member registration completion
     */
    private function triggerMemberRegistrationActions($workflow, $status) {
        $memberId = $workflow['entity_id'];
        
        if ($status === 'approved') {
            $sql = "UPDATE members SET status = 'active', approved_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$memberId]);
            
            // Send welcome email
            $this->notificationService->sendWelcomeEmail($memberId);
        } elseif ($status === 'rejected') {
            $sql = "UPDATE members SET status = 'rejected' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$memberId]);
        }
    }
    
    /**
     * Handle withdrawal completion
     */
    private function triggerWithdrawalActions($workflow, $status) {
        $withdrawalId = $workflow['entity_id'];
        
        if ($status === 'approved') {
            // Process the withdrawal
            $sql = "UPDATE withdrawals SET status = 'approved', approved_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$withdrawalId]);
            
            // Schedule processing
            $this->scheduleWithdrawalProcessing($withdrawalId);
        } elseif ($status === 'rejected') {
            $sql = "UPDATE withdrawals SET status = 'rejected' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$withdrawalId]);
        }
    }
    
    /**
     * Schedule withdrawal processing
     */
    private function scheduleWithdrawalProcessing($withdrawalId) {
        $sql = "INSERT INTO system_jobs (job_type, entity_id, scheduled_at, status, parameters)
                VALUES ('process_withdrawal', ?, NOW(), 'pending', ?)";
        
        $parameters = json_encode(['withdrawal_id' => $withdrawalId]);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$withdrawalId, $parameters]);
    }
    
    /**
     * Get workflow by ID with full details
     */
    public function getWorkflowById($workflowId) {
        $sql = "SELECT wa.*, wt.template_name, wt.entity_type,
                       u.username as requested_by_name
                FROM workflow_approvals wa
                JOIN workflow_templates wt ON wa.template_id = wt.id
                LEFT JOIN users u ON wa.requested_by = u.id
                WHERE wa.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending approvals for user
     */
    public function getPendingApprovalsForUser($userId) {
        $sql = "SELECT wa.*, wt.template_name, wt.entity_type, aa.assigned_at,
                       al.level_name, u.username as requested_by_name,
                       CASE 
                           WHEN wa.entity_type = 'loan' THEN l.member_id
                           WHEN wa.entity_type = 'member_registration' THEN m.id
                           ELSE NULL
                       END as related_member_id
                FROM approval_assignments aa
                JOIN workflow_approvals wa ON aa.workflow_id = wa.id
                JOIN workflow_templates wt ON wa.template_id = wt.id
                JOIN approval_levels al ON wt.id = al.template_id AND aa.level_number = al.level_number
                LEFT JOIN users u ON wa.requested_by = u.id
                LEFT JOIN loans l ON wa.entity_type = 'loan' AND wa.entity_id = l.id
                LEFT JOIN members m ON wa.entity_type = 'member_registration' AND wa.entity_id = m.id
                WHERE aa.approver_id = ? AND aa.status = 'pending'
                ORDER BY aa.assigned_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get approval history for workflow
     */
    public function getApprovalHistory($workflowId) {
        $sql = "SELECT aa.*, u.username, u.first_name, u.last_name,
                       al.level_name, al.level_number
                FROM approval_actions aa
                JOIN users u ON aa.approver_id = u.id
                JOIN workflow_approvals wa ON aa.workflow_id = wa.id
                JOIN approval_levels al ON wa.template_id = al.template_id AND aa.level_number = al.level_number
                WHERE aa.workflow_id = ?
                ORDER BY aa.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$workflowId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get workflow statistics
     */
    public function getWorkflowStats($entityType = null, $dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($entityType) {
            $whereClause .= " AND entity_type = ?";
            $params[] = $entityType;
        }
        
        if ($dateFrom) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_workflows,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'timeout' THEN 1 ELSE 0 END) as timeout,
                    AVG(CASE WHEN completed_at IS NOT NULL THEN 
                        TIMESTAMPDIFF(HOUR, created_at, completed_at) 
                        ELSE NULL END) as avg_completion_hours
                FROM workflow_approvals {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get workflows for entity
     */
    public function getWorkflowsForEntity($entityType, $entityId) {
        $sql = "SELECT wa.*, wt.template_name, 
                       u.username as requested_by_name,
                       (SELECT COUNT(*) FROM approval_actions aa WHERE aa.workflow_id = wa.id) as action_count
                FROM workflow_approvals wa
                JOIN workflow_templates wt ON wa.template_id = wt.id
                LEFT JOIN users u ON wa.requested_by = u.id
                WHERE wa.entity_type = ? AND wa.entity_id = ?
                ORDER BY wa.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType, $entityId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>