<?php
session_start();
require_once '../config/config.php';
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';
require_once '../classes/WorkflowService.php';
require_once '../classes/LoanTypeService.php';

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
} catch (Exception $e) {
    die("System initialization failed: " . $e->getMessage());
}

$member_id = $_SESSION['member_id'];

// Get member details and statistics
$member = $memberController->getMemberById($member_id);

// Get savings information using business rules
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN contribution_type = 'mandatory' THEN amount ELSE 0 END), 0) as mandatory_savings,
            COALESCE(SUM(CASE WHEN contribution_type = 'voluntary' THEN amount ELSE 0 END), 0) as voluntary_savings,
            COALESCE(SUM(amount), 0) as total_savings,
            COUNT(DISTINCT DATE_FORMAT(created_at, '%Y-%m')) as contribution_months
        FROM contributions 
        WHERE member_id = ? AND status = 'completed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ");
    $stmt->execute([$member_id]);
    $savingsData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $savingsData = ['mandatory_savings' => 0, 'voluntary_savings' => 0, 'total_savings' => 0, 'contribution_months' => 0];
}

// Get loan information
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_loans,
            SUM(CASE WHEN status IN ('active', 'disbursed') THEN 1 ELSE 0 END) as active_loans,
            SUM(CASE WHEN status IN ('active', 'disbursed') THEN principal_amount ELSE 0 END) as active_loan_amount,
            SUM(CASE WHEN status IN ('active', 'disbursed') THEN amount_paid ELSE 0 END) as total_paid
        FROM loans 
        WHERE member_id = ?
    ");
    $stmt->execute([$member_id]);
    $loanData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $loanData = ['total_loans' => 0, 'active_loans' => 0, 'active_loan_amount' => 0, 'total_paid' => 0];
}

// Get pending workflows for this member
$pendingWorkflows = [];
try {
    $stmt = $pdo->prepare("
        SELECT wa.*, wt.template_name, l.principal_amount as loan_amount, l.purpose,
               DATEDIFF(NOW(), wa.created_at) as days_pending
        FROM workflow_approvals wa
        JOIN workflow_templates wt ON wa.template_id = wt.id
        LEFT JOIN loans l ON wa.entity_type = 'loan' AND wa.entity_id = l.id
        WHERE wa.status = 'pending'
        AND (
            (wa.entity_type = 'loan' AND l.member_id = ?) OR
            (wa.entity_type = 'member_registration' AND wa.entity_id = ?) OR
            (wa.requested_by = ?)
        )
        ORDER BY wa.created_at DESC
    ");
    $stmt->execute([$member_id, $member_id, $_SESSION['user_id'] ?? null]);
    $pendingWorkflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingWorkflows = [];
}

// Get available loan types for this member
$availableLoanTypes = $loanTypeService->getAvailableLoanTypesForMember($member_id);

// Get credit score
$creditScore = $businessRules->getMemberCreditScore($member_id);

// Check for overdue loans
$hasOverdue = $businessRules->hasOverdueLoans($member_id);

// Calculate loan limits
$maxLoanBasedOnSavings = $savingsData['total_savings'] * $config->getLoanToSavingsMultiplier();
$systemMaxLoan = $config->getMaxLoanAmount();
$effectiveLoanLimit = min($maxLoanBasedOnSavings, $systemMaxLoan);

// Get business rule configuration for display
$businessConfig = [
    'min_mandatory_savings' => $config->getMinMandatorySavings(),
    'loan_to_savings_multiplier' => $config->getLoanToSavingsMultiplier(),
    'max_active_loans' => $config->getMaxActiveLoansPer(),
    'min_membership_months' => $config->getMinMembershipMonths(),
    'auto_approval_limit' => $config->getAutoApprovalLimit(),
];

// Check membership duration
$membershipMonths = 0;
if ($member && $member['date_joined']) {
    $joinDate = new DateTime($member['date_joined']);
    $now = new DateTime();
    $diff = $joinDate->diff($now);
    $membershipMonths = ($diff->y * 12) + $diff->m;
}

// Get recent activity
try {
    $stmt = $pdo->prepare("
        (SELECT 'contribution' as type, amount, created_at, 'Savings contribution' as description
         FROM contributions WHERE member_id = ? AND status = 'completed'
         ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'loan' as type, amount_paid as amount, updated_at as created_at, 'Loan payment' as description
         FROM loans WHERE member_id = ? AND amount_paid > 0
         ORDER BY updated_at DESC LIMIT 3)
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$member_id, $member_id]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentActivity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/csims-colors.css" rel="stylesheet">
    <style>
        body {
            background: var(--member-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 30px var(--shadow-md);
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px var(--shadow-lg);
        }
        .stat-card-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .stat-card-info {
            background: linear-gradient(135deg, var(--sky-blue) 0%, var(--vista-blue) 100%);
            color: white;
        }
        .stat-card-warning {
            background: linear-gradient(135deg, var(--orange-peel) 0%, var(--princeton-orange) 100%);
            color: white;
        }
        .stat-card-primary {
            background: linear-gradient(135deg, var(--member-primary) 0%, var(--member-secondary) 100%);
            color: white;
        }
        .welcome-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .quick-action-btn {
            border-radius: 12px;
            padding: 15px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .sidebar {
            background: linear-gradient(180deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            min-height: 100vh;
            border-radius: 0 16px 16px 0;
        }
        .progress-custom {
            height: 8px;
            border-radius: 4px;
            background: rgba(255,255,255,0.2);
        }
        .progress-bar-custom {
            background: rgba(255,255,255,0.9);
            border-radius: 4px;
        }
        .workflow-progress {
            position: relative;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .workflow-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .workflow-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .step-circle.completed {
            background-color: #28a745;
            color: white;
        }
        .step-circle.current {
            background-color: #007cba;
            color: white;
        }
        .step-circle.pending {
            background-color: #e9ecef;
            color: #6c757d;
            border: 2px solid #dee2e6;
        }
        .step-line {
            height: 2px;
            background-color: #dee2e6;
            margin: 0 10px;
            flex: 1;
        }
        .step-line.completed {
            background-color: #28a745;
        }
        .loan-type-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .loan-type-card:hover {
            border-color: var(--member-primary);
            box-shadow: 0 4px 15px rgba(0, 123, 186, 0.1);
        }
        .loan-type-card.selected {
            border-color: var(--member-primary);
            background-color: rgba(0, 123, 186, 0.05);
        }
        .eligibility-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .eligible {
            background: #d4edda;
            color: #155724;
        }
        .not-eligible {
            background: #f8d7da;
            color: #721c24;
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
                            <a href="member_dashboard_integrated.php" class="nav-link text-white active">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_loan_application_integrated.php" class="nav-link text-white">
                                <i class="fas fa-plus me-2"></i> Apply for Loan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../member_workflow_tracking.php" class="nav-link text-white">
                                <i class="fas fa-chart-line me-2"></i> Track Applications
                                <?php if (!empty($pendingWorkflows)): ?>
                                    <span class="badge bg-warning ms-2"><?= count($pendingWorkflows) ?></span>
                                <?php endif; ?>
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
                    <!-- Welcome Header -->
                    <div class="welcome-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="mb-2">
                                    <i class="fas fa-sun me-2"></i>
                                    Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, 
                                    <?= htmlspecialchars($member['first_name'] ?? 'Member') ?>!
                                </h1>
                                <p class="lead mb-0">Welcome to your integrated CSIMS dashboard</p>
                                <small class="opacity-75">Member since <?= date('F Y', strtotime($member['date_joined'] ?? 'now')) ?></small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <small>Credit Score</small>
                                    <h3 class="mb-0"><?= $creditScore['score'] ?? 'N/A' ?></h3>
                                    <small class="opacity-75"><?= $creditScore['rating'] ?? 'Not Rated' ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Workflows Alert -->
                    <?php if (!empty($pendingWorkflows)): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Application Status Update:</strong> You have <?= count($pendingWorkflows) ?> pending application(s) in the approval process.
                        <a href="../member_workflow_tracking.php" class="alert-link">View Status</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="dashboard-card stat-card-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-piggy-bank fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title">₦<?= number_format($savingsData['total_savings'], 2) ?></h4>
                                    <p class="card-text small mb-0">Total Savings</p>
                                    <div class="progress progress-custom mt-2">
                                        <div class="progress-bar progress-bar-custom" style="width: <?= min(100, ($savingsData['total_savings'] / $businessConfig['min_mandatory_savings']) * 100) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="dashboard-card stat-card-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-money-bill-wave fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title"><?= $loanData['active_loans'] ?></h4>
                                    <p class="card-text small mb-0">Active Loans</p>
                                    <small class="opacity-75">₦<?= number_format($loanData['active_loan_amount'], 0) ?> total</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="dashboard-card stat-card-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-hand-holding-usd fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title">₦<?= number_format($effectiveLoanLimit, 0) ?></h4>
                                    <p class="card-text small mb-0">Loan Limit</p>
                                    <small class="opacity-75">Based on savings</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="dashboard-card stat-card-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title"><?= count($pendingWorkflows) ?></h4>
                                    <p class="card-text small mb-0">Pending Applications</p>
                                    <small class="opacity-75">In approval process</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Pending Workflows -->
                            <?php if (!empty($pendingWorkflows)): ?>
                            <div class="dashboard-card mb-4">
                                <div class="card-header bg-transparent">
                                    <h5 class="mb-0">
                                        <i class="fas fa-hourglass-half text-warning me-2"></i>
                                        Pending Applications
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($pendingWorkflows as $workflow): ?>
                                        <div class="workflow-progress">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($workflow['template_name']) ?></h6>
                                                    <div class="small text-muted">
                                                        <?php if ($workflow['loan_amount']): ?>
                                                            Amount: ₦<?= number_format($workflow['loan_amount'], 2) ?> • 
                                                        <?php endif; ?>
                                                        Submitted <?= $workflow['days_pending'] ?> day(s) ago
                                                    </div>
                                                </div>
                                                <span class="badge bg-warning">Level <?= $workflow['current_level'] ?>/<?= $workflow['total_levels'] ?></span>
                                            </div>
                                            
                                            <div class="workflow-steps">
                                                <?php for ($i = 1; $i <= $workflow['total_levels']; $i++): ?>
                                                    <div class="workflow-step">
                                                        <div class="step-circle <?= $i < $workflow['current_level'] ? 'completed' : ($i == $workflow['current_level'] ? 'current' : 'pending') ?>">
                                                            <?php if ($i < $workflow['current_level']): ?>
                                                                <i class="fas fa-check"></i>
                                                            <?php else: ?>
                                                                <?= $i ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">Level <?= $i ?></small>
                                                    </div>
                                                    <?php if ($i < $workflow['total_levels']): ?>
                                                        <div class="step-line <?= $i < $workflow['current_level'] ? 'completed' : '' ?>"></div>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <div class="text-center mt-2">
                                                <a href="../member_workflow_tracking.php" class="btn btn-sm btn-outline-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Quick Actions -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header bg-transparent">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt text-primary me-2"></i>
                                        Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <button class="quick-action-btn btn btn-primary w-100" onclick="location.href='member_loan_application_integrated.php'">
                                                <i class="fas fa-plus me-2"></i>
                                                Apply for Loan
                                            </button>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <button class="quick-action-btn btn btn-success w-100" onclick="location.href='member_savings_enhanced.php'">
                                                <i class="fas fa-piggy-bank me-2"></i>
                                                Make Deposit
                                            </button>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <button class="quick-action-btn btn btn-info w-100" onclick="location.href='../member_workflow_tracking.php'">
                                                <i class="fas fa-chart-line me-2"></i>
                                                Track Applications
                                            </button>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <button class="quick-action-btn btn btn-warning w-100" onclick="location.href='member_loans_enhanced.php'">
                                                <i class="fas fa-money-bill-wave me-2"></i>
                                                View Loans
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Available Loan Types -->
                            <div class="dashboard-card">
                                <div class="card-header bg-transparent">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list text-info me-2"></i>
                                        Available Loan Types
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($availableLoanTypes)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                                            <p>No loan types available for your current profile.</p>
                                            <small>Build your savings history to unlock more loan options.</small>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach (array_slice($availableLoanTypes, 0, 3) as $loanType): ?>
                                            <div class="loan-type-card" onclick="location.href='member_loan_application_integrated.php?type=<?= $loanType['id'] ?>'">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($loanType['type_name']) ?></h6>
                                                        <p class="text-muted small mb-2"><?= htmlspecialchars($loanType['description']) ?></p>
                                                        <div class="row">
                                                            <div class="col-sm-6">
                                                                <small class="text-muted">Interest Rate:</small>
                                                                <strong class="text-primary"><?= $loanType['interest_rate'] ?>% APR</strong>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <small class="text-muted">Max Amount:</small>
                                                                <strong>₦<?= number_format(min($loanType['max_amount'], $loanType['member_max_amount']), 0) ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="ms-3">
                                                        <span class="eligibility-badge eligible">Eligible</span>
                                                        <?php if ($loanType['requires_guarantor']): ?>
                                                            <div class="mt-1">
                                                                <small class="text-info">
                                                                    <i class="fas fa-users"></i> <?= $loanType['guarantor_count'] ?> Guarantor(s)
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($availableLoanTypes) > 3): ?>
                                            <div class="text-center mt-3">
                                                <a href="member_loan_application_integrated.php" class="btn btn-outline-primary">
                                                    View All <?= count($availableLoanTypes) ?> Loan Types
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Membership Status -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header bg-transparent">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user-check text-success me-2"></i>
                                        Membership Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Status:</span>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Duration:</span>
                                        <strong><?= $membershipMonths ?> months</strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Credit Rating:</span>
                                        <strong class="text-primary"><?= $creditScore['rating'] ?? 'Not Rated' ?></strong>
                                    </div>
                                    
                                    <?php if ($hasOverdue): ?>
                                    <div class="alert alert-warning mt-3 py-2">
                                        <small>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            You have overdue payments. Please settle them to maintain your good standing.
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Business Rules Summary -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header bg-transparent">
                                    <h6 class="mb-0">
                                        <i class="fas fa-gavel text-warning me-2"></i>
                                        Loan Requirements
                                    </h6>
                                </div>
                                <div class="card-body small">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Min. Savings:</span>
                                        <strong>₦<?= number_format($businessConfig['min_mandatory_savings'], 0) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Loan Multiplier:</span>
                                        <strong><?= $businessConfig['loan_to_savings_multiplier'] ?>x savings</strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Max Active Loans:</span>
                                        <strong><?= $businessConfig['max_active_loans'] ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Auto-Approval:</span>
                                        <strong>≤₦<?= number_format($businessConfig['auto_approval_limit'], 0) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div class="dashboard-card">
                                <div class="card-header bg-transparent">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clock text-info me-2"></i>
                                        Recent Activity
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentActivity)): ?>
                                        <p class="text-muted small text-center py-3">No recent activity</p>
                                    <?php else: ?>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    <i class="fas fa-<?= $activity['type'] === 'contribution' ? 'piggy-bank text-success' : 'money-bill-wave text-primary' ?> fa-sm"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="small fw-bold"><?= htmlspecialchars($activity['description']) ?></div>
                                                    <div class="small text-muted"><?= date('M j, Y', strtotime($activity['created_at'])) ?></div>
                                                </div>
                                                <div>
                                                    <span class="small fw-bold">₦<?= number_format($activity['amount'], 2) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh pending workflows every 2 minutes
        setInterval(() => {
            // Only refresh if there are pending workflows
            <?php if (!empty($pendingWorkflows)): ?>
            location.reload();
            <?php endif; ?>
        }, 120000);

        // Add smooth transitions to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>