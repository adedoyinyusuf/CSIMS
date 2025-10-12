<?php
session_start();
require_once '../config/config.php';
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';
require_once '../classes/WorkflowService.php';
require_once '../classes/LoanTypeService.php';
require_once '../classes/NotificationService.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

// Initialize services
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $config = SystemConfigService::getInstance($pdo);
    $businessRules = new BusinessRulesService($pdo);
    $memberController = new MemberController();
    $workflowService = new WorkflowService();
    $loanTypeService = new LoanTypeService();
    $notificationService = new NotificationService();
} catch (Exception $e) {
    die("System initialization failed: " . $e->getMessage());
}

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get member's savings and eligibility info
$savingsData = $businessRules->getMemberSavingsData($member_id);
$creditScore = $businessRules->getMemberCreditScore($member_id);
$hasOverdue = $businessRules->hasOverdueLoans($member_id);

// Pre-selected loan type from URL parameter
$preselectedType = isset($_GET['type']) ? (int)$_GET['type'] : null;

// Get available loan types
$availableLoanTypes = $loanTypeService->getAvailableLoanTypesForMember($member_id);

// Handle form submission
$success_message = '';
$error_message = '';
$application_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    try {
        // Validate and sanitize input
        $loan_type_id = (int)$_POST['loan_type_id'];
        $amount_requested = (float)$_POST['amount_requested'];
        $purpose = trim($_POST['purpose']);
        $repayment_period = (int)$_POST['repayment_period'];
        $guarantors = isset($_POST['guarantors']) ? $_POST['guarantors'] : [];
        
        // Get selected loan type details
        $selectedLoanType = null;
        foreach ($availableLoanTypes as $type) {
            if ($type['id'] == $loan_type_id) {
                $selectedLoanType = $type;
                break;
            }
        }
        
        if (!$selectedLoanType) {
            throw new Exception('Invalid loan type selected.');
        }
        
        // Validate amount
        if ($amount_requested < $selectedLoanType['min_amount']) {
            throw new Exception("Minimum loan amount for {$selectedLoanType['type_name']} is ₦" . number_format($selectedLoanType['min_amount'], 2));
        }
        
        if ($amount_requested > $selectedLoanType['member_max_amount']) {
            throw new Exception("Maximum loan amount for you is ₦" . number_format($selectedLoanType['member_max_amount'], 2));
        }
        
        // Validate repayment period
        if ($repayment_period < $selectedLoanType['min_term'] || $repayment_period > $selectedLoanType['max_term']) {
            throw new Exception("Repayment period must be between {$selectedLoanType['min_term']} and {$selectedLoanType['max_term']} months.");
        }
        
        // Validate guarantors if required
        if ($selectedLoanType['requires_guarantor'] && count($guarantors) < $selectedLoanType['guarantor_count']) {
            throw new Exception("This loan type requires {$selectedLoanType['guarantor_count']} guarantor(s).");
        }
        
        // Check business rules
        if ($hasOverdue) {
            throw new Exception('You have overdue payments. Please clear them before applying for a new loan.');
        }
        
        // Calculate loan preview
        $loanPreview = $loanTypeService->calculateLoanPreview($loan_type_id, $amount_requested, $repayment_period);
        
        // Create loan record
        $stmt = $pdo->prepare("
            INSERT INTO loans (
                member_id, loan_type_id, principal_amount, interest_rate, 
                repayment_period, purpose, status, monthly_payment,
                total_amount, application_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $member_id,
            $loan_type_id,
            $amount_requested,
            $selectedLoanType['interest_rate'],
            $repayment_period,
            $purpose,
            $loanPreview['monthly_payment'],
            $loanPreview['total_amount'],
            date('Y-m-d')
        ]);
        
        $application_id = $pdo->lastInsertId();
        
        // Add guarantors if provided
        if (!empty($guarantors)) {
            $stmt = $pdo->prepare("INSERT INTO loan_guarantors (loan_id, guarantor_member_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
            foreach ($guarantors as $guarantor_id) {
                if (!empty($guarantor_id)) {
                    $stmt->execute([$application_id, (int)$guarantor_id]);
                }
            }
        }
        
        // Determine workflow template based on amount and loan type
        $workflowTemplate = $workflowService->getApplicableWorkflowTemplate('loan', $amount_requested, $selectedLoanType);
        
        if ($workflowTemplate) {
            // Start workflow process
            $workflowResult = $workflowService->startWorkflow($workflowTemplate['id'], 'loan', $application_id, $_SESSION['user_id'] ?? null);
            
            if ($workflowResult['success']) {
                // Send notification to member
                $notificationService->sendLoanApplicationConfirmation($member_id, $application_id, $amount_requested);
                
                // Send notification to first approver
                $firstApprover = $workflowService->getCurrentApprover($workflowResult['workflow_id']);
                if ($firstApprover) {
                    $notificationService->sendApprovalRequest($firstApprover['user_id'], 'loan', $application_id, $amount_requested);
                }
                
                $success_message = "Your loan application has been submitted successfully! Application ID: " . sprintf('%06d', $application_id) . ". You will receive notifications as it progresses through the approval process.";
            } else {
                $error_message = "Application submitted but workflow initialization failed: " . $workflowResult['message'];
            }
        } else {
            // Auto-approve if no workflow required
            $stmt = $pdo->prepare("UPDATE loans SET status = 'approved', approved_date = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([1, $application_id]); // System auto-approval
            
            $notificationService->sendLoanApprovalNotification($member_id, $application_id, $amount_requested, 'approved');
            $success_message = "Your loan application has been auto-approved! Application ID: " . sprintf('%06d', $application_id) . ". Please visit our office for disbursement.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get member list for guarantor selection
$membersList = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, member_number 
        FROM members 
        WHERE id != ? AND status = 'active' 
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$member_id]);
    $membersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $membersList = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/csims-colors.css" rel="stylesheet">
    <style>
        body {
            background: var(--member-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .application-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 30px var(--shadow-md);
            background: white;
        }
        .loan-type-selector {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin: 10px 0;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        .loan-type-selector:hover {
            border-color: var(--member-primary);
            box-shadow: 0 4px 15px rgba(0, 123, 186, 0.1);
        }
        .loan-type-selector.selected {
            border-color: var(--member-primary);
            background: rgba(0, 123, 186, 0.05);
        }
        .loan-type-selector.selected::before {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 15px;
            background: var(--member-primary);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }
        .loan-preview {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .progress-indicator {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        .step {
            display: flex;
            align-items: center;
            flex: 1;
        }
        .step-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .step.active .step-number {
            background: var(--member-primary);
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step-line {
            height: 2px;
            background: #e9ecef;
            flex: 1;
            margin: 0 15px;
        }
        .step.active .step-line {
            background: var(--member-primary);
        }
        .step.completed .step-line {
            background: #28a745;
        }
        .eligibility-check {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .eligibility-check.warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        .eligibility-check.danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .guarantor-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .sidebar {
            background: linear-gradient(180deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            min-height: 100vh;
            border-radius: 0 16px 16px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3 text-white">
                    <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <i class="fas fa-university me-2"></i>
                        <span class="fs-4">CSIMS</span>
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="member_dashboard_integrated.php" class="nav-link text-white">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_loan_application_integrated.php" class="nav-link text-white active">
                                <i class="fas fa-plus me-2"></i> Apply for Loan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../member_workflow_tracking.php" class="nav-link text-white">
                                <i class="fas fa-chart-line me-2"></i> Track Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_loans_enhanced.php" class="nav-link text-white">
                                <i class="fas fa-money-bill-wave me-2"></i> My Loans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_savings_enhanced.php" class="nav-link text-white">
                                <i class="fas fa-piggy-bank me-2"></i> My Savings
                            </a>
                        </li>
                    </ul>
                    <hr>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i>
                            <strong><?= htmlspecialchars($member['first_name'] ?? 'Member') ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                            <li><a class="dropdown-item" href="member_profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="member_notifications.php">Notifications</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Header -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h2><i class="fas fa-plus-circle text-primary me-2"></i>Loan Application</h2>
                                    <p class="text-muted mb-0">Apply for a loan using our integrated workflow system</p>
                                </div>
                                <a href="member_dashboard_integrated.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>

                            <!-- Success/Error Messages -->
                            <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= $success_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $error_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <!-- Progress Indicator -->
                            <div class="application-card mb-4">
                                <div class="card-body">
                                    <div class="progress-indicator">
                                        <div class="step <?= !$application_id ? 'active' : 'completed' ?>">
                                            <div class="step-number"><?= !$application_id ? '1' : '✓' ?></div>
                                            <span>Application</span>
                                            <div class="step-line"></div>
                                        </div>
                                        <div class="step <?= $application_id && !$success_message ? 'active' : '' ?>">
                                            <div class="step-number">2</div>
                                            <span>Review</span>
                                            <div class="step-line"></div>
                                        </div>
                                        <div class="step">
                                            <div class="step-number">3</div>
                                            <span>Approval</span>
                                            <div class="step-line"></div>
                                        </div>
                                        <div class="step">
                                            <div class="step-number">4</div>
                                            <span>Disbursement</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$application_id): ?>
                            <!-- Application Form -->
                            <form method="POST" id="loanApplicationForm">
                                <div class="application-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-list me-2"></i>Select Loan Type
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($availableLoanTypes)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                                <h5 class="text-muted">No Loan Types Available</h5>
                                                <p class="text-muted">You don't meet the eligibility criteria for any loan types at the moment.</p>
                                                <a href="member_savings_enhanced.php" class="btn btn-primary">Build Your Savings</a>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($availableLoanTypes as $index => $loanType): ?>
                                                <div class="loan-type-selector <?= ($preselectedType && $preselectedType == $loanType['id']) || (!$preselectedType && $index == 0) ? 'selected' : '' ?>" 
                                                     data-type-id="<?= $loanType['id'] ?>" 
                                                     data-type-data="<?= htmlspecialchars(json_encode($loanType)) ?>">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-8">
                                                            <h6 class="mb-2 fw-bold"><?= htmlspecialchars($loanType['type_name']) ?></h6>
                                                            <p class="text-muted mb-2 small"><?= htmlspecialchars($loanType['description']) ?></p>
                                                            <div class="row small">
                                                                <div class="col-sm-6">
                                                                    <strong>Interest Rate:</strong> <?= $loanType['interest_rate'] ?>% APR
                                                                </div>
                                                                <div class="col-sm-6">
                                                                    <strong>Term:</strong> <?= $loanType['min_term'] ?>-<?= $loanType['max_term'] ?> months
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 text-end">
                                                            <div class="mb-2">
                                                                <small class="text-muted">Available Amount</small>
                                                                <div class="fw-bold text-primary">
                                                                    ₦<?= number_format($loanType['min_amount'], 0) ?> - ₦<?= number_format($loanType['member_max_amount'], 0) ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($loanType['requires_guarantor']): ?>
                                                                <small class="text-info">
                                                                    <i class="fas fa-users"></i> <?= $loanType['guarantor_count'] ?> Guarantor(s) required
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <input type="hidden" name="loan_type_id" id="loan_type_id" value="<?= $preselectedType ?: $availableLoanTypes[0]['id'] ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($availableLoanTypes)): ?>
                                <div class="application-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-calculator me-2"></i>Loan Details
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="amount_requested" class="form-label">Amount Requested <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₦</span>
                                                        <input type="number" class="form-control" id="amount_requested" name="amount_requested" 
                                                               min="<?= $availableLoanTypes[0]['min_amount'] ?>" 
                                                               max="<?= $availableLoanTypes[0]['member_max_amount'] ?>" 
                                                               step="1000" required>
                                                    </div>
                                                    <div class="form-text" id="amount_help">
                                                        Minimum: ₦<span id="min_amount"><?= number_format($availableLoanTypes[0]['min_amount'], 0) ?></span> - 
                                                        Maximum: ₦<span id="max_amount"><?= number_format($availableLoanTypes[0]['member_max_amount'], 0) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="repayment_period" class="form-label">Repayment Period (Months) <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="repayment_period" name="repayment_period" required>
                                                        <!-- Options will be populated by JavaScript -->
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="purpose" class="form-label">Loan Purpose <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                                                      placeholder="Please describe the purpose of this loan..." required maxlength="500"></textarea>
                                            <div class="form-text">Maximum 500 characters</div>
                                        </div>

                                        <!-- Loan Preview -->
                                        <div class="loan-preview" id="loanPreview" style="display: none;">
                                            <h6><i class="fas fa-eye me-2"></i>Loan Preview</h6>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <small class="text-muted">Monthly Payment</small>
                                                    <div class="fw-bold text-primary">₦<span id="preview_monthly">0</span></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">Total Interest</small>
                                                    <div class="fw-bold">₦<span id="preview_interest">0</span></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">Total Repayment</small>
                                                    <div class="fw-bold">₦<span id="preview_total">0</span></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">Interest Rate</small>
                                                    <div class="fw-bold"><span id="preview_rate">0</span>% APR</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Guarantors Section -->
                                <div class="application-card mb-4" id="guarantorsSection" style="display: none;">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-users me-2"></i>Guarantors <span id="guarantorRequirement"></span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="guarantorsList">
                                            <!-- Guarantor fields will be added dynamically -->
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="addGuarantor">
                                            <i class="fas fa-plus me-1"></i>Add Guarantor
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="member_dashboard_integrated.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                    <button type="submit" name="submit_application" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column - Member Info -->
                        <div class="col-lg-4">
                            <!-- Eligibility Status -->
                            <div class="application-card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shield-alt me-2"></i>Eligibility Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Overall eligibility -->
                                    <?php if (!$hasOverdue && !empty($availableLoanTypes)): ?>
                                        <div class="eligibility-check">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Eligible for Loans</strong>
                                            <div class="small mt-1">You meet all basic requirements for loan applications.</div>
                                        </div>
                                    <?php elseif ($hasOverdue): ?>
                                        <div class="eligibility-check danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Overdue Payments</strong>
                                            <div class="small mt-1">Please clear overdue payments before applying for new loans.</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="eligibility-check warning">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Build Your Profile</strong>
                                            <div class="small mt-1">Increase your savings to unlock loan opportunities.</div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Credit Score -->
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small">Credit Score</span>
                                            <strong class="text-primary"><?= $creditScore['score'] ?? 'N/A' ?></strong>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: <?= min(100, ($creditScore['score'] ?? 0) / 10) ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $creditScore['rating'] ?? 'Not Rated' ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Member Financial Summary -->
                            <div class="application-card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>Financial Summary
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Total Savings:</span>
                                        <strong class="text-success">₦<?= number_format($savingsData['total_savings'] ?? 0, 2) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Active Loans:</span>
                                        <strong><?= $savingsData['active_loans'] ?? 0 ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Loan Capacity:</span>
                                        <strong class="text-primary">₦<?= number_format(($savingsData['total_savings'] ?? 0) * $config->getLoanToSavingsMultiplier(), 0) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Tips -->
                            <div class="application-card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-lightbulb me-2"></i>Application Tips
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Ensure you have sufficient savings history
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Provide a clear and specific loan purpose
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Choose guarantors who are active members
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Review all details before submission
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guarantor Selection Modal -->
    <div class="modal fade" id="guarantorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Guarantor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="guarantorSearch" placeholder="Search members...">
                    <div class="list-group" id="guarantorList">
                        <?php foreach ($membersList as $memberOption): ?>
                            <a href="#" class="list-group-item list-group-item-action guarantor-option" 
                               data-member-id="<?= $memberOption['id'] ?>" 
                               data-member-name="<?= htmlspecialchars($memberOption['first_name'] . ' ' . $memberOption['last_name']) ?>"
                               data-member-number="<?= htmlspecialchars($memberOption['member_number']) ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($memberOption['first_name'] . ' ' . $memberOption['last_name']) ?></strong>
                                        <div class="small text-muted">Member #<?= htmlspecialchars($memberOption['member_number']) ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentLoanType = null;
        let selectedGuarantors = [];
        let guarantorModal = new bootstrap.Modal(document.getElementById('guarantorModal'));
        let currentGuarantorIndex = -1;

        // Loan types data
        const loanTypes = <?= json_encode($availableLoanTypes) ?>;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial loan type
            const initialTypeId = document.getElementById('loan_type_id').value;
            updateLoanType(initialTypeId);
            
            // Setup event listeners
            setupEventListeners();
        });

        function setupEventListeners() {
            // Loan type selection
            document.querySelectorAll('.loan-type-selector').forEach(selector => {
                selector.addEventListener('click', function() {
                    const typeId = this.dataset.typeId;
                    selectLoanType(typeId);
                });
            });

            // Amount and period changes
            document.getElementById('amount_requested').addEventListener('input', updateLoanPreview);
            document.getElementById('repayment_period').addEventListener('change', updateLoanPreview);

            // Guarantor search
            document.getElementById('guarantorSearch').addEventListener('input', filterGuarantors);
            
            // Guarantor selection
            document.querySelectorAll('.guarantor-option').forEach(option => {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectGuarantor(this);
                });
            });

            // Add guarantor button
            document.getElementById('addGuarantor').addEventListener('click', function() {
                if (selectedGuarantors.length < currentLoanType.guarantor_count) {
                    currentGuarantorIndex = selectedGuarantors.length;
                    guarantorModal.show();
                }
            });
        }

        function selectLoanType(typeId) {
            // Update UI
            document.querySelectorAll('.loan-type-selector').forEach(s => s.classList.remove('selected'));
            document.querySelector(`[data-type-id="${typeId}"]`).classList.add('selected');
            
            // Update hidden field
            document.getElementById('loan_type_id').value = typeId;
            
            // Update loan type data
            updateLoanType(typeId);
        }

        function updateLoanType(typeId) {
            currentLoanType = loanTypes.find(type => type.id == typeId);
            if (!currentLoanType) return;

            // Update amount limits
            const amountField = document.getElementById('amount_requested');
            const minSpan = document.getElementById('min_amount');
            const maxSpan = document.getElementById('max_amount');
            
            amountField.min = currentLoanType.min_amount;
            amountField.max = currentLoanType.member_max_amount;
            minSpan.textContent = numberFormat(currentLoanType.min_amount);
            maxSpan.textContent = numberFormat(currentLoanType.member_max_amount);

            // Update repayment period options
            updateRepaymentPeriodOptions();

            // Show/hide guarantor section
            updateGuarantorSection();

            // Update preview
            updateLoanPreview();
        }

        function updateRepaymentPeriodOptions() {
            const periodSelect = document.getElementById('repayment_period');
            periodSelect.innerHTML = '';

            for (let months = currentLoanType.min_term; months <= currentLoanType.max_term; months++) {
                const option = document.createElement('option');
                option.value = months;
                option.textContent = `${months} month${months > 1 ? 's' : ''}`;
                periodSelect.appendChild(option);
            }

            // Set default to middle value
            const defaultMonths = Math.ceil((currentLoanType.min_term + currentLoanType.max_term) / 2);
            periodSelect.value = defaultMonths;
        }

        function updateGuarantorSection() {
            const guarantorSection = document.getElementById('guarantorsSection');
            const requirementSpan = document.getElementById('guarantorRequirement');

            if (currentLoanType.requires_guarantor) {
                guarantorSection.style.display = 'block';
                requirementSpan.textContent = `(${currentLoanType.guarantor_count} required)`;
                selectedGuarantors = []; // Reset guarantors when changing loan type
                renderGuarantors();
            } else {
                guarantorSection.style.display = 'none';
                selectedGuarantors = [];
            }
        }

        function updateLoanPreview() {
            const amount = parseFloat(document.getElementById('amount_requested').value) || 0;
            const period = parseInt(document.getElementById('repayment_period').value) || 12;
            
            if (amount > 0 && currentLoanType) {
                // Calculate using simple interest (matching backend)
                const rate = currentLoanType.interest_rate / 100;
                const interest = amount * rate * (period / 12);
                const total = amount + interest;
                const monthly = total / period;

                document.getElementById('preview_monthly').textContent = numberFormat(monthly);
                document.getElementById('preview_interest').textContent = numberFormat(interest);
                document.getElementById('preview_total').textContent = numberFormat(total);
                document.getElementById('preview_rate').textContent = currentLoanType.interest_rate;
                document.getElementById('loanPreview').style.display = 'block';
            } else {
                document.getElementById('loanPreview').style.display = 'none';
            }
        }

        function renderGuarantors() {
            const container = document.getElementById('guarantorsList');
            container.innerHTML = '';

            selectedGuarantors.forEach((guarantor, index) => {
                const div = document.createElement('div');
                div.className = 'guarantor-item';
                div.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${guarantor.name}</strong>
                            <div class="small text-muted">Member #${guarantor.number}</div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGuarantor(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <input type="hidden" name="guarantors[]" value="${guarantor.id}">
                `;
                container.appendChild(div);
            });

            // Update add button visibility
            const addBtn = document.getElementById('addGuarantor');
            addBtn.style.display = selectedGuarantors.length < currentLoanType.guarantor_count ? 'inline-block' : 'none';
        }

        function removeGuarantor(index) {
            selectedGuarantors.splice(index, 1);
            renderGuarantors();
        }

        function selectGuarantor(element) {
            const id = element.dataset.memberId;
            const name = element.dataset.memberName;
            const number = element.dataset.memberNumber;

            // Check if already selected
            if (selectedGuarantors.some(g => g.id === id)) {
                alert('This member is already selected as a guarantor.');
                return;
            }

            selectedGuarantors.push({ id, name, number });
            renderGuarantors();
            guarantorModal.hide();
        }

        function filterGuarantors() {
            const searchTerm = document.getElementById('guarantorSearch').value.toLowerCase();
            const options = document.querySelectorAll('.guarantor-option');

            options.forEach(option => {
                const name = option.dataset.memberName.toLowerCase();
                const number = option.dataset.memberNumber.toLowerCase();
                
                if (name.includes(searchTerm) || number.includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        function numberFormat(number) {
            return new Intl.NumberFormat('en-NG').format(Math.round(number));
        }

        // Form validation
        document.getElementById('loanApplicationForm').addEventListener('submit', function(e) {
            // Validate guarantors if required
            if (currentLoanType.requires_guarantor && selectedGuarantors.length < currentLoanType.guarantor_count) {
                e.preventDefault();
                alert(`This loan type requires ${currentLoanType.guarantor_count} guarantor(s). You have selected ${selectedGuarantors.length}.`);
                return;
            }

            // Validate amount
            const amount = parseFloat(document.getElementById('amount_requested').value);
            if (amount < currentLoanType.min_amount || amount > currentLoanType.member_max_amount) {
                e.preventDefault();
                alert(`Loan amount must be between ₦${numberFormat(currentLoanType.min_amount)} and ₦${numberFormat(currentLoanType.member_max_amount)}.`);
                return;
            }
        });
    </script>
</body>
</html>