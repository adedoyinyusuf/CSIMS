<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/session.php';
$session = Session::getInstance();
// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Initialize member controller
$memberController = new MemberController();

// Get export parameters
$format = $_GET['format'] ?? 'csv';
$ids = $_GET['ids'] ?? '';

// Determine what to export
if (!empty($ids)) {
    // Export selected members
    $member_ids = explode(',', $ids);
    $members = [];
    
    foreach ($member_ids as $id) {
        $member = $memberController->getMemberById($id);
        if ($member) {
            $members[] = $member;
        }
    }
} else {
    // Export all members based on search filters
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $membership_type = $_GET['membership_type'] ?? '';
    $gender = $_GET['gender'] ?? '';
    $age_min = $_GET['age_min'] ?? '';
    $age_max = $_GET['age_max'] ?? '';
    $join_date_from = $_GET['join_date_from'] ?? '';
    $join_date_to = $_GET['join_date_to'] ?? '';
    $expiry_date_from = $_GET['expiry_date_from'] ?? '';
    $expiry_date_to = $_GET['expiry_date_to'] ?? '';
    $city = $_GET['city'] ?? '';
    $state = $_GET['state'] ?? '';
    
    // Get all matching members (no pagination)
    $members_data = $memberController->searchMembersAdvanced([
        'search' => $search,
        'status' => $status,
        'membership_type' => $membership_type,
        'gender' => $gender,
        'age_min' => $age_min,
        'age_max' => $age_max,
        'join_date_from' => $join_date_from,
        'join_date_to' => $join_date_to,
        'expiry_date_from' => $expiry_date_from,
        'expiry_date_to' => $expiry_date_to,
        'city' => $city,
        'state' => $state
    ], 10000); // Large limit to get all results
    
    $members = $members_data['members'];
}

if (empty($members)) {
    $session->setFlash('error', 'No members found to export');
    header("Location: " . BASE_URL . "/admin/members.php");
    exit();
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "members_export_" . $timestamp;

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'Member ID',
        'First Name',
        'Last Name',
        'Gender',
        'Date of Birth',
        'Age',
        'Email',
        'Phone',
        'Address',
        'City',
        'State',
        'Postal Code',
        'Country',
        'Occupation',
        'Membership Type',
        'Status',
        'Join Date',
        'Expiry Date',
        'Notes',
        'Created At',
        'Updated At'
    ];
    
    fputcsv($output, $headers);
    
    // CSV Data
    foreach ($members as $member) {
        // Calculate age
        $age = '';
        if (!empty($member['date_of_birth'])) {
            $dob = new DateTime($member['date_of_birth']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
        }
        
        $row = [
            $member['member_id'],
            $member['first_name'],
            $member['last_name'],
            $member['gender'],
            $member['date_of_birth'],
            $age,
            $member['email'],
            $member['phone'],
            $member['address'],
            $member['city'],
            $member['state'],
            $member['postal_code'],
            $member['country'],
            $member['occupation'],
            $member['membership_type'],
            $member['status'],
            $member['join_date'],
            $member['expiry_date'],
            $member['notes'],
            $member['created_at'],
            $member['updated_at']
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'excel') {
    // Excel Export (HTML table that Excel can open)
    header('Content-Type: application/vnd.ms-excel');
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
    echo '<th>Date of Birth</th>';
    echo '<th>Age</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Address</th>';
    echo '<th>City</th>';
    echo '<th>State</th>';
    echo '<th>Postal Code</th>';
    echo '<th>Country</th>';
    echo '<th>Occupation</th>';
    echo '<th>Membership Type</th>';
    echo '<th>Status</th>';
    echo '<th>Join Date</th>';
    echo '<th>Expiry Date</th>';
    echo '<th>Notes</th>';
    echo '<th>Created At</th>';
    echo '<th>Updated At</th>';
    echo '</tr>';
    
    // Data
    foreach ($members as $member) {
        // Calculate age
        $age = '';
        if (!empty($member['date_of_birth'])) {
            $dob = new DateTime($member['date_of_birth']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($member['member_id']) . '</td>';
        echo '<td>' . htmlspecialchars($member['first_name']) . '</td>';
        echo '<td>' . htmlspecialchars($member['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($member['gender']) . '</td>';
        echo '<td>' . htmlspecialchars($member['date_of_birth']) . '</td>';
        echo '<td>' . htmlspecialchars($age) . '</td>';
        echo '<td>' . htmlspecialchars($member['email']) . '</td>';
        echo '<td>' . htmlspecialchars($member['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($member['address']) . '</td>';
        echo '<td>' . htmlspecialchars($member['city']) . '</td>';
        echo '<td>' . htmlspecialchars($member['state']) . '</td>';
        echo '<td>' . htmlspecialchars($member['postal_code']) . '</td>';
        echo '<td>' . htmlspecialchars($member['country']) . '</td>';
        echo '<td>' . htmlspecialchars($member['occupation']) . '</td>';
        echo '<td>' . htmlspecialchars($member['membership_type']) . '</td>';
        echo '<td>' . htmlspecialchars($member['status']) . '</td>';
        echo '<td>' . htmlspecialchars($member['join_date']) . '</td>';
        echo '<td>' . htmlspecialchars($member['expiry_date']) . '</td>';
        echo '<td>' . htmlspecialchars($member['notes']) . '</td>';
        echo '<td>' . htmlspecialchars($member['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($member['updated_at']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
    
} elseif ($format === 'pdf') {
    // PDF Export (Simple HTML to PDF)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    // For a simple PDF, we'll use HTML that can be printed to PDF
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Members Export</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; font-size: 12px; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 20px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo 'h1 { color: #333; text-align: center; }';
    echo '@media print { body { margin: 0; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h1>Members Export Report</h1>';
    echo '<p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p><strong>Total Members:</strong> ' . count($members) . '</p>';
    
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Name</th>';
    echo '<th>Gender</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>City</th>';
    echo '<th>Membership</th>';
    echo '<th>Status</th>';
    echo '<th>Join Date</th>';
    echo '<th>Expiry Date</th>';
    echo '</tr>';
    
    foreach ($members as $member) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($member['member_id']) . '</td>';
        echo '<td>' . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($member['gender']) . '</td>';
        echo '<td>' . htmlspecialchars($member['email']) . '</td>';
        echo '<td>' . htmlspecialchars($member['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($member['city']) . '</td>';
        echo '<td>' . htmlspecialchars($member['membership_type']) . '</td>';
        echo '<td>' . htmlspecialchars($member['status']) . '</td>';
        echo '<td>' . htmlspecialchars(date('M d, Y', strtotime($member['join_date']))) . '</td>';
        echo '<td>' . htmlspecialchars(date('M d, Y', strtotime($member['expiry_date']))) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// Default: redirect back if no valid format
$session->setFlash('error', 'Invalid export format');
header("Location: " . BASE_URL . "/admin/members.php");
exit();
?>
