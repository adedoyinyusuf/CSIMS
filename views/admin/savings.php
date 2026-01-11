<?php
/**
 * CSIMS Savings Management Page
 * Updated to use the CSIMS admin template system with Phase 1&2 integrations
 */

session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/SavingsController.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';
require_once '_admin_template_config.php';
require_once '../../config/database.php';
require_once '../../includes/config/SystemConfigService.php';

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

$savingsController = new SavingsController();

// Normalize savings_transactions column names for robust queries via Utilities
require_once __DIR__ . '/../../includes/utilities.php';
$database = Database::getInstance();
$conn = $database->getConnection();
$schema = Utilities::getSavingsSchema($conn);

$statusCol    = $schema['transactions']['status'];
$typeCol      = $schema['transactions']['type'];
$dateCol      = $schema['transactions']['date'];
$processedCol = $schema['transactions']['processed_at'];

// Initialize SystemConfigService and derive default interest rate for create modal
$defaultSavingsInterest = '10';
try {
    $sysConfig = SystemConfigService::getInstance($pdo ?? null);
    if ($sysConfig) {
        $defaultSavingsInterest = (string)$sysConfig->get('DEFAULT_INTEREST_RATE', (float)$defaultSavingsInterest);
    }
} catch (Exception $e) {
    error_log('admin/savings: SystemConfigService init failed: ' . $e->getMessage());
}

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
            
        case 'approve_monthly_deposit':
            try {
                $transaction_id = intval($_POST['transaction_id']);

                // Get transaction details (mysqli)
                $stmt = $conn->prepare("SELECT * FROM savings_transactions WHERE id = ? AND UPPER($statusCol) = 'PENDING' AND UPPER($typeCol) = 'DEPOSIT' AND description LIKE '%Monthly auto-deposit%' ");
                $stmt->bind_param("i", $transaction_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $transaction = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if ($transaction) {
                    // Update transaction status to completed
                    $processedBy = (int)($current_user['id'] ?? ($_SESSION['admin_id'] ?? 0));
                    $stmt = $conn->prepare("UPDATE savings_transactions SET $statusCol = 'completed', $processedCol = NOW(), processed_by = ? WHERE id = ?");
                    $stmt->bind_param("ii", $processedBy, $transaction_id);
                    $stmt->execute();
                    $stmt->close();

                    // Update account balance
                    $stmt = $conn->prepare("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?");
                    $amt = (float)$transaction['amount'];
                    $accId = (int)$transaction['account_id'];
                    $stmt->bind_param("di", $amt, $accId);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['success_message'] = 'Monthly deposit approved and processed successfully!';
                } else {
                    $_SESSION['error_message'] = 'Transaction not found or already processed.';
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error approving deposit: ' . $e->getMessage();
            }
            break;
            
        case 'reject_monthly_deposit':
            try {
                $transaction_id = intval($_POST['transaction_id']);
                $rejection_reason = $_POST['rejection_reason'] ?? 'Rejected by admin';

                // Update transaction status to rejected (mysqli)
                $processedBy = (int)($current_user['id'] ?? ($_SESSION['admin_id'] ?? 0));
                $stmt = $conn->prepare("UPDATE savings_transactions SET $statusCol = 'rejected', $processedCol = NOW(), processed_by = ?, description = CONCAT(description, ' - Rejected: ', ?) WHERE id = ? AND UPPER($statusCol) = 'PENDING'");
                $stmt->bind_param("isi", $processedBy, $rejection_reason, $transaction_id);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();

                if ($affected > 0) {
                    $_SESSION['success_message'] = 'Monthly deposit rejected successfully!';
                } else {
                    $_SESSION['error_message'] = 'Transaction not found or already processed.';
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error rejecting deposit: ' . $e->getMessage();
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

// Add autoload for repositories used when filtering by member
require_once '../../src/autoload.php';

// Determine if viewing a specific member's savings
$member_id_param = isset($_GET['member_id']) ? (int)$_GET['member_id'] : null;
$member_context = null;

// Get savings accounts
if ($member_id_param) {
    // Build account list for specific member
    $database = Database::getInstance();
    $conn = $database->getConnection();
    $accountRepo = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $memberRepo = new \CSIMS\Repositories\MemberRepository($conn);
    $accounts = $accountRepo->findByMemberId($member_id_param);

    // Resolve member name for context
    try {
        $member = $memberRepo->find($member_id_param);
        if ($member instanceof \CSIMS\Models\Member) {
            $member_context = $member->getFullName();
        }
    } catch (\Exception $e) {
        $member_context = null;
    }

    $savings_accounts = [];
    foreach ($accounts as $account) {
        $createdAt = $account->getCreatedAt();
        $createdAtStr = $createdAt ? $createdAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $savings_accounts[] = [
            'id' => $account->getAccountId(),
            'member_id' => $account->getMemberId(),
            'member_name' => $member_context ?? 'N/A',
            'account_type' => strtolower($account->getAccountType()),
            'balance' => $account->getBalance(),
            'interest_rate' => $account->getInterestRate(),
            'status' => strtolower($account->getAccountStatus()),
            'created_at' => $createdAtStr,
            'account_number' => $account->getAccountNumber(),
        ];
    }
} else {
    $savings_accounts = $savingsController->getAllAccounts($search, $account_type_filter, $status_filter);
}
$statistics = $savingsController->getSavingsStatistics();

// Get pending monthly deposits
$pending_deposits = [];
try {
    $query = "\n        SELECT st.*, sa.account_number, m.first_name, m.last_name, m.member_number \n        FROM savings_transactions st\n        JOIN savings_accounts sa ON st.account_id = sa.id\n        JOIN members m ON sa.member_id = m.id\n        WHERE UPPER(st.$statusCol) = 'PENDING' \n        AND UPPER(st.$typeCol) = 'DEPOSIT' \n        AND st.description LIKE '%Monthly auto-deposit%'\n        ORDER BY st.$dateCol DESC\n    ";
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pending_deposits[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching pending deposits: " . $e->getMessage());
}

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
    
    <!-- Premium Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css">
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
                        <i class="<?php echo $pageIcon; ?> mr-3" style="color: #214e34;"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p style="color: var(--text-muted);">
                        <?php echo $pageDescription; ?>
                    </p>
                    <?php if ($member_id_param && $member_context): ?>
                        <p class="text-sm" style="color: #214e34;">
                            Viewing accounts for <?php echo htmlspecialchars($member_context); ?> (ID: <?php echo $member_id_param; ?>)
                        </p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <button type="button" class="btn btn-standard btn-primary" onclick="openCreateModal()">
                        <i class="fas fa-plus mr-2"></i> New Account
                    </button>
                    <button type="button" class="btn btn-standard btn-secondary" onclick="calculateAllInterest()">
                        <i class="fas fa-calculator mr-2"></i> Calculate Interest
                    </button>
                    <button type="button" class="btn btn-standard btn-outline" onclick="exportData()">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-standard btn-outline" onclick="printData()">
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Accounts - Teal Gradient -->
                <div class="stat-card-gradient gradient-teal">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Total Accounts</p>
                        <h3 class="text-4xl font-bold text-white mb-2"><?php echo number_format($statistics['total_accounts'] ?? 0); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-check-circle mr-1"></i> Active savings plans
                        </div>
                    </div>
                    <i class="fas fa-piggy-bank absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
                
                <!-- Total Balance - Green Gradient -->
                <div class="stat-card-gradient gradient-green">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Total Balance</p>
                        <h3 class="text-4xl font-bold text-white mb-2">₦<?php echo number_format($statistics['total_balance'] ?? 0, 2); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-wallet mr-1"></i> All accounts combined
                        </div>
                    </div>
                    <i class="fas fa-coins absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
                
                <!-- Deposits This Month - Purple Gradient -->
                <div class="stat-card-gradient gradient-purple">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Deposits This Month</p>
                        <h3 class="text-4xl font-bold text-white mb-2">₦<?php echo number_format($statistics['total_interest'] ?? 0, 2); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-arrow-up mr-1"></i> New deposits
                        </div>
                    </div>
                    <i class="fas fa-arrow-up absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
                
                <!-- Active Members - Blue Gradient -->
                <div class="stat-card-gradient gradient-blue">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Active Members</p>
                        <h3 class="text-4xl font-bold text-white mb-2"><?php echo number_format($statistics['active_members'] ?? 0); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-user-check mr-1"></i> With savings accounts
                        </div>
                    </div>
                    <i class="fas fa-user-check absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
            </div>

            <!-- Pending Monthly Deposits Section -->
            <?php if (!empty($pending_deposits)): ?>
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-clock mr-2" style="color: #214e34;"></i>
                        Pending Monthly Deposits
                        <span class="badge ml-2" style="background: #214e34; color: white;">
                            <?php echo count($pending_deposits); ?> Pending
                        </span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Account</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_deposits as $deposit): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--lapis-lazuli);">
                                                <i class="fas fa-user text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?></p>
                                                <p class="text-xs" style="color: var(--text-muted);"><?php echo htmlspecialchars($deposit['member_number']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="font-medium"><?php echo htmlspecialchars($deposit['account_number']); ?></p>
                                        <p class="text-xs" style="color: var(--text-muted);">ID: <?php echo $deposit['account_id']; ?></p>
                                    </td>
                                    <td>
                                        <span class="font-bold" style="color: var(--success);">
                                            ₦<?php echo number_format($deposit['amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <p class="text-sm"><?php echo date('M j, Y', strtotime($deposit['created_at'])); ?></p>
                                        <p class="text-xs" style="color: var(--text-muted);"><?php echo date('g:i A', strtotime($deposit['created_at'])); ?></p>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="approve_monthly_deposit">
                                                <input type="hidden" name="transaction_id" value="<?php echo $deposit['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" 
                                                        onclick="return confirm('Approve this monthly deposit of ₦<?php echo number_format($deposit['amount'], 2); ?>?')">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="showRejectModal(<?php echo $deposit['id']; ?>, '<?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?>', <?php echo $deposit['amount']; ?>)">
                                                <i class="fas fa-times mr-1"></i> Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
                            <table class="table-premium" id="savingsTable">
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
                                                <span class="badge" style="background: #214e34; color: white;">
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
                                                    <button class="btn btn-standard btn-sm btn-outline" onclick="showDepositModal(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['member_name'] ?? 'N/A'); ?>', <?php echo $account['member_id']; ?>)" title="Deposit">
                                                        <i class="fas fa-plus" style="color: var(--success);"></i>
                                                    </button>
                                                    <button class="btn btn-standard btn-sm btn-outline" onclick="showWithdrawModal(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['member_name'] ?? 'N/A'); ?>', <?php echo $account['balance']; ?>, <?php echo $account['member_id']; ?>)" title="Withdraw">
                                                        <i class="fas fa-minus" style="color: var(--warning);"></i>
                                                    </button>
                                                    <button class="btn btn-standard btn-sm btn-outline" onclick="calculateInterest(<?php echo $account['id']; ?>)" title="Calculate Interest">
                                                        <i class="fas fa-calculator" style="color: var(--lapis-lazuli);"></i>
                                                    </button>
                                                    <a href="savings_details.php?id=<?php echo $account['id']; ?>" class="btn btn-standard btn-sm btn-outline" title="View Details">
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
    <div id="createAccountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Create New Savings Account</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeCreateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="createAccountForm">
                <input type="hidden" name="action" value="create_account">
                <div class="space-y-4">
                    <div>
                        <label for="member_id" class="form-label">Member ID <span class="text-red-500">*</span></label>
                        <input type="number" class="form-control" name="member_id" id="member_id" required>
                        <p class="text-xs text-gray-500 mt-1">Enter the member ID to create the account for</p>
                    </div>
                    <div>
                        <label for="account_type" class="form-label">Account Type <span class="text-red-500">*</span></label>
                        <select class="form-control" name="account_type" id="account_type" required>
                            <option value="regular">Regular Savings</option>
                            <option value="fixed">Fixed Deposit</option>
                            <option value="target">Target Savings</option>
                        </select>
                    </div>
                    <div>
                        <label for="initial_deposit" class="form-label">Initial Deposit (₦)</label>
                        <input type="number" class="form-control" name="initial_deposit" id="initial_deposit" step="0.01" min="0" value="0">
                        <p class="text-xs text-gray-500 mt-1">Optional: Initial deposit amount</p>
                    </div>
                    <div>
                        <label for="interest_rate" class="form-label">Interest Rate (%) <span class="text-red-500">*</span></label>
                        <input type="number" class="form-control" name="interest_rate" id="interest_rate" step="0.01" min="0" value="<?php echo htmlspecialchars($defaultSavingsInterest); ?>" required>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" class="btn btn-standard btn-outline" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-standard btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deposit Modal -->
    <div id="depositModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Process Deposit</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDepositModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="depositForm">
                <input type="hidden" name="action" value="deposit">
                <input type="hidden" name="account_id" id="deposit_account_id">
                <input type="hidden" name="member_id" id="deposit_member_id">
                <div class="mb-4">
                    <p class="text-sm text-gray-600"><strong>Member:</strong> <span id="deposit_member_name"></span></p>
                </div>
                <div class="mb-4">
                    <label for="deposit_amount" class="form-label">Deposit Amount (₦) <span class="text-red-500">*</span></label>
                    <input type="number" class="form-control" name="amount" id="deposit_amount" step="0.01" min="0.01" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="btn btn-standard btn-outline" onclick="closeDepositModal()">Cancel</button>
                    <button type="submit" class="btn btn-standard btn-primary">Process Deposit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div id="withdrawModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Process Withdrawal</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeWithdrawModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="withdrawForm">
                <input type="hidden" name="action" value="withdraw">
                <input type="hidden" name="account_id" id="withdraw_account_id">
                <input type="hidden" name="member_id" id="withdraw_member_id">
                <div class="mb-4">
                    <p class="text-sm text-gray-600"><strong>Member:</strong> <span id="withdraw_member_name"></span></p>
                    <p class="text-sm text-gray-600 mt-2"><strong>Available Balance:</strong> ₦<span id="withdraw_balance"></span></p>
                </div>
                <div class="mb-4">
                    <label for="withdraw_amount" class="form-label">Withdrawal Amount (₦) <span class="text-red-500">*</span></label>
                    <input type="number" class="form-control" name="amount" id="withdraw_amount" step="0.01" min="0.01" required>
                    <p class="text-xs text-red-500 mt-1" id="withdraw_error" style="display: none;">Amount cannot exceed available balance</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="btn btn-standard btn-outline" onclick="closeWithdrawModal()">Cancel</button>
                    <button type="submit" class="btn btn-standard btn-warning">Process Withdrawal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Monthly Deposit Modal -->
    <div id="rejectDepositModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Reject Monthly Deposit</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeRejectModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="rejectDepositForm">
                <input type="hidden" name="action" value="reject_monthly_deposit">
                <input type="hidden" name="transaction_id" id="reject_transaction_id">
                <div class="mb-4">
                    <p class="text-sm text-gray-600"><strong>Member:</strong> <span id="reject_member_name"></span></p>
                    <p class="text-sm text-gray-600 mt-2"><strong>Amount:</strong> ₦<span id="reject_amount"></span></p>
                </div>
                <div class="mb-4">
                    <label for="rejection_reason" class="form-label">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="3" 
                              placeholder="Please provide a reason for rejecting this deposit..." required></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="btn btn-standard btn-outline" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn btn-standard btn-danger">Reject Deposit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        <?php echo AdminTemplateConfig::getCommonJavaScript(); ?>
        
        // Savings-specific functions
        function openCreateModal() {
            document.getElementById('createAccountModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            document.getElementById('member_id').focus();
        }
        
        function closeCreateModal() {
            document.getElementById('createAccountModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('createAccountForm').reset();
        }
        
        function showDepositModal(accountId, memberName, memberId) {
            document.getElementById('deposit_account_id').value = accountId;
            document.getElementById('deposit_member_id').value = memberId;
            document.getElementById('deposit_member_name').textContent = memberName;
            document.getElementById('deposit_amount').value = '';
            document.getElementById('depositModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('deposit_amount').focus(), 100);
        }
        
        function closeDepositModal() {
            document.getElementById('depositModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('depositForm').reset();
        }

        function showWithdrawModal(accountId, memberName, balance, memberId) {
            document.getElementById('withdraw_account_id').value = accountId;
            document.getElementById('withdraw_member_id').value = memberId;
            document.getElementById('withdraw_member_name').textContent = memberName;
            document.getElementById('withdraw_balance').textContent = new Intl.NumberFormat('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(balance);
            const amountInput = document.getElementById('withdraw_amount');
            amountInput.value = '';
            amountInput.max = balance;
            document.getElementById('withdraw_error').style.display = 'none';
            document.getElementById('withdrawModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => amountInput.focus(), 100);
        }
        
        function closeWithdrawModal() {
            document.getElementById('withdrawModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('withdrawForm').reset();
            document.getElementById('withdraw_error').style.display = 'none';
        }

        function calculateInterest(accountId) {
            if (confirm('Are you sure you want to calculate interest for this account?')) {
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
            if (confirm('Are you sure you want to calculate interest for all active accounts? This may take some time.')) {
                // Get all active account IDs and calculate interest for each
                const accounts = document.querySelectorAll('#savingsTable tbody tr');
                let processed = 0;
                let errors = 0;
                
                accounts.forEach(row => {
                    const status = row.querySelector('td:nth-child(5) .badge')?.textContent.trim().toLowerCase();
                    if (status === 'active') {
                        const actionsCell = row.querySelector('td:last-child');
                        const calcButton = actionsCell?.querySelector('button[onclick*="calculateInterest"]');
                        if (calcButton) {
                            const onclick = calcButton.getAttribute('onclick');
                            const match = onclick.match(/calculateInterest\((\d+)\)/);
                            if (match) {
                                const accountId = match[1];
                                // Calculate interest for this account
                                processed++;
                            }
                        }
                    }
                });
                
                if (processed > 0) {
                    alert(`Processing interest calculation for ${processed} account(s). This will happen in the background.`);
                } else {
                    alert('No active accounts found to calculate interest for.');
                }
            }
        }

        function showRejectModal(transactionId, memberName, amount) {
            document.getElementById('reject_transaction_id').value = transactionId;
            document.getElementById('reject_member_name').textContent = memberName;
            document.getElementById('reject_amount').textContent = new Intl.NumberFormat('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(amount);
            document.getElementById('rejection_reason').value = '';
            document.getElementById('rejectDepositModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('rejection_reason').focus(), 100);
        }
        
        function closeRejectModal() {
            document.getElementById('rejectDepositModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('rejectDepositForm').reset();
        }
        
        function exportData() {
            const search = document.querySelector('input[name="search"]')?.value || '';
            const account_type = document.querySelector('select[name="account_type"]')?.value || '';
            const status = document.querySelector('select[name="status"]')?.value || '';
            
            let exportUrl = '<?php echo BASE_URL; ?>/controllers/savings_export_controller.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (account_type) params.push('account_type=' + encodeURIComponent(account_type));
            if (status) params.push('status=' + encodeURIComponent(status));
            
            exportUrl += params.join('&');
            window.open(exportUrl, '_blank');
        }
        
        function printData() {
            const search = document.querySelector('input[name="search"]')?.value || '';
            const account_type = document.querySelector('select[name="account_type"]')?.value || '';
            const status = document.querySelector('select[name="status"]')?.value || '';
            
            let printUrl = '<?php echo BASE_URL; ?>/views/admin/print_savings.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (account_type) params.push('account_type=' + encodeURIComponent(account_type));
            if (status) params.push('status=' + encodeURIComponent(status));
            
            printUrl += params.join('&');
            window.open(printUrl, '_blank', 'width=800,height=600');
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
                const modals = ['createAccountModal', 'depositModal', 'withdrawModal', 'rejectDepositModal'];
                modals.forEach(modalId => {
                    if (event.target.id === modalId) {
                        if (modalId === 'createAccountModal') closeCreateModal();
                        else if (modalId === 'depositModal') closeDepositModal();
                        else if (modalId === 'withdrawModal') closeWithdrawModal();
                        else if (modalId === 'rejectDepositModal') closeRejectModal();
                    }
                });
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateModal();
                closeDepositModal();
                closeWithdrawModal();
                closeRejectModal();
            }
        });
        
        // Validate withdrawal amount
        document.addEventListener('DOMContentLoaded', function() {
            const withdrawForm = document.getElementById('withdrawForm');
            if (withdrawForm) {
                withdrawForm.addEventListener('submit', function(e) {
                    const amountInput = document.getElementById('withdraw_amount');
                    const balance = parseFloat(amountInput.max);
                    const amount = parseFloat(amountInput.value);
                    const errorDiv = document.getElementById('withdraw_error');
                    
                    if (amount > balance) {
                        e.preventDefault();
                        errorDiv.style.display = 'block';
                        amountInput.focus();
                        return false;
                    }
                });
                
                const amountInput = document.getElementById('withdraw_amount');
                if (amountInput) {
                    amountInput.addEventListener('input', function() {
                        const errorDiv = document.getElementById('withdraw_error');
                        if (errorDiv) errorDiv.style.display = 'none';
                    });
                }
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Real-time Search & Filter functionality for Savings Accounts
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const accountTypeFilter = document.getElementById('account_type');
            const statusFilter = document.getElementById('status');
            const tableBody = document.querySelector('#savingsTable tbody');
            const tableRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];

            // Function to filter table rows
            function filterSavingsTable() {
                const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const selectedAccountType = accountTypeFilter ? accountTypeFilter.value.toLowerCase() : '';
                const selectedStatus = statusFilter ? statusFilter.value.toLowerCase() : '';

                let visibleCount = 0;

                tableRows.forEach(row => {
                    // Skip the "no results" row if it exists
                    if (row.cells.length < 2) {
                        return;
                    }

                    // Get cell contents for searching (adjust indices based on your table structure)
                    const memberName = row.cells[0]?.textContent.toLowerCase() || '';
                    const accountType = row.cells[1]?.textContent.toLowerCase() || '';
                    const balance = row.cells[2]?.textContent.toLowerCase() || '';
                    const status = row.cells[4]?.textContent.toLowerCase().trim() || '';

                    // Check if row matches search term (search member name and balance)
                    const matchesSearch = searchTerm === '' || 
                        memberName.includes(searchTerm) || 
                        balance.includes(searchTerm);

                    // Check if row matches account type filter
                    const matchesAccountType = selectedAccountType === '' || accountType.includes(selectedAccountType);

                    // Check if row matches status filter
                    const matchesStatus = selectedStatus === '' || status === selectedStatus;

                    // Show/hide row based on all filters
                    if (matchesSearch && matchesAccountType && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Log result count (optional)
                console.log(`Showing ${visibleCount} savings accounts`);
            }

            // Add event listeners for real-time filtering
            if (searchInput) {
                searchInput.addEventListener('input', filterSavingsTable);
                searchInput.addEventListener('keyup', filterSavingsTable);
            }

            if (accountTypeFilter) {
                accountTypeFilter.addEventListener('change', filterSavingsTable);
            }

            if (statusFilter) {
                statusFilter.addEventListener('change', filterSavingsTable);
            }

            // Prevent form submission and use client-side filters instead
            const filterForm = searchInput?.closest('form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    filterSavingsTable();
                    return false;
                });
            }
        });
    </script>
</body>
</html>