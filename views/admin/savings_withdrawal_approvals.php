<?php
ob_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../controllers/SavingsController.php';
require_once __DIR__ . '/../../src/autoload.php';

// Initialize session and auth
$session = Session::getInstance();
$auth = new AuthController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

$database = Database::getInstance();
$conn = $database->getConnection();

$success = '';
$error = '';

// Handle withdrawal approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFProtection::validateRequest();
    
    if (isset($_POST['approve_withdrawal'])) {
        $request_id = (int)$_POST['request_id'];
        $admin_comment = trim($_POST['admin_comment'] ?? '');
        $admin_id = $_SESSION['user_id'] ?? 1;
        
        try {
            // Get withdrawal request details
            $stmt = $conn->prepare("
                SELECT wr.*, sa.balance, sa.account_name, m.first_name, m.last_name
                FROM savings_withdrawal_requests wr
                JOIN savings_accounts sa ON wr.account_id = sa.account_id
                JOIN members m ON wr.member_id = m.member_id
                WHERE wr.request_id = ?
            ");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $withdrawal = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$withdrawal) {
                $error = 'Withdrawal request not found.';
            } elseif ($withdrawal['status'] !== 'Pending') {
                $error = 'This request has already been processed.';
            } elseif ($withdrawal['amount'] > $withdrawal['balance']) {
                $error = 'Insufficient balance in account.';
            } else {
                // Process withdrawal using SavingsController
                $savingsController = new SavingsController();
                $description = "Withdrawal Request #{$request_id}" . ($admin_comment ? ": {$admin_comment}" : '');
                
                if ($savingsController->withdraw($withdrawal['account_id'], $withdrawal['amount'], $withdrawal['member_id'], $description)) {
                    // Update request status
                    $stmt = $conn->prepare("
                        UPDATE savings_withdrawal_requests 
                        SET status = 'Approved', 
                            approved_by = ?, 
                            admin_comment = ?,
                            processed_date = NOW(),
                            updated_at = NOW()
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param('isi', $admin_id, $admin_comment, $request_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success = "Withdrawal request #{$request_id} approved and processed successfully!";
                } else {
                    $error = 'Failed to process withdrawal. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error processing approval: ' . $e->getMessage();
        }
    } elseif (isset($_POST['reject_withdrawal'])) {
        $request_id = (int)$_POST['request_id'];
        $admin_comment = trim($_POST['admin_comment'] ?? '');
        $admin_id = $_SESSION['user_id'] ?? 1;
        
        if (empty($admin_comment)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE savings_withdrawal_requests 
                    SET status = 'Rejected', 
                        approved_by = ?, 
                        admin_comment = ?,
                        processed_date = NOW(),
                        updated_at = NOW()
                    WHERE request_id = ? AND status = 'Pending'
                ");
                $stmt->bind_param('isi', $admin_id, $admin_comment, $request_id);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success = "Withdrawal request #{$request_id} rejected successfully.";
                } else {
                    $error = 'Request already processed or not found.';
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = 'Error processing rejection: ' . $e->getMessage();
            }
        }
    }
}

// Get withdrawal requests with filters
$withdrawal_requests = [];
$status_filter = $_GET['status'] ?? 'Pending';
$search = $_GET['search'] ?? '';

try {
    $sql = "
        SELECT wr.*, 
               sa.account_name, sa.account_number, sa.balance,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.ippis_no, m.phone, m.email
        FROM savings_withdrawal_requests wr
        JOIN savings_accounts sa ON wr.account_id = sa.account_id
        JOIN members m ON wr.member_id = m.member_id
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if ($status_filter && $status_filter !== 'All') {
        $sql .= " AND wr.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    if ($search) {
        $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.ippis_no LIKE ? OR wr.request_id LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }

    $sql .= " ORDER BY 
        CASE WHEN wr.status = 'Pending' THEN 1 ELSE 2 END,
        wr.request_date DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $withdrawal_requests[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Table doesn't exist yet - empty array already set
    error_log("Error fetching withdrawal requests: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_requests' => 0,
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0,
    'pending_amount' => 0,
    'approved_amount' => 0
];

try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END) as approved_amount
        FROM savings_withdrawal_requests
    ";
    $stats_result = $conn->query($stats_sql);
    if ($stats_result) {
        $fetched_stats = $stats_result->fetch_assoc();
        // Only update if we got valid data
        if ($fetched_stats && $fetched_stats['total_requests'] !== null) {
            $stats = $fetched_stats;
        }
    }
} catch (Exception $e) {
    // Table doesn't exist yet - use default values
    error_log("Withdrawal requests table not found: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Approvals - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-processed { background: #dbeafe; color: #1e40af; }
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .request-card {
            transition: all 0.3s;
            border: 1px solid #e5e7eb;
        }
        .request-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../../views/includes/header.php'; ?>
    
    <div class="d-flex">
        <?php include __DIR__ . '/../../views/includes/sidebar.php'; ?>
        
        <main class="flex-fill">
            <div class="container-fluid p-4" style="margin-left: 16rem; margin-top: 4rem;">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tasks text-primary"></i> Withdrawal Approvals</h1>
                    <div>
                        <a href="savings_reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-file-alt"></i> View Reports
                        </a>
                    </div>
                </div>
                
                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card" style="border-left-color: #3b82f6;">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Total Requests</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_requests'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card" style="border-left-color: #f59e0b;">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Pending</h6>
                                <h3 class="mb-0 text-warning"><?php echo number_format($stats['pending_count'] ?? 0); ?></h3>
                                <small class="text-muted">₦<?php echo number_format($stats['pending_amount'] ?? 0, 2); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card" style="border-left-color: #10b981;">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Approved</h6>
                                <h3 class="mb-0 text-success"><?php echo number_format($stats['approved_count'] ?? 0); ?></h3>
                                <small class="text-muted">₦<?php echo number_format($stats['approved_amount'] ?? 0, 2); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card" style="border-left-color: #ef4444;">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Rejected</h6>
                                <h3 class="mb-0 text-danger"><?php echo number_format($stats['rejected_count'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status Filter</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="All" <?php echo $status_filter === 'All' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Search by member name, IPPIS, or request ID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Withdrawal Requests -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Withdrawal Requests (<?php echo count($withdrawal_requests); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($withdrawal_requests)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No withdrawal requests found.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($withdrawal_requests as $request): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="request-card card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="mb-1">Request #<?php echo $request['request_id']; ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar"></i> 
                                                            <?php echo date('M d, Y g:i A', strtotime($request['request_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                                        <?php echo $request['status']; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <strong><?php echo htmlspecialchars($request['member_name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        IPPIS: <?php echo htmlspecialchars($request['ippis_no']); ?> | 
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['phone']); ?>
                                                    </small>
                                                </div>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Account</small>
                                                        <strong><?php echo htmlspecialchars($request['account_name']); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($request['account_number']); ?></small>
                                                    </div>
                                                    <div class="col-6 text-end">
                                                        <small class="text-muted d-block">Amount</small>
                                                        <h4 class="mb-0 text-primary">₦<?php echo number_format($request['amount'], 2); ?></h4>
                                                        <small class="text-muted">Balance: ₦<?php echo number_format($request['balance'], 2); ?></small>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">Reason:</small>
                                                    <p class="mb-0"><?php echo htmlspecialchars($request['reason']); ?></p>
                                                </div>
                                                
                                                <?php if ($request['status'] === 'Pending'): ?>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-success flex-fill" 
                                                                onclick="showApproveModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['member_name'], ENT_QUOTES); ?>', <?php echo $request['amount']; ?>)">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-danger flex-fill" 
                                                                onclick="showRejectModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['member_name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($request['admin_comment']): ?>
                                                        <div class="alert alert-info mb-0">
                                                            <small><strong>Admin Comment:</strong><br><?php echo htmlspecialchars($request['admin_comment']); ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div> <!-- container-fluid -->
        </main>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo CSRFProtection::getTokenField(); ?>
                    <input type="hidden" name="request_id" id="approve_request_id">
                    <div class="modal-body">
                        <p>Are you sure you want to approve this withdrawal?</p>
                        <div class="alert alert-info">
                            <strong><span id="approve_member_name"></span></strong><br>
                            Amount: <strong>₦<span id="approve_amount"></span></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Admin Comment (Optional)</label>
                            <textarea name="admin_comment" class="form-control" rows="2" placeholder="Add a note..."></textarea>
                        </div>
                        <div class="alert alert-warning mb-0">
                            <small><i class="fas fa-exclamation-triangle"></i> This will deduct the amount from the member's savings account.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_withdrawal" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve & Process
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo CSRFProtection::getTokenField(); ?>
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <div class="modal-body">
                        <p>Are you sure you want to reject this withdrawal request?</p>
                        <div class="alert alert-info">
                            <strong><span id="reject_member_name"></span></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea name="admin_comment" class="form-control" rows="3" placeholder="Please provide a reason..." required></textarea>
                            <small class="text-muted">This will be visible to the member.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_withdrawal" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showApproveModal(requestId, memberName, amount) {
            document.getElementById('approve_request_id').value = requestId;
            document.getElementById('approve_member_name').textContent = memberName;
            document.getElementById('approve_amount').textContent = amount.toLocaleString('en-NG', {minimumFractionDigits: 2});
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
        
        function showRejectModal(requestId, memberName) {
            document.getElementById('reject_request_id').value = requestId;
            document.getElementById('reject_member_name').textContent = memberName;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
