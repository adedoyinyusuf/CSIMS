<?php
require_once '../config/config.php';
require_once '../controllers/member_controller.php';
require_once '../includes/utilities.php';

$memberController = new MemberController();

// Get membership types for the form
$membership_types = $memberController->getMembershipTypes();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFProtection::validateRequest();
    $data = [
        'ippis_no' => trim($_POST['ippis_no']),
        'username' => trim($_POST['username']),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password'],
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'dob' => $_POST['dob'],
        'gender' => $_POST['gender'],
        'address' => trim($_POST['address']),
        'phone' => trim($_POST['phone']),
        'email' => trim($_POST['email']),
        'occupation' => trim($_POST['occupation'] ?? ''),
        'membership_type_id' => $_POST['membership_type_id'],
        'bank_name' => trim($_POST['bank_name']),
        'account_number' => trim($_POST['account_number']),
        'account_name' => trim($_POST['account_name']),
    ];
    
    // Validation
    if (
        empty($data['ippis_no']) ||
        empty($data['username']) ||
        empty($data['password']) ||
        empty($data['confirm_password']) ||
        empty($data['first_name']) ||
        empty($data['last_name']) ||
        empty($data['email']) ||
        empty($data['phone']) ||
        empty($data['dob']) ||
        empty($data['gender']) ||
        empty($data['address']) ||
        empty($data['occupation']) ||
        empty($data['membership_type_id']) ||
        empty($data['bank_name']) ||
        empty($data['account_number']) ||
        empty($data['account_name'])
    ) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[0-9]{6}$/', $data['ippis_no'])) {
        $error = 'IPPIS Number must be exactly 6 digits.';
    } elseif ($memberController->checkExistingIppis($data['ippis_no'])) {
        $error = 'IPPIS Number already exists. Please check your IPPIS Number.';
    } elseif ($data['password'] !== $data['confirm_password']) {
        $error = 'Passwords do not match.';
    } elseif (strlen($data['password']) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if username already exists
        $existingUsername = $memberController->checkExistingUsername($data['username']);
        if ($existingUsername) {
            $error = 'Username already exists.';
        } else {
            // Check if email already exists
            $existing = $memberController->checkExistingMember($data['email']);
            if ($existing) {
                $error = 'Email already exists.';
            } else {
                // Register the member
                $result = $memberController->registerMember($data);
                if ($result) {
                    $success = 'Registration successful! Your membership application has been submitted and is pending admin approval. You will be notified once approved.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Registration - NPC CTLStaff Loan Society</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f1f5f9;
            min-height: 100vh;
            padding: 24px 16px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header-card {
            background: white;
            border-radius: 12px;
            padding: 40px 32px;
            text-align: center;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        .logo-circle {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-circle i {
            color: white;
            font-size: 36px;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .subtitle {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .tagline {
            font-size: 14px;
            color: #94a3b8;
        }
        
        .progress-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        
        .progress-steps {
            display: flex;
            gap: 12px;
        }
       
        .progress-step {
            flex: 1;
            text-align: center;
            padding: 16px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .progress-step.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .progress-step i {
            display: block;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .progress-step .label {
            font-size: 13px;
            font-weight: 500;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
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
        
        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #3b82f6;
            font-size: 20px;
        }
        
        .form-grid {
            display: grid;
            gap: 20px;
        }
        
        .form-grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .form-grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .required {
            color: #ef4444;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            color: #1e293b;
            background: white;
            transition: all 0.2s;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-help {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }
        
        .btn-register {
            background: #3b82f6;
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
            transition: all 0.2s;
        }
        
        .btn-register:hover {
            background: #2563eb;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        
        .floating-button {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }
        
        .footer-link {
            text-align: center;
            padding: 24px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .footer-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
        }
        
        .footer-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            body { padding: 16px 12px; }
            .header-card { padding: 24px 20px; }
            .form-section { padding: 20px; }
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
            .progress-container { display: none; }
            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-card">
            <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                <img src="<?php echo APP_LOGO_URL; ?>" alt="Logo" style="height: 80px; margin: 0 auto 16px;" />
            <?php else: ?>
                <div class="logo-circle">
                    <i class="fas fa-users"></i>
                </div>
            <?php endif; ?>
            <h1>Member Registration</h1>
            <p class="subtitle">NPC CTLStaff Loan Society</p>
            <p class="tagline">Join our cooperative society and enjoy exclusive benefits</p>
        </div>

        <!-- Progress Steps -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-step active">
                    <i class="fas fa-user-circle"></i>
                    <div class="label">Personal Info</div>
                </div>
                <div class="progress-step">
                    <i class="fas fa-briefcase"></i>
                    <div class="label">Employment</div>
                </div>
                <div class="progress-step">
                    <i class="fas fa-university"></i>
                    <div class="label">Banking</div>
                </div>
                <div class="progress-step">
                    <i class="fas fa-check-circle"></i>
                    <div class="label">Complete</div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle alert-icon"></i>
                <div>
                    <strong>Error!</strong><br>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <div>
                    <strong>Success!</strong><br>
                    <?php echo htmlspecialchars($success); ?><br>
                    <small>You will receive an email notification once approved.</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form id="registrationForm" method="POST" action="">
            <?php echo CSRFProtection::getTokenField(); ?>

            <!-- Account Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-user-lock"></i>
                    Account Information
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">IPPIS Number <span class="required">*</span></label>
                        <input type="text" name="ippis_no" class="form-input" pattern="[0-9]{6}" maxlength="6" 
                               placeholder="6-digit number" required 
                               value="<?php echo isset($_POST['ippis_no']) ? htmlspecialchars($_POST['ippis_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username <span class="required">*</span></label>
                        <input type="text" name="username" class="form-input" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-input" required
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-input" required>
                        <p class="form-help">Minimum 8 characters</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-id-card"></i>
                    Personal Information
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-input" required
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-input" required
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth <span class="required">*</span></label>
                        <input type="date" name="dob" class="form-input" required
                               value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender <span class="required">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Address <span class="required">*</span></label>
                        <textarea name="address" class="form-textarea" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Occupation <span class="required">*</span></label>
                        <input type="text" name="occupation" class="form-input" placeholder="e.g. Teacher, Engineer, Doctor" required
                               value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Membership & Employment -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-briefcase"></i>
                    Membership & Employment
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Membership Type <span class="required">*</span></label>
                        <select name="membership_type_id" class="form-select" required>
                            <option value="">Select Membership Type</option>
                            <?php if (!empty($membership_types)): ?>
                                <?php foreach ($membership_types as $type): ?>
                                    <?php 
                                        $typeId = $type['membership_type_id'] ?? ($type['type_id'] ?? null);
                                        $typeName = $type['name'] ?? ($type['type_name'] ?? '');
                                        $typeFee = $type['fee'] ?? ($type['fee_amount'] ?? null);
                                    ?>
                                    <?php if ($typeId !== null): ?>
                                        <option value="<?php echo $typeId; ?>" <?php echo (isset($_POST['membership_type_id']) && $_POST['membership_type_id'] == $typeId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($typeName); ?>
                                            <?php if ($typeFee !== null): ?>
                                                - <?php echo Utilities::formatCurrency($typeFee); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Marital Status</label>
                        <select name="marital_status" class="form-select">
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Banking Details -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-university"></i>
                    Banking Details
                </div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Bank Name <span class="required">*</span></label>
                        <input type="text" name="bank_name" class="form-input" required
                               value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Number <span class="required">*</span></label>
                        <input type="text" name="account_number" class="form-input" required
                               value="<?php echo isset($_POST['account_number']) ? htmlspecialchars($_POST['account_number']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Name <span class="required">*</span></label>
                        <input type="text" name="account_name" class="form-input" required
                               value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Next of Kin -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Next of Kin (Optional)
                </div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="next_of_kin_name" class="form-input"
                               value="<?php echo isset($_POST['next_of_kin_name']) ? htmlspecialchars($_POST['next_of_kin_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="next_of_kin_relationship" class="form-input"
                               value="<?php echo isset($_POST['next_of_kin_relationship']) ? htmlspecialchars($_POST['next_of_kin_relationship']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="next_of_kin_phone" class="form-input"
                               value="<?php echo isset($_POST['next_of_kin_phone']) ? htmlspecialchars($_POST['next_of_kin_phone']) : ''; ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="next_of_kin_address" class="form-input"
                               value="<?php echo isset($_POST['next_of_kin_address']) ? htmlspecialchars($_POST['next_of_kin_address']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Footer Link -->
            <div class="footer-link">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Admin Login</a>
            </div>
        </form>

        <!-- Floating Button -->
        <button type="submit" form="registrationForm" class="btn-register floating-button">
            <i class="fas fa-check-circle"></i> Register
        </button>
    </div>
</body>
</html>
