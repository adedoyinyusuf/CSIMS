<?php
/**
 * Enhanced Admin View Loan - Shows detailed loan information including guarantors and collateral
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if loan ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = 'Loan ID is required';
    $_SESSION['flash_type'] = 'danger';
    header('Location: loans.php');
    exit();
}

$loan_id = (int)$_GET['id'];

// Initialize controllers
$loanController = new LoanController();
$memberController = new MemberController();

// Get loan details
$loan = $loanController->getLoanById($loan_id);
if (!$loan) {
    $_SESSION['flash_message'] = 'Loan not found';
    $_SESSION['flash_type'] = 'danger';
    header('Location: loans.php');
    exit();
}

// Get enhanced loan data
$guarantors = $loanController->getLoanGuarantors($loan_id);
$collaterals = $loanController->getLoanCollateral($loan_id);
$paymentSchedule = $loanController->getLoanPaymentSchedule($loan_id);

// Calculate totals
$totalGuaranteeAmount = 0;
foreach ($guarantors as $guarantor) {
    $totalGuaranteeAmount += $guarantor['guarantee_amount'];
}

$totalCollateralValue = 0;
foreach ($collaterals as $collateral) {
    $totalCollateralValue += $collateral['estimated_value'];
}

$pageTitle = "View Loan Details";
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
    <!-- Page Wrapper -->
    <div class="wrapper">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div class="main-content">
            <?php include_once __DIR__ . '/../includes/header.php'; ?>

            <!-- Begin Page Content -->
            <div class="p-6">
                <!-- Page Header -->
                <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white p-8 rounded-2xl mb-8 shadow-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-bold mb-2">
                                <i class="fas fa-eye mr-4"></i>Enhanced Loan Details
                            </h1>
                            <p class="text-primary-100 text-lg">Comprehensive loan information with guarantors and collateral</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="loans.php" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-primary-50 transition-all duration-200 shadow-md">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Loans
                            </a>
                            <a href="edit_loan.php?id=<?php echo $loan['loan_id']; ?>" class="border-2 border-white text-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-primary-600 transition-all duration-200">
                                <i class="fas fa-edit mr-2"></i>Edit Loan
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Loan Basic Information -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                    <!-- Loan Details Card -->
                    <div class="xl:col-span-2">
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-file-contract mr-3 text-blue-600"></i>Loan Information
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Loan ID</label>
                                        <div class="text-lg font-semibold text-gray-900">#<?php echo $loan['loan_id']; ?></div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php 
                                            echo match(strtolower($loan['status'])) {
                                                'approved' => 'bg-green-100 text-green-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'disbursed' => 'bg-blue-100 text-blue-800',
                                                'paid' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <i class="fas fa-circle w-2 h-2 mr-2"></i>
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Loan Amount</label>
                                        <div class="text-lg font-semibold text-gray-900">₦<?php echo number_format($loan['amount'], 2); ?></div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Interest Rate</label>
                                        <div class="text-lg font-semibold text-gray-900"><?php echo number_format($loan['interest_rate'], 2); ?>%</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Term</label>
                                        <div class="text-lg font-semibold text-gray-900"><?php echo $loan['term']; ?> months</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Monthly Payment</label>
                                        <div class="text-lg font-semibold text-gray-900">₦<?php echo number_format($loan['monthly_payment'], 2); ?></div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Application Date</label>
                                        <div class="text-lg font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Amount Paid</label>
                                        <div class="text-lg font-semibold text-green-600">₦<?php echo number_format($loan['amount_paid'] ?? 0, 2); ?></div>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-500 mb-2">Purpose</label>
                                    <div class="text-gray-900 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($loan['purpose'])); ?></div>
                                </div>
                                <?php if (!empty($loan['notes'])): ?>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-500 mb-2">Notes</label>
                                    <div class="text-gray-900 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($loan['notes'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Member Information -->
                    <div class="xl:col-span-1">
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-green-100">
                                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-user mr-3 text-green-600"></i>Borrower Information
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="text-center mb-4">
                                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-user text-green-600 text-2xl"></i>
                                    </div>
                                    <h4 class="text-xl font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>
                                    </h4>
                                    <p class="text-gray-600">Member ID: <?php echo $loan['member_id']; ?></p>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Email</label>
                                        <div class="text-gray-900"><?php echo htmlspecialchars($loan['email']); ?></div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Phone</label>
                                        <div class="text-gray-900"><?php echo htmlspecialchars($loan['phone']); ?></div>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <a href="view_member.php?id=<?php echo $loan['member_id']; ?>" class="w-full inline-flex justify-center items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="fas fa-user-circle mr-2"></i>View Member Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guarantors Section -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-green-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-users mr-3 text-green-600"></i>
                                Loan Guarantors (<?php echo count($guarantors); ?>)
                            </h3>
                            <div class="text-sm text-gray-600">
                                Total Guarantee: <span class="font-bold text-green-600">₦<?php echo number_format($totalGuaranteeAmount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($guarantors)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No guarantors found for this loan.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($guarantors as $guarantor): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-green-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($guarantor['first_name'] . ' ' . $guarantor['last_name']); ?>
                                                </h4>
                                                <p class="text-sm text-gray-500">ID: <?php echo $guarantor['guarantor_member_id']; ?></p>
                                            </div>
                                        </div>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Amount:</span>
                                                <span class="font-medium">₦<?php echo number_format($guarantor['guarantee_amount'], 2); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Type:</span>
                                                <span class="font-medium"><?php echo ucfirst($guarantor['guarantee_type']); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Relationship:</span>
                                                <span class="font-medium"><?php echo htmlspecialchars($guarantor['relationship_to_borrower'] ?: 'N/A'); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Status:</span>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match(strtolower($guarantor['status'])) {
                                                        'active' => 'bg-green-100 text-green-800',
                                                        'released' => 'bg-blue-100 text-blue-800',
                                                        'called' => 'bg-yellow-100 text-yellow-800',
                                                        'defaulted' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($guarantor['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <a href="view_member.php?id=<?php echo $guarantor['guarantor_member_id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                                                <i class="fas fa-eye mr-1"></i>View Details
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Collateral Section -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-shield-alt mr-3 text-blue-600"></i>
                                Loan Collateral (<?php echo count($collaterals); ?>)
                            </h3>
                            <div class="text-sm text-gray-600">
                                Total Value: <span class="font-bold text-blue-600">₦<?php echo number_format($totalCollateralValue, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($collaterals)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-shield-alt text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No collateral found for this loan.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <?php foreach ($collaterals as $collateral): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-start justify-between mb-3">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-<?php 
                                                        echo match($collateral['collateral_type']) {
                                                            'property' => 'home',
                                                            'vehicle' => 'car',
                                                            'shares' => 'chart-line',
                                                            'savings' => 'piggy-bank',
                                                            'gold' => 'gem',
                                                            'equipment' => 'cogs',
                                                            default => 'box'
                                                        };
                                                    ?> text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-medium text-gray-900"><?php echo ucfirst($collateral['collateral_type']); ?></h4>
                                                    <p class="text-sm text-blue-600 font-medium">₦<?php echo number_format($collateral['estimated_value'], 2); ?></p>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                echo match(strtolower($collateral['status'])) {
                                                    'pledged' => 'bg-blue-100 text-blue-800',
                                                    'held' => 'bg-yellow-100 text-yellow-800',
                                                    'released' => 'bg-green-100 text-green-800',
                                                    'liquidated' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            ?>">
                                                <?php echo ucfirst($collateral['status']); ?>
                                            </span>
                                        </div>
                                        <div class="space-y-2 text-sm text-gray-600">
                                            <div>
                                                <span class="font-medium">Description:</span>
                                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($collateral['description'])); ?></p>
                                            </div>
                                            <?php if (!empty($collateral['location'])): ?>
                                            <div>
                                                <span class="font-medium">Location:</span>
                                                <span><?php echo htmlspecialchars($collateral['location']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($collateral['document_reference'])): ?>
                                            <div>
                                                <span class="font-medium">Document Ref:</span>
                                                <span class="font-mono text-xs"><?php echo htmlspecialchars($collateral['document_reference']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($collateral['insurance_details'])): ?>
                                            <div>
                                                <span class="font-medium">Insurance:</span>
                                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($collateral['insurance_details'])); ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Schedule Section -->
                <?php if (!empty($paymentSchedule)): ?>
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-purple-100">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-calendar-alt mr-3 text-purple-600"></i>
                            Payment Schedule (<?php echo count($paymentSchedule); ?> payments)
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">Due Date</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">Opening Balance</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">Principal</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">Interest</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">Total Payment</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">Closing Balance</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($paymentSchedule as $payment): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium"><?php echo $payment['payment_number']; ?></td>
                                            <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($payment['due_date'])); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo number_format($payment['opening_balance'], 2); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo number_format($payment['principal_amount'], 2); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo number_format($payment['interest_amount'], 2); ?></td>
                                            <td class="px-4 py-3 text-right font-medium">₦<?php echo number_format($payment['total_amount'], 2); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo number_format($payment['closing_balance'], 2); ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match(strtolower($payment['payment_status'])) {
                                                        'paid' => 'bg-green-100 text-green-800',
                                                        'partial' => 'bg-yellow-100 text-yellow-800',
                                                        'overdue' => 'bg-red-100 text-red-800',
                                                        'pending' => 'bg-gray-100 text-gray-800',
                                                        'waived' => 'bg-blue-100 text-blue-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($payment['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add any necessary JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips or other UI enhancements
        });
    </script>
</body>
</html>
