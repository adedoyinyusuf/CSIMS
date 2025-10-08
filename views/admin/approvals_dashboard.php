<?php
/**
 * Admin Approvals Dashboard - Central hub for processing workflow approvals
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/workflow_controller.php';
require_once '../../controllers/loan_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();
$admin_id = $current_user['admin_id'];

// Initialize controllers
$workflowController = new WorkflowController();
$loanController = new LoanController();

// Handle approval action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $approval_id = (int)$_POST['approval_id'];
    $action = $_POST['action'];
    $comments = trim($_POST['comments'] ?? '');
    
    $result = $workflowController->processApproval($approval_id, $admin_id, $action, $comments);
    
    if ($result) {
        $_SESSION['flash_message'] = ucfirst($action) . ' processed successfully!';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Failed to process ' . $action . '. Please try again.';
        $_SESSION['flash_type'] = 'danger';
    }
    
    // Redirect to avoid resubmission
    header('Location: approvals_dashboard.php');
    exit();
}

// Get pending approvals for current admin
$pending_approvals = $workflowController->getPendingApprovalsForAdmin($admin_id);

// Get approval history (recent items)
$approval_history = $workflowController->getApprovalHistory([], 10, 0);

$pageTitle = "Approval Dashboard";
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                                <i class="fas fa-tasks mr-4"></i>Approval Dashboard
                            </h1>
                            <p class="text-primary-100 text-lg">Process loan applications, withdrawals, and other approval requests</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold"><?php echo count($pending_approvals); ?></div>
                            <div class="text-primary-200">Pending Items</div>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="mb-6 p-4 rounded-lg border-l-4 shadow-md flex items-center justify-between
                        <?php 
                        switch($_SESSION['flash_type']) {
                            case 'success':
                                echo 'bg-green-50 border-green-500 text-green-800';
                                break;
                            case 'danger':
                                echo 'bg-red-50 border-red-500 text-red-800';
                                break;
                            case 'warning':
                                echo 'bg-yellow-50 border-yellow-500 text-yellow-800';
                                break;
                            default:
                                echo 'bg-blue-50 border-blue-500 text-blue-800';
                        }
                        ?>">
                        <div class="flex items-center">
                            <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : ($_SESSION['flash_type'] === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> mr-3"></i>
                            <span class="font-medium"><?php echo $_SESSION['flash_message']; ?></span>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="this.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-yellow-600 uppercase tracking-wider mb-2">Pending Approvals</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo count($pending_approvals); ?></p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-clock text-2xl text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Approved Today</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    <?php 
                                    $today_approved = array_filter($approval_history, function($item) {
                                        return $item['status'] === 'approved' && 
                                               date('Y-m-d', strtotime($item['approved_at'])) === date('Y-m-d');
                                    });
                                    echo count($today_approved); 
                                    ?>
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-check-circle text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Rejected Today</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    <?php 
                                    $today_rejected = array_filter($approval_history, function($item) {
                                        return $item['status'] === 'rejected' && 
                                               date('Y-m-d', strtotime($item['approved_at'])) === date('Y-m-d');
                                    });
                                    echo count($today_rejected); 
                                    ?>
                                </p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-times-circle text-2xl text-red-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Your Role</p>
                                <p class="text-lg font-bold text-gray-800"><?php echo $current_user['role']; ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-user-shield text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-yellow-50 to-yellow-100">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-clock mr-3 text-yellow-600"></i>
                            Pending Approvals Requiring Your Action (<?php echo count($pending_approvals); ?>)
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($pending_approvals)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-clipboard-check text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Pending Approvals</h3>
                                <p class="text-gray-600">All approval items have been processed. Great work!</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($pending_approvals as $approval): ?>
                                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                                        <!-- Approval Header -->
                                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center space-x-4">
                                                    <div>
                                                        <h4 class="text-lg font-semibold text-gray-900">
                                                            <?php 
                                                            echo match($approval['workflow_type']) {
                                                                'loan_application' => 'Loan Application',
                                                                'loan_disbursement' => 'Loan Disbursement',
                                                                'contribution_withdrawal' => 'Contribution Withdrawal',
                                                                'penalty_waiver' => 'Penalty Waiver',
                                                                'dividend_declaration' => 'Dividend Declaration',
                                                                default => ucfirst(str_replace('_', ' ', $approval['workflow_type']))
                                                            };
                                                            ?> #<?php echo $approval['approval_id']; ?>
                                                        </h4>
                                                        <p class="text-sm text-gray-600">
                                                            Submitted: <?php echo date('M d, Y H:i', strtotime($approval['submitted_at'])); ?>
                                                            <?php if ($approval['reference_name']): ?>
                                                                | Member: <?php echo htmlspecialchars($approval['reference_name']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <?php if ($approval['reference_amount']): ?>
                                                        <div class="text-right">
                                                            <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($approval['reference_amount'], 2); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center space-x-3">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php 
                                                        echo match($approval['priority']) {
                                                            'urgent' => 'bg-red-100 text-red-800',
                                                            'high' => 'bg-orange-100 text-orange-800',
                                                            'normal' => 'bg-blue-100 text-blue-800',
                                                            'low' => 'bg-gray-100 text-gray-800',
                                                            default => 'bg-blue-100 text-blue-800'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($approval['priority']); ?> Priority
                                                    </span>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                                        Stage <?php echo $approval['current_stage']; ?>/<?php echo $approval['total_stages']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Approval Details -->
                                        <div class="p-6">
                                            <?php if (!empty($approval['notes'])): ?>
                                                <div class="mb-6">
                                                    <h5 class="text-sm font-medium text-gray-500 mb-2">Notes</h5>
                                                    <p class="text-gray-900 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($approval['notes'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($approval['workflow_type'] === 'loan_application'): ?>
                                                <!-- Enhanced loan details -->
                                                <?php 
                                                $loan = $loanController->getLoanById($approval['reference_id']);
                                                if ($loan):
                                                    $guarantors = $loanController->getLoanGuarantors($approval['reference_id']);
                                                    $collaterals = $loanController->getLoanCollateral($approval['reference_id']);
                                                ?>
                                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                                                        <!-- Loan Info -->
                                                        <div class="bg-blue-50 rounded-lg p-4">
                                                            <h5 class="font-semibold text-blue-800 flex items-center mb-3">
                                                                <i class="fas fa-info-circle mr-2"></i> Loan Details
                                                            </h5>
                                                            <div class="space-y-2 text-sm">
                                                                <div class="flex justify-between">
                                                                    <span class="text-blue-800">Purpose:</span>
                                                                    <span class="font-medium"><?php echo htmlspecialchars(substr($loan['purpose'], 0, 30)) . '...'; ?></span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-blue-800">Term:</span>
                                                                    <span class="font-medium"><?php echo $loan['term']; ?> months</span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-blue-800">Interest:</span>
                                                                    <span class="font-medium"><?php echo number_format($loan['interest_rate'], 1); ?>%</span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-blue-800">Monthly Payment:</span>
                                                                    <span class="font-medium">₦<?php echo number_format($loan['monthly_payment'], 2); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Guarantors -->
                                                        <div class="bg-green-50 rounded-lg p-4">
                                                            <h5 class="font-semibold text-green-800 flex items-center mb-3">
                                                                <i class="fas fa-users mr-2"></i> Guarantors (<?php echo count($guarantors); ?>)
                                                            </h5>
                                                            <div class="space-y-2 text-sm">
                                                                <?php foreach (array_slice($guarantors, 0, 2) as $guarantor): ?>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-green-800"><?php echo htmlspecialchars($guarantor['first_name'] . ' ' . $guarantor['last_name']); ?></span>
                                                                        <span class="font-medium">₦<?php echo number_format($guarantor['guarantee_amount'], 0); ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php if (count($guarantors) > 2): ?>
                                                                    <p class="text-xs text-green-600">+<?php echo count($guarantors) - 2; ?> more</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Collateral -->
                                                        <div class="bg-purple-50 rounded-lg p-4">
                                                            <h5 class="font-semibold text-purple-800 flex items-center mb-3">
                                                                <i class="fas fa-shield-alt mr-2"></i> Collateral (<?php echo count($collaterals); ?>)
                                                            </h5>
                                                            <div class="space-y-2 text-sm">
                                                                <?php foreach (array_slice($collaterals, 0, 2) as $collateral): ?>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-purple-800"><?php echo ucfirst($collateral['collateral_type']); ?></span>
                                                                        <span class="font-medium">₦<?php echo number_format($collateral['estimated_value'], 0); ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php if (count($collaterals) > 2): ?>
                                                                    <p class="text-xs text-purple-600">+<?php echo count($collaterals) - 2; ?> more</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <!-- Action Buttons -->
                                            <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                                <div class="flex space-x-3">
                                                    <a href="view_loan_enhanced.php?id=<?php echo $approval['reference_id']; ?>" 
                                                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 transition-colors duration-200">
                                                        <i class="fas fa-eye mr-2"></i> View Details
                                                    </a>
                                                </div>
                                                <div class="flex space-x-3">
                                                    <button onclick="showApprovalModal(<?php echo $approval['approval_id']; ?>, 'reject')" 
                                                            class="inline-flex items-center px-6 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors duration-200">
                                                        <i class="fas fa-times mr-2"></i> Reject
                                                    </button>
                                                    <button onclick="showApprovalModal(<?php echo $approval['approval_id']; ?>, 'approve')" 
                                                            class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition-colors duration-200">
                                                        <i class="fas fa-check mr-2"></i> Approve
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Approval History -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-history mr-3 text-blue-600"></i>
                            Recent Approval History
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($approval_history)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No approval history available.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Reference</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Amount</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500">Submitted</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500">Processed</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($approval_history as $item): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        <?php echo ucfirst(str_replace('_', ' ', $item['workflow_type'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="font-medium"><?php echo htmlspecialchars($item['reference_name'] ?? 'N/A'); ?></div>
                                                    <div class="text-gray-500">#<?php echo $item['reference_id']; ?></div>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <?php if ($item['reference_amount']): ?>
                                                        ₦<?php echo number_format($item['reference_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                        echo match($item['status']) {
                                                            'approved' => 'bg-green-100 text-green-800',
                                                            'rejected' => 'bg-red-100 text-red-800',
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'in_progress' => 'bg-blue-100 text-blue-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($item['submitted_at'])); ?>
                                                </td>
                                                <td class="px-4 py-3 text-center text-gray-500">
                                                    <?php echo $item['approved_at'] ? date('M d, Y', strtotime($item['approved_at'])) : '-'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-md w-full">
                <form method="POST" action="">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Confirm Action</h3>
                    </div>
                    <div class="p-6">
                        <input type="hidden" name="approval_id" id="approvalId">
                        <input type="hidden" name="action" id="approvalAction">
                        
                        <div class="mb-4">
                            <label for="comments" class="block text-sm font-medium text-gray-700 mb-2">
                                Comments <span id="commentsRequired" class="text-red-500 hidden">*</span>
                            </label>
                            <textarea name="comments" id="comments" rows="3" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                     placeholder="Add your comments here..."></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeApprovalModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="confirmButton"
                                class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showApprovalModal(approvalId, action) {
            document.getElementById('approvalId').value = approvalId;
            document.getElementById('approvalAction').value = action;
            
            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('modalTitle');
            const button = document.getElementById('confirmButton');
            const commentsRequired = document.getElementById('commentsRequired');
            const comments = document.getElementById('comments');
            
            if (action === 'approve') {
                title.textContent = 'Approve Item';
                button.textContent = 'Approve';
                button.className = 'px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors';
                commentsRequired.classList.add('hidden');
                comments.required = false;
            } else {
                title.textContent = 'Reject Item';
                button.textContent = 'Reject';
                button.className = 'px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors';
                commentsRequired.classList.remove('hidden');
                comments.required = true;
            }
            
            modal.classList.remove('hidden');
            comments.focus();
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.getElementById('comments').value = '';
        }
    </script>
</body>
</html>
