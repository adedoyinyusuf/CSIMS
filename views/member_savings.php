<?php
// session is initialized via includes/session.php in config.php
require_once '../config/config.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/SavingsController.php';
require_once '../src/autoload.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$database = Database::getInstance();
$conn = $database->getConnection();

$memberController = new MemberController();
$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get savings data
try {
    $savingsRepository = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $transactionRepository = new \CSIMS\Repositories\SavingsTransactionRepository($conn);
    
    $member_savings = $savingsRepository->findByMemberId($member_id);
    $total_balance = $savingsRepository->getTotalBalanceByMember($member_id);
    
    // Get recent transactions
    $recent_transactions = [];
    foreach ($member_savings as $account) {
        $account_transactions = $transactionRepository->getAccountHistory($account->getAccountId(), 5, 0);
        $recent_transactions = array_merge($recent_transactions, $account_transactions);
    }
    
    // Sort by date descending
    usort($recent_transactions, function($a, $b) {
        return strtotime($b->getTransactionDate()->format('Y-m-d H:i:s')) - strtotime($a->getTransactionDate()->format('Y-m-d H:i:s'));
    });
    
    $recent_transactions = array_slice($recent_transactions, 0, 10);
    
} catch (Exception $e) {
    $member_savings = [];
    $total_balance = 0;
    $recent_transactions = [];
}

// Handle form submissions
$errors = [];
$success = false;
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_account') {
        // Handle new savings account creation
        $success = true;
        $action_message = 'New savings account creation request submitted successfully!';
    } elseif ($action === 'deposit') {
        $accountId = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        if ($accountId <= 0) {
            $errors[] = 'Please select a valid account.';
        }
        if ($amount <= 0) {
            $errors[] = 'Please enter a valid deposit amount.';
        }

        if (empty($errors)) {
            try {
                $savingsController = new SavingsController();
                if ($savingsController->deposit($accountId, $amount, $member_id, $description)) {
                    $success = true;
                    $action_message = 'Deposit successful!';
                    // Refresh balances and transactions
                    $member_savings = $savingsRepository->findByMemberId($member_id);
                    $total_balance = $savingsRepository->getTotalBalanceByMember($member_id);
                    $recent_transactions = [];
                    foreach ($member_savings as $account) {
                        $account_transactions = $transactionRepository->getAccountHistory($account->getAccountId(), 5, 0);
                        $recent_transactions = array_merge($recent_transactions, $account_transactions);
                    }
                    usort($recent_transactions, function($a, $b) {
                        return strtotime($b->getTransactionDate()->format('Y-m-d H:i:s')) - strtotime($a->getTransactionDate()->format('Y-m-d H:i:s'));
                    });
                    $recent_transactions = array_slice($recent_transactions, 0, 10);
                } else {
                    $errors[] = 'Deposit failed. Please try again later.';
                }
            } catch (Throwable $t) {
                $errors[] = 'An error occurred while processing the deposit.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Savings - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include_once __DIR__ . '/includes/member_header.php'; ?>
    <div class="flex min-h-screen">
        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-piggy-bank mr-3 text-primary-600"></i> My Savings
                        </h1>
                        <p class="text-gray-600 mt-2">Manage your savings accounts, deposits, and withdrawals</p>
                    </div>
                    <button onclick="openNewAccountModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i> New Savings Account
                    </button>
                </div>
                
                <!-- Flash Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Success!</h3>
                                <p class="mt-2 text-sm text-green-700"><?php echo htmlspecialchars($action_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Summary Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Savings Balance</p>
                                <p class="text-3xl font-bold text-gray-900">₦<?php echo number_format($total_balance, 2); ?></p>
                            </div>
                            <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-piggy-bank text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Active Accounts</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo count($member_savings); ?></p>
                            </div>
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-account-book text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Savings Transactions</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo count($recent_transactions); ?></p>
                            </div>
                            <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Savings Accounts -->
                <div class="bg-white rounded-2xl shadow-lg mb-8">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-900">My Savings Accounts</h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($member_savings)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-piggy-bank text-gray-400 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Savings Accounts Yet</h3>
                                <p class="text-gray-600 mb-6">Start your savings journey by opening your first account.</p>
                                <button onclick="openNewAccountModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i> Open Savings Account
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($member_savings as $account): ?>
                                    <div class="bg-gradient-to-r from-primary-50 to-purple-50 rounded-xl p-6 border border-gray-200">
                                        <div class="flex items-start justify-between mb-4">
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($account->getAccountName()); ?></h3>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($account->getAccountNumber()); ?></p>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-2">
                                                    <?php echo htmlspecialchars($account->getAccountType()); ?>
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($account->getBalance(), 2); ?></p>
                                                <p class="text-sm text-gray-600">Current Balance</p>
                                            </div>
                                        </div>
                                        
                                        <?php if ($account->getAccountType() === 'Target' && $account->getTargetAmount() > 0): ?>
                                            <div class="mb-4">
                                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                    <span>Progress</span>
                                                    <span><?php echo number_format(($account->getBalance() / $account->getTargetAmount()) * 100, 1); ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min(100, ($account->getBalance() / $account->getTargetAmount()) * 100); ?>%"></div>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">Target: ₦<?php echo number_format($account->getTargetAmount(), 2); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex space-x-2">
                                            <a href="member_savings_details.php?id=<?php echo htmlspecialchars($account->getAccountId()); ?>" class="flex-1 bg-white hover:bg-gray-50 text-primary-600 px-4 py-2 rounded-lg text-sm font-medium border border-primary-200 transition-colors duration-200 text-center">
                                                <i class="fas fa-eye mr-1"></i> View Details
                                            </a>
                                            <button onclick="openDepositModal(<?php echo (int)$account->getAccountId(); ?>)" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                                                <i class="fas fa-plus mr-1"></i> Deposit
                                            </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Savings Transactions -->
                <?php if (!empty($recent_transactions)): ?>
                    <div class="bg-white rounded-2xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-xl font-bold text-gray-900">Savings Transactions</h2>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo $transaction->getTransactionDate()->format('M j, Y'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?php echo $transaction->getTransactionType() === 'Deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo htmlspecialchars($transaction->getTransactionType()); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium
                                                    <?php echo $transaction->getTransactionType() === 'Deposit' ? 'text-green-600' : 'text-red-600'; ?>">
                                                    ₦<?php echo number_format($transaction->getAmount(), 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    ₦<?php echo number_format($transaction->getBalanceAfter(), 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?php echo $transaction->getTransactionStatus() === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                        <?php echo htmlspecialchars($transaction->getTransactionStatus()); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Deposit Modal -->
    <div id="depositModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-md w-full">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900">Make a Deposit</h3>
                    <button onclick="closeDepositModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="deposit" />
                    <input type="hidden" id="deposit_account_id" name="account_id" value="" />
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₦)</label>
                        <input type="number" step="0.01" min="0" name="amount" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-200" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" name="description" placeholder="Optional" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-200" />
                    </div>
                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" onclick="closeDepositModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Deposit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentAccountId = null;
        function openNewAccountModal() {
            alert('New account creation feature coming soon!');
        }
        function openDepositModal(accountId) {
            currentAccountId = accountId;
            document.getElementById('deposit_account_id').value = accountId;
            document.getElementById('depositModal').classList.remove('hidden');
        }
        function closeDepositModal() {
            document.getElementById('depositModal').classList.add('hidden');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>