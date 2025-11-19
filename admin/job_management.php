<?php
session_start();

require_once '../classes/JobSchedulerService.php';
require_once '../includes/config/database.php';

// Simple admin authentication check that matches login_process.php
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../views/auth/login.php');
    exit();
}

$jobScheduler = new JobSchedulerService();
$db = (new PdoDatabase())->getConnection();

// Helper function to check if a column exists
function hasColumn($db, $table, $column) {
    try {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Check what columns exist in system_jobs table
$hasStatus = hasColumn($db, 'system_jobs', 'status');
$hasScheduledAt = hasColumn($db, 'system_jobs', 'scheduled_at');
$hasCreatedAt = hasColumn($db, 'system_jobs', 'created_at');
$hasPriority = hasColumn($db, 'system_jobs', 'priority');
$hasExecutedAt = hasColumn($db, 'system_jobs', 'executed_at');
$hasCompletedAt = hasColumn($db, 'system_jobs', 'completed_at');
$hasParameters = hasColumn($db, 'system_jobs', 'parameters');
$hasUpdatedAt = hasColumn($db, 'system_jobs', 'updated_at');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'run_jobs':
                $results = $jobScheduler->runPendingJobs();
                echo json_encode([
                    'success' => true,
                    'message' => 'Processed ' . count($results) . ' jobs',
                    'results' => $results
                ]);
                break;
                
            case 'cancel_job':
                $jobId = (int)$_POST['job_id'];
                $cancelled = $jobScheduler->cancelJob($jobId);
                
                echo json_encode([
                    'success' => $cancelled,
                    'message' => $cancelled ? 'Job cancelled successfully' : 'Job could not be cancelled'
                ]);
                break;
                
            case 'schedule_job':
                $jobType = $_POST['job_type'];
                $scheduledAt = $_POST['scheduled_at'];
                $parameters = !empty($_POST['parameters']) ? json_decode($_POST['parameters'], true) : [];
                $priority = (int)($_POST['priority'] ?? 5);
                
                $jobId = $jobScheduler->scheduleJob($jobType, null, $scheduledAt, $parameters, $priority);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Job scheduled successfully',
                    'job_id' => $jobId
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

// Get job statistics
$stats = $jobScheduler->getJobStatistics(30);

// Get recent jobs
$orderBy = $hasCreatedAt ? 'created_at DESC' : 'id DESC';
$sql = "SELECT * FROM system_jobs 
        ORDER BY $orderBy 
        LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute();
$recentJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending jobs
$whereParts = [];
if ($hasStatus) {
    $whereParts[] = "status = 'pending'";
} elseif ($hasExecutedAt && $hasCompletedAt) {
    $whereParts[] = "(executed_at IS NULL AND completed_at IS NULL)";
} elseif ($hasCompletedAt) {
    $whereParts[] = "completed_at IS NULL";
} elseif ($hasExecutedAt) {
    $whereParts[] = "executed_at IS NULL";
} elseif ($hasUpdatedAt && $hasCreatedAt) {
    // Fallback: treat rows with updated_at equal to created_at as not yet processed
    $whereParts[] = "(updated_at IS NULL OR updated_at = created_at)";
}

$whereClause = count($whereParts) ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$orderParts = [];
if ($hasPriority) {
    $orderParts[] = 'priority DESC';
}
if ($hasScheduledAt) {
    $orderParts[] = 'scheduled_at ASC';
} elseif ($hasCreatedAt) {
    $orderParts[] = 'created_at ASC';
} else {
    $orderParts[] = 'id ASC';
}
$orderBy = implode(', ', $orderParts);

$sql = "SELECT * FROM system_jobs 
        $whereClause 
        ORDER BY $orderBy 
        LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute();
$pendingJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$totalJobs = count($recentJobs);
$pendingCount = count($pendingJobs);
if ($hasStatus) {
    $completedCount = count(array_filter($recentJobs, function($job) { return isset($job['status']) && $job['status'] === 'completed'; }));
    $failedCount = count(array_filter($recentJobs, function($job) { return isset($job['status']) && $job['status'] === 'failed'; }));
} else {
    // Fallback: derive from timestamps or updated_at > created_at
    if ($hasCompletedAt) {
        $completedCount = count(array_filter($recentJobs, function($job) { return isset($job['completed_at']) && $job['completed_at']; }));
    } elseif ($hasUpdatedAt && $hasCreatedAt) {
        $completedCount = count(array_filter($recentJobs, function($job) {
            return isset($job['updated_at']) && isset($job['created_at']) && $job['updated_at'] > $job['created_at'];
        }));
    } else {
        $completedCount = 0;
    }
    // Without status we cannot reliably detect failures; set to 0
    $failedCount = 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management - CSIMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .job-status-badge {
            font-size: 0.8rem;
        }
        
        .job-type-badge {
            font-size: 0.75rem;
            background-color: #e9ecef;
            color: #495057;
        }
        
        .priority-indicator {
            width: 4px;
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
            border-radius: 4px 0 0 4px;
        }
        
        .priority-high { background-color: #dc3545; }
        .priority-medium { background-color: #ffc107; }
        .priority-low { background-color: #28a745; }
        
        .job-card {
            position: relative;
            transition: transform 0.2s;
        }
        
        .job-card:hover {
            transform: translateX(2px);
        }
        
        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h2><i class="fas fa-cogs me-2"></i>Job Management</h2>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-success me-3" onclick="runJobs()">
                            <i class="fas fa-play me-1"></i>Run Pending Jobs
                        </button>
                        <button class="btn btn-primary me-3" data-bs-toggle="modal" data-bs-target="#scheduleJobModal">
                            <i class="fas fa-plus me-1"></i>Schedule Job
                        </button>
                        <a href="../views/admin/dashboard.php" class="btn btn-outline-secondary">
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
                        <div class="text-warning">
                            <i class="fas fa-clock fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $pendingCount ?></h3>
                        <p class="text-muted mb-0">Pending Jobs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $completedCount ?></h3>
                        <p class="text-muted mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-danger">
                            <i class="fas fa-times-circle fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $failedCount ?></h3>
                        <p class="text-muted mb-0">Failed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-info">
                            <i class="fas fa-list fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= $totalJobs ?></h3>
                        <p class="text-muted mb-0">Total Jobs</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row mt-4">
            <!-- Pending Jobs -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-hourglass-half text-warning me-2"></i>
                            Pending Jobs (<?= count($pendingJobs) ?>)
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($pendingJobs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6>No Pending Jobs</h6>
                                <p class="text-muted">All jobs are up to date</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingJobs as $job): ?>
                                <?php
                                $priorityVal = ($hasPriority && isset($job['priority'])) ? (int)$job['priority'] : 0;
                                $displayJobType = isset($job['job_type']) ? htmlspecialchars($job['job_type']) : 'Job';
                                $displayJobId = isset($job['id']) ? $job['id'] : (isset($job['job_id']) ? $job['job_id'] : null);
                                $scheduledText = ($hasScheduledAt && isset($job['scheduled_at']) && $job['scheduled_at']) 
                                    ? date('M j, Y g:i A', strtotime($job['scheduled_at'])) 
                                    : 'Not scheduled';
                                ?>
                                <div class="job-card card mb-3">
                                    <div class="priority-indicator priority-<?= $priorityVal >= 7 ? 'high' : ($priorityVal >= 5 ? 'medium' : 'low') ?>"></div>
                                    <div class="card-body ps-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <span class="badge job-type-badge me-2"><?= $displayJobType ?></span>
                                                    Job #<?= $displayJobId ?? 'N/A' ?>
                                                </h6>
                                                <div class="small text-muted mb-2">
                                                    <div>
                                                        <i class="fas fa-clock me-1"></i>
                                                        Scheduled: <?= $scheduledText ?>
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-star me-1"></i>
                                                        Priority: <?= ($hasPriority && isset($job['priority'])) ? $job['priority'] : 'N/A' ?>
                                                    </div>
                                                </div>
                                                <?php if ($hasParameters && isset($job['parameters']) && $job['parameters']): ?>
                                                    <div class="small">
                                                        <strong>Parameters:</strong>
                                                        <div class="code-block mt-1">
                                                            <?= htmlspecialchars($job['parameters']) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ms-3">
                                                <?php if ($displayJobId): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="cancelJob(<?= (int)$displayJobId ?>)"
                                                        title="Cancel Job">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Jobs -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Jobs
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach (array_slice($recentJobs, 0, 20) as $job): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0 me-3">
                                    <?php
                                    $statusIcon = 'fas fa-circle';
                                    $statusColor = 'text-muted';
                                    
                                    // Determine status from available columns
                                    $jobStatus = 'unknown';
                                    if ($hasStatus && isset($job['status'])) {
                                        $jobStatus = $job['status'];
                                    } else {
                                        if ($hasCompletedAt && isset($job['completed_at']) && $job['completed_at']) {
                                            $jobStatus = 'completed';
                                        } elseif ($hasExecutedAt && isset($job['executed_at']) && $job['executed_at']) {
                                            $jobStatus = 'running';
                                        } elseif ($hasExecutedAt || $hasCompletedAt) {
                                            $jobStatus = 'pending';
                                        } elseif ($hasUpdatedAt && $hasCreatedAt) {
                                            // If updated_at advanced beyond created_at, assume completed
                                            if (isset($job['updated_at']) && isset($job['created_at']) && $job['updated_at'] > $job['created_at']) {
                                                $jobStatus = 'completed';
                                            } else {
                                                $jobStatus = 'pending';
                                            }
                                        }
                                    }
                                    
                                    switch ($jobStatus) {
                                        case 'completed':
                                            $statusIcon = 'fas fa-check-circle';
                                            $statusColor = 'text-success';
                                            break;
                                        case 'failed':
                                            $statusIcon = 'fas fa-times-circle';
                                            $statusColor = 'text-danger';
                                            break;
                                        case 'running':
                                            $statusIcon = 'fas fa-spinner';
                                            $statusColor = 'text-primary';
                                            break;
                                        case 'pending':
                                            $statusIcon = 'fas fa-clock';
                                            $statusColor = 'text-warning';
                                            break;
                                        case 'cancelled':
                                            $statusIcon = 'fas fa-ban';
                                            $statusColor = 'text-secondary';
                                            break;
                                        default:
                                            $statusIcon = 'fas fa-question-circle';
                                            $statusColor = 'text-muted';
                                            break;
                                    }
                                    ?>
                                    <i class="<?= $statusIcon ?> <?= $statusColor ?>"></i>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge job-type-badge me-2"><?= isset($job['job_type']) ? htmlspecialchars($job['job_type']) : 'Job' ?></span>
                                        <small class="text-muted">Job #<?= isset($job['id']) ? $job['id'] : (isset($job['job_id']) ? $job['job_id'] : 'N/A') ?></small>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if ($hasCreatedAt && isset($job['created_at'])): ?>
                                            <?= date('M j, g:i A', strtotime($job['created_at'])) ?>
                                        <?php else: ?>
                                            <?php $displayJobId = isset($job['id']) ? $job['id'] : (isset($job['job_id']) ? $job['job_id'] : null); ?>
                                            <?php if ($displayJobId): ?>Job #<?= $displayJobId ?><?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($hasCompletedAt && isset($job['completed_at']) && $job['completed_at']): ?>
                                            → <?= date('g:i A', strtotime($job['completed_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($job['result_message']) && $job['result_message']): ?>
                                        <div class="small text-muted mt-1" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($job['result_message']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="badge job-status-badge bg-<?= 
                                        $jobStatus === 'completed' ? 'success' : 
                                        ($jobStatus === 'failed' ? 'danger' : 
                                        ($jobStatus === 'running' ? 'primary' : 
                                        ($jobStatus === 'pending' ? 'warning' : 'secondary'))) ?>">
                                        <?= ucfirst($jobStatus) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Statistics -->
        <?php if (!empty($stats)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Job Statistics (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Job Type</th>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Avg Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge job-type-badge"><?= htmlspecialchars($stat['job_type']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge job-status-badge bg-<?= 
                                                $stat['status'] === 'completed' ? 'success' : 
                                                ($stat['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($stat['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= $stat['count'] ?></td>
                                        <td><?= $stat['avg_duration'] ? number_format($stat['avg_duration'], 1) . 's' : 'N/A' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Schedule Job Modal -->
    <div class="modal fade" id="scheduleJobModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleJobForm">
                        <div class="mb-3">
                            <label for="jobType" class="form-label">Job Type *</label>
                            <select class="form-select" id="jobType" name="job_type" required>
                                <option value="">Select job type</option>
                                <option value="monthly_interest">Monthly Interest Calculation</option>
                                <option value="penalty_calculation">Penalty Calculation</option>
                                <option value="account_maintenance">Account Maintenance</option>
                                <option value="backup_database">Database Backup</option>
                                <option value="send_notifications">Send Notifications</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="scheduledAt" class="form-label">Scheduled Time *</label>
                            <input type="datetime-local" class="form-control" id="scheduledAt" name="scheduled_at" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="1">1 - Lowest</option>
                                <option value="3">3 - Low</option>
                                <option value="5" selected>5 - Medium</option>
                                <option value="7">7 - High</option>
                                <option value="9">9 - Highest</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parameters" class="form-label">Parameters (JSON)</label>
                            <textarea class="form-control code-block" id="parameters" name="parameters" rows="4" 
                                      placeholder='{"key": "value"}'></textarea>
                            <div class="form-text">Optional JSON parameters for the job</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="scheduleJob()">
                        <span class="spinner-border spinner-border-sm me-1" id="scheduleSpinner" style="display: none;"></span>
                        Schedule Job
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runJobs() {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Running...';
            btn.disabled = true;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=run_jobs'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Error: ' + error.message);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function cancelJob(jobId) {
            if (!confirm('Are you sure you want to cancel this job?')) return;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=cancel_job&job_id=${jobId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Error: ' + error.message);
            });
        }

        function scheduleJob() {
            const form = document.getElementById('scheduleJobForm');
            const formData = new FormData(form);
            formData.append('action', 'schedule_job');
            
            const btn = document.querySelector('.modal-footer .btn-primary');
            const spinner = document.getElementById('scheduleSpinner');
            
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('scheduleJobModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Error: ' + error.message);
            })
            .finally(() => {
                btn.disabled = false;
                spinner.style.display = 'none';
            });
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
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Set default scheduled time to 1 hour from now
        document.addEventListener('DOMContentLoaded', function() {
            const scheduledAt = document.getElementById('scheduledAt');
            const now = new Date();
            now.setHours(now.getHours() + 1);
            scheduledAt.value = now.toISOString().slice(0, 16);
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>