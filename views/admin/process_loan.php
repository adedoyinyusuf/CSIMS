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
$canEdit = ($loan['status'] === 'Pending'); // Only pending loans can be edited

if (!$canBeApproved && !$canBeRejected && !$canBeDisbursed) {
    // If it's just a view-only state (e.g., Disbursed/Paid/Rejected), we might still want to show the details,
    // but the form processing part below handles the redirect if logic requires.
    // However, existing logic redirected. We'll keep the logic but relax it if we want to just "View" history here?
    // For now, keeping original logic: redirect if no action possible.
    // actually, let's allow viewing for historical purposes if we want, or redirect to view_loan?
    // The original code redirected to view_loan.php. Let's keep that behavior for consistency.
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
    $pageTitle = "Review Application";
} else if ($canBeDisbursed) {
    $pageTitle = "Disburse Loan";
} else {
    $pageTitle = "Process Loan";
}

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid bg-gray-50 min-vh-100">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-0 main-content">
            <!-- Tailwind CSS -->
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    prefix: '',
                    important: true, 
                    theme: {
                        extend: {
                            colors: {
                                primary: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
                                success: '#22c55e',
                                danger: '#ef4444',
                                warning: '#f59e0b'
                            }
                        }
                    }
                }
            </script>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <!-- Header Section -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                            <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="hover:text-primary-600 transition-colors">Loans</a>
                            <i class="fas fa-chevron-right text-xs"></i>
                            <span class="text-gray-900 font-medium">Loan #<?php echo $loan['loan_id']; ?></span>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo $pageTitle; ?></h1>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan_id; ?>" 
                           class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        
                        <?php if ($canEdit): ?>
                            <a href="<?php echo BASE_URL; ?>/views/admin/edit_loan.php?id=<?php echo $loan_id; ?>" 
                               class="inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                                <i class="fas fa-edit mr-2"></i> Edit Application
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left Column: Loan Info & Actions (2/3 width) -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Loan Details Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-file-invoice-dollar text-primary-500"></i>
                                    Loan Details
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $loanController->getStatusBadgeClass($loan['status']); ?>-100 text-<?php echo $loanController->getStatusBadgeClass($loan['status']); ?>-800">
                                    <?php echo ucfirst($loan['status']); ?>
                                </span>
                            </div>
                            
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Requested Amount</p>
                                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($loan['amount'], 2); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Monthly Payment</p>
                                        <div class="flex items-baseline gap-2">
                                            <p class="text-xl font-semibold text-gray-900">₦<?php echo number_format($loanController->calculateMonthlyPayment($loan['amount'], $loan['interest_rate'], $loan['term']), 2); ?></p>
                                            <span class="text-sm text-gray-400">/ month</span>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-100 pt-4 col-span-1 md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Term</p>
                                            <p class="font-medium text-gray-900"><?php echo $loan['term']; ?> months</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Interest</p>
                                            <p class="font-medium text-gray-900"><?php echo $loan['interest_rate']; ?>%</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Date</p>
                                            <p class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($loan['application_date'])); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Type</p>
                                            <p class="font-medium text-gray-900">Personal</p>
                                        </div>
                                    </div>
                                    
                                    <div class="col-span-1 md:col-span-2 bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Purpose</p>
                                        <p class="text-gray-700 text-sm leading-relaxed">
                                            <?php echo !empty($loan['purpose']) ? nl2br(htmlspecialchars($loan['purpose'])) : '<span class="italic text-gray-400">No purpose stated</span>'; ?>
                                        </p>
                                    </div>

                                    <?php if (!empty($loan['collateral'])): ?>
                                    <div class="col-span-1 border-t pt-4">
                                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Collateral</p>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($loan['collateral']); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($loan['guarantor'])): ?>
                                    <div class="col-span-1 border-t pt-4">
                                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Guarantor</p>
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($loan['guarantor']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Card -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                                <h3 class="text-lg font-semibold text-gray-900">Process Application</h3>
                            </div>
                            
                            <div class="p-6">
                                <form action="" method="POST" id="processLoanForm" class="needs-validation" novalidate>
                                    
                                    <?php if ($canBeApproved || $canBeRejected): ?>
                                        <div class="mb-8">
                                            <label class="block text-sm font-medium text-gray-700 mb-4">Select Decision</label>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <?php if ($canBeApproved): ?>
                                                <div class="relative">
                                                    <input type="radio" name="action" id="action-approve" value="approve" class="peer sr-only" required>
                                                    <label for="action-approve" class="flex flex-col items-center justify-center p-6 bg-white border-2 border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-200 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 transition-all duration-200 h-full">
                                                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 mb-3 shadow-sm">
                                                            <i class="fas fa-check text-xl"></i>
                                                        </div>
                                                        <span class="font-bold text-lg">Approve</span>
                                                        <span class="text-xs text-gray-500 mt-1">Grant the loan</span>
                                                    </label>
                                                    <div class="absolute top-4 right-4 text-green-600 opacity-0 peer-checked:opacity-100 transition-opacity">
                                                        <i class="fas fa-check-circle"></i>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($canBeRejected): ?>
                                                <div class="relative">
                                                    <input type="radio" name="action" id="action-reject" value="reject" class="peer sr-only" required>
                                                    <label for="action-reject" class="flex flex-col items-center justify-center p-6 bg-white border-2 border-gray-200 rounded-xl cursor-pointer hover:bg-red-50 hover:border-red-200 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700 transition-all duration-200 h-full">
                                                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-600 mb-3 shadow-sm">
                                                            <i class="fas fa-times text-xl"></i>
                                                        </div>
                                                        <span class="font-bold text-lg">Reject</span>
                                                        <span class="text-xs text-gray-500 mt-1">Deny application</span>
                                                    </label>
                                                    <div class="absolute top-4 right-4 text-red-600 opacity-0 peer-checked:opacity-100 transition-opacity">
                                                        <i class="fas fa-check-circle"></i>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="hidden text-red-600 text-sm mt-2" id="action-feedback">
                                                Please select an action.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($canBeDisbursed): ?>
                                        <input type="hidden" name="action" value="disburse">
                                        <div class="bg-blue-50 rounded-lg p-5 mb-6 border border-blue-100">
                                            <h4 class="font-medium text-blue-900 mb-4 flex items-center">
                                                <i class="fas fa-money-bill-wave mr-2"></i> Disbursement Details
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="disbursement_date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                                                    <input type="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                                           id="disbursement_date" name="disbursement_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div>
                                                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Method</label>
                                                    <select class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                                            id="payment_method" name="payment_method" required>
                                                        <option value="">Select Method</option>
                                                        <?php foreach ($loanController->getPaymentMethods() as $method): ?>
                                                            <option value="<?php echo $method; ?>"><?php echo ucfirst($method); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-6">
                                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                            Admin Notes <span class="text-gray-400 font-normal">(Optional)</span>
                                        </label>
                                        <textarea class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                                  id="notes" name="notes" rows="3" 
                                                  placeholder="Add any internal comments about this decision..."></textarea>
                                    </div>
                                    
                                    <button type="submit" id="submit-btn" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <?php if ($canBeDisbursed) echo 'Confirm Disbursement'; else echo 'Confirm Selection'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Right Column: Member Profile (1/3 width, View Only) -->
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
                                    <span class="absolute bottom-1 right-1 w-5 h-5 bg-green-500 border-2 border-white rounded-full"></span>
                                </div>
                                
                                <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')); ?></h2>
                                <p class="text-sm text-gray-500 mt-1">Member ID: <span class="font-mono text-gray-700"><?php echo $member['member_id']; ?></span></p>
                                
                                <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" 
                                   class="mt-4 text-sm text-primary-600 hover:text-primary-800 font-medium inline-flex items-center">
                                    View Full Profile <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                </a>
                            </div>
                            
                            <div class="p-4 bg-gray-50/50">
                                <div class="space-y-3">
                                    <div class="flex items-center text-sm">
                                        <div class="w-8 flex justify-center text-gray-400 mr-2"><i class="fas fa-envelope"></i></div>
                                        <div class="truncate text-gray-600" title="<?php echo htmlspecialchars($member['email'] ?? ''); ?>"><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="flex items-center text-sm">
                                        <div class="w-8 flex justify-center text-gray-400 mr-2"><i class="fas fa-phone"></i></div>
                                        <div class="text-gray-600"><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="flex items-center text-sm">
                                        <div class="w-8 flex justify-center text-gray-400 mr-2"><i class="fas fa-wallet"></i></div>
                                        <div class="text-gray-600">Savings: <span class="font-semibold text-green-600">₦<?php echo number_format($member['savings_balance'] ?? 0, 2); ?></span></div>
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
    document.addEventListener('DOMContentLoaded', function() {
        const submitBtn = document.getElementById('submit-btn');
        const actionRadios = document.querySelectorAll('input[name="action"]');
        const actionFeedback = document.getElementById('action-feedback');
        
        // Dynamic Button Styling
        actionRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (actionFeedback) actionFeedback.style.display = 'none';
                
                if (this.value === 'approve') {
                    submitBtn.textContent = 'Approve Application';
                    submitBtn.className = 'w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all transform active:scale-95';
                } else if (this.value === 'reject') {
                    submitBtn.textContent = 'Reject Application';
                    submitBtn.className = 'w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all transform active:scale-95';
                }
            });
        });

        // Form Validation
        const form = document.getElementById('processLoanForm');
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check radio button requirement if they exist/visible
            if (actionRadios.length > 0) {
                let checked = false;
                actionRadios.forEach(r => { if(r.checked) checked = true; });
                if (!checked) {
                    isValid = false;
                    if (actionFeedback) {
                        actionFeedback.style.display = 'block';
                        actionFeedback.classList.remove('hidden');
                    }
                }
            }

            if (!isValid || !form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
