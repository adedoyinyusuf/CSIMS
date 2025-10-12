<?php
session_start();
require_once '../config/config.php';
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/notification_controller.php';
require_once '../classes/WorkflowService.php';
require_once '../classes/LoanTypeService.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

// Initialize services and controllers
try {
    // Database connections
    $database = new Database();
    $pdo = $database->getConnection();
    $conn = Database::getInstance()->getConnection();
    
    // Configuration services
    $config = SystemConfigService::getInstance($pdo);
    $businessRules = new BusinessRulesService($pdo);
    
    // Controllers
    $memberController = new MemberController();
    $loanController = new LoanController($conn);
    $notificationController = new NotificationController($conn);
    
    // Phase 2 services
    $workflowService = new WorkflowService();
    $loanTypeService = new LoanTypeService();
} catch (Exception $e) {
    die("System initialization failed: " . $e->getMessage());
}

$member_id = $_SESSION['member_id'];

// Get member details
$member = $memberController->getMemberById($member_id);

// =================== PHASE 1 DATA ===================
// Get member loans (Phase 1 format)
$member_loans = $loanController->getMemberLoans($member_id);
if ($member_loans === false) {
    $member_loans = [];
}

// Get member savings accounts (Phase 1 format)
try {
    require_once '../src/autoload.php';
    $savingsRepository = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $member_savings = $savingsRepository->findByMemberId($member_id);
} catch (Exception $e) {
    $member_savings = [];
}

// Get notifications (Phase 1 format)
$member_notifications = $notificationController->getMemberNotifications($member_id);
if ($member_notifications === false) {
    $member_notifications = [];
}

// Calculate Phase 1 totals
$total_savings_phase1 = 0;
$total_loan_amount_phase1 = 0;
$active_loans_phase1 = 0;

foreach ($member_savings as $savings_account) {
    $total_savings_phase1 += $savings_account->getBalance();
}

foreach ($member_loans as $loan) {
    $total_loan_amount_phase1 += $loan['amount'];
    if (in_array($loan['status'], ['Approved', 'Disbursed'])) {
        $active_loans_phase1++;
    }
}

$unread_notifications = count(array_filter($member_notifications, function($n) { return !$n['is_read']; }));

// =================== PHASE 2 DATA ===================
// Get savings information using business rules (Phase 2)
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

// Get loan information (Phase 2)
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_loans,
            SUM(CASE WHEN status IN ('active', 'disbursed', 'Approved') THEN 1 ELSE 0 END) as active_loans,
            SUM(CASE WHEN status IN ('active', 'disbursed', 'Approved') THEN principal_amount ELSE 0 END) as active_loan_amount,
            SUM(CASE WHEN status IN ('active', 'disbursed', 'Approved') THEN amount_paid ELSE 0 END) as total_paid
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
$maxLoanBasedOnSavings = max($savingsData['total_savings'], $total_savings_phase1) * $config->getLoanToSavingsMultiplier();
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

// Get recent activity combining Phase 1 and Phase 2
try {
    $stmt = $pdo->prepare("
        (SELECT 'contribution' as type, amount, created_at, 'Savings contribution' as description
         FROM contributions WHERE member_id = ? AND status = 'completed'
         ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'loan_payment' as type, amount_paid as amount, updated_at as created_at, 'Loan payment' as description
         FROM loans WHERE member_id = ? AND amount_paid > 0
         ORDER BY updated_at DESC LIMIT 3)
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$member_id, $member_id]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentActivity = [];
}

// Combine savings data (Phase 1 + Phase 2)
$totalSavings = max($savingsData['total_savings'], $total_savings_phase1);
$activeLoanCount = max($loanData['active_loans'], $active_loans_phase1);
$totalLoanAmount = max($loanData['active_loan_amount'], $total_loan_amount_phase1);

// Loan eligibility check
$canApplyForLoan = $membershipMonths >= $businessConfig['min_membership_months'] 
                && $activeLoanCount < $businessConfig['max_active_loans'] 
                && !$hasOverdue
                && $totalSavings >= $businessConfig['min_mandatory_savings'];
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
            background: white;
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
        .stat-card-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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
        .notification-item {
            border-left: 4px solid #dee2e6;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            border-left-color: var(--member-primary);
            background-color: rgba(0, 123, 186, 0.05);
        }
        .chart-container {
            position: relative;
            height: 200px;
        }
        .metric-card {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                            <a href="member_dashboard_complete.php" class="nav-link text-white active">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_loan_application_integrated.php" class="nav-link text-white">
                                <i class="fas fa-plus me-2"></i> Apply for Loan
                                <?php if ($canApplyForLoan): ?>
                                    <span class="badge bg-success ms-2">Available</span>
                                <?php endif; ?>
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
                            <a href="member_loans.php" class="nav-link text-white">
                                <i class="fas fa-money-bill-wave me-2"></i> My Loans
                                <?php if ($activeLoanCount > 0): ?>
                                    <span class="badge bg-info ms-2"><?= $activeLoanCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_savings_enhanced.php" class="nav-link text-white">
                                <i class="fas fa-piggy-bank me-2"></i> My Savings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_notifications.php" class="nav-link text-white">
                                <i class="fas fa-bell me-2"></i> Notifications
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger ms-2"><?= $unread_notifications ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_profile.php" class="nav-link text-white">
                                <i class="fas fa-user-cog me-2"></i> Profile
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
                                <p class="lead mb-0">Welcome to your comprehensive CSIMS dashboard</p>
                                <div class="d-flex gap-2 flex-wrap mt-2">
                                    <small class="opacity-75">Member since <?= date('F Y', strtotime($member['date_joined'] ?? 'now')) ?></small>
                                    <small class="opacity-75">•</small>
                                    <small class="opacity-75"><?= $membershipMonths ?> months active</small>
                                    <small class="opacity-75">•</small>
                                    <small class="opacity-75">Member #<?= htmlspecialchars($member['member_number'] ?? 'N/A') ?></small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <small>Credit Score</small>
                                    <h3 class="mb-0"><?= $creditScore['score'] ?? 'N/A' ?></h3>
                                    <small class="opacity-75"><?= $creditScore['rating'] ?? 'Not Rated' ?></small>
                                </div>
                                <?php if ($hasOverdue): ?>
                                    <div class="alert alert-warning alert-sm py-2 px-3 mt-2">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <small>Overdue payment detected</small>
                                    </div>
                                <?php endif; ?>
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
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card stat-card-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-piggy-bank fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title">₦<?= number_format($totalSavings, 2) ?></h4>
                                    <p class="card-text small mb-0">Total Savings</p>
                                    <div class="progress progress-custom mt-2">
                                        <div class="progress-bar progress-bar-custom" style="width: <?= min(100, ($totalSavings / $businessConfig['min_mandatory_savings']) * 100) ?>%"></div>
                                    </div>
                                    <small class="opacity-75 mt-1 d-block">
                                        Mandatory: ₦<?= number_format($savingsData['mandatory_savings'], 2) ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card stat-card-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-money-bill-wave fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title"><?= $activeLoanCount ?></h4>
                                    <p class="card-text small mb-0">Active Loans</p>
                                    <small class="opacity-75">₦<?= number_format($totalLoanAmount, 0) ?> outstanding</small>
                                    <div class="mt-2">
                                        <small class="opacity-75">Max: <?= $businessConfig['max_active_loans'] ?> loans</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card stat-card-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-hand-holding-usd fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title">₦<?= number_format($effectiveLoanLimit, 0) ?></h4>
                                    <p class="card-text small mb-0">Loan Limit</p>
                                    <small class="opacity-75"><?= $businessConfig['loan_to_savings_multiplier'] ?>x savings</small>
                                    <div class="progress progress-custom mt-2">
                                        <div class="progress-bar progress-bar-custom" style="width: <?= min(100, ($totalLoanAmount / max($effectiveLoanLimit, 1)) * 100) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card stat-card-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-3 opacity-75"></i>
                                    <h4 class="card-title"><?= count($pendingWorkflows) ?></h4>
                                    <p class="card-text small mb-0">Pending Applications</p>
                                    <small class="opacity-75">In approval process</small>
                                    <?php if (!empty($pendingWorkflows)): ?>
                                        <div class="mt-2">
                                            <small class="opacity-75">
                                                Oldest: <?= max(array_column($pendingWorkflows, 'days_pending')) ?> days
                                            </small>
                                        </div>
                                    <?php endif; ?>
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
                                        Pending Applications (<?= count($pendingWorkflows) ?>)
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach (array_slice($pendingWorkflows, 0, 3) as $workflow): ?>
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
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-center mt-3">
                                        <a href="../member_workflow_tracking.php" class="btn btn-outline-primary">
                                            View All Applications
                                        </a>
                                    </div>
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
                                        <div class="col-md-6 col-xl-3 mb-3">
                                            <button class="quick-action-btn btn <?= $canApplyForLoan ? 'btn-success' : 'btn-secondary disabled' ?> w-100" 
                                                    onclick="<?= $canApplyForLoan ? "location.href='member_loan_application_integrated.php'" : 'showEligibilityModal()' ?>">
                                                <i class="fas fa-plus me-2"></i>
                                                Apply for Loan
                                                <?php if (!$canApplyForLoan): ?>
                                                    <small class="d-block mt-1">Requirements not met</small>
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                        <div class="col-md-6 col-xl-3 mb-3">
                                            <button class="quick-action-btn btn btn-primary w-100" onclick="location.href='member_savings_enhanced.php'">
                                                <i class="fas fa-piggy-bank me-2"></i>
                                                Make Deposit
                                            </button>
                                        </div>
                                        <div class="col-md-6 col-xl-3 mb-3">
                                            <button class="quick-action-btn btn btn-info w-100" onclick="location.href='../member_workflow_tracking.php'">
                                                <i class="fas fa-chart-line me-2"></i>
                                                Track Applications
                                            </button>
                                        </div>
                                        <div class="col-md-6 col-xl-3 mb-3">
                                            <button class="quick-action-btn btn btn-outline-primary w-100" onclick="location.href='member_loans.php'">
                                                <i class="fas fa-eye me-2"></i>
                                                View Loans
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Loans (Phase 1 + Phase 2) -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header bg-transparent">
                                    <h5 class="mb-0">
                                        <i class="fas fa-money-bill-wave text-info me-2"></i>
                                        Recent Loan Activity
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_loans)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                                            <p>No loan history found.</p>
                                            <?php if ($canApplyForLoan): ?>
                                                <a href="member_loan_application_integrated.php" class="btn btn-primary">Apply for Your First Loan</a>
                                            <?php else: ?>
                                                <a href="member_savings_enhanced.php" class="btn btn-success">Build Your Savings First</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_loans, 0, 3) as $loan): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                                <div>
                                                    <strong class="text-primary">₦<?= number_format($loan['amount'], 2) ?></strong>
                                                    <div class="small text-muted">
                                                        Applied: <?= date('M d, Y', strtotime($loan['application_date'])) ?>
                                                        <?php if (isset($loan['interest_rate'])): ?>
                                                            • Rate: <?= $loan['interest_rate'] ?>%
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php 
                                                        echo $loan['status'] === 'Approved' ? 'success' : 
                                                            ($loan['status'] === 'Pending' ? 'warning' : 
                                                            ($loan['status'] === 'Rejected' ? 'danger' : 'info')); 
                                                    ?>">
                                                        <?= $loan['status'] ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="text-center">
                                            <a href="member_loans.php" class="btn btn-outline-primary btn-sm">View All Loans</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Available Loan Types (Phase 2) -->
                            <?php if (!empty($availableLoanTypes) && $canApplyForLoan): ?>
                            <div class="dashboard-card">
                                <div class="card-header bg-transparent">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list text-success me-2"></i>
                                        Available Loan Types
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach (array_slice($availableLoanTypes, 0, 2) as $loanType): ?>
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
                                                    <span class="eligibility-badge eligible">Available</span>
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
                                    
                                    <?php if (count($availableLoanTypes) > 2): ?>
                                        <div class="text-center mt-3">
                                            <a href="member_loan_application_integrated.php" class="btn btn-outline-success">
                                                View All <?= count($availableLoanTypes) ?> Loan Types
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Membership Status & Eligibility -->
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
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Credit Rating:</span>
                                        <strong class="text-primary"><?= $creditScore['rating'] ?? 'Not Rated' ?></strong>
                                    </div>
                                    
                                    <hr>
                                    <h6>Loan Eligibility</h6>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small>Membership:</small>
                                        <span class="eligibility-badge <?= $membershipMonths >= $businessConfig['min_membership_months'] ? 'eligible' : 'not-eligible' ?>">
                                            <?= $membershipMonths >= $businessConfig['min_membership_months'] ? '✓' : '✗' ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small>Savings:</small>
                                        <span class="eligibility-badge <?= $totalSavings >= $businessConfig['min_mandatory_savings'] ? 'eligible' : 'not-eligible' ?>">
                                            <?= $totalSavings >= $businessConfig['min_mandatory_savings'] ? '✓' : '✗' ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small>Active Loans:</small>
                                        <span class="eligibility-badge <?= $activeLoanCount < $businessConfig['max_active_loans'] ? 'eligible' : 'not-eligible' ?>">
                                            <?= $activeLoanCount < $businessConfig['max_active_loans'] ? '✓' : '✗' ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small>Payment History:</small>
                                        <span class="eligibility-badge <?= !$hasOverdue ? 'eligible' : 'not-eligible' ?>">
                                            <?= !$hasOverdue ? '✓' : '✗' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div class="dashboard-card mb-4">
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

                            <!-- Notifications -->
                            <div class="dashboard-card">
                                <div class="card-header bg-transparent">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bell text-warning me-2"></i>
                                        Recent Notifications
                                        <?php if ($unread_notifications > 0): ?>
                                            <span class="badge bg-danger ms-2"><?= $unread_notifications ?></span>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_notifications)): ?>
                                        <p class="text-muted small text-center py-3">No notifications</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_notifications, 0, 4) as $notification): ?>
                                            <div class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="small fw-bold"><?= htmlspecialchars($notification['title']) ?></div>
                                                        <div class="small text-muted"><?= htmlspecialchars($notification['message']) ?></div>
                                                        <div class="small text-muted"><?= date('M j, H:i', strtotime($notification['created_at'])) ?></div>
                                                    </div>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-primary ms-2">New</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="text-center mt-3">
                                            <a href="member_notifications.php" class="btn btn-outline-primary btn-sm">View All Notifications</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Eligibility Requirements Modal -->
    <div class="modal fade" id="eligibilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Loan Eligibility Requirements</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>To be eligible for a loan, you must meet the following requirements:</p>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Membership Duration</strong>
                                <div class="small text-muted">Minimum <?= $businessConfig['min_membership_months'] ?> months</div>
                            </div>
                            <span class="badge bg-<?= $membershipMonths >= $businessConfig['min_membership_months'] ? 'success' : 'danger' ?>"><?= $membershipMonths ?> months</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Minimum Savings</strong>
                                <div class="small text-muted">At least ₦<?= number_format($businessConfig['min_mandatory_savings'], 2) ?></div>
                            </div>
                            <span class="badge bg-<?= $totalSavings >= $businessConfig['min_mandatory_savings'] ? 'success' : 'danger' ?>">₦<?= number_format($totalSavings, 2) ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Active Loans</strong>
                                <div class="small text-muted">Maximum <?= $businessConfig['max_active_loans'] ?> active loans</div>
                            </div>
                            <span class="badge bg-<?= $activeLoanCount < $businessConfig['max_active_loans'] ? 'success' : 'danger' ?>"><?= $activeLoanCount ?> active</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Payment History</strong>
                                <div class="small text-muted">No overdue payments</div>
                            </div>
                            <span class="badge bg-<?= !$hasOverdue ? 'success' : 'danger' ?>"><?= !$hasOverdue ? 'Up to date' : 'Overdue' ?></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="member_savings_enhanced.php" class="btn btn-primary">Improve Eligibility</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let eligibilityModal = new bootstrap.Modal(document.getElementById('eligibilityModal'));

        function showEligibilityModal() {
            eligibilityModal.show();
        }

        // Auto-refresh pending workflows every 2 minutes
        setInterval(() => {
            <?php if (!empty($pendingWorkflows)): ?>
            // Check for updates without full page refresh
            fetch('ajax/check_workflow_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.has_updates) {
                        location.reload();
                    }
                })
                .catch(error => console.log('Update check failed:', error));
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

            // Animate stat cards on load
            const statCards = document.querySelectorAll('[class*="stat-card-"]');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        card.style.transform = 'scale(1)';
                    }, 200);
                }, index * 150);
            });
        });

        // Interactive notification click
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.remove('unread');
                // Mark as read via AJAX
                // fetch('ajax/mark_notification_read.php', {...});
            });
        });

        // Interactive workflow progress hover
        document.querySelectorAll('.workflow-progress').forEach(workflow => {
            workflow.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });
            
            workflow.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = '';
            });
        });
    </script>
</body>
</html>