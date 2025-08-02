<?php
/**
 * Admin - View Loan Details
 * 
 * This page displays detailed information about a specific loan application
 * including repayment history and actions for processing the loan.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once __DIR__ . '/../controllers/auth_controller.php';
require_once __DIR__ . '/../controllers/loan_controller.php';
require_once __DIR__ . '/../controllers/member_controller.php';

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
$remainingBalance = $loan['amount'] - $totalPaid;
$percentPaid = ($loan['amount'] > 0) ? ($totalPaid / $loan['amount']) * 100 : 0;

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
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print</button>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/admin/loans.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Loans
                    </a>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/loans.php">Loans</a></li>
                    <li class="breadcrumb-item active" aria-current="page">View Loan #<?php echo $loan_id; ?></li>
                </ol>
            </nav>
            
            <!-- Flash messages -->
            <?php include_once __DIR__ . '/../includes/flash_messages.php'; ?>
            
            <!-- Loan Status Badge -->
            <div class="row mb-4">
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
                    ?>">
                        <h4 class="alert-heading">Loan Status: <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?></h4>
                        <?php if ($loan['status'] === 'pending'): ?>
                            <p>This loan application is awaiting approval.</p>
                            <hr>
                            <div class="d-flex gap-2">
                                <a href="<?php echo BASE_URL; ?>/admin/process_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-success">Process Loan</a>
                                <a href="<?php echo BASE_URL; ?>/admin/edit_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-primary">Edit Application</a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">Delete Application</button>
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
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Loan Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Loan ID:</th>
                                            <td><?php echo $loan_id; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Amount:</th>
                                            <td><?php echo number_format($loan['amount'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Term:</th>
                                            <td><?php echo $loan['term_months']; ?> months</td>
                                        </tr>
                                        <tr>
                                            <th>Interest Rate:</th>
                                            <td><?php echo $loan['interest_rate']; ?>% per annum</td>
                                        </tr>
                                        <tr>
                                            <th>Monthly Payment:</th>
                                            <td><?php echo number_format($loan['monthly_payment'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Purpose:</th>
                                            <td><?php echo htmlspecialchars($loan['purpose']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Application Date:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                        </tr>
                                        <?php if (!empty($loan['approval_date'])): ?>
                                        <tr>
                                            <th>Approval Date:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan['approval_date'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($loan['disbursement_date'])): ?>
                                        <tr>
                                            <th>Disbursement Date:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan['disbursement_date'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($loan['last_payment_date'])): ?>
                                        <tr>
                                            <th>Last Payment Date:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan['last_payment_date'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Collateral:</th>
                                            <td><?php echo !empty($loan['collateral']) ? htmlspecialchars($loan['collateral']) : 'None'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Guarantor:</th>
                                            <td><?php echo !empty($loan['guarantor']) ? htmlspecialchars($loan['guarantor']) : 'None'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if (!empty($loan['notes'])): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Notes:</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($loan['notes'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (in_array($loan['status'], ['disbursed', 'active', 'paid'])): ?>
                    <!-- Repayment Progress -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Repayment Progress</h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo min(100, $percentPaid); ?>%;" 
                                             aria-valuenow="<?php echo $percentPaid; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo round($percentPaid); ?>%
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th>Total Paid:</th>
                                            <td><?php echo number_format($totalPaid, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Remaining Balance:</th>
                                            <td><?php echo number_format($remainingBalance, 2); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Repayment History -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Repayment History</h5>
                            <?php if (in_array($loan['status'], ['disbursed', 'active'])): ?>
                            <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan_id; ?>" class="btn btn-sm btn-success">
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
                                                    <td><?php echo number_format($repayment['amount'], 2); ?></td>
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
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Member Information</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($member): ?>
                                <div class="text-center mb-3">
                                    <?php if (!empty($member['photo'])): ?>
                                        <img src="<?php echo BASE_URL . '/uploads/members/' . $member['photo']; ?>" 
                                             alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                             class="img-fluid rounded-circle mb-2" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-2" 
                                             style="width: 100px; height: 100px;">
                                            <span class="text-white fs-1">
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
                    
                    <!-- Other Loans -->
                    <?php 
                    $otherLoans = $loanController->getLoansByMemberId($loan['member_id'], 5);
                    if (count($otherLoans) > 1): // More than just the current loan
                    ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Other Loans</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php foreach ($otherLoans as $otherLoan): ?>
                                    <?php if ($otherLoan['loan_id'] != $loan_id): ?>
                                        <a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $otherLoan['loan_id']; ?>" 
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold">Loan #<?php echo $otherLoan['loan_id']; ?></div>
                                                <small><?php echo date('M d, Y', strtotime($otherLoan['application_date'])); ?></small>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php 
                                                    echo match($otherLoan['status']) {
                                                        'pending' => 'warning',
                                                        'approved' => 'info',
                                                        'rejected' => 'danger',
                                                        'disbursed' => 'primary',
                                                        'active' => 'primary',
                                                        'defaulted' => 'danger',
                                                        'paid' => 'success',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo $loanStatuses[$otherLoan['status']] ?? ucfirst($otherLoan['status']); ?>
                                                </span>
                                                <div><?php echo number_format($otherLoan['amount'], 2); ?></div>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
    }
    
    body {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    @page {
        margin: 1cm;
    }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
