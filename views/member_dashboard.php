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
$minSavingsMonths = (int)$config->get('MIN_SAVINGS_MONTHS', 6);
$defaultInterestRate = (float)$config->get('DEFAULT_INTEREST_RATE', 12.0);

// Check membership duration
$membershipMonths = 0;
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

// Get recent activity
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

        // Build UNION query
        $parts = [];
        $params = [];
        if ($hasSavingsTx) {
            $parts[] = "(SELECT 'savings_deposit' as type, st.$stAmtCol AS amount, st.$stDateCol AS created_at, 'Savings deposit' as description FROM savings_transactions st WHERE st.member_id = ? AND (st.$stTypeCol = 'Deposit' OR st.$stTypeCol = 'deposit') AND (st.$stStatusCol = 'Completed' OR st.$stStatusCol = 'completed') ORDER BY st.$stDateCol DESC LIMIT 3)";
            $params[] = $member_id;
        }
        if ($hasRepayments) {
            $parts[] = "(SELECT 'loan_payment' as type, lr.$lrAmtCol AS amount, lr.$lrDateCol AS created_at, 'Loan payment' as description FROM loan_repayments lr WHERE lr.member_id = ? AND (lr.$lrStatusCol = 'Completed' OR lr.$lrStatusCol = 'completed') ORDER BY lr.$lrDateCol DESC LIMIT 3)";
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
    <title>Member Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS (Required for Navbar) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS (No Preflight to prevent Bootstrap conflict) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 900: '#0c4a6e' },
                        slate: { 850: '#1e293b' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'glass': '0 4px 30px rgba(0, 0, 0, 0.1)',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .glass-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02); }
        .stat-icon-bg {
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.1) 0%, rgba(14, 165, 233, 0.1) 100%);
        }
    </style>
</head>
<body class="bg-slate-50 relative">

    <?php include __DIR__ . '/includes/member_header.php'; ?>

    <div class="relative min-h-screen pb-12">
        <!-- Background Decoration -->
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-br from-primary-900 via-primary-800 to-slate-900 -z-10"></div>
        <div class="absolute top-0 left-0 w-full h-96 opacity-30 bg-[url('../assets/images/finance_hero_bg.png')] bg-cover bg-center -z-10 mix-blend-overlay"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Welcome Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 text-white">
                <div>
                    <h1 class="text-3xl font-bold mb-1">Welcome back, <?php echo htmlspecialchars($member['first_name']); ?>!</h1>
                    <p class="text-primary-100 opacity-90 text-sm">
                        Member since <?php echo $joinDateRaw ? date('F Y', strtotime($joinDateRaw)) : 'Unknown'; ?> • 
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white backdrop-blur-sm ml-2">
                            <?php echo $membershipMonths; ?> months
                        </span>
                    </p>
                </div>
                <div class="mt-4 md:mt-0 flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wider text-primary-200 font-semibold">Credit Score</p>
                        <p class="text-2xl font-bold"><?php echo $creditScore['score']; ?></p>
                    </div>
                    <div class="h-10 w-10 flex items-center justify-center rounded-full bg-white/20 backdrop-blur-md">
                        <i class="fas fa-star text-yellow-400"></i>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Savings -->
                <div class="glass-card p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Total Savings</p>
                            <h3 class="text-2xl font-bold text-slate-900">₦<?php echo number_format($savingsData['total_savings'], 2); ?></h3>
                        </div>
                        <div class="p-3 rounded-lg bg-emerald-50 text-emerald-600">
                            <i class="fas fa-piggy-bank text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500">
                        <div class="flex justify-between mb-1">
                            <span>Mandatory:</span>
                            <span class="font-medium text-slate-700">₦<?php echo number_format($savingsData['mandatory_savings'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Voluntary:</span>
                            <span class="font-medium text-slate-700">₦<?php echo number_format($savingsData['voluntary_savings'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Loan Limit -->
                <div class="glass-card p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Loan Limit</p>
                            <h3 class="text-2xl font-bold text-slate-900">₦<?php echo number_format($effectiveLoanLimit, 2); ?></h3>
                        </div>
                        <div class="p-3 rounded-lg bg-blue-50 text-blue-600">
                            <i class="fas fa-hand-holding-usd text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-slate-100 rounded-full h-2 mb-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($loanData['active_loan_amount'] / max($effectiveLoanLimit, 1)) * 100); ?>%"></div>
                        </div>
                        <p class="text-xs text-slate-500"><?php echo $config->getLoanToSavingsMultiplier(); ?>x your savings multiplier</p>
                    </div>
                </div>

                <!-- Active Loans -->
                <div class="glass-card p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Active Loans</p>
                            <h3 class="text-2xl font-bold text-slate-900"><?php echo $loanData['active_loans']; ?></h3>
                        </div>
                        <div class="p-3 rounded-lg bg-amber-50 text-amber-600">
                            <i class="fas fa-money-bill-wave text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-xs text-slate-500 mb-1">Outstanding Balance</p>
                        <p class="text-sm font-bold text-slate-800">₦<?php echo number_format(($loanData['outstanding'] ?? ($loanData['active_loan_amount'] - $loanData['total_paid'])), 2); ?></p>
                    </div>
                </div>

                <!-- Participation -->
                <div class="glass-card p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Participation</p>
                            <h3 class="text-2xl font-bold text-slate-900"><?php echo $savingsData['savings_months']; ?> <span class="text-sm font-normal text-slate-500">months</span></h3>
                        </div>
                        <div class="p-3 rounded-lg bg-indigo-50 text-indigo-600">
                            <i class="fas fa-calendar-check text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-slate-500">
                        <span class="<?php echo $savingsData['savings_months'] >= $minSavingsMonths ? 'text-emerald-600' : 'text-amber-600'; ?> font-medium">
                            <i class="fas <?php echo $savingsData['savings_months'] >= $minSavingsMonths ? 'fa-check' : 'fa-exclamation-circle'; ?> mr-1"></i>
                            <?php echo $savingsData['savings_months'] >= $minSavingsMonths ? 'Meets requirements' : 'Below requirement'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content Column -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Quick Actions -->
                    <div class="glass-card p-6">
                         <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                            <i class="fas fa-bolt text-amber-500 mr-2"></i> Quick Actions
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php 
                            $canApplyForLoan = $membershipMonths >= $businessConfig['min_membership_months'] 
                                            && $loanData['active_loans'] < $businessConfig['max_active_loans'] 
                                            && !$hasOverdue 
                                            && ($savingsData['savings_months'] >= $minSavingsMonths);
                            ?>
                            
                            <!-- Apply for Loan -->
                             <div class="border border-slate-100 rounded-xl p-4 hover:border-blue-200 transition-colors">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="agreeInterest">
                                    <label class="form-check-label text-sm text-slate-600 cursor-pointer" for="agreeInterest">
                                        I agree to the <strong><?php echo number_format($defaultInterestRate, 1); ?>%</strong> interest rate
                                    </label>
                                </div>
                                <a id="applyLoanBtn" href="#" class="block w-full py-2.5 px-4 bg-slate-200 text-slate-500 rounded-lg text-center font-semibold text-sm transition-all pointer-events-none opacity-60">
                                    Apply for Loan
                                </a>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const agree = document.getElementById('agreeInterest');
                                        const btn = document.getElementById('applyLoanBtn');
                                        const canApply = <?php echo $canApplyForLoan ? 'true' : 'false'; ?>;
                                        
                                        if(agree) {
                                            agree.addEventListener('change', function() {
                                                if(this.checked && canApply) {
                                                    btn.classList.remove('bg-slate-200', 'text-slate-500', 'pointer-events-none', 'opacity-60');
                                                    btn.classList.add('bg-primary-600', 'text-white', 'hover:bg-primary-700', 'shadow-md');
                                                    btn.href = 'member_loan_application_business_rules.php';
                                                } else {
                                                    btn.classList.add('bg-slate-200', 'text-slate-500', 'pointer-events-none', 'opacity-60');
                                                    btn.classList.remove('bg-primary-600', 'text-white', 'hover:bg-primary-700', 'shadow-md');
                                                    btn.href = '#';
                                                }
                                            });
                                        }
                                    });
                                </script>
                            </div>

                            <!-- Savings Action -->
                            <a href="member_savings.php" class="flex items-center p-4 border border-slate-100 rounded-xl hover:border-emerald-200 hover:bg-emerald-50/50 transition-all group">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mr-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-800 text-sm">Add Savings</h4>
                                    <p class="text-xs text-slate-500">Deposit to balance</p>
                                </div>
                            </a>

                            <!-- View Loans -->
                            <a href="member_loans.php" class="flex items-center p-4 border border-slate-100 rounded-xl hover:border-blue-200 hover:bg-blue-50/50 transition-all group">
                                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-800 text-sm">View Loans</h4>
                                    <p class="text-xs text-slate-500">Check status & history</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                     <div class="glass-card p-6">
                        <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                            <i class="fas fa-history text-slate-400 mr-2"></i> Recent Activity
                        </h3>
                        <div class="space-y-4">
                            <?php if (!empty($recentActivity)): ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-100">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full <?php echo $activity['type'] === 'savings_deposit' ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-600'; ?> flex items-center justify-center mr-4">
                                                <i class="fas <?php echo $activity['type'] === 'savings_deposit' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo date('M j, Y • h:i A', strtotime($activity['created_at'])); ?></p>
                                            </div>
                                        </div>
                                        <span class="font-bold <?php echo $activity['type'] === 'savings_deposit' ? 'text-emerald-600' : 'text-slate-700'; ?>">
                                            <?php echo $activity['type'] === 'savings_deposit' ? '+' : '-'; ?>₦<?php echo number_format($activity['amount'], 2); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-slate-400">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p class="text-sm">No recent activity found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Sidebar Content -->
                <div class="space-y-6">
                    <!-- Eligibility Status Card -->
                     <div class="glass-card p-6">
                        <h3 class="text-lg font-bold text-slate-900 mb-4">Eligibility Status</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600"><i class="fas fa-user-clock mr-2 text-slate-400"></i>Membership</span>
                                <?php if ($membershipMonths >= $businessConfig['min_membership_months']): ?>
                                    <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded text-xs font-semibold">Eligible</span>
                                <?php else: ?>
                                    <span class="text-amber-600 bg-amber-50 px-2 py-1 rounded text-xs font-semibold"><?php echo $membershipMonths; ?>/<?php echo $businessConfig['min_membership_months']; ?> mo</span>
                                <?php endif; ?>
                            </div>
                             <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600"><i class="fas fa-wallet mr-2 text-slate-400"></i>Savings Pattern</span>
                                <?php if ($savingsData['savings_months'] >= $minSavingsMonths): ?>
                                    <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded text-xs font-semibold">Good</span>
                                <?php else: ?>
                                    <span class="text-amber-600 bg-amber-50 px-2 py-1 rounded text-xs font-semibold">Irregular</span>
                                <?php endif; ?>
                            </div>
                             <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600"><i class="fas fa-file-invoice mr-2 text-slate-400"></i>Loans</span>
                                <?php if ($loanData['active_loans'] < $businessConfig['max_active_loans']): ?>
                                    <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded text-xs font-semibold">Available</span>
                                <?php else: ?>
                                    <span class="text-red-600 bg-red-50 px-2 py-1 rounded text-xs font-semibold">Max Limit</span>
                                <?php endif; ?>
                            </div>
                             <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600"><i class="fas fa-history mr-2 text-slate-400"></i>History</span>
                                <?php if (!$hasOverdue): ?>
                                    <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded text-xs font-semibold">Clean</span>
                                <?php else: ?>
                                    <span class="text-red-600 bg-red-50 px-2 py-1 rounded text-xs font-semibold">Overdue</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pt-4 mt-2 border-t border-slate-100">
                                <div class="flex items-center justify-between font-bold">
                                    <span class="text-slate-800">Overall Status</span>
                                    <?php if ($canApplyForLoan): ?>
                                        <span class="text-emerald-600 flex items-center"><i class="fas fa-check-circle mr-1"></i> Eligible</span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-sm font-normal">Requirements not met</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Card -->
                    <div class="bg-primary-600 rounded-2xl p-6 text-white relative overflow-hidden">
                        <div class="relative z-10">
                            <h4 class="font-bold mb-2">Need Assistance?</h4>
                            <p class="text-primary-100 text-sm mb-4">Our support team is available to help you with your loan applications.</p>
                            <a href="../index.php#contact" class="inline-block bg-white text-primary-600 text-xs font-bold px-4 py-2 rounded-lg hover:bg-primary-50 transition-colors">Contact Support</a>
                        </div>
                        <div class="absolute -bottom-6 -right-6 text-primary-500 opacity-50">
                            <i class="fas fa-headset text-9xl"></i>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
    
    <!-- Bootstrap JS (Required for Header/Offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation Init (Optional if we add animations later) -->
    <script>
        // Simple entry animation
        document.addEventListener('DOMContentLoaded', () => {
             const items = document.querySelectorAll('.glass-card');
             items.forEach((item, index) => {
                 item.style.opacity = '0';
                 item.style.transform = 'translateY(20px)';
                 setTimeout(() => {
                     item.style.transition = 'all 0.6s ease-out';
                     item.style.opacity = '1';
                     item.style.transform = 'translateY(0)';
                 }, index * 100);
             });
        });
    </script>
</body>
</html>