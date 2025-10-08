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

// Only approved, disbursed, or active loans can receive repayments
if (!in_array($loan['status'], ['Approved', 'Disbursed', 'Active'])) {
    $_SESSION['flash_message'] = "Only approved, disbursed, or active loans can receive repayments";
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

$monthlyPayment = $loanController->calculateMonthlyPayment($loan['amount'], $loan['interest_rate'], $loan['term']);
$totalLoanAmount = $monthlyPayment * $loan['term'];
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

<!-- Main Content -->
<div class="flex-1 ml-64 bg-gray-50">
    <div class="p-8">
        <!-- Page Heading -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?php echo $pageTitle; ?></h1>
                <p class="text-gray-600 mt-2">Record a new payment for this loan</p>
            </div>
            <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors duration-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Loan Details
            </a>
        </div>
        
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="loans.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">Loans</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="text-sm font-medium text-gray-700 hover:text-blue-600">View Loan #<?php echo $loan_id; ?></a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-sm font-medium text-gray-500">Add Repayment</span>
                    </div>
                </li>
            </ol>
        </nav>
        
        <!-- Error messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error!</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="-mx-1.5 -my-1.5">
                            <button type="button" class="inline-flex bg-red-50 rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600" onclick="this.parentElement.parentElement.parentElement.parentElement.style.display='none'">
                                <span class="sr-only">Dismiss</span>
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
            
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <!-- Loan Details Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Loan Details</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Loan ID:</span>
                                    <span class="text-sm text-gray-900"><?php echo $loan['loan_id']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Amount:</span>
                                    <span class="text-sm text-gray-900">$<?php echo number_format($loan['amount'], 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Term:</span>
                                    <span class="text-sm text-gray-900"><?php echo $loan['term']; ?> months</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Interest Rate:</span>
                                    <span class="text-sm text-gray-900"><?php echo $loan['interest_rate']; ?>%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Monthly Payment:</span>
                                    <span class="text-sm text-gray-900">$<?php echo number_format($monthlyPayment, 2); ?></span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Status:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?php echo ucfirst($loan['status']); ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Disbursement Date:</span>
                                    <span class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($loan['disbursement_date'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Total Loan Amount:</span>
                                    <span class="text-sm text-gray-900">$<?php echo number_format($totalLoanAmount, 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Total Paid:</span>
                                    <span class="text-sm text-green-600 font-semibold">$<?php echo number_format($totalPaid, 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Remaining Balance:</span>
                                    <span class="text-sm text-red-600 font-semibold">$<?php echo number_format($remainingBalance, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Repayment Progress Bar -->
                        <div class="mt-6">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-sm font-medium text-gray-900">Repayment Progress</h4>
                                <span class="text-sm text-gray-500"><?php echo round($paymentPercentage, 1); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full transition-all duration-300" 
                                     style="width: <?php echo min(100, $paymentPercentage); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                    
                <!-- Member Information Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Member Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo '../../uploads/members/' . $member['photo']; ?>" 
                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                         class="w-20 h-20 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-20 h-20 rounded-full bg-gray-500 flex items-center justify-content-center">
                                        <span class="text-white text-xl font-semibold">
                                            <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                                <div class="mt-2 space-y-1">
                                    <p class="text-sm text-gray-600">Member ID: <?php echo $member['member_id']; ?></p>
                                    <p class="text-sm text-gray-600">Email: <?php echo htmlspecialchars($member['email']); ?></p>
                                    <p class="text-sm text-gray-600">Phone: <?php echo htmlspecialchars($member['phone']); ?></p>
                                </div>
                                <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-3 py-1.5 mt-3 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors duration-200">
                                    View Full Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                
            <div class="space-y-6">
                <!-- Add Repayment Form -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Add Repayment</h3>
                    </div>
                    <div class="p-6">
                        <form action="" method="POST" id="repaymentForm">
                            <div class="space-y-4">
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                        Payment Amount <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="amount" name="amount" step="0.01" min="0.01" 
                                               max="<?php echo $remainingBalance; ?>" 
                                               value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : $monthlyPayment; ?>" required>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Regular monthly payment: $<?php echo number_format($monthlyPayment, 2); ?></p>
                                </div>
                                
                                <div>
                                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                                        Payment Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="payment_date" name="payment_date" 
                                           value="<?php echo isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div>
                                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                                        Payment Method <span class="text-red-500">*</span>
                                    </label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <?php foreach ($loanController->getPaymentMethods() as $method): ?>
                                            <option value="<?php echo $method; ?>" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === $method) ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($method); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="receipt_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Receipt Number
                                    </label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="receipt_number" name="receipt_number" 
                                           value="<?php echo isset($_POST['receipt_number']) ? $_POST['receipt_number'] : ''; ?>">
                                </div>
                                
                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        Notes
                                    </label>
                                    <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="notes" name="notes" rows="3" placeholder="Additional notes..."><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                                </div>
                                
                                <div class="flex flex-col space-y-3 pt-4">
                                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center">
                                        <i class="fas fa-credit-card mr-2"></i> Record Payment
                                    </button>
                                    <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="w-full px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors duration-200 text-center">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                    
                <!-- Payment Summary Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-900">Payment Summary</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Total Loan Amount:</span>
                                <span class="text-sm text-gray-900">$<?php echo number_format($totalLoanAmount, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Already Paid:</span>
                                <span class="text-sm text-green-600">$<?php echo number_format($totalPaid, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Remaining Balance:</span>
                                <span class="text-sm text-red-600">$<?php echo number_format($remainingBalance, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Current Payment:</span>
                                <span class="text-sm text-blue-600 font-semibold">$<span id="current-payment"><?php echo number_format(isset($_POST['amount']) ? $_POST['amount'] : $monthlyPayment, 2); ?></span></span>
                            </div>
                            <hr class="border-gray-200">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-900">New Balance After Payment:</span>
                                <span class="text-sm text-gray-900 font-semibold">$<span id="new-balance"><?php echo number_format($remainingBalance - (isset($_POST['amount']) ? $_POST['amount'] : $monthlyPayment), 2); ?></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('repaymentForm');
        form.addEventListener('submit', function(event) {
            let valid = true;
            const amount = document.getElementById('amount').value;
            const paymentDate = document.getElementById('payment_date').value;
            const paymentMethod = document.getElementById('payment_method').value;
            
            // Reset previous error messages
            document.querySelectorAll('.border-red-500').forEach(function(element) {
                element.classList.remove('border-red-500', 'ring-red-500');
                element.classList.add('border-gray-300');
            });
            
            // Validate amount
            if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
                const amountField = document.getElementById('amount');
                amountField.classList.remove('border-gray-300');
                amountField.classList.add('border-red-500', 'ring-red-500');
                valid = false;
            }
            
            // Validate payment date
            if (!paymentDate) {
                const dateField = document.getElementById('payment_date');
                dateField.classList.remove('border-gray-300');
                dateField.classList.add('border-red-500', 'ring-red-500');
                valid = false;
            }
            
            // Validate payment method
            if (!paymentMethod) {
                const methodField = document.getElementById('payment_method');
                methodField.classList.remove('border-gray-300');
                methodField.classList.add('border-red-500', 'ring-red-500');
                valid = false;
            }
            
            if (!valid) {
                event.preventDefault();
            }
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
                this.classList.remove('border-gray-300');
                this.classList.add('border-red-500', 'ring-red-500');
            } else {
                this.classList.remove('border-red-500', 'ring-red-500');
                this.classList.add('border-gray-300');
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
