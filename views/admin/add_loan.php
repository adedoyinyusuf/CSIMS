<?php
/**
 * Admin - Add Loan Application
 * 
 * This page provides a form for adding new loan applications.
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

// Get all active members for dropdown
$members = $memberController->getAllActiveMembers();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    if (empty($_POST['member_id'])) {
        $errors[] = "Member is required";
    }
    
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors[] = "Valid loan amount is required";
    }
    
    if (empty($_POST['purpose'])) {
        $errors[] = "Loan purpose is required";
    }
    
    if (empty($_POST['term_months']) || !is_numeric($_POST['term_months']) || $_POST['term_months'] <= 0) {
        $errors[] = "Valid loan term is required";
    }
    
    if (!isset($_POST['interest_rate']) || !is_numeric($_POST['interest_rate']) || $_POST['interest_rate'] < 0) {
        $errors[] = "Valid interest rate is required";
    }
    
    if (empty($_POST['application_date'])) {
        $errors[] = "Application date is required";
    }
    
    // If no errors, process the loan application
    if (empty($errors)) {
        $loanData = [
            'member_id' => $_POST['member_id'],
            'amount' => $_POST['amount'],
            'purpose' => $_POST['purpose'],
            'term_months' => $_POST['term_months'],
            'interest_rate' => $_POST['interest_rate'],
            'application_date' => $_POST['application_date'],
            'status' => 'pending',
            'collateral' => $_POST['collateral'] ?? '',
            'guarantor' => $_POST['guarantor'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        $result = $loanController->addLoanApplication($loanData);
        
        if ($result) {
            // Set success message and redirect
            $_SESSION['flash_message'] = "Loan application added successfully";
            $_SESSION['flash_message_class'] = "alert-success";
            header('Location: loans.php');
            exit();
        } else {
            $errors[] = "Failed to add loan application. Please try again.";
        }
    }
}

// Page title
$pageTitle = "Add Loan Application";

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
                    <li class="breadcrumb-item active" aria-current="page">Add Loan Application</li>
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
            
            <!-- Loan Application Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="POST" id="loanForm" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="member_id" class="form-label">Member <span class="text-danger">*</span></label>
                                <select class="form-select" id="member_id" name="member_id" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['member_id']; ?>" <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['member_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a member</div>
                            </div>
                            <div class="col-md-6">
                                <label for="application_date" class="form-label">Application Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="application_date" name="application_date" 
                                       value="<?php echo isset($_POST['application_date']) ? $_POST['application_date'] : date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Please provide an application date</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="amount" class="form-label">Loan Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" 
                                           value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" required>
                                    <div class="invalid-feedback">Please provide a valid loan amount</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="term_months" class="form-label">Loan Term (Months) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="term_months" name="term_months" min="1" max="120" 
                                       value="<?php echo isset($_POST['term_months']) ? $_POST['term_months'] : '12'; ?>" required>
                                <div class="invalid-feedback">Please provide a valid loan term</div>
                            </div>
                            <div class="col-md-4">
                                <label for="interest_rate" class="form-label">Interest Rate (% per annum) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="interest_rate" name="interest_rate" step="0.01" min="0" 
                                           value="<?php echo isset($_POST['interest_rate']) ? $_POST['interest_rate'] : '10'; ?>" required>
                                    <span class="input-group-text">%</span>
                                    <div class="invalid-feedback">Please provide a valid interest rate</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="purpose" class="form-label">Loan Purpose <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="2" required><?php echo isset($_POST['purpose']) ? $_POST['purpose'] : ''; ?></textarea>
                                <div class="invalid-feedback">Please provide the purpose of the loan</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="collateral" class="form-label">Collateral (if any)</label>
                                <textarea class="form-control" id="collateral" name="collateral" rows="2"><?php echo isset($_POST['collateral']) ? $_POST['collateral'] : ''; ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="guarantor" class="form-label">Guarantor (if any)</label>
                                <textarea class="form-control" id="guarantor" name="guarantor" rows="2"><?php echo isset($_POST['guarantor']) ? $_POST['guarantor'] : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title">Payment Preview</h5>
                                        <p class="card-text">Monthly Payment: <strong id="monthly-payment">$0.00</strong></p>
                                        <p class="card-text">Total Repayment: <strong id="total-repayment">$0.00</strong></p>
                                        <p class="card-text">Total Interest: <strong id="total-interest">$0.00</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Submit Loan Application</button>
                                <a href="<?php echo BASE_URL; ?>/admin/loans.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // Calculate loan payment details
    function calculateLoanDetails() {
        const principal = parseFloat(document.getElementById('amount').value) || 0;
        const termMonths = parseInt(document.getElementById('term_months').value) || 0;
        const annualInterestRate = parseFloat(document.getElementById('interest_rate').value) || 0;
        
        // Convert annual interest rate to monthly decimal rate
        const monthlyInterestRate = (annualInterestRate / 100) / 12;
        
        let monthlyPayment = 0;
        
        if (principal > 0 && termMonths > 0) {
            // If interest rate is 0, simple division
            if (monthlyInterestRate === 0) {
                monthlyPayment = principal / termMonths;
            } else {
                // Calculate monthly payment using amortization formula
                monthlyPayment = principal * monthlyInterestRate * 
                                Math.pow(1 + monthlyInterestRate, termMonths) / 
                                (Math.pow(1 + monthlyInterestRate, termMonths) - 1);
            }
        }
        
        const totalRepayment = monthlyPayment * termMonths;
        const totalInterest = totalRepayment - principal;
        
        document.getElementById('monthly-payment').textContent = '$' + monthlyPayment.toFixed(2);
        document.getElementById('total-repayment').textContent = '$' + totalRepayment.toFixed(2);
        document.getElementById('total-interest').textContent = '$' + totalInterest.toFixed(2);
    }
    
    // Set up event listeners for form validation and calculation
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('loanForm');
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
        
        // Calculate loan details when inputs change
        document.getElementById('amount').addEventListener('input', calculateLoanDetails);
        document.getElementById('term_months').addEventListener('input', calculateLoanDetails);
        document.getElementById('interest_rate').addEventListener('input', calculateLoanDetails);
        
        // Initial calculation
        calculateLoanDetails();
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
