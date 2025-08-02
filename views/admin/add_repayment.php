<?php
/**
 * Admin - Add Loan Repayment
 * 
 * This page provides a form for adding loan repayments.
 * Only active loans can receive repayments.
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

// Only active loans can receive repayments
if ($loan['status'] !== 'active') {
    $_SESSION['flash_message'] = "Only active loans can receive repayments";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: view_loan.php?id=' . $loan_id);
    exit();
}

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Get repayment history
$repayments = $loanController->getLoanRepayments($loan_id);

// Calculate loan statistics
$totalPaid = 0;
foreach ($repayments as $repayment) {
    $totalPaid += $repayment['amount'];
}

$monthlyPayment = $loanController->calculateMonthlyPayment($loan['amount'], $loan['interest_rate'], $loan['term_months']);
$totalLoanAmount = $monthlyPayment * $loan['term_months'];
$remainingBalance = $totalLoanAmount - $totalPaid;
$paymentPercentage = ($totalPaid / $totalLoanAmount) * 100;

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors[] = "Valid payment amount is required";
    } else if ($_POST['amount'] > $remainingBalance) {
        $errors[] = "Payment amount cannot exceed the remaining balance of $" . number_format($remainingBalance, 2);
    }
    
    if (empty($_POST['payment_date'])) {
        $errors[] = "Payment date is required";
    }
    
    if (empty($_POST['payment_method']) || !in_array($_POST['payment_method'], $loanController->getPaymentMethods())) {
        $errors[] = "Valid payment method is required";
    }
    
    // If no errors, process the repayment
    if (empty($errors)) {
        $repaymentData = [
            'loan_id' => $loan_id,
            'amount' => $_POST['amount'],
            'payment_date' => $_POST['payment_date'],
            'payment_method' => $_POST['payment_method'],
            'receipt_number' => $_POST['receipt_number'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        $result = $loanController->addRepayment($repaymentData);
        
        if ($result) {
            // Check if this payment completes the loan
            $newTotalPaid = $totalPaid + $_POST['amount'];
            if ($newTotalPaid >= $totalLoanAmount || $remainingBalance - $_POST['amount'] <= 0.01) {
                // Mark loan as paid
                $loanController->markLoanAsPaid($loan_id);
                $_SESSION['flash_message'] = "Repayment recorded successfully. The loan has been marked as fully paid!";
            } else {
                $_SESSION['flash_message'] = "Repayment recorded successfully";
            }
            
            $_SESSION['flash_message_class'] = "alert-success";
            header('Location: view_loan.php?id=' . $loan_id);
            exit();
        } else {
            $errors[] = "Failed to record repayment. Please try again.";
        }
    }
}

// Page title
$pageTitle = "Add Repayment for Loan #" . $loan_id;

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
                    <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Loan Details
                    </a>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="loans.php">Loans</a></li>
                    <li class="breadcrumb-item"><a href="view_loan.php?id=<?php echo $loan_id; ?>">View Loan #<?php echo $loan_id; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Repayment</li>
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
            
            <div class="row">
                <div class="col-md-8">
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
                                    <p><strong>Term:</strong> <?php echo $loan['term_months']; ?> months</p>
                                    <p><strong>Interest Rate:</strong> <?php echo $loan['interest_rate']; ?>%</p>
                                    <p><strong>Monthly Payment:</strong> $<?php echo number_format($monthlyPayment, 2); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> <span class="badge bg-<?php echo $loanController->getStatusBadgeClass($loan['status']); ?>"><?php echo ucfirst($loan['status']); ?></span></p>
                                    <p><strong>Disbursement Date:</strong> <?php echo date('F j, Y', strtotime($loan['disbursement_date'])); ?></p>
                                    <p><strong>Total Loan Amount:</strong> $<?php echo number_format($totalLoanAmount, 2); ?></p>
                                    <p><strong>Total Paid:</strong> $<?php echo number_format($totalPaid, 2); ?></p>
                                    <p><strong>Remaining Balance:</strong> $<?php echo number_format($remainingBalance, 2); ?></p>
                                </div>
                            </div>
                            
                            <!-- Repayment Progress Bar -->
                            <div class="mt-3">
                                <h6>Repayment Progress</h6>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo min(100, $paymentPercentage); ?>%;" 
                                         aria-valuenow="<?php echo $paymentPercentage; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo round($paymentPercentage, 1); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
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
                                    <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                        View Full Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Add Repayment Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Repayment</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" id="repaymentForm" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" 
                                               max="<?php echo $remainingBalance; ?>" 
                                               value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : $monthlyPayment; ?>" required>
                                        <div class="invalid-feedback">Please provide a valid payment amount</div>
                                    </div>
                                    <small class="text-muted">Regular monthly payment: $<?php echo number_format($monthlyPayment, 2); ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">Please provide a payment date</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <?php foreach ($loanController->getPaymentMethods() as $method): ?>
                                            <option value="<?php echo $method; ?>" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === $method) ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($method); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a payment method</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="receipt_number" class="form-label">Receipt Number</label>
                                    <input type="text" class="form-control" id="receipt_number" name="receipt_number" 
                                           value="<?php echo isset($_POST['receipt_number']) ? $_POST['receipt_number'] : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Record Payment</button>
                                    <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Payment Summary Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Payment Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Total Loan Amount:</strong> $<?php echo number_format($totalLoanAmount, 2); ?></p>
                            <p><strong>Already Paid:</strong> $<?php echo number_format($totalPaid, 2); ?></p>
                            <p><strong>Remaining Balance:</strong> $<?php echo number_format($remainingBalance, 2); ?></p>
                            <p><strong>Current Payment:</strong> $<span id="current-payment"><?php echo number_format(isset($_POST['amount']) ? $_POST['amount'] : $monthlyPayment, 2); ?></span></p>
                            <hr>
                            <p><strong>New Balance After Payment:</strong> $<span id="new-balance"><?php echo number_format($remainingBalance - (isset($_POST['amount']) ? $_POST['amount'] : $monthlyPayment), 2); ?></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('repaymentForm');
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
        
        // Update payment summary when amount changes
        const amountInput = document.getElementById('amount');
        const currentPaymentSpan = document.getElementById('current-payment');
        const newBalanceSpan = document.getElementById('new-balance');
        const remainingBalance = <?php echo $remainingBalance; ?>;
        
        amountInput.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            currentPaymentSpan.textContent = amount.toFixed(2);
            
            const newBalance = Math.max(0, remainingBalance - amount);
            newBalanceSpan.textContent = newBalance.toFixed(2);
            
            // Validate against remaining balance
            if (amount > remainingBalance) {
                this.setCustomValidity('Payment amount cannot exceed the remaining balance');
            } else {
                this.setCustomValidity('');
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
