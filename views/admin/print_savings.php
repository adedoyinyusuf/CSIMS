<?php
/**
 * Print Savings Accounts Page
 * Printable view of savings accounts list
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/SavingsController.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$account_type = isset($_GET['account_type']) ? $_GET['account_type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Initialize savings controller
$savingsController = new SavingsController();

// Get all accounts
$accounts = $savingsController->getAllAccounts($search, $account_type, $status);
$statistics = $savingsController->getSavingsStatistics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Savings Accounts - <?php echo APP_NAME; ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            @page { margin: 1cm; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .print-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .print-info {
            margin-bottom: 15px;
            font-size: 11px;
            color: #666;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        .stat-box .value {
            font-size: 20px;
            font-weight: bold;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .no-print {
            margin-bottom: 20px;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">Print</button>
        <button onclick="window.close()" class="btn">Close</button>
    </div>

    <div class="print-header">
        <h1><?php echo APP_NAME; ?></h1>
        <h2>Savings Accounts Report</h2>
        <div class="print-info">
            Printed on: <?php echo date('F d, Y \a\t H:i:s'); ?><br>
            Total Accounts: <?php echo number_format(count($accounts)); ?>
            <?php if ($search || $account_type || $status): ?>
                <br>Filters Applied: 
                <?php 
                $filters = [];
                if ($search) $filters[] = "Search: $search";
                if ($account_type) $filters[] = "Type: " . ucwords($account_type);
                if ($status) $filters[] = "Status: " . ucwords($status);
                echo implode(', ', $filters);
                ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <h3>Total Accounts</h3>
            <div class="value"><?php echo number_format($statistics['total_accounts'] ?? 0); ?></div>
        </div>
        <div class="stat-box">
            <h3>Total Balance</h3>
            <div class="value">₦<?php echo number_format($statistics['total_balance'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-box">
            <h3>Active Members</h3>
            <div class="value"><?php echo number_format($statistics['active_members'] ?? 0); ?></div>
        </div>
        <div class="stat-box">
            <h3>Interest Rate (Avg)</h3>
            <div class="value"><?php 
                $avgRate = 0;
                if (count($accounts) > 0) {
                    $totalRate = 0;
                    foreach ($accounts as $acc) {
                        $totalRate += (float)($acc['interest_rate'] ?? 0);
                    }
                    $avgRate = $totalRate / count($accounts);
                }
                echo number_format($avgRate, 2); 
            ?>%</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Account ID</th>
                <th>Member</th>
                <th>Account Number</th>
                <th>Account Type</th>
                <th class="text-right">Balance</th>
                <th>Interest Rate</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($accounts) > 0): ?>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><?php echo $account['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($account['member_name'] ?? 'N/A'); ?>
                            <br><small style="color: #666;">ID: <?php echo $account['member_id']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($account['account_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(ucwords($account['account_type'] ?? '')); ?></td>
                        <td class="text-right">₦<?php echo number_format($account['balance'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($account['interest_rate'] ?? 0, 2); ?>%</td>
                        <td><?php echo htmlspecialchars(ucfirst($account['status'] ?? '')); ?></td>
                        <td><?php 
                            $createdAt = $account['created_at'] ?? null;
                            echo $createdAt ? date('M d, Y', strtotime($createdAt)) : 'N/A';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">
                        No savings accounts found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <?php if (count($accounts) > 0): ?>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f2f2f2;">
                <td colspan="4" style="text-align: right;">Total:</td>
                <td class="text-right">₦<?php 
                    $totalBalance = 0;
                    foreach ($accounts as $acc) {
                        $totalBalance += (float)($acc['balance'] ?? 0);
                    }
                    echo number_format($totalBalance, 2);
                ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <div style="margin-top: 20px; font-size: 10px; color: #666; text-align: center;">
        This document was generated by <?php echo APP_NAME; ?> on <?php echo date('F d, Y'); ?>
    </div>
</body>
</html>


