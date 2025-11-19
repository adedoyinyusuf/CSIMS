<?php
/**
 * Admin Profile Management Page
 * 
 * This page allows administrators to manage their profile information
 * including uploading profile photos and updating personal details.
 */

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../controllers/auth_controller.php';

// Initialize controllers
$auth = new AuthController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get current user
$current_user = $auth->getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success_message = '';
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                
                // Validate inputs
                if (empty($first_name)) {
                    $errors[] = 'First name is required';
                }
                if (empty($last_name)) {
                    $errors[] = 'Last name is required';
                }
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Valid email is required';
                }
                
                if (empty($errors)) {
                    try {
                        // Update profile
                        $stmt = $conn->prepare("UPDATE admins SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE admin_id = ?");
                        $stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone, $address, $current_user['admin_id']);
                        
                        if ($stmt->execute()) {
                            $success_message = 'Profile updated successfully!';
                            
                            // Refresh current user data
                            $current_user = $auth->getCurrentUser();
                        } else {
                            $errors[] = 'Error updating profile in database';
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = 'Error updating profile: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validate current password
                if (!password_verify($current_password, $current_user['password'])) {
                    $errors[] = 'Current password is incorrect';
                }
                
                // Validate new password
                if (strlen($new_password) < 8) {
                    $errors[] = 'New password must be at least 8 characters long';
                }
                
                if ($new_password !== $confirm_password) {
                    $errors[] = 'New passwords do not match';
                }
                
                if (empty($errors)) {
                    try {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
                        $stmt->bind_param('si', $hashed_password, $current_user['admin_id']);
                        
                        if ($stmt->execute()) {
                            $success_message = 'Password changed successfully!';
                        } else {
                            $errors[] = 'Error changing password in database';
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = 'Error changing password: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'upload_photo':
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../../assets/uploads/profiles/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_info = pathinfo($_FILES['profile_photo']['name']);
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                        $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed';
                    } else if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) { // 5MB limit
                        $errors[] = 'File size must be less than 5MB';
                    } else {
                        // Generate unique filename
                        $filename = 'admin_' . $current_user['admin_id'] . '_' . time() . '.' . $file_info['extension'];
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                            try {
                                // Delete old profile photo if exists
                                if (!empty($current_user['profile_photo']) && file_exists('../../' . $current_user['profile_photo'])) {
                                    unlink('../../' . $current_user['profile_photo']);
                                }
                                
                                // Update profile photo path in database
                                $photo_path = 'assets/uploads/profiles/' . $filename;
                                $stmt = $conn->prepare("UPDATE admins SET profile_photo = ? WHERE admin_id = ?");
                                $stmt->bind_param('si', $photo_path, $current_user['admin_id']);
                                
                                if ($stmt->execute()) {
                                    $success_message = 'Profile photo uploaded successfully!';
                                    
                                    // Refresh current user data
                                    $current_user = $auth->getCurrentUser();
                                } else {
                                    $errors[] = 'Error updating profile photo in database';
                                    // Delete uploaded file if database update fails
                                    if (file_exists($upload_path)) {
                                        unlink($upload_path);
                                    }
                                }
                                
                            } catch (Exception $e) {
                                $errors[] = 'Error updating profile photo: ' . $e->getMessage();
                                // Delete uploaded file if database update fails
                                if (file_exists($upload_path)) {
                                    unlink($upload_path);
                                }
                            }
                        } else {
                            $errors[] = 'Failed to upload file';
                        }
                    }
                } else {
                    $errors[] = 'Please select a file to upload';
                }
                break;
        }
    }
}

// Page title
$pageTitle = "My Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title><?php echo $pageTitle; ?> - CSIMS</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f4ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81'
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a'
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Custom styles for legacy compatibility -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include_once '../includes/header.php'; ?>

                <!-- Begin Page Content -->
                <div id="main-content" class="main-content p-6 bg-gray-50 min-h-screen transition-all duration-300 mt-16">
                    <!-- Page Heading -->
                    <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl shadow-lg mb-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold mb-2"><i class="fas fa-user-edit mr-4"></i>My Profile</h1>
                                <p class="text-primary-100 text-lg">Manage your profile information and settings</p>
                            </div>
                        </div>
                    </div>

                    <!-- Flash Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-50 border-l-4 border-red-200 text-red-800 p-4 mb-6 rounded-lg shadow-sm" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-3 text-lg"></i>
                                <div>
                                    <h4 class="font-medium mb-2">Please fix the following errors:</h4>
                                    <ul class="list-disc list-inside">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="bg-green-50 border-l-4 border-green-200 text-green-800 p-4 mb-6 rounded-lg shadow-sm" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-3 text-lg"></i>
                                <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Profile Photo Section -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-6">Profile Photo</h3>
                                
                                <div class="text-center">
                                    <div class="mb-6">
                                        <?php if (!empty($current_user['profile_photo']) && file_exists('../../' . $current_user['profile_photo'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . $current_user['profile_photo']; ?>" alt="Profile Photo" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-primary-100">
                                        <?php else: ?>
                                            <div class="w-32 h-32 rounded-full mx-auto bg-gray-200 flex items-center justify-center border-4 border-gray-100">
                                                <i class="fas fa-user text-4xl text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="action" value="upload_photo">
                                        
                                        <div>
                                            <input type="file" name="profile_photo" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" required>
                                            <p class="text-xs text-gray-500 mt-2">JPG, JPEG, PNG, or GIF. Max 5MB.</p>
                                        </div>
                                        
                                        <button type="submit" class="w-full bg-primary-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                                            <i class="fas fa-upload mr-2"></i>Upload Photo
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Information Section -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                                <h3 class="text-lg font-semibold text-gray-900 mb-6">Profile Information</h3>
                                
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($current_user['first_name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                        </div>
                                        
                                        <div>
                                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($current_user['last_name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    </div>
                                    
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                    
                                    <div>
                                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                        <textarea id="address" name="address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?php echo htmlspecialchars($current_user['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit" class="bg-primary-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                                            <i class="fas fa-save mr-2"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Change Password Section -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-6">Change Password</h3>
                                
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                            <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                        </div>
                                        
                                        <div>
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors">
                                            <i class="fas fa-key mr-2"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Account Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Username</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($current_user['username']); ?></p>
                                    </div>
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Role</p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($current_user['role']); ?></p>
                                    </div>
                                    <i class="fas fa-shield-alt text-gray-400"></i>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Status</p>
                                        <p class="text-lg font-semibold text-green-600"><?php echo htmlspecialchars($current_user['status']); ?></p>
                                    </div>
                                    <i class="fas fa-check-circle text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>