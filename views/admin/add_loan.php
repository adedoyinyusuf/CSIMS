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
            'term' => $_POST['term_months'],
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

<div class="flex h-screen bg-gray-50">
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Main content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900"><?php echo $pageTitle; ?></h1>
                            <nav class="flex mt-2" aria-label="Breadcrumb">
                                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                    <li class="inline-flex items-center">
                                        <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="text-gray-700 hover:text-blue-600">
                                            Dashboard
                                        </a>
                                    </li>
                                    <li>
                                        <div class="flex items-center">
                                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <a href="<?php echo BASE_URL; ?>/admin/loans.php" class="ml-1 text-gray-700 hover:text-blue-600 md:ml-2">
                                                Loans
                                            </a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="flex items-center">
                                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="ml-1 text-gray-500 md:ml-2">Add Loan Application</span>
                                        </div>
                                    </li>
                                </ol>
                            </nav>
                        </div>
                        <div class="flex space-x-3">
                            <a href="<?php echo BASE_URL; ?>/admin/loans.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to Loans
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Error messages -->
                <?php if (!empty($errors)): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Error!</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            
                <!-- Loan Application Form -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="bg-white shadow-lg rounded-xl border border-gray-100">
                            <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                                <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Loan Application Form
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">Fill in the details below to create a new loan application</p>
                            </div>
                            <div class="p-8">
                                <form action="" method="POST" id="loanForm" class="space-y-8">
                                    <!-- Applicant Information Section -->
                                    <div class="bg-gray-50 rounded-lg p-6">
                                        <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            Applicant Information
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="space-y-2">
                                                <label for="member_id" class="block text-sm font-semibold text-gray-700">Member <span class="text-red-500">*</span></label>
                                                <select id="member_id" name="member_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                                    <option value="">Select Member</option>
                                                    <?php foreach ($members as $member): ?>
                                                        <option value="<?php echo $member['member_id']; ?>" <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['member_id'] . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="application_date" class="block text-sm font-semibold text-gray-700">Application Date <span class="text-red-500">*</span></label>
                                                <input type="date" id="application_date" name="application_date" 
                                                       value="<?php echo isset($_POST['application_date']) ? $_POST['application_date'] : date('Y-m-d'); ?>" required
                                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Loan Details Section -->
                                    <div class="bg-blue-50 rounded-lg p-6">
                                        <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                            Loan Details
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="space-y-2">
                                                <label for="amount" class="block text-sm font-semibold text-gray-700">Loan Amount <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 font-medium">$</span>
                                                    </div>
                                                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" 
                                                           value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" required
                                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="term_months" class="block text-sm font-semibold text-gray-700">Loan Term (Months) <span class="text-red-500">*</span></label>
                                                <input type="number" id="term_months" name="term_months" min="1" max="120" 
                                                       value="<?php echo isset($_POST['term_months']) ? $_POST['term_months'] : '12'; ?>" required
                                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                            </div>
                                            <div class="space-y-2">
                                                <label for="interest_rate" class="block text-sm font-semibold text-gray-700">Interest Rate (% per annum) <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input type="number" id="interest_rate" name="interest_rate" step="0.01" min="0" 
                                                           value="<?php echo isset($_POST['interest_rate']) ? $_POST['interest_rate'] : '10'; ?>" required
                                                           class="w-full pr-10 pl-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 font-medium">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-6 space-y-2">
                                            <label for="purpose" class="block text-sm font-semibold text-gray-700">Loan Purpose <span class="text-red-500">*</span></label>
                                            <textarea id="purpose" name="purpose" rows="3" required placeholder="Describe the purpose of this loan..."
                                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"><?php echo isset($_POST['purpose']) ? $_POST['purpose'] : ''; ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Information Section -->
                                    <div class="bg-green-50 rounded-lg p-6">
                                        <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            Additional Information
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="space-y-2">
                                                <label for="collateral" class="block text-sm font-semibold text-gray-700">Collateral (if any)</label>
                                                <textarea id="collateral" name="collateral" rows="3" placeholder="Describe any collateral offered..."
                                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"><?php echo isset($_POST['collateral']) ? $_POST['collateral'] : ''; ?></textarea>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="guarantor" class="block text-sm font-semibold text-gray-700">Guarantor (if any)</label>
                                                <textarea id="guarantor" name="guarantor" rows="3" placeholder="Provide guarantor details..."
                                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"><?php echo isset($_POST['guarantor']) ? $_POST['guarantor'] : ''; ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-6 space-y-2">
                                            <label for="notes" class="block text-sm font-semibold text-gray-700">Additional Notes</label>
                                            <textarea id="notes" name="notes" rows="3" placeholder="Any additional notes or comments..."
                                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Form Actions -->
                                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-200">
                                        <a href="<?php echo BASE_URL; ?>/admin/loans.php" class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Cancel
                                        </a>
                                        <button type="submit" class="inline-flex justify-center items-center px-8 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Submit Loan Application
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Preview -->
                    <div>
                        <div class="bg-white shadow-lg rounded-xl border border-gray-100">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    Payment Preview
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">Real-time calculation based on loan details</p>
                            </div>
                            <div class="p-6">
                                <div class="space-y-6">
                                    <div class="bg-blue-50 rounded-lg p-4">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Monthly Payment:</span>
                                            </div>
                                            <span class="text-lg font-bold text-blue-600" id="monthly-payment">$0.00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-green-50 rounded-lg p-4">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Total Repayment:</span>
                                            </div>
                                            <span class="text-lg font-bold text-green-600" id="total-repayment">$0.00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-yellow-50 rounded-lg p-4">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Total Interest:</span>
                                            </div>
                                            <span class="text-lg font-bold text-yellow-600" id="total-interest">$0.00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border-l-4 border-blue-500">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 mr-2 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-xs font-medium text-gray-700 mb-1">Note:</p>
                                                <p class="text-xs text-gray-600">Calculations are estimates based on simple interest. Final terms may vary based on approval conditions.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // Calculate loan payment preview
    function calculatePayment() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const termMonths = parseInt(document.getElementById('term_months').value) || 0;
        const interestRate = parseFloat(document.getElementById('interest_rate').value) || 0;
        
        if (amount > 0 && termMonths > 0 && interestRate >= 0) {
            const monthlyRate = interestRate / 100 / 12;
            let monthlyPayment;
            
            if (monthlyRate === 0) {
                monthlyPayment = amount / termMonths;
            } else {
                monthlyPayment = (amount * monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / 
                                (Math.pow(1 + monthlyRate, termMonths) - 1);
            }
            
            const totalRepayment = monthlyPayment * termMonths;
            const totalInterest = totalRepayment - amount;
            
            document.getElementById('monthly-payment').textContent = '$' + monthlyPayment.toFixed(2);
            document.getElementById('total-repayment').textContent = '$' + totalRepayment.toFixed(2);
            document.getElementById('total-interest').textContent = '$' + totalInterest.toFixed(2);
        } else {
            document.getElementById('monthly-payment').textContent = '$0.00';
            document.getElementById('total-repayment').textContent = '$0.00';
            document.getElementById('total-interest').textContent = '$0.00';
        }
    }
    
    // Add event listeners for real-time calculation
    document.getElementById('amount').addEventListener('input', calculatePayment);
    document.getElementById('term_months').addEventListener('input', calculatePayment);
    document.getElementById('interest_rate').addEventListener('input', calculatePayment);
    
    // Form validation
    document.getElementById('loanForm').addEventListener('submit', function(event) {
        const form = event.target;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(function(input) {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('border-red-500');
                input.classList.remove('border-gray-300');
            } else {
                input.classList.remove('border-red-500');
                input.classList.add('border-gray-300');
            }
        });
        
        if (!isValid) {
            event.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
    
    // Initial calculation
    calculatePayment();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
