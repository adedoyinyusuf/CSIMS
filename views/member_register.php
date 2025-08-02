<?php
require_once '../config/database.php';
require_once '../controllers/member_controller.php';

$memberController = new MemberController($conn);

// Get membership types for the form
$membership_types = $memberController->getMembershipTypes();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        'occupation' => trim($_POST['occupation']),
        'membership_type_id' => $_POST['membership_type_id']
    ];
    
    // Validation
    if (empty($data['ippis_no']) || empty($data['username']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[0-9]{6}$/', $data['ippis_no'])) {
        $error = 'IPPIS Number must be exactly 6 digits.';
    } elseif ($memberController->checkExistingIppis($data['ippis_no'])) {
        $error = 'IPPIS Number already exists. Please check your IPPIS Number.';
    } elseif ($data['password'] !== $data['confirm_password']) {
        $error = 'Passwords do not match.';
    } elseif (strlen($data['password']) < 6) {
        $error = 'Password must be at least 6 characters long.';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .registration-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h2 {
            color: #333;
            font-weight: 700;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registration-container">
            <div class="logo">
                <h2><i class="fas fa-users"></i> NPC CTLStaff Loan Society</h2>
                <p class="text-muted">Member Registration</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <br><small class="text-muted">You will receive an email notification once your account is approved.</small>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="ippis_no" class="form-label">IPPIS Number <span class="required">*</span></label>
                    <input type="text" class="form-control" id="ippis_no" name="ippis_no" 
                           value="<?php echo isset($_POST['ippis_no']) ? htmlspecialchars($_POST['ippis_no']) : ''; ?>" 
                           pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit IPPIS Number" required>
                    <div class="form-text">Enter your unique 6-digit IPPIS identification number.</div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username <span class="required">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email <span class="required">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" 
                               value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-control" id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="occupation" class="form-label">Occupation</label>
                        <input type="text" class="form-control" id="occupation" name="occupation" 
                               value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="membership_type_id" class="form-label">Membership Type</label>
                    <select class="form-control" id="membership_type_id" name="membership_type_id" required>
                        <option value="">Select Membership Type</option>
                        <?php foreach ($membership_types as $type): ?>
                            <option value="<?php echo $type['membership_type_id']; ?>" 
                                    <?php echo (isset($_POST['membership_type_id']) && $_POST['membership_type_id'] == $type['membership_type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?> - $<?php echo number_format($type['fee'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus"></i> Register as Member
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="member_login.php">Login here</a></p>
                    <p><a href="../index.php">Back to Admin Login</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>