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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $errors = [];
    
    // Required fields
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'gender', 'membership_type', 'ippis_no'];
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
    if (!empty($_POST['email'])) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT member_id FROM members WHERE email = ? AND member_id != ?");
            if ($stmt) {
                $stmt->bind_param("si", $_POST['email'], $member_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $errors[] = 'Email is already taken by another member';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Ignore DB error for validation to prevent crash
        }
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
            'ippis_no' => $_POST['ippis_no'],
            'first_name' => $_POST['first_name'],
            'middle_name' => $_POST['middle_name'] ?? '',
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'gender' => $_POST['gender'],
            'dob' => !empty($_POST['dob']) ? $_POST['dob'] : null,
            'marital_status' => $_POST['marital_status'] ?? '',
            'address' => $_POST['address'] ?? '',
            'occupation' => $_POST['occupation'] ?? '',
            'highest_qualification' => $_POST['highest_qualification'] ?? '',
            'years_of_residence' => $_POST['years_of_residence'] ?? '',
            
            'department' => $_POST['department'] ?? '',
            'employee_rank' => $_POST['employee_rank'] ?? '',
            'grade_level' => $_POST['grade_level'] ?? '',
            'date_of_first_appointment' => !empty($_POST['date_of_first_appointment']) ? $_POST['date_of_first_appointment'] : null,
            'date_of_retirement' => !empty($_POST['date_of_retirement']) ? $_POST['date_of_retirement'] : null,
            
            'membership_type_id' => $_POST['membership_type'],
            'status' => $_POST['status'],
            'monthly_contribution' => $_POST['monthly_contribution'] ?? 0,
            'savings_balance' => $_POST['savings_balance'] ?? 0,
            
            'bank_name' => $_POST['bank_name'] ?? '',
            'account_number' => $_POST['account_number'] ?? '',
            'account_name' => $_POST['account_name'] ?? '',
            
            'next_of_kin_name' => $_POST['next_of_kin_name'] ?? '',
            'next_of_kin_relationship' => $_POST['next_of_kin_relationship'] ?? '',
            'next_of_kin_phone' => $_POST['next_of_kin_phone'] ?? '',
            'next_of_kin_address' => $_POST['next_of_kin_address'] ?? '',
            
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
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Theme Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd',
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9',
                            600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e'
                        },
                        secondary: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0',
                            300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b',
                            600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-secondary-50 text-secondary-900 font-sans antialiased">
    <!-- Header -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-secondary-50 md:ml-64 transition-all duration-300 p-6">
            
            <!-- Breadcrumbs -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="inline-flex items-center text-sm font-medium text-secondary-700 hover:text-primary-600">
                           <i class="fas fa-home mr-2"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-secondary-400 mx-2 text-xs"></i>
                            <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="text-sm font-medium text-secondary-700 hover:text-primary-600">Members</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-secondary-400 mx-2 text-xs"></i>
                            <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>" class="text-sm font-medium text-secondary-700 hover:text-primary-600">View Member</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-secondary-400 mx-2 text-xs"></i>
                            <span class="text-sm font-medium text-secondary-500">Edit Member</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <div class="max-w-4xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-secondary-900">Edit Member Profile</h1>
                     <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>" class="text-sm text-secondary-500 hover:text-secondary-700">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </a>
                </div>

                <!-- Flash Messages -->
                <?php if ($session->hasFlash('success')): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-md shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-check-circle text-green-500"></i></div>
                            <div class="ml-3"><p class="text-sm font-medium text-green-800"><?php echo $session->getFlash('success'); ?></p></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($session->hasFlash('error')): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-md shadow-sm">
                        <div class="flex">
                             <div class="flex-shrink-0"><i class="fas fa-exclamation-circle text-red-500"></i></div>
                            <div class="ml-3"><p class="text-sm font-medium text-red-800"><?php echo $session->getFlash('error'); ?></p></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-md shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-exclamation-circle text-red-500"></i></div>
                            <div class="ml-3">
                                <ul class="list-disc list-inside text-sm text-red-800">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <!-- Account & Personal Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-secondary-200 overflow-hidden">
                        <div class="px-6 py-4 bg-secondary-50 border-b border-secondary-200">
                            <h2 class="text-lg font-medium text-secondary-900 flex items-center">
                                <i class="fas fa-user mr-3 text-primary-500"></i> Personal Information
                            </h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">IPPIS Number <span class="text-red-500">*</span></label>
                                <input type="text" name="ippis_no" value="<?php echo htmlspecialchars($member['ippis_no'] ?? ''); ?>" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($member['first_name'] ?? ''); ?>" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($member['last_name'] ?? ''); ?>" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($member['middle_name'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($member['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($member['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Date of Birth</label>
                                <input type="date" name="dob" value="<?php echo htmlspecialchars($member['dob'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>

                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Marital Status</label>
                                <select name="marital_status" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="">Select Status</option>
                                    <?php 
                                    $statuses = ['Single', 'Married', 'Divorced', 'Widowed'];
                                    foreach($statuses as $s) {
                                        $sel = ($member['marital_status'] ?? '') === $s ? 'selected' : '';
                                        echo "<option value='$s' $sel>$s</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Phone <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            
                            <div class="col-span-full">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Address</label>
                                <textarea name="address" rows="2" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Occupation</label>
                                <input type="text" name="occupation" value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Highest Qualification</label>
                                <input type="text" name="highest_qualification" value="<?php echo htmlspecialchars($member['highest_qualification'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">State of Residence/Posting</label>
                                <select name="years_of_residence" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="">Select State</option>
                                    <?php 
                                    $states = [
                                        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 
                                        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT - Abuja', 'Gombe', 
                                        'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 
                                        'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 
                                        'Sokoto', 'Taraba', 'Yobe', 'Zamfara'
                                    ];
                                    foreach($states as $state) {
                                        $selected = ($member['years_of_residence'] ?? '') === $state ? 'selected' : '';
                                        echo "<option value=\"$state\" $selected>$state</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-span-full">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Profile Photo</label>
                                <div class="flex items-center gap-4">
                                    <?php if (!empty($member['photo'])): ?>
                                        <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" alt="Current Photo" class="h-16 w-16 rounded-full object-cover border border-secondary-300">
                                    <?php else: ?>
                                        <div class="h-16 w-16 rounded-full bg-secondary-100 flex items-center justify-center text-secondary-400">
                                            <i class="fas fa-user text-2xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-secondary-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-secondary-200 overflow-hidden">
                        <div class="px-6 py-4 bg-secondary-50 border-b border-secondary-200">
                            <h2 class="text-lg font-medium text-secondary-900 flex items-center">
                                <i class="fas fa-briefcase mr-3 text-primary-500"></i> Employment Information
                            </h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Department</label>
                                <select name="department" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="">Select Department</option>
                                    <?php 
                                    $depts = [
                                        'Census Dept', 'CRVSD', 'Finance & Accounts', 'GRD', 'HRM', 'ICTD', 
                                        'Legal Services Dept', 'Planning & Research Dept', 'Population Management Dept', 
                                        'Population Studies Dept', 'Procurement Dept', 'Public Affairs Dept'
                                    ];
                                    foreach($depts as $d) {
                                        $sel = ($member['department'] ?? '') === $d ? 'selected' : '';
                                        echo "<option value='$d' $sel>$d</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Employee Rank</label>
                                <input type="text" name="employee_rank" value="<?php echo htmlspecialchars($member['employee_rank'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Grade Level</label>
                                <input type="text" name="grade_level" value="<?php echo htmlspecialchars($member['grade_level'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Date of First Appointment</label>
                                <input type="date" id="date_of_first_appointment" name="date_of_first_appointment" value="<?php echo htmlspecialchars($member['date_of_first_appointment'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Date of Retirement</label>
                                <input type="date" id="date_of_retirement" name="date_of_retirement" value="<?php echo htmlspecialchars($member['date_of_retirement'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Membership Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-secondary-200 overflow-hidden">
                        <div class="px-6 py-4 bg-secondary-50 border-b border-secondary-200">
                             <h2 class="text-lg font-medium text-secondary-900 flex items-center">
                                <i class="fas fa-id-card mr-3 text-primary-500"></i> Membership Status
                            </h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Type <span class="text-red-500">*</span></label>
                                <select name="membership_type" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="">Select Type</option>
                                    <?php foreach ($membership_types as $type): ?>
                                        <option value="<?php echo $type['membership_type_id'] ?? $type['id'] ?? ''; ?>" <?php echo (($member['membership_type_id'] ?? '') == ($type['membership_type_id'] ?? $type['id'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Status <span class="text-red-500">*</span></label>
                                <select name="status" required class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                    <option value="Active" <?php echo ($member['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo ($member['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Pending" <?php echo ($member['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Expired" <?php echo ($member['status'] === 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Monthly Savings (₦)</label>
                                <input type="number" step="0.01" name="monthly_contribution" value="<?php echo htmlspecialchars($member['monthly_contribution'] ?? 0); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Total Balance (₦)</label>
                                <input type="number" step="0.01" name="savings_balance" value="<?php echo htmlspecialchars($member['savings_balance'] ?? 0); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Banking Information -->
                     <div class="bg-white rounded-xl shadow-sm border border-secondary-200 overflow-hidden">
                        <div class="px-6 py-4 bg-secondary-50 border-b border-secondary-200">
                             <h2 class="text-lg font-medium text-secondary-900 flex items-center">
                                <i class="fas fa-university mr-3 text-primary-500"></i> Banking Information
                            </h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Bank Name</label>
                                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($member['bank_name'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Account Number</label>
                                <input type="text" name="account_number" value="<?php echo htmlspecialchars($member['account_number'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Account Name</label>
                                <input type="text" name="account_name" value="<?php echo htmlspecialchars($member['account_name'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                        </div>
                    </div>

                     <!-- Next of Kin -->
                      <div class="bg-white rounded-xl shadow-sm border border-secondary-200 overflow-hidden">
                        <div class="px-6 py-4 bg-secondary-50 border-b border-secondary-200">
                             <h2 class="text-lg font-medium text-secondary-900 flex items-center">
                                <i class="fas fa-users mr-3 text-primary-500"></i> Next of Kin
                            </h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Name</label>
                                <input type="text" name="next_of_kin_name" value="<?php echo htmlspecialchars($member['next_of_kin_name'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Relationship</label>
                                <input type="text" name="next_of_kin_relationship" value="<?php echo htmlspecialchars($member['next_of_kin_relationship'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                             <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Phone</label>
                                <input type="text" name="next_of_kin_phone" value="<?php echo htmlspecialchars($member['next_of_kin_phone'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-secondary-700 mb-1">Address</label>
                                <input type="text" name="next_of_kin_address" value="<?php echo htmlspecialchars($member['next_of_kin_address'] ?? ''); ?>" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                     <div class="bg-white rounded-xl shadow-sm border border-secondary-200 overflow-hidden">
                        <div class="px-6 py-4 bg-secondary-50 border-b border-secondary-200">
                             <h2 class="text-lg font-medium text-secondary-900 flex items-center">
                                <i class="fas fa-sticky-note mr-3 text-primary-500"></i> Additional Notes
                            </h2>
                        </div>
                        <div class="p-6">
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-secondary-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"><?php echo htmlspecialchars($member['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-secondary-200">
                        <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>" class="px-4 py-2 bg-white border border-secondary-300 rounded-lg text-sm font-medium text-secondary-700 hover:bg-secondary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 shadow-sm">
                            Update Member Profile
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-calculate retirement date
        document.getElementById('date_of_first_appointment').addEventListener('change', function() {
            if(this.value) {
                const appDate = new Date(this.value);
                const retDate = new Date(appDate);
                retDate.setFullYear(retDate.getFullYear() + 35);
                const yyyy = retDate.getFullYear();
                const mm = String(retDate.getMonth() + 1).padStart(2, '0');
                const dd = String(retDate.getDate()).padStart(2, '0');
                document.getElementById('date_of_retirement').value = `${yyyy}-${mm}-${dd}`;
            }
        });
    </script>
</body>
</html>
