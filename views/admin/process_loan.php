<?php
/**
 * Admin - Process Loan Application
 * 
 * This page allows administrators to approve, reject, or disburse loan applications.
 * It handles the workflow for loan status changes and related operations.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../controllers/member_controller.php';

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

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Check if loan can be processed
$canBeApproved = ($loan['status'] === 'pending');
$canBeRejected = ($loan['status'] === 'pending');
$canBeDisbursed = ($loan['status'] === 'approved');

if (!$canBeApproved && !$canBeRejected && !$canBeDisbursed) {
    $_SESSION['flash_message'] = "This loan cannot be processed in its current status: " . ucfirst($loan['status']);
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: view_loan.php?id=' . $loan_id);
    exit();
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate action
    if (empty($_POST['action'])) {
        $errors[] = "Action is required";
    } else {
        $action = $_POST['action'];
        
        // Validate action based on current loan status
        if (($action === 'approve' && !$canBeApproved) ||
            ($action === 'reject' && !$canBeRejected) ||
            ($action === 'disburse' && !$canBeDisbursed)) {
            $errors[] = "Invalid action for the current loan status";
        }
        
        // Additional validation for disbursement
        if ($action === 'disburse') {
            if (empty($_POST['disbursement_date'])) {
                $errors[] = "Disbursement date is required";
            }
            
            if (empty($_POST['payment_method']) || !in_array($_POST['payment_method'], $loanController->getPaymentMethods())) {
                $errors[] = "Valid payment method is required";
            }
        }
        
        // Process the action if no errors
        if (empty($errors)) {
            $result = false;
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            switch ($action) {
                case 'approve':
                    $result = $loanController->approveLoan($loan_id, $notes);
                    $successMessage = "Loan application approved successfully";
                    break;
                    
                case 'reject':
                    $result = $loanController->rejectLoan($loan_id, $notes);
                    $successMessage = "Loan application rejected successfully";
                    break;
                    
                case 'disburse':
                    $disbursementData = [
                        'disbursement_date' => $_POST['disbursement_date'],
                        'payment_method' => $_POST['payment_method'],
                        'notes' => $notes
                    ];
                    $result = $loanController->disburseLoan($loan_id, $disbursementData);
                    $successMessage = "Loan disbursed successfully";
                    break;
            }
            
            if ($result) {
                // Set success message and redirect
                $_SESSION['flash_message'] = $successMessage;
                $_SESSION['flash_message_class'] = "alert-success";
                header('Location: view_loan.php?id=' . $loan_id);
                exit();
            } else {
                $errors[] = "Failed to process loan. Please try again.";
            }
        }
    }
}

// Page title based on available actions
if ($canBeApproved || $canBeRejected) {
    $pageTitle = "Review Loan Application #" . $loan_id;
} else if ($canBeDisbursed) {
    $pageTitle = "Disburse Loan #" . $loan_id;
} else {
    $pageTitle = "Process Loan #" . $loan_id;
}

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
                    <a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Loan Details
                    </a>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/loans.php">Loans</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $loan_id; ?>">View Loan #<?php echo $loan_id; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Process</li>
                </ol>
            </nav>
            
            <!-- Error messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Member Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Member Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <?php if (!empty($member['photo'])): ?>
                                <img src="<?php echo '../../uploads/members/' . $member['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                     class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-2" 
                                     style="width: 80px; height: 80px;">
                                    <span class="text-white fs-3">
                                        <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <h5><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                            <p class="mb-0">Member ID: <?php echo $member['member_id']; ?></p>
                            <p class="mb-0">Email: <?php echo htmlspecialchars($member['email']); ?></p>
                            <p class="mb-0">Phone: <?php echo htmlspecialchars($member['phone']); ?></p>
                            <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                View Full Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loan Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Loan Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Loan ID:</strong> <?php echo $loan['loan_id']; ?></p>
                            <p><strong>Amount:</strong> $<?php echo number_format($loan['amount'], 2); ?></p>
                            <p><strong>Term:</strong> <?php echo $loan['term']; ?> months</p>
                            <p><strong>Interest Rate:</strong> <?php echo $loan['interest_rate']; ?>%</p>
                            <p><strong>Monthly Payment:</strong> $<?php echo number_format($loanController->calculateMonthlyPayment($loan['amount'], $loan['interest_rate'], $loan['term']), 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo $loanController->getStatusBadgeClass($loan['status']); ?>"><?php echo ucfirst($loan['status']); ?></span></p>
                            <p><strong>Application Date:</strong> <?php echo date('F j, Y', strtotime($loan['application_date'])); ?></p>
                            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($loan['purpose']); ?></p>
                            <?php if (!empty($loan['collateral'])): ?>
                                <p><strong>Collateral:</strong> <?php echo htmlspecialchars($loan['collateral']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($loan['guarantor'])): ?>
                                <p><strong>Guarantor:</strong> <?php echo htmlspecialchars($loan['guarantor']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Process Loan Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Process Loan</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="processLoanForm" class="needs-validation" novalidate>
                        <?php if ($canBeApproved || $canBeRejected): ?>
                            <!-- Action Buttons for Pending Loans -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="btn-group" role="group" aria-label="Loan Actions">
                                        <?php if ($canBeApproved): ?>
                                            <input type="radio" class="btn-check" name="action" id="action-approve" value="approve" autocomplete="off" required>
                                            <label class="btn btn-outline-success" for="action-approve">Approve Loan</label>
                                        <?php endif; ?>
                                        
                                        <?php if ($canBeRejected): ?>
                                            <input type="radio" class="btn-check" name="action" id="action-reject" value="reject" autocomplete="off" required>
                                            <label class="btn btn-outline-danger" for="action-reject">Reject Loan</label>
                                        <?php endif; ?>
                                    </div>
                                    <div class="invalid-feedback d-block" id="action-feedback" style="display: none;">Please select an action</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($canBeDisbursed): ?>
                            <!-- Disbursement Form for Approved Loans -->
                            <input type="hidden" name="action" value="disburse">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="disbursement_date" class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="disbursement_date" name="disbursement_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">Please provide a disbursement date</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <?php foreach ($loanController->getPaymentMethods() as $method): ?>
                                            <option value="<?php echo $method; ?>"><?php echo ucfirst($method); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a payment method</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Notes Field for All Actions -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any additional notes or comments about this action"></textarea>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary" id="submit-btn">
                                    <?php if ($canBeApproved || $canBeRejected): ?>
                                        Process Loan
                                    <?php elseif ($canBeDisbursed): ?>
                                        Disburse Loan
                                    <?php endif; ?>
                                </button>
                                <a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('processLoanForm');
        
        form.addEventListener('submit', function(event) {
            // Check if action is selected when required
            const actionRadios = document.querySelectorAll('input[name="action"]');
            const actionFeedback = document.getElementById('action-feedback');
            
            if (actionRadios.length > 1) {
                let actionSelected = false;
                actionRadios.forEach(radio => {
                    if (radio.checked) actionSelected = true;
                });
                
                if (!actionSelected) {
                    event.preventDefault();
                    event.stopPropagation();
                    actionFeedback.style.display = 'block';
                } else {
                    actionFeedback.style.display = 'none';
                }
            }
            
            // Regular form validation
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
        
        // Update submit button text based on selected action
        const actionRadios = document.querySelectorAll('input[name="action"]');
        const submitBtn = document.getElementById('submit-btn');
        
        actionRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'approve') {
                    submitBtn.textContent = 'Approve Loan';
                    submitBtn.className = 'btn btn-success';
                } else if (this.value === 'reject') {
                    submitBtn.textContent = 'Reject Loan';
                    submitBtn.className = 'btn btn-danger';
                }
            });
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
