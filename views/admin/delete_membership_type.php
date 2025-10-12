<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/membership_controller.php';

$auth = new AuthController();
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    header('Location: ../auth/login.php');
    exit();
}

$membershipController = new MembershipController();

// Get membership type ID from URL
$membership_type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($membership_type_id <= 0) {
    header('Location: memberships.php');
    exit();
}

// Get membership type data
$membership_type = $membershipController->getMembershipTypeById($membership_type_id);

if (!$membership_type) {
    header('Location: memberships.php');
    exit();
}

$errors = [];
$success_message = '';

// Check if membership type can be deleted
if ($membership_type['member_count'] > 0) {
    $errors[] = 'Cannot delete this membership type because it has active members.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($errors)) {
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    
    if ($confirm === 'DELETE') {
        $result = $membershipController->deleteMembershipType($membership_type_id);
        
        if ($result) {
            // Set success message in session and redirect
            session_start();
            $_SESSION['success_message'] = 'Membership type "' . $membership_type['name'] . '" has been deleted successfully.';
            header('Location: memberships.php');
            exit();
        } else {
            $errors[] = 'Failed to delete membership type. Please try again.';
        }
    } else {
        $errors[] = 'Please type "DELETE" to confirm deletion.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Membership Type - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 text-danger">Delete Membership Type</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="view_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                               class="btn btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                               class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                        <a href="memberships.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Memberships
                        </a>
                    </div>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Cannot Delete:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Danger Alert -->
                        <div class="alert alert-danger" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                <div>
                                    <h5 class="alert-heading mb-2">Danger Zone!</h5>
                                    <p class="mb-0">
                                        You are about to permanently delete the membership type 
                                        <strong>"<?php echo htmlspecialchars($membership_type['name']); ?>"</strong>. 
                                        This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Membership Type Information -->
                        <div class="card mb-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-trash"></i> 
                                    Membership Type to be Deleted
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Name:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <?php echo htmlspecialchars($membership_type['name']); ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Duration:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <?php echo $membership_type['duration']; ?> months
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Fee:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        ₦<?php echo number_format($membership_type['fee'], 2); ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Active Members:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-<?php echo $membership_type['member_count'] > 0 ? 'danger' : 'success'; ?>">
                                            <?php echo $membership_type['member_count']; ?> members
                                        </span>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Created:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <?php echo date('M d, Y', strtotime($membership_type['created_at'])); ?>
                                    </div>
                                </div>

                                <?php if (!empty($membership_type['description'])): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Description:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <?php echo nl2br(htmlspecialchars($membership_type['description'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Deletion Confirmation Form -->
                        <?php if ($membership_type['member_count'] == 0): ?>
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">Confirm Deletion</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-danger">
                                        <strong>Warning:</strong> This action will permanently delete this membership type and cannot be undone.
                                    </p>
                                    
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="confirm" class="form-label">
                                                Type <strong>DELETE</strong> to confirm:
                                            </label>
                                            <input type="text" class="form-control" id="confirm" name="confirm" 
                                                   placeholder="Type DELETE to confirm" required>
                                            <div class="form-text">This field is case-sensitive.</div>
                                        </div>

                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <a href="memberships.php" class="btn btn-secondary me-md-2">Cancel</a>
                                            <a href="view_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" class="btn btn-info me-md-2">View Details</a>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> Delete Permanently
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <!-- Deletion Status -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Deletion Status</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($membership_type['member_count'] > 0): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-times-circle"></i>
                                        <strong>Cannot Delete</strong><br>
                                        This membership type has <?php echo $membership_type['member_count']; ?> active members.
                                    </div>
                                    <p class="text-muted small">
                                        To delete this membership type, you must first remove or reassign all active members.
                                    </p>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Ready for Deletion</strong><br>
                                        This membership type has no active members and can be safely deleted.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Alternative Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Alternative Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="view_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                                       class="btn btn-outline-info">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <a href="edit_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                                       class="btn btn-outline-warning">
                                        <i class="fas fa-edit"></i> Edit Instead
                                    </a>
                                    <a href="memberships.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-list"></i> Back to List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and confirmation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const confirmInput = document.getElementById('confirm');
            if (confirmInput && confirmInput.value !== 'DELETE') {
                e.preventDefault();
                alert('Please type "DELETE" exactly as shown to confirm deletion.');
                confirmInput.focus();
                return false;
            }
            
            // Final confirmation dialog
            if (!confirm('Are you absolutely sure you want to delete this membership type? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-focus the confirm input
        document.addEventListener('DOMContentLoaded', function() {
            const confirmInput = document.getElementById('confirm');
            if (confirmInput) {
                confirmInput.focus();
            }
        });
    </script>
</body>
</html>