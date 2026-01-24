<?php
require_once '../config/config.php';
// Remove redundant/incorrect path if needed, strictly using what works in other files
require_once '../includes/config/database.php';
require_once '../includes/config/SystemConfigService.php';
require_once '../includes/services/BusinessRulesService.php';
require_once '../controllers/member_controller.php';
require_once '../controllers/loan_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

// Initialize services
try {
    $database = new PdoDatabase();
    $pdo = $database->getConnection();
    // Re-connect for MySQLi repo usage if needed, or rely on internal connections
    // But LoanController likely uses its own DB connection or global $conn
    $config = SystemConfigService::getInstance($pdo);
    $businessRules = new BusinessRulesService($pdo);
    $memberController = new MemberController();
    $loanController = new LoanController();
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
    'default_interest_rate' => (float)$config->get('DEFAULT_INTEREST_RATE', 12.0),
];

// Calculate dynamic loan limit based on savings
$memberSavings = (float)($member['savings_balance'] ?? 0);
$savingsMultiplier = (float)$loanConfig['loan_to_savings_multiplier'];
$maxLoanBySavings = $memberSavings * $savingsMultiplier;
$globalMaxLoan = (float)$loanConfig['max_loan_amount'];

// Effective limit is the lesser of the two (unless savings are 0, then maybe a base limit applies? usually 0)
// If savings are 0, max loan is 0.
$effectiveMaxLoan = min($maxLoanBySavings, $globalMaxLoan);

// Pass to JS
$jsConfig = [
    'effectiveMaxLoan' => $effectiveMaxLoan,
    'savingsBalance' => $memberSavings,
    'multiplier' => $savingsMultiplier
];

// Fetch active loan types
$loanTypes = $loanController->getLoanTypes();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX Eligibility Check Handler
    if (isset($_POST['action']) && $_POST['action'] === 'check_eligibility') {
        header('Content-Type: application/json');
        ob_clean(); // Clean any previous output
        
        $loanTypeId = (int)($_POST['loan_type_id'] ?? 1);
        $requestedAmount = (float)($_POST['amount'] ?? 0);
        
        // Manual check for savings limit first
        $eligibilityErrors = [];
        if ($requestedAmount > $effectiveMaxLoan) {
            $eligibilityErrors[] = "Amount exceeds your eligibility limit of ₦" . number_format($effectiveMaxLoan, 2) . " (3x Savings).";
        }
        
        $businessRulesErrors = $businessRules->validateLoanEligibility($member_id, $requestedAmount, $loanTypeId);
        $eligibilityErrors = array_merge($eligibilityErrors, $businessRulesErrors);
        
        if (empty($eligibilityErrors)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'errors' => $eligibilityErrors]);
        }
        exit;
    }

    $loanTypeId = (int)($_POST['loan_type_id'] ?? 1);
    $requestedAmount = (float)($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $termMonths = (int)($_POST['term_months'] ?? 0);
    $agreedTerms = ($_POST['agree_terms'] ?? '') === '1';

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

    // Require explicit agreement to interest rate and terms
    if (!$agreedTerms) {
        $errors[] = 'Please agree to the interest rate and terms before submitting.';
    }

    // Run business rules validation
    if (empty($errors)) {
        $eligibilityErrors = $businessRules->validateLoanEligibility($member_id, $requestedAmount, $loanTypeId);
        if (!empty($eligibilityErrors)) {
            $errors = array_merge($errors, $eligibilityErrors);
        }
    }

    // If validation passes, SAVE the loan
    if (empty($errors)) {
        // Calculate details
        $interest_rate = $loanController->getInterestRate($requestedAmount, $termMonths);
        // Fallback if 0 returned
        if ($interest_rate <= 0) $interest_rate = $loanConfig['default_interest_rate'];
        
        $monthly_interest_rate = $interest_rate / 100 / 12;
        $monthlyPayment = ($requestedAmount * $monthly_interest_rate * pow(1 + $monthly_interest_rate, $termMonths)) / (pow(1 + $monthly_interest_rate, $termMonths) - 1);

        $loan_data = [
            'member_id' => $member_id,
            'amount' => $requestedAmount,
            'purpose' => $purpose,
            'term_months' => $termMonths,
            'interest_rate' => $interest_rate,
            'monthly_payment' => $monthlyPayment,
            'application_date' => date('Y-m-d'),
            'status' => 'Pending',
            'loan_type_id' => $loanTypeId,
            'collateral' => '', 
            'guarantor' => '',
            'remarks' => $purpose
        ];

        $loan_id = $loanController->addLoanApplication($loan_data);

        if ($loan_id) {
            $success = true;
            // Clear form data so fields are empty on re-render
            $_POST = [];
            // Optional: Redirect to self with success query param to avoid resubmission
            // header('Location: ' . $_SERVER['PHP_SELF'] . '?status=success'); 
            // exit;
            // For now, we fall through to render the success message block
        } else {
            $errors[] = 'System error: Failed to save loan application. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Loan - CSIMS</title>
    <!-- Bootstrap CSS for Member Header -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Premium Design System -->
    <link rel="stylesheet" href="../assets/css/premium-design-system.css?v=2.4">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
                        emerald: { 50: '#ecfdf5', 100: '#d1fae5', 500: '#10b981', 600: '#059669', 700: '#047857' },
                        slate: { 850: '#1e293b' } // Custom dark slate
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .gradient-header { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <?php include __DIR__ . '/includes/member_header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Loan Application</h1>
                <p class="text-slate-500 mt-1">Submit your application with instant eligibility checking.</p>
            </div>
            <a href="member_loans.php" class="inline-flex items-center justify-center px-5 py-2.5 border border-slate-300 shadow-sm text-sm font-medium rounded-xl text-slate-700 bg-white hover:bg-slate-50 transition-all">
                <i class="fas fa-arrow-left mr-2"></i> Back to Loans
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Form Column -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Alerts -->
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Application Error</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-emerald-800">
                                    Application Submitted Successfully! Redirecting...
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Application Form Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center">
                        <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center mr-3">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <h2 class="text-lg font-bold text-slate-800">Application Details</h2>
                    </div>
                    
                    <div class="p-6 md:p-8">
                        <form method="POST" id="loanApplicationForm" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="loan_type_id" class="block text-sm font-medium text-slate-700 mb-2">Loan Type</label>
                                    <div class="relative">
                                        <select id="loan_type_id" name="loan_type_id" required 
                                                class="block w-full pl-3 pr-10 py-3 text-base border-slate-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl shadow-sm">
                                            <option value="">Select Loan Type</option>
                                            <?php foreach ($loanTypes as $type): ?>
                                                <option value="<?php echo $type['id']; ?>" 
                                                        data-rate="<?php echo $type['interest_rate']; ?>"
                                                        <?php echo (isset($_POST['loan_type_id']) && $_POST['loan_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['interest_rate']; ?>%)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-slate-700 mb-2">Amount Required</label>
                                    <div class="relative rounded-xl shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-slate-500 sm:text-sm">₦</span>
                                        </div>
                                        <input type="number" name="amount" id="amount" 
                                               min="1000" max="<?php echo $effectiveMaxLoan; ?>" step="1000" required
                                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                               class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-8 pr-12 sm:text-sm border-slate-300 rounded-xl py-3" 
                                               placeholder="0.00">
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Max allowed: <span class="font-semibold <?php echo $effectiveMaxLoan > 0 ? 'text-primary-600' : 'text-red-500'; ?>">₦<?php echo number_format($effectiveMaxLoan); ?></span>
                                        (<?php echo $loanConfig['loan_to_savings_multiplier']; ?>x Savings: ₦<?php echo number_format($memberSavings); ?>)
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="term_months" class="block text-sm font-medium text-slate-700 mb-2">Duration</label>
                                    <select id="term_months" name="term_months" required 
                                            class="block w-full pl-3 pr-10 py-3 text-base border-slate-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl shadow-sm">
                                        <option value="">Select Term</option>
                                        <?php foreach ([6, 12, 18, 24, 36, 48, 60] as $month): ?>
                                            <option value="<?php echo $month; ?>" <?php echo (isset($_POST['term_months']) && $_POST['term_months'] == $month) ? 'selected' : ''; ?>>
                                                <?php echo $month; ?> Months
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Est. Monthly Repayment</label>
                                    <div class="relative rounded-xl shadow-sm bg-slate-50">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-slate-500 sm:text-sm">₦</span>
                                        </div>
                                        <input type="text" id="monthly_payment_display" readonly 
                                               class="focus:ring-0 focus:border-slate-300 block w-full pl-8 sm:text-sm border-slate-300 rounded-xl py-3 bg-slate-50 text-slate-600 font-semibold" 
                                               placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="purpose" class="block text-sm font-medium text-slate-700 mb-2">Loan Purpose</label>
                                <textarea id="purpose" name="purpose" rows="3" required placeholder="Describe what this loan is for..."
                                          class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-slate-300 rounded-xl px-4 py-3"><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                            </div>

                            <div class="bg-primary-50 rounded-xl p-4 border border-primary-100">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="agree_terms" name="agree_terms" type="checkbox" value="1" <?php echo isset($_POST['agree_terms']) && $_POST['agree_terms'] === '1' ? 'checked' : ''; ?>
                                               class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="agree_terms" class="font-medium text-primary-700">I agree to the Terms & Conditions</label>
                                        <p class="text-primary-600 opacity-90">I accept the interest rate, processing fee (1%), and default penalty clauses.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-4 pt-4">
                                <button type="button" onclick="checkEligibility()" 
                                        class="flex-1 bg-white border border-primary-200 text-primary-700 hover:bg-primary-50 font-semibold py-3 px-6 rounded-xl shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <i class="fas fa-search mr-2"></i> Check Eligibility
                                </button>
                                <button type="submit" id="submitBtn" disabled
                                        class="flex-1 gradient-header text-white hover:opacity-90 font-bold py-3 px-6 rounded-xl shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Eligibility Card -->
                <div class="rounded-2xl shadow-lg gradient-header p-6 text-white relative overflow-hidden">
                    <div class="relative z-10">
                        <h3 class="font-bold text-lg mb-4 flex items-center">
                            <i class="fas fa-clipboard-check mr-2"></i> Requirements
                        </h3>
                        <ul class="space-y-3 text-sm">
                            <li class="flex items-center bg-white/10 rounded-lg p-2 backdrop-blur-sm">
                                <i class="fas fa-calendar-check mr-3 opacity-80"></i>
                                <span>Min. Membership: <strong><?php echo $loanConfig['min_membership_months']; ?> Months</strong></span>
                            </li>
                            <li class="flex items-center bg-white/10 rounded-lg p-2 backdrop-blur-sm">
                                <i class="fas fa-piggy-bank mr-3 opacity-80"></i>
                                <span>Min. Savings: <strong>₦<?php echo number_format($loanConfig['min_mandatory_savings']); ?></strong></span>
                            </li>
                            <li class="flex items-center bg-white/10 rounded-lg p-2 backdrop-blur-sm">
                                <i class="fas fa-building mr-3 opacity-80"></i>
                                <span>Limit: <strong><?php echo $loanConfig['loan_to_savings_multiplier']; ?>x Savings</strong></span>
                            </li>
                        </ul>
                    </div>
                    <!-- Decorative Icon -->
                    <div class="absolute -bottom-4 -right-4 text-white opacity-10">
                        <i class="fas fa-shield-alt text-9xl"></i>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-slate-800 text-lg mb-4 flex items-center">
                        <i class="fas fa-cog text-slate-400 mr-2"></i> Loan Parameters
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Interest Rate</span>
                            <span class="text-sm font-semibold text-slate-700"><?php echo number_format($loanConfig['default_interest_rate'], 1); ?>% p.a.</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Processing Fee</span>
                            <span class="text-sm font-semibold text-slate-700">1.0%</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Grace Period</span>
                            <span class="text-sm font-semibold text-slate-700"><?php echo $loanConfig['grace_period']; ?> Days</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-sm text-slate-500">Penalty Rate</span>
                            <span class="text-sm font-semibold text-red-600"><?php echo $loanConfig['penalty_rate']; ?>% / mo</span>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Eligibility Result Placeholder -->
                <div id="eligibilityResults" class="hidden">
                    <!-- Check results will be injected here via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Logic -->
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
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(monthlyPayment);
            } else {
                document.getElementById('monthly_payment_display').value = '';
            }
        }

        function checkEligibility() {
            const memberId = <?php echo $member_id; ?>;
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const loanTypeId = parseInt(document.getElementById('loan_type_id').value) || 1;

            if (!amount || !loanTypeId) {
                // Tailwind alert
                const resultsDiv = document.getElementById('eligibilityResults');
                resultsDiv.classList.remove('hidden');
                resultsDiv.innerHTML = `
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl shadow-sm animate-pulse">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-amber-500"></i></div>
                            <div class="ml-3"><p class="text-sm text-amber-700">Please enter an amount and select a loan type first.</p></div>
                        </div>
                    </div>`;
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const resultsDiv = document.getElementById('eligibilityResults');
            resultsDiv.classList.remove('hidden');
            resultsDiv.innerHTML = `
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-xl shadow-sm">
                    <div class="flex items-center">
                        <div class="flex-shrink-0"><i class="fas fa-spinner fa-spin text-blue-500"></i></div>
                        <div class="ml-3"><p class="text-sm text-blue-700">Checking eligibility...</p></div>
                    </div>
                </div>`;

            // Prepare form data for AJAX
            const formData = new FormData();
            formData.append('action', 'check_eligibility');
            formData.append('amount', amount);
            formData.append('loan_type_id', loanTypeId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultsDiv.innerHTML = `
                        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0"><i class="fas fa-check-circle text-emerald-500"></i></div>
                                <div class="ml-3"><p class="text-sm font-medium text-emerald-800">You are eligible for this loan!</p></div>
                            </div>
                        </div>`;
                } else {
                    let errorList = data.errors.map(err => `<li>${err}</li>`).join('');
                    resultsDiv.innerHTML = `
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0"><i class="fas fa-times-circle text-red-500"></i></div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Eligibility Issues</h3>
                                    <ul class="list-disc pl-5 mt-1 text-sm text-red-700">${errorList}</ul>
                                </div>
                            </div>
                        </div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                         <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-red-500"></i></div>
                            <div class="ml-3"><p class="text-sm text-red-700">System error checking eligibility.</p></div>
                        </div>
                    </div>`;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            const termSelect = document.getElementById('term_months');
            const typeSelect = document.getElementById('loan_type_id');
            const agreeTerms = document.getElementById('agree_terms');
            const submitBtn = document.getElementById('submitBtn');

            [amountInput, termSelect, typeSelect].forEach(el => {
                el.addEventListener('input', calculateMonthlyPayment);
                el.addEventListener('change', calculateMonthlyPayment);
            });

            function updateSubmitState() {
                if (agreeTerms && submitBtn) {
                    submitBtn.disabled = !agreeTerms.checked;
                    if(!agreeTerms.checked) {
                        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }
            }
            if (agreeTerms) {
                agreeTerms.addEventListener('change', updateSubmitState);
                updateSubmitState();
            }
            
            calculateMonthlyPayment();
        });
    </script>
    <!-- Bootstrap Bundle for Header Compatibility -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>