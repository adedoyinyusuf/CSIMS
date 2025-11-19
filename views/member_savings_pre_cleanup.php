<?php
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/message_controller.php';

require_once '../src/autoload.php';
if (!defined('SAVINGS_CONTROLLER_INCLUDED')) { define('SAVINGS_CONTROLLER_INCLUDED', true); }
require_once '../controllers/SavingsController.php';
$savingsController = new SavingsController();

// Initialize database and services
$database = Database::getInstance();
$conn = $database->getConnection();

$memberController = new MemberController();
$member_id = $_SESSION['member_id'] ?? ((($_SESSION['user_type'] ?? '') === 'member') ? ($_SESSION['user_id'] ?? null) : null);
$member = $memberController->getMemberById($member_id);

$messageController = new MessageController();
$admins = $messageController->getAdmins();

if (!$member) {
    // Debug logging to trace redirect cause
    try {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'member_id_used' => $member_id,
            'session' => [
                'user_type' => $_SESSION['user_type'] ?? null,
                'member_id' => $_SESSION['member_id'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null,
                'last_activity' => $_SESSION['last_activity'] ?? null,
                'user_ip' => $_SESSION['user_ip'] ?? null,
                'user_agent' => $_SESSION['user_agent'] ?? null,
            ],
        ];
        @file_put_contents($logDir . '/savings_debug.log', json_encode($debug) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $t) { /* ignore logging errors */ }
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
    /** @var \CSIMS\Models\SavingsAccount $account */
    $total_balance += $account->getBalance();
    // Sum interest credited transactions for this account
    $interestStats = $transactionRepository->getTransactionStatistics([
        'transaction_type' => 'Interest',
        'account_id' => $account->getAccountId(),
    ]);
    $accountInterestTotal = 0.0;
    foreach ($interestStats as $row) {
        $status = $row['transaction_status'] ?? ($row['status'] ?? '');
        if (is_string($status) && strtolower($status) === 'completed') {
            $accountInterestTotal += (float)($row['total_amount'] ?? 0);
        }
    }
    $total_interest_earned += $accountInterestTotal;
    if ($account->getAccountStatus() === 'Active') {
        $active_accounts++;
    }
}

// Monthly summary and projection helpers
$registeredMonthly = isset($member['monthly_contribution']) ? (float)$member['monthly_contribution'] : 0.0;
$startDate = null;
if (!empty($member['join_date'])) {
    try { $startDate = new DateTime($member['join_date']); } catch (Exception $e) { $startDate = null; }
}
if (!$startDate) {
    foreach ($savings_accounts as $account) {
        $od = $account->getOpeningDate();
        if ($od && (!$startDate || $od < $startDate)) { $startDate = clone $od; }
    }
}
if (!$startDate) { $startDate = new DateTime(date('Y-m-01')); }
$nowMonth = new DateTime(date('Y-m-01'));
$monthsActive = max(1, (($nowMonth->format('Y') - $startDate->format('Y')) * 12) + ($nowMonth->format('n') - $startDate->format('n')) + 1);
$totalMonthlyTarget = 0.0;
foreach ($savings_accounts as $account) { $mt = $account->getMonthlyTarget(); if ($mt) { $totalMonthlyTarget += (float)$mt; } }
$baseMonthly = $registeredMonthly > 0 ? $registeredMonthly : $totalMonthlyTarget;
$projectedTotal = $baseMonthly * $monthsActive;
$startMonthLabel = $startDate->format('F Y');

$update_message = '';
$update_error = '';

// Process deposit/withdrawal requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cooperative workflow: deposits are handled via payroll, not via portal
    if (isset($_POST['deposit_amount'])) {
        $update_error = 'Deposits are collected monthly via salary and credited centrally. Member deposits are disabled on this portal.';
    }
    
    // Handle monthly savings change request
    if (isset($_POST['action']) && $_POST['action'] === 'request_deduction_change') {
        $newAmount = isset($_POST['new_amount']) ? (float)$_POST['new_amount'] : 0;
        $reason = trim($_POST['reason'] ?? '');
        $adminId = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
        
        if ($newAmount <= 0) {
            $update_error = 'Please enter a valid new monthly amount.';
        } elseif (empty($reason)) {
            $update_error = 'Please provide a brief reason for the change.';
        } elseif ($adminId <= 0) {
            $update_error = 'Please select an administrator to send your request.';
        } else {
            $subject = 'Monthly Savings Change Request';
            $memberName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
            $body = "Member #" . (int)$member_id . " (" . htmlspecialchars($memberName) . ") requests to change monthly savings.\n"
                  . "Current: ₦" . number_format($registeredMonthly, 2) . "\n"
                  . "Requested: ₦" . number_format($newAmount, 2) . "\n"
                  . "Start month: " . $startMonthLabel . "\n"
                  . "Reason: " . $reason;
            
            $data = [
                'sender_type' => 'Member',
                'sender_id' => (int)$member_id,
                'recipient_type' => 'Admin',
                'recipient_id' => $adminId,
                'subject' => $subject,
                'message' => $body,
            ];
            
            $msgId = $messageController->createMessage($data);
            if ($msgId) {
                $update_message = 'Your change request has been sent to admin (Ref #' . (int)$msgId . ').';
            } else {
                $update_error = 'Failed to send change request. Please try again later.';
            }
        }
    }
    
    if (isset($_POST['withdraw_amount']) && $_POST['withdraw_amount'] !== '' && isset($_POST['account_id'])) {
        $account_id = (int)$_POST['account_id'];
        $amount = (float)$_POST['withdraw_amount'];
        
        /** @var \CSIMS\Models\SavingsAccount|null $account */
        $account = $savingsRepository->find($account_id);
        if ($account && $account->getMemberId() === (int)$member_id && $account->allowsWithdrawals() && $amount >= 500 && $amount <= $account->getBalance()) {
            $ok = $savingsController->withdraw($account_id, $amount, (int)$member_id, 'Member portal withdrawal');
            if ($ok) {
                $update_message = "Withdrawal request for ₦" . number_format($amount, 2) . " submitted for account #" . htmlspecialchars($account->getAccountNumber()) . ".";
            } else {
                $update_error = "Failed to submit withdrawal. Please try again or contact admin.";
            }
        } else {
            $update_error = "Invalid account or amount. Minimum withdrawal is ₦500; must not exceed balance.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Savings</title>
    <!-- Ensure styles load in head for immediate rendering -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/csims-colors.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/includes/member_header.php'; ?>

<div class="container mt-4">
    <div class="p-4 rounded-3 gradient-primary text-white mb-4">
        <h2 class="mb-0"><i class="fas fa-piggy-bank me-2"></i> Savings Overview</h2>
        <p class="mb-0 text-light">Welcome, <?php echo htmlspecialchars($member['first_name'] ?? 'Member'); ?>.</p>
    </div>

    <div class="alert alert-info border-blue-200 bg-blue-100 text-blue-800">
        Savings are calculated monthly based on your registered amount and credited by administrators. Payroll integration is pending; deposits via the portal are disabled.
    </div>

    <?php if (!empty($update_error)): ?>
        <div class="alert alert-warning border-yellow-200 bg-yellow-100 text-yellow-800">
            <?php echo htmlspecialchars($update_error); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($update_message)): ?>
        <div class="alert alert-success border-green-200 bg-green-100 text-green-800">
            <?php echo htmlspecialchars($update_message); ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4 row-eq-height">
        <!-- Total Balance (always shown) - Blue for info -->
        <div class="col-md-4">
            <div class="card stat-card-standard shadow-sm">
                <div class="card-body">
                    <div class="stat-text">
                        <span class="label">Total Balance</span>
                        <span class="value <?php echo $total_balance > 0 ? 'text-primary' : 'text-muted'; ?>">₦<?php echo number_format($total_balance, 2); ?></span>
                        <?php if ($total_balance <= 0) { ?>
                            <small class="text-muted">No funds posted yet.</small>
                        <?php } ?>
                    </div>
                    <div class="icon-wrap gradient-blue">
                        <i class="fas fa-wallet text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Interest Earned (only if > 0) - Green for actuals -->
        <?php if ($total_interest_earned > 0.0) { ?>
        <div class="col-md-4">
            <div class="card stat-card-standard shadow-sm">
                <div class="card-body">
                    <div class="stat-text">
                        <span class="label">Interest Earned</span>
                        <span class="value text-success">₦<?php echo number_format($total_interest_earned, 2); ?></span>
                    </div>
                    <div class="icon-wrap gradient-green">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
        <!-- Active Accounts (only if > 0) - Blue for info -->
        <?php if ($active_accounts > 0) { ?>
        <div class="col-md-4">
            <div class="card stat-card-standard shadow-sm">
                <div class="card-body">
                    <div class="stat-text">
                        <span class="label">Active Accounts</span>
                        <span class="value text-primary"><?php echo (int)$active_accounts; ?></span>
                    </div>
                    <div class="icon-wrap gradient-blue">
                        <i class="fas fa-folder-open text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <!-- Summary Cards Row -->
    <div class="row g-3 mb-4 row-eq-height">
        <!-- Registered Monthly Amount Card - Amber for projection -->
        <div class="col-md-6">
            <div class="card stat-card-standard shadow-sm">
                <div class="card-body">
                    <div class="stat-text">
                        <span class="label">Registered Monthly Amount</span>
                        <span class="value text-warning">₦<?php echo number_format($registeredMonthly, 2); ?></span>
                    </div>
                    <div class="icon-wrap gradient-amber">
                        <i class="fas fa-calendar-alt text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Projection to Date Card - Amber for projection -->
        <div class="col-md-6">
            <div class="card stat-card-standard shadow-sm">
                <div class="card-body">
                    <div class="stat-text">
                        <span class="label">Projection to Date</span>
                        <span class="value text-warning">₦<?php echo number_format($projectedTotal, 2); ?></span>
                    </div>
                    <div class="icon-wrap gradient-amber">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Monthly Savings Summary -->
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header border-0 d-flex align-items-center justify-content-between gradient-blue">
                    <h5 class="mb-0 text-white"><i class="fas fa-chart-pie me-2"></i>Monthly Savings Summary</h5>
                    <span class="badge bg-white text-primary">Info</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-standard">
                            <tbody>
                                <tr>
                                    <th style="width: 220px;">Start Month</th>
                                    <td class="text-primary"><?php echo htmlspecialchars($startMonthLabel); ?></td>
                                </tr>
                                <tr>
                                    <th>Months Active</th>
                                    <td class="text-primary"><?php echo (int)$monthsActive; ?></td>
                                </tr>
                                <tr class="table-success">
                                    <th>Actual Posted Balance</th>
                                    <td class="fw-bold text-success">₦<?php echo number_format($total_balance, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>Projection is informational; admins post actual balances monthly.</p>
                </div>
            </div>
        </div>

        <!-- Request Monthly Savings Change -->
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header border-0 d-flex align-items-center justify-content-between gradient-amber">
                    <h5 class="mb-0 text-white"><i class="fas fa-edit me-2"></i>Request Monthly Savings Change</h5>
                    <span class="badge bg-white text-warning">Action</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($admins)) { ?>
                    <form method="post">
                        <input type="hidden" name="action" value="request_deduction_change" />
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="new_amount" class="form-label">New Monthly Amount (₦)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="new_amount" name="new_amount" placeholder="e.g., 10000" required />
                            </div>
                            <div class="col-md-4">
                                <label for="admin_id" class="form-label">Send To Administrator</label>
                                <select class="form-select" id="admin_id" name="admin_id" required>
                                    <option value="">Select an admin...</option>
                                    <?php foreach ($admins as $admin) { ?>
                                        <option value="<?php echo (int)($admin['id'] ?? $admin['admin_id'] ?? 0); ?>">
                                            <?php echo htmlspecialchars(($admin['name'] ?? (($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')))); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="reason" class="form-label">Reason</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Briefly explain the change" required></textarea>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Send Request</button>
                        </div>
                    </form>
                    <?php } else { ?>
                        <p class="text-muted mb-0">No administrators found. Please try again later.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-light">
        <div class="card-header d-flex align-items-center gradient-blue">
            <i class="fas fa-university me-2 text-white"></i> 
            <span class="text-white fw-bold">Your Savings Accounts</span>
        </div>
        <div class="card-body">
            <?php if (empty($savings_accounts)): ?>
                <div class="d-flex align-items-center justify-content-between">
                    <p class="text-muted mb-2"><i class="fas fa-info-circle me-2"></i>No savings accounts found.</p>
                    <a href="member_messages.php" class="btn btn-primary btn-sm">Message Admin</a>
                </div>
                <small class="text-muted">Admins create and manage savings accounts.</small>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($savings_accounts as $account): /** @var \CSIMS\Models\SavingsAccount $account */ ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong class="text-primary"><?php echo htmlspecialchars($account->getAccountType()); ?></strong>
                                    <div class="text-muted small">Account #<?php echo htmlspecialchars($account->getAccountNumber()); ?></div>
                                    <div class="small">
                                        Status:
                                        <?php $status = htmlspecialchars($account->getAccountStatus()); ?>
                                        <span class="badge <?php echo $status === 'Active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $status; ?></span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary-700">₦<?php echo number_format($account->getBalance(), 2); ?></div>
                                    <div class="text-muted small">Interest: <?php echo number_format($account->getInterestRate(), 2); ?>%</div>
                                </div>
                            </div>
                            <form method="POST" class="row g-2 mt-2">
                                <input type="hidden" name="account_id" value="<?php echo (int)$account->getAccountId(); ?>">
                                <div class="col-md-12">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Withdraw ₦</span>
                                        <input type="number" class="form-control" name="withdraw_amount" min="500" max="<?php echo $account->getBalance(); ?>" step="0.01" placeholder="500">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Request Withdraw</button>
                                    </div>
                                    <small class="text-muted">Minimum ₦500; up to current balance</small>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-light">
        <div class="card-header d-flex align-items-center gradient-green">
            <i class="fas fa-receipt me-2 text-white"></i> 
            <span class="text-white fw-bold">Recent Transactions</span>
        </div>
        <div class="card-body">
            <?php if (empty($recent_transactions)): ?>
                <p class="text-muted">No recent transactions.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($recent_transactions as $tx): /** @var \CSIMS\Models\SavingsTransaction $tx */ ?>
                        <div class="list-group-item d-flex justify-content-between">
                            <div>
                                <strong class="text-secondary"><?php echo htmlspecialchars($tx->getTransactionType()); ?></strong>
                                <div class="text-muted small"><?php echo htmlspecialchars($tx->getDescription() ?? ''); ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary-600">₦<?php echo number_format($tx->getAmount(), 2); ?></div>
                                <div class="text-muted small"><?php echo $tx->getTransactionDate()->format('M d, Y'); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>