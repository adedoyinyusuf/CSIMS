<?php
/**
 * Member Export Controller
 * Handles CSV/Excel export of members based on filters
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/auth_controller.php';
require_once __DIR__ . '/../controllers/member_controller.php';
require_once __DIR__ . '/../includes/session.php';

$session = Session::getInstance();

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized access');
}

// Initialize member controller
$memberController = new MemberController();

// Get export parameters
$format = $_GET['format'] ?? 'csv';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$membership_type = $_GET['membership_type'] ?? '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10000; // Large limit for export

// Get all matching members (no pagination for export)
$result = $memberController->getAllMembers(1, $search, $status, $membership_type, $per_page);
$members = $result['members'] ?? [];

if (empty($members)) {
    $session->setFlash('error', 'No members found to export');
    header("Location: " . BASE_URL . "/views/admin/members.php");
    exit();
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "members_export_" . $timestamp;

if ($format === 'csv' || $format === '') {
    // CSV Export
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Add BOM for UTF-8 to ensure Excel displays correctly
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'Member ID',
        'First Name',
        'Last Name',
        'Gender',
        'Date of Birth',
        'Email',
        'Phone',
        'Address',
        'City',
        'State',
        'Postal Code',
        'Membership Type',
        'Status',
        'Join Date',
        'Expiry Date',
        'Created At'
    ];
    
    fputcsv($output, $headers);
    
    // CSV Data
    foreach ($members as $member) {
        $row = [
            $member['member_id'] ?? '',
            $member['first_name'] ?? '',
            $member['last_name'] ?? '',
            $member['gender'] ?? '',
            $member['date_of_birth'] ?? '',
            $member['email'] ?? '',
            $member['phone'] ?? '',
            $member['address'] ?? '',
            $member['city'] ?? '',
            $member['state'] ?? '',
            $member['postal_code'] ?? '',
            $member['member_type_label'] ?? $member['member_type'] ?? '',
            $member['status'] ?? '',
            $member['join_date'] ?? '',
            $member['expiry_date'] ?? '',
            $member['created_at'] ?? ''
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
    
    echo '<h2>Members Export - ' . date('Y-m-d H:i:s') . '</h2>';
    echo '<table>';
    
    // Headers
    echo '<tr>';
    echo '<th>Member ID</th>';
    echo '<th>First Name</th>';
    echo '<th>Last Name</th>';
    echo '<th>Gender</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Address</th>';
    echo '<th>City</th>';
    echo '<th>State</th>';
    echo '<th>Membership Type</th>';
    echo '<th>Status</th>';
    echo '<th>Join Date</th>';
    echo '<th>Expiry Date</th>';
    echo '</tr>';
    
    // Data
    foreach ($members as $member) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($member['member_id'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['first_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['last_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['gender'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['phone'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['address'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['city'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['state'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['member_type_label'] ?? $member['member_type'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['status'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['join_date'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($member['expiry_date'] ?? '') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// Default: redirect back
header("Location: " . BASE_URL . "/views/admin/members.php");
exit();


