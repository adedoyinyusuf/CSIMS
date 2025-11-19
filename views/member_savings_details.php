<?php
require_once '../config/config.php';
require_once '../controllers/member_controller.php';
require_once '../src/autoload.php';

if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$database = Database::getInstance();
$conn = $database->getConnection();
$memberController = new MemberController();
$member_id = (int)$_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

$account_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($account_id <= 0) {
    header('Location: member_savings.php');
    exit();
}

$accountRepo = new \CSIMS\Repositories\SavingsAccountRepository($conn);
$transactionRepo = new \CSIMS\Repositories\SavingsTransactionRepository($conn);

try {
    $account = $accountRepo->find($account_id);
} catch (Exception $e) {
    $account = null;
}

if (!$account || ($account instanceof \CSIMS\Models\SavingsAccount && $account->getMemberId() !== $member_id)) {
    header('Location: member_savings.php');
    exit();
}

/** @var \CSIMS\Models\SavingsAccount $account */

$transactions = [];
try {
    $transactions = $transactionRepo->getAccountHistory($account_id, 50, 0);
} catch (Exception $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Account Details - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include_once __DIR__ . '/includes/member_header.php'; ?>
    <div class="flex min-h-screen">
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-piggy-bank mr-3 text-primary-600"></i> Savings Account Details
                        </h1>
                        <p class="text-gray-600 mt-1">Account: <?php echo htmlspecialchars($account->getAccountName()); ?> (<?php echo htmlspecialchars($account->getAccountNumber()); ?>)</p>
                    </div>
                    <a href="member_savings.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-lg font-medium">Back to Savings</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow p-6 border-l-4 border-green-500">
                        <p class="text-sm text-gray-500">Current Balance</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($account->getBalance(), 2); ?></p>
                    </div>
                    <div class="bg-white rounded-2xl shadow p-6 border-l-4 border-blue-500">
                        <p class="text-sm text-gray-500">Account Type</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($account->getAccountType()); ?></p>
                    </div>
                    <div class="bg-white rounded-2xl shadow p-6 border-l-4 border-purple-500">
                        <p class="text-sm text-gray-500">Interest Rate</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($account->getInterestRate(), 2); ?>%</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-900">Savings Transactions</h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($transactions)): ?>
                            <p class="text-gray-600">No transactions found for this account.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo $transaction->getTransactionDate()->format('M j, Y'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $transaction->getTransactionType() === 'Deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo htmlspecialchars($transaction->getTransactionType()); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $transaction->getTransactionType() === 'Deposit' ? 'text-green-600' : 'text-red-600'; ?>">
                                                    ₦<?php echo number_format($transaction->getAmount(), 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    ₦<?php echo number_format($transaction->getBalanceAfter(), 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $transaction->getTransactionStatus() === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                        <?php echo htmlspecialchars($transaction->getTransactionStatus()); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>