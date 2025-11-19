<?php
/**
 * Admin - Edit Loan Application
 * 
 * This page provides a form for editing existing loan applications.
 * Only pending loan applications can be edited.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../controllers/loan_controller.php';
require_once __DIR__ . '/../../controllers/member_controller.php';
require_once '../../includes/config/SystemConfigService.php';

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

// Only pending loans can be edited
if ($loan['status'] !== 'Pending') {
    $_SESSION['flash_message'] = "Only pending loan applications can be edited";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: view_loan.php?id=' . $loan_id);
    exit();
}

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Initialize SystemConfigService for default fallbacks
try {
    $sysConfig = SystemConfigService::getInstance($pdo ?? null);
} catch (Exception $e) {
    $sysConfig = null;
    error_log('edit_loan: SystemConfigService init failed: ' . $e->getMessage());
}

// Compute centralized defaults with safe fallbacks
$defaultTermMonths = '12';
try {
    if ($sysConfig) {
        $defaultTermMonths = (string)$sysConfig->get('MAX_LOAN_DURATION', (int)$defaultTermMonths);
    }
} catch (Exception $e) {
    // keep fallback
}

$defaultInterestRate = '10';
try {
    if ($sysConfig) {
        $defaultInterestRate = (string)$sysConfig->get('DEFAULT_INTEREST_RATE', (float)$defaultInterestRate);
    }
} catch (Exception $e) {
    // keep fallback
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
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
    
    // Validate additional fields
    if (!empty($_POST['savings']) && (!is_numeric($_POST['savings']) || $_POST['savings'] < 0)) {
        $errors[] = "Valid savings amount is required";
    }
    
    // If no errors, process the loan application update
    if (empty($errors)) {
        $loanData = [
            'amount' => $_POST['amount'],
            'purpose' => $_POST['purpose'],
            'term_months' => $_POST['term_months'],
            'interest_rate' => $_POST['interest_rate'],
            'application_date' => $_POST['application_date'],
            'status' => 'Pending',
            'collateral' => $_POST['collateral'] ?? '',
            'guarantor' => $_POST['guarantor'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'savings' => $_POST['savings'] ?? '',
            'month_deduction_started' => $_POST['month_deduction_started'] ?? '',
            'month_deduction_end' => $_POST['month_deduction_end'] ?? '',
            'other_payment_plans' => $_POST['other_payment_plans'] ?? '',
            'remarks' => $_POST['remarks'] ?? ''
        ];
        
        $result = $loanController->updateLoanApplication($loan_id, $loanData);
        
        if ($result) {
            // Set success message and redirect
            $_SESSION['flash_message'] = "Loan application updated successfully";
            $_SESSION['flash_message_class'] = "alert-success";
            header('Location: view_loan.php?id=' . $loan_id);
            exit();
        } else {
            $errors[] = "Failed to update loan application. Please try again.";
        }
    }
}

// Page title
$pageTitle = "Edit Loan Application #" . $loan_id;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?> - CSIMS</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 font-sans">
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include_once '../includes/header.php'; ?>
            
            <!-- Main Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6 pl-12">
                <div class="max-w-4xl mx-auto ml-12">
                    <!-- Page Header -->
                    <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl mb-8 shadow-lg">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold mb-2">
                                    <i class="fas fa-edit mr-4"></i><?php echo $pageTitle; ?>
                                </h1>
                                <p class="text-primary-100 text-lg">Modify loan application details</p>
                            </div>
                            <div class="flex gap-3">
                                <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-primary-50 transition-all duration-200 shadow-md hover:shadow-lg">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to Loan Details
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Breadcrumb -->
                    <nav class="flex mb-8" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                    <i class="fas fa-home mr-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">Loans</a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="text-sm font-medium text-gray-700 hover:text-blue-600">View Loan #<?php echo $loan_id; ?></a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <span class="text-sm font-medium text-gray-500">Edit</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <!-- Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-6">
                            <h4 class="font-bold">Please correct the following errors:</h4>
                            <ul class="list-disc list-inside mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Member Information Card -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8 overflow-hidden">
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-user mr-3 text-blue-600"></i>Member Information
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center space-x-6">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($member['photo'])): ?>
                                        <img src="<?php echo BASE_URL . '/uploads/members/' . $member['photo']; ?>" 
                                             alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                             class="w-20 h-20 rounded-full object-cover border-4 border-gray-200">
                                    <?php else: ?>
                                        <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center">
                                            <span class="text-white text-xl font-bold">
                                                <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xl font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                                    <div class="space-y-1 text-sm text-gray-600">
                                        <p><span class="font-medium">Member ID:</span> <?php echo $member['member_id']; ?></p>
                                        <p><span class="font-medium">Email:</span> <?php echo htmlspecialchars($member['email']); ?></p>
                                        <p><span class="font-medium">Phone:</span> <?php echo htmlspecialchars($member['phone']); ?></p>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" 
                                       class="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                        <i class="fas fa-eye mr-2"></i>View Full Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Loan Form -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-edit mr-3 text-blue-600"></i>Edit Loan Application
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Modify loan details as needed. Admin can adjust amount, terms, and other parameters.</p>
                        </div>
                        <div class="p-6">
                            <form action="" method="POST" id="loanForm" class="space-y-6">
                                <!-- Application Date -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="application_date" class="block text-sm font-medium text-gray-700 mb-2">
                                            Application Date <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                               id="application_date" name="application_date" 
                                               value="<?php echo isset($_POST['application_date']) ? $_POST['application_date'] : $loan['application_date']; ?>" required>
                                    </div>
                                </div>

                                <!-- Loan Details -->
                                <div class="bg-blue-50 rounded-lg p-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-money-bill-wave mr-3 text-blue-600"></i>Loan Details
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                                Loan Amount <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 font-medium">₦</span>
                                                </div>
                                                <input type="number" 
                                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                       id="amount" name="amount" step="0.01" min="0.01" 
                                                       value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : $loan['amount']; ?>" required>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="term_months" class="block text-sm font-medium text-gray-700 mb-2">
                                                Term (Months) <span class="text-red-500">*</span>
                                            </label>
                                            <input type="number" 
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                   id="term_months" name="term_months" min="1" max="120" 
                                                   value="<?php echo isset($_POST['term_months']) ? $_POST['term_months'] : ($loan['term'] ?? $defaultTermMonths); ?>" required>
                                        </div>
                                        <div>
                                            <label for="interest_rate" class="block text-sm font-medium text-gray-700 mb-2">
                                                Interest Rate (% per annum) <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <input type="number" 
                                                       class="w-full pr-10 pl-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                       id="interest_rate" name="interest_rate" step="0.01" min="0" 
                                                       value="<?php echo isset($_POST['interest_rate']) ? $_POST['interest_rate'] : ($loan['interest_rate'] ?? $defaultInterestRate); ?>" required>
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 font-medium">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Loan Purpose -->
                                <div>
                                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                        Loan Purpose <span class="text-red-500">*</span>
                                    </label>
                                    <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                              id="purpose" name="purpose" rows="3" required 
                                              placeholder="Describe the purpose of this loan..."><?php echo isset($_POST['purpose']) ? $_POST['purpose'] : $loan['purpose']; ?></textarea>
                                </div>

                                <!-- Additional Fields -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="collateral" class="block text-sm font-medium text-gray-700 mb-2">
                                            Collateral (optional)
                                        </label>
                                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                  id="collateral" name="collateral" rows="2"
                                                  placeholder="Any collateral offered..."><?php echo isset($_POST['collateral']) ? $_POST['collateral'] : ($loan['collateral'] ?? ''); ?></textarea>
                                    </div>
                                    <div>
                                        <label for="guarantor" class="block text-sm font-medium text-gray-700 mb-2">
                                            Guarantor (optional)
                                        </label>
                                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                  id="guarantor" name="guarantor" rows="2"
                                                  placeholder="Guarantor information..."><?php echo isset($_POST['guarantor']) ? $_POST['guarantor'] : ($loan['guarantor'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <!-- Member-Submitted Information Section -->
                                <div class="bg-purple-50 rounded-lg p-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-user-edit mr-3 text-purple-600"></i>Member-Submitted Details
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="savings" class="block text-sm font-medium text-gray-700 mb-2">
                                                Member's Savings
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 font-medium">₦</span>
                                                </div>
                                                <input type="number" 
                                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                       id="savings" name="savings" step="0.01" min="0" 
                                                       value="<?php echo isset($_POST['savings']) ? $_POST['savings'] : ($loan['savings'] ?? ''); ?>"
                                                       placeholder="Enter member's current savings">
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label for="month_deduction_started" class="block text-sm font-medium text-gray-700 mb-2">
                                                Deduction Start Month
                                            </label>
                                            <input type="month" 
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                   id="month_deduction_started" name="month_deduction_started" 
                                                   value="<?php echo isset($_POST['month_deduction_started']) ? $_POST['month_deduction_started'] : ($loan['month_deduction_started'] ?? ''); ?>">
                                            <p class="mt-1 text-xs text-gray-500">When loan deductions should start.</p>
                                        </div>
                                        
                                        <div>
                                            <label for="month_deduction_end" class="block text-sm font-medium text-gray-700 mb-2">
                                                Deduction End Month
                                            </label>
                                            <input type="month" 
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                   id="month_deduction_end" name="month_deduction_end" 
                                                   value="<?php echo isset($_POST['month_deduction_end']) ? $_POST['month_deduction_end'] : ($loan['month_deduction_end'] ?? ''); ?>">
                                            <p class="mt-1 text-xs text-gray-500">When loan deductions should end.</p>
                                        </div>
                                        
                                        <div>
                                            <label for="other_payment_plans" class="block text-sm font-medium text-gray-700 mb-2">
                                                Other Payment Plans
                                            </label>
                                            <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                      id="other_payment_plans" name="other_payment_plans" rows="2"
                                                      placeholder="Additional payment arrangements..."><?php echo isset($_POST['other_payment_plans']) ? $_POST['other_payment_plans'] : ($loan['other_payment_plans'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-6">
                                        <label for="remarks" class="block text-sm font-medium text-gray-700 mb-2">
                                            Member's Remarks
                                        </label>
                                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                  id="remarks" name="remarks" rows="3"
                                                  placeholder="Member's comments and remarks..."><?php echo isset($_POST['remarks']) ? $_POST['remarks'] : ($loan['remarks'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <!-- Admin Notes -->
                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        Admin Notes (optional)
                                    </label>
                                    <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                              id="notes" name="notes" rows="3"
                                              placeholder="Administrative notes and processing comments..."><?php echo isset($_POST['notes']) ? $_POST['notes'] : ($loan['notes'] ?? ''); ?></textarea>
                                    <p class="mt-1 text-xs text-gray-500">These notes are for administrative use and processing comments.</p>
                                </div>

                                <!-- Payment Preview -->
                                <div class="bg-green-50 rounded-lg p-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-calculator mr-3 text-green-600"></i>Payment Preview
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="bg-white p-4 rounded-lg border">
                                            <p class="text-sm font-medium text-gray-600">Monthly Payment</p>
                                            <p class="text-xl font-bold text-green-600" id="monthly-payment">₦0.00</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <p class="text-sm font-medium text-gray-600">Total Repayment</p>
                                            <p class="text-xl font-bold text-blue-600" id="total-repayment">₦0.00</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <p class="text-sm font-medium text-gray-600">Total Interest</p>
                                            <p class="text-xl font-bold text-yellow-600" id="total-interest">₦0.00</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                    <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" 
                                       class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors flex items-center">
                                        <i class="fas fa-save mr-2"></i>Update Loan Application
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate loan payments
            function calculatePayment() {
                const amount = parseFloat(document.getElementById('amount').value) || 0;
                const termMonths = parseInt(document.getElementById('term_months').value) || 0;
                const interestRate = parseFloat(document.getElementById('interest_rate').value) || 0;

                if (amount > 0 && termMonths > 0 && interestRate >= 0) {
                    // Monthly interest rate
                    const monthlyRate = interestRate / 100 / 12;
                    
                    let monthlyPayment;
                    if (monthlyRate > 0) {
                        // Standard loan payment formula
                        monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / 
                                       (Math.pow(1 + monthlyRate, termMonths) - 1);
                    } else {
                        // No interest case
                        monthlyPayment = amount / termMonths;
                    }

                    const totalRepayment = monthlyPayment * termMonths;
                    const totalInterest = totalRepayment - amount;

                    // Update display
                    document.getElementById('monthly-payment').textContent = '₦' + monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('total-repayment').textContent = '₦' + totalRepayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('total-interest').textContent = '₦' + totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                } else {
                    document.getElementById('monthly-payment').textContent = '₦0.00';
                    document.getElementById('total-repayment').textContent = '₦0.00';
                    document.getElementById('total-interest').textContent = '₦0.00';
                }
            }

            // Add event listeners
            document.getElementById('amount').addEventListener('input', calculatePayment);
            document.getElementById('term_months').addEventListener('input', calculatePayment);
            document.getElementById('interest_rate').addEventListener('input', calculatePayment);

            // Initial calculation
            calculatePayment();

            // Form validation
            document.getElementById('loanForm').addEventListener('submit', function(e) {
                const amount = parseFloat(document.getElementById('amount').value);
                const termMonths = parseInt(document.getElementById('term_months').value);
                const interestRate = parseFloat(document.getElementById('interest_rate').value);
                const purpose = document.getElementById('purpose').value.trim();

                if (!amount || amount <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid loan amount.');
                    document.getElementById('amount').focus();
                    return false;
                }

                if (!termMonths || termMonths <= 0 || termMonths > 120) {
                    e.preventDefault();
                    alert('Please enter a valid loan term (1-120 months).');
                    document.getElementById('term_months').focus();
                    return false;
                }

                if (interestRate < 0) {
                    e.preventDefault();
                    alert('Interest rate cannot be negative.');
                    document.getElementById('interest_rate').focus();
                    return false;
                }

                if (!purpose) {
                    e.preventDefault();
                    alert('Please enter the loan purpose.');
                    document.getElementById('purpose').focus();
                    return false;
                }

                return true;
            });
        });
    </script>
</body>
</html>
