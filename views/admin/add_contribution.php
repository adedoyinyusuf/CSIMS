<?php
/**
 * Add Contribution Page
 * 
 * This page provides a form to add a new contribution record.
 */

// Include required files
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/contribution_controller.php';
require_once '../../controllers/member_controller.php';

// Initialize controllers
$authController = new AuthController();
$contributionController = new ContributionController();
$memberController = new MemberController();

// Check if user is logged in
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get current user
$currentUser = $authController->getCurrentUser();

// Get all members for dropdown
$result = $memberController->getAllMembers(1, 1000, '', 'last_name', 'ASC', 'active');
$members = $result['members'];

// Get contribution types and payment methods
$contributionTypes = $contributionController->getContributionTypes();
$paymentMethods = $contributionController->getPaymentMethods();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate member_id
    if (empty($_POST['member_id'])) {
        $errors[] = "Member is required";
    }
    
    // Validate amount
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    // Validate contribution_date
    if (empty($_POST['contribution_date'])) {
        $errors[] = "Contribution date is required";
    }
    
    // Validate contribution_type
    if (empty($_POST['contribution_type'])) {
        $errors[] = "Contribution type is required";
    }
    
    // Validate payment_method
    if (empty($_POST['payment_method'])) {
        $errors[] = "Payment method is required";
    }
    
    // If no errors, process the contribution
    if (empty($errors)) {
        $contributionData = [
            'member_id' => $_POST['member_id'],
            'amount' => $_POST['amount'],
            'contribution_date' => $_POST['contribution_date'],
            'contribution_type' => $_POST['contribution_type'],
            'payment_method' => $_POST['payment_method'],
            'receipt_number' => $_POST['receipt_number'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        $result = $contributionController->addContribution($contributionData);
        
        if ($result) {
            $_SESSION['flash_message'] = "Contribution added successfully.";
            $_SESSION['flash_message_type'] = "success";
            header('Location: ' . BASE_URL . '/admin/contributions.php');
            exit;
        } else {
            $errors[] = "Failed to add contribution. Please try again.";
        }
    }
}

// Page title
$pageTitle = "Add New Contribution";

// Include header
include_once '../includes/header.php';
?>

<!-- Main Content -->
<div class="flex-1 ml-64 bg-gray-50">
    <div class="p-8">
        <!-- Page Heading -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Add New Contribution</h1>
                <p class="text-gray-600 mt-2">Create a new member contribution record</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/contributions.php" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors duration-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Contributions
            </a>
        </div>
        
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="<?php echo BASE_URL; ?>/admin/contributions.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">Contributions</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-sm font-medium text-gray-500">Add New Contribution</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Display Errors if any -->
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
                                    <li><?php echo htmlspecialchars($error); ?></li>
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

        <!-- Contribution Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Contribution Details</h3>
                <p class="text-sm text-gray-600 mt-1">Fill in the information below to add a new contribution</p>
            </div>
            <div class="p-6">
                <form method="POST" action="" id="contributionForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Member Selection -->
                        <div>
                            <label for="member_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Member <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="member_id" name="member_id" required>
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo htmlspecialchars($member['member_id']); ?>" 
                                        <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name'] . ' (' . $member['member_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Amount <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="amount" name="amount" 
                                       step="0.01" min="0.01" required 
                                       value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Contribution Date -->
                        <div>
                            <label for="contribution_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Contribution Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="contribution_date" name="contribution_date" 
                                   required value="<?php echo isset($_POST['contribution_date']) ? htmlspecialchars($_POST['contribution_date']) : date('Y-m-d'); ?>">
                        </div>
                        
                        <!-- Contribution Type -->
                        <div>
                            <label for="contribution_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Contribution Type <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="contribution_type" name="contribution_type" required>
                                <option value="">Select Type</option>
                                <?php foreach ($contributionTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo (isset($_POST['contribution_type']) && $_POST['contribution_type'] == $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Payment Method -->
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>" 
                                        <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == $method) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($method); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Receipt Number -->
                        <div>
                            <label for="receipt_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Receipt Number
                            </label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="receipt_number" name="receipt_number" 
                                   value="<?php echo isset($_POST['receipt_number']) ? htmlspecialchars($_POST['receipt_number']) : ''; ?>">
                        </div>
                        
                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes
                            </label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" id="notes" name="notes" rows="3" placeholder="Additional notes or comments..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="md:col-span-2 flex items-center justify-end space-x-4 pt-4">
                            <a href="<?php echo BASE_URL; ?>/admin/contributions.php" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 flex items-center">
                                <i class="fas fa-plus mr-2"></i> Add Contribution
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Form validation
    document.getElementById('contributionForm').addEventListener('submit', function(event) {
        let valid = true;
        const member = document.getElementById('member_id').value;
        const amount = document.getElementById('amount').value;
        const date = document.getElementById('contribution_date').value;
        const type = document.getElementById('contribution_type').value;
        const method = document.getElementById('payment_method').value;
        
        // Reset previous error messages
        document.querySelectorAll('.border-red-500').forEach(function(element) {
            element.classList.remove('border-red-500', 'ring-red-500');
            element.classList.add('border-gray-300');
        });
        
        // Validate member
        if (!member) {
            const memberField = document.getElementById('member_id');
            memberField.classList.remove('border-gray-300');
            memberField.classList.add('border-red-500', 'ring-red-500');
            valid = false;
        }
        
        // Validate amount
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            const amountField = document.getElementById('amount');
            amountField.classList.remove('border-gray-300');
            amountField.classList.add('border-red-500', 'ring-red-500');
            valid = false;
        }
        
        // Validate date
        if (!date) {
            const dateField = document.getElementById('contribution_date');
            dateField.classList.remove('border-gray-300');
            dateField.classList.add('border-red-500', 'ring-red-500');
            valid = false;
        }
        
        // Validate type
        if (!type) {
            const typeField = document.getElementById('contribution_type');
            typeField.classList.remove('border-gray-300');
            typeField.classList.add('border-red-500', 'ring-red-500');
            valid = false;
        }
        
        // Validate payment method
        if (!method) {
            const methodField = document.getElementById('payment_method');
            methodField.classList.remove('border-gray-300');
            methodField.classList.add('border-red-500', 'ring-red-500');
            valid = false;
        }
        
        if (!valid) {
            event.preventDefault();
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
