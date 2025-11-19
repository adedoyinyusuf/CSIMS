<?php
// Centralize session and security via config
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../config/database.php';
require_once '../controllers/member_controller.php';

// Remove manual session check; rely on member_auth_check.php
// if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
//     header('Location: member_login.php');
//     exit();
// }

$memberController = new MemberController($conn);
$member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];

// Get member details
$member = $memberController->getMemberById($member_id);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle extended profile fields
        $extended_data = [
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'marital_status' => $_POST['marital_status'] ?? '',
            'highest_qualification' => trim($_POST['highest_qualification'] ?? ''),
            'years_of_residence' => $_POST['years_of_residence'] ?? null
        ];
        
        // Update basic profile fields
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'dob' => $_POST['dob'],
            'gender' => $_POST['gender'],
            'address' => trim($_POST['address']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'occupation' => trim($_POST['occupation']),
            'membership_type_id' => $member['membership_type_id'],
            'status' => $member['status']
        ];
        
        // Merge extended data
        $data = array_merge($data, $extended_data);
        
        $result = $memberController->updateMemberProfile($member_id, $data);
        if ($result['success']) {
            $success = $result['message'];
            $member = $memberController->getMemberById($member_id);
            $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['upload_photo'])) {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['profile_photo']['name']);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                $error = 'Only JPG, JPEG, PNG, and GIF files are allowed';
            } else if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) { // 5MB limit
                $error = 'File size must be less than 5MB';
            } else {
                // Generate unique filename
                $filename = 'member_' . $member_id . '_' . time() . '.' . $file_info['extension'];
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    try {
                        // Delete old profile photo if exists
                        if (!empty($member['photo']) && file_exists('../' . $member['photo'])) {
                            unlink('../' . $member['photo']);
                        }
                        
                        // Update profile photo path in database
                        $photo_path = 'assets/uploads/profiles/' . $filename;
                        $stmt = $conn->prepare("UPDATE members SET photo = ? WHERE member_id = ?");
                        $stmt->bind_param('si', $photo_path, $member_id);
                        
                        if ($stmt->execute()) {
                            $success = 'Profile photo uploaded successfully!';
                            
                            // Refresh member data
                            $member = $memberController->getMemberById($member_id);
                        } else {
                            $error = 'Error updating profile photo in database';
                            // Delete uploaded file if database update fails
                            if (file_exists($upload_path)) {
                                unlink($upload_path);
                            }
                        }
                        
                    } catch (Exception $e) {
                        $error = 'Error updating profile photo: ' . $e->getMessage();
                        // Delete uploaded file if database update fails
                        if (file_exists($upload_path)) {
                            unlink($upload_path);
                        }
                    }
                } else {
                    $error = 'Failed to upload file';
                }
            }
        } else {
            $error = 'Please select a file to upload';
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            $result = $memberController->changePassword($member_id, $current_password, $new_password);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['update_employment'])) {
        $employment_data = [
            'employee_rank' => trim($_POST['employee_rank'] ?? ''),
            'grade_level' => trim($_POST['grade_level'] ?? ''),
            'position' => trim($_POST['position'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'date_of_first_appointment' => $_POST['date_of_first_appointment'] ?? null,
            'date_of_retirement' => $_POST['date_of_retirement'] ?? null
        ];
        
        $result = $memberController->updateMemberProfile($member_id, $employment_data);
        if ($result['success']) {
            $success = 'Employment information updated successfully!';
            $member = $memberController->getMemberById($member_id);
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['update_banking'])) {
        $banking_data = [
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'account_number' => trim($_POST['account_number'] ?? ''),
            'account_name' => trim($_POST['account_name'] ?? '')
        ];
        
        $result = $memberController->updateMemberProfile($member_id, $banking_data);
        if ($result['success']) {
            $success = 'Banking information updated successfully!';
            $member = $memberController->getMemberById($member_id);
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['update_next_of_kin'])) {
        $next_of_kin_data = [
            'next_of_kin_name' => trim($_POST['next_of_kin_name'] ?? ''),
            'next_of_kin_relationship' => $_POST['next_of_kin_relationship'] ?? '',
            'next_of_kin_phone' => trim($_POST['next_of_kin_phone'] ?? ''),
            'next_of_kin_address' => trim($_POST['next_of_kin_address'] ?? '')
        ];
        
        $result = $memberController->updateMemberProfile($member_id, $next_of_kin_data);
        if ($result['success']) {
            $success = 'Next of kin information updated successfully!';
            $member = $memberController->getMemberById($member_id);
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NPC CTLStaff Loan Society</title>
    <!-- Assets centralized via includes/member_header.php -->
    <style>
        body {
            background: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }
        .navbar {
            background: #ffffff;
            box-shadow: 0 2px 10px var(--shadow-sm);
            border-bottom: 1px solid var(--border-light);
        }
        .navbar .navbar-brand,
        .navbar .nav-link {
            color: var(--text-primary);
        }
        .navbar .nav-link:hover,
        .navbar .nav-link:focus {
            color: var(--text-secondary);
        }
        .card {
            background: var(--surface-primary);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-sm);
        }
        .btn-primary {
            background-color: var(--member-primary);
            border-color: var(--member-primary);
            border-radius: 25px;
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--shadow-sm);
        }
        .sidebar {
            background: #ffffff;
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 10px var(--shadow-sm);
            border-right: 1px solid var(--border-light);
            color: var(--text-primary);
        }
        .nav-link {
            color: var(--text-secondary);
            border-radius: 10px;
            margin: 2px 0;
        }
        .nav-link:hover, .nav-link.active {
            background-color: var(--primary-50);
            color: var(--text-primary);
        }
        .profile-header {
            background: #ffffff;
            color: var(--text-primary);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            box-shadow: 0 4px 20px var(--shadow-sm);
            border-top: 3px solid var(--member-primary);
        }
        .form-control:focus {
            border-color: var(--member-primary);
            box-shadow: 0 0 0 0.2rem var(--primary-50);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/member_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2><i class="fas fa-user"></i> My Profile</h2>
                                <p class="mb-0">Manage your personal information and account settings</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="text-center">
                                    <?php if (!empty($member['photo']) && file_exists('../' . $member['photo'])): ?>
                                        <img src="<?php echo '../' . $member['photo']; ?>" alt="Profile Photo" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid white;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-4x mb-2"></i>
                                    <?php endif; ?>
                                    <p class="mb-0">Member ID: <?php echo $member['member_id']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Profile Photo Section -->
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-camera"></i> Profile Photo</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <?php if (!empty($member['photo']) && file_exists('../' . $member['photo'])): ?>
                                            <img src="<?php echo '../' . $member['photo']; ?>" alt="Profile Photo" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #f8f9fa;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px; border: 4px solid #f8f9fa;">
                                                <i class="fas fa-user fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <input type="file" name="profile_photo" accept="image/*" class="form-control form-control-sm" required>
                                            <small class="form-text text-muted">JPG, JPEG, PNG, or GIF. Max 5MB.</small>
                                        </div>
                                        
                                        <button type="submit" name="upload_photo" class="btn btn-primary btn-sm">
                                            <i class="fas fa-upload"></i> Upload Photo
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Information -->
                        <div class="col-md-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-user-edit"></i> Personal Information</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="middle_name" class="form-label">Middle Name</label>
                                                <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                                       value="<?php echo htmlspecialchars($member['middle_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                                            </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($member['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($member['phone']); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="dob" class="form-label">Date of Birth</label>
                                                <input type="date" class="form-control" id="dob" name="dob" 
                                                       value="<?php echo $member['dob']; ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="gender" class="form-label">Gender</label>
                                                <select class="form-control" id="gender" name="gender">
                                                    <option value="Male" <?php echo $member['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo $member['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="Other" <?php echo $member['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($member['address']); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                            <label for="occupation" class="form-label">Occupation</label>
                            <input type="text" class="form-control" id="occupation" name="occupation" 
                                   value="<?php echo htmlspecialchars($member['occupation']); ?>">
                        </div>
                        
                            <div class="col-md-6 mb-3">
                                <label for="marital_status" class="form-label">Marital Status</label>
                                <select class="form-control" id="marital_status" name="marital_status">
                                    <option value="">Select Status</option>
                                    <option value="Single" <?php echo ($member['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo ($member['marital_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo ($member['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo ($member['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Other" <?php echo ($member['marital_status'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="highest_qualification" class="form-label">Highest Qualification</label>
                                <input type="text" class="form-control" id="highest_qualification" name="highest_qualification" 
                                       value="<?php echo htmlspecialchars($member['highest_qualification'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="years_of_residence" class="form-label">Years of Residence</label>
                                <input type="number" class="form-control" id="years_of_residence" name="years_of_residence" 
                                       value="<?php echo htmlspecialchars($member['years_of_residence'] ?? ''); ?>" min="0">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-standard btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Membership Info & Password Change -->
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                            <!-- Membership Information -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-id-card"></i> Membership Info</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                        $typeLabel = isset($member['membership_type']) && $member['membership_type'] !== null && $member['membership_type'] !== ''
                                            ? $member['membership_type']
                                            : ($member['member_type_label'] ?? $member['member_type'] ?? 'N/A');
                                        $joinDateOut = !empty($member['join_date']) && strtotime($member['join_date']) !== false
                                            ? date('M d, Y', strtotime($member['join_date']))
                                            : 'N/A';
                                        $expiryDateOut = !empty($member['expiry_date']) && strtotime($member['expiry_date']) !== false
                                            ? date('M d, Y', strtotime($member['expiry_date']))
                                            : 'N/A';
                                        $statusLabel = $member['status'] ?? 'Unknown';
                                        $feeOut = number_format((float)($member['membership_fee'] ?? 0), 2);
                                    ?>
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($typeLabel); ?></p>
                                    <p><strong>Join Date:</strong> <?php echo $joinDateOut; ?></p>
                                    <p><strong>Expiry Date:</strong> <?php echo $expiryDateOut; ?></p>
                                    <p><strong>Status:</strong>
                                        <span class="badge bg-<?php echo $statusLabel === 'Active' ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </span>
                                    </p>
                                    <p><strong>Fee:</strong> =N=<?php echo $feeOut; ?></p>
                                </div>
                            </div>
                                </div>
                                
                                <div class="col-md-6">
                            <!-- Change Password -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-lock"></i> Change Password</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <small class="form-text text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        <button type="submit" name="change_password" class="btn btn-standard btn-warning btn-sm">
                                            <i class="fas fa-key"></i> Change Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Information -->
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-briefcase"></i> Employment Information</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="employee_rank" class="form-label">Employee Rank</label>
                                                <input type="text" class="form-control" id="employee_rank" name="employee_rank" 
                                                       value="<?php echo htmlspecialchars($member['employee_rank'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="grade_level" class="form-label">Grade Level</label>
                                                <input type="text" class="form-control" id="grade_level" name="grade_level" 
                                                       value="<?php echo htmlspecialchars($member['grade_level'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="position" class="form-label">Position</label>
                                                <input type="text" class="form-control" id="position" name="position" 
                                                       value="<?php echo htmlspecialchars($member['position'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="department" class="form-label">Department</label>
                                                <input type="text" class="form-control" id="department" name="department" 
                                                       value="<?php echo htmlspecialchars($member['department'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="date_of_first_appointment" class="form-label">Date of First Appointment</label>
                                                <input type="date" class="form-control" id="date_of_first_appointment" name="date_of_first_appointment" 
                                                       value="<?php echo htmlspecialchars($member['date_of_first_appointment'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="date_of_retirement" class="form-label">Date of Retirement</label>
                                                <input type="date" class="form-control" id="date_of_retirement" name="date_of_retirement" 
                                                       value="<?php echo htmlspecialchars($member['date_of_retirement'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="update_employment" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Employment Info
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Banking Information -->
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-university"></i> Banking Information</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="bank_name" class="form-label">Bank Name</label>
                                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                                       value="<?php echo htmlspecialchars($member['bank_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="account_number" class="form-label">Account Number</label>
                                                <input type="text" class="form-control" id="account_number" name="account_number" 
                                                       value="<?php echo htmlspecialchars($member['account_number'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="account_name" class="form-label">Account Name</label>
                                                <input type="text" class="form-control" id="account_name" name="account_name" 
                                                       value="<?php echo htmlspecialchars($member['account_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="update_banking" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Banking Info
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Next of Kin Information -->
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-users"></i> Next of Kin Information</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="next_of_kin_name" class="form-label">Next of Kin Name</label>
                                                <input type="text" class="form-control" id="next_of_kin_name" name="next_of_kin_name" 
                                                       value="<?php echo htmlspecialchars($member['next_of_kin_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="next_of_kin_relationship" class="form-label">Relationship</label>
                                                <select class="form-control" id="next_of_kin_relationship" name="next_of_kin_relationship">
                                                    <option value="">Select Relationship</option>
                                                    <option value="Spouse" <?php echo ($member['next_of_kin_relationship'] ?? '') === 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                                                    <option value="Child" <?php echo ($member['next_of_kin_relationship'] ?? '') === 'Child' ? 'selected' : ''; ?>>Child</option>
                                                    <option value="Parent" <?php echo ($member['next_of_kin_relationship'] ?? '') === 'Parent' ? 'selected' : ''; ?>>Parent</option>
                                                    <option value="Sibling" <?php echo ($member['next_of_kin_relationship'] ?? '') === 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                                                    <option value="Other" <?php echo ($member['next_of_kin_relationship'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="next_of_kin_phone" class="form-label">Next of Kin Phone</label>
                                                <input type="tel" class="form-control" id="next_of_kin_phone" name="next_of_kin_phone" 
                                                       value="<?php echo htmlspecialchars($member['next_of_kin_phone'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="next_of_kin_address" class="form-label">Next of Kin Address</label>
                                                <textarea class="form-control" id="next_of_kin_address" name="next_of_kin_address" rows="2"><?php echo htmlspecialchars($member['next_of_kin_address'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="update_next_of_kin" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Next of Kin Info
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!-- Monthly contribution card removed to align with Savings module -->
