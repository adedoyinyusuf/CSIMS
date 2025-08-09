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

<?php include '../includes/header.php'; ?>

<div class="flex h-screen bg-gray-100">
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 ml-64 overflow-x-hidden overflow-y-auto">
        <main class="p-6">
                <!-- Breadcrumb -->
                <nav class="flex mb-6" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-home mr-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">Members</a>
                            </div>
                        </li>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-sm font-medium text-gray-500">Add Member</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Add New Member</h1>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($session->hasFlash('success')): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo $session->getFlash('success'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($session->hasFlash('error')): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo $session->getFlash('error'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Add Member Form -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h5 class="text-lg font-semibold text-gray-900">Member Information</h5>
                    </div>
                    <div class="p-6">
                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                            <!-- Personal Information -->
                            <div class="space-y-6">
                                <div>
                                    <h5 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">Personal Information</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                
                                    <div>
                                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="first_name" name="first_name" required>
                                    </div>
                                    
                                    <div>
                                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="last_name" name="last_name" required>
                                    </div>
                                
                                    <div>
                                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">Gender <span class="text-red-500">*</span></label>
                                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                                        <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="date_of_birth" name="date_of_birth">
                                    </div>
                                    
                                    <div>
                                        <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">Occupation</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="occupation" name="occupation">
                                    </div>
                                    
                                    <div>
                                        <label for="national_id" class="block text-sm font-medium text-gray-700 mb-2">National ID</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="national_id" name="national_id">
                                    </div>
                                
                                    <div>
                                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
                                        <input type="file" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="photo" name="photo" accept="image/*" onchange="previewPhoto(this)">
                                        <p class="text-sm text-gray-500 mt-1">Upload a profile photo (JPG, PNG, GIF). Max size: 2MB</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                        <div class="border border-gray-300 rounded-md p-4 text-center">
                                            <img id="photoPreview" src="<?php echo BASE_URL; ?>/assets/images/placeholder.png" alt="Profile Preview" class="max-h-36 mx-auto">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="space-y-6">
                                <div>
                                    <h5 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">Contact Information</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                                        <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="email" name="email" required>
                                    </div>
                                    
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone <span class="text-red-500">*</span></label>
                                        <input type="tel" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="phone" name="phone" required>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="address" name="address">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <div>
                                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">City</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="city" name="city">
                                    </div>
                                    
                                    <div>
                                        <label for="state" class="block text-sm font-medium text-gray-700 mb-2">State/Province</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="state" name="state">
                                    </div>
                                    
                                    <div>
                                        <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="postal_code" name="postal_code">
                                    </div>
                                    
                                    <div>
                                        <label for="country" class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="country" name="country" value="Nigeria">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Membership Information -->
                            <div class="space-y-6">
                                <div>
                                    <h5 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">Membership Information</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="membership_type_id" class="block text-sm font-medium text-gray-700 mb-2">Membership Type <span class="text-red-500">*</span></label>
                                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="membership_type_id" name="membership_type_id" required>
                                            <option value="">Select Membership Type</option>
                                            <?php foreach ($membership_types as $type): ?>
                                                <option value="<?php echo $type['type_id']; ?>">
                                                    <?php echo $type['type_name']; ?> - <?php echo Utilities::formatCurrency($type['fee_amount']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="status" name="status" required>
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="join_date" class="block text-sm font-medium text-gray-700 mb-2">Join Date <span class="text-red-500">*</span></label>
                                        <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="join_date" name="join_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date <span class="text-red-500">*</span></label>
                                        <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="expiry_date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="notes" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Cancel</a>
                                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Add Member</button>
                            </div>
                        </form>
                    </div>
                </div>
                
            </main>
        </div>
    </div>
    
    <!-- Custom JavaScript -->
    <script>
        // Photo preview functionality
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Auto-calculate expiry date based on membership type
        document.getElementById('membership_type_id').addEventListener('change', function() {
            const joinDate = new Date(document.getElementById('join_date').value);
            if (joinDate) {
                // Add 1 year to join date
                const expiryDate = new Date(joinDate);
                expiryDate.setFullYear(expiryDate.getFullYear() + 1);
                document.getElementById('expiry_date').value = expiryDate.toISOString().split('T')[0];
            }
        });
        
        // Update expiry date when join date changes
        document.getElementById('join_date').addEventListener('change', function() {
            const joinDate = new Date(this.value);
            if (joinDate) {
                const expiryDate = new Date(joinDate);
                expiryDate.setFullYear(expiryDate.getFullYear() + 1);
                document.getElementById('expiry_date').value = expiryDate.toISOString().split('T')[0];
            }
        });
    </script>

<?php include '../includes/footer.php'; ?>
