<?php
/**
 * Admin - Loans List View
 * 
 * This page displays a list of all loan applications with filtering, sorting,
 * and pagination capabilities.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize controllers
$loanController = new LoanController();
$memberController = new MemberController();

// Get loan statuses for filter dropdown
$loanStatuses = $loanController->getLoanStatuses();

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'application_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page

// Get loans with pagination
$result = $loanController->getAllLoans($page, $limit, $search, $sort_by, $sort_order, $status_filter);
$loans = $result['loans'];
$pagination = $result['pagination'];

// Get loan statistics
$loanStats = $loanController->getLoanStatistics();

// Page title
$pageTitle = "Loan Applications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title><?php echo $pageTitle; ?> - CSIMS</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a'
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Legacy CSS for compatibility -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-gray-50 font-sans">
    <!-- Page Wrapper -->
    <div class="wrapper">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div class="main-content">
            <?php include_once __DIR__ . '/../includes/header.php'; ?>

            <!-- Begin Page Content -->
            <div class="p-6">
                <!-- Page Header -->
                <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl mb-8 shadow-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-bold mb-2"><i class="fas fa-money-bill-wave mr-4"></i>Loan Applications</h1>
                            <p class="text-primary-100 text-lg">Track and manage all loan applications</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="<?php echo BASE_URL; ?>/admin/add_loan.php" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-primary-50 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>New Loan Application
                            </a>
                            <button onclick="openImportModal()" class="border-2 border-white text-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-primary-600 transition-all duration-200">
                                <i class="fas fa-upload mr-2"></i>Import
                            </button>
                            <button onclick="exportToCSV()" class="border-2 border-white text-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-primary-600 transition-all duration-200">
                                <i class="fas fa-download mr-2"></i>Export CSV
                            </button>
                            <button onclick="window.print()" class="border-2 border-white text-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-primary-600 transition-all duration-200">
                                <i class="fas fa-print mr-2"></i>Print
                            </button>
                        </div>
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
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-primary-500 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-primary-600 uppercase tracking-wider mb-2">
                                Active Loans
                            </p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($loanStats['active_loans']['count']); ?></p>
                            <p class="text-sm text-gray-500">₱<?php echo number_format($loanStats['active_loans']['amount'], 2); ?></p>
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
                            <p class="text-sm text-gray-500">₱<?php echo number_format($loanStats['pending_loans']['amount'], 2); ?></p>
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
                            <p class="text-sm text-gray-500">₱<?php echo number_format($loanStats['paid_loans']['amount'], 2); ?></p>
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
                            <p class="text-2xl font-bold text-gray-800">₱<?php echo number_format($loanStats['month_repayment_amount'], 2); ?></p>
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
                                                <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $loan['member_id']; ?>" 
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
                                        <div>₱<?php echo number_format($loan['amount'], 2); ?></div>
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
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-blue-100 text-blue-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'disbursed' => 'bg-purple-100 text-purple-800',
                                                'active' => 'bg-indigo-100 text-indigo-800',
                                                'defaulted' => 'bg-red-100 text-red-800',
                                                'paid' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo match($loan['status']) {
                                                    'pending' => 'clock',
                                                    'approved' => 'check',
                                                    'rejected' => 'times',
                                                    'disbursed' => 'arrow-right',
                                                    'active' => 'play',
                                                    'defaulted' => 'exclamation-triangle',
                                                    'paid' => 'check-circle',
                                                    default => 'question'
                                                };
                                            ?> mr-1"></i>
                                            <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-1">
                                            <!-- View Details Button - Available for all statuses -->
                                            <a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $loan['loan_id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900 transition-colors p-1" title="View Details">
                                                <i class="fas fa-eye text-sm"></i>
                                            </a>
                                            
                                            <!-- Approve Button - Only for pending loans -->
                                            <?php if ($loan['status'] === 'Pending'): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/process_loan.php?id=<?php echo $loan['loan_id']; ?>&action=approve" 
                                                   class="text-green-600 hover:text-green-900 transition-colors p-1" title="Approve">
                                                    <i class="fas fa-check text-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Reject Button - Only for pending loans -->
                                            <?php if ($loan['status'] === 'Pending'): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/process_loan.php?id=<?php echo $loan['loan_id']; ?>&action=reject" 
                                                   class="text-red-600 hover:text-red-900 transition-colors p-1" title="Reject">
                                                    <i class="fas fa-times text-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Edit Button - Available for pending and approved loans -->
                                            <?php if (in_array($loan['status'], ['Pending', 'Approved'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/edit_loan.php?id=<?php echo $loan['loan_id']; ?>" 
                                                   class="text-yellow-600 hover:text-yellow-900 transition-colors p-1" title="Edit">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Add Repayment Button - Available for approved, disbursed, and active loans -->
                                            <?php if (in_array($loan['status'], ['Approved', 'Disbursed', 'Active'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan['loan_id']; ?>" 
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
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            
            <?php include_once __DIR__ . '/../includes/footer.php'; ?>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

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
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors flex items-center" onclick="closeDeleteModal()">
                        <i class="fas fa-times mr-1"></i>
                        Cancel
                    </button>
                    <a href="#" id="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center">
                        <i class="fas fa-trash mr-1"></i>
                        Delete
                    </a>
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

        function confirmDelete(id) {
             loanIdToDelete = id;
             document.getElementById('confirmDelete').href = 'delete_loan.php?id=' + id;
             document.getElementById('deleteModal').classList.remove('hidden');
         }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            loanIdToDelete = null;
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
                    if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                        closeDeleteModal();
                    }
                    if (!document.getElementById('importModal').classList.contains('hidden')) {
                        closeImportModal();
                    }
                }
            });

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
