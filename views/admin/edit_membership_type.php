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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $duration = (int)$_POST['duration'];
    $fee = (float)$_POST['fee'];
    $benefits = trim($_POST['benefits']);
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Membership type name is required';
    }
    
    if (empty($duration) || $duration <= 0) {
        $errors[] = 'Duration must be a positive number';
    }
    
    if (empty($fee) || $fee < 0) {
        $errors[] = 'Fee must be a non-negative number';
    }
    
    // If no validation errors, update the membership type
    if (empty($errors)) {
        $data = [
            'name' => $name,
            'description' => $description,
            'duration' => $duration,
            'fee' => $fee,
            'benefits' => $benefits
        ];
        
        $result = $membershipController->updateMembershipType($membership_type_id, $data);
        
        if ($result) {
            $success_message = 'Membership type updated successfully!';
            // Refresh membership type data
            $membership_type = $membershipController->getMembershipTypeById($membership_type_id);
        } else {
            $errors[] = 'Failed to update membership type. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Membership Type - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Membership Type</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="view_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                               class="btn btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                        <a href="memberships.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Memberships
                        </a>
                    </div>
                </div>

                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Warning for Active Members -->
                <?php if ($membership_type['member_count'] > 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This membership type has <?php echo $membership_type['member_count']; ?> active members. 
                        Changes to fee structure may affect existing memberships.
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Edit Membership Type Form -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Edit Membership Type Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Membership Type Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($membership_type['name']); ?>" 
                                                   required>
                                            <div class="form-text">Enter a unique name for this membership type</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="duration" class="form-label">Duration (Months) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="duration" name="duration" 
                                                   value="<?php echo isset($_POST['duration']) ? $_POST['duration'] : $membership_type['duration']; ?>" 
                                                   min="1" max="60" required>
                                            <div class="form-text">Duration in months (1-60)</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fee" class="form-label">Membership Fee (₦) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="fee" name="fee" 
                                                   value="<?php echo isset($_POST['fee']) ? $_POST['fee'] : $membership_type['fee']; ?>" 
                                                   min="0" step="0.01" required>
                                            <div class="form-text">Annual membership fee in Naira</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="monthly_contribution" class="form-label">Monthly Savings Requirement (₦)</label>
                                            <input type="number" class="form-control" id="monthly_contribution" name="monthly_contribution" 
                                                   value="<?php echo isset($_POST['monthly_contribution']) ? $_POST['monthly_contribution'] : (isset($membership_type['monthly_contribution']) ? $membership_type['monthly_contribution'] : '0.00'); ?>" 
                                                   min="0" step="0.01">
                                            <div class="form-text">Optional monthly savings requirement</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($membership_type['description']); ?></textarea>
                                        <div class="form-text">Brief description of this membership type</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="benefits" class="form-label">Benefits</label>
                                        <textarea class="form-control" id="benefits" name="benefits" rows="4"><?php echo isset($_POST['benefits']) ? htmlspecialchars($_POST['benefits']) : htmlspecialchars($membership_type['benefits']); ?></textarea>
                                        <div class="form-text">List the benefits and privileges of this membership type</div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="memberships.php" class="btn btn-secondary me-md-2">Cancel</a>
                                        <a href="view_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" class="btn btn-info me-md-2">View</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Membership Type
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Current Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Current Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Type ID:</strong> #<?php echo $membership_type['membership_type_id']; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Active Members:</strong> <?php echo $membership_type['member_count']; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Created:</strong> <?php echo date('M d, Y', strtotime($membership_type['created_at'])); ?>
                                </div>
                                <?php if (isset($membership_type['updated_at'])): ?>
                                <div class="mb-2">
                                    <strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($membership_type['updated_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="view_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                                       class="btn btn-outline-info">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($membership_type['member_count'] == 0): ?>
                                        <a href="delete_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this membership type?')">
                                            <i class="fas fa-trash"></i> Delete Type
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled>
                                            <i class="fas fa-trash"></i> Cannot Delete (Has Members)
                                        </button>
                                    <?php endif; ?>
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
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const duration = parseInt(document.getElementById('duration').value);
            const fee = parseFloat(document.getElementById('fee').value);
            
            let errors = [];
            
            if (!name) {
                errors.push('Membership type name is required');
            }
            
            if (!duration || duration <= 0) {
                errors.push('Duration must be a positive number');
            }
            
            if (fee < 0) {
                errors.push('Fee cannot be negative');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n' + errors.join('\n'));
            }
        });
    </script>
</body>
</html>