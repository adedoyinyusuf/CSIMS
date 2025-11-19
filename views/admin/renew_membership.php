<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/session.php';
$session = Session::getInstance();

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $session->setFlash('error', 'Member ID is required');
    header("Location: members.php");
    exit();
}

$member_id = (int)$_GET['id'];

// Initialize member controller
$memberController = new MemberController();

// Get member details
$member = $memberController->getMemberById($member_id);

if (!$member) {
    $session->setFlash('error', 'Member not found');
    header("Location: members.php");
    exit();
}

// Get membership types
$membership_types = $memberController->getMembershipTypes();

// Calculate current expiry date
$current_expiry = new DateTime($member['expiry_date']);
$today = new DateTime();
$is_expired = $today > $current_expiry;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $errors = [];
    
    // Required fields
    if (empty($_POST['membership_type'])) {
        $errors[] = 'Membership type is required';
    }
    
    if (empty($_POST['payment_amount'])) {
        $errors[] = 'Payment amount is required';
    } elseif (!is_numeric($_POST['payment_amount']) || $_POST['payment_amount'] <= 0) {
        $errors[] = 'Payment amount must be a positive number';
    }
    
    if (empty($_POST['payment_method'])) {
        $errors[] = 'Payment method is required';
    }
    
    // If no errors, renew membership
    if (empty($errors)) {
        $renewal_data = [
            'membership_type_id' => $_POST['membership_type'],
            'payment_amount' => $_POST['payment_amount'],
            'payment_method' => $_POST['payment_method'],
            'receipt_number' => $_POST['receipt_number'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        if ($memberController->renewMembership($member_id, $renewal_data)) {
            $session->setFlash('success', 'Membership renewed successfully');
            header("Location: view_member.php?id=$member_id");
            exit();
        } else {
            $session->setFlash('error', 'Failed to renew membership');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Membership - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    
</head>
<body>
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
                        <li class="breadcrumb-item"><a href="view_member.php?id=<?php echo $member_id; ?>">View Member</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Renew Membership</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Renew Membership</h1>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($session->hasFlash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('success'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($session->hasFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('error'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-sync-alt me-2"></i> Renew Membership for <?php echo $member['first_name'] . ' ' . $member['last_name']; ?></h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="POST" class="needs-validation" novalidate>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Current Membership Type</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['membership_type']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Current Expiry Date</label>
                                            <input type="text" class="form-control <?php echo $is_expired ? 'text-danger' : ''; ?>" value="<?php echo date('M d, Y', strtotime($member['expiry_date'])); ?> <?php echo $is_expired ? '(Expired)' : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="membership_type" class="form-label">New Membership Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="membership_type" name="membership_type" required>
                                            <option value="">Select Membership Type</option>
                                            <?php foreach ($membership_types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>" data-fee="<?php echo $type['fee']; ?>" data-duration="<?php echo $type['duration']; ?>" <?php echo ($member['membership_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type['name']); ?> (<?php echo htmlspecialchars($type['duration']); ?> months) - <?php echo Utilities::formatCurrency($type['fee']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a membership type</div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="payment_amount" class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                                <input type="number" step="0.01" min="0" class="form-control" id="payment_amount" name="payment_amount" required>
                                            </div>
                                            <div class="invalid-feedback">Please enter a valid payment amount</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select Payment Method</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Check">Check</option>
                                                <option value="Mobile Money">Mobile Money</option>
                                                <option value="Credit Card">Credit Card</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <div class="invalid-feedback">Please select a payment method</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="receipt_number" class="form-label">Receipt Number</label>
                                        <input type="text" class="form-control" id="receipt_number" name="receipt_number">
                                        <small class="form-text text-muted">Optional: Enter receipt or transaction reference number</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_expiry_date" class="form-label">New Expiry Date (Preview)</label>
                                        <input type="text" class="form-control" id="new_expiry_date" readonly>
                                        <small class="form-text text-muted">This date will be calculated automatically based on the selected membership type</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                        <small class="form-text text-muted">Optional: Add any additional notes about this renewal</small>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="view_member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Renew Membership</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i> Member Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if (!empty($member['photo'])): ?>
                                        <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" alt="Profile" class="img-thumbnail" style="max-width: 120px;">
                                    <?php else: ?>
                                        <div class="profile-img bg-secondary d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; margin: 0 auto;">
                                            <i class="fas fa-user fa-4x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <table class="table table-hover">
                                    <tbody>
                                        <tr>
                                            <th width="40%">Member ID</th>
                                            <td><?php echo $member['member_id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Name</th>
                                            <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td><?php echo $member['email']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td><?php echo $member['phone']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Join Date</th>
                                            <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <?php if ($member['status'] == 'Active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($member['status'] == 'Inactive'): ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i> Renewal Information</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">When you renew a membership:</p>
                                <ul>
                                    <li>The new expiry date will be calculated from:</li>
                                    <ul>
                                        <li>Current expiry date if membership is still active</li>
                                        <li>Today's date if membership has expired</li>
                                    </ul>
                                    <li>The system will automatically record the renewal transaction</li>
                                    <li>The member's status will be updated to 'Active'</li>
                                </ul>
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="fas fa-lightbulb me-2"></i> Tip: You can view a member's renewal history in their profile under the Savings tab.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Include Footer -->
                <?php include '../../views/includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            
            // Fetch all forms we want to apply validation to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Calculate new expiry date based on selected membership type
        document.getElementById('membership_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const duration = selectedOption.getAttribute('data-duration');
            const fee = selectedOption.getAttribute('data-fee');
            
            if (duration && fee) {
                // Set the payment amount to the membership fee
                document.getElementById('payment_amount').value = fee;
                
                // Calculate new expiry date
                const currentExpiry = new Date('<?php echo $member['expiry_date']; ?>');
                const today = new Date();
                
                // Start from current expiry if not expired, otherwise start from today
                const startDate = <?php echo $is_expired ? 'today' : 'currentExpiry'; ?>;
                
                // Add months to the start date
                const newExpiry = new Date(startDate);
                newExpiry.setMonth(newExpiry.getMonth() + parseInt(duration));
                
                // Format the date for display
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                document.getElementById('new_expiry_date').value = newExpiry.toLocaleDateString('en-US', options);
            } else {
                document.getElementById('payment_amount').value = '';
                document.getElementById('new_expiry_date').value = '';
            }
        });
        
        // Trigger the change event to calculate initial values
        document.getElementById('membership_type').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
