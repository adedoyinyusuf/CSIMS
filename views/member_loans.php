<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$loanController = new LoanController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get member's loans
$member_loans = $loanController->getLoansByMemberId($member_id);
// Guard against non-array results
if (!is_array($member_loans) && !($member_loans instanceof Traversable)) {
    $member_loans = [];
}

// Detect schema variants and precompute repayment sums (schema-resilient)
$has_repayments = false;
$has_remaining_balance = false;
$has_amount_paid = false;
$has_total_repaid = false;
$has_principal_amount = false;
try {
    $chk = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
    if ($chk && $chk->num_rows > 0) { $has_repayments = true; }
    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'");
    if ($chk && $chk->num_rows > 0) { $has_remaining_balance = true; }
    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
    if ($chk && $chk->num_rows > 0) { $has_amount_paid = true; }
    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
    if ($chk && $chk->num_rows > 0) { $has_total_repaid = true; }
    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'principal_amount'");
    if ($chk && $chk->num_rows > 0) { $has_principal_amount = true; }
} catch (Exception $e) { /* ignore schema checks */ }

// Prefetch repayment sums by loan_id to avoid per-loan queries when possible
$repayment_sums_by_loan = [];
try {
    if ($has_repayments && !empty($member_loans)) {
        $loanIds = array_values(array_filter(array_map(function($l){
            return isset($l['loan_id']) ? (int)$l['loan_id'] : (isset($l['id']) ? (int)$l['id'] : 0);
        }, $member_loans), function($id){ return $id > 0; }));
        if (!empty($loanIds)) {
            $idList = implode(',', array_map('intval', $loanIds));
            $q = $conn->query("SELECT loan_id, SUM(amount) AS total FROM loan_repayments WHERE loan_id IN ($idList) GROUP BY loan_id");
            if ($q) {
                while ($row = $q->fetch_assoc()) {
                    $repayment_sums_by_loan[(int)$row['loan_id']] = (float)($row['total'] ?? 0);
                }
            }
        }
    }
} catch (Exception $e) { /* ignore prefetch errors */ }

// Calculate statistics (schema-resilient totals)
$total_loan_amount = 0.0;
$total_amount_paid = 0.0;
$active_loans = 0;
$completed_loans = 0;

foreach ($member_loans as $loan) {
    $principal = (float)($loan['amount'] ?? ($has_principal_amount ? ($loan['principal_amount'] ?? 0) : 0));
    $status = strtolower(trim($loan['status'] ?? ''));
    $loanId = isset($loan['loan_id']) ? (int)$loan['loan_id'] : (isset($loan['id']) ? (int)$loan['id'] : 0);

    // Compute paid in a schema-resilient way
    $paid = 0.0;
    if ($has_repayments && $loanId > 0 && isset($repayment_sums_by_loan[$loanId])) {
        $paid = (float)$repayment_sums_by_loan[$loanId];
    } elseif ($has_amount_paid && isset($loan['amount_paid'])) {
        $paid = (float)$loan['amount_paid'];
    } elseif ($has_total_repaid && isset($loan['total_repaid'])) {
        $paid = (float)$loan['total_repaid'];
    }

    $total_loan_amount += $principal;
    $total_amount_paid += $paid;

    if (in_array($status, ['approved', 'disbursed', 'active'])) {
        $active_loans++;
    } elseif ($status === 'paid') {
        $completed_loans++;
    }
}

$outstanding_balance = $total_loan_amount - $total_amount_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loan Portfolio - NPC CTLStaff Loan Society</title>
    <!-- Premium Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css?v=2.4">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css?v=2.4">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
                        emerald: { 50: '#ecfdf5', 100: '#d1fae5', 500: '#10b981', 600: '#059669', 700: '#047857' },
                        amber: { 50: '#fffbeb', 100: '#fef3c7', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        /* Reset manual button styles for Tailwind if preflight is off */
        button { border-style: solid; }
        .gradient-card-1 { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .gradient-card-2 { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .gradient-card-3 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(226, 232, 240, 0.8); }
        .progress-bar { transition: width 1s ease-in-out; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <?php include_once __DIR__ . '/includes/member_header.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Loan Portfolio</h1>
                <p class="text-slate-500 mt-1">Manage your active loans and track your repayment progress.</p>
            </div>
            <a href="member_loan_application_business_rules.php" class="inline-flex items-center justify-center px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-all shadow-lg hover:shadow-primary-500/30 transform hover:-translate-y-0.5">
                <i class="fas fa-plus-circle mr-2"></i> New Application
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <!-- Total Borrowed -->
            <div class="relative overflow-hidden rounded-2xl shadow-lg gradient-card-1 p-6 text-white">
                <div class="relative z-10">
                    <p class="text-blue-100 font-medium text-sm uppercase tracking-wider mb-1">Total Principal</p>
                    <h3 class="text-3xl font-bold">₦<?php echo number_format($total_loan_amount, 2); ?></h3>
                    <div class="mt-4 flex items-center text-blue-100 text-sm">
                        <span class="bg-white/20 px-2 py-1 rounded-lg backdrop-blur-sm mr-2">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> <?php echo count($member_loans); ?> Loans
                        </span>
                    </div>
                </div>
                <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4">
                    <i class="fas fa-wallet text-9xl"></i>
                </div>
            </div>

            <!-- Repayment Progress -->
            <div class="relative overflow-hidden rounded-2xl shadow-lg gradient-card-2 p-6 text-white">
                <div class="relative z-10">
                    <p class="text-emerald-100 font-medium text-sm uppercase tracking-wider mb-1">Amount Repaid</p>
                    <h3 class="text-3xl font-bold">₦<?php echo number_format($total_amount_paid, 2); ?></h3>
                    
                    <?php 
                        $overallProgress = ($total_loan_amount > 0) ? ($total_amount_paid / $total_loan_amount) * 100 : 0;
                    ?>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-emerald-100 mb-1">
                            <span>Repayment Progress</span>
                            <span><?php echo number_format($overallProgress, 1); ?>%</span>
                        </div>
                        <div class="w-full bg-black/20 rounded-full h-1.5">
                            <div class="bg-white h-1.5 rounded-full" style="width: <?php echo min(100, $overallProgress); ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4">
                    <i class="fas fa-check-circle text-9xl"></i>
                </div>
            </div>

            <!-- Outstanding -->
            <div class="relative overflow-hidden rounded-2xl shadow-lg gradient-card-3 p-6 text-white">
                <div class="relative z-10">
                    <p class="text-amber-100 font-medium text-sm uppercase tracking-wider mb-1">Outstanding Balance</p>
                    <h3 class="text-3xl font-bold">₦<?php echo number_format($outstanding_balance, 2); ?></h3>
                    <div class="mt-4 flex items-center text-amber-100 text-sm">
                        <span class="bg-white/20 px-2 py-1 rounded-lg backdrop-blur-sm">
                            <i class="fas fa-clock mr-1"></i> Active: <?php echo $active_loans; ?>
                        </span>
                    </div>
                </div>
                <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4">
                    <i class="fas fa-chart-pie text-9xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Loans Section -->
        <div>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-800 flex items-center">
                    <i class="fas fa-layer-group text-primary-600 mr-2"></i> Active Loans
                </h2>
                <!-- Filter/Sort could go here -->
            </div>

            <?php if (empty($member_loans)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                        <i class="fas fa-search-dollar text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900">No Active Loans</h3>
                    <p class="text-slate-500 max-w-md mx-auto mt-2">You don't have any loan applications yet. Start your journey today by applying for a new facility.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($member_loans as $loan): 
                        $loanId = $loan['loan_id'] ?? $loan['id'] ?? 0;
                        $principal = (float)($loan['amount'] ?? 0);
                        // Recalculate specifics for this loan
                        $thisPaid = 0.0;
                         if ($has_repayments && $loanId > 0 && isset($repayment_sums_by_loan[$loanId])) {
                            $thisPaid = (float)$repayment_sums_by_loan[$loanId];
                        } elseif ($has_amount_paid && isset($loan['amount_paid'])) {
                            $thisPaid = (float)$loan['amount_paid'];
                        } elseif ($has_total_repaid && isset($loan['total_repaid'])) {
                            $thisPaid = (float)$loan['total_repaid'];
                        }
                        
                        $remaining = max(0, $principal - $thisPaid);
                        if ($has_remaining_balance && isset($loan['remaining_balance']) && $loan['remaining_balance'] > 0) {
                            $remaining = (float)$loan['remaining_balance'];
                        }
                        
                        $progress = ($principal > 0) ? (($principal - $remaining) / $principal) * 100 : 0;
                        $status = strtolower($loan['status'] ?? 'pending');
                        
                        // Badge Styles
                        $badgeClass = match($status) {
                            'approved', 'active' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
                            'disbursed' => 'bg-blue-100 text-blue-700 border-blue-200',
                            'rejected' => 'bg-red-100 text-red-700 border-red-200',
                            'paid' => 'bg-slate-100 text-slate-700 border-slate-200',
                            default => 'bg-gray-100 text-gray-700 border-gray-200'
                        };
                    ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300">
                        <!-- Card Header -->
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">
                                    LN
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900"><?php echo htmlspecialchars($loan['purpose'] ?: 'Personal Loan'); ?></h4>
                                    <p class="text-xs text-slate-500 font-mono">ID: #<?php echo str_pad($loanId, 6, '0', STR_PAD_LEFT); ?></p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo $badgeClass; ?>">
                                <span class="w-1.5 h-1.5 rounded-full bg-current mr-2 animate-pulse"></span>
                                <?php echo ucfirst($status); ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Principal</p>
                                    <p class="text-lg font-bold text-slate-900 mt-1">₦<?php echo number_format($principal, 2); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Monthly Payment</p>
                                    <p class="text-lg font-bold text-slate-900 mt-1">₦<?php echo number_format((float)($loan['monthly_payment'] ?? 0), 2); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Duration</p>
                                    <p class="text-lg font-bold text-slate-900 mt-1"><?php echo $loan['term'] ?? $loan['term_months'] ?? 12; ?> Months</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Interest Rate</p>
                                    <p class="text-lg font-bold text-slate-900 mt-1"><?php echo number_format((float)($loan['interest_rate'] ?? 0), 1); ?>%</p>
                                </div>
                            </div>

                            <!-- Progress Section -->
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                <div class="flex justify-between items-end mb-2">
                                    <div>
                                        <span class="text-slate-900 font-bold text-sm">Repayment Progress</span>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            <span class="text-emerald-600 font-medium">₦<?php echo number_format($thisPaid, 2); ?></span> paid of ₦<?php echo number_format($principal, 2); ?>
                                        </p>
                                    </div>
                                    <span class="text-xl font-bold text-emerald-600"><?php echo number_format($progress, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-emerald-500 h-3 rounded-full relative progress-bar" style="width: <?php echo $progress; ?>%">
                                        <div class="absolute inset-0 bg-white/30 animate-[shimmer_2s_infinite]"></div>
                                    </div>
                                </div>
                                <p class="text-right text-xs text-slate-400 mt-2">Outstanding: ₦<?php echo number_format($remaining, 2); ?></p>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
                            <button onclick="viewPaymentSchedule(<?php echo $loanId; ?>)" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 hover:text-slate-800 transition-colors">
                                <i class="far fa-calendar-alt mr-2"></i> Schedule
                            </button>
                            <button onclick="viewLoanDetails(<?php echo $loanId; ?>)" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 shadow-sm hover:shadow transition-colors">
                                View Details <i class="fas fa-arrow-right ml-2 text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript Helpers -->
    <script>
        function viewLoanDetails(id) {
            window.location.href = 'member_loan_details.php?id=' + id;
        }
        function viewPaymentSchedule(id) {
            // Check if member_payment_schedule.php exists or fallback
            window.location.href = 'member_payment_schedule.php?id=' + id;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
