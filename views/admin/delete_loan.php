<?php
/**
 * Admin - Delete Loan
 * 
 * This page handles the deletion of loan applications.
 * Only pending or rejected loans can be deleted.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../controllers/loan_controller.php';
require_once __DIR__ . '/../../controllers/member_controller.php';

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

// Only pending or rejected loans can be deleted
if (!in_array($loan['status'], ['pending', 'rejected'])) {
    $_SESSION['flash_message'] = "Only pending or rejected loans can be deleted";
    $_SESSION['flash_message_class'] = "alert-danger";
    header('Location: view_loan.php?id=' . $loan_id);
    exit();
}

// Get member details
$member = $memberController->getMemberById($loan['member_id']);

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Attempt to delete the loan
    $result = $loanController->deleteLoan($loan_id);
    
    if ($result) {
        // Set success message and redirect
        $_SESSION['flash_message'] = "Loan application deleted successfully";
        $_SESSION['flash_message_class'] = "alert-success";
        header('Location: loans.php');
        exit();
    } else {
        $errors[] = "Failed to delete loan application. Please try again.";
    }
}

// Page title
$pageTitle = "Delete Loan Application #" . $loan_id;

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Loan Details
                    </a>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="loans.php">Loans</a></li>
                    <li class="breadcrumb-item"><a href="view_loan.php?id=<?php echo $loan_id; ?>">View Loan #<?php echo $loan_id; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Delete</li>
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
            
            <!-- Delete Confirmation Card -->
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Confirm Deletion</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. Are you sure you want to delete this loan application?
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Loan Details</h5>
                            <p><strong>Loan ID:</strong> <?php echo $loan['loan_id']; ?></p>
                            <p><strong>Amount:</strong> $<?php echo number_format($loan['amount'], 2); ?></p>
                            <p><strong>Term:</strong> <?php echo $loan['term']; ?> months</p>
                            <p><strong>Interest Rate:</strong> <?php echo $loan['interest_rate']; ?>%</p>
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo $loanController->getStatusBadgeClass($loan['status']); ?>"><?php echo ucfirst($loan['status']); ?></span></p>
                            <p><strong>Application Date:</strong> <?php echo date('F j, Y', strtotime($loan['application_date'])); ?></p>
                            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($loan['purpose']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Member Information</h5>
                            <div class="d-flex align-items-center mb-3">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo '../../uploads/members/' . $member['photo']; ?>" 
                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                         class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" 
                                         style="width: 60px; height: 60px;">
                                        <span class="text-white fs-4">
                                            <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h6>
                                    <p class="mb-0 text-muted">Member ID: <?php echo $member['member_id']; ?></p>
                                </div>
                            </div>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone']); ?></p>
                            <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary">
                                View Full Profile
                            </a>
                        </div>
                    </div>
                    
                    <form action="" method="POST">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" value="1" required>
                            <label class="form-check-label" for="confirm_delete">
                                I confirm that I want to permanently delete this loan application
                            </label>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger" id="delete-btn" disabled>
                                <i class="bi bi-trash"></i> Delete Loan Application
                            </button>
                            <a href="view_loan.php?id=<?php echo $loan_id; ?>" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enable/disable delete button based on checkbox
        const confirmCheckbox = document.getElementById('confirm_delete');
        const deleteButton = document.getElementById('delete-btn');
        
        confirmCheckbox.addEventListener('change', function() {
            deleteButton.disabled = !this.checked;
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>