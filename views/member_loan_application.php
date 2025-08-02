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

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $term_months = trim($_POST['term_months'] ?? '');
    $collateral = trim($_POST['collateral'] ?? '');
    $guarantor = trim($_POST['guarantor'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = 'Please enter a valid loan amount.';
    }
    
    if (empty($purpose)) {
        $errors[] = 'Please specify the purpose of the loan.';
    }
    
    if (empty($term_months) || !is_numeric($term_months) || $term_months <= 0 || $term_months > 60) {
        $errors[] = 'Please enter a valid loan term (1-60 months).';
    }
    
    if (empty($collateral)) {
        $errors[] = 'Please specify collateral for the loan.';
    }
    
    if (empty($guarantor)) {
        $errors[] = 'Please provide guarantor information.';
    }
    
    if (empty($errors)) {
        // Calculate interest rate and monthly payment (simplified calculation)
        $interest_rate = 5.0; // Default 5% annual interest rate
        $monthly_interest_rate = $interest_rate / 100 / 12;
        $monthly_payment = ($amount * $monthly_interest_rate * pow(1 + $monthly_interest_rate, $term_months)) / 
                          (pow(1 + $monthly_interest_rate, $term_months) - 1);
        
        $loan_data = [
            'member_id' => $member_id,
            'amount' => $amount,
            'purpose' => $purpose,
            'term_months' => $term_months,
            'interest_rate' => $interest_rate,
            'monthly_payment' => $monthly_payment,
            'application_date' => date('Y-m-d'),
            'status' => 'Pending',
            'collateral' => $collateral,
            'guarantor' => $guarantor,
            'notes' => $notes
        ];
        
        $loan_id = $loanController->addLoan($loan_data);
        
        if ($loan_id) {
            $success = true;
            // Clear form data
            $amount = $purpose = $term_months = $collateral = $guarantor = $notes = '';
        } else {
            $errors[] = 'Failed to submit loan application. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application - NPC CTLStaff Loan Society</title>
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
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .loan-calculator {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1.5rem;
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
                        <a class="nav-link" href="member_contributions.php">
                            <i class="fas fa-piggy-bank me-2"></i> My Contributions
                        </a>
                        <a class="nav-link" href="member_notifications.php">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                        <a class="nav-link active" href="member_loan_application.php">
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
                        <h2><i class="fas fa-plus-circle me-2"></i> Apply for Loan</h2>
                        <a href="member_loans.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to My Loans
                        </a>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i> Please correct the following errors:</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i> Application Submitted Successfully!</h6>
                            <p class="mb-0">Your loan application has been submitted and is pending review. You will be notified once it's processed.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Loan Application Form</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="amount" class="form-label">Loan Amount (₦) <span class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" id="amount" name="amount" 
                                                           value="<?php echo htmlspecialchars($amount ?? ''); ?>" 
                                                           min="1000" max="5000000" step="100" required>
                                                    <div class="form-text">Minimum: ₦1,000 | Maximum: ₦5,000,000</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="term_months" class="form-label">Loan Term (Months) <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="term_months" name="term_months" required>
                                                        <option value="">Select loan term</option>
                                                        <?php for ($i = 6; $i <= 60; $i += 6): ?>
                                                            <option value="<?php echo $i; ?>" <?php echo (isset($term_months) && $term_months == $i) ? 'selected' : ''; ?>>
                                                                <?php echo $i; ?> months (<?php echo number_format($i/12, 1); ?> years)
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="purpose" class="form-label">Purpose of Loan <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required 
                                                      placeholder="Please describe the purpose of this loan..."><?php echo htmlspecialchars($purpose ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="collateral" class="form-label">Collateral <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="collateral" name="collateral" rows="3" required 
                                                      placeholder="Please describe the collateral you're offering..."><?php echo htmlspecialchars($collateral ?? ''); ?></textarea>
                                            <div class="form-text">Describe any assets or property you're offering as security for this loan.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="guarantor" class="form-label">Guarantor Information <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="guarantor" name="guarantor" rows="3" required 
                                                      placeholder="Please provide guarantor details (Name, Phone, Relationship)..."><?php echo htmlspecialchars($guarantor ?? ''); ?></textarea>
                                            <div class="form-text">Provide the name, contact information, and relationship of your guarantor.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Additional Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                                      placeholder="Any additional information..."><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="reset" class="btn btn-outline-secondary me-md-2">
                                                <i class="fas fa-undo me-2"></i> Reset Form
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i> Submit Application
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Loan Calculator -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Loan Calculator</h6>
                                </div>
                                <div class="card-body loan-calculator">
                                    <div class="mb-3">
                                        <label class="form-label">Amount: ₦<span id="calc-amount">0</span></label>
                                        <div class="text-muted small">Term: <span id="calc-term">0</span> months</div>
                                    </div>
                                    <hr>
                                    <div class="mb-2">
                                        <strong>Interest Rate: 5.0% per annum</strong>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Monthly Payment: ₦<span id="calc-monthly">0</span></strong>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Total Payment: ₦<span id="calc-total">0</span></strong>
                                    </div>
                                    <div class="text-muted small">
                                        Total Interest: ₦<span id="calc-interest">0</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Loan Requirements -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Loan Requirements</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Active membership status
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Valid collateral
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Reliable guarantor
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Clear loan purpose
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            No outstanding defaults
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function calculateLoan() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const termMonths = parseInt(document.getElementById('term_months').value) || 0;
            const annualRate = 5.0; // 5% annual interest rate
            
            if (amount > 0 && termMonths > 0) {
                const monthlyRate = annualRate / 100 / 12;
                const monthlyPayment = (amount * monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / 
                                     (Math.pow(1 + monthlyRate, termMonths) - 1);
                const totalPayment = monthlyPayment * termMonths;
                const totalInterest = totalPayment - amount;
                
                document.getElementById('calc-amount').textContent = amount.toLocaleString();
                document.getElementById('calc-term').textContent = termMonths;
                document.getElementById('calc-monthly').textContent = monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('calc-total').textContent = totalPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('calc-interest').textContent = totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                document.getElementById('calc-amount').textContent = '0';
                document.getElementById('calc-term').textContent = '0';
                document.getElementById('calc-monthly').textContent = '0';
                document.getElementById('calc-total').textContent = '0';
                document.getElementById('calc-interest').textContent = '0';
            }
        }
        
        // Update calculator when amount or term changes
        document.getElementById('amount').addEventListener('input', calculateLoan);
        document.getElementById('term_months').addEventListener('change', calculateLoan);
        
        // Initial calculation
        calculateLoan();
    </script>
</body>
</html>