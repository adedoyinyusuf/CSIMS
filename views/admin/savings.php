<?php
/**
 * CSIMS Savings Management Page
 * Updated to use the CSIMS admin template system with Phase 1&2 integrations
 */

session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/SavingsController.php';
require_once '../../includes/services/NotificationService.php';
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
$notificationService = new NotificationService();
$businessRulesService = new SimpleBusinessRulesService();

$savingsController = new SavingsController();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_account':
            $result = $savingsController->createAccount(
                $_POST['member_id'],
                $_POST['account_type'],
                floatval($_POST['initial_deposit']),
                floatval($_POST['interest_rate'])
            );
            if ($result) {
                $_SESSION['success_message'] = 'Savings account created successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to create savings account.';
            }
            break;
            
        case 'deposit':
            $result = $savingsController->deposit(
                intval($_POST['account_id']),
                floatval($_POST['amount']),
                intval($_POST['member_id']),
                'Admin deposit by ' . $current_user['first_name']
            );
            if ($result) {
                $_SESSION['success_message'] = 'Deposit processed successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to process deposit.';
            }
            break;
            
        case 'withdraw':
            $result = $savingsController->withdraw(
                intval($_POST['account_id']),
                floatval($_POST['amount']),
                intval($_POST['member_id']),
                'Admin withdrawal by ' . $current_user['first_name']
            );
            if ($result) {
                $_SESSION['success_message'] = 'Withdrawal processed successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to process withdrawal.';
            }
            break;
            
        case 'calculate_interest':
            $result = $savingsController->calculateInterest(intval($_POST['account_id']));
            if ($result) {
                $_SESSION['success_message'] = 'Interest calculated successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to calculate interest.';
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$account_type_filter = $_GET['account_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get savings accounts
$savings_accounts = $savingsController->getAllAccounts($search, $account_type_filter, $status_filter);
$statistics = $savingsController->getSavingsStatistics();

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Page configuration
$pageConfig = AdminTemplateConfig::getPageConfig('savings');
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <!-- Tailwind CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
    <style>
        .balance-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--success);
        }
    </style>
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
                        <i class="<?php echo $pageIcon; ?> mr-3" style="color: var(--persian-orange);"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p style="color: var(--text-muted);"><?php echo $pageDescription; ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                        <i class="fas fa-plus mr-2"></i> New Account
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="calculateAllInterest()">
                        <i class="fas fa-calculator mr-2"></i> Calculate Interest
                    </button>
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

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Accounts</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($statistics['total_accounts'] ?? 0); ?></p>
                                <p class="text-xs" style="color: var(--success);">Active savings plans</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-piggy-bank text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Balance</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">₦<?php echo number_format($statistics['total_balance'] ?? 0, 2); ?></p>
                                <p class="text-xs" style="color: var(--success);">All accounts combined</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--success) 0%, var(--lapis-lazuli) 100%);">
                                <i class="fas fa-coins text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Deposits This Month</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">₦<?php echo number_format($statistics['total_interest'] ?? 0, 2); ?></p>
                                <p class="text-xs" style="color: var(--persian-orange);">New deposits</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--persian-orange) 0%, var(--orange-red) 100%);">
                                <i class="fas fa-arrow-up text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Active Members</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($statistics['active_members'] ?? 0); ?></p>
                                <p class="text-xs" style="color: var(--success);">With savings accounts</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--success) 0%, var(--persian-orange) 100%);">
                                <i class="fas fa-user-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filter and Search Section -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-filter mr-2" style="color: var(--lapis-lazuli);"></i>
                        Filter & Search
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label for="search" class="form-label">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-muted);"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Search members or accounts..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-control" id="account_type" name="account_type">
                                <option value="">All Types</option>
                                <option value="regular" <?php echo $account_type_filter === 'regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="fixed" <?php echo $account_type_filter === 'fixed' ? 'selected' : ''; ?>>Fixed Deposit</option>
                                <option value="target" <?php echo $account_type_filter === 'target' ? 'selected' : ''; ?>>Target Savings</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="per_page" class="form-label">Show</label>
                            <select class="form-control" id="per_page" name="per_page">
                                <option value="15">15 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
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

            <!-- Main Content Table -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Savings Accounts</h3>
                    <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                        <?php echo count($savings_accounts); ?> Total Accounts
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($savings_accounts)): ?>
                        <div class="text-center py-8" style="color: var(--text-muted);">
                            <i class="fas fa-piggy-bank text-3xl mb-2"></i>
                            <h5>No savings accounts found</h5>
                            <p>Create the first savings account to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="savingsTable">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Account Type</th>
                                        <th>Balance</th>
                                        <th>Interest Rate</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($savings_accounts as $account): ?>
                                        <tr>
                                            <td>
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--lapis-lazuli);">
                                                        <i class="fas fa-user text-white text-xs"></i>
                                                    </div>
                                                    <div>
                                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($account['member_name'] ?? 'N/A'); ?></strong>
                                                        <br><small style="color: var(--text-muted);">ID: <?php echo $account['member_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                                                    <?php echo ucwords($account['account_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="balance-amount">₦<?php echo number_format($account['balance'], 2); ?></span>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?php echo number_format($account['interest_rate'], 2); ?>% p.a.
                                            </td>
                                            <td>
                                                <span class="badge" style="background: <?php echo $account['status'] === 'active' ? 'var(--success)' : 'var(--text-muted)'; ?>; color: white;">
                                                    <?php echo ucfirst($account['status']); ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?php echo date('M j, Y', strtotime($account['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="flex space-x-1">
                                                    <button class="btn btn-sm btn-outline" onclick="showDepositModal(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['member_name'] ?? 'N/A'); ?>', <?php echo $account['member_id']; ?>)" title="Deposit">
                                                        <i class="fas fa-plus" style="color: var(--success);"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline" onclick="showWithdrawModal(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['member_name'] ?? 'N/A'); ?>', <?php echo $account['balance']; ?>, <?php echo $account['member_id']; ?>)" title="Withdraw">
                                                        <i class="fas fa-minus" style="color: var(--warning);"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline" onclick="calculateInterest(<?php echo $account['id']; ?>)" title="Calculate Interest">
                                                        <i class="fas fa-calculator" style="color: var(--lapis-lazuli);"></i>
                                                    </button>
                                                    <a href="savings_details.php?id=<?php echo $account['id']; ?>" class="btn btn-sm btn-outline" title="View Details">
                                                        <i class="fas fa-eye" style="color: var(--text-primary);"></i>
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
    
    <!-- Create Account Modal -->
    <div class="modal fade" id="createAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Savings Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_account">
                        <div class="mb-3">
                            <label for="member_id" class="form-label">Member ID</label>
                            <input type="number" class="form-control" name="member_id" required>
                        </div>
                        <div class="mb-3">
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-select" name="account_type" required>
                                <option value="regular">Regular Savings</option>
                                <option value="fixed">Fixed Deposit</option>
                                <option value="target">Target Savings</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="initial_deposit" class="form-label">Initial Deposit (₦)</label>
                            <input type="number" class="form-control" name="initial_deposit" step="0.01" min="0" value="0">
                        </div>
                        <div class="mb-3">
                            <label for="interest_rate" class="form-label">Interest Rate (%)</label>
                            <input type="number" class="form-control" name="interest_rate" step="0.01" min="0" value="10" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deposit Modal -->
    <div class="modal fade" id="depositModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="depositForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="deposit">
                        <input type="hidden" name="account_id" id="deposit_account_id">
                        <input type="hidden" name="member_id" id="deposit_member_id">
                        <p><strong>Member:</strong> <span id="deposit_member_name"></span></p>
                        <div class="mb-3">
                            <label for="deposit_amount" class="form-label">Deposit Amount (₦)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Process Deposit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div class="modal fade" id="withdrawModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="withdrawForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="withdraw">
                        <input type="hidden" name="account_id" id="withdraw_account_id">
                        <input type="hidden" name="member_id" id="withdraw_member_id">
                        <p><strong>Member:</strong> <span id="withdraw_member_name"></span></p>
                        <p><strong>Available Balance:</strong> ₦<span id="withdraw_balance"></span></p>
                        <div class="mb-3">
                            <label for="withdraw_amount" class="form-label">Withdrawal Amount (₦)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Process Withdrawal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        <?php echo AdminTemplateConfig::getCommonJavaScript(); ?>
        
        // Savings-specific functions
        function openCreateModal() {
            // Show create account modal (implement modal opening logic)
            alert('Create Account Modal - to be implemented with proper modal system');
        }
        
        function showDepositModal(accountId, memberName, memberId) {
            document.getElementById('deposit_account_id').value = accountId;
            document.getElementById('deposit_member_id').value = memberId;
            document.getElementById('deposit_member_name').textContent = memberName;
            // Use bootstrap modal or implement custom modal system
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                new bootstrap.Modal(document.getElementById('depositModal')).show();
            } else {
                alert('Deposit Modal - to be implemented with proper modal system');
            }
        }

        function showWithdrawModal(accountId, memberName, balance, memberId) {
            document.getElementById('withdraw_account_id').value = accountId;
            document.getElementById('withdraw_member_id').value = memberId;
            document.getElementById('withdraw_member_name').textContent = memberName;
            document.getElementById('withdraw_balance').textContent = formatCurrency(balance);
            document.querySelector('#withdrawForm input[name="amount"]').max = balance;
            
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                new bootstrap.Modal(document.getElementById('withdrawModal')).show();
            } else {
                alert('Withdraw Modal - to be implemented with proper modal system');
            }
        }

        function calculateInterest(accountId) {
            if (confirmDelete('Are you sure you want to calculate interest for this account?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="calculate_interest">
                    <input type="hidden" name="account_id" value="${accountId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function calculateAllInterest() {
            if (confirmDelete('Are you sure you want to calculate interest for all accounts? This may take some time.')) {
                showAlert('Bulk interest calculation feature coming soon - Individual account calculation is available.', 'info');
            }
        }
        
        function exportData() {
            showAlert('Exporting savings data...', 'success');
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