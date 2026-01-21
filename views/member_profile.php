<?php
// Centralize session and security via config
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../config/database.php';
require_once '../controllers/member_controller.php';

$memberController = new MemberController($conn);
$member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];

// Get member details
$member = $memberController->getMemberById($member_id);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    CSRFProtection::validateRequest();

    if (isset($_POST['update_profile_all'])) {
        // Collect all data
        $data = [
            // Account/Personal
            'ippis_no' => trim($_POST['ippis_no'] ?? ''), // Read-only typically, but nice to pass or valid checks
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'dob' => $_POST['dob'] ?? null,
            'gender' => $_POST['gender'] ?? '',
            'address' => trim($_POST['address'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'occupation' => trim($_POST['occupation'] ?? ''),
            'marital_status' => $_POST['marital_status'] ?? '',
            'highest_qualification' => trim($_POST['highest_qualification'] ?? ''),
            'years_of_residence' => $_POST['years_of_residence'] ?? null,
            
            // Employment
            'employee_rank' => trim($_POST['employee_rank'] ?? ''),
            'grade_level' => trim($_POST['grade_level'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'date_of_first_appointment' => $_POST['date_of_first_appointment'] ?? null,
            'date_of_retirement' => $_POST['date_of_retirement'] ?? null,
            
            // Banking
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'account_number' => trim($_POST['account_number'] ?? ''),
            'account_name' => trim($_POST['account_name'] ?? ''),
            
            // Next of Kin
            'next_of_kin_name' => trim($_POST['next_of_kin_name'] ?? ''),
            'next_of_kin_relationship' => $_POST['next_of_kin_relationship'] ?? '',
            'next_of_kin_phone' => trim($_POST['next_of_kin_phone'] ?? ''),
            'next_of_kin_address' => trim($_POST['next_of_kin_address'] ?? ''),
            
            // Monthly Contribution
            'monthly_contribution' => isset($_POST['monthly_contribution']) ? (float)$_POST['monthly_contribution'] : 0,

            // Keep existing preserved fields
            'membership_type_id' => $member['membership_type_id'],
            'status' => $member['status']
        ];
        
        // Auto-calc retirement if appointment date provided
        if (!empty($data['date_of_first_appointment'])) {
             $appt = new DateTime($data['date_of_first_appointment']);
             $appt->modify('+35 years');
             $data['date_of_retirement'] = $appt->format('Y-m-d');
        }

        // Handle Photo Upload if present in this form submission
        // (If we use a single form for everything including file)
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
             $upload_dir = '../assets/uploads/profiles/';
             if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
             
             $finfo = pathinfo($_FILES['profile_photo']['name']);
             $ext = strtolower($finfo['extension']);
             if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && $_FILES['profile_photo']['size'] <= 5*1024*1024) {
                 $new_file = 'member_' . $member_id . '_' . time() . '.' . $ext;
                 if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_file)) {
                     // Remove old
                     if (!empty($member['photo']) && file_exists('../' . $member['photo'])) {
                         @unlink('../' . $member['photo']);
                     }
                     // Update DB specific column
                     $p_path = 'assets/uploads/profiles/' . $new_file;
                     $stmtP = $conn->prepare("UPDATE members SET photo = ? WHERE member_id = ?");
                     $stmtP->bind_param('si', $p_path, $member_id);
                     $stmtP->execute();
                 }
             }
        }
        
        $result = $memberController->updateMemberProfile($member_id, $data);
        if ($result['success']) {
            $success = 'Profile updated successfully!';
            $member = $memberController->getMemberById($member_id); // Refresh
            $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];
        } else {
            $error = $result['message'];
        }

    } elseif (isset($_POST['change_password'])) {
        $curr = $_POST['current_password'];
        $new = $_POST['new_password'];
        $conf = $_POST['confirm_password'];
        
        if (empty($curr) || empty($new) || empty($conf)) {
            $error = 'All password fields are required.';
        } elseif ($new !== $conf) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $res = $memberController->changePassword($member_id, $curr, $new);
            if ($res['success']) $success = $res['message'];
            else $error = $res['message'];
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
    
    <!-- Fonts & Icons from Register Page -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Include Member Header for Nav -->
    <!-- (Note: We might need to scope our styles if header has conflicts, but usually it's fine) -->
    
    <style>
        /* Adopted Styles from Member Register */
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --bg-light: #f1f5f9;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --border: #e2e8f0;
        }

        body {
            background: var(--bg-light);
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
        }
        
        /* Container override for profile */
        .profile-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .header-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-title h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .header-title p {
            color: var(--text-gray);
            margin: 0;
        }

        .profile-avatar {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .avatar-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            color: #166534;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            gap: 20px;
        }
        
        .form-grid-2 { grid-template-columns: repeat(2, 1fr); }
        .form-grid-3 { grid-template-columns: repeat(3, 1fr); }
        
        @media (max-width: 768px) {
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        }

        .form-group { margin-bottom: 0; }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .required { color: #ef4444; }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            color: #1e293b;
            background: white;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-input[readonly] {
            background-color: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn-save {
            background: var(--primary);
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-save:hover { background: var(--primary-dark); }
        
        .floating-actions {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        /* Password Section Toggle */
        .password-toggle {
            cursor: pointer;
            color: var(--primary);
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
            display: inline-block;
        }
        
        .password-section {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed var(--border);
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/member_header.php'; ?>

    <div class="profile-container">
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <?php echo CSRFProtection::getTokenField(); ?>

            <!-- Header Card -->
            <div class="header-card">
                <div class="header-title">
                    <h1>My Profile</h1>
                    <p>Manage your account settings and personal information</p>
                    <div class="mt-2">
                        <span class="badge <?php echo ($member['status'] === 'Active') ? 'badge-success' : 'badge-warning'; ?>">
                            Status: <?php echo htmlspecialchars($member['status']); ?>
                        </span>
                        <span class="badge badge-success ms-2">
                            Type: <?php echo htmlspecialchars($member['membership_type'] ?? 'Standard'); ?>
                        </span>
                    </div>
                </div>
                <div class="profile-avatar">
                   <?php if (!empty($member['photo']) && file_exists('../' . $member['photo'])): ?>
                       <img src="<?php echo '../' . $member['photo']; ?>" alt="Profile" class="avatar-img">
                   <?php else: ?>
                       <div class="avatar-img d-flex align-items-center justify-content-center bg-secondary text-white">
                           <i class="fas fa-user fa-2x"></i>
                       </div>
                   <?php endif; ?>
                   <div>
                       <label class="form-label" style="font-size:12px; margin-bottom:4px;">Change Photo</label>
                       <input type="file" name="profile_photo" class="form-input" style="padding: 6px; font-size: 12px; width: 200px;" accept="image/*">
                   </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-user-circle"></i> Personal Information
                </div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label class="form-label">IPPIS Number (Read Only)</label>
                        <input type="text" name="ippis_no" class="form-input" value="<?php echo htmlspecialchars($member['ippis_no'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-input" value="<?php echo htmlspecialchars($member['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                         <label class="form-label">Email <span class="required">*</span></label>
                         <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                         <label class="form-label">Phone <span class="required">*</span></label>
                         <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                         <label class="form-label">Date of Birth</label>
                         <input type="date" name="dob" class="form-input" value="<?php echo htmlspecialchars($member['dob'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Male" <?php echo $member['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $member['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Marital Status</label>
                        <select name="marital_status" class="form-select">
                            <option value="">Select</option>
                            <?php foreach(['Single', 'Married', 'Divorced', 'Widowed'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($member['marital_status']??'') === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-textarea" rows="2"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="form-grid form-grid-3 mt-3">
                    <div class="form-group">
                        <label class="form-label">Occupation</label>
                        <input type="text" name="occupation" class="form-input" value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Highest Qualification</label>
                        <input type="text" name="highest_qualification" class="form-input" value="<?php echo htmlspecialchars($member['highest_qualification']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State of Residence/Posting</label>
                        <select name="years_of_residence" class="form-select">
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
                </div>
            </div>

            <!-- Employment Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-briefcase"></i> Employment Information
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                             <option value="">Select Department</option>
                             <?php 
                                $depts = ['Census Dept', 'CRVSD', 'Finance & Accounts', 'GRD', 'HRM', 'ICTD', 'Legal Services Dept', 'Planning & Research Dept', 'Population Management Dept', 'Population Studies Dept', 'Procurement Dept', 'Public Affairs Dept'];
                                foreach($depts as $d) {
                                    $sel = ($member['department']??'') === $d ? 'selected' : '';
                                    echo "<option value='$d' $sel>$d</option>";
                                }
                             ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employee Rank</label>
                        <input type="text" name="employee_rank" class="form-input" value="<?php echo htmlspecialchars($member['employee_rank']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Grade Level</label>
                        <input type="text" name="grade_level" class="form-input" value="<?php echo htmlspecialchars($member['grade_level']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of First Appointment</label>
                        <input type="date" name="date_of_first_appointment" id="date_of_first_appointment" class="form-input" value="<?php echo $member['date_of_first_appointment']??''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Retirement (Auto-calc)</label>
                        <input type="date" name="date_of_retirement" id="date_of_retirement" class="form-input" value="<?php echo $member['date_of_retirement']??''; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Savings Balance (₦)</label>
                        <input type="text" class="form-input" value="<?php echo number_format($member['savings_balance'] ?? 0, 2); ?>" readonly style="font-weight: 700; color: #166534; background-color: #f0fdf4;">
                        <small class="text-gray-500" style="font-size:12px;">Accumulated Balance</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Savings (₦)</label>
                        <input type="number" step="0.01" name="monthly_contribution" class="form-input" value="<?php echo $member['monthly_contribution']??0; ?>">
                        <small class="text-gray-500" style="font-size:12px;">Deducted from salary</small>
                    </div>
                </div>
            </div>

            <!-- Banking Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-university"></i> Banking Details
                </div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-input" value="<?php echo htmlspecialchars($member['bank_name']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-input" value="<?php echo htmlspecialchars($member['account_number']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Name</label>
                        <input type="text" name="account_name" class="form-input" value="<?php echo htmlspecialchars($member['account_name']??''); ?>">
                    </div>
                </div>
            </div>

            <!-- Next of Kin -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-users"></i> Next of Kin
                </div>
                <div class="form-grid form-grid-2">
                     <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="next_of_kin_name" class="form-input" value="<?php echo htmlspecialchars($member['next_of_kin_name']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="next_of_kin_relationship" class="form-input" value="<?php echo htmlspecialchars($member['next_of_kin_relationship']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="next_of_kin_phone" class="form-input" value="<?php echo htmlspecialchars($member['next_of_kin_phone']??''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="next_of_kin_address" class="form-textarea" rows="1"><?php echo htmlspecialchars($member['next_of_kin_address']??''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Floating Action Button -->
            <div class="floating-actions">
                <button type="submit" name="update_profile_all" class="btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>

        <!-- Password Change Section (Outside main form) -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-lock"></i> Security
            </div>
            
            <div class="password-toggle" onclick="document.getElementById('password-fields').style.display = 'block'; this.style.display='none';">
                <i class="fas fa-key"></i> Change Password
            </div>
            
            <form method="POST" id="password-fields" class="password-section">
                 <?php echo CSRFProtection::getTokenField(); ?>
                 <div class="form-grid form-grid-3">
                     <div class="form-group">
                         <label class="form-label">Current Password</label>
                         <input type="password" name="current_password" class="form-input" required>
                     </div>
                     <div class="form-group">
                         <label class="form-label">New Password</label>
                         <input type="password" name="new_password" class="form-input" required placeholder="Min 6 chars">
                     </div>
                     <div class="form-group">
                         <label class="form-label">Confirm New Password</label>
                         <input type="password" name="confirm_password" class="form-input" required>
                     </div>
                 </div>
                 <div class="mt-3">
                     <button type="submit" name="change_password" class="btn-save" style="background:#f59e0b;">
                         <i class="fas fa-check"></i> Update Password
                     </button>
                 </div>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Retirement Auto-Calculator
        const apptInput = document.getElementById('date_of_first_appointment');
        const retInput = document.getElementById('date_of_retirement');
        
        apptInput.addEventListener('change', function() {
            if (this.value) {
                const date = new Date(this.value);
                date.setFullYear(date.getFullYear() + 35);
                retInput.value = date.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
