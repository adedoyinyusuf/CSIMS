<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/loan_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

require_once __DIR__ . '/../controllers/loan_controller.php';
$loanController = new LoanController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get all active members for guarantor selection
$activeMembers = $memberController->getActiveMembers($member_id); // Exclude current member

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $term_months = trim($_POST['term_months'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Guarantors data
    $guarantors = [];
    if (isset($_POST['guarantors']) && is_array($_POST['guarantors'])) {
        foreach ($_POST['guarantors'] as $guarantor_data) {
            if (!empty($guarantor_data['member_id']) && !empty($guarantor_data['guarantee_amount'])) {
                $guarantors[] = [
                    'member_id' => (int)$guarantor_data['member_id'],
                    'guarantee_amount' => (float)$guarantor_data['guarantee_amount'],
                    'guarantee_percentage' => (float)($guarantor_data['guarantee_percentage'] ?? 100),
                    'guarantee_type' => $guarantor_data['guarantee_type'] ?? 'full',
                    'relationship' => trim($guarantor_data['relationship'] ?? '')
                ];
            }
        }
    }
    
    // Collateral data
    $collaterals = [];
    if (isset($_POST['collaterals']) && is_array($_POST['collaterals'])) {
        foreach ($_POST['collaterals'] as $collateral_data) {
            if (!empty($collateral_data['type']) && !empty($collateral_data['description'])) {
                $collaterals[] = [
                    'type' => $collateral_data['type'],
                    'description' => trim($collateral_data['description']),
                    'estimated_value' => (float)($collateral_data['estimated_value'] ?? 0),
                    'location' => trim($collateral_data['location'] ?? ''),
                    'document_reference' => trim($collateral_data['document_reference'] ?? ''),
                    'insurance_details' => trim($collateral_data['insurance_details'] ?? '')
                ];
            }
        }
    }
    
    // Validation
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = 'Please enter a valid loan amount.';
    }
    
    if (empty($purpose)) {
        $errors[] = 'Please specify the purpose of the loan.';
    }
    
    if (empty($term_months) || !is_numeric($term_months) || $term_months <= 0 || $term_months > 60) {
        $errors[] = 'Please enter a valid loan term (1-60 months).';
    }
    
    if (empty($guarantors)) {
        $errors[] = 'Please add at least one guarantor.';
    }
    
    if (empty($collaterals)) {
        $errors[] = 'Please add at least one collateral item.';
    }
    
    // Validate total guarantee amount
    $totalGuaranteeAmount = array_sum(array_column($guarantors, 'guarantee_amount'));
    if ($totalGuaranteeAmount < $amount) {
        $errors[] = 'Total guarantee amount must be at least equal to the loan amount.';
    }
    
    if (empty($errors)) {
        // Calculate interest rate and monthly payment
        $interest_rate = $loanController->getInterestRate((float)$amount, (int)$term_months);
        
        $loan_data = [
            'member_id' => $member_id,
            'amount' => $amount,
            'purpose' => $purpose,
            'term_months' => $term_months,
            'interest_rate' => $interest_rate,
            'application_date' => date('Y-m-d'),
            'status' => 'Pending',
            'notes' => $notes,
            'guarantors' => $guarantors,
            'collaterals' => $collaterals
        ];
        
        $loan_id = $loanController->addEnhancedLoanApplication($loan_data);
        
        if ($loan_id) {
            // Submit for workflow approval
            require_once __DIR__ . '/../controllers/workflow_controller.php';
            $workflowController = new WorkflowController();
            
            $workflow_data = [
                'workflow_type' => 'loan_application',
                'reference_id' => $loan_id,
                'member_id' => $member_id,
                'submitted_by' => 1, // Default admin ID - you may need to adjust this
                'amount' => $amount, // For approval chain determination
                'priority' => 'normal',
                'notes' => "Loan application submitted by member: {$member['first_name']} {$member['last_name']}"
            ];
            
            $approval_id = $workflowController->submitForApproval($workflow_data);
            
            if ($approval_id) {
                $success = true;
                // Clear form data on success
                $amount = $purpose = $term_months = $notes = '';
                $guarantors = [];
                $collaterals = [];
            } else {
                // Loan created but approval submission failed
                $errors[] = 'Loan application created but approval workflow failed. Please contact administrator.';
            }
        } else {
            $errors[] = 'Failed to submit loan application. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Loan Application - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-br from-primary-600 to-primary-800 shadow-xl">
            <div class="flex flex-col h-full p-6">
                <h4 class="text-white text-xl font-bold mb-6">
                    <i class="fas fa-university mr-2"></i> Member Portal
                </h4>
                
                <div class="mb-6">
                    <small class="text-primary-200">Welcome,</small>
                    <div class="text-white font-semibold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                </div>
                
                <nav class="flex-1 space-y-2">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_dashboard.php">
                        <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_profile.php">
                        <i class="fas fa-user mr-3"></i> My Profile
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_loans.php">
                        <i class="fas fa-money-bill-wave mr-3"></i> My Loans
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_contributions.php">
                        <i class="fas fa-piggy-bank mr-3"></i> My Contributions
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_notifications.php">
                        <i class="fas fa-bell mr-3"></i> Notifications
                    </a>
                    <a class="flex items-center px-4 py-3 text-white bg-white/20 rounded-lg font-medium" href="member_loan_application_enhanced.php">
                        <i class="fas fa-plus-circle mr-3"></i> Apply for Loan
                    </a>
                </nav>
                
                <div class="mt-auto">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_logout.php">
                        <i class="fas fa-sign-out-alt mr-3"></i> Logout
                    </a>
                </div>
            </div>
        </div>
            
        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-plus-circle mr-3 text-primary-600"></i> Enhanced Loan Application
                        </h1>
                        <p class="text-gray-600 mt-2">Submit your loan application with detailed guarantors and collateral information</p>
                    </div>
                    <a href="member_loans.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i> Back to My Loans
                    </a>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Application Submitted Successfully!</h3>
                                <p class="mt-2 text-sm text-green-700">Your enhanced loan application has been submitted and is pending review. You will be notified once it's processed.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                    
                <form method="POST" action="" class="space-y-8">
                    <!-- Basic Loan Information -->
                    <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-primary-50 to-primary-100">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-file-alt mr-2 text-primary-600"></i>
                                Basic Loan Information
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                        Loan Amount (₦) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="amount" name="amount" value="<?php echo htmlspecialchars($amount ?? ''); ?>" min="1000" required>
                                </div>
                                <div>
                                    <label for="term_months" class="block text-sm font-medium text-gray-700 mb-2">
                                        Loan Term (Months) <span class="text-red-500">*</span>
                                    </label>
                                    <select class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="term_months" name="term_months" required>
                                        <option value="">Select loan term...</option>
                                        <?php for ($i = 1; $i <= 36; $i++) { ?>
                                            <option value="<?php echo $i; ?>" <?php echo (isset($term_months) && $term_months == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-6">
                                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                    Purpose of Loan <span class="text-red-500">*</span>
                                </label>
                                <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="purpose" name="purpose" rows="3" required placeholder="Please describe the purpose of this loan..."><?php echo htmlspecialchars($purpose ?? ''); ?></textarea>
                            </div>
                            <div class="mt-6">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Additional Notes
                                </label>
                                <textarea class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors duration-200" id="notes" name="notes" rows="2" placeholder="Any additional information..."><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Guarantors Section -->
                    <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-green-100">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center justify-between">
                                <span><i class="fas fa-users mr-2 text-green-600"></i> Loan Guarantors</span>
                                <button type="button" onclick="addGuarantor()" class="inline-flex items-center px-3 py-1 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors">
                                    <i class="fas fa-plus mr-1"></i> Add Guarantor
                                </button>
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Add one or more guarantors for this loan application</p>
                        </div>
                        <div class="p-6">
                            <div id="guarantors-container">
                                <!-- Guarantor forms will be added here by JavaScript -->
                            </div>
                            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-medium text-gray-700">Total Guarantee Amount:</span>
                                    <span class="font-bold text-green-600" id="total-guarantee">₦0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collateral Section -->
                    <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center justify-between">
                                <span><i class="fas fa-shield-alt mr-2 text-blue-600"></i> Loan Collateral</span>
                                <button type="button" onclick="addCollateral()" class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-plus mr-1"></i> Add Collateral
                                </button>
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Add one or more collateral items to secure this loan</p>
                        </div>
                        <div class="p-6">
                            <div id="collaterals-container">
                                <!-- Collateral forms will be added here by JavaScript -->
                            </div>
                            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-medium text-gray-700">Total Collateral Value:</span>
                                    <span class="font-bold text-blue-600" id="total-collateral">₦0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button type="reset" onclick="resetForm()" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                            <i class="fas fa-undo mr-2"></i> Reset Form
                        </button>
                        <button type="submit" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
let guarantorCount = 0;
let collateralCount = 0;

// Active members data for guarantor selection
const activeMembers = <?php echo json_encode($activeMembers); ?>;

// Add guarantor form
function addGuarantor() {
    guarantorCount++;
    const container = document.getElementById('guarantors-container');
    const guarantorForm = `
        <div class="guarantor-form border border-gray-200 rounded-lg p-4 mb-4" data-index="${guarantorCount}">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-sm font-medium text-gray-900">Guarantor ${guarantorCount}</h4>
                <button type="button" onclick="removeGuarantor(${guarantorCount})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Member <span class="text-red-500">*</span></label>
                    <select name="guarantors[${guarantorCount}][member_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        <option value="">Choose a member...</option>
                        ${activeMembers.map(member => 
                            `<option value="${member.member_id}">${member.first_name} ${member.last_name} (ID: ${member.member_id})</option>`
                        ).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Guarantee Amount (₦) <span class="text-red-500">*</span></label>
                    <input type="number" name="guarantors[${guarantorCount}][guarantee_amount]" class="guarantee-amount w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" min="1000" required onchange="updateTotalGuarantee()">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Guarantee Type</label>
                    <select name="guarantors[${guarantorCount}][guarantee_type]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="full">Full Guarantee</option>
                        <option value="partial">Partial Guarantee</option>
                        <option value="joint">Joint Guarantee</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Relationship to Borrower</label>
                    <input type="text" name="guarantors[${guarantorCount}][relationship]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., Colleague, Friend, Family">
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', guarantorForm);
}

// Remove guarantor form
function removeGuarantor(index) {
    const guarantorForm = document.querySelector(`[data-index="${index}"]`);
    if (guarantorForm) {
        guarantorForm.remove();
        updateTotalGuarantee();
    }
}

// Add collateral form
function addCollateral() {
    collateralCount++;
    const container = document.getElementById('collaterals-container');
    const collateralForm = `
        <div class="collateral-form border border-gray-200 rounded-lg p-4 mb-4" data-index="${collateralCount}">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-sm font-medium text-gray-900">Collateral ${collateralCount}</h4>
                <button type="button" onclick="removeCollateral(${collateralCount})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Collateral Type <span class="text-red-500">*</span></label>
                    <select name="collaterals[${collateralCount}][type]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        <option value="">Select type...</option>
                        <option value="property">Property/Real Estate</option>
                        <option value="vehicle">Vehicle</option>
                        <option value="shares">Shares/Securities</option>
                        <option value="savings">Savings Account</option>
                        <option value="gold">Gold/Jewelry</option>
                        <option value="equipment">Equipment/Machinery</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Value (₦) <span class="text-red-500">*</span></label>
                    <input type="number" name="collaterals[${collateralCount}][estimated_value]" class="collateral-value w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" min="1000" required onchange="updateTotalCollateral()">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                    <textarea name="collaterals[${collateralCount}][description]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" rows="2" placeholder="Detailed description of the collateral..." required></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                    <input type="text" name="collaterals[${collateralCount}][location]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Physical location or address">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Document Reference</label>
                    <input type="text" name="collaterals[${collateralCount}][document_reference]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., Title deed number, Registration number">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Insurance Details</label>
                    <textarea name="collaterals[${collateralCount}][insurance_details]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" rows="2" placeholder="Insurance company, policy number, coverage amount..."></textarea>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', collateralForm);
}

// Remove collateral form
function removeCollateral(index) {
    const collateralForm = document.querySelector(`[data-index="${index}"]`);
    if (collateralForm) {
        collateralForm.remove();
        updateTotalCollateral();
    }
}

// Update total guarantee amount
function updateTotalGuarantee() {
    const guaranteeAmounts = document.querySelectorAll('.guarantee-amount');
    let total = 0;
    guaranteeAmounts.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });
    document.getElementById('total-guarantee').textContent = '₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Update total collateral value
function updateTotalCollateral() {
    const collateralValues = document.querySelectorAll('.collateral-value');
    let total = 0;
    collateralValues.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });
    document.getElementById('total-collateral').textContent = '₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Reset form
function resetForm() {
    document.getElementById('guarantors-container').innerHTML = '';
    document.getElementById('collaterals-container').innerHTML = '';
    guarantorCount = 0;
    collateralCount = 0;
    updateTotalGuarantee();
    updateTotalCollateral();
}

// Initialize with one guarantor and one collateral
document.addEventListener('DOMContentLoaded', function() {
    addGuarantor();
    addCollateral();
});
</script>

</body>
</html>
