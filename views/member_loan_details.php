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

// Get loan ID from URL
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$loan_id) {
    header('Location: member_loans.php');
    exit();
}

// Get loan details
$loan = $loanController->getLoanById($loan_id);

// Check if loan exists and belongs to the logged-in member
if (!$loan || $loan['member_id'] != $member_id) {
    header('Location: member_loans.php');
    exit();
}

// Get loan repayments if loan is active
$repayments = [];
if ($loan['status'] === 'Active') {
    $repayments = $loanController->getLoanRepayments($loan_id);
}

// Calculate loan progress
$total_paid = 0;
foreach ($repayments as $repayment) {
    $total_paid += $repayment['amount'];
}

$remaining_balance = $loan['amount'] - $total_paid;
$progress_percentage = $loan['amount'] > 0 ? ($total_paid / $loan['amount']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details - NPC CTLStaff Loan Society</title>
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
        .loan-status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #cce7ff; color: #004085; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .progress-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .info-item {
            border-bottom: 1px solid #e9ecef;
            padding: 0.75rem 0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .repayment-item {
            border-left: 4px solid #28a745;
            background-color: #f8f9fa;
            margin-bottom: 0.5rem;
            padding: 1rem;
            border-radius: 0 8px 8px 0;
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
                        <h2><i class="fas fa-file-invoice-dollar me-2"></i> Loan Details</h2>
                        <a href="member_loans.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to My Loans
                        </a>
                    </div>
                    
                    <div class="row">
                        <!-- Loan Information -->
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Loan Information</h5>
                                    <span class="loan-status status-<?php echo strtolower($loan['status']); ?>">
                                        <?php echo $loan['status']; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-item">
                                                <strong>Loan Amount:</strong>
                                                <div class="text-primary fs-4">₦<?php echo number_format($loan['amount'], 2); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Application Date:</strong>
                                                <div><?php echo date('F j, Y', strtotime($loan['application_date'])); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Loan Term:</strong>
                                                <div><?php echo $loan['term_months']; ?> months</div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Interest Rate:</strong>
                                                <div><?php echo $loan['interest_rate']; ?>% per annum</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-item">
                                                <strong>Monthly Payment:</strong>
                                                <div class="text-success fs-5">₦<?php echo number_format($loan['monthly_payment'], 2); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Purpose:</strong>
                                                <div><?php echo htmlspecialchars($loan['purpose']); ?></div>
                                            </div>
                                            <?php if ($loan['status'] === 'Active' && !empty($loan['disbursement_date'])): ?>
                                                <div class="info-item">
                                                    <strong>Disbursement Date:</strong>
                                                    <div><?php echo date('F j, Y', strtotime($loan['disbursement_date'])); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($loan['notes'])): ?>
                                                <div class="info-item">
                                                    <strong>Notes:</strong>
                                                    <div><?php echo htmlspecialchars($loan['notes']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Collateral and Guarantor -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Security Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-shield-alt me-2"></i> Collateral</h6>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($loan['collateral'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-user-shield me-2"></i> Guarantor</h6>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($loan['guarantor'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Repayment History -->
                            <?php if ($loan['status'] === 'Active' && !empty($repayments)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Repayment History</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($repayments as $repayment): ?>
                                            <div class="repayment-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>₦<?php echo number_format($repayment['amount'], 2); ?></strong>
                                                        <small class="text-muted ms-2">
                                                            <?php echo date('M j, Y', strtotime($repayment['payment_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="small text-muted"><?php echo htmlspecialchars($repayment['payment_method']); ?></div>
                                                        <?php if (!empty($repayment['reference_number'])): ?>
                                                            <div class="small">Ref: <?php echo htmlspecialchars($repayment['reference_number']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($repayment['notes'])): ?>
                                                    <div class="mt-2 small text-muted">
                                                        <?php echo htmlspecialchars($repayment['notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Loan Progress -->
                        <div class="col-lg-4">
                            <?php if ($loan['status'] === 'Active'): ?>
                                <div class="card progress-card mb-4">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Loan Progress</h6>
                                        <div class="mb-3">
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-light" role="progressbar" 
                                                     style="width: <?php echo $progress_percentage; ?>%" 
                                                     aria-valuenow="<?php echo $progress_percentage; ?>" 
                                                     aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-light mt-1"><?php echo number_format($progress_percentage, 1); ?>% paid</small>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="fw-bold">₦<?php echo number_format($total_paid, 2); ?></div>
                                                <small>Paid</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="fw-bold">₦<?php echo number_format($remaining_balance, 2); ?></div>
                                                <small>Remaining</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Loan Summary -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Loan Summary</h6>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <strong>Principal Amount:</strong>
                                        <div>₦<?php echo number_format($loan['amount'], 2); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <strong>Total Interest:</strong>
                                        <div>₦<?php echo number_format(($loan['monthly_payment'] * $loan['term_months']) - $loan['amount'], 2); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <strong>Total Payable:</strong>
                                        <div class="fw-bold text-primary">₦<?php echo number_format($loan['monthly_payment'] * $loan['term_months'], 2); ?></div>
                                    </div>
                                    <?php if ($loan['status'] === 'Active'): ?>
                                        <div class="info-item">
                                            <strong>Payments Made:</strong>
                                            <div><?php echo count($repayments); ?> of <?php echo $loan['term_months']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <?php if ($loan['status'] === 'Pending'): ?>
                                <div class="card mt-3">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Application Status</h6>
                                        <p class="text-muted small">Your loan application is under review. You will be notified once a decision is made.</p>
                                        <div class="spinner-border spinner-border-sm text-warning" role="status">
                                            <span class="visually-hidden">Loading...</span>
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