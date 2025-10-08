<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/contribution_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$contributionController = new ContributionController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

if (!$member) {
    header('Location: member_login.php');
    exit();
}

// Get member's contributions with error handling
$contributions = $contributionController->getContributionsByMemberId($member_id);
if ($contributions === false) {
    $contributions = [];
}

$total_contributions = $contributionController->getMemberTotalContributions($member_id);
$contribution_count = $contributionController->getMemberContributionCount($member_id);
$last_contribution = $contributionController->getMemberLastContribution($member_id);

// Calculate this year's contributions
$this_year_total = 0;
foreach ($contributions as $contribution) {
    if (date('Y', strtotime($contribution['contribution_date'])) == date('Y')) {
        $this_year_total += $contribution['amount'];
    }
}
$update_message = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_monthly_contribution'])) {
    $new_contribution = floatval($_POST['new_monthly_contribution']);
    // Validate range
    if ($new_contribution >= 5000 && $new_contribution <= 100000) {
        $result = $memberController->updateMonthlyContribution($member_id, $new_contribution);
        if ($result) {
            $update_message = 'Monthly contribution updated successfully to ₦' . number_format($new_contribution, 2) . '!';
            // Refresh member data after update
            $member = $memberController->getMemberById($member_id);
        } else {
            $update_error = 'Failed to update monthly contribution. Please try again.';
        }
    } else {
        $update_error = 'Monthly contribution must be between ₦5,000 and ₦100,000.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Contributions - NPC CTLStaff Loan Society</title>
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
        @media (max-width: 767px) {
            .stat-card {
                margin-bottom: 1.5rem;
            }
        }
    }
        .contribution-type {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: #e9ecef;
            color: #495057;
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
                        <a class="nav-link active" href="member_contributions.php">
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
                        <h2><i class="fas fa-piggy-bank me-2"></i> My Contributions</h2>
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
                    
                    <!-- Contribution Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-coins fa-2x mb-2"></i>
                                    <h4>₦<?php echo number_format($member['monthly_contribution'], 2); ?></h4>
                                    <p class="mb-0">Monthly Contribution</p>
                                    <button class="btn btn-light btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#updateContributionModal">Modify</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                    <h4>₦<?php echo number_format($total_contributions, 2); ?></h4>
                                    <p class="mb-0">Total Contributions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-year fa-2x mb-2"></i>
                                    <h4>₦<?php echo number_format($this_year_total, 2); ?></h4>
                                    <p class="mb-0">This Year</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-2x mb-2"></i>
                                    <h4><?php echo $contribution_count; ?></h4>
                                    <p class="mb-0">Total Payments</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4><?php echo $last_contribution ? date('M d', strtotime($last_contribution['contribution_date'])) : 'N/A'; ?></h4>
                                    <p class="mb-0">Last Payment</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contributions List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Contribution History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($contributions)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-piggy-bank fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No contributions found</h5>
                                    <p class="text-muted">Your contribution history will appear here once payments are recorded.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                                <th>Payment Method</th>
                                                <th>Receipt Number</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($contributions as $contribution): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></td>
                                                    <td class="fw-bold text-success">₦<?php echo number_format($contribution['amount'], 2); ?></td>
                                                    <td>
                                                        <span class="contribution-type">
                                                            <?php echo htmlspecialchars($contribution['contribution_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($contribution['payment_method']); ?></td>
                                                    <td>
                                                        <?php if (!empty($contribution['receipt_number'])): ?>
                                                            <code><?php echo htmlspecialchars($contribution['receipt_number']); ?></code>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($contribution['notes'])): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($contribution['notes']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Summary Card -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Contribution Summary</h6>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted">Average per payment:</small>
                                                        <div class="fw-bold">₦<?php echo number_format($contribution_count > 0 ? $total_contributions / $contribution_count : 0, 2); ?></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Member since:</small>
                                                        <div class="fw-bold"><?php echo date('M Y', strtotime($member['join_date'])); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Membership Status</h6>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted">Membership Type:</small>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($member['membership_type']); ?></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Status:</small>
                                                        <div class="fw-bold text-<?php echo $member['status'] === 'Active' ? 'success' : 'warning'; ?>">
                                                            <?php echo $member['status']; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
<!-- Modal for updating monthly contribution -->
<div class="modal fade" id="updateContributionModal" tabindex="-1" aria-labelledby="updateContributionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="member_contributions.php">
        <div class="modal-header">
          <h5 class="modal-title" id="updateContributionModalLabel">Update Monthly Contribution</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label for="new_monthly_contribution" class="form-label">New Monthly Contribution (₦)</label>
          <input type="number" class="form-control" name="new_monthly_contribution" id="new_monthly_contribution" min="5000" max="100000" step="500" required value="<?php echo $member['monthly_contribution']; ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>