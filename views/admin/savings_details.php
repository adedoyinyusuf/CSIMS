<?php
/**
 * Savings Account Details Page
 * Shows detailed information about a specific savings account
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/SavingsController.php';
require_once '../../includes/utilities.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Check if account ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'Account ID is required';
    header("Location: savings.php");
    exit();
}

$account_id = (int)$_GET['id'];

// Initialize controllers
$savingsController = new SavingsController();
$database = Database::getInstance();
$conn = $database->getConnection();
$schema = Utilities::getSavingsSchema($conn);

// Get account details
$accountRepo = new \CSIMS\Repositories\SavingsAccountRepository($conn);
/** @var \CSIMS\Models\SavingsAccount|null $account */
$account = $accountRepo->find($account_id);

if (!$account) {
    $_SESSION['error_message'] = 'Savings account not found';
    header("Location: savings.php");
    exit();
}

// Get member details
$memberRepo = new \CSIMS\Repositories\MemberRepository($conn);
/** @var \CSIMS\Models\Member|null $member */
$member = $memberRepo->find($account->getMemberId());

// Get transaction history
$transactionRepo = new \CSIMS\Repositories\SavingsTransactionRepository($conn);
$transactions = $transactionRepo->getAccountHistory($account_id, 50, 0);

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Account Details - <?php echo APP_NAME; ?></title>
    <!-- Premium Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-admin">
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
<h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">
    <i class="fas fa-piggy-bank mr-3" style="color: #214e34;"></i>
    Savings Account Details
</h1>
                    <p style="color: var(--text-muted);">Account #<?php echo htmlspecialchars($account->getAccountNumber()); ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" class="btn btn-standard btn-outline">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Savings
                    </a>
                    <button type="button" class="btn btn-standard btn-primary" onclick="window.print()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in mb-6">
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
                <div class="alert alert-error flex items-center justify-between animate-slide-in mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 icon-error"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Account Information Card -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">Account Information</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="form-label text-xs" style="color: var(--text-muted);">Account Number</label>
                            <p class="text-lg font-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($account->getAccountNumber()); ?></p>
                        </div>
                        <div>
                            <label class="form-label text-xs" style="color: var(--text-muted);">Account Type</label>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                                    <?php echo ucwords($account->getAccountType()); ?>
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="form-label text-xs" style="color: var(--text-muted);">Account Status</label>
                            <p class="text-lg font-semibold">
                                <span class="badge" style="background: <?php echo $account->getAccountStatus() === 'active' ? 'var(--success)' : 'var(--text-muted)'; ?>; color: white;">
                                    <?php echo ucfirst($account->getAccountStatus()); ?>
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="form-label text-xs" style="color: var(--text-muted);">Member</label>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                <?php 
                                    if ($member) {
                                        echo htmlspecialchars($member->getFullName());
                                        echo ' <small style="color: var(--text-muted);">(ID: ' . $account->getMemberId() . ')</small>';
                                    } else {
                                        echo 'Member ID: ' . $account->getMemberId();
                                    }
                                ?>
                            </p>
                            <?php if ($member): ?>
                                <a href="view_member.php?id=<?php echo $account->getMemberId(); ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                    View Member Profile →
                                </a>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label text-xs" style="color: var(--text-muted);">Current Balance</label>
                            <p class="text-2xl font-bold" style="color: var(--success);">
                                ₦<?php echo number_format($account->getBalance(), 2); ?>
                            </p>
                        </div>
                        <div>
                            <label class="form-label text-xs" style="color: var(--text-muted);">Interest Rate</label>
                            <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                <?php echo number_format($account->getInterestRate(), 2); ?>% per annum
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Transaction History</h3>
                    <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                        <?php echo count($transactions); ?> Transactions
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-8" style="color: var(--text-muted);">
                            <i class="fas fa-history text-3xl mb-2"></i>
                            <p>No transactions found for this account</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-premium" id="transactionsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Balance After</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo $transaction->getTransactionDate()->format('M d, Y H:i'); ?></td>
                                            <td>
                                                <span class="badge <?php echo strtolower($transaction->getTransactionType()) === 'deposit' ? 'badge-success' : 'badge-warning'; ?>">
                                                    <?php echo ucfirst($transaction->getTransactionType()); ?>
                                                </span>
                                            </td>
                                            <td class="font-bold <?php echo strtolower($transaction->getTransactionType()) === 'deposit' ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo strtolower($transaction->getTransactionType()) === 'deposit' ? '+' : '-'; ?>₦<?php echo number_format($transaction->getAmount(), 2); ?>
                                            </td>
                                            <td class="font-semibold">₦<?php echo number_format($transaction->getBalanceAfter(), 2); ?></td>
                                            <td>
                                                <span class="badge <?php echo strtolower($transaction->getTransactionStatus()) === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                                    <?php echo ucfirst($transaction->getTransactionStatus()); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction->getDescription() ?? 'N/A'); ?></td>
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
    
    <?php include '../../views/includes/footer.php'; ?>
</body>
</html>

