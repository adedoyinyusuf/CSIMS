<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/member_controller.php';

try {
    $loanController = new LoanController();
    $memberController = new MemberController();

    $member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];
    $member = $memberController->getMemberById($member_id);

    if (!$member) {
        header('Location: member_login.php');
        exit();
    }

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
    if (in_array($loan['status'], ['Approved', 'Disbursed', 'Active'])) {
        $repayments = $loanController->getLoanRepayments($loan_id);
        if ($repayments === false) {
            $repayments = [];
        }
    }

    // Generate amortization schedule aligned with admin dashboard logic
    $paymentSchedule = $loanController->getLoanPaymentSchedule($loan_id);

    // Schema-resilient calculations
    $has_remaining_balance = false;
    $has_amount_paid = false;
    $has_total_repaid = false;
    $has_principal_amount = false;
    try {
        $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'");
        if ($chk && $chk->num_rows > 0) { $has_remaining_balance = true; }
        $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
        if ($chk && $chk->num_rows > 0) { $has_amount_paid = true; }
        $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
        if ($chk && $chk->num_rows > 0) { $has_total_repaid = true; }
        $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'principal_amount'");
        if ($chk && $chk->num_rows > 0) { $has_principal_amount = true; }
    } catch (Exception $e) { /* ignore schema checks */ }

    // Principal detection
    $principal = (float)($loan['amount'] ?? ($has_principal_amount ? ($loan['principal_amount'] ?? 0) : 0));

    // Calculate total paid preferring repayments, then loan fields
    $total_paid = 0.0;
    if (!empty($repayments)) {
        foreach ($repayments as $repayment) {
            $total_paid += (float)$repayment['amount'];
        }
    } elseif ($has_amount_paid && isset($loan['amount_paid'])) {
        $total_paid = (float)$loan['amount_paid'];
    } elseif ($has_total_repaid && isset($loan['total_repaid'])) {
        $total_paid = (float)$loan['total_repaid'];
    }

    // Remaining balance prefers loan.remaining_balance when available
    if ($has_remaining_balance && isset($loan['remaining_balance'])) {
        $remaining_balance = (float)$loan['remaining_balance'];
    } else {
        $remaining_balance = max(0.0, $principal - $total_paid);
    }

    // Progress based on remaining vs principal when available
    $progress_percentage = ($principal > 0)
        ? ((($principal - $remaining_balance) / $principal) * 100)
        : 0.0;
    
} catch (Exception $e) {
    error_log("Error in loan details page: " . $e->getMessage());
    header('Location: member_loans.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details - NPC CTLStaff Loan Society</title>
    <!-- Assets centralized via includes/member_header.php -->
    <style>
        .sidebar {
            min-height: 100vh;
            background: #ffffff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.06);
        }
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--text-primary);
            background-color: var(--primary-50);
        }
        /* Ensure any legacy white text is readable on white sidebar */
        .sidebar .text-white, .sidebar .text-white-50 { color: var(--text-secondary) !important; }
        .sidebar h4 { color: var(--text-primary); }
        .sidebar .fw-bold { color: var(--text-primary); }
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
<?php include __DIR__ . '/includes/member_header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar removed; global navigation provided by shared header -->
            
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
                                                <div class="text-primary fs-4">₦<?php echo number_format($principal, 2); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Application Date:</strong>
                                                <div><?php echo date('F j, Y', strtotime($loan['application_date'])); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Loan Term:</strong>
                                                <div><?php echo isset($loan['term_months']) ? $loan['term_months'] : (isset($loan['term']) ? $loan['term'] : 'N/A'); ?> months</div>
                                            </div>
                                            <div class="info-item">
                                                <strong>Interest Rate:</strong>
                                                <div><?php echo isset($loan['interest_rate']) ? number_format($loan['interest_rate'], 2) : 'N/A'; ?>% per annum</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-item">
                                                <strong>Monthly Payment:</strong>
                                                <div class="text-success fs-5">₦<?php echo isset($loan['monthly_payment']) ? number_format($loan['monthly_payment'], 2) : 'N/A'; ?></div>
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

                            <!-- Repayment Progress -->
                            <div class="card mb-4 progress-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="card-title mb-0">Repayment Progress</h5>
                                        <span class="fw-bold"><?php echo number_format($progress_percentage, 1); ?>%</span>
                                    </div>
                                    <div class="progress bg-white bg-opacity-25" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo max(0,min(100,$progress_percentage)); ?>%" aria-valuenow="<?php echo (int)$progress_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 small">
                                        <span>Paid: ₦<?php echo number_format($total_paid, 2); ?></span>
                                        <span>Outstanding: ₦<?php echo number_format($remaining_balance, 2); ?></span>
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
                                            <p class="text-muted"><?php echo isset($loan['collateral']) && !empty($loan['collateral']) ? nl2br(htmlspecialchars($loan['collateral'])) : 'No collateral specified'; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-user-shield me-2"></i> Guarantor</h6>
                                            <p class="text-muted"><?php echo isset($loan['guarantor']) && !empty($loan['guarantor']) ? nl2br(htmlspecialchars($loan['guarantor'])) : 'No guarantor specified'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Repayments -->
                            <?php if ($loan['status'] === 'Active' && !empty($repayments)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Recent Repayments</h5>
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

                        <div class="col-lg-4">
                            <!-- Non-transaction cards removed for member focus -->
                        </div>

                            <!-- Payment Structure omitted for members -->

                            <!-- Amortization Schedule intentionally omitted for members -->

                            <!-- Payment schedule info omitted for members -->
                            
                            <!-- Remarks -->
                            <?php if (isset($loan['remarks']) && !empty($loan['remarks'])): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Remarks</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($loan['remarks'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
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
