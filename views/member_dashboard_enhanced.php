<?php
session_start();
require_once '../config/config.php';
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';

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
        .eligibility-indicator {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .eligible {
            background: #dcfce7;
            color: #166534;
        }
        .not-eligible {
            background: #fef2f2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-white mb-4">
                        <h4><i class="fas fa-university me-2"></i>CSIMS</h4>
                        <small>Cooperative Society</small>
                    </div>
                    
                    <div class="text-white mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-white bg-opacity-20 p-2 me-3">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                <small class="opacity-75">Member #<?php echo htmlspecialchars($member['member_number'] ?? 'N/A'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <nav class="nav nav-pills flex-column">
                        <a class="nav-link text-white active" href="member_dashboard_enhanced.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link text-white" href="member_loan_application_business_rules.php">
                            <i class="fas fa-plus-circle me-2"></i>Apply for Loan
                        </a>
                        <a class="nav-link text-white" href="member_loans.php">
                            <i class="fas fa-money-bill-wave me-2"></i>My Loans
                        </a>
                        <a class="nav-link text-white" href="member_savings.php">
                            <i class="fas fa-piggy-bank me-2"></i>My Savings
                        </a>
                        <a class="nav-link text-white" href="member_profile.php">
                            <i class="fas fa-user-cog me-2"></i>Profile
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    
                    <!-- Welcome Header -->
                    <div class="welcome-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($member['first_name']); ?>!</h2>
                                <p class="mb-2 opacity-90">Member since <?php echo date('F Y', strtotime($member['date_joined'] ?? 'now')); ?> • <?php echo $membershipMonths; ?> months</p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-light text-dark">Status: <?php echo ucfirst($member['status'] ?? 'Active'); ?></span>
                                    <?php if ($membershipMonths >= $businessConfig['min_membership_months']): ?>
                                        <span class="badge bg-success">Loan Eligible</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Probation Period</span>
                                    <?php endif; ?>
                                    <?php if ($hasOverdue): ?>
                                        <span class="badge bg-danger">Has Overdue</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="text-white">
                                    <h4><i class="fas fa-star me-2"></i>Credit Score</h4>
                                    <div class="display-6 fw-bold"><?php echo $creditScore['score']; ?></div>
                                    <small><?php echo $creditScore['rating']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card card stat-card-success">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-piggy-bank fa-2x mb-3"></i>
                                    <h3 class="fw-bold">₦<?php echo number_format($savingsData['total_savings'], 2); ?></h3>
                                    <p class="mb-2">Total Savings</p>
                                    <small class="opacity-75">
                                        Mandatory: ₦<?php echo number_format($savingsData['mandatory_savings'], 2); ?><br>
                                        Voluntary: ₦<?php echo number_format($savingsData['voluntary_savings'], 2); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card card stat-card-info">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-hand-holding-usd fa-2x mb-3"></i>
                                    <h3 class="fw-bold">₦<?php echo number_format($effectiveLoanLimit, 2); ?></h3>
                                    <p class="mb-2">Loan Limit</p>
                                    <div class="progress progress-custom mb-2">
                                        <div class="progress-bar progress-bar-custom" style="width: <?php echo min(100, ($loanData['active_loan_amount'] / max($effectiveLoanLimit, 1)) * 100); ?>%"></div>
                                    </div>
                                    <small class="opacity-75"><?php echo $config->getLoanToSavingsMultiplier(); ?>x your savings</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card card stat-card-warning">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-money-bill-wave fa-2x mb-3"></i>
                                    <h3 class="fw-bold"><?php echo $loanData['active_loans']; ?></h3>
                                    <p class="mb-2">Active Loans</p>
                                    <small class="opacity-75">
                                        Outstanding: ₦<?php echo number_format($loanData['active_loan_amount'] - $loanData['total_paid'], 2); ?><br>
                                        Max allowed: <?php echo $businessConfig['max_active_loans']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card card stat-card-primary">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-calendar-check fa-2x mb-3"></i>
                                    <h3 class="fw-bold"><?php echo $savingsData['contribution_months']; ?></h3>
                                    <p class="mb-2">Active Months</p>
                                    <small class="opacity-75">
                                        Last 12 months with contributions<br>
                                        Min required: 6 months
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="dashboard-card card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <?php 
                                            $canApplyForLoan = $membershipMonths >= $businessConfig['min_membership_months'] 
                                                            && $loanData['active_loans'] < $businessConfig['max_active_loans'] 
                                                            && !$hasOverdue;
                                            ?>
                                            <a href="<?php echo $canApplyForLoan ? 'member_loan_application_business_rules.php' : '#'; ?>" 
                                               class="btn <?php echo $canApplyForLoan ? 'btn-success' : 'btn-secondary'; ?> quick-action-btn w-100 <?php echo !$canApplyForLoan ? 'disabled' : ''; ?>">
                                                <i class="fas fa-plus-circle me-2"></i>
                                                Apply for Loan
                                                <?php if (!$canApplyForLoan): ?>
                                                    <small class="d-block mt-1">Requirements not met</small>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <a href="member_savings.php" class="btn btn-primary quick-action-btn w-100">
                                                <i class="fas fa-piggy-bank me-2"></i>
                                                Make Contribution
                                            </a>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <a href="member_loans.php" class="btn btn-outline-primary quick-action-btn w-100">
                                                <i class="fas fa-eye me-2"></i>
                                                View Loan Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Loan Eligibility Status -->
                        <div class="col-lg-8 mb-4">
                            <div class="dashboard-card card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Loan Eligibility Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Membership Duration</span>
                                                <span class="eligibility-indicator <?php echo $membershipMonths >= $businessConfig['min_membership_months'] ? 'eligible' : 'not-eligible'; ?>">
                                                    <?php echo $membershipMonths; ?>/<?php echo $businessConfig['min_membership_months']; ?> months
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Monthly Savings</span>
                                                <span class="eligibility-indicator <?php echo $savingsData['mandatory_savings'] >= $businessConfig['min_mandatory_savings'] ? 'eligible' : 'not-eligible'; ?>">
                                                    ₦<?php echo number_format($savingsData['mandatory_savings'], 2); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Active Loans</span>
                                                <span class="eligibility-indicator <?php echo $loanData['active_loans'] < $businessConfig['max_active_loans'] ? 'eligible' : 'not-eligible'; ?>">
                                                    <?php echo $loanData['active_loans']; ?>/<?php echo $businessConfig['max_active_loans']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Payment History</span>
                                                <span class="eligibility-indicator <?php echo !$hasOverdue ? 'eligible' : 'not-eligible'; ?>">
                                                    <?php echo !$hasOverdue ? 'Up to date' : 'Overdue'; ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Member Status</span>
                                                <span class="eligibility-indicator <?php echo ($member['status'] ?? '') === 'Active' ? 'eligible' : 'not-eligible'; ?>">
                                                    <?php echo ucfirst($member['status'] ?? 'Unknown'); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Overall Eligibility</span>
                                                <span class="eligibility-indicator <?php echo $canApplyForLoan ? 'eligible' : 'not-eligible'; ?>">
                                                    <?php echo $canApplyForLoan ? 'Eligible' : 'Not Eligible'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="col-lg-4 mb-4">
                            <div class="dashboard-card card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recentActivity)): ?>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    <i class="fas <?php echo $activity['type'] === 'contribution' ? 'fa-piggy-bank text-success' : 'fa-money-bill-wave text-info'; ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($activity['description']); ?></div>
                                                    <small class="text-muted">₦<?php echo number_format($activity['amount'], 2); ?></small>
                                                </div>
                                                <div class="text-muted">
                                                    <small><?php echo date('M j', strtotime($activity['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">No recent activity</p>
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
</body>
</html>