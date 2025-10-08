<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/contribution_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$contributionController = new ContributionController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get enhanced contribution data
$stats = $contributionController->getMemberContributionStats($member_id);
$contributions = $contributionController->getContributionsByMemberId($member_id);
$targets = $contributionController->getMemberContributionTargets($member_id);
$withdrawals = $contributionController->getMemberWithdrawals($member_id);
$shares = $contributionController->getMemberShares($member_id);

// Handle form submissions
$errors = [];
$success = false;
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'request_withdrawal') {
        $withdrawal_data = [
            'member_id' => $member_id,
            'withdrawal_type' => $_POST['withdrawal_type'],
            'amount' => (float)$_POST['amount'],
            'contribution_types' => $_POST['contribution_types'],
            'withdrawal_date' => $_POST['withdrawal_date'],
            'reason' => $_POST['reason'],
            'supporting_documents' => $_POST['supporting_documents'] ?? ''
        ];
        
        $withdrawal_id = $contributionController->submitWithdrawalRequest($withdrawal_data);
        
        if ($withdrawal_id) {
            $success = true;
            $action_message = 'Withdrawal request submitted successfully and is pending approval.';
            // Refresh data
            $withdrawals = $contributionController->getMemberWithdrawals($member_id);
        } else {
            $errors[] = 'Failed to submit withdrawal request. Please try again.';
        }
    }
    
    if ($action === 'purchase_shares') {
        $share_data = [
            'member_id' => $member_id,
            'share_type' => $_POST['share_type'],
            'number_of_shares' => (int)$_POST['number_of_shares'],
            'par_value' => (float)$_POST['par_value'],
            'purchase_date' => $_POST['purchase_date'],
            'payment_status' => $_POST['payment_status'],
            'amount_paid' => (float)$_POST['amount_paid'],
            'dividend_eligible' => isset($_POST['dividend_eligible']) ? 1 : 0,
            'payment_method' => $_POST['payment_method']
        ];
        
        $share_id = $contributionController->purchaseShares($share_data);
        
        if ($share_id) {
            $success = true;
            $action_message = 'Share purchase completed successfully!';
            // Refresh data
            $stats = $contributionController->getMemberContributionStats($member_id);
            $shares = $contributionController->getMemberShares($member_id);
        } else {
            $errors[] = 'Failed to purchase shares. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Contributions - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-br from-primary-600 to-purple-700 shadow-xl">
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
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_loans_enhanced.php">
                        <i class="fas fa-money-bill-wave mr-3"></i> My Loans
                    </a>
                    <a class="flex items-center px-4 py-3 text-white bg-white/20 rounded-lg font-medium" href="member_contributions_enhanced.php">
                        <i class="fas fa-piggy-bank mr-3"></i> My Contributions
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_notifications.php">
                        <i class="fas fa-bell mr-3"></i> Notifications
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="member_loan_application_enhanced.php">
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
                            <i class="fas fa-piggy-bank mr-3 text-primary-600"></i> Enhanced Contributions
                        </h1>
                        <p class="text-gray-600 mt-2">Manage your contributions, targets, withdrawals, and share capital</p>
                    </div>
                </div>
                
                <!-- Flash Messages -->
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
                                <h3 class="text-sm font-medium text-green-800">Success!</h3>
                                <p class="mt-2 text-sm text-green-700"><?php echo htmlspecialchars($action_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Enhanced Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Total Contributions</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($stats['total_contributions'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $stats['contribution_count']; ?> payments</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-coins text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Share Capital</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($stats['total_share_value'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $stats['total_shares']; ?> shares</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-chart-pie text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Withdrawals</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($stats['total_withdrawals'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $stats['withdrawal_count']; ?> requests</p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-arrow-down text-2xl text-red-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-purple-600 uppercase tracking-wider mb-2">Net Position</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($stats['net_contributions'], 2); ?></p>
                                <p class="text-sm text-gray-500">Available balance</p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-balance-scale text-2xl text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-4 mb-8">
                    <button onclick="showWithdrawalModal()" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors duration-200 shadow-lg">
                        <i class="fas fa-arrow-down mr-2"></i> Request Withdrawal
                    </button>
                    <button onclick="showSharePurchaseModal()" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 shadow-lg">
                        <i class="fas fa-chart-pie mr-2"></i> Purchase Shares
                    </button>
                    <a href="member_contribution_targets.php" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors duration-200 shadow-lg">
                        <i class="fas fa-bullseye mr-2"></i> Set Targets
                    </a>
                </div>

                <!-- Tabs Navigation -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button onclick="showTab('contributions')" class="tab-button active border-b-2 border-primary-500 py-2 px-1 text-sm font-medium text-primary-600">
                                <i class="fas fa-history mr-2"></i> Contributions History
                            </button>
                            <button onclick="showTab('targets')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-bullseye mr-2"></i> Targets (<?php echo count($targets); ?>)
                            </button>
                            <button onclick="showTab('withdrawals')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-arrow-down mr-2"></i> Withdrawals (<?php echo count($withdrawals); ?>)
                            </button>
                            <button onclick="showTab('shares')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-chart-pie mr-2"></i> Share Portfolio (<?php echo count($shares); ?>)
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Tab Contents -->
                
                <!-- Contributions History Tab -->
                <div id="contributions-tab" class="tab-content">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-history mr-3 text-primary-600"></i> Contributions History
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($contributions)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-piggy-bank text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No contributions found.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                                <th class="px-4 py-3 text-right font-medium text-gray-500">Amount</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Payment Method</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Receipt</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($contributions as $contribution): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></td>
                                                    <td class="px-4 py-3 text-right font-medium text-green-600">₦<?php echo number_format($contribution['amount'], 2); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <?php echo htmlspecialchars($contribution['contribution_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3"><?php echo htmlspecialchars($contribution['payment_method'] ?? 'N/A'); ?></td>
                                                    <td class="px-4 py-3">
                                                        <?php if (!empty($contribution['receipt_number'])): ?>
                                                            <code class="text-xs"><?php echo htmlspecialchars($contribution['receipt_number']); ?></code>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <?php if (!empty($contribution['description'])): ?>
                                                            <span class="text-gray-600"><?php echo htmlspecialchars($contribution['description']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
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

                <!-- Targets Tab -->
                <div id="targets-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-bullseye mr-3 text-green-600"></i> Contribution Targets
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($targets)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-bullseye text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No contribution targets set.</p>
                                    <a href="member_contribution_targets.php" class="mt-4 inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="fas fa-plus mr-2"></i> Set Your First Target
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($targets as $target): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <h4 class="font-medium text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $target['target_type'])); ?></h4>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match($target['achievement_status']) {
                                                        'achieved' => 'bg-green-100 text-green-800',
                                                        'in_progress' => 'bg-yellow-100 text-yellow-800',
                                                        'overdue' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $target['achievement_status'])); ?>
                                                </span>
                                            </div>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Target:</span>
                                                    <span class="font-medium">₦<?php echo number_format($target['target_amount'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Achieved:</span>
                                                    <span class="font-medium text-green-600">₦<?php echo number_format($target['amount_achieved'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Progress:</span>
                                                    <span class="font-medium"><?php echo number_format($target['achievement_percentage'], 1); ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min(100, $target['achievement_percentage']); ?>%"></div>
                                                </div>
                                                <div class="flex justify-between pt-2">
                                                    <span class="text-gray-500">Period:</span>
                                                    <span class="text-xs text-gray-600">
                                                        <?php echo date('M d, Y', strtotime($target['target_period_start'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($target['target_period_end'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Withdrawals Tab -->
                <div id="withdrawals-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-arrow-down mr-3 text-red-600"></i> Withdrawal Requests
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($withdrawals)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-arrow-down text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No withdrawal requests found.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($withdrawals as $withdrawal): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <div>
                                                    <h4 class="font-medium text-gray-900">₦<?php echo number_format($withdrawal['amount'], 2); ?></h4>
                                                    <p class="text-sm text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_type'])); ?> withdrawal</p>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match($withdrawal['approval_status']) {
                                                        'processed' => 'bg-green-100 text-green-800',
                                                        'approved' => 'bg-blue-100 text-blue-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'rejected' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($withdrawal['approval_status']); ?>
                                                </span>
                                            </div>
                                            <div class="text-sm text-gray-600 space-y-1">
                                                <p><span class="font-medium">Requested:</span> <?php echo date('M d, Y', strtotime($withdrawal['withdrawal_date'])); ?></p>
                                                <p><span class="font-medium">Fee:</span> ₦<?php echo number_format($withdrawal['withdrawal_fee'], 2); ?></p>
                                                <p><span class="font-medium">Net Amount:</span> ₦<?php echo number_format($withdrawal['net_amount'], 2); ?></p>
                                                <p><span class="font-medium">Reason:</span> <?php echo htmlspecialchars($withdrawal['reason']); ?></p>
                                                <?php if ($withdrawal['reference_number']): ?>
                                                    <p><span class="font-medium">Reference:</span> <?php echo htmlspecialchars($withdrawal['reference_number']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Shares Tab -->
                <div id="shares-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-chart-pie mr-3 text-blue-600"></i> Share Portfolio
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($shares)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-chart-pie text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No shares purchased yet.</p>
                                    <button onclick="showSharePurchaseModal()" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-plus mr-2"></i> Purchase Your First Shares
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($shares as $share): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <div>
                                                    <h4 class="font-medium text-gray-900"><?php echo ucfirst($share['share_type']); ?> Shares</h4>
                                                    <p class="text-sm text-gray-600">Certificate: <?php echo htmlspecialchars($share['certificate_number']); ?></p>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match($share['payment_status']) {
                                                        'paid' => 'bg-green-100 text-green-800',
                                                        'partial' => 'bg-yellow-100 text-yellow-800',
                                                        'pending' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($share['payment_status']); ?>
                                                </span>
                                            </div>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Shares:</span>
                                                    <span class="font-medium"><?php echo number_format($share['number_of_shares']); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Par Value:</span>
                                                    <span class="font-medium">₦<?php echo number_format($share['par_value'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Total Value:</span>
                                                    <span class="font-medium text-blue-600">₦<?php echo number_format($share['total_value'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Amount Paid:</span>
                                                    <span class="font-medium text-green-600">₦<?php echo number_format($share['amount_paid'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Purchase Date:</span>
                                                    <span class="text-gray-600"><?php echo date('M d, Y', strtotime($share['purchase_date'])); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Dividend Eligible:</span>
                                                    <span class="<?php echo $share['dividend_eligible'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php echo $share['dividend_eligible'] ? 'Yes' : 'No'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Withdrawal Request Modal -->
    <div id="withdrawalModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full max-h-90vh overflow-y-auto">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="request_withdrawal">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Request Contribution Withdrawal</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="withdrawal_type" class="block text-sm font-medium text-gray-700 mb-2">Withdrawal Type <span class="text-red-500">*</span></label>
                                <select name="withdrawal_type" id="withdrawal_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="">Select type...</option>
                                    <option value="partial">Partial Withdrawal</option>
                                    <option value="emergency">Emergency Withdrawal</option>
                                    <option value="resignation">Resignation Withdrawal</option>
                                    <option value="investment">Investment Withdrawal</option>
                                </select>
                            </div>
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount (₦) <span class="text-red-500">*</span></label>
                                <input type="number" name="amount" id="amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" min="1000" step="0.01" required>
                                <p class="text-xs text-gray-500 mt-1">Maximum available: ₦<?php echo number_format($stats['net_contributions'], 2); ?></p>
                            </div>
                        </div>
                        <div>
                            <label for="contribution_types" class="block text-sm font-medium text-gray-700 mb-2">Contribution Types</label>
                            <input type="text" name="contribution_types" id="contribution_types" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., Dues, Investment">
                        </div>
                        <div>
                            <label for="withdrawal_date" class="block text-sm font-medium text-gray-700 mb-2">Requested Date <span class="text-red-500">*</span></label>
                            <input type="date" name="withdrawal_date" id="withdrawal_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">Reason <span class="text-red-500">*</span></label>
                            <textarea name="reason" id="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Please explain the reason for this withdrawal..." required></textarea>
                        </div>
                        <div>
                            <label for="supporting_documents" class="block text-sm font-medium text-gray-700 mb-2">Supporting Documents</label>
                            <textarea name="supporting_documents" id="supporting_documents" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="List any supporting documents provided..."></textarea>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="font-medium text-yellow-800 mb-2">Withdrawal Fees</h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Partial: 2% (max ₦50,000)</li>
                                <li>• Emergency: 1% (max ₦50,000)</li>
                                <li>• Resignation: 5% (max ₦50,000)</li>
                                <li>• Investment: 1% (max ₦50,000)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeWithdrawalModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Share Purchase Modal -->
    <div id="sharePurchaseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="purchase_shares">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Purchase Share Capital</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="share_type" class="block text-sm font-medium text-gray-700 mb-2">Share Type <span class="text-red-500">*</span></label>
                                <select name="share_type" id="share_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="ordinary">Ordinary Shares</option>
                                    <option value="preference">Preference Shares</option>
                                    <option value="founder">Founder Shares</option>
                                </select>
                            </div>
                            <div>
                                <label for="number_of_shares" class="block text-sm font-medium text-gray-700 mb-2">Number of Shares <span class="text-red-500">*</span></label>
                                <input type="number" name="number_of_shares" id="number_of_shares" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" min="1" required onchange="calculateShareValue()">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="par_value" class="block text-sm font-medium text-gray-700 mb-2">Par Value per Share (₦) <span class="text-red-500">*</span></label>
                                <input type="number" name="par_value" id="par_value" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" min="1000" step="0.01" value="1000" required onchange="calculateShareValue()">
                            </div>
                            <div>
                                <label for="total_value_display" class="block text-sm font-medium text-gray-700 mb-2">Total Value (₦)</label>
                                <input type="text" id="total_value_display" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">Purchase Date <span class="text-red-500">*</span></label>
                                <input type="date" name="purchase_date" id="purchase_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div>
                                <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-2">Payment Status <span class="text-red-500">*</span></label>
                                <select name="payment_status" id="payment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required onchange="toggleAmountPaid()">
                                    <option value="paid">Fully Paid</option>
                                    <option value="partial">Partially Paid</option>
                                    <option value="pending">Payment Pending</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="amount_paid" class="block text-sm font-medium text-gray-700 mb-2">Amount Paid (₦) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount_paid" id="amount_paid" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" min="0" step="0.01" required>
                        </div>
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Deduction">Salary Deduction</option>
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="dividend_eligible" id="dividend_eligible" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" checked>
                            <label for="dividend_eligible" class="ml-2 text-sm text-gray-700">Eligible for dividends</label>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeSharePurchaseModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Purchase Shares
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-primary-500', 'text-primary-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Add active class to selected button
            const activeButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            activeButton.classList.add('active', 'border-primary-500', 'text-primary-600');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Modal functions
        function showWithdrawalModal() {
            document.getElementById('withdrawalModal').classList.remove('hidden');
        }

        function closeWithdrawalModal() {
            document.getElementById('withdrawalModal').classList.add('hidden');
        }

        function showSharePurchaseModal() {
            document.getElementById('sharePurchaseModal').classList.remove('hidden');
            calculateShareValue();
        }

        function closeSharePurchaseModal() {
            document.getElementById('sharePurchaseModal').classList.add('hidden');
        }

        // Calculate share values
        function calculateShareValue() {
            const shares = parseInt(document.getElementById('number_of_shares').value) || 0;
            const parValue = parseFloat(document.getElementById('par_value').value) || 0;
            const totalValue = shares * parValue;
            
            document.getElementById('total_value_display').value = '₦' + totalValue.toLocaleString('en-NG', {minimumFractionDigits: 2});
            
            // Update amount paid field based on payment status
            const paymentStatus = document.getElementById('payment_status').value;
            const amountPaidField = document.getElementById('amount_paid');
            
            if (paymentStatus === 'paid') {
                amountPaidField.value = totalValue;
            } else if (paymentStatus === 'pending') {
                amountPaidField.value = 0;
            }
        }

        function toggleAmountPaid() {
            calculateShareValue();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showTab('contributions');
        });
    </script>
</body>
</html>
