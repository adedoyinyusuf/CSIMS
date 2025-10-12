<?php
session_start();
require_once '../config/config.php';
require_once '../controllers/member_controller.php';
require_once '../src/autoload.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

// Initialize database and services
$database = Database::getInstance();
$conn = $database->getConnection();

$memberController = new MemberController();
$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

if (!$member) {
    header('Location: member_login.php');
    exit();
}

// Get member's savings accounts and transactions
try {
    $savingsRepository = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $transactionRepository = new \CSIMS\Repositories\SavingsTransactionRepository($conn);
    
    $savings_accounts = $savingsRepository->findByMemberId($member_id);
    $recent_transactions = [];
    
    foreach ($savings_accounts as $account) {
        $account_transactions = $transactionRepository->getAccountHistory($account->getAccountId(), 5, 0);
        $recent_transactions = array_merge($recent_transactions, $account_transactions);
    }
    
    // Sort by date descending
    usort($recent_transactions, function($a, $b) {
        return strtotime($b->getTransactionDate()->format('Y-m-d H:i:s')) - strtotime($a->getTransactionDate()->format('Y-m-d H:i:s'));
    });
    
    $recent_transactions = array_slice($recent_transactions, 0, 10);
} catch (Exception $e) {
    $savings_accounts = [];
    $recent_transactions = [];
    error_log('Error loading savings data: ' . $e->getMessage());
}

// Calculate summary statistics
$total_balance = 0;
$total_interest_earned = 0;
$active_accounts = 0;

foreach ($savings_accounts as $account) {
    $total_balance += $account->getBalance();
    $total_interest_earned += $account->getTotalInterestEarned() ?? 0;
    if ($account->getAccountStatus() === 'Active') {
        $active_accounts++;
    }
}

$update_message = '';
$update_error = '';

// Handle deposit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_amount'])) {
    $account_id = intval($_POST['account_id']);
    $amount = floatval($_POST['deposit_amount']);
    
    if ($amount >= 1000) {
        $result = $savingsController->deposit($account_id, $amount, $member_id);
        if ($result) {
            $update_message = 'Deposit of ₦' . number_format($amount, 2) . ' made successfully!';
            // Refresh data
            $savings_accounts = $savingsController->getMemberSavingsAccounts($member_id) ?? [];
            $recent_transactions = $savingsController->getMemberRecentTransactions($member_id) ?? [];
        } else {
            $update_error = 'Failed to process deposit. Please try again.';
        }
    } else {
        $update_error = 'Minimum deposit amount is ₦1,000.';
    }
}

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_amount'])) {
    $account_id = intval($_POST['account_id']);
    $amount = floatval($_POST['withdraw_amount']);
    
    if ($amount >= 500) {
        $result = $savingsController->withdraw($account_id, $amount, $member_id);
        if ($result) {
            $update_message = 'Withdrawal of ₦' . number_format($amount, 2) . ' processed successfully!';
            // Refresh data
            $savings_accounts = $savingsController->getMemberSavingsAccounts($member_id) ?? [];
            $recent_transactions = $savingsController->getMemberRecentTransactions($member_id) ?? [];
        } else {
            $update_error = 'Failed to process withdrawal. Please check your balance and try again.';
        }
    } else {
        $update_error = 'Minimum withdrawal amount is ₦500.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Savings - NPC CTLStaff Loan Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.15);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 32px rgba(40, 167, 69, 0.25);
        }
        .stat-card h4 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0;
        }
        .stat-card i {
            margin-bottom: 0.5rem;
        }
        .account-card {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .account-card:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 767px) {
            .stat-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-university"></i> Member Portal
                    </h4>
                    
                    <div class="mb-3">
                        <small class="text-white-50">Welcome,</small>
                        <div class="text-white fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="member_dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="member_profile.php">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a class="nav-link" href="member_loans.php">
                            <i class="fas fa-money-bill-wave me-2"></i> My Loans
                        </a>
                        <a class="nav-link active" href="member_savings.php">
                            <i class="fas fa-piggy-bank me-2"></i> My Savings
                        </a>
                        <a class="nav-link" href="member_notifications.php">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                        <a class="nav-link" href="member_loan_application.php">
                            <i class="fas fa-plus-circle me-2"></i> Apply for Loan
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <a class="nav-link" href="member_logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-piggy-bank me-2"></i> My Savings</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAccountModal">
                            <i class="fas fa-plus me-2"></i> Open New Account
                        </button>
                    </div>
                    
                    <?php if (!empty($update_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($update_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($update_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($update_error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Savings Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-wallet fa-2x"></i>
                                    <h4>₦<?php echo number_format($total_balance, 2); ?></h4>
                                    <p>Total Balance</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                    <h4>₦<?php echo number_format($total_interest_earned, 2); ?></h4>
                                    <p>Interest Earned</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-check fa-2x"></i>
                                    <h4><?php echo $active_accounts; ?></h4>
                                    <p>Active Accounts</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Savings Accounts -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-university me-2"></i> My Savings Accounts</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($savings_accounts)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-piggy-bank fa-3x text-muted mb-3"></i>
                                            <h5>No Savings Accounts Yet</h5>
                                            <p class="text-muted">Click "Open New Account" to start saving</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($savings_accounts as $account): ?>
                                            <div class="card account-card mb-3">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-3">
                                                            <h6 class="text-muted mb-1">Account Type</h6>
                                                            <span class="badge bg-primary"><?php echo ucwords($account['account_type']); ?></span>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <h6 class="text-muted mb-1">Balance</h6>
                                                            <h5 class="text-success mb-0">₦<?php echo number_format($account['balance'], 2); ?></h5>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <h6 class="text-muted mb-1">Interest Rate</h6>
                                                            <span class="text-info"><?php echo number_format($account['interest_rate'], 2); ?>% p.a.</span>
                                                        </div>
                                                        <div class="col-md-3 text-end">
                                                            <button class="btn btn-sm btn-success me-2" data-bs-toggle="modal" data-bs-target="#depositModal<?php echo $account['id']; ?>">
                                                                <i class="fas fa-plus"></i> Deposit
                                                            </button>
                                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#withdrawModal<?php echo $account['id']; ?>">
                                                                <i class="fas fa-minus"></i> Withdraw
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Deposit Modal -->
                                            <div class="modal fade" id="depositModal<?php echo $account['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Make Deposit</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="deposit_amount" class="form-label">Amount</label>
                                                                    <input type="number" class="form-control" name="deposit_amount" min="1000" step="0.01" required>
                                                                    <div class="form-text">Minimum deposit: ₦1,000</div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success">Make Deposit</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Withdraw Modal -->
                                            <div class="modal fade" id="withdrawModal<?php echo $account['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Make Withdrawal</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="withdraw_amount" class="form-label">Amount</label>
                                                                    <input type="number" class="form-control" name="withdraw_amount" min="500" max="<?php echo $account['balance']; ?>" step="0.01" required>
                                                                    <div class="form-text">Available balance: ₦<?php echo number_format($account['balance'], 2); ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-warning">Make Withdrawal</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Transactions -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Transactions</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_transactions)): ?>
                                        <p class="text-muted">No recent transactions</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach (array_slice($recent_transactions, 0, 5) as $transaction): ?>
                                                <div class="list-group-item border-0 px-0">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo ucfirst($transaction['transaction_type']); ?></h6>
                                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></small>
                                                        </div>
                                                        <span class="text-<?php echo $transaction['transaction_type'] === 'deposit' ? 'success' : 'danger'; ?>">
                                                            <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>₦<?php echo number_format($transaction['amount'], 2); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Account Modal -->
    <div class="modal fade" id="newAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Open New Savings Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Contact the administration to open a new savings account.</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Visit the office or call our hotline to start the account opening process.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>