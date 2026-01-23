<?php
/**
 * Admin - Loans Management
 * Re-designed Premium Layout
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/enhanced_loan_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';

// Session & Auth
$session = Session::getInstance();
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}
$current_user = $auth->getCurrentUser();

// Controllers
$loanController = class_exists('EnhancedLoanController') ? new EnhancedLoanController() : new LoanController();
$memberController = new MemberController();
$rulesService = new SimpleBusinessRulesService();

// Parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$loan_type = isset($_GET['loan_type']) ? trim($_GET['loan_type']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'application_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Fetch Loans
// Note: getAllLoans signature might vary, ensuring compatibility with previous file's usage
// Previous usage: getAllLoans($page, $per_page, $search, $sort_by, $sort_order, $status_filter, $loan_type, $amount_range);
$amount_range = isset($_GET['amount_range']) ? $_GET['amount_range'] : '';
$result = $loanController->getAllLoans($page, $per_page, $search, $sort_by, $sort_order, $status_filter, $loan_type, $amount_range);
$loans = $result['loans'] ?? [];
$pagination = $result['pagination'] ?? [];
$total_loans = $pagination['total_items'] ?? 0;
$total_pages = $pagination['total_pages'] ?? 1;

// Additional Data for Filters/Stats
$loanTypes = $loanController->getLoanTypes();
$loanStatuses = $loanController->getLoanStatuses(); // e.g. ['Active','Pending','Paid']

// Calculate Stats for Cards (Simplified logic for performance)
$loanStats = $loanController->getLoanStatistics(); 

// AJAX Handler
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    ob_end_clean();
    header('Content-Type: application/json');
    ob_start();

    // Render Table Rows
    if (count($loans) > 0) {
        foreach ($loans as $loan) {
            $loanId = (int)($loan['loan_id'] ?? $loan['id'] ?? 0);
            $memberName = trim(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? ''));
            $amount = (float)($loan['amount'] ?? 0);
            $paid = (float)($loan['total_repaid'] ?? $loan['amount_paid'] ?? 0); 
            // If total_repaid missing in view, use calc:
            $remaining = (float)($loan['remaining_balance'] ?? ($amount - $paid));
            $term = (int)($loan['term_months'] ?? 0);
            $interest = (float)($loan['interest_rate'] ?? 0);
            $status = ucfirst($loan['status'] ?? 'Pending');
            $date = date('M d, Y', strtotime($loan['application_date'] ?? 'now'));
            
            // Status Badges
            $badgeClass = match($status) {
                'Active', 'Approved', 'Disbursed' => 'bg-green-100 text-green-800',
                'Pending' => 'bg-yellow-100 text-yellow-800',
                'Paid' => 'bg-blue-100 text-blue-800',
                'Defaulted', 'Overdue' => 'bg-red-100 text-red-800',
                default => 'bg-gray-100 text-gray-800'
            };
            ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $loanId; ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                    <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 mr-3">
                            <i class="fas fa-user text-xs"></i>
                        </div>
                        <?php echo htmlspecialchars($memberName); ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($loan['loan_type'] ?? 'Personal'); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">₦<?php echo number_format($amount, 2); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">₦<?php echo number_format($paid, 2); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-500">₦<?php echo number_format($remaining, 2); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $term; ?> M</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $interest; ?>%</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                        <?php echo $status; ?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative z-10">
                    <div class="flex space-x-3">
                        <a href="process_loan.php?id=<?php echo $loanId; ?><?php echo ($status === 'Pending') ? '&action=approve' : ''; ?>" class="text-blue-600 hover:text-blue-900 relative z-20" title="Manage">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="view_loan.php?id=<?php echo $loanId; ?>" class="text-gray-400 hover:text-gray-600 relative z-20" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="11" class="px-6 py-12 text-center text-gray-500"><i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i><p>No loans found matching your criteria.</p></td></tr>';
    }
    $rows_html = ob_get_clean();

    // Render Pagination
    ob_start();
    if ($total_pages > 1): ?>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <a href="#" onclick="fetchResults(<?php echo max(1, $page - 1); ?>); return false;" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php 
            $range = 2;
            for ($i = 1; $i <= $total_pages; $i++): 
                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                    $activeClass = $i == $page ? 'bg-indigo-50 border-indigo-500 text-indigo-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50';
            ?>
                <a href="#" onclick="fetchResults(<?php echo $i; ?>); return false;" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo $activeClass; ?>">
                    <?php echo $i; ?>
                </a>
            <?php elseif ($i == $page - $range - 1 || $i == $page + $range + 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
            <?php endif; endfor; ?>
            <a href="#" onclick="fetchResults(<?php echo min($total_pages, $page + 1); ?>); return false;" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
        </nav>
    <?php endif;
    $pagination_html = ob_get_clean();

    echo json_encode(['rows' => $rows_html, 'pagination' => $pagination_html, 'total' => $total_loans]);
    exit();
}

// Separate logic for "Awaiting Disbursement"
// Use existing getAllLoans but filtering for 'Approved'
try {
    $awaitingResult = $loanController->getAllLoans(1, 20, '', 'application_date', 'ASC', 'Approved', '', '');
    $awaiting = $awaitingResult['loans'] ?? [];
} catch(Exception $e) { $awaiting = []; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css?v=2.4">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css?v=2.4">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
                }
            }
        }
    }
    </script>
    <style>
        .gradient-blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .gradient-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .gradient-orange { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .gradient-red { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex h-screen overflow-hidden">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 md:ml-64 transition-all duration-300 p-6">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Loans Management</h1>
                    <p class="text-gray-500">Track, manage and approve all loan applications</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <a href="<?php echo BASE_URL; ?>/views/admin/add_loan.php" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg font-medium text-white hover:bg-primary-700 shadow-sm transition-colors">
                        <i class="fas fa-plus mr-2"></i> New Loan
                    </a>
                    <button onclick="openImportModal()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">
                        <i class="fas fa-file-import mr-2"></i> Import
                    </button>
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Loans -->
                <div onclick="filterByStatus('')" class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-blue text-white cursor-pointer transition-transform hover:scale-105 active:scale-95">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Total Loans</p>
                        <h3 class="text-3xl font-bold mt-1"><?php echo number_format($loanStats['total_loans'] ?? 0); ?></h3>
                        <p class="text-xs opacity-70 mt-1">
                            Valued at ₦<?php echo number_format($loanStats['total_amount'] ?? 0); ?>
                        </p>
                    </div>
                    <i class="fas fa-hand-holding-usd absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>

                <!-- Pending -->
                <div onclick="filterByStatus('Pending')" class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-orange text-white cursor-pointer transition-transform hover:scale-105 active:scale-95">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Pending Review</p>
                        <h3 class="text-3xl font-bold mt-1"><?php echo number_format($loanStats['pending_count'] ?? 0); ?></h3>
                        <p class="text-xs opacity-70 mt-1">Applications awaiting approval</p>
                    </div>
                    <i class="fas fa-clock absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>

                <!-- Active/Disbursed -->
                <div onclick="filterByStatus('Active')" class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-green text-white cursor-pointer transition-transform hover:scale-105 active:scale-95">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Active Loans</p>
                        <h3 class="text-3xl font-bold mt-1"><?php echo number_format($loanStats['active_count'] ?? $loanStats['approved_count'] ?? 0); ?></h3>
                        <p class="text-xs opacity-70 mt-1">Currently running</p>
                    </div>
                    <i class="fas fa-check-circle absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>

                <!-- Overdue -->
                <div onclick="filterByStatus('Overdue')" class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-red text-white cursor-pointer transition-transform hover:scale-105 active:scale-95">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Default Risk</p>
                        <h3 class="text-3xl font-bold mt-1"><?php echo number_format($loanStats['overdue_count'] ?? 0); ?></h3>
                        <p class="text-xs opacity-70 mt-1">Overdue payments</p>
                    </div>
                    <i class="fas fa-exclamation-triangle absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>
            </div>

            <!-- Awaiting Disbursement (Conditional) -->
            <?php if (!empty($awaiting)): ?>
            <div class="mb-8 bg-white rounded-xl shadow-sm border border-orange-200 overflow-hidden">
                <div class="px-6 py-4 bg-orange-50 border-b border-orange-100 flex justify-between items-center">
                    <h3 class="font-semibold text-orange-800 flex items-center">
                        <i class="fas fa-hourglass-half mr-2"></i> Approved & Awaiting Disbursement
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach($awaiting as $ln): ?>
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-900">#<?php echo $ln['loan_id']; ?></td>
                                <td class="px-6 py-3 text-sm text-gray-700"><?php echo htmlspecialchars($ln['first_name'].' '.$ln['last_name']); ?></td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">₦<?php echo number_format($ln['amount'],2); ?></td>
                                <td class="px-6 py-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($ln['approval_date'])); ?></td>
                                <td class="px-6 py-3 text-sm">
                                    <a href="process_loan.php?id=<?php echo $ln['loan_id']; ?>&action=disburse" class="text-white bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-xs font-medium transition">Disburse Now</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600 mr-2">
                        <i class="fas fa-filter"></i>
                    </div>
                    Filter Loans
                </h3>
                <form id="loanFilterForm" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fas fa-search"></i></span>
                            <input type="text" id="search" class="pl-10 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" placeholder="Member Name, ID, Amount..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Statuses</option>
                            <?php foreach($loanStatuses as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php if($status_filter == $k) echo 'selected'; ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select id="loan_type" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Types</option>
                            <?php foreach($loanTypes as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php if($loan_type == $t['id']) echo 'selected'; ?>><?php echo $t['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select id="sort_by" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="application_date">Date</option>
                            <option value="amount">Amount</option>
                            <option value="status">Status</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Main Loan Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="font-semibold text-gray-900">All Loan Applications</h3>
                    <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2.5 py-0.5 rounded-full" id="totalCountBadge"><?php echo $total_loans; ?> Applications</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">%</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody id="loansTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Initial Load handled by PHP logic above using AJAX handler reused -->
                            <?php
                                // We reuse the logic from AJAX handler section manually for first render
                                if (count($loans) > 0) {
                                    foreach ($loans as $loan) {
                                        $loanId = (int)($loan['loan_id'] ?? $loan['id'] ?? 0);
                                        $memberName = trim(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? ''));
                                        $amount = (float)($loan['amount'] ?? 0);
                                        $paid = (float)($loan['total_repaid'] ?? $loan['amount_paid'] ?? 0); 
                                        $remaining = (float)($loan['remaining_balance'] ?? ($amount - $paid));
                                        $term = (int)($loan['term_months'] ?? 0);
                                        $interest = (float)($loan['interest_rate'] ?? 0);
                                        $status = ucfirst($loan['status'] ?? 'Pending');
                                        $date = date('M d, Y', strtotime($loan['application_date'] ?? 'now'));
                                        $badgeClass = match($status) {
                                            'Active', 'Approved' => 'bg-green-100 text-green-800',
                                            'Pending' => 'bg-yellow-100 text-yellow-800', 
                                            'Paid' => 'bg-blue-100 text-blue-800',
                                            'Defaulted', 'Overdue' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $loanId; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <div class="flex items-center">
                                                    <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 mr-3">
                                                        <i class="fas fa-user text-xs"></i>
                                                    </div>
                                                    <?php echo htmlspecialchars($memberName); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($loan['loan_type'] ?? 'Personal'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">₦<?php echo number_format($amount, 2); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">₦<?php echo number_format($paid, 2); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-500">₦<?php echo number_format($remaining, 2); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $term; ?> M</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $interest; ?>%</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-3">
                                                    <a href="process_loan.php?id=<?php echo $loanId; ?><?php echo ($status === 'Pending') ? '&action=approve' : ''; ?>" class="text-blue-600 hover:text-blue-900" title="Manage"><i class="fas fa-edit"></i></a>
                                                    <a href="view_loan.php?id=<?php echo $loanId; ?>" class="text-gray-400 hover:text-gray-600" title="View"><i class="fas fa-eye"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                echo '<tr><td colspan="11" class="px-6 py-12 text-center text-gray-500 mb-3"><i class="fas fa-inbox text-4xl text-gray-300"></i><p>No loan applications found.</p></td></tr>';
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between" id="paginationContainer">
                    <?php if ($total_pages > 1): ?>
                         <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <a href="#" onclick="fetchResults(<?php echo max(1, $page - 1); ?>); return false;" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                             <a href="#" onclick="fetchResults(<?php echo min($total_pages, $page + 1); ?>); return false;" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        let searchTimeout;

        function fetchResults(page = 1) {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status').value;
            const type = document.getElementById('loan_type').value;
            const sortBy = document.getElementById('sort_by').value;
            const tbody = document.getElementById('loansTableBody');

            tbody.style.opacity = '0.5';

            const params = new URLSearchParams({
                ajax: '1',
                page: page,
                search: search,
                status: status,
                loan_type: type,
                sort_by: sortBy,
                per_page: 15
            });

            fetch('loans.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = data.rows;
                    tbody.style.opacity = '1';
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('totalCountBadge').textContent = data.total + ' Applications';
                })
                .catch(err => {
                    console.error('Search failed', err);
                    tbody.style.opacity = '1';
                });
        }

        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => fetchResults(1), 300);
        });

        function filterByStatus(status) {
            // Set the dropdown value
            const statusSelect = document.getElementById('status');
            statusSelect.value = status;
            
            // Trigger fetch
            fetchResults(1);
            
            // Scroll to filters
            document.getElementById('loanFilterForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        ['status', 'loan_type', 'sort_by'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => fetchResults(1));
        });
    </script>
<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden transform transition-all">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">Import Loans</h3>
            <button onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <form action="import_loans_action.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="csv_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-400">CSV, XLS up to 10MB</p>
                            </div>
                            <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" required />
                        </label>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="closeImportModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium shadow-sm transition-colors">Import Loans</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openImportModal() {
        document.getElementById('importModal').classList.remove('hidden');
    }
    function closeImportModal() {
        document.getElementById('importModal').classList.add('hidden');
    }
    // Close on outside click
    document.getElementById('importModal').addEventListener('click', function(e) {
        if (e.target === this) closeImportModal();
    });
    
    // File input preview (optional simple feedback)
    document.getElementById('csv_file').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            // You could update the UI here to show filename
        }
    });
</script>
</body>
</html>
