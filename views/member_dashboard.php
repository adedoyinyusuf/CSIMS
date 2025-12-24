<?php
// session is initialized via includes/session.php in config.php
require_once '../config/config.php';
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';
require_once '../src/autoload.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

// Initialize services
try {
    // Initialize PDO for business rules and mysqli for repositories
    $database = new PdoDatabase();
    $pdo = $database->getConnection();
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $config = SystemConfigService::getInstance($pdo);
    $businessRules = new BusinessRulesService($pdo);
    $memberController = new MemberController();
} catch (Exception $e) {
    die("System initialization failed: " . $e->getMessage());
}

$member_id = $_SESSION['member_id'];

// Get member details and statistics
$member = $memberController->getMemberById($member_id);

// Get savings information using repositories (balances) and business rules (months)
try {
    $savingsRepo = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $totalBalance = $savingsRepo->getTotalBalanceByMember((int)$member_id);

    // Sum balances by account type for member
    $stmt = $conn->prepare("SELECT 
            COALESCE(SUM(CASE WHEN account_type LIKE 'Mandatory%' THEN balance ELSE 0 END), 0) AS mandatory_balance,
            COALESCE(SUM(CASE WHEN account_type LIKE 'Voluntary%' THEN balance ELSE 0 END), 0) AS voluntary_balance
        FROM savings_accounts 
        WHERE member_id = ? 
          AND account_status IN ('Active','Inactive')");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['mandatory_balance' => 0, 'voluntary_balance' => 0];

    // Contribution months from BusinessRulesService
    $summary = $businessRules->getMemberSavingsData((int)$member_id);

    $savingsData = [
        'mandatory_savings' => (float)($row['mandatory_balance'] ?? 0),
        'voluntary_savings' => (float)($row['voluntary_balance'] ?? 0),
        'total_savings' => (float)$totalBalance,
        'savings_months' => (int)($summary['savings_months'] ?? 0),
    ];
} catch (Exception $e) {
    $savingsData = ['mandatory_savings' => 0, 'voluntary_savings' => 0, 'total_savings' => 0, 'savings_months' => 0];
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

$overrideLoanStatsError = null;
try {
    // Schema-resilient override: recompute using mysqli and column detection
    if ($conn) {
        $hasAmountPaid = ($conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'"))->num_rows > 0;
        $hasPrincipalAmount = ($conn->query("SHOW COLUMNS FROM loans LIKE 'principal_amount'"))->num_rows > 0;
        $hasAmount = ($conn->query("SHOW COLUMNS FROM loans LIKE 'amount'"))->num_rows > 0;
        $hasRemainingBalance = ($conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'"))->num_rows > 0;
        $hasTotalRepaid = ($conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'"))->num_rows > 0;

        // Counts
        $stmt = $conn->prepare("SELECT COUNT(*) AS total_loans, SUM(CASE WHEN LOWER(TRIM(status)) IN ('active','disbursed','approved') THEN 1 ELSE 0 END) AS active_loans FROM loans WHERE member_id = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $counts = $res ? $res->fetch_assoc() : ['total_loans' => 0, 'active_loans' => 0];

        // Active principal/amount
        $activeAmt = 0.0;
        if ($hasPrincipalAmount) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(principal_amount),0) AS amt FROM loans WHERE member_id = ? AND LOWER(TRIM(status)) IN ('active','disbursed','approved')");
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $activeAmt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
        } elseif ($hasAmount) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS amt FROM loans WHERE member_id = ? AND LOWER(TRIM(status)) IN ('active','disbursed','approved')");
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $activeAmt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
        }

        // Paid or repaid
        $totalPaid = 0.0;
        if ($hasAmountPaid) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM loans WHERE member_id = ? AND LOWER(TRIM(status)) IN ('active','disbursed','approved')");
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $totalPaid = (float)($stmt->get_result()->fetch_assoc()['paid'] ?? 0);
        } elseif ($hasTotalRepaid) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(total_repaid),0) AS paid FROM loans WHERE member_id = ? AND LOWER(TRIM(status)) IN ('active','disbursed','approved')");
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $totalPaid = (float)($stmt->get_result()->fetch_assoc()['paid'] ?? 0);
        }

        // Outstanding prefers remaining_balance if available
        $outstanding = $activeAmt - $totalPaid;
        if ($hasRemainingBalance) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(remaining_balance),0) AS bal FROM loans WHERE member_id = ? AND LOWER(TRIM(status)) IN ('active','disbursed','approved')");
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $outstanding = (float)($stmt->get_result()->fetch_assoc()['bal'] ?? $outstanding);
        }

        $loanData = [
            'total_loans' => (int)($counts['total_loans'] ?? 0),
            'active_loans' => (int)($counts['active_loans'] ?? 0),
            'active_loan_amount' => (float)$activeAmt,
            'total_paid' => (float)$totalPaid,
            'outstanding' => (float)$outstanding,
        ];
    }
} catch (Exception $e) {
    $overrideLoanStatsError = $e->getMessage();
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

// Minimum savings months requirement and default interest rate via SystemConfigService only
// Remove legacy settings table fallbacks; rely on defined defaults
$minSavingsMonths = (int)$config->get('MIN_SAVINGS_MONTHS', 6);
$defaultInterestRate = (float)$config->get('DEFAULT_INTEREST_RATE', 12.0);

// Check membership duration
$membershipMonths = 0;
// Support multiple possible column names: date_joined, join_date, created_at
$joinDateRaw = null;
if (is_array($member)) {
    $joinDateRaw = $member['date_joined'] ?? $member['join_date'] ?? ($member['created_at'] ?? null);
}
if ($joinDateRaw) {
    $ts = strtotime($joinDateRaw);
    if ($ts !== false) {
        $joinDate = new DateTime(date('Y-m-d', $ts));
        $now = new DateTime();
        $diff = $joinDate->diff($now);
        $membershipMonths = ($diff->y * 12) + $diff->m;
    }
}

// Get recent activity (schema-resilient for column name variants)
try {
    $recentActivity = [];
    if ($conn) {
        $hasSavingsTx = ($conn->query("SHOW TABLES LIKE 'savings_transactions'"))->num_rows > 0;
        $hasRepayments = ($conn->query("SHOW TABLES LIKE 'loan_repayments'"))->num_rows > 0;

        // Savings transactions column detection
        $stStatusCol = 'transaction_status';
        $stTypeCol = 'transaction_type';
        $stDateCol = 'transaction_date';
        $stAmtCol = 'amount';
        if ($hasSavingsTx) {
            $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'transaction_status'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'status'");
                if ($col && $col->num_rows > 0) { $stStatusCol = 'status'; }
            }

            $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'transaction_type'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'type'");
                if ($col && $col->num_rows > 0) { $stTypeCol = 'type'; }
            }

            $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'transaction_date'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'created_at'");
                if ($col && $col->num_rows > 0) { $stDateCol = 'created_at'; } else {
                    $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'date'");
                    if ($col && $col->num_rows > 0) { $stDateCol = 'date'; }
                }
            }

            $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'amount'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM savings_transactions LIKE 'transaction_amount'");
                if ($col && $col->num_rows > 0) { $stAmtCol = 'transaction_amount'; }
            }
        }

        // Loan repayments column detection
        $lrStatusCol = 'status';
        $lrDateCol = 'payment_date';
        $lrAmtCol = 'amount';
        if ($hasRepayments) {
            $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'status'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'payment_status'");
                if ($col && $col->num_rows > 0) { $lrStatusCol = 'payment_status'; }
            }

            $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'payment_date'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'created_at'");
                if ($col && $col->num_rows > 0) { $lrDateCol = 'created_at'; } else {
                    $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'date'");
                    if ($col && $col->num_rows > 0) { $lrDateCol = 'date'; }
                }
            }

            $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'amount'");
            if (!$col || $col->num_rows === 0) {
                $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'payment_amount'");
                if ($col && $col->num_rows > 0) { $lrAmtCol = 'payment_amount'; }
            }
        }

        // Build UNION query pieces based on available tables
        $parts = [];
        $params = [];
        if ($hasSavingsTx) {
            $parts[] = "(SELECT 'savings_deposit' as type, st.$stAmtCol AS amount, st.$stDateCol AS created_at, 'Savings deposit' as description\n             FROM savings_transactions st WHERE st.member_id = ? AND (st.$stTypeCol = 'Deposit' OR st.$stTypeCol = 'deposit') AND (st.$stStatusCol = 'Completed' OR st.$stStatusCol = 'completed')\n             ORDER BY st.$stDateCol DESC LIMIT 3)";
            $params[] = $member_id;
        }
        if ($hasRepayments) {
            $parts[] = "(SELECT 'loan_payment' as type, lr.$lrAmtCol AS amount, lr.$lrDateCol AS created_at, 'Loan payment' as description\n             FROM loan_repayments lr WHERE lr.member_id = ? AND (lr.$lrStatusCol = 'Completed' OR lr.$lrStatusCol = 'completed')\n             ORDER BY lr.$lrDateCol DESC LIMIT 3)";
            $params[] = $member_id;
        }

        if (!empty($parts)) {
            $sql = implode(" UNION ALL ", $parts) . " ORDER BY created_at DESC LIMIT 5";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $recentActivity = [];
        }
    }
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/csims-colors.css" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
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
            background: linear-gradient(135deg, var(--success) 0%, var(--success) 100%) !important;
            color: white;
        }
        .stat-card-info {
            /* Replaced CSS variables with concrete colors to avoid white fallback */
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%) !important;
            color: white;
        }
        .stat-card-warning {
            /* Replaced CSS variables with concrete colors to avoid white fallback */
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: white;
        }
        .stat-card-primary {
            background: linear-gradient(135deg, var(--member-primary) 0%, var(--member-secondary) 100%) !important;
            color: white;
        }
        /* Ensure the inner body doesn’t override stat card backgrounds */
        .stat-card-success .card-body,
        .stat-card-info .card-body,
        .stat-card-warning .card-body,
        .stat-card-primary .card-body {
            background: transparent !important;
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
            background: #ffffff;
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
    <?php include __DIR__ . '/includes/member_header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content (offcanvas handles navigation) -->
            <div class="col-12">
                <div class="container-fluid py-4">
                    
                    <!-- Welcome Header -->
                    <div class="welcome-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($member['first_name']); ?>!</h2>
        <p class="mb-2 opacity-90">Member since <?php echo $joinDateRaw ? date('F Y', strtotime($joinDateRaw)) : 'Unknown'; ?> • <?php echo $membershipMonths; ?> months</p>
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
                                        Outstanding: ₦<?php echo number_format(($loanData['outstanding'] ?? ($loanData['active_loan_amount'] - $loanData['total_paid'])), 2); ?><br>
                                        Max allowed: <?php echo $businessConfig['max_active_loans']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="dashboard-card card stat-card-primary">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-calendar-check fa-2x mb-3"></i>
                                    <h3 class="fw-bold"><?php echo $savingsData['savings_months']; ?></h3>
                                    <p class="mb-2">Savings Participation</p>
                                    <small class="opacity-75">
                                        Savings deposits in last 12 months<br>
                                        Min required: <?php echo (int)$minSavingsMonths; ?> months
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loan Eligibility -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="dashboard-card card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Loan Eligibility</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="list-unstyled mb-0">
                                                <li class="mb-2">
                                                    <i class="fas fa-user-check me-2 text-success"></i>
                                                    Must be a member
                                                </li>
                                                <li class="mb-2">
                                                    <?php $meetsMonths = $membershipMonths >= $businessConfig['min_membership_months']; ?>
                                                    <i class="fas <?php echo $meetsMonths ? 'fa-check text-success' : 'fa-times text-danger'; ?> me-2"></i>
                                                    At least <?php echo (int)$businessConfig['min_membership_months']; ?> months as member
                                                    <small class="ms-2 opacity-75">Current: <?php echo (int)$membershipMonths; ?> months</small>
                                                </li>
                                                <li class="mb-2">
                                                    <?php $meetsSavings = $savingsData['savings_months'] >= $minSavingsMonths; ?>
                                <i class="fas <?php echo $meetsSavings ? 'fa-check text-success' : 'fa-times text-danger'; ?> me-2"></i>
                                Regular monthly savings
                                <small class="ms-2 opacity-75">Required: <?php echo (int)$minSavingsMonths; ?> of last 12 months, Current: <?php echo (int)$savingsData['savings_months']; ?></small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-percentage me-2 text-primary"></i>
                                                    Interest: <?php echo number_format($defaultInterestRate, 2); ?>% per annum
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Loan offers require agreement to the interest rate and terms.
                                            </div>
                                        </div>
                                    </div>
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
                                                            && !$hasOverdue 
                                                            && ($savingsData['savings_months'] >= $minSavingsMonths);
                                            ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="agreeInterest">
                                                <label class="form-check-label" for="agreeInterest">
                                                    I agree to interest rate and terms
                                                </label>
                                            </div>
                                            <a id="applyLoanBtn" href="#" 
                                               class="btn btn-secondary quick-action-btn w-100 disabled">
                                                <i class="fas fa-plus-circle me-2"></i>
                                                Apply for Loan
                                                <small id="applyLoanHint" class="d-block mt-1">
                                                    <?php echo $canApplyForLoan ? 'Please agree to interest terms' : 'Requirements not met'; ?>
                                                </small>
                                            </a>
                                            <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                var agree = document.getElementById('agreeInterest');
                                                var btn = document.getElementById('applyLoanBtn');
                                                var hint = document.getElementById('applyLoanHint');
                                                var canApply = <?php echo $canApplyForLoan ? 'true' : 'false'; ?>;
                                                function updateApplyState() {
                                                    if (canApply && agree && agree.checked) {
                                                        btn.classList.remove('disabled', 'btn-secondary');
                                                        btn.classList.add('btn-success');
                                                        btn.setAttribute('href', 'member_loan_application_business_rules.php');
                                                        if (hint) { hint.classList.add('d-none'); }
                                                    } else {
                                                        btn.classList.add('disabled');
                                                        btn.classList.remove('btn-success');
                                                        btn.classList.add('btn-secondary');
                                                        btn.setAttribute('href', '#');
                                                        if (hint) {
                                                            hint.classList.remove('d-none');
                                                            hint.textContent = canApply ? 'Please agree to interest terms' : 'Requirements not met';
                                                        }
                                                    }
                                                }
                                                if (agree) { agree.addEventListener('change', updateApplyState); }
                                                updateApplyState();
                                            });
                                            </script>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <a href="member_savings.php" class="btn btn-primary quick-action-btn w-100">
                                                <i class="fas fa-piggy-bank me-2"></i>
                                                Add Savings Deposit
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
                                                    <i class="fas <?php echo $activity['type'] === 'savings_deposit' ? 'fa-piggy-bank text-success' : 'fa-money-bill-wave text-info'; ?>"></i>
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