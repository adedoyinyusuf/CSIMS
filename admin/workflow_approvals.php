<?php
session_start();
require_once '../classes/DatabaseConnection.php';
require_once '../classes/WorkflowService.php';
require_once '../classes/UserManagement.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$workflowService = new WorkflowService();
$userManagement = new UserManagement();
$currentUser = $_SESSION['user_id'];

// Handle AJAX requests for approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'approve':
            case 'reject':
            case 'request_changes':
                $workflowId = (int)$_POST['workflow_id'];
                $comments = $_POST['comments'] ?? null;
                
                $result = $workflowService->processApproval($workflowId, $currentUser, $_POST['action'], $comments);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Approval processed successfully',
                    'workflow' => $result
                ]);
                break;
                
            case 'get_workflow_details':
                $workflowId = (int)$_POST['workflow_id'];
                $workflow = $workflowService->getWorkflowById($workflowId);
                $history = $workflowService->getApprovalHistory($workflowId);
                
                echo json_encode([
                    'success' => true,
                    'workflow' => $workflow,
                    'history' => $history
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// Get pending approvals for current user
$pendingApprovals = $workflowService->getPendingApprovalsForUser($currentUser);

// Get workflow statistics
$stats = $workflowService->getWorkflowStats();
$loanStats = $workflowService->getWorkflowStats('loan');
$memberStats = $workflowService->getWorkflowStats('member_registration');

// Get recent workflows for overview
$db = DatabaseConnection::getInstance()->getConnection();
$recentWorkflows = [];

try {
    $sql = "SELECT wa.*, wt.template_name, wt.entity_type, u.username as requested_by_name,
                   CASE WHEN wa.status = 'pending' THEN 
                        CONCAT('Level ', wa.current_level, ' of ', wa.total_levels)
                        ELSE UPPER(wa.status)
                   END as status_display
            FROM workflow_approvals wa
            JOIN workflow_templates wt ON wa.template_id = wt.id
            LEFT JOIN users u ON wa.requested_by = u.id
            ORDER BY wa.created_at DESC 
            LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $recentWorkflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching recent workflows: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Approvals - CSIMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .workflow-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .workflow-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        
        .workflow-details {
            font-size: 0.9rem;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
        }
        
        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .modal-lg {
            max-width: 800px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -37px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #007cba;
        }
        
        .filter-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-link.active {
            color: #007cba;
            border-bottom-color: #007cba;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h2><i class="fas fa-tasks me-2"></i>Workflow Approvals</h2>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-3">
                            <?= count($pendingApprovals) ?> Pending
                        </span>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-primary">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $stats['total_workflows'] ?></h3>
                        <p class="text-muted mb-0">Total Workflows</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $stats['approved'] ?></h3>
                        <p class="text-muted mb-0">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-warning">
                            <i class="fas fa-clock fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $stats['pending'] ?></h3>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-info">
                            <i class="fas fa-hourglass-half fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= number_format($stats['avg_completion_hours'], 1) ?>h</h3>
                        <p class="text-muted mb-0">Avg. Completion</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row mt-4">
            <!-- Pending Approvals -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-circle text-warning me-2"></i>
                            Pending Approvals (<?= count($pendingApprovals) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingApprovals)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <h4>All caught up!</h4>
                                <p class="text-muted">No pending approvals at this time.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingApprovals as $approval): ?>
                                <div class="workflow-card card mb-3 priority-medium">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-2">
                                                    <?= htmlspecialchars($approval['template_name']) ?>
                                                    <span class="badge bg-primary status-badge ms-2">
                                                        <?= htmlspecialchars($approval['level_name']) ?>
                                                    </span>
                                                </h6>
                                                
                                                <div class="workflow-details">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <small class="text-muted d-block">
                                                                <i class="fas fa-user me-1"></i>
                                                                Requested by: <?= htmlspecialchars($approval['requested_by_name'] ?? 'System') ?>
                                                            </small>
                                                            <small class="text-muted d-block">
                                                                <i class="fas fa-clock me-1"></i>
                                                                Submitted: <?= date('M j, Y g:i A', strtotime($approval['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <?php if ($approval['amount']): ?>
                                                                <small class="text-muted d-block">
                                                                    <i class="fas fa-dollar-sign me-1"></i>
                                                                    Amount: <?= number_format($approval['amount'], 2) ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <small class="text-muted d-block">
                                                                <i class="fas fa-layer-group me-1"></i>
                                                                Level: <?= $approval['current_level'] ?> of <?= $approval['total_levels'] ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="action-buttons ms-3">
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="viewWorkflowDetails(<?= $approval['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="approveWorkflow(<?= $approval['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="rejectWorkflow(<?= $approval['id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="requestChanges(<?= $approval['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Workflows -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Workflows
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach (array_slice($recentWorkflows, 0, 10) as $workflow): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <?php
                                    $iconClass = 'fas fa-circle';
                                    $iconColor = 'text-muted';
                                    
                                    switch ($workflow['status']) {
                                        case 'approved': $iconColor = 'text-success'; break;
                                        case 'rejected': $iconColor = 'text-danger'; break;
                                        case 'pending': $iconColor = 'text-warning'; break;
                                        case 'timeout': $iconColor = 'text-secondary'; break;
                                    }
                                    ?>
                                    <i class="<?= $iconClass ?> <?= $iconColor ?> me-2"></i>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <small class="d-block fw-bold">
                                        <?= htmlspecialchars($workflow['template_name']) ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <?= htmlspecialchars($workflow['requested_by_name'] ?? 'System') ?>
                                    </small>
                                    <small class="text-muted">
                                        <?= date('M j, g:i A', strtotime($workflow['created_at'])) ?>
                                    </small>
                                </div>
                                <div class="flex-shrink-0">
                                    <small class="badge bg-light text-dark">
                                        <?= htmlspecialchars($workflow['status_display']) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Workflow Details Modal -->
    <div class="modal fade" id="workflowDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Workflow Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="workflowDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="workflowDetailsActions">
                    <!-- Action buttons will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="actionForm">
                        <input type="hidden" id="actionWorkflowId" name="workflow_id">
                        <input type="hidden" id="actionType" name="action">
                        
                        <div class="mb-3">
                            <label for="actionComments" class="form-label">Comments:</label>
                            <textarea class="form-control" id="actionComments" name="comments" 
                                      rows="3" placeholder="Optional comments..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="confirmActionBtn" onclick="submitAction()">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentWorkflowId = null;

        function viewWorkflowDetails(workflowId) {
            const modal = new bootstrap.Modal(document.getElementById('workflowDetailsModal'));
            
            // Show loading state
            document.getElementById('workflowDetailsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Fetch workflow details
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_workflow_details&workflow_id=${workflowId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayWorkflowDetails(data.workflow, data.history);
                } else {
                    document.getElementById('workflowDetailsContent').innerHTML = 
                        `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('workflowDetailsContent').innerHTML = 
                    `<div class="alert alert-danger">Error loading workflow details: ${error.message}</div>`;
            });
        }

        function displayWorkflowDetails(workflow, history) {
            const content = `
                <div class="row">
                    <div class="col-md-8">
                        <h6>Workflow Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td>${workflow.template_name}</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="badge bg-${getStatusColor(workflow.status)}">${workflow.status}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Requested by:</strong></td>
                                <td>${workflow.requested_by_name || 'System'}</td>
                            </tr>
                            <tr>
                                <td><strong>Amount:</strong></td>
                                <td>${workflow.amount ? '$' + parseFloat(workflow.amount).toFixed(2) : 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>Level:</strong></td>
                                <td>${workflow.current_level} of ${workflow.total_levels}</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td>${new Date(workflow.created_at).toLocaleString()}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <h6>Approval History</h6>
                        <div class="timeline">
                            ${history.map(h => `
                                <div class="timeline-item">
                                    <small class="text-muted">${new Date(h.created_at).toLocaleString()}</small>
                                    <div><strong>${h.first_name} ${h.last_name}</strong></div>
                                    <div><span class="badge bg-${getActionColor(h.action)}">${h.action}</span></div>
                                    ${h.comments ? `<small class="text-muted">${h.comments}</small>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('workflowDetailsContent').innerHTML = content;
            
            // Update action buttons if workflow is pending
            const actionsContainer = document.getElementById('workflowDetailsActions');
            if (workflow.status === 'pending') {
                actionsContainer.innerHTML = `
                    <button type="button" class="btn btn-success" onclick="approveWorkflow(${workflow.id})">
                        <i class="fas fa-check me-1"></i>Approve
                    </button>
                    <button type="button" class="btn btn-danger" onclick="rejectWorkflow(${workflow.id})">
                        <i class="fas fa-times me-1"></i>Reject
                    </button>
                    <button type="button" class="btn btn-warning" onclick="requestChanges(${workflow.id})">
                        <i class="fas fa-edit me-1"></i>Request Changes
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                `;
            } else {
                actionsContainer.innerHTML = `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                `;
            }
        }

        function approveWorkflow(workflowId) {
            showActionModal(workflowId, 'approve', 'Approve Workflow', 'btn-success', 'Are you sure you want to approve this workflow?');
        }

        function rejectWorkflow(workflowId) {
            showActionModal(workflowId, 'reject', 'Reject Workflow', 'btn-danger', 'Are you sure you want to reject this workflow?');
        }

        function requestChanges(workflowId) {
            showActionModal(workflowId, 'request_changes', 'Request Changes', 'btn-warning', 'Request changes to this workflow?');
        }

        function showActionModal(workflowId, action, title, buttonClass, message) {
            currentWorkflowId = workflowId;
            
            document.getElementById('actionModalTitle').textContent = title;
            document.getElementById('actionWorkflowId').value = workflowId;
            document.getElementById('actionType').value = action;
            document.getElementById('actionComments').value = '';
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.className = `btn ${buttonClass}`;
            confirmBtn.textContent = title.split(' ')[0];
            
            // Hide details modal if open
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('workflowDetailsModal'));
            if (detailsModal) {
                detailsModal.hide();
            }
            
            const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
            actionModal.show();
        }

        function submitAction() {
            const form = document.getElementById('actionForm');
            const formData = new FormData(form);
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            const originalText = confirmBtn.textContent;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
            confirmBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('actionModal'));
                    modal.hide();
                    
                    // Show success message and reload page
                    showAlert('success', 'Action completed successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', `Error: ${data.message}`);
                }
            })
            .catch(error => {
                showAlert('danger', `Error: ${error.message}`);
            })
            .finally(() => {
                confirmBtn.textContent = originalText;
                confirmBtn.disabled = false;
            });
        }

        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'approved': 'success',
                'rejected': 'danger',
                'timeout': 'secondary',
                'changes_requested': 'info'
            };
            return colors[status] || 'secondary';
        }

        function getActionColor(action) {
            const colors = {
                'approve': 'success',
                'reject': 'danger',
                'request_changes': 'warning'
            };
            return colors[action] || 'primary';
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Auto-refresh pending approvals every 2 minutes
        setInterval(() => {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 120000);
    </script>
</body>
</html>