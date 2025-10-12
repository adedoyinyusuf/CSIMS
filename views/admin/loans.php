<?php
/**
 * Admin - Loans Management
 * 
 * Enhanced loans management page with CSIMS color scheme, 
 * Phase 1&2 integrations, and comprehensive loan tracking.
 */

session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/enhanced_loan_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/services/NotificationService.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access the loans page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize controllers and services
$loanController = class_exists('EnhancedLoanController') ? new EnhancedLoanController() : new LoanController();
$memberController = new MemberController();
$notificationService = new NotificationService();
$businessRulesService = new SimpleBusinessRulesService();

// Get filter parameters with enhanced options
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$loan_type = isset($_GET['loan_type']) ? trim($_GET['loan_type']) : '';
$amount_range = isset($_GET['amount_range']) ? $_GET['amount_range'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'application_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;

// Get loans with enhanced filtering
$result = $loanController->getAllLoans($page, $per_page, $search, $sort_by, $sort_order, $status_filter, $loan_type, $amount_range);
$loans = $result['loans'] ?? [];
$pagination = $result['pagination'] ?? [];
$total_pages = $pagination['total_pages'] ?? 1;
$total_loans = $pagination['total_items'] ?? 0;

// Get comprehensive loan statistics
$loanStats = $loanController->getLoanStatistics();
$loanTypes = $loanController->getLoanTypes();
$loanStatuses = $loanController->getLoanStatuses();

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get business rules alerts for loans
$loan_alerts = $businessRulesService->getLoanAlerts();

// Page title
$pageTitle = "Loans Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <!-- Tailwind CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
</head>

<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header with Loan Statistics -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
                    <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-hand-holding-usd mr-3" style="color: var(--persian-orange);"></i>
                        Loans Management
                    </h1>
                    <p style="color: var(--text-muted);">Track, manage and approve all loan applications</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="<?php echo BASE_URL; ?>/views/admin/add_loan.php" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i> New Loan Application
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="openLoanImportModal()">
                        <i class="fas fa-file-import mr-2"></i> Import Loans
                    </button>
                    <button type="button" class="btn btn-outline" onclick="exportLoans()">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline" onclick="printLoans()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Loan Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Loans</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($total_loans); ?></p>
                                <p class="text-xs" style="color: var(--success);">₦<?php echo number_format($loanStats['total_amount'] ?? 0, 2); ?></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-hand-holding-usd text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--persian-orange);">Pending Applications</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($loanStats['pending_count'] ?? 0); ?></p>
                                <p class="text-xs" style="color: var(--warning);">Awaiting Review</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--persian-orange) 0%, var(--jasper) 100%);">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--success);">Approved Loans</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($loanStats['approved_count'] ?? 0); ?></p>
                                <p class="text-xs" style="color: var(--success);">₦<?php echo number_format($loanStats['approved_amount'] ?? 0, 2); ?></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--persian-orange) 0%, var(--success) 100%);">
                                <i class="fas fa-check-circle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--jasper);">Default Risk</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($loanStats['overdue_count'] ?? 0); ?></p>
                                <p class="text-xs" style="color: var(--danger);">Need Attention</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--jasper) 0%, var(--fire-brick) 100%);">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Flash Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3" style="color: var(--success);"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3" style="color: var(--error);"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Business Rules Alerts for Loans -->
            <?php if (!empty($loan_alerts)): ?>
                <div class="alert alert-warning flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                        <div>
                            <strong>Loan Business Rules Alert:</strong>
                            <span><?php echo count($loan_alerts); ?> loan(s) require attention</span>
                            <a href="<?php echo BASE_URL; ?>/views/admin/loan_approvals.php" class="ml-2 text-sm underline">Review Now</a>
                        </div>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Enhanced Filter and Search Section -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-filter mr-2" style="color: var(--lapis-lazuli);"></i>
                        Filter & Search Loans
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label for="search" class="form-label">Search Loans</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-muted);"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Member name, loan ID, purpose" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($loanStatuses as $status_key => $status_label): ?>
                                    <option value="<?php echo htmlspecialchars($status_key); ?>" 
                                        <?php echo ($status_filter === $status_key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="loan_type" class="form-label">Loan Type</label>
                            <select class="form-control" id="loan_type" name="loan_type">
                                <option value="">All Types</option>
                                <?php foreach ($loanTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['id']); ?>" 
                                        <?php echo ($loan_type == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="amount_range" class="form-label">Amount Range</label>
                            <select class="form-control" id="amount_range" name="amount_range">
                                <option value="">All Amounts</option>
                                <option value="0-100000" <?php echo ($amount_range == '0-100000') ? 'selected' : ''; ?>>₦0 - ₦100k</option>
                                <option value="100001-500000" <?php echo ($amount_range == '100001-500000') ? 'selected' : ''; ?>>₦100k - ₦500k</option>
                                <option value="500001-1000000" <?php echo ($amount_range == '500001-1000000') ? 'selected' : ''; ?>>₦500k - ₦1M</option>
                                <option value="1000001-9999999" <?php echo ($amount_range == '1000001-9999999') ? 'selected' : ''; ?>>Above ₦1M</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="mb-6 p-4 rounded-lg border-l-4 shadow-md flex items-center justify-between
                    <?php 
                    switch($_SESSION['flash_type']) {
                        case 'success':
                            echo 'bg-green-50 border-green-500 text-green-800';
                            break;
                        case 'danger':
                            echo 'bg-red-50 border-red-500 text-red-800';
                            break;
                        case 'warning':
                            echo 'bg-yellow-50 border-yellow-500 text-yellow-800';
                            break;
                        default:
                            echo 'bg-blue-50 border-blue-500 text-blue-800';
                    }
                    ?>">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : ($_SESSION['flash_type'] === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> mr-3"></i>
                        <span class="font-medium"><?php echo $_SESSION['flash_message']; ?></span>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="this.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
            
            <!-- Filters and Search -->
            <div class="bg-white rounded-2xl shadow-lg mb-8 overflow-hidden">
                <div class="bg-gradient-to-r from-secondary-700 to-secondary-800 text-white px-6 py-4">
                    <h3 class="text-lg font-semibold"><i class="fas fa-filter mr-3"></i>Filters and Search</h3>
                </div>
                <div class="p-6">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <!-- Search -->
                        <div class="lg:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" id="search" name="search" 
                                   placeholder="Member name, loan ID" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                        </div>
                        
                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                <option value="">All Status</option>
                                <?php foreach ($loanStatuses as $status_key => $status_label): ?>
                                    <option value="<?php echo htmlspecialchars($status_key); ?>" 
                                        <?php echo ($status_filter === $status_key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sort Options -->
                        <div>
                            <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select id="sort_by" name="sort_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                <option value="application_date" <?php echo ($sort_by === 'application_date') ? 'selected' : ''; ?>>Application Date</option>
                                <option value="amount" <?php echo ($sort_by === 'amount') ? 'selected' : ''; ?>>Amount</option>
                                <option value="status" <?php echo ($sort_by === 'status') ? 'selected' : ''; ?>>Status</option>
                                <option value="term" <?php echo ($sort_by === 'term') ? 'selected' : ''; ?>>Term</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                            <select id="sort_order" name="sort_order" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="lg:col-span-5 flex gap-3 pt-4">
                            <button type="submit" class="bg-primary-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-700 transition-colors shadow-md hover:shadow-lg">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                            <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="border-2 border-gray-300 text-gray-700 px-6 py-2 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-primary-500 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-primary-600 uppercase tracking-wider mb-2">
                                Active Loans
                            </p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($loanStats['active_loans']['count']); ?></p>
                            <p class="text-sm text-gray-500">=N=<?php echo number_format($loanStats['active_loans']['amount'], 2); ?></p>
                        </div>
                        <div class="bg-primary-100 p-3 rounded-full">
                            <i class="fas fa-chart-line text-2xl text-primary-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-yellow-500 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-yellow-600 uppercase tracking-wider mb-2">
                                Pending Loans
                            </p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($loanStats['pending_loans']['count']); ?></p>
                            <p class="text-sm text-gray-500">=N=<?php echo number_format($loanStats['pending_loans']['amount'], 2); ?></p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-clock text-2xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">
                                Paid Loans
                            </p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($loanStats['paid_loans']['count']); ?></p>
                            <p class="text-sm text-gray-500">=N=<?php echo number_format($loanStats['paid_loans']['amount'], 2); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-check-circle text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">
                                This Month Repayments
                            </p>
                            <p class="text-2xl font-bold text-gray-800">=N=<?php echo number_format($loanStats['month_repayment_amount'], 2); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-calendar-alt text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
                <div class="border-b border-gray-200 pb-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-filter text-primary-600 mr-2"></i>
                        Filter & Search
                    </h3>
                </div>
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search text-gray-400 mr-1"></i>Search
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                               id="search" 
                               name="search" 
                               placeholder="Search by name, purpose..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag text-gray-400 mr-1"></i>Status
                        </label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                                id="status" 
                                name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($loanStatuses as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($status_filter === $key) ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sort text-gray-400 mr-1"></i>Sort By
                        </label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                                id="sort_by" 
                                name="sort_by">
                            <option value="application_date" <?php echo ($sort_by === 'application_date') ? 'selected' : ''; ?>>Date</option>
                            <option value="amount" <?php echo ($sort_by === 'amount') ? 'selected' : ''; ?>>Amount</option>
                            <option value="status" <?php echo ($sort_by === 'status') ? 'selected' : ''; ?>>Status</option>
                            <option value="term" <?php echo ($sort_by === 'term') ? 'selected' : ''; ?>>Term</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sort-amount-down text-gray-400 mr-1"></i>Order
                        </label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                                id="sort_order" 
                                name="sort_order">
                            <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Loans Table -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-table text-white mr-2"></i>
                        Loan Applications List
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    <i class="fas fa-hashtag mr-1"></i>ID
                                </th>
                                <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    <i class="fas fa-user mr-1"></i>Member
                                </th>
                                <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    <i class="fas fa-dollar-sign mr-1"></i>Amount
                                </th>
                                <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">
                                    <i class="fas fa-calendar mr-1"></i>Term
                                </th>
                                <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                    <i class="fas fa-percentage mr-1"></i>Interest
                                </th>
                                <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                    <i class="fas fa-clock mr-1"></i>Application Date
                                </th>
                                <th class="px-3 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    <i class="fas fa-flag mr-1"></i>Status
                                </th>
                                <th class="px-3 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    <i class="fas fa-cogs mr-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="8" class="px-3 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                        <p class="text-gray-500 text-lg">No loan applications found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <?php echo $loan['loan_id']; ?>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex items-center min-w-0">
                                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center mr-2 flex-shrink-0">
                                                <i class="fas fa-user text-white text-xs"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $loan['member_id']; ?>"
                                                   class="text-sm font-medium text-primary-600 hover:text-primary-800 transition-colors block truncate">
                                                    <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>
                                                </a>
                                                <div class="md:hidden text-xs text-gray-500 mt-1">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        <?php echo $loan['term']; ?>mo
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                        <div>=N=<?php echo number_format($loan['amount'], 2); ?></div>
                                        <div class="lg:hidden text-xs text-gray-500 mt-1">
                                            <?php echo $loan['interest_rate']; ?>% • <?php echo date('M d, Y', strtotime($loan['application_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap hidden md:table-cell">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo $loan['term']; ?> months
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900 hidden lg:table-cell">
                                        <?php echo $loan['interest_rate']; ?>%
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                                        <?php echo date('M d, Y', strtotime($loan['application_date'])); ?>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                            echo match($loan['status']) {
                                                'Pending' => 'bg-yellow-100 text-yellow-800',
                                                'Approved' => 'bg-blue-100 text-blue-800',
                                                'Rejected' => 'bg-red-100 text-red-800',
                                                'Disbursed' => 'bg-purple-100 text-purple-800',
                                                'Paid' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo match($loan['status']) {
                                                    'Pending' => 'clock',
                                                    'Approved' => 'check',
                                                    'Rejected' => 'times',
                                                    'Disbursed' => 'arrow-right',
                                                    'Paid' => 'check-circle',
                                                    default => 'question'
                                                };
                                            ?> mr-1"></i>
                                            <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-1">
                                            <!-- View Details Button - Available for all statuses -->
                                            <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan['loan_id']; ?>"
                                               class="text-blue-600 hover:text-blue-900 transition-colors p-1" title="View Details">
                                                <i class="fas fa-eye text-sm"></i>
                                            </a>
                                            
                                            <!-- Approve Button - Only for pending loans -->
                                            <?php if ($loan['status'] === 'Pending'): ?>
                                                <button onclick="openApproveModal(<?php echo $loan['loan_id']; ?>, '<?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name'], ENT_QUOTES); ?>', <?php echo $loan['amount']; ?>, <?php echo $loan['term']; ?>)"
                                                        class="text-green-600 hover:text-green-900 transition-colors p-1" title="Approve">
                                                    <i class="fas fa-check text-sm"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Reject Button - Only for pending loans -->
                                            <?php if ($loan['status'] === 'Pending'): ?>
                                                <button onclick="openRejectModal(<?php echo $loan['loan_id']; ?>)"
                                                        class="text-red-600 hover:text-red-900 transition-colors p-1" title="Reject">
                                                    <i class="fas fa-times text-sm"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Edit Button - Available for pending and approved loans -->
                                            <?php if (in_array($loan['status'], ['Pending', 'Approved'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/views/admin/edit_loan.php?id=<?php echo $loan['loan_id']; ?>"
                                                   class="text-yellow-600 hover:text-yellow-900 transition-colors p-1" title="Edit">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Add Repayment Button - Available for approved, disbursed, and active loans -->
                                            <?php if (in_array($loan['status'], ['Approved', 'Disbursed', 'Paid'])): ?>
                                                <a href="add_repayment.php?id=<?php echo $loan['loan_id']; ?>"
                                                   class="text-purple-600 hover:text-purple-900 transition-colors p-1" title="Add Repayment">
                                                    <i class="fas fa-plus-circle text-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Button - Available for pending and rejected loans -->
                                            <?php if (in_array($loan['status'], ['Pending', 'Rejected'])): ?>
                                                <button onclick="confirmDelete(<?php echo $loan['loan_id']; ?>)"
                                                        class="text-red-600 hover:text-red-900 transition-colors p-1" title="Delete">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="flex flex-col sm:flex-row justify-between items-center mt-8 space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-600 flex items-center">
                        <i class="fas fa-info-circle text-primary-600 mr-2"></i>
                        <span>Showing <strong class="text-gray-900"><?php echo (($pagination['current_page'] - 1) * $limit) + 1; ?></strong> to <strong class="text-gray-900"><?php echo min($pagination['current_page'] * $limit, $pagination['total_items']); ?></strong> of <strong class="text-gray-900"><?php echo $pagination['total_items']; ?></strong> entries</span>
                    </div>
                    <nav aria-label="Page navigation">
                        <div class="flex items-center space-x-1">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors" 
                                   aria-label="First">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?page=<?php echo $pagination['current_page'] - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors" 
                                   aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Calculate range of page numbers to display
                            $start_page = max(1, $pagination['current_page'] - 2);
                            $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                                   class="px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo ($i === $pagination['current_page']) ? 'bg-primary-600 text-white border border-primary-600' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50 hover:text-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                <a href="?page=<?php echo $pagination['current_page'] + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors" 
                                   aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <a href="?page=<?php echo $pagination['total_pages']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors" 
                                   aria-label="Last">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>
                </div>
                <!-- End of Main Content -->
            </main>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Approve Loan Modal -->
    <div id="approveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-2xl rounded-2xl bg-white">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <form method="POST" action="<?php echo BASE_URL; ?>/views/admin/process_loan.php" id="approveForm">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-check-circle mr-3"></i>Approve Loan Application
                            </h3>
                            <button type="button" class="text-white hover:text-gray-200 transition-colors" onclick="closeApproveModal()">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="p-6">
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="font-semibold text-blue-800 mb-2">Loan Details:</h4>
                                    <div class="text-blue-700" id="approveLoanDetails">
                                        <!-- Loan details will be populated by JavaScript -->
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors" 
                                id="approve_notes" 
                                name="notes" 
                                rows="3" 
                                placeholder="Add any notes about the approval..."
                            ></textarea>
                        </div>
                        
                        <input type="hidden" name="loan_id" id="approveLoanId" value="">
                        <input type="hidden" name="action" value="approve">
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                        <button type="button" 
                                class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
                                onclick="closeApproveModal()">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors flex items-center">
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
                <form method="POST" action="<?php echo BASE_URL; ?>/views/admin/process_loan.php" id="rejectForm">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-times-circle mr-3"></i>Reject Loan Application
                            </h3>
                            <button type="button" class="text-white hover:text-gray-200 transition-colors" onclick="closeRejectModal()">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="p-6">
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-lg">
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" 
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
                        
                        <input type="hidden" name="loan_id" id="rejectLoanId" value="">
                        <input type="hidden" name="action" value="reject">
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                        <button type="button" 
                                class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
                                onclick="closeRejectModal()">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors flex items-center">
                            <i class="fas fa-times-circle mr-2"></i>Reject Loan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        Confirm Deletion
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="closeDeleteModal()">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-gray-600">Are you sure you want to delete this loan application? This action cannot be undone.</p>
                    <div id="deleteError" class="mt-3 p-3 bg-red-100 border border-red-400 text-red-700 rounded hidden">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span id="deleteErrorMessage"></span>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors flex items-center" onclick="closeDeleteModal()">
                        <i class="fas fa-times mr-1"></i>
                        Cancel
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center" onclick="deleteLoanAjax()">
                        <i class="fas fa-trash mr-1"></i>
                        <span id="deleteButtonText">Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-upload text-primary-600 mr-2"></i>
                        Import Loans
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="closeImportModal()">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="csvFile" class="block text-sm font-medium text-gray-700 mb-2">
                            Select CSV File
                        </label>
                        <input type="file" id="csvFile" name="csvFile" accept=".csv" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <p class="text-xs text-gray-500 mt-1">
                            CSV format: member_id, amount, purpose, term_months, interest_rate, application_date, status
                        </p>
                    </div>
                    <div id="importProgress" class="mb-4 hidden">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Processing...</p>
                    </div>
                    <div id="importResult" class="mb-4 hidden">
                        <!-- Results will be displayed here -->
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors" onclick="closeImportModal()">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-upload mr-1"></i>
                            Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        .avatar-sm {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        .btn-group .btn {
            margin: 0 1px;
        }
        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }
    </style>
    
    <script>
        let loanIdToDelete = null;

        // Approve Modal Functions
        function openApproveModal(loanId, memberName, amount, term) {
            document.getElementById('approveLoanId').value = loanId;
            document.getElementById('approveForm').action = '<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=' + loanId;
            document.getElementById('approveLoanDetails').innerHTML = `
                <p><strong>Member:</strong> ${memberName}</p>
                <p><strong>Amount:</strong> ₦${new Intl.NumberFormat().format(amount)}</p>
                <p><strong>Term:</strong> ${term} months</p>
            `;
            document.getElementById('approveModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            // Focus on notes field after a short delay
            setTimeout(() => {
                document.getElementById('approve_notes').focus();
            }, 100);
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('approve_notes').value = '';
        }

        // Reject Modal Functions
        function openRejectModal(loanId) {
            document.getElementById('rejectLoanId').value = loanId;
            document.getElementById('rejectForm').action = '<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=' + loanId;
            document.getElementById('rejectModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            // Focus on notes field after a short delay
            setTimeout(() => {
                document.getElementById('reject_notes').focus();
            }, 100);
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('reject_notes').value = '';
            document.getElementById('reject_notes_error').classList.add('hidden');
        }

        // Delete Modal Functions
        function confirmDelete(id) {
            loanIdToDelete = id;
            document.getElementById('deleteError').classList.add('hidden');
            document.getElementById('confirmDeleteBtn').disabled = false;
            document.getElementById('deleteButtonText').textContent = 'Delete';
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            loanIdToDelete = null;
            document.getElementById('deleteError').classList.add('hidden');
        }
        
        // AJAX Delete Function
        function deleteLoanAjax() {
            if (!loanIdToDelete) return;
            
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            const deleteText = document.getElementById('deleteButtonText');
            const errorDiv = document.getElementById('deleteError');
            const errorMsg = document.getElementById('deleteErrorMessage');
            
            // Disable button and show loading
            deleteBtn.disabled = true;
            deleteText.textContent = 'Deleting...';
            errorDiv.classList.add('hidden');
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('loan_id', loanIdToDelete);
            
            // Send AJAX request
            fetch('<?php echo BASE_URL; ?>/controllers/loan_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and reload page
                    closeDeleteModal();
                    location.reload();
                } else {
                    // Show error message
                    errorMsg.textContent = data.message || 'Failed to delete loan application';
                    errorDiv.classList.remove('hidden');
                    deleteBtn.disabled = false;
                    deleteText.textContent = 'Delete';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMsg.textContent = 'An error occurred while deleting the loan application';
                errorDiv.classList.remove('hidden');
                deleteBtn.disabled = false;
                deleteText.textContent = 'Delete';
            });
        }

        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('importProgress').classList.add('hidden');
            document.getElementById('importResult').classList.add('hidden');
        }

        function exportToCSV() {
             // Get current filters
             const search = document.querySelector('input[name="search"]').value;
             const status = document.querySelector('select[name="status"]').value;
             const sortBy = document.querySelector('select[name="sort_by"]').value;
             const sortOrder = document.querySelector('select[name="sort_order"]').value;
             
             // Build export URL with filters
             let exportUrl = 'export_loans.php?';
             const params = [];
             
             if (search) params.push('search=' + encodeURIComponent(search));
             if (status) params.push('status=' + encodeURIComponent(status));
             if (sortBy) params.push('sort_by=' + encodeURIComponent(sortBy));
             if (sortOrder) params.push('sort_order=' + encodeURIComponent(sortOrder));
             
             exportUrl += params.join('&');
             
             // Open export URL
             window.open(exportUrl, '_blank');
         }

        // Set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            document.getElementById('approveModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeApproveModal();
                }
            });
            
            document.getElementById('rejectModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeRejectModal();
                }
            });
            
            document.getElementById('deleteModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
            
            document.getElementById('importModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImportModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (!document.getElementById('approveModal').classList.contains('hidden')) {
                        closeApproveModal();
                    }
                    if (!document.getElementById('rejectModal').classList.contains('hidden')) {
                        closeRejectModal();
                    }
                    if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                        closeDeleteModal();
                    }
                    if (!document.getElementById('importModal').classList.contains('hidden')) {
                        closeImportModal();
                    }
                }
            });
            
            // Form validation for reject modal
            const rejectForm = document.getElementById('rejectForm');
            const rejectNotesField = document.getElementById('reject_notes');
            const rejectNotesError = document.getElementById('reject_notes_error');
            
            if (rejectForm) {
                rejectForm.addEventListener('submit', function(event) {
                    if (!rejectNotesField.value.trim()) {
                        event.preventDefault();
                        event.stopPropagation();
                        rejectNotesField.classList.add('border-red-500', 'ring-red-500');
                        rejectNotesField.classList.remove('border-gray-300');
                        rejectNotesError.classList.remove('hidden');
                        return false;
                    }
                    rejectNotesField.classList.remove('border-red-500', 'ring-red-500');
                    rejectNotesField.classList.add('border-gray-300');
                    rejectNotesError.classList.add('hidden');
                });
                
                // Real-time validation
                rejectNotesField.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.classList.remove('border-red-500', 'ring-red-500');
                        this.classList.add('border-gray-300');
                        rejectNotesError.classList.add('hidden');
                    } else {
                        this.classList.add('border-red-500', 'ring-red-500');
                        this.classList.remove('border-gray-300');
                        rejectNotesError.classList.remove('hidden');
                    }
                });
            }
            
            // Confirmation for approve form
            const approveForm = document.getElementById('approveForm');
            if (approveForm) {
                approveForm.addEventListener('submit', function(event) {
                    if (!confirm('Are you sure you want to approve this loan application?')) {
                        event.preventDefault();
                        return false;
                    }
                });
            }

            // Handle import form submission
            document.getElementById('importForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                const fileInput = document.getElementById('csvFile');
                
                if (!fileInput.files[0]) {
                    alert('Please select a CSV file.');
                    return;
                }
                
                formData.append('csvFile', fileInput.files[0]);
                
                // Show progress
                document.getElementById('importProgress').classList.remove('hidden');
                document.getElementById('importResult').classList.add('hidden');
                
                // Send AJAX request
                fetch('loan_import_controller.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('importProgress').classList.add('hidden');
                    document.getElementById('importResult').classList.remove('hidden');
                    
                    if (data.success) {
                        document.getElementById('importResult').innerHTML = `
                            <div class="p-3 bg-green-100 border border-green-400 text-green-700 rounded">
                                <i class="fas fa-check-circle mr-2"></i>
                                Successfully imported ${data.imported_count} loans.
                            </div>
                        `;
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        let errorHtml = `
                            <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Import failed: ${data.message}
                        `;
                        
                        if (data.errors && data.errors.length > 0) {
                            errorHtml += '<ul class="mt-2 ml-4">';
                            data.errors.forEach(error => {
                                errorHtml += `<li>• ${error}</li>`;
                            });
                            errorHtml += '</ul>';
                        }
                        
                        errorHtml += '</div>';
                        document.getElementById('importResult').innerHTML = errorHtml;
                    }
                })
                .catch(error => {
                    document.getElementById('importProgress').classList.add('hidden');
                    document.getElementById('importResult').classList.remove('hidden');
                    document.getElementById('importResult').innerHTML = `
                        <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            An error occurred during import.
                        </div>
                    `;
                });
            });
        });
    </script>
</body>
</html>
