<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$loanController = new LoanController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get member's loans
$loans = $loanController->getLoansByMemberId($member_id);

// Calculate totals
$total_loan_amount = 0;
$active_loans = 0;
$pending_loans = 0;

foreach ($loans as $loan) {
    if ($loan['status'] === 'Active') {
        $total_loan_amount += $loan['amount'];
        $active_loans++;
    } elseif ($loan['status'] === 'Pending') {
        $pending_loans++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans - NPC CTLStaff Loan Society</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .loan-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #cce7ff; color: #004085; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
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
                        <a class="nav-link active" href="member_loans.php">
                            <i class="fas fa-money-bill-wave me-2"></i> My Loans
                        </a>
                        <a class="nav-link" href="member_contributions.php">
                            <i class="fas fa-piggy-bank me-2"></i> My Contributions
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
                        <h2><i class="fas fa-money-bill-wave me-2"></i> My Loans</h2>
                        <a href="member_loan_application.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Apply for New Loan
                        </a>
                    </div>
                    
                    <!-- Loan Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                    <h4>₦<?php echo number_format($total_loan_amount, 2); ?></h4>
                                    <p class="mb-0">Total Active Loans</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h4><?php echo $active_loans; ?></h4>
                                    <p class="mb-0">Active Loans</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4><?php echo $pending_loans; ?></h4>
                                    <p class="mb-0">Pending Applications</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loans List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Loan History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($loans)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No loans found</h5>
                                    <p class="text-muted">You haven't applied for any loans yet.</p>
                                    <a href="member_loan_application.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i> Apply for Your First Loan
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Application Date</th>
                                                <th>Amount</th>
                                                <th>Purpose</th>
                                                <th>Term</th>
                                                <th>Interest Rate</th>
                                                <th>Monthly Payment</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loans as $loan): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                                    <td>₦<?php echo number_format($loan['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($loan['purpose']); ?></td>
                                                    <td><?php echo $loan['term_months']; ?> months</td>
                                                    <td><?php echo $loan['interest_rate']; ?>%</td>
                                                    <td>₦<?php echo number_format($loan['monthly_payment'], 2); ?></td>
                                                    <td>
                                                        <span class="loan-status status-<?php echo strtolower($loan['status']); ?>">
                                                            <?php echo $loan['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="member_loan_details.php?id=<?php echo $loan['loan_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
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
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>