<?php
/**
 * Loan Export Controller
 * Handles CSV/Excel export of loans based on filters
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/auth_controller.php';
require_once __DIR__ . '/../controllers/loan_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized access');
}

// Initialize loan controller
$loanController = class_exists('EnhancedLoanController') ? new EnhancedLoanController() : new LoanController();

// Get export parameters
$format = $_GET['format'] ?? 'csv';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'application_date';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// Build filters array
$filters = [];
if (!empty($status)) {
    $filters['status'] = $status;
}

// Get all matching loans (large limit for export)
$result = $loanController->getAllLoans(1, 10000, $search, $sortBy, $sortOrder, $filters);
$loans = $result['loans'] ?? [];

if (empty($loans)) {
    $_SESSION['error_message'] = 'No loans found to export';
    header("Location: " . BASE_URL . "/views/admin/loans.php");
    exit();
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "loans_export_" . $timestamp;

if ($format === 'csv' || $format === '') {
    // CSV Export
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Add BOM for UTF-8 to ensure Excel displays correctly
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'Loan ID',
        'Member ID',
        'Member Name',
        'Loan Type',
        'Amount',
        'Term (Months)',
        'Interest Rate (%)',
        'Status',
        'Application Date',
        'Purpose',
        'Amount Paid',
        'Amount Remaining'
    ];
    
    fputcsv($output, $headers);
    
    // CSV Data
    foreach ($loans as $loan) {
        $loanId = $loan['loan_id'] ?? $loan['id'] ?? '';
        $memberId = $loan['member_id'] ?? '';
        $memberName = trim(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? '')) ?: ($loan['member_name'] ?? 'N/A');
        $loanType = $loan['loan_type_name'] ?? $loan['loan_type'] ?? 'N/A';
        $amount = (float)($loan['amount'] ?? 0);
        
        // Calculate paid and remaining amounts
        $amountPaid = (float)($loan['amount_paid'] ?? $loan['paid_amount'] ?? 0);
        $amountRemaining = max(0, $amount - $amountPaid);
        
        $row = [
            $loanId,
            $memberId,
            $memberName,
            $loanType,
            number_format($amount, 2),
            $loan['term'] ?? $loan['term_months'] ?? 'N/A',
            $loan['interest_rate'] ?? $loan['annual_rate'] ?? '0.00',
            ucfirst($loan['status'] ?? 'Unknown'),
            $loan['application_date'] ?? $loan['created_at'] ?? '',
            $loan['purpose'] ?? 'N/A',
            number_format($amountPaid, 2),
            number_format($amountRemaining, 2)
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'excel') {
    // Excel Export (HTML format)
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>Loans Export - ' . date('Y-m-d H:i:s') . '</h2>';
    echo '<table>';
    
    // Headers
    echo '<tr>';
    echo '<th>Loan ID</th>';
    echo '<th>Member ID</th>';
    echo '<th>Member Name</th>';
    echo '<th>Loan Type</th>';
    echo '<th>Amount</th>';
    echo '<th>Term (Months)</th>';
    echo '<th>Interest Rate (%)</th>';
    echo '<th>Status</th>';
    echo '<th>Application Date</th>';
    echo '<th>Purpose</th>';
    echo '<th>Amount Paid</th>';
    echo '<th>Amount Remaining</th>';
    echo '</tr>';
    
    // Data
    foreach ($loans as $loan) {
        $loanId = $loan['loan_id'] ?? $loan['id'] ?? '';
        $memberId = $loan['member_id'] ?? '';
        $memberName = trim(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? '')) ?: ($loan['member_name'] ?? 'N/A');
        $loanType = $loan['loan_type_name'] ?? $loan['loan_type'] ?? 'N/A';
        $amount = (float)($loan['amount'] ?? 0);
        
        // Calculate paid and remaining amounts
        $amountPaid = (float)($loan['amount_paid'] ?? $loan['paid_amount'] ?? 0);
        $amountRemaining = max(0, $amount - $amountPaid);
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($loanId) . '</td>';
        echo '<td>' . htmlspecialchars($memberId) . '</td>';
        echo '<td>' . htmlspecialchars($memberName) . '</td>';
        echo '<td>' . htmlspecialchars($loanType) . '</td>';
        echo '<td>₦' . htmlspecialchars(number_format($amount, 2)) . '</td>';
        echo '<td>' . htmlspecialchars($loan['term'] ?? $loan['term_months'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($loan['interest_rate'] ?? $loan['annual_rate'] ?? '0.00') . '%</td>';
        echo '<td>' . htmlspecialchars(ucfirst($loan['status'] ?? 'Unknown')) . '</td>';
        echo '<td>' . htmlspecialchars($loan['application_date'] ?? $loan['created_at'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($loan['purpose'] ?? 'N/A') . '</td>';
        echo '<td>₦' . htmlspecialchars(number_format($amountPaid, 2)) . '</td>';
        echo '<td>₦' . htmlspecialchars(number_format($amountRemaining, 2)) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// Default: redirect back
header("Location: " . BASE_URL . "/views/admin/loans.php");
exit();
