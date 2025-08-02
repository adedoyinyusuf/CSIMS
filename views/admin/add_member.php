<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize member controller
$memberController = new MemberController();

// Get membership types
$membership_types = $memberController->getMembershipTypes();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $first_name = Utilities::sanitizeInput($_POST['first_name']);
    $last_name = Utilities::sanitizeInput($_POST['last_name']);
    $gender = Utilities::sanitizeInput($_POST['gender']);
    $date_of_birth = Utilities::sanitizeInput($_POST['date_of_birth']);
    $email = Utilities::sanitizeInput($_POST['email']);
    $phone = Utilities::sanitizeInput($_POST['phone']);
    $address = Utilities::sanitizeInput($_POST['address']);
    $city = Utilities::sanitizeInput($_POST['city']);
    $state = Utilities::sanitizeInput($_POST['state']);
    $postal_code = Utilities::sanitizeInput($_POST['postal_code']);
    $country = Utilities::sanitizeInput($_POST['country']);
    $occupation = Utilities::sanitizeInput($_POST['occupation']);
    $membership_type_id = (int)$_POST['membership_type_id'];
    $join_date = Utilities::sanitizeInput($_POST['join_date']);
    $expiry_date = Utilities::sanitizeInput($_POST['expiry_date']);
    $status = Utilities::sanitizeInput($_POST['status']);
    $notes = Utilities::sanitizeInput($_POST['notes']);
    
    // Handle photo upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo = Utilities::uploadFile($_FILES['photo'], '../assets/images/members/', ['jpg', 'jpeg', 'png', 'gif']);
        if (!$photo) {
            $session->setFlash('error', 'Invalid photo format. Please upload a valid image file (JPG, JPEG, PNG, GIF).');
            header("Location: " . BASE_URL . "admin/add_member.php");
            exit();
        }
    }
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($membership_type_id)) {
        $session->setFlash('error', 'Please fill in all required fields.');
    } 
    // Validate email format
    elseif (!Utilities::validateEmail($email)) {
        $session->setFlash('error', 'Please enter a valid email address.');
    } 
    else {
        // Create member data array
        $member_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'date_of_birth' => $date_of_birth,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal_code,
            'country' => $country,
            'occupation' => $occupation,
            'membership_type_id' => $membership_type_id,
            'join_date' => $join_date,
            'expiry_date' => $expiry_date,
            'status' => $status,
            'notes' => $notes,
            'photo' => $photo
        ];
        
        // Add member
        $result = $memberController->addMember($member_data);
        
        if ($result) {
            $session->setFlash('success', 'Member added successfully!');
            header("Location: " . BASE_URL . "admin/members.php");
            exit();
        } else {
            $session->setFlash('error', 'Failed to add member. Please try again.');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member - <?php echo APP_NAME; ?></title>
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
                            <li class="breadcrumb-item active" aria-current="page">Add Member</li>
                        </ol>
                    </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add New Member</h1>
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
                
                <!-- Add Member Form -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">Member Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Personal Information -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2">Personal Information</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label required">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label required">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="gender" class="form-label required">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <div class="invalid-feedback">Please select gender</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="occupation" class="form-label">Occupation</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="photo" class="form-label">Photo</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <div class="form-text">Upload a profile photo (JPG, PNG, GIF). Max size: 2MB</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Preview</label>
                                    <div class="border rounded p-2 text-center">
                                        <img id="photoPreview" src="<?php echo BASE_URL; ?>/assets/images/placeholder.png" alt="Profile Preview" class="img-fluid" style="max-height: 150px;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2">Contact Information</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label required">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label required">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                    <div class="invalid-feedback">Please enter phone number</div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="state" name="state">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" value="Nigeria">
                                </div>
                            </div>
                            
                            <!-- Membership Information -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2">Membership Information</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="membership_type_id" class="form-label required">Membership Type</label>
                                    <select class="form-select" id="membership_type_id" name="membership_type_id" required>
                                        <option value="">Select Membership Type</option>
                                        <?php foreach ($membership_types as $type): ?>
                                            <option value="<?php echo $type['type_id']; ?>">
                                                <?php echo $type['type_name']; ?> - <?php echo Utilities::formatCurrency($type['fee_amount']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select membership type</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label required">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                    <div class="invalid-feedback">Please select status</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="join_date" class="form-label required">Join Date</label>
                                    <input type="date" class="form-control" id="join_date" name="join_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">Please select join date</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="expiry_date" class="form-label required">Expiry Date</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required>
                                    <div class="invalid-feedback">Please select expiry date</div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-end">
                                    <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="btn btn-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Add Member</button>
                                </div>
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
        // Photo preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('photoPreview').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Calculate expiry date based on join date (1 year later)
        document.getElementById('join_date').addEventListener('change', function() {
            const joinDate = new Date(this.value);
            if (!isNaN(joinDate.getTime())) {
                const expiryDate = new Date(joinDate);
                expiryDate.setFullYear(expiryDate.getFullYear() + 1);
                
                // Format date as YYYY-MM-DD for input
                const formattedDate = expiryDate.toISOString().split('T')[0];
                document.getElementById('expiry_date').value = formattedDate;
            }
        });
    </script>
</body>
</html>
