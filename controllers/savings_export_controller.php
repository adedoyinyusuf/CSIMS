<?php
/**
 * Savings Export Controller
 * Handles CSV/Excel export of savings accounts based on filters
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/auth_controller.php';
require_once __DIR__ . '/../controllers/SavingsController.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized access');
}

// Initialize savings controller
$savingsController = new SavingsController();

// Get export parameters
$format = $_GET['format'] ?? 'csv';
$search = $_GET['search'] ?? '';
$account_type = $_GET['account_type'] ?? '';
$status = $_GET['status'] ?? '';

// Get all matching accounts
$accounts = $savingsController->getAllAccounts($search, $account_type, $status);

if (empty($accounts)) {
    $_SESSION['error_message'] = 'No savings accounts found to export';
    header("Location: " . BASE_URL . "/views/admin/savings.php");
    exit();
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "savings_accounts_export_" . $timestamp;

if ($format === 'csv' || $format === '') {
    // CSV Export
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Add BOM for UTF-8 to ensure Excel displays correctly
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'Account ID',
        'Member ID',
        'Member Name',
        'Account Number',
        'Account Type',
        'Balance',
        'Interest Rate',
        'Status',
        'Created Date'
    ];
    
    fputcsv($output, $headers);
    
    // CSV Data
    foreach ($accounts as $account) {
        $row = [
            $account['id'] ?? '',
            $account['member_id'] ?? '',
            $account['member_name'] ?? 'N/A',
            $account['account_number'] ?? '',
            ucwords($account['account_type'] ?? ''),
            $account['balance'] ?? '0.00',
            $account['interest_rate'] ?? '0.00',
            ucfirst($account['status'] ?? ''),
            $account['created_at'] ?? ''
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
    
    echo '<h2>Savings Accounts Export - ' . date('Y-m-d H:i:s') . '</h2>';
    echo '<table>';
    
    // Headers
    echo '<tr>';
    echo '<th>Account ID</th>';
    echo '<th>Member ID</th>';
    echo '<th>Member Name</th>';
    echo '<th>Account Number</th>';
    echo '<th>Account Type</th>';
    echo '<th>Balance</th>';
    echo '<th>Interest Rate</th>';
    echo '<th>Status</th>';
    echo '<th>Created Date</th>';
    echo '</tr>';
    
    // Data
    foreach ($accounts as $account) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($account['id'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($account['member_id'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($account['member_name'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($account['account_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(ucwords($account['account_type'] ?? '')) . '</td>';
        echo '<td>₦' . htmlspecialchars(number_format($account['balance'] ?? 0, 2)) . '</td>';
        echo '<td>' . htmlspecialchars($account['interest_rate'] ?? '0.00') . '%</td>';
        echo '<td>' . htmlspecialchars(ucfirst($account['status'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($account['created_at'] ?? '') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// Default: redirect back
header("Location: " . BASE_URL . "/views/admin/savings.php");
exit();



