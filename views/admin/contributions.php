<?php
/**
 * Contributions Management Page
 * 
 * This page displays a list of all contributions with filtering, sorting, and pagination.
 * It also provides options to add, edit, view, and delete contributions.
 */

// Include required files
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/contribution_controller.php';
require_once '../../controllers/member_controller.php';

// Initialize controllers
$auth = new AuthController();
$contributionController = new ContributionController();
$memberController = new MemberController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get current user
$current_user = $auth->getCurrentUser();

// Process pagination, search, and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'contribution_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get contributions with pagination
$result = $contributionController->getAllContributions(
    $page, $limit, $search, $sort_by, $sort_order, $filter_type, $date_from, $date_to
);

$contributions = $result['contributions'];
$pagination = $result['pagination'];

// Get contribution types for filter dropdown
$contributionTypes = $contributionController->getContributionTypes();

// Calculate statistics
$totalContributions = $pagination['total_records'];
$totalAmount = 0;
$monthlyAmount = 0;
$currentMonth = date('Y-m');

// Get all contributions for statistics (without pagination)
if (!empty($contributions)) {
    $allContributionsResult = $contributionController->getAllContributions(
        1, 999999, $search, $sort_by, $sort_order, $filter_type, $date_from, $date_to
    );
    $allContributions = $allContributionsResult['contributions'];
    
    foreach ($allContributions as $contribution) {
        $totalAmount += $contribution['amount'];
        
        // Check if contribution is from current month
        if (date('Y-m', strtotime($contribution['contribution_date'])) === $currentMonth) {
            $monthlyAmount += $contribution['amount'];
        }
    }
}

$averageContribution = $totalContributions > 0 ? $totalAmount / $totalContributions : 0;

// Handle contribution deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $contribution_id = (int)$_GET['id'];
    if ($contributionController->deleteContribution($contribution_id)) {
        $_SESSION['flash_message'] = "Contribution deleted successfully.";
        $_SESSION['flash_message_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to delete contribution.";
        $_SESSION['flash_message_type'] = "danger";
    }
    
    // Redirect to remove the delete parameter from URL
    header('Location: contributions.php');
    exit;
}

// Page title
$pageTitle = "Manage Contributions";
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

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
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
    
    <!-- Custom styles for legacy compatibility -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include_once '../includes/header.php'; ?>

                <!-- Begin Page Content -->
<div id="main-content" class="p-6 bg-gray-50 min-h-screen transition-all duration-300 ml-64">
    <!-- Page Heading -->
    <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl shadow-lg mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2"><i class="fas fa-hand-holding-usd mr-4"></i>Manage Contributions</h1>
                <p class="text-primary-100 text-lg">Track and manage all member contributions</p>
            </div>
            <div class="flex gap-3">
                <a href="<?php echo BASE_URL; ?>/views/admin/add_contribution.php" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-primary-50 transition-all duration-200 shadow-md hover:shadow-lg">
                    <i class="fas fa-plus mr-2"></i>Add New Contribution
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

    <!-- Flash Message -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="<?php echo $_SESSION['flash_message_type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?> border-l-4 p-4 mb-6 rounded-lg shadow-sm" role="alert">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-<?php echo $_SESSION['flash_message_type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> mr-3 text-lg"></i>
                    <span class="font-medium"><?php echo $_SESSION['flash_message']; ?></span>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="this.parentElement.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php 
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_message_type']);
        ?>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="bg-white rounded-2xl shadow-lg mb-8 overflow-hidden">
        <div class="bg-gradient-to-r from-secondary-700 to-secondary-800 text-white px-6 py-4">
            <h3 class="text-lg font-semibold"><i class="fas fa-filter mr-3"></i>Filters and Search</h3>
        </div>
        <div class="p-6">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Name, Receipt #, Notes" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                </div>
                
                <!-- Contribution Type Filter -->
                <div>
                    <label for="filter_type" class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select id="filter_type" name="filter_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                        <option value="">All Types</option>
                        <?php foreach ($contributionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                <?php echo ($filter_type === $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Date Range Filter -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                </div>
                
                <!-- Sort Options -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select id="sort_by" name="sort_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                        <option value="contribution_date" <?php echo ($sort_by === 'contribution_date') ? 'selected' : ''; ?>>Date</option>
                        <option value="amount" <?php echo ($sort_by === 'amount') ? 'selected' : ''; ?>>Amount</option>
                        <option value="contribution_type" <?php echo ($sort_by === 'contribution_type') ? 'selected' : ''; ?>>Type</option>
                        <option value="payment_method" <?php echo ($sort_by === 'payment_method') ? 'selected' : ''; ?>>Payment Method</option>
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
                <div class="lg:col-span-6 flex gap-3 pt-4">
                    <button type="submit" class="bg-primary-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-700 transition-colors shadow-md hover:shadow-lg">
                        <i class="fas fa-search mr-2"></i>Apply Filters
                    </button>
                    <a href="<?php echo BASE_URL; ?>/views/admin/contributions.php" class="border-2 border-gray-300 text-gray-700 px-6 py-2 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
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
                        Total Contributions
                    </p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($totalContributions); ?></p>
                </div>
                <div class="bg-primary-100 p-3 rounded-full">
                    <i class="fas fa-donate text-2xl text-primary-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">
                        Total Amount
                    </p>
                    <p class="text-2xl font-bold text-gray-800">₱<?php echo number_format($totalAmount, 2); ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-peso-sign text-2xl text-green-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">
                        This Month
                    </p>
                    <p class="text-2xl font-bold text-gray-800">₱<?php echo number_format($monthlyAmount, 2); ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-calendar text-2xl text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-yellow-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-yellow-600 uppercase tracking-wider mb-2">
                        Average Contribution
                    </p>
                    <p class="text-2xl font-bold text-gray-800">₱<?php echo number_format($averageContribution, 2); ?></p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <i class="fas fa-chart-line text-2xl text-yellow-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Contributions Table -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-secondary-700 to-secondary-800 text-white px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-semibold"><i class="fas fa-table mr-3"></i>Contributions List</h3>
            <div class="flex gap-2">
                <button onclick="exportToCSV()" class="bg-green-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors shadow-md">
                    <i class="fas fa-file-csv mr-2"></i>Export CSV
                </button>
            </div>
        </div>
        <div class="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full" id="contributionsTable">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">#</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Member</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Amount</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">Payment Method</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">Receipt #</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($contributions)): ?>
                            <tr>
                                <td colspan="8" class="px-3 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                                        <p class="text-lg font-medium">No contributions found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contributions as $contribution): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-4 text-sm font-bold text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($contribution['contribution_id']); ?></td>
                                    <td class="px-3 py-4 text-sm">
                                        <div class="flex items-center min-w-0">
                                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center mr-2 flex-shrink-0">
                                                <i class="fas fa-user text-white text-xs"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo htmlspecialchars($contribution['member_id']); ?>" class="font-semibold text-gray-900 hover:text-primary-600 transition-colors block truncate">
                                                    <?php echo htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']); ?>
                                                </a>
                                                <div class="md:hidden text-xs text-gray-500 mt-1">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo htmlspecialchars($contribution['contribution_type']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 text-sm font-bold text-green-600 whitespace-nowrap">₱<?php echo htmlspecialchars(number_format($contribution['amount'], 2)); ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-900 whitespace-nowrap">
                                        <div><?php echo htmlspecialchars(date('M d, Y', strtotime($contribution['contribution_date']))); ?></div>
                                        <div class="lg:hidden text-xs text-gray-500 mt-1">
                                            <?php echo htmlspecialchars($contribution['payment_method']); ?> • <?php echo htmlspecialchars($contribution['receipt_number']); ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 text-sm hidden md:table-cell">
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo htmlspecialchars($contribution['contribution_type']); ?></span>
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-900 hidden lg:table-cell whitespace-nowrap"><?php echo htmlspecialchars($contribution['payment_method']); ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-900 hidden lg:table-cell whitespace-nowrap"><?php echo htmlspecialchars($contribution['receipt_number']); ?></td>
                                    <td class="px-3 py-4 text-sm">
                                        <div class="flex gap-1">
                                            <a href="<?php echo BASE_URL; ?>/views/admin/view_contribution.php?id=<?php echo htmlspecialchars($contribution['contribution_id']); ?>" 
                                               class="bg-blue-100 text-blue-700 p-2 rounded-lg hover:bg-blue-200 transition-colors" title="View">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/views/admin/edit_contribution.php?id=<?php echo htmlspecialchars($contribution['contribution_id']); ?>" 
                                               class="bg-yellow-100 text-yellow-700 p-2 rounded-lg hover:bg-yellow-200 transition-colors" title="Edit">
                                                <i class="fas fa-edit text-xs"></i>
                                            </a>
                                            <button class="bg-red-100 text-red-700 p-2 rounded-lg hover:bg-red-200 transition-colors" title="Delete" 
                                                    onclick="confirmDelete(<?php echo htmlspecialchars($contribution['contribution_id']); ?>)">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="flex justify-center mt-8 px-6">
                    <nav class="flex items-center space-x-2">
                        <?php if ($pagination['current_page'] > 1): ?>
                            <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                First
                            </a>
                            <a href="?page=<?php echo $pagination['current_page'] - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        // Calculate range of page numbers to display
                        $start_page = max(1, $pagination['current_page'] - 2);
                        $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                               class="px-4 py-2 text-sm font-medium <?php echo ($i == $pagination['current_page']) ? 'text-white bg-primary-600 border-primary-600' : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50'; ?> border rounded-lg transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $pagination['current_page'] + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Next
                            </a>
                            <a href="?page=<?php echo $pagination['total_pages']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Last
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
            
            <!-- Showing entries info -->
            <div class="text-center mt-6 text-sm text-gray-600 px-6 pb-6">
                Showing <?php echo ($pagination['total_records'] == 0) ? 0 : (($pagination['current_page'] - 1) * $pagination['limit'] + 1); ?> to 
                <?php echo min($pagination['current_page'] * $pagination['limit'], $pagination['total_records']); ?> of 
                <?php echo $pagination['total_records']; ?> entries
            </div>
        </div>
    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            
            <?php include_once '../includes/footer.php'; ?>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Delete</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="closeDeleteModal()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="mt-2 px-2 py-3">
                    <p class="text-sm text-gray-600">
                        Are you sure you want to delete this contribution? This action cannot be undone.
                    </p>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors" onclick="closeDeleteModal()">
                        Cancel
                    </button>
                    <a href="#" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors text-center" id="confirmDeleteBtn">
                        Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Import Contributions</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeImportModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="importFile" class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                        <input type="file" id="importFile" name="import_file" accept=".csv" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <p class="text-xs text-gray-500 mt-1">Only CSV files are supported. Maximum file size: 10MB</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV Format Requirements:</label>
                        <div class="text-xs text-gray-600 bg-gray-50 p-3 rounded">
                            <p class="font-medium mb-1">Required columns (in order):</p>
                            <p>member_id, contribution_type, amount, payment_method, contribution_date, receipt_number, notes</p>
                            <p class="mt-2"><strong>Note:</strong> First row should contain column headers</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeImportModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-upload mr-2"></i>Import
                        </button>
                    </div>
                </form>
                
                <div id="importProgress" class="hidden mt-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-sm text-blue-800">Processing import...</span>
                        </div>
                    </div>
                </div>
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
        // Function to export table to CSV
        function exportToCSV() {
            const table = document.getElementById('contributionsTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
                    let cellText = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + cellText + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            downloadLink.download = 'contributions_' + new Date().toISOString().slice(0, 10) + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        // Function to confirm deletion
        function confirmDelete(id) {
            document.getElementById('confirmDeleteBtn').href = '<?php echo BASE_URL; ?>/views/admin/contributions.php?delete=1&id=' + id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        // Function to close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
                closeImportModal();
            }
        });
        
        // Import Modal Functions
        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('importProgress').classList.add('hidden');
        }
        
        // Handle Import Form Submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file to import.');
                return;
            }
            
            // Show progress indicator
            document.getElementById('importProgress').classList.remove('hidden');
            
            // Create FormData object
            const formData = new FormData();
            formData.append('import_file', file);
            formData.append('import_type', 'contributions');
            
            // Send AJAX request
            fetch('<?php echo BASE_URL; ?>/controllers/contribution_import_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('importProgress').classList.add('hidden');
                
                if (data.success) {
                    alert('Import completed successfully! ' + data.message);
                    closeImportModal();
                    location.reload(); // Refresh the page to show new contributions
                } else {
                    alert('Import failed: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('importProgress').classList.add('hidden');
                console.error('Error:', error);
                alert('An error occurred during import. Please try again.');
            });
        });
        
        // Close import modal when clicking outside
         document.getElementById('importModal').addEventListener('click', function(e) {
             if (e.target === this) {
                 closeImportModal();
             }
         });
    </script>
</body>
</html>
