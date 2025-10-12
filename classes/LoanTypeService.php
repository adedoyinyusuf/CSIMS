<?php
require_once 'DatabaseConnection.php';
require_once 'LogService.php';

class LoanTypeService {
    private $db;
    private $logService;
    
    public function __construct() {
        $this->db = DatabaseConnection::getInstance()->getConnection();
        $this->logService = new LogService();
    }
    
    /**
     * Get all loan types with their configurations
     */
    public function getAllLoanTypes($includeInactive = false) {
        try {
            $whereClause = $includeInactive ? "" : "WHERE is_active = 1";
            
            $sql = "SELECT lt.*, 
                           COUNT(l.id) as total_loans,
                           SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_loans,
                           SUM(CASE WHEN l.status = 'active' THEN l.principal_amount ELSE 0 END) as outstanding_amount
                    FROM loan_types lt
                    LEFT JOIN loans l ON lt.id = l.loan_type_id
                    {$whereClause}
                    GROUP BY lt.id
                    ORDER BY lt.display_order ASC, lt.type_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $loanTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add additional calculated fields
            foreach ($loanTypes as &$loanType) {
                $loanType['utilization_rate'] = $this->calculateUtilizationRate($loanType['id']);
                $loanType['avg_loan_amount'] = $this->getAverageLoanAmount($loanType['id']);
                $loanType['approval_rate'] = $this->getApprovalRate($loanType['id']);
            }
            
            return $loanTypes;
            
        } catch (Exception $e) {
            $this->logService->log("loan_type_fetch_error", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get loan type by ID
     */
    public function getLoanTypeById($loanTypeId) {
        try {
            $sql = "SELECT * FROM loan_types WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanTypeId]);
            
            $loanType = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($loanType) {
                $loanType['statistics'] = $this->getLoanTypeStatistics($loanTypeId);
                $loanType['recent_loans'] = $this->getRecentLoansByType($loanTypeId, 10);
            }
            
            return $loanType;
            
        } catch (Exception $e) {
            $this->logService->log("loan_type_get_error", [
                'loan_type_id' => $loanTypeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create new loan type
     */
    public function createLoanType($data) {
        try {
            $this->db->beginTransaction();
            
            // Validate required fields
            $this->validateLoanTypeData($data);
            
            // Set default values
            $data = array_merge([
                'is_active' => 1,
                'display_order' => 999,
                'auto_disburse' => 0,
                'disburse_delay_hours' => 24,
                'requires_guarantor' => 0,
                'guarantor_count' => 0,
                'collateral_required' => 0,
                'grace_period_days' => 30,
                'penalty_rate' => 5.0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], $data);
            
            $sql = "INSERT INTO loan_types (
                        type_name, description, interest_rate, max_amount, min_amount,
                        max_term_months, min_term_months, processing_fee_rate,
                        is_active, display_order, auto_disburse, disburse_delay_hours,
                        requires_guarantor, guarantor_count, collateral_required,
                        grace_period_days, penalty_rate, eligibility_criteria,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['type_name'],
                $data['description'],
                $data['interest_rate'],
                $data['max_amount'],
                $data['min_amount'],
                $data['max_term_months'],
                $data['min_term_months'],
                $data['processing_fee_rate'],
                $data['is_active'],
                $data['display_order'],
                $data['auto_disburse'],
                $data['disburse_delay_hours'],
                $data['requires_guarantor'],
                $data['guarantor_count'],
                $data['collateral_required'],
                $data['grace_period_days'],
                $data['penalty_rate'],
                $data['eligibility_criteria'] ?? null,
                $data['created_at'],
                $data['updated_at']
            ]);
            
            $loanTypeId = $this->db->lastInsertId();
            
            // Create default workflow template for this loan type if needed
            $this->createWorkflowTemplateForLoanType($loanTypeId, $data);
            
            $this->db->commit();
            
            $this->logService->log("loan_type_created", [
                'loan_type_id' => $loanTypeId,
                'type_name' => $data['type_name']
            ]);
            
            return $loanTypeId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logService->log("loan_type_create_error", [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update existing loan type
     */
    public function updateLoanType($loanTypeId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Validate data
            $this->validateLoanTypeData($data, $loanTypeId);
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Build update query dynamically
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'type_name', 'description', 'interest_rate', 'max_amount', 'min_amount',
                'max_term_months', 'min_term_months', 'processing_fee_rate',
                'is_active', 'display_order', 'auto_disburse', 'disburse_delay_hours',
                'requires_guarantor', 'guarantor_count', 'collateral_required',
                'grace_period_days', 'penalty_rate', 'eligibility_criteria', 'updated_at'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No valid fields to update');
            }
            
            $params[] = $loanTypeId;
            
            $sql = "UPDATE loan_types SET " . implode(', ', $updateFields) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $this->db->commit();
            
            $this->logService->log("loan_type_updated", [
                'loan_type_id' => $loanTypeId,
                'updated_fields' => array_keys($data)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logService->log("loan_type_update_error", [
                'loan_type_id' => $loanTypeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete/deactivate loan type
     */
    public function deleteLoanType($loanTypeId, $hardDelete = false) {
        try {
            $this->db->beginTransaction();
            
            // Check if loan type has active loans
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM loans WHERE loan_type_id = ? AND status IN ('active', 'disbursed', 'pending_approval')");
            $stmt->execute([$loanTypeId]);
            $activeLoans = $stmt->fetchColumn();
            
            if ($activeLoans > 0 && $hardDelete) {
                throw new Exception("Cannot delete loan type with active loans. Deactivate instead.");
            }
            
            if ($hardDelete && $activeLoans == 0) {
                // Hard delete - remove completely
                $sql = "DELETE FROM loan_types WHERE id = ?";
                $action = "deleted";
            } else {
                // Soft delete - just deactivate
                $sql = "UPDATE loan_types SET is_active = 0, updated_at = NOW() WHERE id = ?";
                $action = "deactivated";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanTypeId]);
            
            $this->db->commit();
            
            $this->logService->log("loan_type_{$action}", [
                'loan_type_id' => $loanTypeId,
                'active_loans_count' => $activeLoans
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logService->log("loan_type_delete_error", [
                'loan_type_id' => $loanTypeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get loan types available for a specific member
     */
    public function getAvailableLoanTypesForMember($memberId, $requestedAmount = null) {
        try {
            // Get member's savings and loan history
            $memberData = $this->getMemberLoanEligibilityData($memberId);
            
            $sql = "SELECT lt.* FROM loan_types lt WHERE lt.is_active = 1";
            $params = [];
            
            // Filter by amount if provided
            if ($requestedAmount) {
                $sql .= " AND (lt.min_amount IS NULL OR lt.min_amount <= ?) 
                         AND (lt.max_amount IS NULL OR lt.max_amount >= ?)";
                $params[] = $requestedAmount;
                $params[] = $requestedAmount;
            }
            
            $sql .= " ORDER BY lt.display_order ASC, lt.interest_rate ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $loanTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter based on eligibility criteria
            $availableLoanTypes = [];
            foreach ($loanTypes as $loanType) {
                if ($this->checkMemberEligibility($memberId, $loanType, $memberData, $requestedAmount)) {
                    $loanType['member_max_amount'] = $this->calculateMemberMaxAmount($memberId, $loanType, $memberData);
                    $availableLoanTypes[] = $loanType;
                }
            }
            
            return $availableLoanTypes;
            
        } catch (Exception $e) {
            $this->logService->log("available_loan_types_error", [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate loan preview for specific loan type and amount
     */
    public function calculateLoanPreview($loanTypeId, $amount, $termMonths) {
        try {
            $loanType = $this->getLoanTypeById($loanTypeId);
            if (!$loanType) {
                throw new Exception('Loan type not found');
            }
            
            // Validate amount and term
            if ($loanType['min_amount'] && $amount < $loanType['min_amount']) {
                throw new Exception("Amount below minimum of " . number_format($loanType['min_amount'], 2));
            }
            
            if ($loanType['max_amount'] && $amount > $loanType['max_amount']) {
                throw new Exception("Amount exceeds maximum of " . number_format($loanType['max_amount'], 2));
            }
            
            if ($loanType['min_term_months'] && $termMonths < $loanType['min_term_months']) {
                throw new Exception("Term below minimum of {$loanType['min_term_months']} months");
            }
            
            if ($loanType['max_term_months'] && $termMonths > $loanType['max_term_months']) {
                throw new Exception("Term exceeds maximum of {$loanType['max_term_months']} months");
            }
            
            // Calculate loan details
            $interestRate = (float)$loanType['interest_rate'];
            $processingFeeRate = (float)$loanType['processing_fee_rate'];
            
            $monthlyInterestRate = $interestRate / 100 / 12;
            $processingFee = $amount * ($processingFeeRate / 100);
            
            if ($monthlyInterestRate > 0) {
                $monthlyPayment = $amount * (
                    $monthlyInterestRate * pow(1 + $monthlyInterestRate, $termMonths)
                ) / (pow(1 + $monthlyInterestRate, $termMonths) - 1);
            } else {
                $monthlyPayment = $amount / $termMonths;
            }
            
            $totalPayment = $monthlyPayment * $termMonths;
            $totalInterest = $totalPayment - $amount;
            $totalAmount = $totalPayment + $processingFee;
            
            return [
                'loan_type_id' => $loanTypeId,
                'loan_type_name' => $loanType['type_name'],
                'principal_amount' => $amount,
                'term_months' => $termMonths,
                'interest_rate' => $interestRate,
                'monthly_interest_rate' => $monthlyInterestRate,
                'processing_fee_rate' => $processingFeeRate,
                'monthly_payment' => round($monthlyPayment, 2),
                'processing_fee' => round($processingFee, 2),
                'total_interest' => round($totalInterest, 2),
                'total_payment' => round($totalPayment, 2),
                'total_amount' => round($totalAmount, 2),
                'requires_guarantor' => (bool)$loanType['requires_guarantor'],
                'guarantor_count' => (int)$loanType['guarantor_count'],
                'collateral_required' => (bool)$loanType['collateral_required'],
                'grace_period_days' => (int)$loanType['grace_period_days']
            ];
            
        } catch (Exception $e) {
            $this->logService->log("loan_preview_error", [
                'loan_type_id' => $loanTypeId,
                'amount' => $amount,
                'term' => $termMonths,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get loan type statistics
     */
    public function getLoanTypeStatistics($loanTypeId) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_applications,
                        COUNT(CASE WHEN status IN ('approved', 'active', 'disbursed') THEN 1 END) as approved_applications,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_applications,
                        COUNT(CASE WHEN status = 'pending_approval' THEN 1 END) as pending_applications,
                        AVG(principal_amount) as avg_amount,
                        SUM(CASE WHEN status = 'active' THEN principal_amount ELSE 0 END) as outstanding_principal,
                        AVG(term_months) as avg_term,
                        MIN(application_date) as first_application,
                        MAX(application_date) as latest_application
                    FROM loans 
                    WHERE loan_type_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanTypeId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logService->log("loan_type_stats_error", [
                'loan_type_id' => $loanTypeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Validate loan type data
     */
    private function validateLoanTypeData($data, $loanTypeId = null) {
        $errors = [];
        
        // Required fields for new loan types
        if (!$loanTypeId) {
            if (empty($data['type_name'])) {
                $errors[] = 'Loan type name is required';
            }
            if (!isset($data['interest_rate']) || $data['interest_rate'] <= 0) {
                $errors[] = 'Valid interest rate is required';
            }
        }
        
        // Validate numeric fields
        $numericFields = ['interest_rate', 'max_amount', 'min_amount', 'processing_fee_rate', 'penalty_rate'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
            }
        }
        
        // Validate ranges
        if (isset($data['min_amount']) && isset($data['max_amount']) && 
            $data['min_amount'] > $data['max_amount']) {
            $errors[] = 'Minimum amount cannot be greater than maximum amount';
        }
        
        if (isset($data['min_term_months']) && isset($data['max_term_months']) && 
            $data['min_term_months'] > $data['max_term_months']) {
            $errors[] = 'Minimum term cannot be greater than maximum term';
        }
        
        // Check for duplicate name (excluding current record if updating)
        if (!empty($data['type_name'])) {
            $sql = "SELECT id FROM loan_types WHERE type_name = ?";
            $params = [$data['type_name']];
            
            if ($loanTypeId) {
                $sql .= " AND id != ?";
                $params[] = $loanTypeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->fetch()) {
                $errors[] = 'Loan type name already exists';
            }
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(', ', $errors));
        }
    }
    
    /**
     * Create workflow template for loan type
     */
    private function createWorkflowTemplateForLoanType($loanTypeId, $loanTypeData) {
        try {
            // Create workflow template based on loan type max amount
            $maxAmount = $loanTypeData['max_amount'] ?? 1000000;
            
            if ($maxAmount <= 100000) {
                $templateName = 'Small ' . $loanTypeData['type_name'] . ' Approval';
                $levels = 1;
            } elseif ($maxAmount <= 500000) {
                $templateName = 'Medium ' . $loanTypeData['type_name'] . ' Approval';
                $levels = 2;
            } else {
                $templateName = 'Large ' . $loanTypeData['type_name'] . ' Approval';
                $levels = 3;
            }
            
            // Check if template already exists
            $stmt = $this->db->prepare("SELECT id FROM workflow_templates WHERE template_name = ?");
            $stmt->execute([$templateName]);
            
            if (!$stmt->fetch()) {
                // Create new template
                $stmt = $this->db->prepare("
                    INSERT INTO workflow_templates (template_name, entity_type, min_amount, max_amount, is_active)
                    VALUES (?, 'loan', ?, ?, 1)
                ");
                $stmt->execute([
                    $templateName,
                    $loanTypeData['min_amount'] ?? 0,
                    $maxAmount
                ]);
                
                $templateId = $this->db->lastInsertId();
                
                // Create approval levels for this template
                $this->createApprovalLevelsForTemplate($templateId, $levels);
            }
            
        } catch (Exception $e) {
            // Log but don't throw - this is not critical
            $this->logService->log("workflow_template_creation_error", [
                'loan_type_id' => $loanTypeId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create approval levels for workflow template
     */
    private function createApprovalLevelsForTemplate($templateId, $levelCount) {
        $levels = [
            1 => ['Branch Manager', '2', 24],
            2 => ['Area Manager', '3', 48],
            3 => ['Regional Manager', '4', 72]
        ];
        
        for ($i = 1; $i <= $levelCount; $i++) {
            if (isset($levels[$i])) {
                $stmt = $this->db->prepare("
                    INSERT INTO approval_levels (template_id, level_number, level_name, required_roles, timeout_hours, priority)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $templateId,
                    $i,
                    $levels[$i][0],
                    $levels[$i][1],
                    $levels[$i][2],
                    $i
                ]);
            }
        }
    }
    
    /**
     * Helper methods for eligibility and calculations
     */
    private function getMemberLoanEligibilityData($memberId) {
        $stmt = $this->db->prepare("
            SELECT m.*, 
                   COALESCE(SUM(c.amount), 0) as total_savings,
                   COUNT(l.id) as total_loans,
                   COUNT(CASE WHEN l.status = 'active' THEN 1 END) as active_loans,
                   SUM(CASE WHEN l.status = 'active' THEN l.principal_amount - l.amount_paid ELSE 0 END) as outstanding_balance,
                   DATEDIFF(NOW(), m.created_at) as membership_days
            FROM members m
            LEFT JOIN contributions c ON m.id = c.member_id AND c.status = 'completed'
            LEFT JOIN loans l ON m.id = l.member_id
            WHERE m.id = ?
            GROUP BY m.id
        ");
        $stmt->execute([$memberId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function checkMemberEligibility($memberId, $loanType, $memberData, $requestedAmount) {
        // Parse eligibility criteria if exists
        if (!empty($loanType['eligibility_criteria'])) {
            $criteria = json_decode($loanType['eligibility_criteria'], true);
            
            if (isset($criteria['min_membership_months'])) {
                $membershipMonths = $memberData['membership_days'] / 30;
                if ($membershipMonths < $criteria['min_membership_months']) {
                    return false;
                }
            }
            
            if (isset($criteria['min_savings_amount'])) {
                if ($memberData['total_savings'] < $criteria['min_savings_amount']) {
                    return false;
                }
            }
            
            if (isset($criteria['max_active_loans'])) {
                if ($memberData['active_loans'] >= $criteria['max_active_loans']) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function calculateMemberMaxAmount($memberId, $loanType, $memberData) {
        $maxAmount = $loanType['max_amount'] ?: PHP_FLOAT_MAX;
        
        // Apply savings multiplier if configured
        if (!empty($loanType['eligibility_criteria'])) {
            $criteria = json_decode($loanType['eligibility_criteria'], true);
            
            if (isset($criteria['savings_multiplier'])) {
                $savingsBasedMax = $memberData['total_savings'] * $criteria['savings_multiplier'];
                $maxAmount = min($maxAmount, $savingsBasedMax);
            }
        }
        
        return $maxAmount;
    }
    
    private function calculateUtilizationRate($loanTypeId) {
        $stmt = $this->db->prepare("
            SELECT 
                lt.max_amount,
                SUM(CASE WHEN l.status = 'active' THEN l.principal_amount ELSE 0 END) as outstanding
            FROM loan_types lt
            LEFT JOIN loans l ON lt.id = l.loan_type_id
            WHERE lt.id = ?
            GROUP BY lt.id
        ");
        $stmt->execute([$loanTypeId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data || !$data['max_amount']) return 0;
        
        return min(100, ($data['outstanding'] / $data['max_amount']) * 100);
    }
    
    private function getAverageLoanAmount($loanTypeId) {
        $stmt = $this->db->prepare("
            SELECT AVG(principal_amount) as avg_amount
            FROM loans 
            WHERE loan_type_id = ? AND status IN ('approved', 'active', 'disbursed')
        ");
        $stmt->execute([$loanTypeId]);
        
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function getApprovalRate($loanTypeId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN status IN ('approved', 'active', 'disbursed') THEN 1 END) as approved
            FROM loans 
            WHERE loan_type_id = ?
        ");
        $stmt->execute([$loanTypeId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data || $data['total_applications'] == 0) return 100;
        
        return ($data['approved'] / $data['total_applications']) * 100;
    }
    
    private function getRecentLoansByType($loanTypeId, $limit) {
        $stmt = $this->db->prepare("
            SELECT l.*, m.first_name, m.last_name, m.member_number
            FROM loans l
            JOIN members m ON l.member_id = m.id
            WHERE l.loan_type_id = ?
            ORDER BY l.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$loanTypeId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>