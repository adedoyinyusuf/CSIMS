<?php
// Session is managed via config/member_auth_check.php
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/notification_controller.php';
require_once '../includes/utilities.php';
// Check if member is logged in
// session_start(); // Session is initialized via includes/session.php in config.php
// Removed legacy session check; centralized in member_auth_check.php
// if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
//     header('Location: member_login.php');
//     exit();
// }
$database = Database::getInstance();
$conn = $database->getConnection();

$memberController = new MemberController();
$loanController = new LoanController($conn);
$notificationController = new NotificationController($conn);
$member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];
// Get member details
$member = $memberController->getMemberById($member_id);
// Get member statistics
$member_loans = $loanController->getMemberLoans($member_id);
if ($member_loans === false) {
    $member_loans = []; // Handle error case
}
// Get member savings accounts
try {
    require_once '../src/autoload.php';
    $savingsRepository = new \CSIMS\Repositories\SavingsAccountRepository($conn);
    $member_savings = $savingsRepository->findByMemberId($member_id);
} catch (Exception $e) {
    $member_savings = []; // Handle error case
}
$member_notifications = $notificationController->getMemberNotifications($member_id);
if ($member_notifications === false) {
    $member_notifications = []; // Handle error case
}
// Calculate totals
$total_savings = 0;
$total_loan_amount = 0;
$active_loans = 0;
$member_loan_outstanding = 0;
foreach ($member_savings as $savings_account) {
    $total_savings += $savings_account->getBalance();
}
foreach ($member_loans as $loan) {
    $total_loan_amount += $loan['amount'];
    if (in_array($loan['status'], ['Approved', 'Disbursed'])) {
        $active_loans++;
    }
    // Compute outstanding per-loan with schema flexibility
    if (isset($loan['amount_paid'])) {
        $member_loan_outstanding += max(0, (float)$loan['amount'] - (float)$loan['amount_paid']);
    } elseif (isset($loan['remaining_balance'])) {
        $member_loan_outstanding += (float)$loan['remaining_balance'];
    } elseif (isset($loan['total_repaid'])) {
        $member_loan_outstanding += max(0, (float)$loan['amount'] - (float)$loan['total_repaid']);
    } else {
        $member_loan_outstanding += (float)$loan['amount'];
    }
}
$unread_notifications = count(array_filter($member_notifications, function($n) { return !$n['is_read']; }));

// Recent savings activity per member: deposits/withdrawals
$member_contributions = [];
try {
    // Normalize savings schema using Utilities helper
    $schema = Utilities::getSavingsSchema($conn);
    $txSchema = $schema['transactions'];

    // Available columns for filtering
    $has_member_id_st = !empty($txSchema['member_id']);
    $has_account_id_st = !empty($txSchema['account_id']);

    // Canonical column names
    $typeCol = $txSchema['type'];
    $dateCol = $txSchema['date'];
    $statusCol = $txSchema['status'];

    $accountIds = [];
    foreach ($member_savings as $sa) {
        if (method_exists($sa, 'getId')) { $accountIds[] = $sa->getId(); }
        elseif (method_exists($sa, 'getAccountId')) { $accountIds[] = $sa->getAccountId(); }
        elseif (property_exists($sa, 'id')) { $accountIds[] = $sa->id; }
    }
    $accountIds = array_filter(array_unique($accountIds));

    $where = [];
    if ($has_member_id_st) { $where[] = "member_id = " . (int)$member_id; }
    if ($has_account_id_st && !empty($accountIds)) { $where[] = "account_id IN (" . implode(',', array_map('intval', $accountIds)) . ")"; }
    $whereSql = !empty($where) ? ("WHERE " . implode(' OR ', $where)) : "";

    $sql = "SELECT amount, $typeCol AS transaction_type, $dateCol AS transaction_date FROM savings_transactions $whereSql ORDER BY $dateCol DESC LIMIT 10";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $member_contributions[] = [
                'amount' => (float)($row['amount'] ?? 0),
                'contribution_type' => $row['transaction_type'] ?? 'Transaction',
                'contribution_date' => $row['transaction_date'] ?? date('Y-m-d'),
            ];
        }
    }
} catch (Exception $e) {
    // leave member_contributions empty on error
}

// Member repayments metrics
$member_repayments_this_month = 0; $member_repayments_total = 0; $member_repayments_count_this_month = 0; $member_repayments_last_month = 0;
try {
    $has_lr_member_id = false; $has_lr_loan_id = false;
    $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'member_id'");
    if ($col && $col->num_rows > 0) { $has_lr_member_id = true; }
    $col = $conn->query("SHOW COLUMNS FROM loan_repayments LIKE 'loan_id'");
    if ($col && $col->num_rows > 0) { $has_lr_loan_id = true; }

    $loanIds = [];
    foreach ($member_loans as $loan) {
        if (isset($loan['id'])) { $loanIds[] = (int)$loan['id']; }
        elseif (isset($loan['loan_id'])) { $loanIds[] = (int)$loan['loan_id']; }
    }
    $loanIds = array_filter(array_unique($loanIds));

    $where = [];
    if ($has_lr_member_id) { $where[] = "member_id = " . (int)$member_id; }
    elseif ($has_lr_loan_id && !empty($loanIds)) { $where[] = "loan_id IN (" . implode(',', array_map('intval', $loanIds)) . ")"; }
    $whereSql = !empty($where) ? ("WHERE " . implode(' OR ', $where)) : "";

    // Current month sum and count
    $sql = "SELECT SUM(amount) as total, COUNT(*) as cnt FROM loan_repayments $whereSql AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())";
    // If WHERE is empty, replace leading AND
    if (empty($where)) { $sql = str_replace(' $whereSql AND', ' WHERE', $sql); }
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        $member_repayments_this_month = (float)($row['total'] ?? 0);
        $member_repayments_count_this_month = (int)($row['cnt'] ?? 0);
    }

    // Last month sum
    $sql = "SELECT SUM(amount) as total FROM loan_repayments $whereSql AND YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    if (empty($where)) { $sql = str_replace(' $whereSql AND', ' WHERE', $sql); }
    $res = $conn->query($sql);
    $member_repayments_last_month = 0;
    if ($res) {
        $member_repayments_last_month = (float)($res->fetch_assoc()['total'] ?? 0);
    }

    // Total sum
    $sql = "SELECT SUM(amount) as total FROM loan_repayments $whereSql";
    $res = $conn->query($sql);
    if ($res) {
        $member_repayments_total = (float)($res->fetch_assoc()['total'] ?? 0);
    }

    // Last repayment date
    $sql = "SELECT MAX(payment_date) as last_date FROM loan_repayments $whereSql";
    $res = $conn->query($sql);
    $member_last_repayment_date = null; $member_days_since_last_repayment = null;
    if ($res) {
        $last = $res->fetch_assoc()['last_date'] ?? null;
        if (!empty($last)) {
            $member_last_repayment_date = $last;
            $member_days_since_last_repayment = (int)((time() - strtotime($last)) / 86400);
            if ($member_days_since_last_repayment < 0) { $member_days_since_last_repayment = 0; }
        }
    }

    // Repayment cadence: average days between repayments (last 120 days) and classification
    $member_avg_days_between_repayments = null; $member_repayment_cadence_label = null; $member_payments_last_30_days = 0;
    $sql = "SELECT payment_date FROM loan_repayments $whereSql AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 120 DAY) ORDER BY payment_date ASC";
    if (empty($where)) { $sql = str_replace(' $whereSql AND', ' WHERE', $sql); }
    $dates = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $dt = $row['payment_date'] ?? null;
            if (!empty($dt)) { $dates[] = $dt; }
        }
    }
    if (count($dates) >= 2) {
        $sumDiff = 0; $cntDiff = 0; $prevTs = null;
        foreach ($dates as $d) {
            $ts = strtotime($d);
            if ($prevTs !== null) {
                $diffDays = abs(($ts - $prevTs) / 86400);
                $sumDiff += $diffDays; $cntDiff++;
            }
            $prevTs = $ts;
        }
        if ($cntDiff > 0) { $member_avg_days_between_repayments = $sumDiff / $cntDiff; }
    }
    if (isset($member_avg_days_between_repayments)) {
        $avg = $member_avg_days_between_repayments;
        if ($avg <= 9) { $member_repayment_cadence_label = 'Weekly'; }
        elseif ($avg <= 20) { $member_repayment_cadence_label = 'Biweekly'; }
        elseif ($avg <= 40) { $member_repayment_cadence_label = 'Monthly'; }
        else { $member_repayment_cadence_label = 'Irregular'; }
    }
    // Payments in last 30 days
    $sql = "SELECT COUNT(*) as cnt FROM loan_repayments $whereSql AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    if (empty($where)) { $sql = str_replace(' $whereSql AND', ' WHERE', $sql); }
    $res = $conn->query($sql);
    if ($res) { $member_payments_last_30_days = (int)($res->fetch_assoc()['cnt'] ?? 0); }
} catch (Exception $e) {
    // leave defaults on error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard</title>
    <!-- Assets centralized via includes/member_header.php -->
    <style>
        body {
            background: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: var(--text-primary);
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-light);
            box-shadow: 0 6px 24px var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            color: var(--text-primary);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 32px var(--shadow-md);
        }
        .stat-card-success {
            background: #ffffff;
            border-top: 3px solid var(--success);
            color: var(--text-primary);
        }
        .stat-card-warning {
            background: #ffffff;
            border-top: 3px solid var(--warning);
            color: var(--text-primary);
        }
        .stat-card-info {
            background: #ffffff;
            border-top: 3px solid var(--true-blue);
            color: var(--text-primary);
        }
        .welcome-section {
            background: #ffffff;
            color: var(--text-primary);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-light);
            box-shadow: 0 6px 24px var(--shadow-sm);
        }
        .sidebar {
            background: #ffffff;
            color: var(--text-primary);
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 12px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--primary-50);
            color: var(--text-primary);
            transform: translateX(4px);
        }
        .navbar {
            background: #ffffff;
            box-shadow: 0 2px 10px var(--shadow-sm);
            border-bottom: 1px solid var(--border-light);
        }
        .navbar .navbar-brand,
        .navbar .nav-link {
            color: var(--text-primary);
        }
        .navbar .nav-link:hover,
        .navbar .nav-link:focus {
            color: var(--text-secondary);
        }
        .dropdown-menu {
            background: #ffffff;
            border: 1px solid var(--border-light);
            box-shadow: 0 8px 24px var(--shadow-sm);
        }
        .dropdown-menu .dropdown-item {
            color: var(--text-primary);
        }
        .dropdown-menu .dropdown-item:hover,
        .dropdown-menu .dropdown-item:focus {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-secondary);
        }
        .card {
            border-radius: 16px;
            border: 1px solid var(--border-light);
            box-shadow: 0 6px 24px var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 32px var(--shadow-md);
        }
        .card-header {
            background: #ffffff;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            font-weight: 600;
        }
        .badge {
            background: var(--persian-orange);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <!-- Shared Member Header -->
    <?php include_once __DIR__ . '/includes/member_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-12">
                <div class="p-4">
                    <!-- Welcome Section -->
                    <div class="welcome-section">
                        <h2><i class="fas fa-home me-2" style="color: var(--true-blue);"></i> Welcome back, <?php echo htmlspecialchars($member['first_name']); ?>!</h2>
                        <p class="mb-0">Member since <?php echo date('F Y', strtotime($member['date_joined'] ?? $member['join_date'] ?? 'now')); ?> | Membership: <?php echo htmlspecialchars($member['membership_type']); ?></p>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card card-member stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-piggy-bank fa-2x mb-2" style="color: var(--success);"></i>
                                    <h5>Total Savings</h5>
                                    <h3>₦<?php echo number_format($total_savings, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card card-member stat-card-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-money-bill-wave fa-2x mb-2" style="color: var(--accent-color);"></i>
                                    <h5>Total Loans</h5>
                                    <h3>₦<?php echo number_format($total_loan_amount, 2); ?></h3>
                                    <div class="text-muted small mt-1">Outstanding: ₦<?php echo number_format($member_loan_outstanding, 2); ?></div>
                                    <div class="text-muted small">Repayments This Month: ₦<?php echo number_format($member_repayments_this_month, 2); ?> • <?php echo number_format($member_repayments_count_this_month); ?> payments</div>
                                    <?php $curMR = (float)$member_repayments_this_month; $prevMR = (float)$member_repayments_last_month; $deltaMR = $prevMR > 0 ? (($curMR - $prevMR) / $prevMR) * 100 : 0; $isUpMR = $curMR >= $prevMR; ?>
                                    <div class="text-muted small" style="color: <?php echo $isUpMR ? 'var(--success)' : 'var(--danger)'; ?>;">MoM: <?php echo $isUpMR ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaMR), 1); ?>% vs last month</div>
                                    <div class="text-muted small">Total Repaid: ₦<?php echo number_format($member_repayments_total, 2); ?></div>
                                    <div class="text-muted small">Last Repayment: <?php echo $member_last_repayment_date ? date('M d, Y', strtotime($member_last_repayment_date)) : 'N/A'; ?><?php if ($member_last_repayment_date): ?> • <?php echo number_format($member_days_since_last_repayment); ?> days ago<?php endif; ?></div>
                                    <?php if (isset($member_avg_days_between_repayments)) { $cad = $member_repayment_cadence_label ?? 'Irregular'; ?>
                                    <div class="text-muted small">Repayment Cadence: <?php echo htmlspecialchars($cad); ?> • Avg <?php echo number_format($member_avg_days_between_repayments, 1); ?> days • <?php echo number_format($member_payments_last_30_days); ?> payments in 30d</div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card card-member stat-card-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2" style="color: var(--persian-orange);"></i>
                                    <h5>Active Loans</h5>
                                    <h3><?php echo $active_loans; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card card-member stat-card-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-bell fa-2x mb-2" style="color: var(--secondary-color);"></i>
                                    <h5>Notifications</h5>
                                    <h3><?php echo $unread_notifications; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <!-- Recent Loans -->
                        <div class="col-md-6 mb-4">
                            <div class="card card-member">
                                <div class="card-header">
                                    <h5><i class="fas fa-money-bill-wave me-2" style="color: var(--accent-color);"></i> Recent Loans</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_loans)): ?>
                                        <p class="text-muted">No loans found.</p>
                                        <a href="member_loans.php" class="btn btn-outline">Apply for Loan</a>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_loans, 0, 3) as $loan): ?>
                                            <div class="d-flex flex-column mb-3 p-2 border rounded">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>₦<?php echo number_format($loan['amount'], 2); ?></strong>
                                                        <br><small class="text-muted">Applied: <?php echo date('M d, Y', strtotime($loan['application_date'])); ?></small>
                                                    </div>
                                                    <span class="badge bg-<?php 
                                                        echo $loan['status'] === 'Approved' ? 'success' : 
                                                            ($loan['status'] === 'Pending' ? 'warning' : 
                                                            ($loan['status'] === 'Rejected' ? 'danger' : 'info')); 
                                                    ?>">
                                                        <?php echo $loan['status']; ?>
                                                    </span>
                                                </div>
                                                <div class="mt-2">
                                                    <small><strong>Interest Rate:</strong> <?php echo isset($loan['interest_rate']) ? number_format($loan['interest_rate'], 2) : 'N/A'; ?>%</small><br>
                                                    <small><strong>Savings:</strong> ₦<?php echo isset($loan['savings']) ? number_format($loan['savings'] ?? 0, 2) : 'N/A'; ?></small><br>
                                                    <small><strong>Deduction Started:</strong> <?php echo isset($loan['month_deduction_started']) ? htmlspecialchars($loan['month_deduction_started']) : 'N/A'; ?></small><br>
                                                    <small><strong>Deduction Ends:</strong> <?php echo isset($loan['month_deduction_should_end']) ? htmlspecialchars($loan['month_deduction_should_end']) : 'N/A'; ?></small><br>
                                                    <small><strong>Other Payment Plans:</strong> <?php echo isset($loan['other_payment_plans']) ? htmlspecialchars($loan['other_payment_plans']) : 'N/A'; ?></small><br>
                                                    <small><strong>Remarks:</strong> <?php echo isset($loan['remarks']) ? htmlspecialchars($loan['remarks']) : 'N/A'; ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="member_loans.php" class="btn btn-outline btn-sm">View All</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Savings Activity -->
                        <div class="col-md-6 mb-4">
                            <div class="card card-member">
                                <div class="card-header">
                                    <h5><i class="fas fa-piggy-bank me-2" style="color: var(--success);"></i> Recent Savings Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_contributions)): ?>
                                        <p class="text-muted">No savings activity found.</p>
                                        <a href="member_savings.php" class="btn btn-outline">Manage Savings</a>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_contributions, 0, 3) as $contribution): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong>₦<?php echo number_format($contribution['amount'], 2); ?></strong>
                                                    <br><small class="text-muted"><?php echo $contribution['contribution_type']; ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="member_savings.php" class="btn btn-outline btn-sm">View All</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Notifications -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-member">
                                <div class="card-header">
                                    <h5><i class="fas fa-bell me-2" style="color: var(--secondary-color);"></i> Recent Notifications</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_notifications)): ?>
                                        <p class="text-muted">No notifications.</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_notifications, 0, 5) as $notification): ?>
                                            <div class="d-flex justify-content-between align-items-start mb-3 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small>
                                                </div>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge badge-primary">New</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="member_notifications.php" class="btn btn-outline btn-sm">View All</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>