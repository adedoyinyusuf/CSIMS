<?php
/**
 * Admin - Add Loan Application
 * 
 * This page provides a form for adding new loan applications.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/config/SystemConfigService.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize controllers
$loanController = new LoanController();
$memberController = new MemberController();

// Initialize SystemConfigService for defaults
try {
    $sysConfig = SystemConfigService::getInstance($pdo ?? null);
} catch (Exception $e) {
    $sysConfig = null;
    error_log('add_loan: SystemConfigService init failed: ' . $e->getMessage());
}

// Compute centralized defaults with safe fallbacks
$defaultTermMonths = '12';
try {
    if ($sysConfig) {
        $defaultTermMonths = (string)$sysConfig->get('MAX_LOAN_DURATION', (int)$defaultTermMonths);
    }
} catch (Exception $e) {
    // keep fallback
}

$defaultInterestRate = '10';
try {
    if ($sysConfig) {
        $defaultInterestRate = (string)$sysConfig->get('DEFAULT_INTEREST_RATE', (float)$defaultInterestRate);
    }
} catch (Exception $e) {
    // keep fallback
}

// Get all active members for dropdown
$members = $memberController->getAllActiveMembers();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    if (empty($_POST['member_id'])) {
        $errors[] = "Member is required";
    }
    
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors[] = "Valid loan amount is required";
    }
    
    if (empty($_POST['purpose'])) {
        $errors[] = "Loan purpose is required";
    }
    
    if (empty($_POST['term_months']) || !is_numeric($_POST['term_months']) || $_POST['term_months'] <= 0) {
        $errors[] = "Valid loan term is required";
    }
    
    if (!isset($_POST['interest_rate']) || !is_numeric($_POST['interest_rate']) || $_POST['interest_rate'] < 0) {
        $errors[] = "Valid interest rate is required";
    }
    
    if (empty($_POST['application_date'])) {
        $errors[] = "Application date is required";
    }
    
    // If no errors, process the loan application
    if (empty($errors)) {
        $loanData = [
            'member_id' => $_POST['member_id'],
            'amount' => $_POST['amount'],
            'purpose' => $_POST['purpose'],
            'term' => $_POST['term_months'],
            'interest_rate' => $_POST['interest_rate'],
            'application_date' => $_POST['application_date'],
            'status' => 'pending',
            'collateral' => $_POST['collateral'] ?? '',
            'guarantor' => $_POST['guarantor'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        $result = $loanController->addLoanApplication($loanData);
        
        if ($result) {
            // Set success message and redirect
            $_SESSION['flash_message'] = "Loan application added successfully";
            $_SESSION['flash_message_class'] = "alert-success";
            header('Location: loans.php');
            exit();
        } else {
            $errors[] = "Failed to add loan application. Please try again.";
        }
    }
}

// Page title
$pageTitle = "Add Loan Application";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle; ?> - CSIMS</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 font-sans">
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include_once __DIR__ . '/../includes/header.php'; ?>
            
            <!-- Main Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6 pl-12">
                <div class="max-w-4xl mx-auto ml-12">
                <!-- Page Header -->
                <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl mb-8 shadow-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-bold mb-2"><i class="fas fa-plus-circle mr-4"></i><?php echo $pageTitle; ?></h1>
                            <p class="text-primary-100 text-lg">Create a new loan application for a member</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-primary-50 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Loans
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Error messages -->
                <?php if (!empty($errors)): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Error!</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            
                <!-- Loan Application Form -->
                <div class="bg-white shadow-lg rounded-2xl border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-file-alt mr-3 text-blue-600"></i>Loan Application Form
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Fill in the details below to create a new loan application</p>
                    </div>
                    <div class="p-6">
                        <form action="" method="POST" id="loanForm" class="space-y-8">
                            <!-- Applicant Information Section -->
                            <div class="bg-gray-50 rounded-lg p-6">
                                <h4 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                                    <i class="fas fa-user mr-3 text-blue-600"></i>Applicant Information
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="member_id" class="block text-sm font-medium text-gray-700 mb-2">Member <span class="text-red-500">*</span></label>
                                        <select id="member_id" name="member_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <option value="">Select Member</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo $member['member_id']; ?>" <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['member_id'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="application_date" class="block text-sm font-medium text-gray-700 mb-2">Application Date <span class="text-red-500">*</span></label>
                                        <input type="date" id="application_date" name="application_date" 
                                               value="<?php echo isset($_POST['application_date']) ? $_POST['application_date'] : date('Y-m-d'); ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Loan Details Section -->
                            <div class="bg-blue-50 rounded-lg p-6">
                                <h4 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                                    <i class="fas fa-money-bill-wave mr-3 text-blue-600"></i>Loan Details
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Loan Amount <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 font-medium">=N=</span>
                                            </div>
                                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" 
                                                   value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" required
                                                   class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="term_months" class="block text-sm font-medium text-gray-700 mb-2">Loan Term (Months) <span class="text-red-500">*</span></label>
                                        <input type="number" id="term_months" name="term_months" min="1" max="120" 
                                               value="<?php echo isset($_POST['term_months']) ? $_POST['term_months'] : $defaultTermMonths; ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                    </div>
                                    <div>
                                        <label for="interest_rate" class="block text-sm font-medium text-gray-700 mb-2">Interest Rate (% per annum) <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input type="number" id="interest_rate" name="interest_rate" step="0.01" min="0" 
                                                   value="<?php echo isset($_POST['interest_rate']) ? $_POST['interest_rate'] : $defaultInterestRate; ?>" required
                                                   class="w-full pr-10 pl-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 font-medium">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">Loan Purpose <span class="text-red-500">*</span></label>
                                    <textarea id="purpose" name="purpose" rows="3" required placeholder="Describe the purpose of this loan..."
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"><?php echo isset($_POST['purpose']) ? $_POST['purpose'] : ''; ?></textarea>
                                </div>
                            </div>
                                
                            <!-- Additional Information Section -->
                            <div class="bg-green-50 rounded-lg p-6">
                                <h4 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                                    <i class="fas fa-info-circle mr-3 text-green-600"></i>Additional Information
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="collateral" class="block text-sm font-medium text-gray-700 mb-2">Collateral (if any)</label>
                                        <textarea id="collateral" name="collateral" rows="3" placeholder="Describe any collateral offered..."
                                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"><?php echo isset($_POST['collateral']) ? $_POST['collateral'] : ''; ?></textarea>
                                    </div>
                                    <div>
                                        <label for="guarantor" class="block text-sm font-medium text-gray-700 mb-2">Guarantor (if any)</label>
                                        <textarea id="guarantor" name="guarantor" rows="3" placeholder="Provide guarantor details..."
                                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"><?php echo isset($_POST['guarantor']) ? $_POST['guarantor'] : ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional notes or comments..."
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-200">
                                <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                                <button type="submit" class="inline-flex justify-center items-center px-8 py-3 border border-transparent rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-check mr-2"></i>Submit Loan Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- End of Main Content -->
                </div>
            </main>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('loanForm').addEventListener('submit', function(event) {
            const form = event.target;
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(function(input) {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('border-red-500');
                    input.classList.remove('border-gray-300');
                } else {
                    input.classList.remove('border-red-500');
                    input.classList.add('border-gray-300');
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>
