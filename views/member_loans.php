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
    <title>My Loans - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white">
    <?php include_once __DIR__ . '/includes/member_header.php'; ?>
    <div class="flex min-h-screen">
        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-money-bill-wave mr-3 text-primary-600"></i> My Enhanced Loans
                        </h1>
                        <p class="text-gray-600 mt-2">View your loans with detailed guarantor, collateral, and payment information</p>
                    </div>
                    <a href="member_loan_application_business_rules.php" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors duration-200 shadow-lg">
                        <i class="fas fa-plus-circle mr-2"></i> Apply for New Loan
                    </a>
                </div>
                
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-primary-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-primary-600 uppercase tracking-wider mb-2">Total Loans</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($total_loan_amount, 2); ?></p>
                            </div>
                            <div class="bg-primary-100 p-3 rounded-full">
                                <i class="fas fa-chart-line text-2xl text-primary-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Amount Paid</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($total_amount_paid, 2); ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-check-circle text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-yellow-600 uppercase tracking-wider mb-2">Outstanding</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($outstanding_balance, 2); ?></p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-exclamation-triangle text-2xl text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Active Loans</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $active_loans; ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-clock text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loans List -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-list mr-3 text-primary-600"></i> My Loan Applications
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($member_loans)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-money-bill-wave text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Loans Yet</h3>
                                <p class="text-gray-600 mb-6">You haven't applied for any loans yet. Click the button below to get started.</p>
                                <a href="member_loan_application_business_rules.php" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors duration-200">
                                    <i class="fas fa-plus-circle mr-2"></i> Apply for Your First Loan
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php 
                                    if (!is_array($member_loans)) { $member_loans = []; }
                                    foreach ($member_loans as $loan): 
                                        $loanId = isset($loan['loan_id']) ? (int)$loan['loan_id'] : (isset($loan['id']) ? (int)$loan['id'] : null);
                                        $principal = (float)($loan['amount'] ?? ($has_principal_amount ? ($loan['principal_amount'] ?? 0) : 0));
                                        // Paid detection
                                        $amountPaid = 0.0;
                                        if ($has_repayments && $loanId && isset($repayment_sums_by_loan[$loanId])) {
                                            $amountPaid = (float)$repayment_sums_by_loan[$loanId];
                                        } elseif ($has_amount_paid && isset($loan['amount_paid'])) {
                                            $amountPaid = (float)$loan['amount_paid'];
                                        } elseif ($has_total_repaid && isset($loan['total_repaid'])) {
                                            $amountPaid = (float)$loan['total_repaid'];
                                        }
                                        // Remaining detection prefers remaining_balance if available
                                        $remaining = null;
                                        if ($has_remaining_balance && isset($loan['remaining_balance'])) {
                                            $remaining = (float)$loan['remaining_balance'];
                                        } else {
                                            $remaining = max(0.0, $principal - $amountPaid);
                                        }
                                        // Progress percentage based on remaining vs principal when available
                                        $progressPct = ($principal > 0)
                                            ? round((($principal - $remaining) / $principal) * 100, 1)
                                            : 0.0;
                                        $status = strtolower(trim($loan['status'] ?? ''));
                                    // Get enhanced data for each loan
                                    $guarantors = $loanId ? $loanController->getLoanGuarantors($loanId) : [];
                                    $collaterals = $loanId ? $loanController->getLoanCollateral($loanId) : [];
                                    $paymentSchedule = $loanId ? $loanController->getLoanPaymentSchedule($loanId) : [];
                                    
                                    $totalGuaranteeAmount = array_sum(array_column($guarantors, 'guarantee_amount'));
                                    $totalCollateralValue = array_sum(array_column($collaterals, 'estimated_value'));
                                ?>
                                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                                        <!-- Loan Header -->
                                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center space-x-4">
                                                    <div>
                                                        <h4 class="text-lg font-semibold text-gray-900">Loan #<?php echo htmlspecialchars($loanId ?? ''); ?></h4>
                                                        <p class="text-sm text-gray-600">Applied: <?php 
                                                            $appDate = $loan['application_date'] ?? null;
                                                            $appTs = $appDate ? strtotime($appDate) : false;
                                                            echo $appTs ? date('M d, Y', $appTs) : 'N/A';
                                                        ?></p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($principal, 2); ?></p>
                                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($loan['term'] ?? ''); ?> months @ <?php echo number_format((float)($loan['interest_rate'] ?? 0), 1); ?>%</p>
                                                    </div>
                                                </div>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php 
                                                    echo match($status) {
                                                        'approved' => 'bg-green-100 text-green-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'rejected' => 'bg-red-100 text-red-800',
                                                        'disbursed' => 'bg-blue-100 text-blue-800',
                                                        'paid' => 'bg-gray-100 text-gray-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <i class="fas fa-circle w-2 h-2 mr-2"></i>
                                                    <?php echo $status ? ucfirst($status) : 'Unknown'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Loan Details -->
                                        <div class="p-6">
                                            <!-- Purpose -->
                                            <div class="mb-6">
                                                <h5 class="text-sm font-medium text-gray-500 mb-2">Purpose</h5>
                                                <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($loan['purpose'])); ?></p>
                                            </div>
                                            
                                            <!-- Enhanced Information Grid -->
                                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                                <!-- Guarantors -->
                                                <div class="bg-green-50 rounded-lg p-4">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <h5 class="font-semibold text-green-800 flex items-center">
                                                            <i class="fas fa-users mr-2"></i> Guarantors
                                                        </h5>
                                                        <span class="text-sm text-green-600 font-medium"><?php echo count($guarantors); ?></span>
                                                    </div>
                                                    <?php if (empty($guarantors)): ?>
                                                        <p class="text-sm text-green-600">No guarantors</p>
                                                    <?php else: ?>
                                                        <div class="space-y-2">
                                                            <?php foreach (array_slice($guarantors, 0, 2) as $guarantor): ?>
                                                                <div class="flex justify-between items-center text-sm">
                                                                    <span class="text-green-800"><?php echo htmlspecialchars($guarantor['first_name'] . ' ' . $guarantor['last_name']); ?></span>
                                                                    <span class="text-green-600 font-medium">₦<?php echo number_format($guarantor['guarantee_amount'], 0); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (count($guarantors) > 2): ?>
                                                                <p class="text-xs text-green-600">+<?php echo count($guarantors) - 2; ?> more</p>
                                                            <?php endif; ?>
                                                            <div class="border-t border-green-200 pt-2 mt-2">
                                                                <div class="flex justify-between items-center text-sm font-semibold text-green-800">
                                                                    <span>Total Guarantee:</span>
                                                                    <span>₦<?php echo number_format($totalGuaranteeAmount, 0); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Collateral -->
                                                <div class="bg-blue-50 rounded-lg p-4">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <h5 class="font-semibold text-blue-800 flex items-center">
                                                            <i class="fas fa-shield-alt mr-2"></i> Collateral
                                                        </h5>
                                                        <span class="text-sm text-blue-600 font-medium"><?php echo count($collaterals); ?></span>
                                                    </div>
                                                    <?php if (empty($collaterals)): ?>
                                                        <p class="text-sm text-blue-600">No collateral</p>
                                                    <?php else: ?>
                                                        <div class="space-y-2">
                                                            <?php foreach (array_slice($collaterals, 0, 2) as $collateral): ?>
                                                                <div class="flex justify-between items-center text-sm">
                                                                    <span class="text-blue-800"><?php echo ucfirst($collateral['collateral_type']); ?></span>
                                                                    <span class="text-blue-600 font-medium">₦<?php echo number_format($collateral['estimated_value'], 0); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (count($collaterals) > 2): ?>
                                                                <p class="text-xs text-blue-600">+<?php echo count($collaterals) - 2; ?> more</p>
                                                            <?php endif; ?>
                                                            <div class="border-t border-blue-200 pt-2 mt-2">
                                                                <div class="flex justify-between items-center text-sm font-semibold text-blue-800">
                                                                    <span>Total Value:</span>
                                                                    <span>₦<?php echo number_format($totalCollateralValue, 0); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Payment Info -->
                                                <div class="bg-slate-50 rounded-lg p-4">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <h5 class="font-semibold text-slate-800 flex items-center">
                                                            <i class="fas fa-calendar-alt mr-2"></i> Payments
                                                        </h5>
                                                        <span class="text-sm text-slate-600 font-medium"><?php echo count($paymentSchedule); ?></span>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <div class="flex justify-between items-center text-sm">
                                                            <span class="text-slate-800">Monthly Payment:</span>
                                                            <span class="text-slate-600 font-medium">₦<?php echo number_format((float)($loan['monthly_payment'] ?? 0), 2); ?></span>
                                                        </div>
                                                        <div class="flex justify-between items-center text-sm">
                                                            <span class="text-slate-800">Amount Paid:</span>
                                                            <span class="text-green-600 font-medium">₦<?php echo number_format($amountPaid, 2); ?></span>
                                                        </div>
                                                        <div class="flex justify-between items-center text-sm">
                                                            <span class="text-slate-800">Outstanding:</span>
                                                            <span class="text-red-600 font-medium">₦<?php echo number_format($remaining, 2); ?></span>
                                                        </div>
                                                        <!-- Repayment Progress -->
                                                        <div class="mt-2">
                                                            <div class="flex justify-between items-center text-xs text-slate-600 mb-1">
                                                                <span>Progress</span>
                                                                <span><?php echo $progressPct; ?>%</span>
                                                            </div>
                                                            <div class="w-full h-2 bg-slate-200 rounded">
                                                                <div class="h-2 bg-green-500 rounded" style="width: <?php echo max(0,min(100,$progressPct)); ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($loan['last_payment_date'])): ?>
                                                            <div class="border-t border-slate-200 pt-2 mt-2">
                                                                <p class="text-xs text-slate-600">Last Payment: <?php echo date('M d, Y', strtotime($loan['last_payment_date'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                                                <button onclick="viewLoanDetails(<?php echo json_encode($loanId); ?>)" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors duration-200">
                                                    <i class="fas fa-eye mr-2"></i> View Details
                                                </button>
                                                <?php if (!empty($paymentSchedule) && $loanId): ?>
                                                    <button onclick="viewPaymentSchedule(<?php echo json_encode($loanId); ?>)" class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg font-medium hover:bg-slate-700 transition-colors duration-200">
                                                        <i class="fas fa-calendar-alt mr-2"></i> Payment Schedule
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Details Modal -->
    <div id="loanDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-90vh overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-gray-900">Enhanced Loan Details</h3>
                        <button onclick="closeLoanDetailsModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div id="loanDetailsContent" class="p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewLoanDetails(loanId) {
            // Show modal
            document.getElementById('loanDetailsModal').classList.remove('hidden');
            
            // Load content via AJAX (you can implement this)
            document.getElementById('loanDetailsContent').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-4xl text-primary-600 mb-4"></i>
                    <p class="text-gray-600">Loading detailed loan information...</p>
                </div>
            `;
            
            // For now, redirect to a detailed view page
            setTimeout(() => {
                window.location.href = 'member_loan_details.php?id=' + loanId;
            }, 1000);
        }
        
        function viewPaymentSchedule(loanId) {
            // Redirect to payment schedule page
            window.location.href = 'member_payment_schedule.php?id=' + loanId;
        }
        
        function closeLoanDetailsModal() {
            document.getElementById('loanDetailsModal').classList.add('hidden');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
