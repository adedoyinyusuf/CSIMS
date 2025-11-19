<?php
/**
 * CSIMS Transaction Management Page
 * Built using the CSIMS admin template system with Phase 1&2 integrations
 */

session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';
require_once '_admin_template_config.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize common services
$businessRulesService = new SimpleBusinessRulesService();

// Handle transaction actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_transaction':
            // Implementation for approving transactions
            $_SESSION['success_message'] = 'Transaction approved successfully!';
            break;
            
        case 'reject_transaction':
            // Implementation for rejecting transactions
            $_SESSION['success_message'] = 'Transaction rejected successfully!';
            break;
            
        case 'reverse_transaction':
            // Implementation for reversing transactions
            $_SESSION['success_message'] = 'Transaction reversed successfully!';
            break;
            
        default:
            $_SESSION['error_message'] = 'Invalid action specified.';
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$transaction_type = $_GET['transaction_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$member_id = $_GET['member_id'] ?? '';
$per_page = $_GET['per_page'] ?? 25;

// Mock transaction data (replace with actual database queries)
$transactions = [
    [
        'id' => 1,
        'transaction_id' => 'TXN001',
        'member_id' => 1,
        'member_name' => 'John Doe',
        'type' => 'deposit',
        'amount' => 50000.00,
        'description' => 'Savings deposit',
        'status' => 'completed',
        'created_at' => '2024-01-15 10:30:00',
        'approved_by' => 'Admin User'
    ],
    [
        'id' => 2,
        'transaction_id' => 'TXN002',
        'member_id' => 2,
        'member_name' => 'Jane Smith',
        'type' => 'withdrawal',
        'amount' => 25000.00,
        'description' => 'Emergency withdrawal',
        'status' => 'pending',
        'created_at' => '2024-01-16 14:15:00',
        'approved_by' => null
    ],
    [
        'id' => 3,
        'transaction_id' => 'TXN003',
        'member_id' => 3,
        'member_name' => 'Mike Johnson',
        'type' => 'loan_disbursement',
        'amount' => 100000.00,
        'description' => 'Personal loan disbursement',
        'status' => 'completed',
        'created_at' => '2024-01-17 09:45:00',
        'approved_by' => 'Admin User'
    ],
    [
        'id' => 4,
        'transaction_id' => 'TXN004',
        'member_id' => 1,
        'member_name' => 'John Doe',
        'type' => 'loan_repayment',
        'amount' => 15000.00,
        'description' => 'Monthly loan repayment',
        'status' => 'completed',
        'created_at' => '2024-01-18 16:20:00',
        'approved_by' => 'System Auto'
    ]
];

// Mock statistics (replace with actual calculations)
$statistics = [
    'total_transactions' => count($transactions),
    'total_amount' => array_sum(array_column($transactions, 'amount')),
    'pending_transactions' => count(array_filter($transactions, fn($t) => $t['status'] === 'pending')),
    'completed_today' => 2
];

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Page configuration
$pageConfig = AdminTemplateConfig::getPageConfig('transactions');
$pageTitle = $pageConfig['title'];
$pageDescription = $pageConfig['description'];
$pageIcon = $pageConfig['icon'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    
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
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
                    <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">
                <i class="<?php echo $pageIcon; ?> mr-3" style="color: #214e34;"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p style="color: var(--text-muted);"><?php echo $pageDescription; ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <button type="button" class="btn btn-outline" onclick="exportData()">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline" onclick="printData()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Enhanced Flash Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 icon-success"></i>
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
                        <i class="fas fa-exclamation-circle mr-3 icon-error"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Transactions</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($statistics['total_transactions']); ?></p>
                                <p class="text-xs" style="color: var(--success);">All time</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-exchange-alt text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Amount</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">₦<?php echo number_format($statistics['total_amount'], 2); ?></p>
                                <p class="text-xs" style="color: var(--success);">All transactions</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--success) 0%, var(--lapis-lazuli) 100%);">
                                <i class="fas fa-money-bill-wave text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Pending Approval</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($statistics['pending_transactions']); ?></p>
                                <p class="text-xs" style="color: var(--warning);">Requires attention</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--warning) 0%, #214e34 100%);">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Completed Today</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($statistics['completed_today']); ?></p>
                                <p class="text-xs" style="color: var(--success);">Today's activity</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--success) 0%, #214e34 100%);">
                                <i class="fas fa-check-double text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Filter and Search Section -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-filter mr-2 icon-lapis"></i>
                        Filter & Search
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label for="search" class="form-label">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search icon-muted"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Search transactions..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="transaction_type" class="form-label">Transaction Type</label>
                            <select class="form-control" id="transaction_type" name="transaction_type">
                                <option value="">All Types</option>
                                <option value="deposit" <?php echo $transaction_type === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                <option value="withdrawal" <?php echo $transaction_type === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                                <option value="loan_disbursement" <?php echo $transaction_type === 'loan_disbursement' ? 'selected' : ''; ?>>Loan Disbursement</option>
                                <option value="loan_repayment" <?php echo $transaction_type === 'loan_repayment' ? 'selected' : ''; ?>>Loan Repayment</option>
                                <option value="transfer" <?php echo $transaction_type === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="reversed" <?php echo $status_filter === 'reversed' ? 'selected' : ''; ?>>Reversed</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="per_page" class="form-label">Show</label>
                            <select class="form-control" id="per_page" name="per_page">
                                <option value="15" <?php echo $per_page == 15 ? 'selected' : ''; ?>>15 per page</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 per page</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 per page</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100 per page</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-standard btn-primary">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-standard btn-outline">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Main Content Table -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Transaction History</h3>
                    <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                        <?php echo count($transactions); ?> Total Transactions
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-8" style="color: var(--text-muted);">
                            <i class="fas fa-exchange-alt text-3xl mb-2"></i>
                            <h5>No transactions found</h5>
                            <p>No transactions match your current filters</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="transactionsTable">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Member</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($transaction['transaction_id']); ?></strong>
                                            </td>
                                            <td>
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--lapis-lazuli);">
                                                        <i class="fas fa-user text-white text-xs"></i>
                                                    </div>
                                                    <div>
                                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($transaction['member_name']); ?></strong>
                                                        <br><small style="color: var(--text-muted);">ID: <?php echo $transaction['member_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                                                    <?php echo ucwords(str_replace('_', ' ', $transaction['type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-lg font-semibold" style="color: var(--success);">
                                                    ₦<?php echo number_format($transaction['amount'], 2); ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_color = match($transaction['status']) {
                                                    'completed' => 'var(--success)',
                                                    'pending' => 'var(--warning)',
                                                    'failed' => 'var(--error)',
                                                    'reversed' => 'var(--text-muted)',
                                                    default => 'var(--text-muted)'
                                                };
                                                ?>
                                                <span class="badge" style="background: <?php echo $status_color; ?>; color: white;">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?php echo date('M j, Y H:i', strtotime($transaction['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="flex space-x-1">
                                                    <?php if ($transaction['status'] === 'pending'): ?>
                                                        <button class="btn btn-standard btn-sm btn-outline" onclick="approveTransaction(<?php echo $transaction['id']; ?>)" title="Approve">
                                                            <i class="fas fa-check icon-success"></i>
                                                        </button>
                                                        <button class="btn btn-standard btn-sm btn-outline" onclick="rejectTransaction(<?php echo $transaction['id']; ?>)" title="Reject">
                                                            <i class="fas fa-times icon-error"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($transaction['status'] === 'completed'): ?>
                                                        <button class="btn btn-standard btn-sm btn-outline" onclick="reverseTransaction(<?php echo $transaction['id']; ?>)" title="Reverse">
                                                            <i class="fas fa-undo icon-warning"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>" class="btn btn-standard btn-sm btn-outline" title="View Details">
                                                        <i class="fas fa-eye icon-text-primary"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </main>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../views/includes/footer.php'; ?>
    
    <!-- JavaScript -->
    <script>
        <?php echo AdminTemplateConfig::getCommonJavaScript(); ?>
        
        // Transaction-specific functions
        function approveTransaction(transactionId) {
            if (confirmDelete('Are you sure you want to approve this transaction?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_transaction">
                    <input type="hidden" name="transaction_id" value="${transactionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectTransaction(transactionId) {
            if (confirmDelete('Are you sure you want to reject this transaction?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject_transaction">
                    <input type="hidden" name="transaction_id" value="${transactionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function reverseTransaction(transactionId) {
            if (confirmDelete('Are you sure you want to reverse this transaction? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reverse_transaction">
                    <input type="hidden" name="transaction_id" value="${transactionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function exportData() {
            showAlert('Exporting transaction data...', 'success');
            // Implement actual export functionality
        }
        
        function printData() {
            window.print();
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>