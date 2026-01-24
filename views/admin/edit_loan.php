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
                        primary: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
                        success: '#22c55e',
                        danger: '#ef4444'
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
            <!-- Header (using existing include logic which likely has Bootstrap, we just override structure below) -->
            <?php include_once '../includes/header.php'; ?>
            
            <!-- Main Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                        <div>
                            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                                <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="hover:text-primary-600 transition-colors">Loans</a>
                                <i class="fas fa-chevron-right text-xs"></i>
                                <span class="text-gray-900 font-medium">Loan #<?php echo $loan['loan_id']; ?></span>
                                <i class="fas fa-chevron-right text-xs"></i>
                                <span class="text-gray-500">Edit</span>
                            </div>
                            <h1 class="text-2xl font-bold text-gray-900"><?php echo $pageTitle; ?></h1>
                            <p class="text-gray-500 mt-1">Update loan amounts, terms, and details.</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" 
                               class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                                <i class="fas fa-times mr-2"></i> Cancel & Return
                            </a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                    <ul class="list-disc pl-5 mt-2 text-sm text-red-700">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <!-- Main Form Area (Left 2/3) -->
                        <div class="lg:col-span-2 space-y-6">
                            
                            <form action="" method="POST" id="loanForm">
                                <!-- Core Loan Details -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-sliders-h mr-2 text-primary-500"></i> Loan Configuration
                                        </h3>
                                    </div>
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                        
                                        <div class="col-span-1 md:col-span-2">
                                            <label for="application_date" class="block text-sm font-medium text-gray-700 mb-1">
                                                Application Date <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" 
                                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                   id="application_date" name="application_date" 
                                                   value="<?php echo isset($_POST['application_date']) ? $_POST['application_date'] : $loan['application_date']; ?>" required>
                                        </div>

                                        <div class="col-span-1 px-4 py-3 bg-blue-50 rounded-lg border border-blue-100">
                                            <label for="amount" class="block text-sm font-medium text-blue-900 mb-1">
                                                Loan Amount (₦) <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative rounded-md shadow-sm">
                                                <input type="number" step="0.01" min="0.01" 
                                                       class="w-full rounded-lg border-blue-300 pr-12 text-lg font-semibold focus:border-blue-500 focus:ring-blue-500" 
                                                       id="amount" name="amount" 
                                                       value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : $loan['amount']; ?>" required>
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                    <span class="text-blue-500 font-bold">NGN</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-span-1 grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="term_months" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Term (Months) <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" min="1" max="120"
                                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                       id="term_months" name="term_months" 
                                                       value="<?php echo isset($_POST['term_months']) ? $_POST['term_months'] : ($loan['term'] ?? $defaultTermMonths); ?>" required>
                                            </div>
                                            <div>
                                                <label for="interest_rate" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Rate (%) <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" step="0.01" min="0" 
                                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                       id="interest_rate" name="interest_rate" 
                                                       value="<?php echo isset($_POST['interest_rate']) ? $_POST['interest_rate'] : ($loan['interest_rate'] ?? $defaultInterestRate); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-span-1 md:col-span-2">
                                            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">
                                                Loan Purpose <span class="text-red-500">*</span>
                                            </label>
                                            <textarea class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                      id="purpose" name="purpose" rows="2" required 
                                                      placeholder="e.g. Home renovation..."><?php echo isset($_POST['purpose']) ? $_POST['purpose'] : $loan['purpose']; ?></textarea>
                                        </div>

                                    </div>
                                    
                                    <!-- Live Preview -->
                                    <div class="border-t border-gray-100 bg-green-50/50 p-6 flex flex-col sm:flex-row justify-between items-center text-center sm:text-left gap-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-green-700 font-semibold mb-1">New Monthly Payment</p>
                                            <p class="text-3xl font-bold text-green-700" id="monthly-payment">₦0.00</p>
                                        </div>
                                        <div class="h-8 w-px bg-green-200 hidden sm:block"></div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold mb-1">Total Payback</p>
                                            <p class="text-lg font-semibold text-gray-700" id="total-repayment">₦0.00</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Collateral & Validation -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                        <h3 class="text-lg font-semibold text-gray-900">Security & Guarantors</h3>
                                    </div>
                                    <div class="p-6 grid grid-cols-1 gap-6">
                                        <div>
                                            <label for="collateral" class="block text-sm font-medium text-gray-700 mb-1">Collateral Details</label>
                                            <textarea class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                      id="collateral" name="collateral" rows="2"><?php echo isset($_POST['collateral']) ? $_POST['collateral'] : ($loan['collateral'] ?? ''); ?></textarea>
                                        </div>
                                        <div>
                                            <label for="guarantor" class="block text-sm font-medium text-gray-700 mb-1">Guarantor Information</label>
                                            <textarea class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                      id="guarantor" name="guarantor" rows="2"><?php echo isset($_POST['guarantor']) ? $_POST['guarantor'] : ($loan['guarantor'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Admin Override Section -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                        <h3 class="text-lg font-semibold text-gray-900">Admin Override Settings</h3>
                                    </div>
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                         <div>
                                            <label for="savings" class="block text-sm font-medium text-gray-700 mb-1">Adjusted Savings (Override)</label>
                                            <div class="relative rounded-md shadow-sm">
                                                <input type="number" class="w-full rounded-lg border-gray-300 pr-12 focus:border-primary-500 focus:ring-primary-500" 
                                                       id="savings" name="savings" step="0.01" min="0" 
                                                       value="<?php echo isset($_POST['savings']) ? $_POST['savings'] : ($loan['savings'] ?? ''); ?>"
                                                       placeholder="Leave empty to use system default">
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-400">NGN</span>
                                                </div>
                                            </div>
                                         </div>
                                         <div>
                                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Override Reason / Admin Notes</label>
                                            <textarea class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                                      id="notes" name="notes" rows="1"><?php echo isset($_POST['notes']) ? $_POST['notes'] : ($loan['notes'] ?? ''); ?></textarea>
                                         </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-6">
                                    <button type="submit" 
                                            class="w-full flex justify-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-lg font-bold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all transform active:scale-95">
                                        <i class="fas fa-save mr-2 mt-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>

                        </div>
                        
                        <!-- Right Column: Member Profile (Sticky) -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 sticky top-6">
                                <div class="p-6 flex flex-col items-center text-center border-b border-gray-100">
                                    <div class="relative mb-4">
                                        <?php 
                                        $photoPath = realpath(__DIR__ . '/../../uploads/members/' . ($member['photo'] ?? ''));
                                        if (!empty($member['photo']) && $photoPath && file_exists($photoPath)): 
                                        ?>
                                            <img src="<?php echo '../../uploads/members/' . $member['photo']; ?>" 
                                                 alt="Member Photo" 
                                                 class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                                        <?php else: ?>
                                            <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-3xl font-bold border-4 border-white shadow-md">
                                                <?php echo strtoupper(substr($member['first_name'] ?? 'U', 0, 1) . substr($member['last_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')); ?></h2>
                                    <p class="text-sm text-gray-500 mt-1">Editing Loan for Member #<?php echo $member['member_id']; ?></p>
                                </div>
                                
                                <div class="p-4 bg-gray-50/50 text-sm space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Savings Ballance</span>
                                        <span class="font-semibold text-gray-900">₦<?php echo number_format($member['savings_balance'] ?? 0, 2); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Loan Limit (3x)</span>
                                        <span class="font-semibold text-gray-900">₦<?php echo number_format(($member['savings_balance'] ?? 0) * 3, 2); ?></span>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate loan payments
            function calculatePayment() {
                const amount = parseFloat(document.getElementById('amount').value) || 0;
                const termMonths = parseInt(document.getElementById('term_months').value) || 0;
                const interestRate = parseFloat(document.getElementById('interest_rate').value) || 0;

                if (amount > 0 && termMonths > 0 && interestRate >= 0) {
                    const monthlyRate = interestRate / 100 / 12;
                    let monthlyPayment;
                    if (monthlyRate > 0) {
                        monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / 
                                       (Math.pow(1 + monthlyRate, termMonths) - 1);
                    } else {
                        monthlyPayment = amount / termMonths;
                    }

                    const totalRepayment = monthlyPayment * termMonths;

                    document.getElementById('monthly-payment').textContent = '₦' + monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('total-repayment').textContent = '₦' + totalRepayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                } else {
                    document.getElementById('monthly-payment').textContent = '₦0.00';
                    document.getElementById('total-repayment').textContent = '₦0.00';
                }
            }

            document.getElementById('amount').addEventListener('input', calculatePayment);
            document.getElementById('term_months').addEventListener('input', calculatePayment);
            document.getElementById('interest_rate').addEventListener('input', calculatePayment);

            calculatePayment(); // Init
        });
    </script>
</body>
</html>
