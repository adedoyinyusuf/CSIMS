<?php
/**
 * Print Loans Page
 * Printable view of loans list
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'application_date';
$sortOrder = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Initialize loan controller
$loanController = class_exists('EnhancedLoanController') ? new EnhancedLoanController() : new LoanController();

// Build filters array
$filters = [];
if (!empty($status)) {
    $filters['status'] = $status;
}

// Get all loans (large limit for print)
$result = $loanController->getAllLoans(1, 10000, $search, $sortBy, $sortOrder, $filters);
$loans = $result['loans'] ?? [];
$loanStats = $loanController->getLoanStatistics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Loans - <?php echo APP_NAME; ?></title>
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
        <h2>Loans Report</h2>
        <div class="print-info">
            Printed on: <?php echo date('F d, Y \a\t H:i:s'); ?><br>
            Total Loans: <?php echo number_format(count($loans)); ?>
            <?php if ($search || $status): ?>
                <br>Filters Applied: 
                <?php 
                $filters = [];
                if ($search) $filters[] = "Search: $search";
                if ($status) $filters[] = "Status: " . ucwords($status);
                echo implode(', ', $filters);
                ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <h3>Total Loans</h3>
            <div class="value"><?php 
                $totalCount = is_array($loanStats['total_loans'] ?? null) 
                    ? ($loanStats['total_loans']['count'] ?? 0) 
                    : (int)($loanStats['total_loans'] ?? 0);
                echo number_format($totalCount); 
            ?></div>
        </div>
        <div class="stat-box">
            <h3>Total Amount</h3>
            <div class="value">₦<?php echo number_format($loanStats['total_amount'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-box">
            <h3>Pending</h3>
            <div class="value"><?php 
                $pendingCount = is_array($loanStats['pending_loans'] ?? null) 
                    ? ($loanStats['pending_loans']['count'] ?? 0) 
                    : (int)($loanStats['pending_loans'] ?? 0);
                echo number_format($pendingCount); 
            ?></div>
        </div>
        <div class="stat-box">
            <h3>Approved</h3>
            <div class="value"><?php 
                $approvedCount = is_array($loanStats['approved_loans'] ?? null) 
                    ? ($loanStats['approved_loans']['count'] ?? 0) 
                    : (int)($loanStats['approved_loans'] ?? 0);
                echo number_format($approvedCount); 
            ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Loan ID</th>
                <th>Member</th>
                <th>Loan Type</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Remaining</th>
                <th>Term</th>
                <th>Interest Rate</th>
                <th>Status</th>
                <th>Application Date</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($loans) > 0): ?>
                <?php foreach ($loans as $loan): ?>
                    <?php
                    $loanId = $loan['loan_id'] ?? $loan['id'] ?? 'N/A';
                    $memberName = trim(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? '')) ?: ($loan['member_name'] ?? 'N/A');
                    $memberId = $loan['member_id'] ?? 'N/A';
                    $loanType = $loan['loan_type_name'] ?? $loan['loan_type'] ?? 'N/A';
                    $amount = (float)($loan['amount'] ?? 0);
                    $amountPaid = (float)($loan['amount_paid'] ?? $loan['paid_amount'] ?? 0);
                    $amountRemaining = max(0, $amount - $amountPaid);
                    $term = $loan['term'] ?? $loan['term_months'] ?? 'N/A';
                    $interestRate = $loan['interest_rate'] ?? $loan['annual_rate'] ?? '0.00';
                    $status = ucfirst($loan['status'] ?? 'Unknown');
                    $appDate = $loan['application_date'] ?? $loan['created_at'] ?? '';
                    $purpose = $loan['purpose'] ?? 'N/A';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($loanId); ?></td>
                        <td>
                            <?php echo htmlspecialchars($memberName); ?>
                            <br><small style="color: #666;">ID: <?php echo htmlspecialchars($memberId); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($loanType); ?></td>
                        <td class="text-right">₦<?php echo number_format($amount, 2); ?></td>
                        <td class="text-right">₦<?php echo number_format($amountPaid, 2); ?></td>
                        <td class="text-right">₦<?php echo number_format($amountRemaining, 2); ?></td>
                        <td><?php echo htmlspecialchars($term); ?> <?php echo is_numeric($term) ? 'months' : ''; ?></td>
                        <td><?php echo number_format((float)$interestRate, 2); ?>%</td>
                        <td><?php echo htmlspecialchars($status); ?></td>
                        <td><?php 
                            echo $appDate ? date('M d, Y', strtotime($appDate)) : 'N/A';
                        ?></td>
                        <td><?php echo htmlspecialchars($purpose); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 20px;">
                        No loans found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <?php if (count($loans) > 0): ?>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f2f2f2;">
                <td colspan="3" style="text-align: right;">Totals:</td>
                <td class="text-right">₦<?php 
                    $totalAmount = 0;
                    $totalPaid = 0;
                    $totalRemaining = 0;
                    foreach ($loans as $loan) {
                        $totalAmount += (float)($loan['amount'] ?? 0);
                        $totalPaid += (float)($loan['amount_paid'] ?? $loan['paid_amount'] ?? 0);
                        $totalRemaining += max(0, (float)($loan['amount'] ?? 0) - (float)($loan['amount_paid'] ?? $loan['paid_amount'] ?? 0));
                    }
                    echo number_format($totalAmount, 2);
                ?></td>
                <td class="text-right">₦<?php echo number_format($totalPaid, 2); ?></td>
                <td class="text-right">₦<?php echo number_format($totalRemaining, 2); ?></td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <div style="margin-top: 20px; font-size: 10px; color: #666; text-align: center;">
        This document was generated by <?php echo APP_NAME; ?> on <?php echo date('F d, Y'); ?>
    </div>
</body>
</html>

