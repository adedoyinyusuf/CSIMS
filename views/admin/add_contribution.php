<?php
/**
 * Add Contribution Page
 * 
 * This page provides a form to add a new contribution record.
 */

// Include required files
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';
require_once '../controllers/contribution_controller.php';
require_once '../controllers/member_controller.php';

// Initialize controllers
$authController = new AuthController();
$contributionController = new ContributionController();
$memberController = new MemberController();

// Check if user is logged in
if (!$authController->isLoggedIn()) {
    header('Location: <?php echo BASE_URL; ?>/index.php');
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
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add New Contribution</h1>
        <a href="<?php echo BASE_URL; ?>/admin/contributions.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Contributions
        </a>
    </div>
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/contributions.php">Contributions</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add New Contribution</li>
        </ol>
    </nav>

    <!-- Display Errors if any -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Contribution Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contribution Details</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="contributionForm">
                <div class="row">
                    <!-- Member Selection -->
                    <div class="col-md-6 mb-3">
                        <label for="member_id">Member <span class="text-danger">*</span></label>
                        <select class="form-control" id="member_id" name="member_id" required>
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
                    <div class="col-md-6 mb-3">
                        <label for="amount">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0.01" required 
                                   value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Contribution Date -->
                    <div class="col-md-6 mb-3">
                        <label for="contribution_date">Contribution Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="contribution_date" name="contribution_date" 
                               required value="<?php echo isset($_POST['contribution_date']) ? htmlspecialchars($_POST['contribution_date']) : date('Y-m-d'); ?>">
                    </div>
                    
                    <!-- Contribution Type -->
                    <div class="col-md-6 mb-3">
                        <label for="contribution_type">Contribution Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="contribution_type" name="contribution_type" required>
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
                    <div class="col-md-6 mb-3">
                        <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
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
                    <div class="col-md-6 mb-3">
                        <label for="receipt_number">Receipt Number</label>
                        <input type="text" class="form-control" id="receipt_number" name="receipt_number" 
                               value="<?php echo isset($_POST['receipt_number']) ? htmlspecialchars($_POST['receipt_number']) : ''; ?>">
                    </div>
                    
                    <!-- Notes -->
                    <div class="col-md-12 mb-3">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Add Contribution</button>
                        <a href="<?php echo BASE_URL; ?>/admin/contributions.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
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
        document.querySelectorAll('.is-invalid').forEach(function(element) {
            element.classList.remove('is-invalid');
        });
        
        // Validate member
        if (!member) {
            document.getElementById('member_id').classList.add('is-invalid');
            valid = false;
        }
        
        // Validate amount
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            document.getElementById('amount').classList.add('is-invalid');
            valid = false;
        }
        
        // Validate date
        if (!date) {
            document.getElementById('contribution_date').classList.add('is-invalid');
            valid = false;
        }
        
        // Validate type
        if (!type) {
            document.getElementById('contribution_type').classList.add('is-invalid');
            valid = false;
        }
        
        // Validate payment method
        if (!method) {
            document.getElementById('payment_method').classList.add('is-invalid');
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
