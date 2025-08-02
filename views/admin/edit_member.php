<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: <?php echo BASE_URL; ?>/index.php");
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $errors = [];
    
    // Required fields
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'gender', 'membership_type'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Email validation
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if email already exists (excluding current member)
    if (!empty($_POST['email']) && $memberController->isEmailTaken($_POST['email'], $member_id)) {
        $errors[] = 'Email is already taken by another member';
    }
    
    // Phone validation (basic)
    if (!empty($_POST['phone']) && !preg_match('/^[0-9\+\-\(\)\s]{10,15}$/', $_POST['phone'])) {
        $errors[] = 'Invalid phone number format';
    }
    
    // Date validation
    if (!empty($_POST['date_of_birth']) && !Utilities::validateDate($_POST['date_of_birth'])) {
        $errors[] = 'Invalid date of birth';
    }
    
    // Photo upload handling        

    
    $photo = $member['photo']; // Keep existing photo by default
    
    if (isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
        $upload_dir = '../../assets/images/members/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed for photo';
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = 'Photo size should not exceed 2MB';
        } else {
            // Generate unique filename
            $new_filename = 'member_' . $member_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Delete old photo if exists and not default
                if (!empty($member['photo']) && file_exists($upload_dir . $member['photo'])) {
                    unlink($upload_dir . $member['photo']);
                }
                
                $photo = $new_filename;
            } else {
                $errors[] = 'Failed to upload photo';
            }
        }
    }
    
    // If no errors, update member
    if (empty($errors)) {
        $member_data = [
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'gender' => $_POST['gender'],
            'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'state' => $_POST['state'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? '',
            'country' => $_POST['country'] ?? '',
            'occupation' => $_POST['occupation'] ?? '',
            'membership_type_id' => $_POST['membership_type'],
            'status' => $_POST['status'],
            'notes' => $_POST['notes'] ?? '',
            'photo' => $photo
        ];
        
        if ($memberController->updateMember($member_id, $member_data)) {
            $session->setFlash('success', 'Member updated successfully');
            header("Location: view_member.php?id=$member_id");
            exit();
        } else {
            $session->setFlash('error', 'Failed to update member');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/members.php">Members</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>">View Member</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Member</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Member</h1>
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
                
                <div class="card">
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h4 class="mb-3">Personal Information</h4>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                                            <div class="invalid-feedback">First name is required</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                                            <div class="invalid-feedback">Last name is required</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($member['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($member['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($member['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a gender</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($member['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="occupation" class="form-label">Occupation</label>
                                        <input type="text" class="form-control" id="occupation" name="occupation" value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="photo" class="form-label">Photo</label>
                                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                        <small class="form-text text-muted">Upload a new photo (JPG, PNG, GIF, max 2MB) or leave blank to keep current photo</small>
                                        
                                        <?php if (!empty($member['photo'])): ?>
                                            <div class="mt-2">
                                                <label>Current Photo:</label>
                                                <div class="mt-1">
                                                    <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" alt="Member Photo" class="img-thumbnail" style="max-width: 100px;">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Contact Information -->
                                <div class="col-md-6">
                                    <h4 class="mb-3">Contact Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required>
                                        <div class="invalid-feedback">Please provide a valid email address</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($member['phone']); ?>" required>
                                        <div class="invalid-feedback">Please provide a valid phone number</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($member['city'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="state" class="form-label">State/Province</label>
                                            <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($member['state'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="postal_code" class="form-label">Postal Code</label>
                                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($member['postal_code'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="country" class="form-label">Country</label>
                                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($member['country'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Membership Information -->
                                <div class="col-md-12 mt-4">
                                    <h4 class="mb-3">Membership Information</h4>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="membership_type" class="form-label">Membership Type <span class="text-danger">*</span></label>
                                                <select class="form-select" id="membership_type" name="membership_type" required>
                                                    <option value="">Select Membership Type</option>
                                                    <?php foreach ($membership_types as $type): ?>
                                                        <option value="<?php echo $type['id']; ?>" <?php echo ($member['membership_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($type['name']); ?> (<?php echo htmlspecialchars($type['duration']); ?> months)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="invalid-feedback">Please select a membership type</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="Active" <?php echo ($member['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="Inactive" <?php echo ($member['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="Expired" <?php echo ($member['status'] === 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                                </select>
                                                <div class="invalid-feedback">Please select a status</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Join Date</label>
                                                <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($member['join_date'])); ?>" readonly>
                                                <small class="form-text text-muted">Join date cannot be modified</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Expiry Date</label>
                                                <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>" readonly>
                                                <small class="form-text text-muted">Use the Renew Membership feature to update expiry date</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Additional Information -->
                                <div class="col-md-12 mt-4">
                                    <h4 class="mb-3">Additional Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($member['notes'] ?? ''); ?></textarea>
                                        <small class="form-text text-muted">Add any additional notes or comments about this member</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Member</button>
                            </div>
                        </form>
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
    </script>
</body>
</html>
