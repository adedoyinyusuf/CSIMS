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

require_once __DIR__ . '/../controllers/loan_controller.php';
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
    $notes = trim($_POST['remarks'] ?? ''); // Use remarks instead of notes
    $savings = trim($_POST['savings'] ?? '');
    $month_deduction_started = trim($_POST['month_deduction_started'] ?? '');
    $month_deduction_end = trim($_POST['month_deduction_end'] ?? '');
    $other_payment_plans = trim($_POST['other_payment_plans'] ?? '');
    
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
    
    // Validate savings amount
    if (!empty($savings) && (!is_numeric($savings) || $savings < 0)) {
        $errors[] = 'Please enter a valid savings amount.';
    }
    
    if (empty($errors)) {
        // Calculate interest rate and monthly payment using dynamic logic
        $interest_rate = $loanController->getInterestRate((float)$amount, (int)$term_months);
        $monthly_interest_rate = $interest_rate / 100 / 12;
        $monthly_payment = ($amount * $monthly_interest_rate * pow(1 + $monthly_interest_rate, $term_months)) /
                          (pow(1 + $monthly_interest_rate, $term_months) - 1);
        
        // Prepare loan data with all fields separated
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
            'notes' => $notes, // Use notes field for admin notes/processing notes
            'savings' => $savings,
            'month_deduction_started' => $month_deduction_started,
            'month_deduction_end' => $month_deduction_end,
            'other_payment_plans' => $other_payment_plans,
            'remarks' => $notes // Member's remarks/comments
        ];
        $loan_id = $loanController->addLoanApplication($loan_data);
        
        if ($loan_id) {
            $success = true;
            // Clear form data
            $amount = $purpose = $term_months = $collateral = $guarantor = $notes = $savings = $month_deduction_started = $month_deduction_end = $other_payment_plans = '';
        } else {
            $errors[] = 'Failed to submit loan application. Please check your information and try again.';
            error_log("Loan application failed for member {$member_id}. Data: " . print_r($loan_data, true));
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-br from-primary-600 to-purple-700 shadow-xl">
            <div class="flex flex-col h-full p-6">
                <h4 class="text-white text-xl font-bold mb-6">
                    <i class="fas fa-university mr-2"></i> Member Portal
                </h4>
                
                <div class="mb-6">
                    <small class="text-primary-200">Welcome,</small>
                    <div class="text-white font-semibold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                </div>
                
                <nav class="flex-1 space-y-2">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_dashboard.php">
                        <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_profile.php">
                        <i class="fas fa-user mr-3"></i> My Profile
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_loans.php">
                        <i class="fas fa-money-bill-wave mr-3"></i> My Loans
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_contributions.php">
                        <i class="fas fa-piggy-bank mr-3"></i> My Contributions
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_notifications.php">
                        <i class="fas fa-bell mr-3"></i> Notifications
                    </a>
                    <a class="flex items-center px-4 py-3 text-white bg-white/20 rounded-lg font-medium" href="member_loan_application.php">
                        <i class="fas fa-plus-circle mr-3"></i> Apply for Loan
                    </a>
                </nav>
                
                <div class="mt-auto">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_logout.php">
                        <i class="fas fa-sign-out-alt mr-3"></i> Logout
                    </a>
                </div>
            </div>
        </div>
            
        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-plus-circle mr-3 text-primary-600"></i> Apply for Loan
                        </h1>
                        <p class="text-gray-600 mt-2">Submit your loan application for review</p>
                    </div>
                    <a href="member_loans.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i> Back to My Loans
                    </a>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Application Submitted Successfully!</h3>
                                <p class="mt-2 text-sm text-green-700">Your loan application has been submitted and is pending review. You will be notified once it's processed.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                    
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-primary-50 to-primary-100">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-file-alt mr-2 text-primary-600"></i>
                                    Loan Application Form
                                </h3>
                            </div>
                            <div class="p-6">
                                <form method="POST" action="" class="space-y-8">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                                Loan Amount (₦) <span class="text-red-500">*</span>
                                            </label>
                                            <input type="number" class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="amount" name="amount" value="<?php echo htmlspecialchars($amount ?? ''); ?>" min="1000" required>
                                        </div>
                                        <div>
                                            <label for="term_months" class="block text-sm font-medium text-gray-700 mb-2">
                                                Loan Term (Months) <span class="text-red-500">*</span>
                                            </label>
                                            <select class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="term_months" name="term_months" required>
                                                <option value="">Select loan term...</option>
                                                <?php for ($i = 1; $i <= 36; $i++) { ?>
                                                    <option value="<?php echo $i; ?>" <?php echo (isset($term_months) && $term_months == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="savings" class="block text-sm font-medium text-gray-700 mb-2">
                                                Savings <span class="text-red-500">*</span>
                                            </label>
                                            <input type="number" class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="savings" name="savings" value="<?php echo htmlspecialchars($savings ?? ''); ?>" min="0" required>
                                            <p class="mt-1 text-xs text-gray-500">Enter your current savings amount.</p>
                                        </div>
                                        <div>
                                            <label for="month_deduction_started" class="block text-sm font-medium text-gray-700 mb-2">
                                                Month Deduction Started <span class="text-red-500">*</span>
                                            </label>
                                            <input type="month" class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="month_deduction_started" name="month_deduction_started" value="<?php echo htmlspecialchars($month_deduction_started ?? ''); ?>" required>
                                            <p class="mt-1 text-xs text-gray-500">Select the month when deductions should start.</p>
                                        </div>
                                        <div>
                                            <label for="month_deduction_end" class="block text-sm font-medium text-gray-700 mb-2">
                                                Month Deduction Should End <span class="text-red-500">*</span>
                                            </label>
                                            <input type="month" class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="month_deduction_end" name="month_deduction_end" value="<?php echo htmlspecialchars($month_deduction_end ?? ''); ?>" required>
                                            <p class="mt-1 text-xs text-gray-500">Select the month when deductions should end.</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                                Purpose of Loan <span class="text-red-500">*</span>
                                            </label>
                                            <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="purpose" name="purpose" rows="3" required placeholder="Please describe the purpose of this loan..."><?php echo htmlspecialchars($purpose ?? ''); ?></textarea>
                                        </div>
                                        <div>
                                            <label for="collateral" class="block text-sm font-medium text-gray-700 mb-2">
                                                Collateral <span class="text-red-500">*</span>
                                            </label>
                                            <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="collateral" name="collateral" rows="3" required placeholder="Please describe the collateral you're offering..."><?php echo htmlspecialchars($collateral ?? ''); ?></textarea>
                                            <p class="mt-1 text-xs text-gray-500">Describe any assets or property you're offering as security for this loan.</p>
                                        </div>
                                        <div>
                                            <label for="guarantor" class="block text-sm font-medium text-gray-700 mb-2">
                                                Guarantor Information <span class="text-red-500">*</span>
                                            </label>
                                            <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="guarantor" name="guarantor" rows="3" required placeholder="Please provide guarantor details (Name, Phone, Relationship)..."><?php echo htmlspecialchars($guarantor ?? ''); ?></textarea>
                                            <p class="mt-1 text-xs text-gray-500">Provide the name, contact information, and relationship of your guarantor.</p>
                                        </div>
                                        <div>
                                            <label for="other_payment_plans" class="block text-sm font-medium text-gray-700 mb-2">
                                                Other Payment Plans</label>
                                            <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="other_payment_plans" name="other_payment_plans" rows="2" placeholder="Describe any other payment plans..."><?php echo htmlspecialchars($other_payment_plans ?? ''); ?></textarea>
                                            <p class="mt-1 text-xs text-gray-500">Optional: Provide details of any additional payment arrangements.</p>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="remarks" class="block text-sm font-medium text-gray-700 mb-2">
                                            Remarks</label>
                                        <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="remarks" name="remarks" rows="2" placeholder="Any additional remarks..."><?php echo htmlspecialchars($remarks ?? ''); ?></textarea>
                                        <p class="mt-1 text-xs text-gray-500">Optional: Add any comments or notes relevant to your application.</p>
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                                        <button type="reset" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                                            <i class="fas fa-undo mr-2"></i> Reset Form
                                        </button>
                                        <button type="submit" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                                            <i class="fas fa-paper-plane mr-2"></i> Submit Application
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                        
                    <div class="lg:col-span-1">
                        <!-- Loan Calculator -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-calculator mr-2 text-primary-600"></i> Loan Calculator
                                </h5>
                            </div>
                            <div class="p-6">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount: ₦<span id="calc-amount">0</span></label>
                                    <div class="text-gray-500 text-sm">Term: <span id="calc-term">0</span> months</div>
                                </div>
                                <hr class="border-gray-200 my-4">
                                <div class="mb-3">
                                    <strong class="text-gray-900">Interest Rate: 5.0% per annum</strong>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-900">Monthly Payment: ₦<span id="calc-monthly">0</span></strong>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-900">Total Payment: ₦<span id="calc-total">0</span></strong>
                                </div>
                                <div class="text-gray-500 text-sm">
                                    Total Interest: ₦<span id="calc-interest">0</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Requirements -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-clipboard-list mr-2 text-primary-600"></i> Loan Requirements
                                </h5>
                            </div>
                            <div class="p-6">
                                <ul class="space-y-3">
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                        <span class="text-gray-700">Active membership status</span>
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                        <span class="text-gray-700">Valid collateral</span>
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                        <span class="text-gray-700">Reliable guarantor</span>
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                        <span class="text-gray-700">Clear loan purpose</span>
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                        <span class="text-gray-700">No outstanding defaults</span>
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
                document.getElementById('calc-monthly').textContent = monthlyPayment.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('calc-total').textContent = totalPayment.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('calc-interest').textContent = totalInterest.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                document.getElementById('calc-amount').textContent = '0';
                document.getElementById('calc-term').textContent = '0';
                document.getElementById('calc-monthly').textContent = '0';
                document.getElementById('calc-total').textContent = '0';
                document.getElementById('calc-interest').textContent = '0';
            }
        }
        
        // Update calculator when amount or term changes
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('amount').addEventListener('input', calculateLoan);
            document.getElementById('term_months').addEventListener('change', calculateLoan);
            
            // Initial calculation
            calculateLoan();
        });
    </script>
</body>
</html>