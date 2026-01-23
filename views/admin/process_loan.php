<?php
/**
 * Admin - Process Loan Application
 * 
 * This page allows administrators to approve, reject, or disburse loan applications.
 * It handles the workflow for loan status changes and related operations.
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

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Check if loan can be processed
$canBeApproved = ($loan['status'] === 'Pending');
$canBeRejected = ($loan['status'] === 'Pending');
$canBeDisbursed = ($loan['status'] === 'Approved');

if (!$canBeApproved && !$canBeRejected && !$canBeDisbursed) {
    $_SESSION['flash_message'] = "This loan cannot be processed in its current status: " . ucfirst($loan['status']);
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: view_loan.php?id=' . $loan_id);
    exit();
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate action
    if (empty($_POST['action'])) {
        $errors[] = "Action is required";
    } else {
        $action = $_POST['action'];
        
        // Validate action based on current loan status
        if (($action === 'approve' && !$canBeApproved) ||
            ($action === 'reject' && !$canBeRejected) ||
            ($action === 'disburse' && !$canBeDisbursed)) {
            $errors[] = "Invalid action for the current loan status";
        }
        
        // Additional validation for disbursement
        if ($action === 'disburse') {
            if (empty($_POST['disbursement_date'])) {
                $errors[] = "Disbursement date is required";
            }
            
            if (empty($_POST['payment_method']) || !in_array($_POST['payment_method'], $loanController->getPaymentMethods())) {
                $errors[] = "Valid payment method is required";
            }
        }
        
        // Process the action if no errors
        if (empty($errors)) {
            $result = false;
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            switch ($action) {
                case 'approve':
                    $result = $loanController->approveLoan($loan_id, $notes);
                    $successMessage = "Loan application approved successfully";
                    break;
                    
                case 'reject':
                    $result = $loanController->rejectLoan($loan_id, $notes);
                    $successMessage = "Loan application rejected successfully";
                    break;
                    
                case 'disburse':
                    $disbursementData = [
                        'disbursement_date' => $_POST['disbursement_date'],
                        'payment_method' => $_POST['payment_method'],
                        'notes' => $notes
                    ];
                    $result = $loanController->disburseLoan($loan_id, $disbursementData);
                    $successMessage = "Loan disbursed successfully";
                    break;
            }
            
            if ($result) {
                // Set success message and redirect
                $_SESSION['flash_message'] = $successMessage;
                $_SESSION['flash_message_class'] = "alert-success";
                header('Location: ' . BASE_URL . '/views/admin/loans.php?refresh=stats');
                exit();
            } else {
                $errors[] = "Failed to process loan. Please try again.";
            }
        }
    }
}

// Page title based on available actions
if ($canBeApproved || $canBeRejected) {
    $pageTitle = "Review Loan Application #" . $loan_id;
} else if ($canBeDisbursed) {
    $pageTitle = "Disburse Loan #" . $loan_id;
} else {
    $pageTitle = "Process Loan #" . $loan_id;
}

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
            <style>
                :root {
                    --primary-start: #4f46e5;
                    --primary-end: #0ea5e9;
                    --accent: #f97316;
                    --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
                    --text-primary: #0f172a;
                    --text-muted: #64748b;
                }
                .page-header-vibrant {
                    background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
                    color: #fff;
                    border-radius: 20px;
                    padding: 1.25rem 1.5rem;
                    margin: 0.5rem 0 1.25rem;
                    box-shadow: var(--card-shadow);
                    position: relative;
                    overflow: hidden;
                }
                .page-header-vibrant::after {
                    content: '';
                    position: absolute;
                    right: -60px; top: -60px;
                    width: 160px; height: 160px;
                    border-radius: 50%;
                    background: radial-gradient(circle, rgba(255,255,255,0.25), rgba(255,255,255,0));
                }
                .card { border-radius: 18px; box-shadow: var(--card-shadow); border: 1px solid rgba(15,23,42,0.06); }
                .card-header { background: linear-gradient(90deg, rgba(99,102,241,0.08), rgba(14,165,233,0.08)); border-bottom: 1px solid rgba(15,23,42,0.06); }
                .card-title { color: var(--text-primary); font-weight: 700; }
                .muted { color: var(--text-muted); }
                .btn-outline-secondary { border-radius: 12px; }
                .btn-outline-secondary:hover { background: rgba(255,255,255,0.2); color: #fff; border-color: #fff; }
                .action-group .btn { border-radius: 12px; padding: 0.6rem 0.9rem; font-weight: 600; }
                .btn-check + label.btn { transition: transform .15s ease, box-shadow .15s ease; }
                .btn-check:checked + label.btn { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
                .btn-outline-success { border-color: var(--success); color: var(--success); }
                .btn-outline-success:hover, .btn-check:checked + .btn-outline-success { background: linear-gradient(135deg, var(--success) 0%, var(--success) 100%); color:#fff; }
                .btn-outline-danger { border-color: #ef4444; color: #dc2626; }
                .btn-outline-danger:hover, .btn-check:checked + .btn-outline-danger { background: linear-gradient(135deg,#ef4444,#f97316); color:#fff; }
                .summary-badges .badge { background: rgba(99,102,241,0.08); color: var(--text-primary); border: 1px solid rgba(99,102,241,0.2); padding: .5rem .75rem; border-radius: 999px; font-weight: 600; }
                .summary-badges .badge i { color: var(--accent); }
            </style>

            <div class="page-header-vibrant d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(255,255,255,0.2);border-radius:12px;">
                        <i class="fas fa-clipboard-check" style="font-size:20px;color:#fff"></i>
                    </div>
                    <div>
                        <h1 class="h4 mb-1" style="font-weight:800;letter-spacing:.3px;">
                            <?php echo $pageTitle; ?>
                        </h1>
                        <div class="summary-badges d-flex flex-wrap gap-2">
                            <span class="badge"><i class="fas fa-money-bill-wave me-2"></i>₦<?php echo number_format($loan['amount'] ?? 0, 2); ?></span>
                            <span class="badge"><i class="fas fa-calendar-alt me-2"></i><?php echo (int)($loan['term'] ?? $loan['term_months'] ?? 0); ?> months</span>
                            <span class="badge"><i class="fas fa-percentage me-2"></i><?php echo number_format($loan['interest_rate'] ?? 0, 2); ?>%</span>
                        </div>
                    </div>
                </div>
                <div class="btn-toolbar mb-0">
                    <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Loan Details
                    </a>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/loans.php">Loans</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>">View Loan #<?php echo $loan_id; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Process</li>
                </ol>
            </nav>
            
            <!-- Error messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Member Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Member Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <?php 
                            $photoPath = realpath(__DIR__ . '/../../uploads/members/' . ($member['photo'] ?? ''));
                            if (!empty($member['photo']) && $photoPath && file_exists($photoPath)): 
                            ?>
                                <img src="<?php echo '../../uploads/members/' . $member['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                     class="img-fluid rounded-circle mb-2 shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center mx-auto mb-2 shadow-lg" 
                                     style="width: 80px; height: 80px;">
                                    <span class="text-white text-2xl font-bold">
                                        <?php echo strtoupper(substr($member['first_name'] ?? 'U', 0, 1) . substr($member['last_name'] ?? 'U', 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <h5><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                            <p class="mb-0">Member ID: <?php echo $member['member_id']; ?></p>
                            <p class="mb-0">Email: <?php echo htmlspecialchars($member['email']); ?></p>
                            <p class="mb-0">Phone: <?php echo htmlspecialchars($member['phone']); ?></p>
                            <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                View Full Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loan Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Loan Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Loan ID:</strong> <?php echo $loan['loan_id']; ?></p>
                            <p><strong>Amount:</strong> $<?php echo number_format($loan['amount'], 2); ?></p>
                            <p><strong>Term:</strong> <?php echo $loan['term']; ?> months</p>
                            <p><strong>Interest Rate:</strong> <?php echo $loan['interest_rate']; ?>%</p>
                            <p><strong>Monthly Payment:</strong> $<?php echo number_format($loanController->calculateMonthlyPayment($loan['amount'], $loan['interest_rate'], $loan['term']), 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo $loanController->getStatusBadgeClass($loan['status']); ?>"><?php echo ucfirst($loan['status']); ?></span></p>
                            <p><strong>Application Date:</strong> <?php echo date('F j, Y', strtotime($loan['application_date'])); ?></p>
                            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($loan['purpose']); ?></p>
                            <?php if (!empty($loan['collateral'])): ?>
                                <p><strong>Collateral:</strong> <?php echo htmlspecialchars($loan['collateral']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($loan['guarantor'])): ?>
                                <p><strong>Guarantor:</strong> <?php echo htmlspecialchars($loan['guarantor']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Process Loan Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Process Loan</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="processLoanForm" class="needs-validation" novalidate>
                        <?php if ($canBeApproved || $canBeRejected): ?>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Action</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php if ($canBeApproved): ?>
                                    <div class="relative">
                                        <input type="radio" name="action" id="action-approve" value="approve" class="peer sr-only" required>
                                        <label for="action-approve" class="flex flex-col items-center justify-center p-6 bg-white border-2 border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 transition-all duration-200 h-full user-select-none">
                                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 mb-3 text-xl">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <span class="font-bold text-lg">Approve Application</span>
                                            <span class="text-xs text-gray-500 mt-1 text-center">Grant the loan request</span>
                                        </label>
                                        <div class="absolute top-4 right-4 text-green-600 opacity-0 peer-checked:opacity-100 transition-opacity">
                                            <i class="fas fa-check-circle text-xl"></i>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($canBeRejected): ?>
                                    <div class="relative">
                                        <input type="radio" name="action" id="action-reject" value="reject" class="peer sr-only" required>
                                        <label for="action-reject" class="flex flex-col items-center justify-center p-6 bg-white border-2 border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700 transition-all duration-200 h-full user-select-none">
                                            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-600 mb-3 text-xl">
                                                <i class="fas fa-times"></i>
                                            </div>
                                            <span class="font-bold text-lg">Reject Application</span>
                                            <span class="text-xs text-gray-500 mt-1 text-center">Deny with a reason</span>
                                        </label>
                                        <div class="absolute top-4 right-4 text-red-600 opacity-0 peer-checked:opacity-100 transition-opacity">
                                            <i class="fas fa-check-circle text-xl"></i>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="invalid-feedback text-red-600 text-sm mt-2" id="action-feedback" style="display: none;">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Please select whether to approve or reject this loan.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($canBeDisbursed): ?>
                            <!-- Disbursement Form for Approved Loans -->
                            <input type="hidden" name="action" value="disburse">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="disbursement_date" class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="disbursement_date" name="disbursement_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">Please provide a disbursement date</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <?php foreach ($loanController->getPaymentMethods() as $method): ?>
                                            <option value="<?php echo $method; ?>"><?php echo ucfirst($method); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a payment method</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Notes Field for All Actions -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any additional notes or comments about this action"></textarea>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary" id="submit-btn" style="border-radius:12px;box-shadow:var(--card-shadow);">
                                    <?php if ($canBeApproved || $canBeRejected): ?>
                                        Process Loan
                                    <?php elseif ($canBeDisbursed): ?>
                                        Disburse Loan
                                    <?php endif; ?>
                                </button>
                                <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-secondary" style="border-radius:12px;">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('processLoanForm');
        
        form.addEventListener('submit', function(event) {
            // Check if action is selected when required
            const actionRadios = document.querySelectorAll('input[name="action"]');
            const actionFeedback = document.getElementById('action-feedback');
            
            if (actionRadios.length > 1) {
                let actionSelected = false;
                actionRadios.forEach(radio => {
                    if (radio.checked) actionSelected = true;
                });
                
                if (!actionSelected) {
                    event.preventDefault();
                    event.stopPropagation();
                    actionFeedback.style.display = 'block';
                } else {
                    actionFeedback.style.display = 'none';
                }
            }
            
            // Regular form validation
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
        
        // Update submit button text based on selected action
        const actionRadios = document.querySelectorAll('input[name="action"]');
        const submitBtn = document.getElementById('submit-btn');
        
        actionRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Reset base classes
                submitBtn.className = 'btn px-6 py-2.5 rounded-xl font-bold text-white shadow-lg transition-all transform hover:-translate-y-1';
                
                if (this.value === 'approve') {
                    submitBtn.textContent = 'Confirm Approval';
                    submitBtn.classList.add('bg-gradient-to-r', 'from-green-500', 'to-green-600', 'hover:from-green-600', 'hover:to-green-700', 'shadow-green-500/30');
                } else if (this.value === 'reject') {
                    submitBtn.textContent = 'Confirm Rejection';
                    submitBtn.classList.add('bg-gradient-to-r', 'from-red-500', 'to-red-600', 'hover:from-red-600', 'hover:to-red-700', 'shadow-red-500/30');
                }
            });
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
