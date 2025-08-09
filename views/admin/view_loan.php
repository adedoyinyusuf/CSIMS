<?php
/**
 * Admin - View Loan Details
 * 
 * This page displays detailed information about a specific loan application
 * including repayment history and actions for processing the loan.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../controllers/loan_controller.php';
require_once __DIR__ . '/../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize controllers
$loanController = new LoanController();
$memberController = new MemberController();

// Check if loan ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "Loan ID is required";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: loans.php');
    exit();
}

$loan_id = (int)$_GET['id'];

// Get loan details
$loan = $loanController->getLoanById($loan_id);

if (!$loan) {
    $_SESSION['flash_message'] = "Loan not found";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: loans.php');
    exit();
}

// Get loan repayments
$repayments = $loanController->getLoanRepayments($loan_id);

// Get loan statuses for display
$loanStatuses = $loanController->getLoanStatuses();

// Get payment methods
$paymentMethods = $loanController->getPaymentMethods();

// Calculate loan summary
$totalPaid = $loan['amount_paid'] ?? 0;
$loanAmount = $loan['amount'] ?? 0;
$remainingBalance = $loanAmount - $totalPaid;
$percentPaid = ($loanAmount > 0) ? ($totalPaid / $loanAmount) * 100 : 0;

// Calculate due date based on disbursement date and term
$due_date = null;
if (!empty($loan['disbursement_date']) && !empty($loan['term'])) {
    $disbursement_date = new DateTime($loan['disbursement_date']);
    $disbursement_date->add(new DateInterval('P' . $loan['term'] . 'M')); // Add months
    $due_date = $disbursement_date->format('Y-m-d');
}

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Page title
$pageTitle = "Loan Details #" . $loan_id;

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main id="mainContent" class="main-content">
            <div class="page-header fade-in">
                <h1><?php echo $pageTitle; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/loans.php">Loans</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View Loan #<?php echo $loan_id; ?></li>
                    </ol>
                </nav>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-light" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/loans.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Back to Loans
                    </a>
                </div>
            </div>
            
            <!-- Flash messages -->
            <?php include_once __DIR__ . '/../includes/flash_messages.php'; ?>
            
            <!-- Loan Status Badge -->
            <div class="row mb-4 slide-in">
                <div class="col-12">
                    <div class="alert alert-<?php 
                        echo match($loan['status']) {
                            'pending' => 'warning',
                            'approved' => 'info',
                            'rejected' => 'danger',
                            'disbursed' => 'primary',
                            'active' => 'primary',
                            'defaulted' => 'danger',
                            'paid' => 'success',
                            default => 'secondary'
                        };
                    ?> shadow-sm border-0">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-<?php 
                                echo match($loan['status']) {
                                    'pending' => 'clock-history',
                                    'approved' => 'check-circle',
                                    'rejected' => 'x-circle',
                                    'disbursed' => 'cash-coin',
                                    'active' => 'activity',
                                    'defaulted' => 'exclamation-triangle',
                                    'paid' => 'check2-circle',
                                    default => 'info-circle'
                                };
                            ?> me-2 fs-4"></i>
                            <h4 class="alert-heading mb-0">Loan Status: <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?></h4>
                        </div>
                        <?php if ($loan['status'] === 'pending'): ?>
                            <p>This loan application is awaiting approval.</p>
                            <hr>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                                <a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-info">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin/edit_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Edit Application
                                </a>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i> Delete Application
                                </button>
                            </div>
                        <?php elseif ($loan['status'] === 'approved'): ?>
                            <p>This loan has been approved on <?php echo date('M d, Y', strtotime($loan['approval_date'])); ?> and is awaiting disbursement.</p>
                            <hr>
                            <div class="d-flex gap-2">
                                <a href="<?php echo BASE_URL; ?>/admin/process_loan.php?id=<?php echo $loan_id; ?>&action=disburse" class="btn btn-primary">Mark as Disbursed</a>
                            </div>
                        <?php elseif (in_array($loan['status'], ['disbursed', 'active'])): ?>
                            <p>This loan is active. Disbursed on <?php echo date('M d, Y', strtotime($loan['disbursement_date'])); ?>.</p>
                            <hr>
                            <div class="d-flex gap-2">
                                <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan_id; ?>" class="btn btn-success">Add Repayment</a>
                            </div>
                        <?php elseif ($loan['status'] === 'paid'): ?>
                            <p>This loan has been fully paid off. Last payment on <?php echo date('M d, Y', strtotime($loan['last_payment_date'])); ?>.</p>
                        <?php elseif ($loan['status'] === 'rejected'): ?>
                            <p>This loan application was rejected.</p>
                            <hr>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">Delete Application</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Loan Details -->
                <div class="col-md-8 fade-in">
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-header bg-light border-0">
                            <h5 class="card-title mb-0 d-flex align-items-center">
                                <i class="bi bi-file-text me-2 text-primary"></i>
                                Loan Details
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="loan-details-section">
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-hash text-primary me-2"></i>Loan ID</label>
                                            <div class="detail-value fw-bold text-primary fs-5">#<?php echo $loan_id; ?></div>
                                        </div>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-currency-dollar text-success me-2"></i>Amount</label>
                                            <div class="detail-value fw-bold fs-4 text-success">₱<?php echo number_format($loanAmount, 2); ?></div>
                                        </div>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-calendar-range text-info me-2"></i>Term</label>
                                            <div class="detail-value fw-semibold"><?php echo $loan['term']; ?> months</div>
                                        </div>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-percent text-warning me-2"></i>Interest Rate</label>
                                            <div class="detail-value fw-semibold"><?php echo $loan['interest_rate']; ?>%</div>
                                        </div>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-calendar-event text-secondary me-2"></i>Application Date</label>
                                            <div class="detail-value"><?php echo date('F d, Y', strtotime($loan['application_date'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="loan-details-section">
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-cash-coin text-success me-2"></i>Monthly Payment</label>
                                            <div class="detail-value fw-bold text-success">₱<?php echo number_format($loan['monthly_payment'] ?? 0, 2); ?></div>
                                        </div>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-calendar-check text-info me-2"></i>Due Date</label>
                                            <div class="detail-value"><?php echo $due_date ? date('F d, Y', strtotime($due_date)) : 'Not set'; ?></div>
                                        </div>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-flag text-primary me-2"></i>Status</label>
                                            <div class="detail-value">
                                                <span class="badge bg-<?php 
                                                    echo match($loan['status']) {
                                                        'pending' => 'warning',
                                                        'approved' => 'info',
                                                        'rejected' => 'danger',
                                                        'disbursed' => 'primary',
                                                        'active' => 'primary',
                                                        'defaulted' => 'danger',
                                                        'paid' => 'success',
                                                        default => 'secondary'
                                                    };
                                                ?> fs-6">
                                                    <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if (!empty($loan['purpose'])): ?>
                                        <div class="detail-item mb-4">
                                            <label class="detail-label"><i class="bi bi-card-text text-secondary me-2"></i>Purpose</label>
                                            <div class="detail-value"><?php echo htmlspecialchars($loan['purpose']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (in_array($loan['status'], ['disbursed', 'active', 'paid'])): ?>
                    <!-- Repayment Progress -->
                    <div class="card mb-4 shadow-sm border-0 slide-in">
                        <div class="card-header bg-light border-0">
                            <h5 class="card-title mb-0 d-flex align-items-center">
                                <i class="bi bi-graph-up me-2 text-success"></i>
                                Repayment Progress
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row align-items-center g-4">
                                <div class="col-md-6">
                                    <div class="progress-container">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Progress</span>
                                            <span class="fw-bold text-<?php echo $percentPaid >= 100 ? 'success' : ($percentPaid >= 50 ? 'warning' : 'danger'); ?>"><?php echo round($percentPaid); ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 30px; border-radius: 15px;">
                                            <div class="progress-bar bg-gradient bg-<?php echo $percentPaid >= 100 ? 'success' : ($percentPaid >= 50 ? 'warning' : 'info'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo min(100, $percentPaid); ?>%; border-radius: 15px;" 
                                                 aria-valuenow="<?php echo $percentPaid; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <span class="fw-bold"><?php echo round($percentPaid); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="payment-summary">
                                        <div class="summary-item mb-3">
                                            <div class="d-flex justify-content-between align-items-center p-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-wrapper bg-success bg-opacity-10 p-3 rounded-circle me-3">
                                                        <i class="bi bi-cash-stack text-success fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold text-muted d-block">Total Paid</span>
                                                        <span class="fw-bold text-success fs-4">₱<?php echo number_format($totalPaid, 2); ?></span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted"><?php echo number_format(($totalPaid / $loanAmount) * 100, 1); ?>% of loan</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="d-flex justify-content-between align-items-center p-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-wrapper <?php echo $remainingBalance > 0 ? 'bg-warning' : 'bg-success'; ?> bg-opacity-10 p-3 rounded-circle me-3">
                                                        <i class="bi bi-hourglass-split <?php echo $remainingBalance > 0 ? 'text-warning' : 'text-success'; ?> fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold text-muted d-block">Remaining Balance</span>
                                                        <span class="fw-bold <?php echo $remainingBalance <= 0 ? 'text-success' : 'text-warning'; ?> fs-4">₱<?php echo number_format($remainingBalance, 2); ?></span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted"><?php echo $remainingBalance > 0 ? number_format((($loanAmount - $remainingBalance) / $loanAmount) * 100, 1) . '% paid' : 'Fully paid'; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Repayment History -->
                    <div class="card mb-4 shadow-sm border-0 fade-in">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 d-flex align-items-center">
                                <i class="bi bi-clock-history me-2 text-info"></i>
                                Repayment History
                            </h5>
                            <?php if (in_array($loan['status'], ['disbursed', 'active'])): ?>
                            <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan_id; ?>" class="btn btn-sm btn-success shadow-sm">
                                <i class="bi bi-plus-circle"></i> Add Repayment
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($repayments)): ?>
                                <p class="text-center">No repayments recorded yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Payment Method</th>
                                                <th>Receipt #</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($repayments as $repayment): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($repayment['payment_date'])); ?></td>
                                                    <td>₱<?php echo number_format($repayment['amount'] ?? 0, 2); ?></td>
                                                    <td><?php echo htmlspecialchars($repayment['payment_method']); ?></td>
                                                    <td><?php echo !empty($repayment['receipt_number']) ? htmlspecialchars($repayment['receipt_number']) : '-'; ?></td>
                                                    <td><?php echo !empty($repayment['notes']) ? htmlspecialchars($repayment['notes']) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Member Information -->
                <div class="col-md-4 slide-in">
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-header bg-light border-0">
                            <h5 class="card-title mb-0 d-flex align-items-center">
                                <i class="bi bi-person-circle me-2 text-primary"></i>
                                Member Information
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($member): ?>
                                <div class="text-center mb-3">
                                    <?php 
                                    // Check if photo exists and file is accessible
                                    $photo_path = !empty($member['photo']) ? BASE_URL . '/uploads/members/' . $member['photo'] : null;
                                    $photo_file_exists = !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/CSIMS/uploads/members/' . $member['photo']);
                                    ?>
                                    <?php if (!empty($member['photo']) && $photo_file_exists): ?>
                                        <img src="<?php echo $photo_path; ?>" 
                                             alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                             class="img-fluid rounded-circle mb-2" 
                                             style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #e9ecef;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-2" 
                                             style="width: 100px; height: 100px; border: 3px solid #e9ecef;">
                                            <span class="text-white fs-1 fw-bold">
                                                <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                                    <p class="text-muted">Member ID: <?php echo $member['member_id']; ?></p>
                                </div>
                                
                                <table class="table table-borderless">
                                    <tr>
                                        <th><i class="bi bi-envelope"></i> Email:</th>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-telephone"></i> Phone:</th>
                                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-geo-alt"></i> Address:</th>
                                        <td><?php echo htmlspecialchars($member['address']); ?></td>
                                    </tr>
                                </table>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-outline-primary">
                                        View Full Profile
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-center">Member information not available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    

                </div>
            </div>
        </main>
    </div>
</div>

<!-- Approve Loan Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo BASE_URL; ?>/admin/process_loan.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="approveModalLabel">
                        <i class="bi bi-check-circle me-2"></i>Approve Loan Application
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Loan Details:</strong><br>
                        Amount: ₱<?php echo number_format($loanAmount, 2); ?><br>
                        Term: <?php echo $loan['term']; ?> months<br>
                        Monthly Payment: ₱<?php echo number_format($loan['monthly_payment'] ?? 0, 2); ?>
                    </div>
                    <p>Are you sure you want to approve this loan application?</p>
                    <div class="mb-3">
                        <label for="approve_notes" class="form-label">Approval Notes (Optional)</label>
                        <textarea class="form-control" id="approve_notes" name="notes" rows="3" placeholder="Add any notes about the approval..."></textarea>
                    </div>
                    <input type="hidden" name="loan_id" value="<?php echo $loan_id; ?>">
                    <input type="hidden" name="action" value="approve">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Approve Loan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Loan Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo BASE_URL; ?>/admin/process_loan.php">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Reject Loan Application
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will reject the loan application and cannot be easily undone.
                    </div>
                    <p>Please provide a reason for rejecting this loan application:</p>
                    <div class="mb-3">
                        <label for="reject_notes" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_notes" name="notes" rows="4" placeholder="Please provide a clear reason for rejection..." required></textarea>
                        <div class="invalid-feedback">
                            Please provide a reason for rejection.
                        </div>
                    </div>
                    <input type="hidden" name="loan_id" value="<?php echo $loan_id; ?>">
                    <input type="hidden" name="action" value="reject">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Reject Loan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this loan application? This action cannot be undone.</p>
                <p class="text-danger"><strong>Note:</strong> Only pending or rejected loan applications can be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?php echo BASE_URL; ?>/admin/delete_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
    /* Main content responsive layout */
    .main-content {
        margin-left: 256px;
        padding: 1.5rem;
        padding-bottom: 3rem;
        width: calc(100% - 256px);
        max-width: calc(100% - 256px);
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        position: relative;
        z-index: 1;
        overflow-x: hidden;
        box-sizing: border-box;
    }
    
    .main-content::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            radial-gradient(circle at 25% 25%, rgba(102, 126, 234, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 75% 75%, rgba(118, 75, 162, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        background-size: 800px 800px, 600px 600px, 400px 400px;
        background-position: 0% 0%, 100% 100%, 50% 50%;
        animation: backgroundFloat 20s ease-in-out infinite;
        z-index: -1;
        pointer-events: none;
    }
    
    @keyframes backgroundFloat {
        0%, 100% {
            background-position: 0% 0%, 100% 100%, 50% 50%;
        }
        33% {
            background-position: 30% 20%, 80% 70%, 60% 40%;
        }
        66% {
            background-position: 70% 80%, 20% 30%, 40% 60%;
        }
    }
    
    /* Enhanced Mobile responsive */
    @media (max-width: 992px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            max-width: 100%;
            padding: 1rem;
            padding-top: 1.5rem;
            padding-bottom: 3rem;
        }
    }
    
    @media (max-width: 576px) {
        .main-content {
            padding: 0.75rem;
            padding-top: 1rem;
            padding-bottom: 2.5rem;
        }
    }
    
    /* Sidebar collapsed state */
    .main-content.sidebar-collapsed {
        margin-left: 4rem;
        width: calc(100% - 4rem);
        max-width: calc(100% - 4rem);
        padding-bottom: 3rem;
    }
    
    @media (max-width: 992px) {
        .main-content.sidebar-collapsed {
            margin-left: 0;
            width: 100%;
            max-width: 100%;
            padding-bottom: 3rem;
        }
    }
    
    /* Container and Row Enhancements */
    .container-fluid {
        padding: 0;
        margin: 0;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    .row {
        margin: 0;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    .row > .col-md-8,
    .row > .col-md-4 {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box;
    }
    
    @media (max-width: 768px) {
        .row > .col-md-8,
        .row > .col-md-4 {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
    }
    
    /* Ensure cards stay within bounds */
    .main-content .card {
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box;
    }
    
    /* Page Header Styling */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.1) 75%, transparent 75%);
        background-size: 20px 20px;
        opacity: 0.3;
        pointer-events: none;
    }
    
    .page-header h1 {
        margin: 0;
        font-weight: 700;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        position: relative;
        z-index: 2;
    }
    
    @media (max-width: 576px) {
        .page-header {
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
        }
    }
    
    .breadcrumb {
        background: rgba(255,255,255,0.1);
        border-radius: 25px;
        padding: 0.5rem 1rem;
        margin-top: 1rem;
    }
    
    .breadcrumb-item a {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
    }
    
    .breadcrumb-item.active {
        color: white;
    }
    
    /* Enhanced Card Styling */
    .card {
        border: 1px solid rgba(0,0,0,0.08) !important;
        border-radius: 20px;
        box-shadow: 0 6px 25px rgba(0,0,0,0.1);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        background: rgba(255, 255, 255, 0.95);
        margin-bottom: 2rem;
        position: relative;
        backdrop-filter: blur(15px);
    }
    
    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-6px);
        box-shadow: 0 15px 45px rgba(0,0,0,0.2);
        border-color: rgba(102, 126, 234, 0.3) !important;
    }
    
    .card:hover::before {
        opacity: 1;
    }
    
    .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        border-bottom: 1px solid rgba(0,0,0,0.1) !important;
        padding: 1.75rem 2rem !important;
        border-radius: 20px 20px 0 0 !important;
        position: relative;
    }
    
    .card-header h5 {
        margin: 0;
        font-weight: 700;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.2rem;
    }
    
    .card-body {
        padding: 2rem;
    }
    
    /* Ensure equal height cards */
    .row .col-md-8,
    .row .col-md-4 {
        display: flex;
        flex-direction: column;
    }
    
    .row .col-md-8 .card,
    .row .col-md-4 .card {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .row .col-md-8 .card-body,
    .row .col-md-4 .card-body {
        flex: 1;
    }
    
    @media (max-width: 576px) {
        .card {
            margin-bottom: 1rem;
            border-radius: 12px;
        }
        
        .card-header {
            padding: 1rem !important;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .card-body {
            padding: 1rem;
        }
    }
    
    /* Fix for last card spacing and alignment */
    .col-md-4 .card:last-child,
    .col-md-8 .card:last-child {
        margin-bottom: 3rem;
    }
    
    /* Ensure proper column spacing */
    .col-md-4,
    .col-md-8 {
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .col-md-4 .card:last-child,
        .col-md-8 .card:last-child {
            margin-bottom: 2.5rem;
        }
        
        .col-md-4,
        .col-md-8 {
            margin-bottom: 1.5rem;
        }
    }
    
    /* Fix for main row container */
    main .row {
        margin-bottom: 2rem;
    }
    
    main .row:last-child {
        margin-bottom: 3rem;
    }
    
    /* Detail Items Enhancement */
    .detail-item {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        backdrop-filter: blur(15px);
        display: flex;
        flex-direction: column;
        min-height: 120px;
        justify-content: center;
    }
    
    .detail-item:hover {
        background: rgba(255, 255, 255, 1);
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        border-color: rgba(102, 126, 234, 0.3);
    }
    
    .detail-item:last-child {
        margin-bottom: 0;
    }
    
    .detail-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
        line-height: 1.2;
    }
    
    .detail-label i {
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }
    
    .detail-value {
        font-size: 1.1rem;
        color: #2c3e50;
        line-height: 1.4;
        font-weight: 600;
        word-break: break-word;
        flex-grow: 1;
        display: flex;
        align-items: center;
    }
    
    .loan-details-section {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .loan-details-section .detail-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    /* Grid Layout Enhancement */
    .row.g-4 {
        --bs-gutter-x: 2rem;
        --bs-gutter-y: 2rem;
    }
    
    @media (max-width: 768px) {
        .row.g-4 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }
        
        .detail-item {
            padding: 1.25rem;
            min-height: 100px;
        }
        
        .detail-label {
            font-size: 0.7rem;
        }
        
        .detail-value {
            font-size: 1rem;
        }
    }
    
    /* Status Badge Enhancement */
    .alert {
        border-radius: 12px;
        border: 1px solid;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
        border-left: 4px solid;
        backdrop-filter: blur(10px);
        font-weight: 500;
    }
    
    .alert::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.9) 50%, rgba(255,255,255,0.4) 100%);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    .alert-success {
        background: linear-gradient(135deg, rgba(209, 242, 235, 0.95) 0%, rgba(163, 228, 215, 0.95) 100%);
        border-color: rgba(25, 135, 84, 0.3);
        border-left-color: #198754;
        box-shadow: 0 6px 20px rgba(26, 188, 156, 0.25);
        color: #0f5132;
    }
    
    .alert-warning {
        background: linear-gradient(135deg, rgba(254, 249, 231, 0.95) 0%, rgba(252, 243, 207, 0.95) 100%);
        border-color: rgba(255, 193, 7, 0.3);
        border-left-color: #ffc107;
        box-shadow: 0 6px 20px rgba(241, 196, 15, 0.25);
        color: #664d03;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, rgba(250, 219, 216, 0.95) 0%, rgba(245, 183, 177, 0.95) 100%);
        border-color: rgba(220, 53, 69, 0.3);
        border-left-color: #dc3545;
        box-shadow: 0 6px 20px rgba(231, 76, 60, 0.25);
        color: #721c24;
    }
    
    .alert-info {
        background: linear-gradient(135deg, rgba(214, 234, 248, 0.95) 0%, rgba(174, 214, 241, 0.95) 100%);
        border-color: rgba(13, 202, 240, 0.3);
        border-left-color: #0dcaf0;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.25);
        color: #055160;
    }
    
    .alert-primary {
        background: linear-gradient(135deg, rgba(204, 229, 255, 0.95) 0%, rgba(153, 214, 255, 0.95) 100%);
        border-color: rgba(13, 110, 253, 0.3);
        border-left-color: #0d6efd;
        box-shadow: 0 6px 20px rgba(13, 110, 253, 0.25);
        color: #052c65;
    }
    
    @media (max-width: 576px) {
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
    }
    
    /* Progress Bar Enhancement */
    .progress {
        height: 25px;
        border-radius: 20px;
        background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        box-shadow: inset 0 3px 6px rgba(0,0,0,0.15), 0 2px 4px rgba(0,0,0,0.05);
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    .progress::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, rgba(255,255,255,0.5), transparent);
        z-index: 2;
    }
    
    .progress-bar {
        border-radius: 20px;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    
    .progress-bar.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        color: #212529;
        text-shadow: none;
    }
    
    .progress-bar.bg-info {
        background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
    }
    
    .progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0.15) 75%, transparent 75%);
        background-size: 15px 15px;
        animation: progress-animation 2s linear infinite;
    }
    
    @keyframes progress-animation {
        0% { background-position: 0 0; }
        100% { background-position: 15px 0; }
    }
    
    .progress-container {
        background: rgba(255,255,255,0.9);
        padding: 2rem;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.08);
        backdrop-filter: blur(15px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 1rem;
    }
    
    .progress-container .d-flex {
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    
    .progress-container .text-muted {
        font-weight: 600;
        font-size: 0.9rem;
        color: #6c757d !important;
    }
    
    .progress-container .fw-bold {
        font-size: 1.1rem;
        font-weight: 700;
    }
    
    /* Button Enhancement */
    .btn {
        border-radius: 10px;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid transparent;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.4s;
    }
    
    .btn:hover::before {
        left: 100%;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    }
    
    .btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 3px 12px rgba(102, 126, 234, 0.4);
        color: white;
        border-color: rgba(102, 126, 234, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        box-shadow: 0 3px 12px rgba(40, 167, 69, 0.4);
        color: white;
        border-color: rgba(40, 167, 69, 0.3);
    }
    
    .btn-success:hover {
        background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.5);
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        box-shadow: 0 3px 12px rgba(255, 193, 7, 0.4);
        color: #212529;
        border-color: rgba(255, 193, 7, 0.3);
    }
    
    .btn-warning:hover {
        background: linear-gradient(135deg, #e0a800 0%, #e8650e 100%);
        box-shadow: 0 6px 20px rgba(255, 193, 7, 0.5);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        box-shadow: 0 3px 12px rgba(220, 53, 69, 0.4);
        color: white;
        border-color: rgba(220, 53, 69, 0.3);
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.5);
    }
    
    .btn-light {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        color: #495057;
        border-color: rgba(0, 0, 0, 0.1);
    }
    
    .btn-light:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        color: #495057;
    }
    
    .btn-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        box-shadow: 0 3px 12px rgba(23, 162, 184, 0.4);
        color: white;
        border-color: rgba(23, 162, 184, 0.3);
    }
    
    .btn-info:hover {
        background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
        box-shadow: 0 6px 20px rgba(23, 162, 184, 0.5);
    }
    
    @media (max-width: 576px) {
        .btn {
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }
    }
    
    /* List Group Enhancement */
    .list-group-item {
        border: none;
        border-radius: 12px !important;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
        padding: 1rem 1.5rem;
    }
    
    .list-group-item:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    /* Payment Summary Enhancement */
    .payment-summary {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 16px;
        padding: 0;
        margin: 1rem 0;
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        backdrop-filter: blur(10px);
        overflow: hidden;
    }
    
    .payment-summary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: rgba(102, 126, 234, 0.2);
    }
    
    .summary-item {
        border-radius: 12px;
        background: rgba(255,255,255,0.7);
        margin-bottom: 0.75rem;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.03);
    }
    
    .summary-item:last-child {
        margin-bottom: 0;
    }
    
    .summary-item:hover {
        background: rgba(255,255,255,0.95);
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .icon-wrapper {
        transition: all 0.3s ease;
    }
    
    .summary-item:hover .icon-wrapper {
        transform: scale(1.1);
    }
    
    /* List Group Enhancement */
    .list-group {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.05);
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
    }
    
    .list-group-item {
        border: none;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        background: transparent;
        transition: all 0.3s ease;
        padding: 1rem 1.25rem;
        position: relative;
        overflow: hidden;
    }
    
    .list-group-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }
    
    .list-group-item:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: translateX(8px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        z-index: 2;
    }
    
    .list-group-item:hover::before {
        transform: scaleY(1);
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    .list-group-item .d-flex {
        align-items: center;
        gap: 0.75rem;
    }
    
    .list-group-item .badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    
    /* Icon Enhancement */
    .bi, .fas, .fa {
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
        transition: all 0.3s ease;
    }
    
    .list-group-item:hover .bi,
    .list-group-item:hover .fas,
    .list-group-item:hover .fa {
        transform: scale(1.1);
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }
    
    /* Modal Enhancement */
    .modal-content {
        border: none;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        backdrop-filter: blur(10px);
        background: rgba(255,255,255,0.95);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px 20px 0 0;
        border-bottom: none;
        padding: 1.5rem;
    }
    
    .modal-header .modal-title {
        font-weight: 700;
        font-size: 1.25rem;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }
    
    .modal-header .btn-close:hover {
        opacity: 1;
        transform: scale(1.1);
    }
    
    .modal-body {
        padding: 2rem;
    }
    
    .modal-footer {
        border-top: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem 2rem;
        background: rgba(248,249,250,0.5);
        border-radius: 0 0 20px 20px;
    }
    
    /* Form Enhancement */
    .form-control {
        border-radius: 12px;
        border: 2px solid rgba(0,0,0,0.1);
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        background: rgba(255,255,255,0.8);
        backdrop-filter: blur(5px);
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        background: white;
        transform: translateY(-1px);
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.75rem;
    }
    
    /* Responsive Modal */
    @media (max-width: 576px) {
        .modal-dialog {
            margin: 1rem;
        }
        
        .modal-content {
            border-radius: 16px;
        }
        
        .modal-header {
            padding: 1rem;
            border-radius: 16px 16px 0 0;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-radius: 0 0 16px 16px;
        }
    }
    
    /* Table Enhancement */
    .table-responsive {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(0,0,0,0.08);
    }
    
    .table {
        margin-bottom: 0;
        background: transparent;
    }
    
    .table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        font-weight: 700;
        padding: 1.5rem 1.25rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        position: relative;
        text-align: center;
        vertical-align: middle;
    }
    
    .table thead th:first-child {
        text-align: left;
    }
    
    .table thead th::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.8), rgba(255,255,255,0.3));
    }
    
    .table tbody td {
        padding: 1.25rem;
        border-color: rgba(0,0,0,0.08);
        font-weight: 500;
        vertical-align: middle;
        text-align: center;
        background: rgba(255,255,255,0.7);
    }
    
    .table tbody td:first-child {
        text-align: left;
        font-weight: 600;
    }
    
    .table tbody tr {
        transition: all 0.3s ease;
    }
    
    .table tbody tr:hover {
        background: rgba(102, 126, 234, 0.05);
    }
    
    .table tbody tr:hover td {
        background: rgba(102, 126, 234, 0.08);
        transform: translateX(2px);
    }
    
    .table-borderless th,
    .table-borderless td {
        border: none;
        padding: 0.75rem 0;
    }
    
    .table-borderless th {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
        width: 40%;
    }
    
    .table-borderless td {
        color: #2c3e50;
        font-weight: 500;
    }
    
    /* Animation Classes */
    .fade-in {
        animation: fadeIn 0.6s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .slide-in {
        animation: slideIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
</style>

<!-- Print Styles -->
<style media="print">
    .sidebar, .navbar, .btn, .breadcrumb, .alert .btn, .modal, .no-print {
        display: none !important;
    }
    
    main {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        margin-bottom: 20px !important;
        box-shadow: none !important;
        transform: none !important;
    }
    
    body {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    @page {
        margin: 1cm;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation for reject modal
    const rejectForm = document.querySelector('#rejectModal form');
    const rejectNotesField = document.getElementById('reject_notes');
    
    if (rejectForm) {
        rejectForm.addEventListener('submit', function(event) {
            if (!rejectNotesField.value.trim()) {
                event.preventDefault();
                event.stopPropagation();
                rejectNotesField.classList.add('is-invalid');
                return false;
            }
            rejectNotesField.classList.remove('is-invalid');
            rejectNotesField.classList.add('is-valid');
        });
        
        // Real-time validation
        rejectNotesField.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }
    
    // Confirmation dialogs
    const approveForm = document.querySelector('#approveModal form');
    if (approveForm) {
        approveForm.addEventListener('submit', function(event) {
            if (!confirm('Are you sure you want to approve this loan application?')) {
                event.preventDefault();
                return false;
            }
        });
    }
    
    // Auto-focus on modal open
    const approveModal = document.getElementById('approveModal');
    const rejectModal = document.getElementById('rejectModal');
    
    if (approveModal) {
        approveModal.addEventListener('shown.bs.modal', function() {
            document.getElementById('approve_notes').focus();
        });
    }
    
    if (rejectModal) {
        rejectModal.addEventListener('shown.bs.modal', function() {
            document.getElementById('reject_notes').focus();
        });
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
