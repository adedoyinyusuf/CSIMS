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
    
    // Get monthly contribution data
    $monthly_contribution = (float)($member['monthly_contribution'] ?? 0);
    $current_month = date('F');
    $current_year = date('Y');
    
    // Check if current month's contribution has been paid
    $current_month_paid = false;
    $current_month_amount = 0;
    $current_month_date = null;
    
    foreach ($recent_transactions as $transaction) {
        $desc = $transaction->getDescription();
        $trans_date = $transaction->getTransactionDate();
        if (strpos($desc, 'IPPIS Deduction - ' . $current_month . ' ' . $current_year) !== false) {
            $current_month_paid = true;
            $current_month_amount = $transaction->getAmount();
            $current_month_date = $trans_date->format('M d, Y');
            break;
        }
    }
    
    // Get contribution history (last 6 months)
    // Get contribution history (last 6 months, but not before Jan 2026)
    $contribution_history = [];
    for ($i = 0; $i < 6; $i++) {
        $check_time = strtotime("-$i months");
        $month_date = date('F Y', $check_time);
        $month_name = date('F', $check_time);
        $year = date('Y', $check_time);
        
        // System Launch: Jan 2026. Do not show history before this date.
        if ((int)$year < 2026) {
            continue;
        }
        
        $paid = false;
        $amount = 0;
        $payment_date = null;
        
        foreach ($recent_transactions as $transaction) {
            $desc = $transaction->getDescription();
            if (strpos($desc, 'IPPIS Deduction - ' . $month_name . ' ' . $year) !== false) {
                $paid = true;
                $amount = $transaction->getAmount();
                $payment_date = $transaction->getTransactionDate()->format('M d, Y');
                break;
            }
        }
        
        // Determine status
        $status = 'missed';
        $status_label = 'Not Paid';
        
        if ($paid) {
            $status = ($amount >= $monthly_contribution) ? 'full' : 'partial';
            $status_label = $payment_date;
        } else {
            // If it's the current month and not paid, check if we are past the 28th (Automation Date)
            // Actually, simpler: if current month, say "Pending"
            if ($month_name === $current_month && $year === $current_year) {
                $status = 'pending';
                $status_label = 'Awaiting IPPIS';
            }
        }
        
        $contribution_history[] = [
            'month' => $month_name,
            'year' => $year,
            'month_date' => $month_date,
            'paid' => $paid,
            'amount' => $amount,
            'expected' => $monthly_contribution,
            'payment_date' => $payment_date,
            'status' => $status,
            'status_label' => $status_label
        ];
    }
    
    // Calculate year-to-date contributions
    $ytd_contributions = 0;
    foreach ($recent_transactions as $transaction) {
        if (strpos($transaction->getDescription(), 'IPPIS Deduction') !== false &&
            strpos($transaction->getDescription(), $current_year) !== false) {
            $ytd_contributions += $transaction->getAmount();
        }
    }
    
} catch (Exception $e) {
    $member_savings = [];
    $total_balance = 0;
    $recent_transactions = [];
    $monthly_contribution = 0;
    $contribution_history = [];
    $ytd_contributions = 0;
}

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    CSRFProtection::validateRequest();
    
    $account_id = (int)$_POST['withdrawal_account_id'];
    $amount = (float)$_POST['withdrawal_amount'];
    $reason = trim($_POST['withdrawal_reason']);
    
    if ($account_id <= 0) {
        $errors[] = 'Please select a valid account.';
    }
    if ($amount <= 0) {
        $errors[] = 'Please enter a valid withdrawal amount.';
    }
    if (empty($reason)) {
        $errors[] = 'Please provide a reason for withdrawal.';
    }
    
    if (empty($errors)) {
        try {
            // Create withdrawal request
            $stmt = $conn->prepare("
                INSERT INTO savings_withdrawal_requests 
                (member_id, account_id, amount, reason, status, request_date, created_at)
                VALUES (?, ?, ?, ?, 'Pending', NOW(), NOW())
            ");
            $stmt->bind_param('iids', $member_id, $account_id, $amount, $reason);
            
            if ($stmt->execute()) {
                $success = true;
                $action_message = 'Withdrawal request submitted successfully! Awaiting admin approval.';
            } else {
                $errors[] = 'Failed to submit withdrawal request. Please try again.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = 'An error occurred while submitting your request.';
        }
    }
}

// Get pending withdrawal requests
$pending_withdrawals = [];
try {
    $stmt = $conn->prepare("
        SELECT wr.*, sa.account_name, sa.account_number  
        FROM savings_withdrawal_requests wr
        JOIN savings_accounts sa ON wr.account_id = sa.account_id
        WHERE wr.member_id = ?
        ORDER BY wr.request_date DESC
        LIMIT 10
    ");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_withdrawals[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Silently fail - table might not exist yet
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
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd',
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9',
                            600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e'
                        },
                        secondary: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0',
                            300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b',
                            600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-secondary-50 text-secondary-900">
    <?php include_once __DIR__ . '/includes/member_header.php'; ?>
    
    <div class="flex min-h-screen">
        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <!-- Page Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-secondary-900">My Savings</h1>
                        <p class="text-secondary-500 mt-1">Overview of your savings portfolio</p>
                    </div>
                    <!-- Optional: Add a subtle date display or quick action -->
                    <div class="mt-4 md:mt-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white text-secondary-600 shadow-sm border border-secondary-200">
                            <i class="fas fa-calendar-alt mr-2 text-primary-500"></i> <?php echo date('F d, Y'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-md shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Attention needed</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-md shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($action_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Top Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total Balance -->
                    <div class="bg-white rounded-xl shadow-sm border border-secondary-100 p-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-secondary-500">Total Savings Balance</p>
                            <p class="text-3xl font-bold text-secondary-900 mt-1">₦<?php echo number_format($total_balance, 2); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-primary-50 rounded-full flex items-center justify-center text-primary-600">
                            <i class="fas fa-wallet text-xl"></i>
                        </div>
                    </div>
                    
                    <!-- Monthly Commitment -->
                    <div class="bg-white rounded-xl shadow-sm border border-secondary-100 p-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-secondary-500">Monthly Commitment</p>
                            <p class="text-3xl font-bold text-secondary-900 mt-1">₦<?php echo number_format($monthly_contribution, 2); ?></p>
                            <a href="member_profile.php#monthly_contribution" class="text-xs text-primary-600 hover:text-primary-700 font-medium mt-1 inline-block">
                                Update changes <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-full flex items-center justify-center text-green-600">
                            <i class="fas fa-calendar-check text-xl"></i>
                        </div>
                    </div>
                    
                    <!-- YTD Contributions -->
                    <div class="bg-white rounded-xl shadow-sm border border-secondary-100 p-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-secondary-500">YTD Contributions</p>
                            <p class="text-3xl font-bold text-secondary-900 mt-1">₦<?php echo number_format($ytd_contributions, 2); ?></p>
                            <p class="text-xs text-secondary-400 mt-1">For <?php echo $current_year; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-50 rounded-full flex items-center justify-center text-purple-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left Column (Funds & Accounts) -->
                    <div class="lg:col-span-2 space-y-8">
                        
                        <!-- Savings Accounts List -->
                        <div class="bg-white rounded-xl shadow-sm border border-secondary-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-secondary-100 flex justify-between items-center">
                                <h2 class="text-lg font-semibold text-secondary-900">Your Accounts</h2>
                                <!-- Open Account Button removed as per IPPIS logic -->
                            </div>
                            
                            <div class="p-6">
                                <?php if (empty($member_savings)): ?>
                                    <div class="text-center py-8">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-secondary-100 mb-4">
                                            <i class="fas fa-piggy-bank text-secondary-400"></i>
                                        </div>
                                        <h3 class="text-sm font-medium text-secondary-900">No accounts found</h3>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <?php foreach ($member_savings as $account): ?>
                                            <!-- Modern Account Card -->
                                            <div class="group relative bg-white border border-secondary-200 rounded-xl p-5 hover:shadow-md transition-shadow duration-300">
                                                <div class="flex justify-between items-start mb-4">
                                                    <div>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-50 text-primary-700 mb-2">
                                                            <?php echo htmlspecialchars($account->getAccountType()); ?>
                                                        </span>
                                                        <h3 class="text-base font-semibold text-secondary-900 truncate">
                                                            <?php echo htmlspecialchars($account->getAccountName()); ?>
                                                        </h3>
                                                        <p class="text-xs text-secondary-500 font-mono mt-1">
                                                            **** <?php echo substr($account->getAccountNumber(), -4); ?>
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-xl font-bold text-secondary-900">₦<?php echo number_format($account->getBalance(), 2); ?></p>
                                                    </div>
                                                </div>
                                                
                                                <div class="border-t border-secondary-100 pt-4 flex space-x-3">
                                                    <button onclick="openDepositModal(<?php echo (int)$account->getAccountId(); ?>)" class="flex-1 text-center bg-secondary-900 hover:bg-secondary-800 text-white text-xs font-medium py-2 rounded-lg transition-colors">
                                                        Add Funds
                                                    </button>
                                                    <a href="member_savings_details.php?id=<?php echo htmlspecialchars($account->getAccountId()); ?>" class="flex-1 text-center bg-white border border-secondary-300 hover:bg-secondary-50 text-secondary-700 text-xs font-medium py-2 rounded-lg transition-colors">
                                                        History
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Contribution History (Timeline) -->
                        <div class="bg-white rounded-xl shadow-sm border border-secondary-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-secondary-100">
                                <h2 class="text-lg font-semibold text-secondary-900">Contribution Timeline</h2>
                            </div>
                            <div class="p-6">
                                <?php if (empty($contribution_history)): ?>
                                    <p class="text-sm text-secondary-500 text-center py-4">No contribution history available.</p>
                                <?php else: ?>
                                    <div class="flow-root">
                                        <ul role="list" class="-mb-8">
                                            <?php foreach ($contribution_history as $index => $history): ?>
                                                <li>
                                                    <div class="relative pb-8">
                                                        <?php if ($index !== count($contribution_history) - 1): ?>
                                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-secondary-200" aria-hidden="true"></span>
                                                        <?php endif; ?>
                                                        <div class="relative flex space-x-3">
                                                            <div>
                                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white 
                                                                    <?php 
                                                                    if($history['status'] === 'full') echo 'bg-green-100 text-green-600';
                                                                    elseif($history['status'] === 'partial') echo 'bg-yellow-100 text-yellow-600';
                                                                    elseif($history['status'] === 'pending') echo 'bg-blue-100 text-blue-600';
                                                                    else echo 'bg-red-100 text-red-600';
                                                                    ?>">
                                                                    <?php 
                                                                    if($history['status'] === 'full') echo '<i class="fas fa-check"></i>';
                                                                    elseif($history['status'] === 'partial') echo '<i class="fas fa-minus"></i>';
                                                                    elseif($history['status'] === 'pending') echo '<i class="fas fa-clock"></i>';
                                                                    else echo '<i class="fas fa-times"></i>';
                                                                    ?>
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                                <div>
                                                                    <p class="text-sm font-medium text-secondary-900">
                                                                        <?php echo $history['month_date']; ?>
                                                                        <span class="font-normal text-secondary-500 ml-2">
                                                                            <?php echo $history['status'] === 'pending' ? 'Automation Scheduled' : $history['status_label']; ?>
                                                                        </span>
                                                                    </p>
                                                                </div>
                                                                <div class="text-right text-sm whitespace-nowrap text-secondary-500">
                                                                    <?php if ($history['paid']): ?>
                                                                        <span class="font-bold text-secondary-900">₦<?php echo number_format($history['amount'], 2); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="text-secondary-400">---</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Right Column (Withdrawal & Requests) -->
                    <div class="space-y-8">
                        
                        <!-- Request Withdrawal Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-secondary-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-secondary-100">
                                <h2 class="text-lg font-semibold text-secondary-900">Request Withdrawal</h2>
                            </div>
                            <div class="p-6">
                                <form method="POST" class="space-y-4">
                                    <?php echo CSRFProtection::getTokenField(); ?>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-secondary-700 mb-1 uppercase tracking-wider">Source Account</label>
                                        <select name="withdrawal_account_id" class="block w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm pt-2 pb-2" required>
                                            <option value="">Select an account</option>
                                            <?php foreach ($member_savings as $account): ?>
                                                <option value="<?php echo $account->getAccountId(); ?>">
                                                    <?php echo htmlspecialchars($account->getAccountName()); ?> (₦<?php echo number_format($account->getBalance(), 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-secondary-700 mb-1 uppercase tracking-wider">Amount</label>
                                        <div class="relative rounded-md shadow-sm">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span class="text-secondary-500 sm:text-sm">₦</span>
                                            </div>
                                            <input type="number" name="withdrawal_amount" step="0.01" min="0" class="block w-full rounded-lg border-secondary-300 pl-8 focus:border-primary-500 focus:ring-primary-500 sm:text-sm pt-2 pb-2" placeholder="0.00" required>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-secondary-700 mb-1 uppercase tracking-wider">Reason</label>
                                        <textarea name="withdrawal_reason" rows="3" class="block w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm p-3" placeholder="Briefly describe why..." required></textarea>
                                    </div>

                                    <div class="pt-2">
                                        <button type="submit" name="request_withdrawal" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                            Submit Request
                                        </button>
                                        <p class="mt-2 text-center text-xs text-secondary-500">
                                            <i class="fas fa-info-circle mr-1"></i> Subject to admin approval (3-5 days)
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Pending Requests -->
                        <?php if (!empty($pending_withdrawals)): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-secondary-100 overflow-hidden">
                                <div class="px-6 py-4 border-b border-secondary-100 bg-secondary-50">
                                    <h2 class="text-sm font-semibold text-secondary-900 uppercase tracking-wide">Recent Requests</h2>
                                </div>
                                <div class="divide-y divide-secondary-100">
                                    <?php foreach ($pending_withdrawals as $withdrawal): ?>
                                        <div class="p-4 hover:bg-secondary-50 transition-colors">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <p class="text-sm font-medium text-secondary-900">₦<?php echo number_format($withdrawal['amount'], 2); ?></p>
                                                    <p class="text-xs text-secondary-500"><?php echo htmlspecialchars($withdrawal['account_name']); ?></p>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                    <?php 
                                                    echo $withdrawal['status'] === 'Approved' ? 'bg-green-100 text-green-800' : 
                                                         ($withdrawal['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 
                                                         'bg-yellow-100 text-yellow-800'); 
                                                    ?>">
                                                    <?php echo htmlspecialchars($withdrawal['status']); ?>
                                                </span>
                                            </div>
                                            <div class="mt-1 flex justify-between items-center text-xs text-secondary-400">
                                                <span><?php echo date('M d, Y', strtotime($withdrawal['request_date'])); ?></span>
                                                <span>#<?php echo $withdrawal['request_id']; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <!-- Recent Transactions Table (Full Width) -->
                <?php if (!empty($recent_transactions)): ?>
                    <div class="mt-8">
                        <h2 class="text-lg font-bold text-secondary-900 mb-4">Recent Activity</h2>
                        <div class="bg-white rounded-xl shadow-sm border border-secondary-100 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-secondary-200">
                                    <thead class="bg-secondary-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">Description</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-secondary-500 uppercase tracking-wider">Amount</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-secondary-500 uppercase tracking-wider">Balance</th>
                                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-secondary-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-secondary-200">
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                            <tr class="hover:bg-secondary-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-900">
                                                    <?php echo $transaction->getTransactionDate()->format('d M, Y'); ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-secondary-600 max-w-xs truncate">
                                                    <?php echo htmlspecialchars($transaction->getDescription() ?: 'Transaction'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        <?php echo $transaction->getTransactionType() === 'Deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo htmlspecialchars($transaction->getTransactionType()); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
                                                    <?php echo $transaction->getTransactionType() === 'Deposit' ? 'text-green-600' : 'text-secondary-900'; ?>">
                                                    <?php echo $transaction->getTransactionType() === 'Deposit' ? '+' : '-'; ?>
                                                    ₦<?php echo number_format($transaction->getAmount(), 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-secondary-500">
                                                    ₦<?php echo number_format($transaction->getBalanceAfter(), 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <?php if ($transaction->getTransactionStatus() === 'Completed'): ?>
                                                        <i class="fas fa-check-circle text-green-500"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-clock text-yellow-500"></i>
                                                    <?php endif; ?>
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
        </main>
    </div>

    <!-- Deposit Modal -->
    <div id="depositModal" class="fixed inset-0 bg-secondary-900 bg-opacity-75 hidden z-50 transition-opacity" style="backdrop-filter: blur(4px);">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full transform transition-all scale-100">
                <div class="p-5 border-b border-secondary-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-secondary-900">Make a Deposit</h3>
                    <button onclick="closeDepositModal()" class="text-secondary-400 hover:text-secondary-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="deposit" />
                    <input type="hidden" id="deposit_account_id" name="account_id" value="" />
                    
                    <div>
                        <label class="block text-xs font-medium text-secondary-700 mb-1 uppercase tracking-wider">Amount (₦)</label>
                        <input type="number" step="0.01" min="0" name="amount" class="block w-full rounded-lg border-secondary-300 focus:border-primary-500 focus:ring-primary-500 sm:text-sm py-2" placeholder="0.00" required />
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-secondary-700 mb-1 uppercase tracking-wider">Description</label>
                        <input type="text" name="description" placeholder="Optional" class="block w-full rounded-lg border-secondary-300 focus:border-primary-500 focus:ring-primary-500 sm:text-sm py-2" />
                    </div>
                    
                    <div class="pt-4 flex space-x-3">
                        <button type="button" onclick="closeDepositModal()" class="flex-1 px-4 py-2 border border-secondary-300 rounded-lg text-sm font-medium text-secondary-700 hover:bg-secondary-50">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 shadow-sm">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Logic
        function openDepositModal(accountId) {
            document.getElementById('deposit_account_id').value = accountId;
            document.getElementById('depositModal').classList.remove('hidden');
        }
        function closeDepositModal() {
            document.getElementById('depositModal').classList.add('hidden');
        }
        
        // Close modal on click outside
        window.onclick = function(event) {
            const modal = document.getElementById('depositModal');
            if (event.target == modal) {
                closeDepositModal();
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>