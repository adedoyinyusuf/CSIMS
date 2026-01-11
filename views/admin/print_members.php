<?php
/**
 * Print Members Page
 * Printable view of members list
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$membership_type = isset($_GET['membership_type']) ? $_GET['membership_type'] : '';

// Initialize member controller
$memberController = new MemberController();

// Get all members (no pagination for print)
$result = $memberController->getAllMembers(1, $search, $status, $membership_type, 10000);
$members = $result['members'] ?? [];
$total_members = $result['pagination']['total_items'] ?? count($members);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Members - <?php echo APP_NAME; ?></title>
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
        }
        .btn:hover {
            background: #0056b3;
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
        <h2>Members List</h2>
        <div class="print-info">
            Printed on: <?php echo date('F d, Y \a\t H:i:s'); ?><br>
            Total Members: <?php echo number_format($total_members); ?>
            <?php if ($search || $status || $membership_type): ?>
                <br>Filters Applied: 
                <?php 
                $filters = [];
                if ($search) $filters[] = "Search: $search";
                if ($status) $filters[] = "Status: $status";
                if ($membership_type) $filters[] = "Type: $membership_type";
                echo implode(', ', $filters);
                ?>
            <?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Gender</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Membership Type</th>
                <th>Status</th>
                <th>Join Date</th>
                <th>Expiry Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($members) > 0): ?>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?php echo $member['member_id']; ?></td>
                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['gender'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo htmlspecialchars($member['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($member['member_type_label'] ?? $member['member_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($member['status']); ?></td>
                        <td><?php 
                            $joinDate = $member['join_date'] ?? null;
                            echo $joinDate ? date('M d, Y', strtotime($joinDate)) : 'N/A';
                        ?></td>
                        <td><?php 
                            $expiryDate = $member['expiry_date'] ?? null;
                            echo $expiryDate ? date('M d, Y', strtotime($expiryDate)) : 'N/A';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 20px;">
                        No members found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 10px; color: #666; text-align: center;">
        This document was generated by <?php echo APP_NAME; ?> on <?php echo date('F d, Y'); ?>
    </div>
</body>
</html>



