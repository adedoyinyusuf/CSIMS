<?php
session_start();

require_once 'classes/WorkflowService.php';
require_once 'includes/config/database.php';

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: login.php');
    exit();
}

$workflowService = new WorkflowService();
$db = (new PdoDatabase())->getConnection();

// Get member ID from session or database
$memberId = null;
try {
    $stmt = $db->prepare("SELECT id FROM members WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    $memberId = $member ? $member['id'] : null;
} catch (Exception $e) {
    error_log("Error getting member ID: " . $e->getMessage());
}

if (!$memberId) {
    header('Location: dashboard.php');
    exit();
}

// Get pending workflows for this member
$pendingWorkflows = [];
$completedWorkflows = [];

try {
    // Get pending workflows
    $stmt = $db->prepare("
        SELECT wa.*, wt.template_name, wt.entity_type,
               u.username as requested_by_name,
               CASE 
                   WHEN wa.entity_type = 'loan' THEN l.principal_amount
                   ELSE wa.amount
               END as display_amount,
               CASE 
                   WHEN wa.entity_type = 'loan' THEN l.purpose
                   ELSE NULL
               END as purpose,
               DATEDIFF(NOW(), wa.created_at) as days_pending
        FROM workflow_approvals wa
        JOIN workflow_templates wt ON wa.template_id = wt.id
        LEFT JOIN users u ON wa.requested_by = u.id
        LEFT JOIN loans l ON wa.entity_type = 'loan' AND wa.entity_id = l.id
        WHERE wa.status = 'pending'
        AND (
            (wa.entity_type = 'loan' AND l.member_id = ?) OR
            (wa.entity_type = 'member_registration' AND wa.entity_id = ?) OR
            (wa.requested_by = ?)
        )
        ORDER BY wa.created_at DESC
    ");
    $stmt->execute([$memberId, $memberId, $_SESSION['user_id']]);
    $pendingWorkflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get completed workflows from last 30 days
    $stmt = $db->prepare("
        SELECT wa.*, wt.template_name, wt.entity_type,
               u.username as requested_by_name,
               CASE 
                   WHEN wa.entity_type = 'loan' THEN l.principal_amount
                   ELSE wa.amount
               END as display_amount,
               CASE 
                   WHEN wa.entity_type = 'loan' THEN l.purpose
                   ELSE NULL
               END as purpose,
               DATEDIFF(wa.completed_at, wa.created_at) as processing_days
        FROM workflow_approvals wa
        JOIN workflow_templates wt ON wa.template_id = wt.id
        LEFT JOIN users u ON wa.requested_by = u.id
        LEFT JOIN loans l ON wa.entity_type = 'loan' AND wa.entity_id = l.id
        WHERE wa.status IN ('approved', 'rejected', 'timeout', 'changes_requested')
        AND wa.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND (
            (wa.entity_type = 'loan' AND l.member_id = ?) OR
            (wa.entity_type = 'member_registration' AND wa.entity_id = ?) OR
            (wa.requested_by = ?)
        )
        ORDER BY wa.completed_at DESC
    ");
    $stmt->execute([$memberId, $memberId, $_SESSION['user_id']]);
    $completedWorkflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add approval history for each workflow
    foreach ($pendingWorkflows as &$workflow) {
        $workflow['approval_history'] = $workflowService->getApprovalHistory($workflow['id']);
    }
    
    foreach ($completedWorkflows as &$workflow) {
        $workflow['approval_history'] = $workflowService->getApprovalHistory($workflow['id']);
    }
    
} catch (Exception $e) {
    error_log("Error fetching workflows: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .workflow-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #dee2e6;
        }
        
        .workflow-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .workflow-card.pending { border-left-color: #ffc107; }
        .workflow-card.approved { border-left-color: #28a745; }
        .workflow-card.rejected { border-left-color: #dc3545; }
        .workflow-card.timeout { border-left-color: #6c757d; }
        .workflow-card.changes_requested { border-left-color: #17a2b8; }
        
        .progress-steps {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        
        .step {
            position: relative;
            flex: 1;
            text-align: center;
        }
        
        .step-indicator {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
        }
        
        .step-indicator.completed {
            background-color: #28a745;
            color: white;
        }
        
        .step-indicator.current {
            background-color: #007cba;
            color: white;
        }
        
        .step-indicator.pending {
            background-color: #e9ecef;
            color: #6c757d;
            border: 2px solid #dee2e6;
        }
        
        .step-connector {
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            z-index: 1;
        }
        
        .step-connector.completed {
            background-color: #28a745;
        }
        
        .step:last-child .step-connector {
            display: none;
        }
        
        .approval-history {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
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
        
        .status-badge {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        
        .days-indicator {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .amount-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: #007cba;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h2><i class="fas fa-chart-line me-2"></i>Application Status</h2>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-warning me-3">
                            <?= count($pendingWorkflows) ?> Pending
                        </span>
                        <a href="member_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Applications -->
        <?php if (!empty($pendingWorkflows)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning bg-opacity-25">
                        <h5 class="mb-0">
                            <i class="fas fa-clock text-warning me-2"></i>
                            Pending Applications (<?= count($pendingWorkflows) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($pendingWorkflows as $workflow): ?>
                            <div class="workflow-card card mb-4 pending">
                                <div class="card-body">
                                    <!-- Header -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="card-title mb-1">
                                                <?= htmlspecialchars($workflow['template_name']) ?>
                                                <span class="badge bg-warning status-badge ms-2">Pending Approval</span>
                                            </h6>
                                            <div class="text-muted small">
                                                Submitted <?= $workflow['days_pending'] ?> day(s) ago
                                                • Application ID: #<?= $workflow['id'] ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($workflow['display_amount']): ?>
                                                <div class="amount-display">
                                                    ₦<?= number_format($workflow['display_amount'], 2) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="days-indicator">
                                                Level <?= $workflow['current_level'] ?> of <?= $workflow['total_levels'] ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Steps -->
                                    <div class="progress-steps">
                                        <?php for ($i = 1; $i <= $workflow['total_levels']; $i++): ?>
                                            <div class="step">
                                                <?php if ($i < $workflow['current_level']): ?>
                                                    <div class="step-indicator completed">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                    <small class="text-success">Approved</small>
                                                <?php elseif ($i == $workflow['current_level']): ?>
                                                    <div class="step-indicator current">
                                                        <i class="fas fa-clock"></i>
                                                    </div>
                                                    <small class="text-primary">In Review</small>
                                                <?php else: ?>
                                                    <div class="step-indicator pending"><?= $i ?></div>
                                                    <small class="text-muted">Pending</small>
                                                <?php endif; ?>
                                                
                                                <?php if ($i < $workflow['total_levels']): ?>
                                                    <div class="step-connector <?= $i < $workflow['current_level'] ? 'completed' : '' ?>"></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <!-- Application Details -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Application Type</small>
                                            <strong><?= htmlspecialchars($workflow['template_name']) ?></strong>
                                        </div>
                                        <?php if ($workflow['purpose']): ?>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Purpose</small>
                                            <strong><?= htmlspecialchars($workflow['purpose']) ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Approval History -->
                                    <?php if (!empty($workflow['approval_history'])): ?>
                                    <div class="approval-history mt-3">
                                        <h6 class="mb-3">Approval History</h6>
                                        <div class="timeline">
                                            <?php foreach ($workflow['approval_history'] as $history): ?>
                                                <div class="timeline-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong><?= htmlspecialchars($history['first_name'] . ' ' . $history['last_name']) ?></strong>
                                                            <span class="badge bg-<?= $history['action'] === 'approve' ? 'success' : ($history['action'] === 'reject' ? 'danger' : 'warning') ?> ms-2">
                                                                <?= ucfirst($history['action']) ?>
                                                            </span>
                                                            <div class="small text-muted">
                                                                <?= date('M j, Y g:i A', strtotime($history['created_at'])) ?>
                                                            </div>
                                                            <?php if ($history['comments']): ?>
                                                                <div class="mt-1 small">
                                                                    <em>"<?= htmlspecialchars($history['comments']) ?>"</em>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Expected Timeline -->
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">Expected Processing Time</small>
                                                <strong>3-5 business days</strong>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">Current Status</small>
                                                <strong>Awaiting Level <?= $workflow['current_level'] ?> Approval</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Completed Applications -->
        <?php if (!empty($completedWorkflows)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Recent Completed Applications (<?= count($completedWorkflows) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($completedWorkflows as $workflow): ?>
                            <div class="workflow-card card mb-3 <?= $workflow['status'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="card-title mb-1">
                                                <?= htmlspecialchars($workflow['template_name']) ?>
                                                <span class="badge bg-<?= $workflow['status'] === 'approved' ? 'success' : ($workflow['status'] === 'rejected' ? 'danger' : 'secondary') ?> status-badge ms-2">
                                                    <?= ucfirst(str_replace('_', ' ', $workflow['status'])) ?>
                                                </span>
                                            </h6>
                                            <div class="text-muted small">
                                                Completed on <?= date('M j, Y', strtotime($workflow['completed_at'])) ?>
                                                • Processed in <?= $workflow['processing_days'] ?> day(s)
                                                • Application ID: #<?= $workflow['id'] ?>
                                            </div>
                                            <?php if ($workflow['display_amount']): ?>
                                                <div class="mt-1">
                                                    <strong>Amount: ₦<?= number_format($workflow['display_amount'], 2) ?></strong>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($workflow['final_comments']): ?>
                                                <div class="mt-2 p-2 bg-light rounded small">
                                                    <strong>Final Comments:</strong> <?= htmlspecialchars($workflow['final_comments']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($workflow['status'] === 'approved'): ?>
                                                <i class="fas fa-check-circle fa-2x text-success"></i>
                                            <?php elseif ($workflow['status'] === 'rejected'): ?>
                                                <i class="fas fa-times-circle fa-2x text-danger"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock fa-2x text-secondary"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($pendingWorkflows) && empty($completedWorkflows)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Applications Found</h4>
                    <p class="text-muted mb-4">You don't have any loan applications or approval requests at this time.</p>
                    <a href="loan_application.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Apply for Loan
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 2 minutes to check for updates
        setInterval(function() {
            // Only refresh if there are pending workflows
            <?php if (!empty($pendingWorkflows)): ?>
            location.reload();
            <?php endif; ?>
        }, 120000);

        // Add smooth animations for progress indicators
        document.addEventListener('DOMContentLoaded', function() {
            const progressSteps = document.querySelectorAll('.progress-steps');
            
            progressSteps.forEach(progress => {
                const steps = progress.querySelectorAll('.step-indicator');
                
                steps.forEach((step, index) => {
                    setTimeout(() => {
                        step.style.opacity = '0';
                        step.style.transform = 'scale(0.8)';
                        
                        setTimeout(() => {
                            step.style.opacity = '1';
                            step.style.transform = 'scale(1)';
                        }, 100);
                    }, index * 150);
                });
            });
        });
    </script>
</body>
</html>