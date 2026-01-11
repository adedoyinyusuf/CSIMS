<?php
/**
 * Admin - View Loan Details
 * 
 * This page displays detailed information about a specific loan application
 * including repayment history and actions for processing the loan.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../controllers/loan_controller.php';
require_once __DIR__ . '/../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize controllers
$loanController = new LoanController();
$memberController = new MemberController();

// Check if loan ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "Loan ID is required";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: loans.php');
    exit();
}

$loan_id = (int)$_GET['id'];

// Get loan details
$loan = $loanController->getLoanById($loan_id);

if (!$loan) {
    $_SESSION['flash_message'] = "Loan not found";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: loans.php');
    exit();
}

// Get loan repayments
$repayments = $loanController->getLoanRepayments($loan_id);

// Get loan statuses for display
$loanStatuses = $loanController->getLoanStatuses();

// Get payment methods
$paymentMethods = $loanController->getPaymentMethods();

// Calculate loan summary using schema-resilient logic
$loanAmount = (float)($loan['amount'] ?? ($loan['principal_amount'] ?? ($loan['total_amount'] ?? 0)));

// Detect available columns
$has_amount_paid = false; $has_total_repaid = false; $has_remaining_balance = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
    if ($col && $col->num_rows > 0) { $has_amount_paid = true; }
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
    if ($col && $col->num_rows > 0) { $has_total_repaid = true; }
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'");
    if ($col && $col->num_rows > 0) { $has_remaining_balance = true; }
} catch (Exception $e) { /* ignore schema detection errors */ }

// Compute total paid preferring schema columns, else sum repayments
$totalPaid = 0.0;
if ($has_amount_paid && isset($loan['amount_paid'])) {
    $totalPaid = (float)$loan['amount_paid'];
} elseif ($has_total_repaid && isset($loan['total_repaid'])) {
    $totalPaid = (float)$loan['total_repaid'];
} else {
    $repayment_total = 0.0;
    if (!empty($repayments) && is_array($repayments)) {
        foreach ($repayments as $r) {
            $amt = null;
            if (isset($r['amount'])) { $amt = $r['amount']; }
            elseif (isset($r['payment_amount'])) { $amt = $r['payment_amount']; }
            elseif (isset($r['paid_amount'])) { $amt = $r['paid_amount']; }
            elseif (isset($r['repayment_amount'])) { $amt = $r['repayment_amount']; }
            if ($amt !== null) { $repayment_total += (float)$amt; }
        }
    }
    $totalPaid = (float)$repayment_total;
}

// Prefer remaining_balance when present, else compute from amount and paid
$remainingBalance = ($has_remaining_balance && isset($loan['remaining_balance']))
    ? (float)$loan['remaining_balance']
    : max(0.0, $loanAmount - $totalPaid);

$percentPaid = ($loanAmount > 0) ? (($totalPaid / $loanAmount) * 100) : 0;

// Calculate due date based on disbursement date and term
$due_date = null;
if (!empty($loan['disbursement_date']) && !empty($loan['term'])) {
    $disbursement_date = new DateTime($loan['disbursement_date']);
    $disbursement_date->add(new DateInterval('P' . $loan['term'] . 'M')); // Add months
    $due_date = $disbursement_date->format('Y-m-d');
}

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Extract guarantor information from notes if present
$guarantor_info = '';
$admin_notes = $loan['notes'] ?? '';
if (!empty($loan['notes'])) {
    // Check if notes contain guarantor information
    if (strpos($loan['notes'], 'Guarantor:') !== false) {
        $parts = explode("\n\n", $loan['notes']);
        foreach ($parts as $part) {
            if (strpos($part, 'Guarantor:') !== false) {
                $guarantor_info = trim(str_replace('Guarantor:', '', $part));
                break;
            }
        }
        // Extract admin notes (everything after "Admin Notes:")
        if (strpos($loan['notes'], 'Admin Notes:') !== false) {
            $admin_parts = explode('Admin Notes:', $loan['notes']);
            $admin_notes = isset($admin_parts[1]) ? trim($admin_parts[1]) : '';
        } else {
            $admin_notes = '';
        }
    }
}

// Page title
$pageTitle = "Loan Details #" . $loan_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?> - CSIMS</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f4ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81'
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 font-sans">
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include_once '../includes/header.php'; ?>
            
            <!-- Main Content -->
            <main id="mainContent" class="flex-1 md:ml-64 mt-16 p-6 bg-gray-50 overflow-x-hidden">
                <div class="max-w-5xl mx-auto">
                    <!-- Page Header -->
                    <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl mb-8 shadow-lg">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold mb-2">
                                    <i class="fas fa-file-invoice-dollar mr-4"></i><?php echo $pageTitle; ?>
                                </h1>
                                <p class="text-primary-100 text-lg">View detailed loan information and manage loan status</p>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" class="bg-white text-primary-600 px-6 py-3 rounded-xl font-semibold hover:bg-primary-50 transition-all duration-200 shadow-md hover:shadow-lg" onclick="window.print()">
                                    <i class="fas fa-print mr-2"></i>Print
                                </button>
                                <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="border-2 border-white text-white px-6 py-3 rounded-xl font-semibold hover:bg-white hover:text-primary-600 transition-all duration-200">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to Loans
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Breadcrumb -->
                    <nav class="flex mb-8" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                    <i class="fas fa-home mr-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">Loans</a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <span class="text-sm font-medium text-gray-500">View Loan #<?php echo $loan_id; ?></span>
                                </div>
                            </li>
                        </ol>
                    </nav>
            
            <!-- Flash messages -->
            <?php include_once __DIR__ . '/../includes/flash_messages.php'; ?>
            
            <!-- Loan Status Badge -->
            <?php 
                // Safe date formatter to prevent deprecated strtotime(null) warnings
                $formatDisplayDate = function($dateStr) {
                    if (empty($dateStr)) { return null; }
                    $ts = strtotime($dateStr);
                    return ($ts !== false) ? date('M d, Y', $ts) : null;
                };
                // Safe month-year formatter for strings like YYYY-MM
                $formatMonthYear = function($monthStr) {
                    if (empty($monthStr)) { return null; }
                    $ts = strtotime($monthStr . '-01');
                    return ($ts !== false) ? date('F Y', $ts) : null;
                };
            ?>
            <div class="mb-8">
                <div class="bg-<?php 
                    echo match($loan['status']) {
                        'Pending' => 'yellow-50 border-yellow-200',
                        'Approved' => 'blue-50 border-blue-200',
                        'Rejected' => 'red-50 border-red-200',
                        'Disbursed' => 'indigo-50 border-indigo-200',
                        'Paid' => 'green-50 border-green-200',
                        default => 'gray-50 border-gray-200'
                    };
                ?> rounded-2xl shadow-lg p-6 border">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-<?php 
                            echo match($loan['status']) {
                                'Pending' => 'clock',
                                'Approved' => 'check-circle',
                                'Rejected' => 'times-circle',
                                'Disbursed' => 'coins',
                                'Paid' => 'check-circle',
                                default => 'info-circle'
                            };
                        ?> text-<?php 
                            echo match($loan['status']) {
                                'Pending' => 'yellow-600',
                                'Approved' => 'blue-600',
                                'Rejected' => 'red-600',
                                'Disbursed' => 'indigo-600',
                                'Paid' => 'green-600',
                                default => 'gray-600'
                            };
                        ?> text-2xl mr-3"></i>
                        <h4 class="text-xl font-semibold text-<?php 
                            echo match($loan['status']) {
                                'Pending' => 'yellow-800',
                                'Approved' => 'blue-800',
                                'Rejected' => 'red-800',
                                'Disbursed' => 'indigo-800',
                                'Paid' => 'green-800',
                                default => 'gray-800'
                            };
                        ?>">Loan Status: <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?></h4>
                    </div>
                    <?php if ($loan['status'] === 'Pending'): ?>
                        <p class="text-yellow-700 mb-4">This loan application is awaiting approval.</p>
                        <div class="border-t border-yellow-200 pt-4">
                            <div class="flex gap-3 flex-wrap">
                                <button type="button" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg flex items-center" onclick="openModal('approveModal')">
                                    <i class="fas fa-check-circle mr-2"></i>Approve
                                </button>
                                <button type="button" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg flex items-center" onclick="openModal('rejectModal')">
                                    <i class="fas fa-times-circle mr-2"></i>Reject
                                </button>
                                <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg inline-flex items-center">
                                    <i class="fas fa-eye mr-2"></i>View Details
                                </a>
                                <a href="<?php echo BASE_URL; ?>/views/admin/edit_loan.php?id=<?php echo $loan_id; ?>" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg inline-flex items-center">
                                    <i class="fas fa-edit mr-2"></i>Edit Application
                                </a>
                                <button type="button" class="px-6 py-3 border-2 border-red-600 text-red-600 hover:bg-red-600 hover:text-white font-semibold rounded-xl transition-all duration-200 flex items-center" onclick="openModal('deleteModal')">
                                    <i class="fas fa-trash mr-2"></i>Delete Application
                                </button>
                            </div>
                        </div>
                    <?php elseif ($loan['status'] === 'Approved'): ?>
                        <?php $approvedOn = $formatDisplayDate($loan['approval_date'] ?? null); ?>
                        <p class="text-blue-700 mb-4">
                            <?php if ($approvedOn): ?>
                                This loan has been approved on <?php echo $approvedOn; ?> and is awaiting disbursement.
                            <?php else: ?>
                                This loan has been approved and is awaiting disbursement.
                            <?php endif; ?>
                        </p>
                        <div class="border-t border-blue-200 pt-4">
                            <div class="flex gap-3">
                                <a href="<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=<?php echo $loan_id; ?>&action=disburse" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg inline-flex items-center">
                                    <i class="fas fa-hand-holding-usd mr-2"></i>Mark as Disbursed
                                </a>
                            </div>
                        </div>
                    <?php elseif (in_array($loan['status'], ['Disbursed', 'Paid'])): ?>
                        <?php $disbursedOn = $formatDisplayDate($loan['disbursement_date'] ?? null); ?>
                        <p class="text-indigo-700 mb-4">
                            <?php if ($disbursedOn): ?>
                                This loan is active. Disbursed on <?php echo $disbursedOn; ?>.
                            <?php else: ?>
                                This loan is active.
                            <?php endif; ?>
                        </p>
                        <div class="border-t border-indigo-200 pt-4">
                            <div class="flex gap-3">
                                <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan_id; ?>" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg inline-flex items-center">
                                    <i class="fas fa-plus-circle mr-2"></i>Add Repayment
                                </a>
                            </div>
                        </div>
                    <?php elseif ($loan['status'] === 'paid'): ?>
                        <?php $lastPaidOn = $formatDisplayDate($loan['last_payment_date'] ?? null); ?>
                        <p class="text-green-700 mb-4">
                            <?php if ($lastPaidOn): ?>
                                This loan has been fully paid off. Last payment on <?php echo $lastPaidOn; ?>.
                            <?php else: ?>
                                This loan has been fully paid off.
                            <?php endif; ?>
                        </p>
                    <?php elseif ($loan['status'] === 'rejected'): ?>
                        <p class="text-red-700 mb-4">This loan application was rejected.</p>
                        <div class="border-t border-red-200 pt-4">
                            <div class="flex gap-3">
                                <button type="button" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg flex items-center" onclick="openModal('deleteModal')">
                                    <i class="fas fa-trash mr-2"></i>Delete Application
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <!-- Loan Details -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden">
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-file-text mr-3 text-primary-600"></i>
                                Loan Details
                            </h5>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-6">
                                    <div class="loan-details-section">
                                        <div class="bg-gray-50 p-4 rounded-xl">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-hashtag text-primary-600 mr-2"></i>Loan ID
                                            </label>
                                            <div class="text-lg font-bold text-primary-600">#<?php echo $loan_id; ?></div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-xl">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-dollar-sign text-green-600 mr-2"></i>Amount
                                            </label>
                                            <div class="text-xl font-bold text-green-600">₦<?php echo number_format($loanAmount, 2); ?></div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-xl">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Term
                                            </label>
                                            <div class="text-lg font-semibold text-gray-800"><?php echo $loan['term']; ?> months</div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-xl">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-percentage text-yellow-600 mr-2"></i>Interest Rate
                                            </label>
                                            <div class="text-lg font-semibold text-gray-800"><?php echo $loan['interest_rate']; ?>%</div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-xl">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-calendar text-gray-600 mr-2"></i>Application Date
                                            </label>
                                            <div class="text-lg font-medium text-gray-800"><?php echo $formatDisplayDate($loan['application_date'] ?? null) ?? 'N/A'; ?></div>
                                        </div>
                                        
                                        <!-- Additional Member Information -->
                                        <?php if (!empty($loan['savings'])): ?>
                                        <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                                            <label class="block text-sm font-medium text-blue-700 mb-1">
                                                <i class="fas fa-piggy-bank text-blue-600 mr-2"></i>Member's Savings
                                            </label>
                                            <div class="text-lg font-bold text-blue-800">₦<?php echo number_format($loan['savings'], 2); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($loan['month_deduction_started'])): ?>
                                        <div class="bg-green-50 p-4 rounded-xl border border-green-200">
                                            <label class="block text-sm font-medium text-green-700 mb-1">
                                                <i class="fas fa-calendar-plus text-green-600 mr-2"></i>Deduction Start Month
                                            </label>
                                            <div class="text-lg font-medium text-green-800"><?php echo $formatMonthYear($loan['month_deduction_started'] ?? null) ?? 'N/A'; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($loan['month_deduction_end'])): ?>
                                        <div class="bg-red-50 p-4 rounded-xl border border-red-200">
                                            <label class="block text-sm font-medium text-red-700 mb-1">
                                                <i class="fas fa-calendar-minus text-red-600 mr-2"></i>Deduction End Month
                                            </label>
                                            <div class="text-lg font-medium text-red-800"><?php echo $formatMonthYear($loan['month_deduction_end'] ?? null) ?? 'N/A'; ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Additional Member-Submitted Information Section -->
                                    <div class="bg-gray-50 p-4 rounded-xl mt-6">
                                        <h6 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                            <i class="fas fa-user-edit text-purple-600 mr-2"></i>Member-Submitted Details
                                        </h6>
                                        
                                        <?php if (!empty($loan['other_payment_plans'])): ?>
                                        <div class="bg-white p-4 rounded-xl mb-3 border border-gray-200">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-credit-card text-purple-600 mr-2"></i>Other Payment Plans
                                            </label>
                                            <div class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($loan['other_payment_plans'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($loan['remarks'])): ?>
                                        <div class="bg-white p-4 rounded-xl border border-gray-200">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-comment text-gray-600 mr-2"></i>Member's Remarks
                                            </label>
                                            <div class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($loan['remarks'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($loan['collateral'])): ?>
                                        <div class="bg-white p-4 rounded-xl mt-3 border border-gray-200">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-shield-alt text-green-600 mr-2"></i>Collateral Offered
                                            </label>
                                            <div class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($loan['collateral'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($loan['guarantor'])): ?>
                                        <div class="bg-white p-4 rounded-xl mt-3 border border-gray-200">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                                <i class="fas fa-user-shield text-blue-600 mr-2"></i>Guarantor Information
                                            </label>
                                            <div class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($loan['guarantor'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    </div>
                                <div class="space-y-6">
                                    <!-- Monthly Payment Card -->
                                    <div class="bg-gradient-to-br from-emerald-50 to-green-50 p-6 rounded-xl border-2 border-emerald-200 shadow-sm hover:shadow-md transition-shadow">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="p-3 bg-emerald-500 rounded-xl shadow-md">
                                                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-emerald-700 uppercase tracking-wide">Monthly Payment</p>
                                                    <p class="text-3xl font-bold text-emerald-900">₦<?php echo number_format($loan['monthly_payment'] ?? 0, 2); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Due Date Card -->
                                    <div class="bg-gradient-to-br from-sky-50 to-blue-50 p-6 rounded-xl border-2 border-sky-200 shadow-sm hover:shadow-md transition-shadow">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="p-3 bg-sky-500 rounded-xl shadow-md">
                                                    <i class="fas fa-calendar-check text-white text-2xl"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-sky-700 uppercase tracking-wide">Due Date</p>
                                                    <p class="text-xl font-bold text-sky-900"><?php echo $formatDisplayDate($due_date ?? null) ?? 'Not set'; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Card -->
                                    <div class="bg-gradient-to-br from-<?php 
                                        echo match($loan['status']) {
                                            'Pending' => 'yellow',
                                            'Approved' => 'blue',
                                            'Rejected' => 'red',
                                            'Disbursed' => 'indigo',
                                            'Paid' => 'green',
                                            default => 'gray'
                                        };
                                    ?>-50 to-<?php 
                                        echo match($loan['status']) {
                                            'Pending' => 'yellow',
                                            'Approved' => 'blue',
                                            'Rejected' => 'red',
                                            'Disbursed' => 'indigo',
                                            'Paid' => 'green',
                                            default => 'gray'
                                        };
                                    ?>-50 p-6 rounded-xl border-2 border-<?php 
                                        echo match($loan['status']) {
                                            'Pending' => 'yellow',
                                            'Approved' => 'blue',
                                            'Rejected' => 'red',
                                            'Disbursed' => 'indigo',
                                            'Paid' => 'green',
                                            default => 'gray'
                                        };
                                    ?>-200 shadow-sm hover:shadow-md transition-shadow">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="p-3 bg-<?php 
                                                    echo match($loan['status']) {
                                                        'Pending' => 'yellow',
                                                        'Approved' => 'blue',
                                                        'Rejected' => 'red',
                                                        'Disbursed' => 'indigo',
                                                        'Paid' => 'green',
                                                        default => 'gray'
                                                    };
                                                ?>-500 rounded-xl shadow-md">
                                                    <i class="fas fa-<?php 
                                                        echo match($loan['status']) {
                                                            'Pending' => 'clock',
                                                            'Approved' => 'check-circle',
                                                            'Rejected' => 'times-circle',
                                                            'Disbursed' => 'coins',
                                                            'Paid' => 'check-double',
                                                            default => 'flag'
                                                        };
                                                    ?> text-white text-2xl"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-<?php 
                                                        echo match($loan['status']) {
                                                            'Pending' => 'yellow',
                                                            'Approved' => 'blue',
                                                            'Rejected' => 'red',
                                                            'Disbursed' => 'indigo',
                                                            'Paid' => 'green',
                                                            default => 'gray'
                                                        };
                                                    ?>-700 uppercase tracking-wide">Status</p>
                                                    <p class="text-xl font-bold text-<?php 
                                                        echo match($loan['status']) {
                                                            'Pending' => 'yellow',
                                                            'Approved' => 'blue',
                                                            'Rejected' => 'red',
                                                            'Disbursed' => 'indigo',
                                                            'Paid' => 'green',
                                                            default => 'gray'
                                                        };
                                                    ?>-900"><?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Purpose Card -->
                                    <?php if (!empty($loan['purpose'])): ?>
                                    <div class="bg-gradient-to-br from-purple-50 to-violet-50 p-6 rounded-xl border-2 border-purple-200 shadow-sm hover:shadow-md transition-shadow">
                                        <div class="flex items-start space-x-3">
                                            <div class="p-3 bg-purple-500 rounded-xl shadow-md flex-shrink-0">
                                                <i class="fas fa-lightbulb text-white text-2xl"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-purple-700 uppercase tracking-wide mb-2">Loan Purpose</p>
                                                <p class="text-base text-purple-900 leading-relaxed"><?php echo htmlspecialchars($loan['purpose']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (in_array($loan['status'], ['disbursed', 'active', 'paid'])): ?>
                    <!-- Repayment Progress -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden slide-in">
                        <div class="bg-gradient-to-r from-green-50 to-green-100 px-6 py-4 border-b border-gray-200">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="bi bi-graph-up mr-2 text-green-600"></i>
                                Repayment Progress
                            </h5>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                                <div>
                                    <div>
                                        <div class="flex justify-between mb-2">
                                            <span class="text-gray-600">Progress</span>
                                            <span class="font-semibold text-<?php echo $percentPaid >= 100 ? 'green-600' : ($percentPaid >= 50 ? 'yellow-600' : 'blue-600'); ?>"><?php echo round($percentPaid); ?>%</span>
                                        </div>
                                        <div class="w-full h-8 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-8 rounded-full bg-<?php echo $percentPaid >= 100 ? 'green-500' : ($percentPaid >= 50 ? 'yellow-500' : 'blue-500'); ?> flex items-center justify-center text-white text-sm font-semibold" style="width: <?php echo min(100, $percentPaid); ?>%;">
                                                <?php echo round($percentPaid); ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="space-y-3">
                                        <div class="p-4 border border-gray-200 rounded-xl flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="p-3 rounded-full bg-green-100 mr-3">
                                                    <i class="bi bi-cash-stack text-green-600 text-xl"></i>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-gray-600 block">Total Paid</span>
                                                    <span class="font-bold text-green-700 text-xl">=N=<?php echo number_format($totalPaid, 2); ?></span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <small class="text-gray-500"><?php echo number_format(($totalPaid / $loanAmount) * 100, 1); ?>% of loan</small>
                                            </div>
                                        </div>
                                        <div class="p-4 border border-gray-200 rounded-xl flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="p-3 rounded-full <?php echo $remainingBalance > 0 ? 'bg-yellow-100' : 'bg-green-100'; ?> mr-3">
                                                    <i class="bi bi-hourglass-split <?php echo $remainingBalance > 0 ? 'text-yellow-600' : 'text-green-600'; ?> text-xl"></i>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-gray-600 block">Remaining Balance</span>
                                                    <span class="font-bold <?php echo $remainingBalance <= 0 ? 'text-green-700' : 'text-yellow-700'; ?> text-xl">=N=<?php echo number_format($remainingBalance, 2); ?></span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <small class="text-gray-500"><?php echo $remainingBalance > 0 ? number_format((($loanAmount - $remainingBalance) / $loanAmount) * 100, 1) . '% paid' : 'Fully paid'; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Repayment History -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden fade-in">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-blue-50 to-blue-100">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center mb-0">
                                <i class="bi bi-clock-history mr-2 text-blue-600"></i>
                                Repayment History
                            </h5>
                            <?php if (in_array($loan['status'], ['disbursed', 'active'])): ?>
                            <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan_id; ?>" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-sm text-sm inline-flex items-center gap-2">
                                <i class="bi bi-plus-circle"></i> Add Repayment
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <?php if (empty($repayments)): ?>
                                <p class="text-center text-gray-600">No repayments recorded yet.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Payment Method</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Receipt #</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <?php foreach ($repayments as $repayment): ?>
                                                <tr class="odd:bg-white even:bg-gray-50">
                                                    <td class="px-4 py-2 text-sm text-gray-800"><?php echo date('M d, Y', strtotime($repayment['payment_date'])); ?></td>
                                                    <td class="px-4 py-2 text-sm text-gray-800">=N=<?php echo number_format($repayment['amount'] ?? 0, 2); ?></td>
                                                    <td class="px-4 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($repayment['payment_method']); ?></td>
                                                    <td class="px-4 py-2 text-sm text-gray-800"><?php echo !empty($repayment['receipt_number']) ? htmlspecialchars($repayment['receipt_number']) : '-'; ?></td>
                                                    <td class="px-4 py-2 text-sm text-gray-800"><?php echo !empty($repayment['notes']) ? htmlspecialchars($repayment['notes']) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Member Information -->
                <div class="xl:col-span-1 slide-in">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-50 to-primary-100 px-6 py-4 border-b border-gray-200">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center mb-0">
                                <i class="bi bi-person-circle mr-2 text-primary-600"></i>
                                Member Information
                            </h5>
                        </div>
                        <div class="p-6">
                            <?php if ($member): ?>
                                <div class="text-center mb-3">
                                    <?php 
                                    // Check if photo exists and file is accessible
                                    $photo_path = !empty($member['photo']) ? BASE_URL . '/uploads/members/' . $member['photo'] : null;
                                    $photo_file_exists = !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/CSIMS/uploads/members/' . $member['photo']);
                                    ?>
                                    <?php if (!empty($member['photo']) && $photo_file_exists): ?>
                                        <img src="<?php echo $photo_path; ?>" 
                                             alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                             class="rounded-full mb-2 w-24 h-24 object-cover border-4 border-gray-200 mx-auto" >
                                    <?php else: ?>
                                        <div class="rounded-full bg-primary-600 flex items-center justify-center mx-auto mb-2 w-24 h-24 border-4 border-gray-200">
                                            <span class="text-white text-3xl font-bold">
                                                <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="mb-0 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                                    <p class="text-gray-500">Member ID: <?php echo $member['member_id']; ?></p>
                                </div>
                                
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-start justify-between py-2 border-b border-gray-100">
                                        <div class="font-medium text-gray-700"><i class="bi bi-envelope mr-2"></i> Email</div>
                                        <div class="text-gray-800 text-right break-words ml-4"><?php echo htmlspecialchars($member['email']); ?></div>
                                    </div>
                                    <div class="flex items-start justify-between py-2 border-b border-gray-100">
                                        <div class="font-medium text-gray-700"><i class="bi bi-telephone mr-2"></i> Phone</div>
                                        <div class="text-gray-800 text-right break-words ml-4"><?php echo htmlspecialchars($member['phone']); ?></div>
                                    </div>
                                    <div class="flex items-start justify-between py-2">
                                        <div class="font-medium text-gray-700"><i class="bi bi-geo-alt mr-2"></i> Address</div>
                                        <div class="text-gray-800 text-right break-words ml-4"><?php echo htmlspecialchars($member['address']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="grid gap-2 mt-3">
                                    <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="px-4 py-2 border border-blue-600 text-blue-600 rounded-xl hover:bg-blue-50 inline-flex items-center justify-center">
                                        View Full Profile
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-gray-600">Member information not available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    

                </div>
            </div>
        </main>
    </div>
</div>

<!-- Approve Loan Modal -->
<div id="approveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-2xl rounded-2xl bg-white">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <form method="POST" action="<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=<?php echo $loan_id; ?>">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>Approve Loan Application
                        </h3>
                        <button type="button" class="text-white hover:text-gray-200 transition-colors" onclick="closeModal('approveModal')">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-xl">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="font-semibold text-blue-800 mb-2">Loan Details:</h4>
                                <div class="text-blue-700">
                                    <p><strong>Amount:</strong> ₦<?php echo number_format($loanAmount, 2); ?></p>
                                    <p><strong>Term:</strong> <?php echo $loan['term']; ?> months</p>
                                    <p><strong>Monthly Payment:</strong> ₦<?php echo number_format($loan['monthly_payment'] ?? 0, 2); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-gray-700 mb-4">Are you sure you want to approve this loan application?</p>
                    
                    <div class="mb-6">
                        <label for="approve_notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Approval Notes (Optional)
                        </label>
                        <textarea 
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors" 
                            id="approve_notes" 
                            name="notes" 
                            rows="3" 
                            placeholder="Add any notes about the approval..."
                        ></textarea>
                    </div>
                    
                    <input type="hidden" name="loan_id" value="<?php echo $loan_id; ?>">
                    <input type="hidden" name="action" value="approve">
                </div>
                
                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button type="button" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors"
                            onclick="closeModal('approveModal')">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl transition-colors flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>Approve Loan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Loan Modal -->
<div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-2xl rounded-2xl bg-white">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <form method="POST" action="<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=<?php echo $loan_id; ?>">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-times-circle mr-3"></i>Reject Loan Application
                        </h3>
                        <button type="button" class="text-white hover:text-gray-200 transition-colors" onclick="closeModal('rejectModal')">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6">
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-xl">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="font-semibold text-yellow-800 mb-1">Warning:</h4>
                                <p class="text-yellow-700">This action will reject the loan application and cannot be easily undone.</p>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-gray-700 mb-4">Please provide a reason for rejecting this loan application:</p>
                    
                    <div class="mb-6">
                        <label for="reject_notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Rejection Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea 
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" 
                            id="reject_notes" 
                            name="notes" 
                            rows="4" 
                            placeholder="Please provide a clear reason for rejection..." 
                            required
                        ></textarea>
                        <p class="mt-2 text-sm text-red-600 hidden" id="reject_notes_error">
                            Please provide a reason for rejection.
                        </p>
                    </div>
                    
                    <input type="hidden" name="loan_id" value="<?php echo $loan_id; ?>">
                    <input type="hidden" name="action" value="reject">
                </div>
                
                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button type="button" 
                            class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors"
                            onclick="closeModal('rejectModal')">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-xl transition-colors flex items-center">
                        <i class="fas fa-times-circle mr-2"></i>Reject Loan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/3 shadow-2xl rounded-2xl bg-white">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-gray-700 to-gray-800 text-white px-6 py-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-trash-alt mr-3"></i>Confirm Delete
                    </h3>
                    <button type="button" class="text-white hover:text-gray-200 transition-colors" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-xl">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="font-semibold text-red-800 mb-1">Warning:</h4>
                            <p class="text-red-700">This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
                
                <p class="text-gray-700 mb-4">Are you sure you want to delete this loan application?</p>
                
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-xl">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-blue-700"><strong>Note:</strong> Only pending or rejected loan applications can be deleted.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                <button type="button" 
                        class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors"
                        onclick="closeModal('deleteModal')">
                    Cancel
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/delete_loan.php?id=<?php echo $loan_id; ?>" 
                   class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-xl transition-colors flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i>Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
    /* Override any conflicting styles */
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        background: none !important;
        min-height: auto !important;
        position: static !important;
        z-index: auto !important;
        overflow-x: visible !important;
        box-sizing: border-box;
    }
    
    .main-content::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            radial-gradient(circle at 25% 25%, rgba(102, 126, 234, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 75% 75%, rgba(118, 75, 162, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        background-size: 800px 800px, 600px 600px, 400px 400px;
        background-position: 0% 0%, 100% 100%, 50% 50%;
        animation: backgroundFloat 20s ease-in-out infinite;
        z-index: -1;
        pointer-events: none;
    }
    
    @keyframes backgroundFloat {
        0%, 100% {
            background-position: 0% 0%, 100% 100%, 50% 50%;
        }
        33% {
            background-position: 30% 20%, 80% 70%, 60% 40%;
        }
        66% {
            background-position: 70% 80%, 20% 30%, 40% 60%;
        }
    }
    
    /* Removed conflicting layout overrides for .main-content, Bootstrap rows/cols to rely on Tailwind spacing and sidebar offset */
    
    /* Page Header Styling */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.1) 75%, transparent 75%);
        background-size: 20px 20px;
        opacity: 0.3;
        pointer-events: none;
    }
    
    .page-header h1 {
        margin: 0;
        font-weight: 700;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        position: relative;
        z-index: 2;
    }
    
    @media (max-width: 576px) {
        .page-header {
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
        }
    }
    
    .breadcrumb {
        background: rgba(255,255,255,0.1);
        border-radius: 25px;
        padding: 0.5rem 1rem;
        margin-top: 1rem;
    }
    
    .breadcrumb-item a {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
    }
    
    .breadcrumb-item.active {
        color: white;
    }
    
    /* Enhanced Card Styling */
    .card {
        border: 1px solid rgba(0,0,0,0.08) !important;
        border-radius: 20px;
        box-shadow: 0 6px 25px rgba(0,0,0,0.1);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        background: rgba(255, 255, 255, 0.95);
        margin-bottom: 2rem;
        position: relative;
        backdrop-filter: blur(15px);
    }
    
    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-6px);
        box-shadow: 0 15px 45px rgba(0,0,0,0.2);
        border-color: rgba(102, 126, 234, 0.3) !important;
    }
    
    .card:hover::before {
        opacity: 1;
    }
    
    .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        border-bottom: 1px solid rgba(0,0,0,0.1) !important;
        padding: 1.75rem 2rem !important;
        border-radius: 20px 20px 0 0 !important;
        position: relative;
    }
    
    .card-header h5 {
        margin: 0;
        font-weight: 700;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.2rem;
    }
    
    .card-body {
        padding: 2rem;
    }
    
    /* Equal-height card styles removed to avoid interfering with Tailwind grid */
    
    @media (max-width: 576px) {
        .card {
            margin-bottom: 1rem;
            border-radius: 12px;
        }
        
        .card-header {
            padding: 1rem !important;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .card-body {
            padding: 1rem;
        }
    }
    
    /* Fix for last card spacing and alignment */
    .col-md-4 .card:last-child,
    .col-md-8 .card:last-child {
        margin-bottom: 3rem;
    }
    
    /* Ensure proper column spacing */
    .col-md-4,
    .col-md-8 {
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .col-md-4 .card:last-child,
        .col-md-8 .card:last-child {
            margin-bottom: 2.5rem;
        }
        
        .col-md-4,
        .col-md-8 {
            margin-bottom: 1.5rem;
        }
    }
    
    /* Bootstrap row spacing rules removed */
    
    /* Detail Items Enhancement */
    .detail-item {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        backdrop-filter: blur(15px);
        display: flex;
        flex-direction: column;
        min-height: 120px;
        justify-content: center;
    }
    
    .detail-item:hover {
        background: rgba(255, 255, 255, 1);
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        border-color: rgba(102, 126, 234, 0.3);
    }
    
    .detail-item:last-child {
        margin-bottom: 0;
    }
    
    .detail-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
        line-height: 1.2;
    }
    
    .detail-label i {
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }
    
    .detail-value {
        font-size: 1.1rem;
        color: #2c3e50;
        line-height: 1.4;
        font-weight: 600;
        word-break: break-word;
        flex-grow: 1;
        display: flex;
        align-items: center;
    }
    
    .loan-details-section {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .loan-details-section .detail-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    /* Grid Layout Enhancement */
    .row.g-4 {
        --bs-gutter-x: 2rem;
        --bs-gutter-y: 2rem;
    }
    
    @media (max-width: 768px) {
        .row.g-4 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }
        
        .detail-item {
            padding: 1.25rem;
            min-height: 100px;
        }
        
        .detail-label {
            font-size: 0.7rem;
        }
        
        .detail-value {
            font-size: 1rem;
        }
    }
    
    /* Status Badge Enhancement */
    .alert {
        border-radius: 12px;
        border: 1px solid;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
        border-left: 4px solid;
        backdrop-filter: blur(10px);
        font-weight: 500;
    }
    
    .alert::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.9) 50%, rgba(255,255,255,0.4) 100%);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    .alert-success {
        background: linear-gradient(135deg, rgba(209, 242, 235, 0.95) 0%, rgba(163, 228, 215, 0.95) 100%);
        border-color: rgba(25, 135, 84, 0.3);
        border-left-color: #198754;
        box-shadow: 0 6px 20px rgba(26, 188, 156, 0.25);
        color: #0f5132;
    }
    
    .alert-warning {
        background: linear-gradient(135deg, rgba(254, 249, 231, 0.95) 0%, rgba(252, 243, 207, 0.95) 100%);
        border-color: rgba(255, 193, 7, 0.3);
        border-left-color: #ffc107;
        box-shadow: 0 6px 20px rgba(241, 196, 15, 0.25);
        color: #664d03;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, rgba(250, 219, 216, 0.95) 0%, rgba(245, 183, 177, 0.95) 100%);
        border-color: rgba(220, 53, 69, 0.3);
        border-left-color: #dc3545;
        box-shadow: 0 6px 20px rgba(231, 76, 60, 0.25);
        color: #721c24;
    }
    
    .alert-info {
        background: linear-gradient(135deg, rgba(214, 234, 248, 0.95) 0%, rgba(174, 214, 241, 0.95) 100%);
        border-color: rgba(13, 202, 240, 0.3);
        border-left-color: #0dcaf0;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.25);
        color: #055160;
    }
    
    .alert-primary {
        background: linear-gradient(135deg, rgba(204, 229, 255, 0.95) 0%, rgba(153, 214, 255, 0.95) 100%);
        border-color: rgba(13, 110, 253, 0.3);
        border-left-color: #0d6efd;
        box-shadow: 0 6px 20px rgba(13, 110, 253, 0.25);
        color: #052c65;
    }
    
    @media (max-width: 576px) {
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
    }
    
    /* Progress Bar Enhancement */
    .progress {
        height: 25px;
        border-radius: 20px;
        background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        box-shadow: inset 0 3px 6px rgba(0,0,0,0.15), 0 2px 4px rgba(0,0,0,0.05);
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    .progress::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, rgba(255,255,255,0.5), transparent);
        z-index: 2;
    }
    
    .progress-bar {
        border-radius: 20px;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, var(--success) 0%, var(--success) 100%);
        box-shadow: 0 2px 8px rgba(0, 75, 35, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    
    .progress-bar.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        color: #212529;
        text-shadow: none;
    }
    
    .progress-bar.bg-info {
        background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
    }
    
    .progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0.15) 75%, transparent 75%);
        background-size: 15px 15px;
        animation: progress-animation 2s linear infinite;
    }
    
    @keyframes progress-animation {
        0% { background-position: 0 0; }
        100% { background-position: 15px 0; }
    }
    
    .progress-container {
        background: rgba(255,255,255,0.9);
        padding: 2rem;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.08);
        backdrop-filter: blur(15px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 1rem;
    }
    
    .progress-container .d-flex {
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    
    .progress-container .text-muted {
        font-weight: 600;
        font-size: 0.9rem;
        color: #6c757d !important;
    }
    
    .progress-container .fw-bold {
        font-size: 1.1rem;
        font-weight: 700;
    }
    
    /* Button Enhancement */
    .btn {
        border-radius: 10px;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid transparent;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.4s;
    }
    
    .btn:hover::before {
        left: 100%;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    }
    
    .btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 3px 12px rgba(102, 126, 234, 0.4);
        color: white;
        border-color: rgba(102, 126, 234, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        box-shadow: 0 3px 12px rgba(40, 167, 69, 0.4);
        color: white;
        border-color: rgba(40, 167, 69, 0.3);
    }
    
    .btn-success:hover {
        background: linear-gradient(135deg, var(--success) 0%, var(--success) 100%);
        box-shadow: 0 6px 20px rgba(0, 75, 35, 0.5);
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        box-shadow: 0 3px 12px rgba(255, 193, 7, 0.4);
        color: #212529;
        border-color: rgba(255, 193, 7, 0.3);
    }
    
    .btn-warning:hover {
        background: linear-gradient(135deg, #e0a800 0%, #e8650e 100%);
        box-shadow: 0 6px 20px rgba(255, 193, 7, 0.5);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        box-shadow: 0 3px 12px rgba(220, 53, 69, 0.4);
        color: white;
        border-color: rgba(220, 53, 69, 0.3);
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.5);
    }
    
    .btn-light {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        color: #495057;
        border-color: rgba(0, 0, 0, 0.1);
    }
    
    .btn-light:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        color: #495057;
    }
    
    .btn-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        box-shadow: 0 3px 12px rgba(23, 162, 184, 0.4);
        color: white;
        border-color: rgba(23, 162, 184, 0.3);
    }
    
    .btn-info:hover {
        background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
        box-shadow: 0 6px 20px rgba(23, 162, 184, 0.5);
    }
    
    @media (max-width: 576px) {
        .btn {
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }
    }
    
    /* List Group Enhancement */
    .list-group-item {
        border: none;
        border-radius: 12px !important;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
        padding: 1rem 1.5rem;
    }
    
    .list-group-item:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    /* Payment Summary Enhancement */
    .payment-summary {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 16px;
        padding: 0;
        margin: 1rem 0;
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        backdrop-filter: blur(10px);
        overflow: hidden;
    }
    
    .payment-summary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: rgba(102, 126, 234, 0.2);
    }
    
    .summary-item {
        border-radius: 12px;
        background: rgba(255,255,255,0.7);
        margin-bottom: 0.75rem;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.03);
    }
    
    .summary-item:last-child {
        margin-bottom: 0;
    }
    
    .summary-item:hover {
        background: rgba(255,255,255,0.95);
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .icon-wrapper {
        transition: all 0.3s ease;
    }
    
    .summary-item:hover .icon-wrapper {
        transform: scale(1.1);
    }
    
    /* List Group Enhancement */
    .list-group {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.05);
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
    }
    
    .list-group-item {
        border: none;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        background: transparent;
        transition: all 0.3s ease;
        padding: 1rem 1.25rem;
        position: relative;
        overflow: hidden;
    }
    
    .list-group-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }
    
    .list-group-item:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: translateX(8px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        z-index: 2;
    }
    
    .list-group-item:hover::before {
        transform: scaleY(1);
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    .list-group-item .d-flex {
        align-items: center;
        gap: 0.75rem;
    }
    
    .list-group-item .badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    
    /* Icon Enhancement */
    .bi, .fas, .fa {
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
        transition: all 0.3s ease;
    }
    
    .list-group-item:hover .bi,
    .list-group-item:hover .fas,
    .list-group-item:hover .fa {
        transform: scale(1.1);
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }
    
    /* Modal Enhancement */
    .modal-content {
        border: none;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        backdrop-filter: blur(10px);
        background: rgba(255,255,255,0.95);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px 20px 0 0;
        border-bottom: none;
        padding: 1.5rem;
    }
    
    .modal-header .modal-title {
        font-weight: 700;
        font-size: 1.25rem;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }
    
    .modal-header .btn-close:hover {
        opacity: 1;
        transform: scale(1.1);
    }
    
    .modal-body {
        padding: 2rem;
    }
    
    .modal-footer {
        border-top: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem 2rem;
        background: rgba(248,249,250,0.5);
        border-radius: 0 0 20px 20px;
    }
    
    /* Form Enhancement */
    .form-control {
        border-radius: 12px;
        border: 2px solid rgba(0,0,0,0.1);
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        background: rgba(255,255,255,0.8);
        backdrop-filter: blur(5px);
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        background: white;
        transform: translateY(-1px);
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.75rem;
    }
    
    /* Responsive Modal */
    @media (max-width: 576px) {
        .modal-dialog {
            margin: 1rem;
        }
        
        .modal-content {
            border-radius: 16px;
        }
        
        .modal-header {
            padding: 1rem;
            border-radius: 16px 16px 0 0;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-radius: 0 0 16px 16px;
        }
    }
    
    /* Table Enhancement */
    .table-responsive {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(0,0,0,0.08);
    }
    
    .table {
        margin-bottom: 0;
        background: transparent;
    }
    
    .table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        font-weight: 700;
        padding: 1.5rem 1.25rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        position: relative;
        text-align: center;
        vertical-align: middle;
    }
    
    .table thead th:first-child {
        text-align: left;
    }
    
    .table thead th::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.8), rgba(255,255,255,0.3));
    }
    
    .table tbody td {
        padding: 1.25rem;
        border-color: rgba(0,0,0,0.08);
        font-weight: 500;
        vertical-align: middle;
        text-align: center;
        background: rgba(255,255,255,0.7);
    }
    
    .table tbody td:first-child {
        text-align: left;
        font-weight: 600;
    }
    
    .table tbody tr {
        transition: all 0.3s ease;
    }
    
    .table tbody tr:hover {
        background: rgba(102, 126, 234, 0.05);
    }
    
    .table tbody tr:hover td {
        background: rgba(102, 126, 234, 0.08);
        transform: translateX(2px);
    }
    
    .table-borderless th,
    .table-borderless td {
        border: none;
        padding: 0.75rem 0;
    }
    
    .table-borderless th {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
        width: 40%;
    }
    
    .table-borderless td {
        color: #2c3e50;
        font-weight: 500;
    }
    
    /* Animation Classes */
    .fade-in {
        animation: fadeIn 0.6s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .slide-in {
        animation: slideIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
</style>

<!-- Print Styles -->
<style media="print">
    .sidebar, .navbar, .btn, .breadcrumb, .alert .btn, .modal, .no-print {
        display: none !important;
    }
    
    main {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        margin-bottom: 20px !important;
        box-shadow: none !important;
        transform: none !important;
    }
    
    body {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    @page {
        margin: 1cm;
    }
</style>

<script>
// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    
    // Auto-focus on specific fields when modal opens
    setTimeout(() => {
        if (modalId === 'approveModal') {
            document.getElementById('approve_notes')?.focus();
        } else if (modalId === 'rejectModal') {
            document.getElementById('reject_notes')?.focus();
        }
    }, 100);
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
        const modals = ['approveModal', 'rejectModal', 'deleteModal'];
        modals.forEach(modalId => {
            if (event.target.id === modalId) {
                closeModal(modalId);
            }
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = ['approveModal', 'rejectModal', 'deleteModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && !modal.classList.contains('hidden')) {
                closeModal(modalId);
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Form validation for reject modal
    const rejectForm = document.querySelector('#rejectModal form');
    const rejectNotesField = document.getElementById('reject_notes');
    const rejectNotesError = document.getElementById('reject_notes_error');
    
    if (rejectForm) {
        rejectForm.addEventListener('submit', function(event) {
            if (!rejectNotesField.value.trim()) {
                event.preventDefault();
                event.stopPropagation();
                rejectNotesField.classList.add('border-red-500', 'ring-red-500');
                rejectNotesField.classList.remove('border-gray-300');
                if (rejectNotesError) rejectNotesError.classList.remove('hidden');
                return false;
            }
            rejectNotesField.classList.remove('border-red-500', 'ring-red-500');
            rejectNotesField.classList.add('border-gray-300');
            if (rejectNotesError) rejectNotesError.classList.add('hidden');
        });
        
        // Real-time validation
        rejectNotesField.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('border-red-500', 'ring-red-500');
                this.classList.add('border-gray-300');
                if (rejectNotesError) rejectNotesError.classList.add('hidden');
            } else {
                this.classList.add('border-red-500', 'ring-red-500');
                this.classList.remove('border-gray-300');
                if (rejectNotesError) rejectNotesError.classList.remove('hidden');
            }
        });
    }
    
    // Confirmation dialog for approval
    const approveForm = document.querySelector('#approveModal form');
    if (approveForm) {
        approveForm.addEventListener('submit', function(event) {
            if (!confirm('Are you sure you want to approve this loan application?')) {
                event.preventDefault();
                return false;
            }
        });
    }
});
</script>

                </div>
            </main>
        </div>
    </div>
</body>
</html>

