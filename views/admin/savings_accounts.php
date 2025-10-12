<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../src/autoload.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access the dashboard');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize services
$database = Database::getInstance();
$conn = $database->getConnection();

try {
    $savingsRepository = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $transactionRepository = new \CSIMS\Repositories\SavingsTransactionRepository($conn);
    
    // Get filters from request
    $filters = [];
    if (!empty($_GET['member_id'])) {
        $filters['member_id'] = (int)$_GET['member_id'];
    }
    if (!empty($_GET['account_type'])) {
        $filters['account_type'] = $_GET['account_type'];
    }
    if (!empty($_GET['status'])) {
        $filters['account_status'] = $_GET['status'];
    }
    
    // Pagination
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    // Get accounts
    $accounts = $savingsRepository->findAll($filters, ['created_at' => 'DESC'], $limit, $offset);
    $totalAccounts = $savingsRepository->count($filters);
    
    // Get statistics
    $stats = $savingsRepository->getAccountStatistics();
    
} catch (Exception $e) {
    $accounts = [];
    $totalAccounts = 0;
    $stats = [];
    error_log("Error loading savings accounts: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Accounts - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Savings Accounts</h1>
                    <p class="text-gray-600">Manage member savings accounts and transactions</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="<?php echo BASE_URL; ?>/views/admin/create_savings_account.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i> New Account
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-piggy-bank text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Accounts</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $totalAccounts; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Accounts</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                $activeCount = 0;
                                foreach ($stats as $stat) {
                                    if ($stat['account_status'] === 'Active') {
                                        $activeCount += $stat['count'];
                                    }
                                }
                                echo $activeCount;
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-coins text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Balance</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                ₦<?php 
                                $totalBalance = 0;
                                foreach ($stats as $stat) {
                                    $totalBalance += $stat['total_balance'] ?? 0;
                                }
                                echo number_format($totalBalance, 2);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-calculator text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Avg Balance</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                ₦<?php 
                                $avgBalance = 0;
                                $totalCount = 0;
                                foreach ($stats as $stat) {
                                    $avgBalance += ($stat['avg_balance'] ?? 0) * ($stat['count'] ?? 0);
                                    $totalCount += $stat['count'] ?? 0;
                                }
                                if ($totalCount > 0) {
                                    $avgBalance = $avgBalance / $totalCount;
                                }
                                echo number_format($avgBalance, 2);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <select name="account_type" class="border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Types</option>
                            <option value="Regular" <?php echo ($_GET['account_type'] ?? '') === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                            <option value="Target" <?php echo ($_GET['account_type'] ?? '') === 'Target' ? 'selected' : ''; ?>>Target</option>
                            <option value="Fixed" <?php echo ($_GET['account_type'] ?? '') === 'Fixed' ? 'selected' : ''; ?>>Fixed Deposit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo ($_GET['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($_GET['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Closed" <?php echo ($_GET['status'] ?? '') === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" class="ml-2 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Accounts Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Savings Accounts</h3>
                </div>
                
                <?php if (empty($accounts)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-piggy-bank text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No savings accounts found</h3>
                        <p class="text-gray-600">Get started by creating the first savings account.</p>
                        <a href="<?php echo BASE_URL; ?>/views/admin/create_savings_account.php" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Create Account
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($accounts as $account): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($account->getAccountName()); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($account->getAccountNumber()); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">Member #<?php echo $account->getMemberId(); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full 
                                            <?php 
                                            switch($account->getAccountType()) {
                                                case 'Regular': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'Target': echo 'bg-green-100 text-green-800'; break;
                                                case 'Fixed': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($account->getAccountType()); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        ₦<?php echo number_format($account->getBalance(), 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full 
                                            <?php 
                                            switch($account->getAccountStatus()) {
                                                case 'Active': echo 'bg-green-100 text-green-800'; break;
                                                case 'Inactive': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'Closed': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($account->getAccountStatus()); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $account->getCreatedAt()->format('M j, Y'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="<?php echo BASE_URL; ?>/views/admin/view_savings_account.php?id=<?php echo $account->getAccountId(); ?>" 
                                           class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/views/admin/edit_savings_account.php?id=<?php echo $account->getAccountId(); ?>" 
                                           class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalAccounts > $limit): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < ceil($totalAccounts / $limit)): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($offset + $limit, $totalAccounts); ?></span> of 
                                    <span class="font-medium"><?php echo $totalAccounts; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php for ($i = 1; $i <= ceil($totalAccounts / $limit); $i++): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                           <?php echo $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>