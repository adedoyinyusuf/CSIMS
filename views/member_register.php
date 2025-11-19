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
        'occupation' => trim($_POST['occupation']),
        'membership_type_id' => $_POST['membership_type_id'],
        'bank_name' => trim($_POST['bank_name']),
        'account_number' => trim($_POST['account_number']),
        'account_name' => trim($_POST['account_name']),
        // monthly_contribution removed; Savings replaces legacy contributions
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
        // monthly_contribution removed from required fields
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
    <!-- Replace Bootstrap with Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Remove legacy gradient; keep minimal custom styles */
        body { background: #f3f4f6; }
        .required { color: #ef4444; }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-3xl bg-white rounded-lg shadow p-6">
            <div class="text-center mb-6">
                <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                    <img src="<?php echo APP_LOGO_URL; ?>" alt="<?php echo APP_SHORT_NAME; ?> Logo" class="h-16 mx-auto mb-2" />
                <?php else: ?>
                    <i class="fas fa-users fa-2x mb-2"></i>
                <?php endif; ?>
                <h2 class="text-2xl font-bold text-gray-900">NPC CTLStaff Loan Society</h2>
                <p class="text-gray-600">Member Registration</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <br><small class="text-gray-600">You will receive an email notification once your account is approved.</small>
                </div>
            <?php endif; ?>

            <form id="registrationForm" method="POST" action="" class="space-y-6">
                <?php echo CSRFProtection::getTokenField(); ?>

                <!-- Core Identification -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="ippis_no" class="block text-sm font-medium text-gray-700">IPPIS Number <span class="required">*</span></label>
                        <input type="text" id="ippis_no" name="ippis_no" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit IPPIS Number" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['ippis_no']) ? htmlspecialchars($_POST['ippis_no']) : ''; ?>">
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <!-- Contact -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>

                <!-- Passwords -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required class="mt-1 w-full px-3 py-2 border rounded">
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters.</p>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 w-full px-3 py-2 border rounded">
                    </div>
                </div>

                <!-- Personal -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                    <div>
                        <label for="dob" class="block text-sm font-medium text-gray-700">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="dob" name="dob" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                    </div>
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" required class="mt-1 w-full px-3 py-2 border rounded">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address <span class="required">*</span></label>
                    <textarea id="address" name="address" rows="3" required class="mt-1 w-full px-3 py-2 border rounded"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <!-- Membership Type -->
                <div>
                    <label for="membership_type_id" class="block text-sm font-medium text-gray-700">Membership Type <span class="required">*</span></label>
                    <select id="membership_type_id" name="membership_type_id" required class="mt-1 w-full px-3 py-2 border rounded">
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


                <!-- Employment (optional) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="marital_status" class="block text-sm font-medium text-gray-700">Marital Status</label>
                        <select id="marital_status" name="marital_status" class="mt-1 w-full px-3 py-2 border rounded">
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Banking Details -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">Banking Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                        <div>
                            <label for="bank_name" class="block text-sm font-medium text-gray-700">Bank Name <span class="required">*</span></label>
                            <input type="text" id="bank_name" name="bank_name" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                        </div>
                        <div>
                            <label for="account_number" class="block text-sm font-medium text-gray-700">Account Number <span class="required">*</span></label>
                            <input type="text" id="account_number" name="account_number" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['account_number']) ? htmlspecialchars($_POST['account_number']) : ''; ?>">
                        </div>
                        <div>
                            <label for="account_name" class="block text-sm font-medium text-gray-700">Account Name <span class="required">*</span></label>
                            <input type="text" id="account_name" name="account_name" required class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- Next of Kin (optional) -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">Next of Kin</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
                        <div>
                            <label for="next_of_kin_name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" id="next_of_kin_name" name="next_of_kin_name" class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['next_of_kin_name']) ? htmlspecialchars($_POST['next_of_kin_name']) : ''; ?>">
                        </div>
                        <div>
                            <label for="next_of_kin_relationship" class="block text-sm font-medium text-gray-700">Relationship</label>
                            <input type="text" id="next_of_kin_relationship" name="next_of_kin_relationship" class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['next_of_kin_relationship']) ? htmlspecialchars($_POST['next_of_kin_relationship']) : ''; ?>">
                        </div>
                        <div>
                            <label for="next_of_kin_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" id="next_of_kin_phone" name="next_of_kin_phone" class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['next_of_kin_phone']) ? htmlspecialchars($_POST['next_of_kin_phone']) : ''; ?>">
                        </div>
                        <div class="lg:col-span-3">
                            <label for="next_of_kin_address" class="block text-sm font-medium text-gray-700">Address</label>
                            <input type="text" id="next_of_kin_address" name="next_of_kin_address" class="mt-1 w-full px-3 py-2 border rounded" value="<?php echo isset($_POST['next_of_kin_address']) ? htmlspecialchars($_POST['next_of_kin_address']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end items-center pt-4 border-t border-gray-200">
                    <a href="../index.php" class="text-sm text-gray-600 hover:text-blue-600">Back to Admin Login</a>
                </div>
            </form>
        </div>
    </div>
    <button type="submit" form="registrationForm" class="fixed bottom-6 right-6 z-50 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg" style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #2563eb; color: #fff; padding: 12px 16px; border-radius: 9999px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);">Register</button>
</body>
</html>