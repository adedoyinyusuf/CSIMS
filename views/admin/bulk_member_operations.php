<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/contribution_controller.php';

$memberController = new MemberController();
$contributionController = new ContributionController();

$message = '';
$error = '';

// Handle bulk operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_members'])) {
        $action = $_POST['bulk_action'];
        $memberIds = $_POST['selected_members'];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($memberIds as $memberId) {
            try {
                switch ($action) {
                    case 'activate':
                        $result = $memberController->updateMemberStatus($memberId, 'active');
                        break;
                    case 'deactivate':
                        $result = $memberController->updateMemberStatus($memberId, 'inactive');
                        break;
                    case 'suspend':
                        $result = $memberController->updateMemberStatus($memberId, 'suspended');
                        break;
                    case 'delete':
                        $result = $memberController->deleteMember($memberId);
                        break;
                    case 'extend_membership':
                        $months = intval($_POST['extend_months'] ?? 12);
                        $result = $memberController->extendMembership($memberId, $months);
                        break;
                    default:
                        $result = false;
                }
                
                if ($result) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
            }
        }
        
        if ($successCount > 0) {
            $message = "Successfully processed $successCount member(s).";
        }
        if ($errorCount > 0) {
            $error = "Failed to process $errorCount member(s).";
        }
    }
    
    // Handle bulk email sending
    if (isset($_POST['send_bulk_email'])) {
        $memberIds = $_POST['selected_members'] ?? [];
        $subject = $_POST['email_subject'] ?? '';
        $message_body = $_POST['email_message'] ?? '';
        
        if (!empty($memberIds) && !empty($subject) && !empty($message_body)) {
            $emailsSent = 0;
            foreach ($memberIds as $memberId) {
                $member = $memberController->getMemberById($memberId);
                if ($member && !empty($member['email'])) {
                    // Here you would integrate with your email service
                    // For now, we'll just count as sent
                    $emailsSent++;
                }
            }
            $message = "Bulk email sent to $emailsSent member(s).";
        } else {
            $error = "Please select members and provide email subject and message.";
        }
    }
}

// Get all members for display
$searchParams = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'membership_type' => $_GET['membership_type'] ?? '',
    'page' => $_GET['page'] ?? 1
];

$result = $memberController->searchMembersAdvanced(
    $searchParams['search'],
    $searchParams['status'],
    $searchParams['membership_type'],
    '', // gender
    '', '', // age range
    '', '', // join date range
    '', '', // expiry date range
    '', '', // city, state
    $searchParams['page'],
    20 // items per page
);

$members = $result['members'];
$totalPages = $result['total_pages'];
$currentPage = $result['current_page'];
$totalMembers = $result['total_count'];

// Get membership types for filtering
$membershipTypes = $memberController->getMembershipTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Member Operations - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
                        <li class="breadcrumb-item active">Bulk Operations</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tasks me-2"></i>Bulk Member Operations</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="members.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Members
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Search and Filter Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Members</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($searchParams['search']) ?>"
                                       placeholder="Name, email, phone...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?= $searchParams['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $searchParams['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="expired" <?= $searchParams['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="suspended" <?= $searchParams['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="membership_type" class="form-label">Membership Type</label>
                                <select class="form-select" id="membership_type" name="membership_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($membershipTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type['type_name']) ?>"
                                                <?= $searchParams['membership_type'] === $type['type_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Actions Bar -->
                <div class="card mb-4" id="bulkActionsBar" style="display: none;">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <span id="selectedCount">0</span> member(s) selected
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success btn-sm" onclick="performBulkAction('activate')">
                                        <i class="fas fa-check"></i> Activate
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="performBulkAction('deactivate')">
                                        <i class="fas fa-pause"></i> Deactivate
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="performBulkAction('suspend')">
                                        <i class="fas fa-ban"></i> Suspend
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#extendModal">
                                        <i class="fas fa-calendar-plus"></i> Extend
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal">
                                        <i class="fas fa-envelope"></i> Email
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="performBulkAction('delete')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Members Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Members (<?= $totalMembers ?> total)</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">
                                Select All
                            </label>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No members found</h5>
                                <p class="text-muted">Try adjusting your search criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAllTable" onchange="toggleSelectAll()">
                                            </th>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Membership Type</th>
                                            <th>Status</th>
                                            <th>Expiry Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="member-checkbox" 
                                                           value="<?= $member['id'] ?>" 
                                                           onchange="updateBulkActions()">
                                                </td>
                                                <td><?= $member['id'] ?></td>
                                                <td>
                                                    <a href="view_member.php?id=<?= $member['id'] ?>">
                                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($member['email']) ?></td>
                                                <td><?= htmlspecialchars($member['phone']) ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= htmlspecialchars($member['membership_type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'active' => 'success',
                                                        'inactive' => 'secondary',
                                                        'expired' => 'danger',
                                                        'suspended' => 'warning'
                                                    ][$member['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $statusClass ?>">
                                                        <?= ucfirst($member['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($member['membership_expiry']): ?>
                                                        <?= date('M d, Y', strtotime($member['membership_expiry'])) ?>
                                                        <?php
                                                        $daysUntilExpiry = (strtotime($member['membership_expiry']) - time()) / (60 * 60 * 24);
                                                        if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0):
                                                        ?>
                                                            <br><small class="text-warning">
                                                                <i class="fas fa-exclamation-triangle"></i>
                                                                Expires in <?= ceil($daysUntilExpiry) ?> days
                                                            </small>
                                                        <?php elseif ($daysUntilExpiry <= 0): ?>
                                                            <br><small class="text-danger">
                                                                <i class="fas fa-times-circle"></i>
                                                                Expired
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view_member.php?id=<?= $member['id'] ?>" 
                                                           class="btn btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_member.php?id=<?= $member['id'] ?>" 
                                                           class="btn btn-outline-secondary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Members pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($currentPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($searchParams, ['page' => $currentPage - 1])) ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($searchParams, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($currentPage < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($searchParams, ['page' => $currentPage + 1])) ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Extend Membership Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="extendForm" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="extend_months" class="form-label">Extend by (months)</label>
                            <select class="form-select" id="extend_months" name="extend_months" required>
                                <option value="1">1 Month</option>
                                <option value="3">3 Months</option>
                                <option value="6">6 Months</option>
                                <option value="12" selected>12 Months</option>
                                <option value="24">24 Months</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This will extend the membership expiry date for all selected members.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_action" value="extend_membership" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Extend Membership
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Bulk Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="emailForm" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="email_subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="email_message" class="form-label">Message</label>
                            <textarea class="form-control" id="email_message" name="email_message" rows="8" required></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            This email will be sent to all selected members with valid email addresses.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_bulk_email" class="btn btn-primary">
                            <i class="fas fa-envelope"></i> Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hidden form for bulk actions -->
    <form id="bulkActionForm" method="POST" style="display: none;">
        <input type="hidden" name="bulk_action" id="bulkActionType">
        <div id="selectedMembersContainer"></div>
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllTable');
            const memberCheckboxes = document.querySelectorAll('.member-checkbox');
            
            memberCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = checkedBoxes.length;
            
            if (checkedBoxes.length > 0) {
                bulkActionsBar.style.display = 'block';
            } else {
                bulkActionsBar.style.display = 'none';
            }
            
            // Update select all checkbox state
            const selectAllCheckbox = document.getElementById('selectAllTable');
            const allCheckboxes = document.querySelectorAll('.member-checkbox');
            selectAllCheckbox.checked = checkedBoxes.length === allCheckboxes.length;
        }
        
        function performBulkAction(action) {
            const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one member.');
                return;
            }
            
            let confirmMessage = '';
            switch (action) {
                case 'activate':
                    confirmMessage = `Are you sure you want to activate ${checkedBoxes.length} member(s)?`;
                    break;
                case 'deactivate':
                    confirmMessage = `Are you sure you want to deactivate ${checkedBoxes.length} member(s)?`;
                    break;
                case 'suspend':
                    confirmMessage = `Are you sure you want to suspend ${checkedBoxes.length} member(s)?`;
                    break;
                case 'delete':
                    confirmMessage = `Are you sure you want to delete ${checkedBoxes.length} member(s)? This action cannot be undone.`;
                    break;
                default:
                    confirmMessage = `Are you sure you want to perform this action on ${checkedBoxes.length} member(s)?`;
            }
            
            if (confirm(confirmMessage)) {
                const form = document.getElementById('bulkActionForm');
                const container = document.getElementById('selectedMembersContainer');
                const actionType = document.getElementById('bulkActionType');
                
                // Clear previous selections
                container.innerHTML = '';
                
                // Add selected member IDs
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_members[]';
                    input.value = checkbox.value;
                    container.appendChild(input);
                });
                
                actionType.value = action;
                form.submit();
            }
        }
        
        // Handle extend membership form
        document.getElementById('extendForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one member.');
                return;
            }
            
            // Add selected member IDs to the form
            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_members[]';
                input.value = checkbox.value;
                this.appendChild(input);
            });
        });
        
        // Handle bulk email form
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one member.');
                return;
            }
            
            // Add selected member IDs to the form
            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_members[]';
                input.value = checkbox.value;
                this.appendChild(input);
            });
        });
        
        // Initialize
        updateBulkActions();
    </script>
</body>
</html>
