<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/contribution_controller.php';

$memberController = new MemberController();
$contributionController = new ContributionController();

// Get filter parameters
$memberId = $_GET['member_id'] ?? '';
$activityType = $_GET['activity_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = 20;

// Build activity log query
$activities = [];
$totalActivities = 0;

// Get member activities from different sources
try {
    // 1. Member registration/updates
    $memberQuery = "SELECT 
                        m.id as member_id,
                        CONCAT(m.first_name, ' ', m.last_name) as member_name,
                        'member_registration' as activity_type,
                        'Member registered' as activity_description,
                        m.join_date as activity_date,
                        'System' as performed_by
                    FROM members m
                    WHERE 1=1";
    
    // 2. Contributions
    $contributionQuery = "SELECT 
                            c.member_id,
                            CONCAT(m.first_name, ' ', m.last_name) as member_name,
                            'contribution' as activity_type,
                            CONCAT('Contribution: $', c.amount, ' (', c.contribution_type, ')') as activity_description,
                            c.contribution_date as activity_date,
                            'System' as performed_by
                        FROM contributions c
                        JOIN members m ON c.member_id = m.id
                        WHERE 1=1";
    
    // 3. Membership renewals (from contributions with type 'Membership Fee')
    $renewalQuery = "SELECT 
                        c.member_id,
                        CONCAT(m.first_name, ' ', m.last_name) as member_name,
                        'membership_renewal' as activity_type,
                        CONCAT('Membership renewed: $', c.amount) as activity_description,
                        c.contribution_date as activity_date,
                        'System' as performed_by
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE c.contribution_type = 'Membership Fee'";
    
    // Apply filters
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($memberId)) {
        $whereConditions[] = "member_id = ?";
        $params[] = $memberId;
        $types .= 'i';
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "activity_date >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "activity_date <= ?";
        $params[] = $dateTo;
        $types .= 's';
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = ' AND ' . implode(' AND ', $whereConditions);
    }
    
    // Combine all queries
    $unionQuery = "(
        $memberQuery $whereClause
    ) UNION ALL (
        $contributionQuery $whereClause
    ) UNION ALL (
        $renewalQuery $whereClause
    )";
    
    // Add activity type filter if specified
    if (!empty($activityType)) {
        $unionQuery = "SELECT * FROM ($unionQuery) as combined WHERE activity_type = ?";
        $params[] = $activityType;
        $types .= 's';
    }
    
    // Add ordering and pagination
    $unionQuery .= " ORDER BY activity_date DESC";
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM ($unionQuery) as counted";
    $countStmt = $memberController->conn->prepare($countQuery);
    if (!empty($types)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalActivities = $countStmt->get_result()->fetch_assoc()['total'];
    
    // Add pagination
    $offset = ($page - 1) * $itemsPerPage;
    $unionQuery .= " LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage;
    $params[] = $offset;
    $types .= 'ii';
    
    // Execute main query
    $stmt = $memberController->conn->prepare($unionQuery);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
} catch (Exception $e) {
    $error = "Error loading activity log: " . $e->getMessage();
}

$totalPages = ceil($totalActivities / $itemsPerPage);

// Get all members for filter dropdown
$allMembers = $memberController->getAllMembers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Activity Log - CSIMS</title>
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
                        <li class="breadcrumb-item active">Activity Log</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-history me-2"></i>Member Activity Log</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="exportLog()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="refreshLog()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Error Display -->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filter Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Activities</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="member_id" class="form-label">Member</label>
                                <select class="form-select" id="member_id" name="member_id">
                                    <option value="">All Members</option>
                                    <?php foreach ($allMembers as $member): ?>
                                        <option value="<?= $member['id'] ?>" 
                                                <?= $memberId == $member['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="activity_type" class="form-label">Activity Type</label>
                                <select class="form-select" id="activity_type" name="activity_type">
                                    <option value="">All Types</option>
                                    <option value="member_registration" <?= $activityType === 'member_registration' ? 'selected' : '' ?>>
                                        Member Registration
                                    </option>
                                    <option value="contribution" <?= $activityType === 'contribution' ? 'selected' : '' ?>>
                                        Contribution
                                    </option>
                                    <option value="membership_renewal" <?= $activityType === 'membership_renewal' ? 'selected' : '' ?>>
                                        Membership Renewal
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?= htmlspecialchars($dateTo) ?>">
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
                
                <!-- Activity Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?= $totalActivities ?></h5>
                                <p class="card-text">Total Activities</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <?= count(array_filter($activities, function($a) { return $a['activity_type'] === 'contribution'; })) ?>
                                </h5>
                                <p class="card-text">Contributions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info">
                                    <?= count(array_filter($activities, function($a) { return $a['activity_type'] === 'membership_renewal'; })) ?>
                                </h5>
                                <p class="card-text">Renewals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <?= count(array_filter($activities, function($a) { return $a['activity_type'] === 'member_registration'; })) ?>
                                </h5>
                                <p class="card-text">Registrations</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Activity Log</h5>
                        <small class="text-muted">
                            Showing <?= count($activities) ?> of <?= $totalActivities ?> activities
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No activities found</h5>
                                <p class="text-muted">Try adjusting your filter criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Member</th>
                                            <th>Activity Type</th>
                                            <th>Description</th>
                                            <th>Performed By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activities as $activity): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?= date('M d, Y', strtotime($activity['activity_date'])) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= date('h:i A', strtotime($activity['activity_date'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="view_member.php?id=<?= $activity['member_id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($activity['member_name']) ?>
                                                    </a>
                                                    <br>
                                                    <small class="text-muted">ID: <?= $activity['member_id'] ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $typeClass = [
                                                        'member_registration' => 'primary',
                                                        'contribution' => 'success',
                                                        'membership_renewal' => 'info',
                                                        'status_change' => 'warning'
                                                    ][$activity['activity_type']] ?? 'secondary';
                                                    
                                                    $typeIcon = [
                                                        'member_registration' => 'user-plus',
                                                        'contribution' => 'dollar-sign',
                                                        'membership_renewal' => 'refresh',
                                                        'status_change' => 'edit'
                                                    ][$activity['activity_type']] ?? 'circle';
                                                    ?>
                                                    <span class="badge bg-<?= $typeClass ?>">
                                                        <i class="fas fa-<?= $typeIcon ?> me-1"></i>
                                                        <?= ucwords(str_replace('_', ' ', $activity['activity_type'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($activity['activity_description']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?= htmlspecialchars($activity['performed_by']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view_member.php?id=<?= $activity['member_id'] ?>" 
                                                           class="btn btn-outline-primary" title="View Member">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($activity['activity_type'] === 'contribution'): ?>
                                                            <a href="contributions.php?member_id=<?= $activity['member_id'] ?>" 
                                                               class="btn btn-outline-success" title="View Contributions">
                                                                <i class="fas fa-dollar-sign"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Activity log pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportLog() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = '?' + params.toString();
        }
        
        function refreshLog() {
            window.location.reload();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            // Only refresh if no filters are applied
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('member_id') && !urlParams.has('activity_type') && 
                !urlParams.has('date_from') && !urlParams.has('date_to')) {
                refreshLog();
            }
        }, 30000);
    </script>
</body>
</html>
