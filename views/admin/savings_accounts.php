<?php
ob_start(); // Start output buffering to prevent headers being sent
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../src/autoload.php';

// Initialize session instance for flash messages and auth checks
$session = Session::getInstance();

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

// Normalize column names for cross-schema compatibility via Utilities
require_once __DIR__ . '/../../includes/utilities.php';
$schema = Utilities::getSavingsSchema($conn);

$statusCol    = $schema['transactions']['status'];
$typeCol      = $schema['transactions']['type'];
$dateCol      = $schema['transactions']['date'];
$processedCol = $schema['transactions']['processed_at'];
$saIdCol      = $schema['accounts']['account_id'];
$mIdCol       = $schema['members']['member_id'];

// Handle POST actions for monthly deposit approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_monthly_deposit':
            try {
                $transaction_id = (int)$_POST['transaction_id'];
                
                // Get the pending transaction
                $stmt = $conn->prepare("SELECT * FROM savings_transactions WHERE id = ? AND UPPER(".$statusCol.") = 'PENDING'");
                $stmt->bind_param('i', $transaction_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $transaction = $result ? $result->fetch_assoc() : null;
                
                if ($transaction) {
                    // Update transaction status to completed
                    $stmt = $conn->prepare("UPDATE savings_transactions SET ".$statusCol." = 'completed', ".$processedCol." = NOW() WHERE id = ?");
                    $stmt->bind_param('i', $transaction_id);
                    $stmt->execute();
                    
                    // Update account balance
                    $amount = (float)$transaction['amount'];
                    $accountId = (int)$transaction['account_id'];
                    $stmt = $conn->prepare("UPDATE savings_accounts SET balance = balance + ? WHERE ".$saIdCol." = ?");
                    $stmt->bind_param('di', $amount, $accountId);
                    $stmt->execute();
                    
                    $session->setFlash('success', 'Monthly deposit approved successfully');
                } else {
                    $session->setFlash('error', 'Transaction not found or already processed');
                }
            } catch (Exception $e) {
                $session->setFlash('error', 'Error approving deposit: ' . $e->getMessage());
                error_log("Error approving monthly deposit: " . $e->getMessage());
            }
            break;
            
        case 'reject_monthly_deposit':
            try {
                $transaction_id = (int)$_POST['transaction_id'];
                $rejection_reason = $_POST['rejection_reason'] ?? 'No reason provided';
                
                // Update transaction status to rejected
                $stmt = $conn->prepare("UPDATE savings_transactions SET ".$statusCol." = 'rejected', description = CONCAT(description, ' - Rejected: ', ?), ".$processedCol." = NOW() WHERE id = ? AND UPPER(".$statusCol.") = 'PENDING'");
                $stmt->bind_param('si', $rejection_reason, $transaction_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $session->setFlash('success', 'Monthly deposit rejected successfully');
                } else {
                    $session->setFlash('error', 'Transaction not found or already processed');
                }
            } catch (Exception $e) {
                $session->setFlash('error', 'Error rejecting deposit: ' . $e->getMessage());
                error_log("Error rejecting monthly deposit: " . $e->getMessage());
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit();
}

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
    
    // Get search parameter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Pagination
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    // Get accounts with search functionality
    if (!empty($search)) {
        // Build custom search query
        $searchParam = '%' . $search . '%';
        $sql = "SELECT sa.* FROM savings_accounts sa 
                LEFT JOIN members m ON sa.member_id = m." . $mIdCol . "
                WHERE (sa.account_name LIKE ? 
                       OR sa.account_number LIKE ? 
                       OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
        
        // Add filters
        $params = [$searchParam, $searchParam, $searchParam];
        $types = 'sss';
        
        if (!empty($filters['member_id'])) {
            $sql .= " AND sa.member_id = ?";
            $params[] = $filters['member_id'];
            $types .= 'i';
        }
        if (!empty($filters['account_type'])) {
            $sql .= " AND sa.account_type = ?";
            $params[] = $filters['account_type'];
            $types .= 's';
        }
        if (!empty($filters['account_status'])) {
            $sql .= " AND sa.account_status = ?";
            $params[] = $filters['account_status'];
            $types .= 's';
        }
        
        // Count total (without pagination)
        $countSql = "SELECT COUNT(*) as total FROM savings_accounts sa 
                     LEFT JOIN members m ON sa.member_id = m." . $mIdCol . "
                     WHERE (sa.account_name LIKE ? 
                            OR sa.account_number LIKE ? 
                            OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
        $countParams = [$searchParam, $searchParam, $searchParam];
        $countTypes = 'sss';
        
        if (!empty($filters['member_id'])) {
            $countSql .= " AND sa.member_id = ?";
            $countParams[] = $filters['member_id'];
            $countTypes .= 'i';
        }
        if (!empty($filters['account_type'])) {
            $countSql .= " AND sa.account_type = ?";
            $countParams[] = $filters['account_type'];
            $countTypes .= 's';
        }
        if (!empty($filters['account_status'])) {
            $countSql .= " AND sa.account_status = ?";
            $countParams[] = $filters['account_status'];
            $countTypes .= 's';
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($countTypes, ...$countParams);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalAccounts = $countResult->fetch_assoc()['total'] ?? 0;
        
        // Add ordering and pagination
        $sql .= " ORDER BY sa.opening_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = \CSIMS\Models\SavingsAccount::fromArray($row);
        }
    } else {
        // Use repository method for non-search queries
        $accounts = $savingsRepository->findAll($filters, ['opening_date' => 'DESC'], $limit, $offset);
        $totalAccounts = $savingsRepository->count($filters);
    }
    
    // Get statistics
    $stats = $savingsRepository->getAccountStatistics();
    
    // Get pending monthly deposits
    $pending_deposits = [];
    try {
        $sql = "SELECT st.*, sa.account_number, m.first_name, m.last_name, m.member_number 
                FROM savings_transactions st
                JOIN savings_accounts sa ON st.account_id = sa.".$saIdCol.
               " JOIN members m ON sa.member_id = m.".$mIdCol.
               " WHERE UPPER(st.".$statusCol.") = 'PENDING'
                 AND UPPER(st.".$typeCol.") = 'DEPOSIT'
                 AND st.description LIKE '%Monthly auto-deposit%'
                ORDER BY st.".$dateCol." DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_deposits = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Exception $e) {
        error_log("Error fetching pending deposits: " . $e->getMessage());
    }
    
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
    
    <!-- Premium Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css?v=2.3">
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css?v=2.3">
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a26d77676f.js" crossorigin="anonymous"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include __DIR__ . '/../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include __DIR__ . '/../../views/includes/sidebar.php'; ?>
        
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
            
            <!-- Flash Messages -->
            <?php if ($session->hasFlash('success')): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $session->getFlash('success'); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($session->hasFlash('error')): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $session->getFlash('error'); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div id="savingsStatsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Accounts - Teal Gradient -->
                <div class="stat-card-gradient gradient-teal">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Total Accounts</p>
                        <h3 class="text-4xl font-bold text-white mb-2"><?php echo $totalAccounts; ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-check-circle mr-1"></i> Active savings plans
                        </div>
                    </div>
                    <i class="fas fa-piggy-bank absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
                
                <!-- Active Accounts - Green Gradient -->
                <div class="stat-card-gradient gradient-green">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Active Accounts</p>
                        <h3 class="text-4xl font-bold text-white mb-2">
                            <?php 
                            $activeCount = 0;
                            foreach ($stats as $stat) {
                                if ($stat['account_status'] === 'Active') {
                                    $activeCount += $stat['count'];
                                }
                            }
                            echo $activeCount;
                            ?>
                        </h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-chart-line mr-1"></i> Currently operating
                        </div>
                    </div>
                    <i class="fas fa-chart-line absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
                
                <!-- Total Balance - Blue Gradient -->
                <div class="stat-card-gradient gradient-blue">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Total Balance</p>
                        <h3 class="text-4xl font-bold text-white mb-2">
                            ₦<?php 
                            $totalBalance = 0;
                            foreach ($stats as $stat) {
                                $totalBalance += $stat['total_balance'] ?? 0;
                            }
                            echo number_format($totalBalance, 2);
                            ?>
                        </h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-wallet mr-1"></i> All accounts combined
                        </div>
                    </div>
                    <i class="fas fa-coins absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
                
                <!-- Average Balance - Purple Gradient -->
                <div class="stat-card-gradient gradient-purple">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Avg Balance</p>
                        <h3 class="text-4xl font-bold text-white mb-2">
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
                        </h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-calculator mr-1"></i> Per account
                        </div>
                    </div>
                    <i class="fas fa-calculator absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
            </div>
            
            <!-- Pending Monthly Deposits Section -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Pending Monthly Deposits</h3>
                        <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <?php echo count($pending_deposits); ?> Pending
                        </span>
                    </div>
                </div>

                <?php if (empty($pending_deposits)): ?>
                    <div class="px-6 py-8 text-center">
                        <i class="fas fa-clock text-gray-400 text-3xl mb-2"></i>
                        <div class="text-gray-900 font-medium mb-1">No pending monthly deposits</div>
                        <div class="text-gray-600 text-sm">When the auto-deposit job runs in pending mode, items appear here for approval.</div>
                        <a href="<?php echo BASE_URL; ?>/admin/job_management.php" class="mt-3 inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm">
                            <i class="fas fa-cogs mr-2"></i>View Jobs
                        </a>
                    </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pending_deposits as $deposit): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Member #<?php echo htmlspecialchars($deposit['member_number']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($deposit['account_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    ₦<?php echo number_format($deposit['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($deposit['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" class="inline-block mr-2">
                                        <input type="hidden" name="action" value="approve_monthly_deposit">
                                        <input type="hidden" name="transaction_id" value="<?php echo $deposit['id']; ?>">
                                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                            <i class="fas fa-check mr-1"></i>Approve
                                        </button>
                                    </form>
                                    <button type="button" onclick="showRejectModal(<?php echo $deposit['id']; ?>, '<?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?>', <?php echo $deposit['amount']; ?>)" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                        <i class="fas fa-times mr-1"></i>Reject
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>
            
            <!-- Enhanced Premium Filter and Search Section -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-filter mr-2" style="color: #3b28cc;"></i>
                        Filter & Search Savings Accounts
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form action="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <!-- Search Input -->
                        <div class="lg:col-span-2">
                            <label for="search" class="form-label">Search Accounts</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search icon-muted"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Member name, account number, balance..." 
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Account Type Filter -->
                        <div>
                            <label for="account_type" class="form-label">Account Type</label>
                            <select name="account_type" id="account_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="Regular" <?php echo ($_GET['account_type'] ?? '') === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="Target" <?php echo ($_GET['account_type'] ?? '') === 'Target' ? 'selected' : ''; ?>>Target</option>
                                <option value="Fixed" <?php echo ($_GET['account_type'] ?? '') === 'Fixed' ? 'selected' : ''; ?>>Fixed Deposit</option>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo ($_GET['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($_GET['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Closed" <?php echo ($_GET['status'] ?? '') === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        
                        <!-- Per Page -->
                        <div>
                            <label for="per_page" class="form-label">Show</label>
                            <select class="form-control" id="per_page" name="per_page">
                                <option value="15">15 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                            <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" class="btn btn-outline">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
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
                        <table class="table-premium" id="savingsAccountsTable">
                            <thead>
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
                                        <?php echo $account->getOpeningDate()->format('M j, Y'); ?>
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

    <!-- Reject Monthly Deposit Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Reject Monthly Deposit</h3>
                    <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject_monthly_deposit">
                    <input type="hidden" name="transaction_id" id="rejectTransactionId">
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">
                            Member: <span id="rejectMemberName" class="font-medium"></span><br>
                            Amount: <span id="rejectAmount" class="font-medium text-green-600"></span>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Rejection Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Please provide a reason for rejecting this deposit..." required></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRejectModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            <i class="fas fa-times mr-1"></i>Reject Deposit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showRejectModal(transactionId, memberName, amount) {
            document.getElementById('rejectTransactionId').value = transactionId;
            document.getElementById('rejectMemberName').textContent = memberName;
            document.getElementById('rejectAmount').textContent = '₦' + new Intl.NumberFormat().format(amount);
            document.getElementById('rejection_reason').value = '';
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }


        // Close modal when clicking outside
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>