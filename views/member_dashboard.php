<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/contribution_controller.php';
require_once '../controllers/notification_controller.php';
// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}
$memberController = new MemberController($conn);
$loanController = new LoanController($conn);
$contributionController = new ContributionController();
$notificationController = new NotificationController($conn);
$member_id = $_SESSION['member_id'];
// Get member details
$member = $memberController->getMemberById($member_id);
// Get member statistics
$member_loans = $loanController->getMemberLoans($member_id);
if ($member_loans === false) {
    $member_loans = []; // Handle error case
}
$member_contributions = $contributionController->getContributionsByMemberId($member_id);
if ($member_contributions === false) {
    $member_contributions = []; // Handle error case
}
$member_notifications = $notificationController->getMemberNotifications($member_id);
if ($member_notifications === false) {
    $member_notifications = []; // Handle error case
}
// Calculate totals
$total_contributions = 0;
$total_loan_amount = 0;
$active_loans = 0;
foreach ($member_contributions as $contribution) {
    $total_contributions += $contribution['amount'];
}
foreach ($member_loans as $loan) {
    $total_loan_amount += $loan['amount'];
    if (in_array($loan['status'], ['Approved', 'Disbursed'])) {
        $active_loans++;
    }
}
$unread_notifications = count(array_filter($member_notifications, function($n) { return !$n['is_read']; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .stat-card { background: #f8f9fa; border-radius: 10px; }
        .stat-card-success { background: #d4edda; border-radius: 10px; }
        .stat-card-warning { background: #fff3cd; border-radius: 10px; }
        .stat-card-info { background: #d1ecf1; border-radius: 10px; }
        .welcome-section { background: #6c63ff; color: #fff; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .sidebar { background: #343a40; color: #fff; border-radius: 10px; }
        .sidebar .nav-link { color: #fff; }
        .sidebar .nav-link.active { background: #6c63ff; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-users"></i> NPC CTLStaff Loan Society
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['member_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="member_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="member_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="member_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="member_profile.php">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="member_loans.php">
                                <i class="fas fa-money-bill-wave"></i> My Loans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="member_contributions.php">
                                <i class="fas fa-piggy-bank"></i> My Contributions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="member_notifications.php">
                                <i class="fas fa-bell"></i> Notifications
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger"><?php echo $unread_notifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="member_messages.php">
                                <i class="fas fa-envelope"></i> Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="loan_application.php">
                                <i class="fas fa-file-alt"></i> Apply for Loan
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Welcome Section -->
                    <div class="welcome-section">
                        <h2><i class="fas fa-home"></i> Welcome back, <?php echo htmlspecialchars($member['first_name']); ?>!</h2>
                        <p class="mb-0">Member since <?php echo date('F Y', strtotime($member['join_date'])); ?> | Membership: <?php echo htmlspecialchars($member['membership_type']); ?></p>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-piggy-bank fa-2x mb-2"></i>
                                    <h5>Total Contributions</h5>
                                    <h3>$<?php echo number_format($total_contributions, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                    <h5>Total Loans</h5>
                                    <h3>$<?php echo number_format($total_loan_amount, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h5>Active Loans</h5>
                                    <h3><?php echo $active_loans; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-bell fa-2x mb-2"></i>
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
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-money-bill-wave"></i> Recent Loans</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_loans)): ?>
                                        <p class="text-muted">No loans found.</p>
                                        <a href="loan_application.php" class="btn btn-primary">Apply for Loan</a>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_loans, 0, 3) as $loan): ?>
                                            <div class="d-flex flex-column mb-3 p-2 border rounded">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>$<?php echo number_format($loan['amount'], 2); ?></strong>
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
                                                    <small><strong>Savings:</strong> $<?php echo isset($loan['savings']) ? number_format($loan['savings'] ?? 0, 2) : 'N/A'; ?></small><br>
                                                    <small><strong>Deduction Started:</strong> <?php echo isset($loan['month_deduction_started']) ? htmlspecialchars($loan['month_deduction_started']) : 'N/A'; ?></small><br>
                                                    <small><strong>Deduction Ends:</strong> <?php echo isset($loan['month_deduction_should_end']) ? htmlspecialchars($loan['month_deduction_should_end']) : 'N/A'; ?></small><br>
                                                    <small><strong>Other Payment Plans:</strong> <?php echo isset($loan['other_payment_plans']) ? htmlspecialchars($loan['other_payment_plans']) : 'N/A'; ?></small><br>
                                                    <small><strong>Remarks:</strong> <?php echo isset($loan['remarks']) ? htmlspecialchars($loan['remarks']) : 'N/A'; ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="member_loans.php" class="btn btn-outline-primary btn-sm">View All</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Contributions -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-piggy-bank"></i> Recent Contributions</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($member_contributions)): ?>
                                        <p class="text-muted">No contributions found.</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($member_contributions, 0, 3) as $contribution): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong>$<?php echo number_format($contribution['amount'], 2); ?></strong>
                                                    <br><small class="text-muted"><?php echo $contribution['contribution_type']; ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="member_contributions.php" class="btn btn-outline-primary btn-sm">View All</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Notifications -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-bell"></i> Recent Notifications</h5>
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
                                                    <span class="badge bg-primary">New</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="member_notifications.php" class="btn btn-outline-primary btn-sm">View All</a>
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