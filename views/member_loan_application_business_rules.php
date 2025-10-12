<?php
session_start();
require_once '../config/database.php';
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

// Initialize services
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $config = SystemConfigService::getInstance($pdo);
    $businessRules = new BusinessRulesService($pdo);
    $memberController = new MemberController();
} catch (Exception $e) {
    die("System initialization failed: " . $e->getMessage());
}

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);
$errors = [];
$success = false;

// Get loan configuration for display
$loanConfig = [
    'min_mandatory_savings' => $config->getMinMandatorySavings(),
    'max_loan_amount' => $config->getMaxLoanAmount(),
    'loan_to_savings_multiplier' => $config->getLoanToSavingsMultiplier(),
    'min_membership_months' => $config->getMinMembershipMonths(),
    'max_active_loans' => $config->getMaxActiveLoansPer(),
    'penalty_rate' => $config->getLoanPenaltyRate(),
    'grace_period' => $config->getDefaultGracePeriod(),
    'guarantor_threshold' => $config->getGuarantorRequirementThreshold(),
    'min_guarantors' => $config->getMinGuarantorsRequired(),
    'auto_approval_limit' => $config->getAutoApprovalLimit(),
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loanTypeId = (int)($_POST['loan_type_id'] ?? 1);
    $requestedAmount = (float)($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $termMonths = (int)($_POST['term_months'] ?? 0);

    // Validate basic form data
    if ($requestedAmount <= 0) {
        $errors[] = 'Please enter a valid loan amount.';
    }
    if (empty($purpose)) {
        $errors[] = 'Please specify the purpose of the loan.';
    }
    if ($termMonths <= 0 || $termMonths > 60) {
        $errors[] = 'Please enter a valid loan term (1-60 months).';
    }

    // Run business rules validation
    if (empty($errors)) {
        $eligibilityErrors = $businessRules->validateLoanEligibility($member_id, $requestedAmount, $loanTypeId);
        if (!empty($eligibilityErrors)) {
            $errors = array_merge($errors, $eligibilityErrors);
        }
    }

    // If validation passes, redirect to enhanced controller
    if (empty($errors)) {
        $_SESSION['loan_application_data'] = [
            'member_id' => $member_id,
            'loan_type_id' => $loanTypeId,
            'amount' => $requestedAmount,
            'purpose' => $purpose,
            'term_months' => $termMonths
        ];
        header('Location: loan_application_confirmation.php');
        exit();
    }
}

// Get loan types
try {
    $stmt = $pdo->prepare("SELECT * FROM loan_types ORDER BY name");
    $stmt->execute();
    $loanTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $loanTypes = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Loan - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/csims-colors.css" rel="stylesheet">
    <style>
        body {
            background: var(--member-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px var(--shadow-md);
            border: 1px solid var(--border-light);
        }
        .eligibility-card {
            background: linear-gradient(135deg, var(--sky-blue) 0%, var(--vista-blue) 100%);
            color: white;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .config-info {
            background: var(--primary-50);
            border-left: 4px solid var(--primary-500);
            border-radius: 0 8px 8px 0;
            padding: 15px;
            margin: 10px 0;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--member-primary) 0%, var(--member-secondary) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-check-eligibility {
            background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);
            border: none;
            color: white;
        }
        .alert-danger {
            border-left: 4px solid #dc3545;
            border-radius: 0 8px 8px 0;
        }
        .alert-success {
            border-left: 4px solid #198754;
            border-radius: 0 8px 8px 0;
        }
        .input-group-text {
            background: var(--primary-100);
            border-color: var(--border-light);
        }
        .form-control:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="d-flex flex-column flex-shrink-0 p-3 text-white" style="background: linear-gradient(180deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); min-height: 100vh;">
                    <a href="member_dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <i class="fas fa-university me-2"></i>
                        <span class="fs-4">CSIMS</span>
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="member_dashboard.php" class="nav-link text-white">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_loan_application_business_rules.php" class="nav-link active">
                                <i class="fas fa-plus me-2"></i> Apply for Loan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_loans.php" class="nav-link text-white">
                                <i class="fas fa-money-bill-wave me-2"></i> My Loans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="member_savings.php" class="nav-link text-white">
                                <i class="fas fa-piggy-bank me-2"></i> My Savings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container py-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Apply for Loan</h2>
                            <p class="text-muted">Submit your loan application with automatic eligibility checking</p>
                        </div>
                        <a href="member_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>

                    <!-- Business Rules Info Card -->
                    <div class="eligibility-card">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-info-circle me-2"></i>Loan Eligibility Requirements
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="mb-0 list-unstyled">
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Minimum <?php echo $loanConfig['min_membership_months']; ?> months membership</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Monthly savings: ₦<?php echo number_format($loanConfig['min_mandatory_savings'], 2); ?>+</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Maximum <?php echo $loanConfig['max_active_loans']; ?> active loans</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="mb-0 list-unstyled">
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Max loan: <?php echo $loanConfig['loan_to_savings_multiplier']; ?>x your savings</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Loans ≥₦<?php echo number_format($loanConfig['guarantor_threshold'], 2); ?> need <?php echo $loanConfig['min_guarantors']; ?> guarantors</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Auto-approval: ≤₦<?php echo number_format($loanConfig['auto_approval_limit'], 2); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error/Success Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Eligibility Check Failed</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>Loan application submitted successfully!
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Loan Application Form -->
                        <div class="col-lg-8">
                            <div class="form-card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-alt me-2"></i>Loan Application Details
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <form method="POST" id="loanApplicationForm">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="loan_type_id" class="form-label">Loan Type</label>
                                                <select class="form-select" id="loan_type_id" name="loan_type_id" required>
                                                    <option value="">Select Loan Type</option>
                                                    <?php foreach ($loanTypes as $type): ?>
                                                        <option value="<?php echo $type['id']; ?>" 
                                                                data-rate="<?php echo $type['interest_rate']; ?>"
                                                                <?php echo (isset($_POST['loan_type_id']) && $_POST['loan_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['interest_rate']; ?>% annual)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="amount" class="form-label">Loan Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₦</span>
                                                    <input type="number" class="form-control" id="amount" name="amount" 
                                                           min="1000" max="<?php echo $loanConfig['max_loan_amount']; ?>"
                                                           step="1000" required
                                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                                                </div>
                                                <small class="text-muted">Maximum: ₦<?php echo number_format($loanConfig['max_loan_amount'], 2); ?></small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="term_months" class="form-label">Loan Term</label>
                                                <select class="form-select" id="term_months" name="term_months" required>
                                                    <option value="">Select Term</option>
                                                    <option value="6" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '6') ? 'selected' : ''; ?>>6 months</option>
                                                    <option value="12" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '12') ? 'selected' : ''; ?>>12 months</option>
                                                    <option value="18" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '18') ? 'selected' : ''; ?>>18 months</option>
                                                    <option value="24" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '24') ? 'selected' : ''; ?>>24 months</option>
                                                    <option value="36" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '36') ? 'selected' : ''; ?>>36 months</option>
                                                    <option value="48" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '48') ? 'selected' : ''; ?>>48 months</option>
                                                    <option value="60" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == '60') ? 'selected' : ''; ?>>60 months</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Monthly Payment Estimate</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₦</span>
                                                    <input type="text" class="form-control" id="monthly_payment_display" readonly>
                                                </div>
                                                <small class="text-muted">Calculated automatically</small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="purpose" class="form-label">Purpose of Loan</label>
                                            <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                                                      placeholder="Please describe the purpose of your loan..." required><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="d-flex gap-3">
                                            <button type="button" class="btn btn-check-eligibility" onclick="checkEligibility()">
                                                <i class="fas fa-search me-2"></i>Check Eligibility
                                            </button>
                                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                                <i class="fas fa-paper-plane me-2"></i>Submit Application
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Eligibility Check Results -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clipboard-check me-2"></i>Eligibility Check
                                    </h6>
                                </div>
                                <div class="card-body" id="eligibilityResults">
                                    <p class="text-muted text-center py-3">
                                        <i class="fas fa-info-circle fa-2x d-block mb-2"></i>
                                        Fill out the form and click "Check Eligibility" to see if you qualify for this loan.
                                    </p>
                                </div>
                            </div>

                            <!-- Loan Configuration Info -->
                            <div class="config-info mt-3">
                                <h6><i class="fas fa-cog me-2"></i>System Settings</h6>
                                <small class="d-block"><strong>Penalty Rate:</strong> <?php echo $loanConfig['penalty_rate']; ?>% per month</small>
                                <small class="d-block"><strong>Grace Period:</strong> <?php echo $loanConfig['grace_period']; ?> days</small>
                                <small class="d-block"><strong>Processing Fee:</strong> 1% of loan amount</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function calculateMonthlyPayment() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const termMonths = parseInt(document.getElementById('term_months').value) || 0;
            const loanTypeSelect = document.getElementById('loan_type_id');
            const selectedOption = loanTypeSelect.options[loanTypeSelect.selectedIndex];
            const annualRate = parseFloat(selectedOption.getAttribute('data-rate')) || 0;

            if (amount > 0 && termMonths > 0 && annualRate > 0) {
                const monthlyRate = annualRate / 100 / 12;
                const monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / (Math.pow(1 + monthlyRate, termMonths) - 1);
                
                document.getElementById('monthly_payment_display').value = new Intl.NumberFormat('en-NG', {
                    style: 'currency',
                    currency: 'NGN',
                    minimumFractionDigits: 2
                }).format(monthlyPayment).replace('NGN', '');
            } else {
                document.getElementById('monthly_payment_display').value = '';
            }
        }

        function checkEligibility() {
            const memberId = <?php echo $member_id; ?>;
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const loanTypeId = parseInt(document.getElementById('loan_type_id').value) || 1;

            if (!amount || !loanTypeId) {
                alert('Please fill in the loan amount and type first.');
                return;
            }

            // Show loading
            const resultsDiv = document.getElementById('eligibilityResults');
            resultsDiv.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-2 mb-0">Checking eligibility...</p>
                </div>
            `;

            // Make AJAX request to enhanced controller
            fetch('/controllers/enhanced_loan_controller.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(response => response.text())
            .then(data => {
                // For now, make a direct request to check eligibility
                window.location.href = `check_loan_eligibility.php?member_id=${memberId}&amount=${amount}&loan_type_id=${loanTypeId}`;
            })
            .catch(error => {
                resultsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error checking eligibility. Please try again.
                    </div>
                `;
                console.error('Error:', error);
            });
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('amount').addEventListener('input', calculateMonthlyPayment);
            document.getElementById('term_months').addEventListener('change', calculateMonthlyPayment);
            document.getElementById('loan_type_id').addEventListener('change', calculateMonthlyPayment);
            
            // Initial calculation
            calculateMonthlyPayment();
        });
    </script>
</body>
</html>