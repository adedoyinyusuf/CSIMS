<?php
/**
 * Admin - Loans Management
 * 
 * Enhanced loans management page with CSIMS color scheme, 
 * Phase 1&2 integrations, and comprehensive loan tracking.
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/enhanced_loan_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';

// Ensure we use the unified Session instance and cookie
$session = Session::getInstance();

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize controllers and services
$loanController = class_exists('EnhancedLoanController') ? new EnhancedLoanController() : new LoanController();
$memberController = new MemberController();
$businessRulesService = new SimpleBusinessRulesService();

// Get filter parameters with enhanced options
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$loan_type = isset($_GET['loan_type']) ? trim($_GET['loan_type']) : '';
$amount_range = isset($_GET['amount_range']) ? $_GET['amount_range'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'application_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;

// Optional: show loans for a specific member on this page (mirrors savings pattern)
$member_id_param = isset($_GET['member_id']) ? (int)$_GET['member_id'] : null;
$member_context = null;
$member_loans = [];
if ($member_id_param) {
    // Resolve member name for context if available
    try {
        if (method_exists($memberController, 'getMemberById')) {
            $member = $memberController->getMemberById($member_id_param);
            if (is_array($member)) {
                $first = $member['first_name'] ?? '';
                $last = $member['last_name'] ?? '';
                $name = trim(($first . ' ' . $last));
                $member_context = $name !== '' ? $name : ($member['name'] ?? 'Member #' . $member_id_param);
            }
        }
    } catch (\Exception $e) { /* ignore */ }

    // Fetch loans for the specified member (limit for performance)
    try {
        if (method_exists($memberController, 'getLoansByMember')) {
            $ml = $memberController->getLoansByMember($member_id_param, 100);
            if (is_array($ml)) { $member_loans = $ml; }
        } elseif (method_exists($loanController, 'getLoansByMemberId')) {
            $ml = $loanController->getLoansByMemberId($member_id_param, 100);
            if (is_array($ml)) { $member_loans = $ml; }
        } elseif (method_exists($loanController, 'getMemberLoans')) {
            $ml = $loanController->getMemberLoans($member_id_param);
            if (is_array($ml)) { $member_loans = $ml; }
        }
    } catch (\Exception $e) { /* ignore */ }
}

// Get loans with enhanced filtering
$result = $loanController->getAllLoans($page, $per_page, $search, $sort_by, $sort_order, $status_filter, $loan_type, $amount_range);
$loans = $result['loans'] ?? [];
$pagination = $result['pagination'] ?? [];
$total_pages = $pagination['total_pages'] ?? 1;
$total_loans = $pagination['total_items'] ?? 0;

// Get comprehensive loan statistics
$loanStats = $loanController->getLoanStatistics();
$loanTypes = $loanController->getLoanTypes();
$loanStatuses = $loanController->getLoanStatuses();
// Normalize keys for robust label lookup while keeping original for filters
$loanStatusesLower = is_array($loanStatuses) ? array_change_key_case($loanStatuses, CASE_LOWER) : [];

// Members with loans overview (top N)
$membersWithLoans = [];
try {
    if (method_exists($loanController, 'getMembersWithLoans')) {
        $membersWithLoans = $loanController->getMembersWithLoans(10, $search);
    }
} catch (Exception $e) { /* ignore */ }

// Prefetch repayment sums for visible loans to avoid per-row queries
$hasRepaymentsTable = false;
$prefetchedPaid = [];
try {
    $tbl = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
    if ($tbl && $tbl->num_rows > 0) { $hasRepaymentsTable = true; }
    if ($hasRepaymentsTable && !empty($loans)) {
        // Determine loans primary key in current schema
        $loanPkCol = null;
        $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'loan_id'");
        if ($col && $col->num_rows > 0) { $loanPkCol = 'loan_id'; } else {
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'id'");
            if ($col && $col->num_rows > 0) { $loanPkCol = 'id'; }
        }
        if ($loanPkCol) {
            $loanIds = array_map(function($l){ return (int)($l['loan_id'] ?? $l['id'] ?? 0); }, $loans);
            $loanIds = array_filter($loanIds, function($id){ return $id > 0; });
            if (!empty($loanIds)) {
                $in = implode(',', $loanIds);
                // loan_repayments table uses loan_id as FK
                $rs = $conn->query("SELECT loan_id, SUM(amount) AS total_paid FROM loan_repayments WHERE loan_id IN ($in) GROUP BY loan_id");
                if ($rs) {
                    while ($row = $rs->fetch_assoc()) {
                        $prefetchedPaid[(int)$row['loan_id']] = (float)($row['total_paid'] ?? 0);
                    }
                }
            }
        }
    }
} catch (Exception $e) { /* ignore */ }

$repayments_count_this_month = 0;
try {
    $tbl = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
    if ($tbl && $tbl->num_rows > 0) {
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM loan_repayments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())");
        if ($res) { $repayments_count_this_month = (int)($res->fetch_assoc()['cnt'] ?? 0); }
    }
} catch (Exception $e) { /* ignore */ }

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get business rules alerts for loans
$loan_alerts = $businessRulesService->getLoanAlerts();

// Page title
$pageTitle = "Loans Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <!-- Tailwind CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
</head>

<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header with Loan Statistics -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
            <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">
                <i class="fas fa-hand-holding-usd mr-3" style="color: #07beb8;"></i>
                Loans Management
            </h1>
                    <p style="color: var(--text-muted);">Track, manage and approve all loan applications</p>
                    <div class="mt-2">
                        <span class="badge badge-info">Repayments This Month: <?php echo number_format($repayments_count_this_month ?? 0); ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="<?php echo BASE_URL; ?>/views/admin/add_loan.php" class="btn btn-standard btn-primary">
                        <i class="fas fa-plus mr-2"></i> New Loan Application
                    </a>
                    <button type="button" class="btn btn-standard btn-secondary" onclick="openImportModal()">
                        <i class="fas fa-file-import mr-2"></i> Import Loans
                    </button>
                    <button type="button" class="btn btn-standard btn-outline" onclick="exportLoans()">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-standard btn-outline" onclick="printLoans()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Loan Statistics Cards -->
            <div id="loanStatsGrid" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card card-admin stat-card-standard">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #3b28cc;">Total Loans</p>
                                <p id="totalLoansCount" class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format(0); ?></p>
                                <p class="text-xs" style="color: var(--success);">₦<span id="totalAmountText"><?php echo number_format(0, 2); ?></span></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #3b28cc 0%, #3b28cc 100%);">
                                <i class="fas fa-hand-holding-usd text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin stat-card-standard">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #07beb8;">Pending Applications</p>
                                <p id="pendingCount" class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format(0); ?></p>
                                <p class="text-xs" style="color: var(--warning);">Awaiting Review</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #07beb8 0%, #07beb8 100%);">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin stat-card-standard">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #214e34;">Approved Loans</p>
                                <p id="approvedCount" class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format(0); ?></p>
                                <p class="text-xs" style="color: var(--success);">₦<span id="approvedAmountText"><?php echo number_format(0, 2); ?></span></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #214e34 0%, #214e34 100%);">
                                <i class="fas fa-check-circle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin stat-card-standard">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #cb0b0a;">Default Risk</p>
                                <p id="overdueCount" class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format(0); ?></p>
                                <p class="text-xs" style="color: var(--danger);">Need Attention</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #cb0b0a 0%, #cb0b0a 100%);">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function(){
                const fmtNumber = (n) => new Intl.NumberFormat().format(Number(n || 0));
                const fmtCurrency = (n) => new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n || 0));

                async function refreshLoanStats() {
                    try {
                        const res = await fetch('<?php echo BASE_URL; ?>/views/admin/ajax/loan_stats.php', { credentials: 'same-origin', cache: 'no-cache' });
                        if (!res.ok) {
                            console.warn('[LoanStats] Refresh failed with status', res.status);
                            return;
                        }
                        const json = await res.json();
                        if (!json || !json.success) {
                            console.warn('[LoanStats] Invalid response payload', json);
                            return;
                        }
                        const d = json.data || {};
                        console.debug('[LoanStats] Refreshed stats', d);

                        const el = (id) => document.getElementById(id);
                        if (el('totalLoansCount')) el('totalLoansCount').textContent = fmtNumber(d.total_loans);
                        if (el('totalAmountText')) el('totalAmountText').textContent = fmtCurrency(d.total_amount);
                        if (el('pendingCount')) el('pendingCount').textContent = fmtNumber(d.pending_count);
                        if (el('approvedCount')) el('approvedCount').textContent = fmtNumber(d.approved_count);
                        if (el('approvedAmountText')) el('approvedAmountText').textContent = fmtCurrency(d.approved_amount);
                        if (el('overdueCount')) el('overdueCount').textContent = fmtNumber(d.overdue_count);
                    } catch (e) {
                        console.error('[LoanStats] Error refreshing stats', e);
                    }
                }

                // Trigger refresh on page load (always) and when URL has refresh flag
                document.addEventListener('DOMContentLoaded', function(){
                    refreshLoanStats();
                    const params = new URLSearchParams(window.location.search);
                    if (params.get('refresh') === 'stats') {
                        refreshLoanStats();
                    }

                    const btn = document.getElementById('refreshStatsBtn');
                    if (btn) {
                        btn.addEventListener('click', function(ev){
                            try { ev.preventDefault(); } catch(_) {}
                            refreshLoanStats();
                        });
                    }
                });

                // Expose for manual calls if needed
                window.refreshLoanStats = refreshLoanStats;
            })();
            </script>

            <?php
            // Fetch approved loans awaiting disbursement (top 50)
            try {
                $approvedListResult = $loanController->getAllLoans(1, 50, '', 'application_date', 'DESC', 'Approved', '', '');
                $approvedLoans = $approvedListResult['loans'] ?? [];
            } catch (Exception $e) {
                $approvedLoans = [];
            }
            ?>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Approved Loans Awaiting Disbursement</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="window.refreshLoanStats && window.refreshLoanStats()">Refresh Stats</button>
                </div>
                <div class="card-body">
                    <?php if (empty($approvedLoans)): ?>
                        <p class="text-sm" style="color: var(--text-muted);">No approved loans awaiting disbursement.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Applied</th>
                                        <th>Approved</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvedLoans as $al): 
                                        $loanId = (int)($al['loan_id'] ?? $al['id'] ?? 0);
                                        $memberName = trim(($al['first_name'] ?? '') . ' ' . ($al['last_name'] ?? ''));
                                        $amount = (float)($al['amount'] ?? $al['principal_amount'] ?? 0);
                                        $applied = $al['application_date'] ?? $al['created_at'] ?? null;
                                        $approvedDate = $al['approval_date'] ?? $al['approved_at'] ?? null;
                                    ?>
                                    <tr>
                                        <td><?php echo $loanId; ?></td>
                                        <td><?php echo $memberName !== '' ? htmlspecialchars($memberName) : 'Member #' . ($al['member_id'] ?? ''); ?></td>
                                        <td>₦<?php echo number_format($amount, 2); ?></td>
                                        <td><?php echo $applied ? date('M d, Y', strtotime($applied)) : '-'; ?></td>
                                        <td><?php echo $approvedDate ? date('M d, Y', strtotime($approvedDate)) : '-'; ?></td>
                                        <td>
                                            <?php if ($loanId): ?>
                                                <a href="<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=<?php echo $loanId; ?>&action=disburse" class="btn btn-sm btn-primary">
                                                    Disburse
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Enhanced Flash Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 icon-success"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 icon-error"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Business Rules Alerts for Loans -->
            <?php if (!empty($loan_alerts)): ?>
                <div class="alert alert-warning flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 icon-warning"></i>
                        <div>
                            <strong>Loan Business Rules Alert:</strong>
                            <span><?php echo count($loan_alerts); ?> loan(s) require attention</span>
                            <a href="<?php echo BASE_URL; ?>/views/admin/loan_approvals.php" class="ml-2 text-sm underline">Review Now</a>
                        </div>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Flash Messages (semantic) -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <?php
                    $flashType = $_SESSION['flash_type'] ?? 'info';
                    $alertClass = match ($flashType) {
                        'success' => 'alert alert-success',
                        'danger' => 'alert alert-error',
                        'warning' => 'alert alert-warning',
                        default => 'alert alert-info',
                    };
                    $icon = match ($flashType) {
                        'success' => 'check-circle',
                        'danger' => 'exclamation-triangle',
                        'warning' => 'exclamation-triangle',
                        default => 'info-circle',
                    };
                    $iconColor = match ($flashType) {
                        'success' => 'var(--success)',
                        'danger' => 'var(--error)',
                        'warning' => 'var(--warning)',
                        default => 'var(--info)',
                    };
                ?>
                <div class="<?php echo $alertClass; ?> flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $icon; ?> mr-3" style="color: <?php echo $iconColor; ?>;"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
            
            <!-- Filters and Search -->
            <div class="bg-white rounded-2xl shadow-lg mb-8 overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                        <i class="fas fa-filter text-gray-600 mr-2"></i>Filter & Search
                    </h3>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                        <!-- Search -->
                        <div class="lg:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" id="search" name="search" 
                                   placeholder="Member name, loan ID" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="form-control">
                        </div>
                        
                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">All Status</option>
                                <?php foreach ($loanStatuses as $status_key => $status_label): ?>
                                    <option value="<?php echo htmlspecialchars($status_key); ?>" 
                                        <?php echo ($status_filter === $status_key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Loan Type -->
                        <div>
                            <label for="loan_type" class="block text-sm font-medium text-gray-700 mb-2">Loan Type</label>
                            <select id="loan_type" name="loan_type" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach ($loanTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['id']); ?>" 
                                        <?php echo ($loan_type == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Amount Range -->
                        <div>
                            <label for="amount_range" class="block text-sm font-medium text-gray-700 mb-2">Amount Range</label>
                            <select id="amount_range" name="amount_range" class="form-control">
                                <option value="">All Amounts</option>
                                <option value="0-100000" <?php echo ($amount_range == '0-100000') ? 'selected' : ''; ?>>₦0 - ₦100k</option>
                                <option value="100001-500000" <?php echo ($amount_range == '100001-500000') ? 'selected' : ''; ?>>₦100k - ₦500k</option>
                                <option value="500001-1000000" <?php echo ($amount_range == '500001-1000000') ? 'selected' : ''; ?>>₦500k - ₦1M</option>
                                <option value="1000001-9999999" <?php echo ($amount_range == '1000001-9999999') ? 'selected' : ''; ?>>Above ₦1M</option>
                            </select>
                        </div>
                        
                        <!-- Sort Options -->
                        <div>
                            <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select id="sort_by" name="sort_by" class="form-control">
                                <option value="application_date" <?php echo ($sort_by === 'application_date') ? 'selected' : ''; ?>>Application Date</option>
                                <option value="amount" <?php echo ($sort_by === 'amount') ? 'selected' : ''; ?>>Amount</option>
                                <option value="status" <?php echo ($sort_by === 'status') ? 'selected' : ''; ?>>Status</option>
                                <option value="term" <?php echo ($sort_by === 'term') ? 'selected' : ''; ?>>Term</option>
                            </select>
                        </div>
                        
                        <!-- Sort Order -->
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                            <select id="sort_order" name="sort_order" class="form-control">
                                <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="lg:col-span-6 flex gap-3 pt-2">
                            <button type="submit" class="btn btn-standard btn-primary">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                            <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="btn btn-standard btn-outline">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Quick Jump to Loan Applications -->
            <div class="mb-4 px-6">
                <a href="#loanApplicationsSection" class="text-primary hover:text-secondary text-sm"
                   onclick="const el=document.getElementById('loanApplicationsSection'); if(el){ el.scrollIntoView({behavior:'smooth', block:'start'}); } return false;">
                    <i class="fas fa-arrow-down mr-1"></i>Jump to Loan Applications
                </a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="hidden">


                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">
                                Paid Loans
                            </p>
                            <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                <?php 
                                    $paidCount = is_array($loanStats['paid_loans'] ?? null) 
                                        ? ($loanStats['paid_loans']['count'] ?? 0) 
                                        : (int)($loanStats['paid_loans'] ?? 0);
                                    echo number_format($paidCount); 
                                ?>
                            </p>
                            <p class="text-sm" style="color: var(--text-muted);">
                                =N=<?php 
                                    $paidAmount = is_array($loanStats['paid_loans'] ?? null) 
                                        ? ($loanStats['paid_loans']['amount'] ?? 0) 
                                        : 0;
                                    echo number_format($paidAmount, 2); 
                                ?>
                            </p>
                        </div>
                        <div class="badge badge-success">
                            <i class="fas fa-check-circle text-2xl icon-success"></i>
                         </div>
                    </div>
                </div>

            </div>


            <!-- Loans Applications List -->

            <?php if ($member_id_param): ?>
            <!-- Member Loans (mirrors savings table style) -->
            <section id="memberLoansSection" class="mt-4 w-full grid grid-cols-1 gap-6">
                <div class="card card-admin animate-fade-in">
                    <div class="card-header flex items-center justify-between">
                        <h3 class="text-lg font-semibold">
                            Loans for <?php echo htmlspecialchars($member_context ?? ('Member #' . $member_id_param)); ?>
                        </h3>
                        <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                            <?php echo count($member_loans); ?> Total Loans
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($member_loans)): ?>
                            <div class="text-center py-8" style="color: var(--text-muted);">
                                <i class="fas fa-hand-holding-usd text-3xl mb-2"></i>
                                <h5>No loans found for this member</h5>
                                <p>Create a new loan application to get started</p>
                            </div>
                        <?php else: ?>
                            <?php
                                // Detect schema variations for paid/remaining balances
                                $has_repayments = false;
                                $has_amount_paid = false;
                                $has_total_repaid = false;
                                $has_remaining_balance = false;
                                try {
                                    $chk = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
                                    if ($chk && $chk->num_rows > 0) { $has_repayments = true; }
                                    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
                                    if ($chk && $chk->num_rows > 0) { $has_amount_paid = true; }
                                    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
                                    if ($chk && $chk->num_rows > 0) { $has_total_repaid = true; }
                                    $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'");
                                    if ($chk && $chk->num_rows > 0) { $has_remaining_balance = true; }
                                } catch (Exception $e) { /* ignore detection errors */ }

                                // Prefetch repayment sums by loan to avoid per-row queries
                                $repayment_sums_by_loan = [];
                                if ($has_repayments && !empty($member_loans)) {
                                    $loanIds = array_map(function($l){ return (int)($l['loan_id'] ?? ($l['id'] ?? 0)); }, $member_loans);
                                    $loanIds = array_filter($loanIds, function($id){ return $id > 0; });
                                    if (!empty($loanIds)) {
                                        $in = implode(',', $loanIds);
                                        try {
                                            // Prefer 'amount' column; fallback handled per-row if different schemas exist
                                            $rs = $conn->query("SELECT loan_id, SUM(amount) AS total FROM loan_repayments WHERE loan_id IN ($in) GROUP BY loan_id");
                                            if ($rs) {
                                                while ($row = $rs->fetch_assoc()) {
                                                    $repayment_sums_by_loan[(int)$row['loan_id']] = (float)($row['total'] ?? 0);
                                                }
                                            }
                                        } catch (Exception $e) { /* ignore prefetch errors */ }
                                    }
                                }
                            ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-standard" id="memberLoansTable">
                                    <thead>
                                        <tr>
                                            <th>Loan ID</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($member_loans as $loan): ?>
                                            <?php
                                                $loanId = (int)($loan['loan_id'] ?? ($loan['id'] ?? 0));
                                                $amount = (float)($loan['amount'] ?? ($loan['principal_amount'] ?? ($loan['total_amount'] ?? 0)));
                                                // Compute paid preferring prefetch sums, then schema columns
                                                $paid = 0.0;
                                                if ($loanId > 0 && isset($repayment_sums_by_loan[$loanId])) {
                                                    $paid = (float)$repayment_sums_by_loan[$loanId];
                                                } elseif ($has_amount_paid && isset($loan['amount_paid'])) {
                                                    $paid = (float)$loan['amount_paid'];
                                                } elseif ($has_total_repaid && isset($loan['total_repaid'])) {
                                                    $paid = (float)$loan['total_repaid'];
                                                }
                                                // Prefer remaining_balance column when present
                                                $remaining = $has_remaining_balance && isset($loan['remaining_balance'])
                                                    ? (float)$loan['remaining_balance']
                                                    : max($amount - $paid, 0);
                                                $typeName = $loan['loan_type'] ?? ($loan['loan_type_name'] ?? 'N/A');
                                                $statusLabel = ucfirst(strtolower($loan['status'] ?? 'N/A'));
                                                $appDateRaw = $loan['application_date'] ?? ($loan['created_at'] ?? null);
                                                $appDate = ($appDateRaw && strtotime($appDateRaw) !== false)
                                                    ? date('M j, Y', strtotime($appDateRaw))
                                                    : 'N/A';
                                            ?>
                                            <tr>
                                                <td style="color: var(--text-primary);">#<?php echo $loanId; ?></td>
                                                <td>
                                                    <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                                                        <?php echo htmlspecialchars($typeName); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="balance-amount">₦<?php echo number_format($amount, 2); ?></span>
                                                </td>
                                                <td style="color: var(--text-primary);">₦<?php echo number_format($paid, 2); ?></td>
                                                <td>
                                                    <span class="badge" style="background: #07beb8; color: white;">
                                                        ₦<?php echo number_format($remaining, 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: <?php echo (strtolower($statusLabel) === 'approved' || strtolower($statusLabel) === 'disbursed' || strtolower($statusLabel) === 'active') ? 'var(--success)' : ((strtolower($statusLabel) === 'pending') ? 'var(--warning)' : 'var(--text-muted)'); ?>; color: white;">
                                                        <?php echo htmlspecialchars($statusLabel); ?>
                                                    </span>
                                                </td>
                                                <td style="color: var(--text-primary);">
                                                    <?php echo htmlspecialchars($appDate); ?>
                                                </td>
                                                <td>
                                                    <div class="flex space-x-1">
                                                        <a class="btn btn-standard btn-sm btn-outline" href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loanId; ?>" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a class="btn btn-standard btn-sm btn-outline" href="<?php echo BASE_URL; ?>/views/admin/add_repayment.php?loan_id=<?php echo $loanId; ?>" title="Add Repayment">
                                                            <i class="fas fa-money-check"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- NEW STANDALONE LOAN APPLICATIONS SECTION -->
            <section id="loanApplicationsSection" class="mt-6">
                <div class="card card-admin animate-fade-in">
                    <div class="card-header flex items-center justify-between">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-file-contract mr-2"></i>Loan Applications
                        </h3>
                        <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                            <?php echo isset($loans) ? count($loans) : 0; ?> Total Applications
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-standard" id="loansTable">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-hashtag mr-1"></i>ID
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-user mr-1"></i>Member
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">
                                            <i class="fas fa-tags mr-1"></i>Type
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                            <i class="fas fa-bullseye mr-1"></i>Purpose
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-dollar-sign mr-1"></i>Amount
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-money-check-alt mr-1"></i>Paid
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-balance-scale mr-1"></i>Remaining
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">
                                            <i class="fas fa-calendar mr-1"></i>Term
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                            <i class="fas fa-percentage mr-1"></i>Interest
                                        </th>
                                        <th class="px-3 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                            <i class="fas fa-clock mr-1"></i>Application Date
                                        </th>
                                        <th class="px-3 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-flag mr-1"></i>Status
                                        </th>
                                        <th class="px-3 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                            <i class="fas fa-cogs mr-1"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($loans)): ?>
                                        <tr>
                                            <td colspan="12" class="px-3 py-12 text-center">
                                                <div class="flex flex-col items-center">
                                                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                                    <p class="text-gray-500 text-lg">No loan applications found.</p>
                                                    <p class="text-gray-400 text-sm mt-2">Applications will appear here once submitted.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($loans as $loan): ?>
                                            <?php
                                            $loanId = (int)($loan['loan_id'] ?? $loan['id'] ?? 0);
                                            $paid = 0.0;
                                            if (isset($prefetchedPaid[$loanId])) {
                                                $paid = (float)$prefetchedPaid[$loanId];
                                            } elseif (isset($loan['amount_paid'])) {
                                                $paid = (float)$loan['amount_paid'];
                                            } elseif (isset($loan['total_repaid'])) {
                                                $paid = (float)$loan['total_repaid'];
                                            }
                                            $amountVal = (float)($loan['amount'] ?? $loan['principal_amount'] ?? $loan['total_amount'] ?? 0);
                                            $remaining = max(0, $amountVal - $paid);
                                            $termVal = (int)($loan['term'] ?? $loan['term_months'] ?? 0);
                                            $interestVal = $loan['interest_rate'] ?? $loan['annual_rate'] ?? null;
                                            $appDate = $loan['application_date'] ?? $loan['created_at'] ?? null;
                                            ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-3 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                                    <?php echo $loanId ?: '—'; ?>
                                                </td>
                                                <td class="px-3 py-4">
                                                    <div class="flex items-center min-w-0">
                                                        <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center mr-2 flex-shrink-0">
                                                            <i class="fas fa-user text-white text-xs"></i>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $loan['member_id']; ?>"
                                                               class="text-sm font-medium text-primary hover:text-secondary transition-colors block truncate">
                                                                <?php echo htmlspecialchars(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? '')); ?>
                                                            </a>
                                                            <div class="md:hidden text-xs text-gray-500 mt-1">
                                                                <span class="badge badge-info">
                                                                    <?php echo $termVal; ?>mo
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-700 hidden md:table-cell">
                                                    <?php echo isset($loan['loan_type_name']) ? htmlspecialchars($loan['loan_type_name']) : '—'; ?>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-700 hidden lg:table-cell">
                                                    <?php echo isset($loan['purpose']) ? htmlspecialchars($loan['purpose']) : '—'; ?>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                                    <div>₦<?php echo number_format($amountVal, 2); ?></div>
                                                    <div class="lg:hidden text-xs text-gray-500 mt-1">
                                                        <?php if ($interestVal !== null) { echo $interestVal; } else { echo '—'; } ?>% • <?php echo $appDate ? date('M d, Y', strtotime($appDate)) : '—'; ?>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm font-bold text-blue-600">
                                                    <div>₦<?php echo number_format($paid, 2); ?></div>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm font-bold text-amber-600">
                                                    <div>₦<?php echo number_format($remaining, 2); ?></div>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap hidden md:table-cell">
                                                    <span class="badge badge-info">
                                                        <?php echo $termVal; ?> months
                                                    </span>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900 hidden lg:table-cell">
                                                    <?php echo $interestVal !== null ? $interestVal . '%' : '—'; ?>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                                                    <?php echo $appDate ? date('M d, Y', strtotime($appDate)) : '—'; ?>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-center">
                                                    <?php 
                                                        $statusRaw = $loan['status'] ?? 'unknown';
                                                        $status = strtolower(trim($statusRaw));
                                                        $statusClassMap = [
                                                            'pending' => 'badge badge-warning',
                                                            'pending_approval' => 'badge badge-warning',
                                                            'approved' => 'badge badge-primary',
                                                            'rejected' => 'badge badge-danger',
                                                            'disbursed' => 'badge badge-info',
                                                            'active' => 'badge badge-info',
                                                            'paid' => 'badge badge-success',
                                                        ];
                                                        $statusIconMap = [
                                                            'pending' => 'clock',
                                                            'pending_approval' => 'clock',
                                                            'approved' => 'check',
                                                            'rejected' => 'times',
                                                            'disbursed' => 'arrow-right',
                                                            'active' => 'play',
                                                            'paid' => 'check-circle',
                                                        ];
                                                        $badgeClass = $statusClassMap[$status] ?? 'badge badge-secondary';
                                                        $icon = $statusIconMap[$status] ?? 'question';
                                                        // Get status label - try lowercase map first, then original case
                                                        $statusLabel = $loanStatusesLower[$status] ?? ($loanStatuses[$statusRaw] ?? ucfirst(strtolower($statusRaw)));
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $badgeClass; ?>">
                                                        <i class="fas fa-<?php echo $icon; ?> mr-1"></i>
                                                        <?php echo htmlspecialchars($statusLabel); ?>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div class="flex items-center justify-end space-x-1">
                                                        <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loanId; ?>"
                                                           class="text-primary hover:text-secondary transition-colors p-1" title="View Details">
                                                            <i class="fas fa-eye text-sm"></i>
                                                        </a>
                                                        <?php if (in_array(strtolower($loan['status']), ['pending','approved'])): ?>
                                                            <a href="<?php echo BASE_URL; ?>/views/admin/edit_loan.php?id=<?php echo $loanId; ?>"
                                                               class="text-warning hover:text-secondary transition-colors p-1" title="Edit">
                                                                <i class="fas fa-edit text-sm"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (strtolower($loan['status']) === 'pending'): ?>
                                                            <button onclick="openApproveModal(<?php echo $loanId; ?>, '<?php echo htmlspecialchars(($loan['first_name'] ?? '') . ' ' . ($loan['last_name'] ?? ''), ENT_QUOTES); ?>', <?php echo (float)$amountVal; ?>, <?php echo (int)$termVal; ?>)"
                                                                    class="text-success hover:text-secondary transition-colors p-1" title="Approve">
                                                                <i class="fas fa-check text-sm"></i>
                                                            </button>
                                                            <button onclick="openRejectModal(<?php echo $loanId; ?>)"
                                                                    class="text-danger hover:text-secondary transition-colors p-1" title="Reject">
                                                                <i class="fas fa-times text-sm"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (in_array(strtolower($loan['status']), ['pending','approved','active'])): ?>
                                                            <button onclick="confirmDelete(<?php echo $loanId; ?>)"
                                                                    class="text-danger hover:text-secondary transition-colors p-1" title="Delete">
                                                                <i class="fas fa-trash text-sm"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

                </div>
                <!-- End of Main Content -->
            </main>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Approve Loan Modal -->
    <div id="approveModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <form method="POST" action="<?php echo BASE_URL; ?>/views/admin/process_loan.php" id="approveForm">
                    <!-- Modal Header -->
                    <div class="modal-header gradient-success">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-check-circle mr-3"></i>Approve Loan Application
                            </h3>
                            <button type="button" class="text-white hover:text-light transition-colors" onclick="closeApproveModal()">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="p-6">
                        <div class="alert alert-info mb-6 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="font-semibold mb-2">Loan Details:</h4>
                                    <div id="approveLoanDetails">
                                        <!-- Loan details will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-primary mb-4">Are you sure you want to approve this loan application?</p>
                        
                        <div class="mb-6">
                            <label for="approve_notes" class="form-label">
                                Approval Notes (Optional)
                            </label>
                            <textarea 
                                class="form-control" 
                                id="approve_notes" 
                                name="notes" 
                                rows="3" 
                                placeholder="Add any notes about the approval..."
                            ></textarea>
                        </div>
                        
                        <input type="hidden" name="loan_id" id="approveLoanId" value="">
                        <input type="hidden" name="action" value="approve">
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer">
                        <button type="button" 
                                class="btn btn-outline"
                                onclick="closeApproveModal()">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn btn-primary flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>Approve Loan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Loan Modal -->
    <div id="rejectModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <form method="POST" action="<?php echo BASE_URL; ?>/views/admin/process_loan.php" id="rejectForm">
                    <!-- Modal Header -->
                    <div class="modal-header gradient-danger">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-times-circle mr-3"></i>Reject Loan Application
                            </h3>
                            <button type="button" class="text-white hover:text-light transition-colors" onclick="closeRejectModal()">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="p-6">
                        <div class="alert alert-warning mb-6 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="font-semibold mb-1">Warning:</h4>
                                    <p>This action will reject the loan application and cannot be easily undone.</p>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-primary mb-4">Please provide a reason for rejecting this loan application:</p>
                        
                        <div class="mb-6">
                            <label for="reject_notes" class="form-label required">Rejection Reason</label>
                            <textarea 
                                class="form-control" 
                                id="reject_notes" 
                                name="notes" 
                                rows="4" 
                                placeholder="Please provide a clear reason for rejection..." 
                                required
                            ></textarea>
                            <p class="mt-2 text-sm text-error hidden" id="reject_notes_error">
                                Please provide a reason for rejection.
                            </p>
                        </div>
                        
                        <input type="hidden" name="loan_id" id="rejectLoanId" value="">
                        <input type="hidden" name="action" value="reject">
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer">
                        <button type="button" 
                                class="btn btn-outline"
                                onclick="closeRejectModal()">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn btn-danger flex items-center">
                            <i class="fas fa-times-circle mr-2"></i>Reject Loan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-primary flex items-center">
                        <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                        Confirm Deletion
                    </h3>
                    <button type="button" class="text-muted hover:text-secondary transition-colors" onclick="closeDeleteModal()">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-secondary">Are you sure you want to delete this loan application? This action cannot be undone.</p>
                    <div id="deleteError" class="alert alert-error hidden">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span id="deleteErrorMessage"></span>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="btn btn-standard btn-outline flex items-center" onclick="closeDeleteModal()">
                        <i class="fas fa-times mr-1"></i>
                        Cancel
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-standard btn-danger flex items-center" onclick="deleteLoanAjax()">
                        <i class="fas fa-trash mr-1"></i>
                        <span id="deleteButtonText">Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-primary flex items-center">
                        <i class="fas fa-upload text-primary mr-2"></i>
                        Import Loans
                    </h3>
                    <button type="button" class="text-muted hover:text-secondary transition-colors" onclick="closeImportModal()">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="csvFile" class="form-label">
                            Select CSV File
                        </label>
                        <input type="file" id="csvFile" name="csvFile" accept=".csv" required class="form-control">
                        <p class="text-xs text-muted mt-1">
                            CSV format: member_id, amount, purpose, term_months, interest_rate, application_date, status
                        </p>
                    </div>
                    <div id="importProgress" class="mb-4 hidden">
                        <div class="progress">
                            <div class="progress-bar" style="width: 0%"></div>
                        </div>
                        <p class="text-sm text-info mt-1">Processing...</p>
                    </div>
                    <div id="importResult" class="mb-4 hidden">
                        <!-- Results will be displayed here -->
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" class="btn btn-outline" onclick="closeImportModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload mr-1"></i>
                            Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        .avatar-sm {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        .btn-group .btn {
            margin: 0 1px;
        }
        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background: var(--gradient-primary);
            border-color: var(--primary-500);
        }
    </style>
    
    <script>
        let loanIdToDelete = null;

        // Approve Modal Functions
        function openApproveModal(loanId, memberName, amount, term) {
            document.getElementById('approveLoanId').value = loanId;
            document.getElementById('approveForm').action = '<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=' + loanId;
            document.getElementById('approveLoanDetails').innerHTML = `
                <p><strong>Member:</strong> ${memberName}</p>
                <p><strong>Amount:</strong> ₦${new Intl.NumberFormat().format(amount)}</p>
                <p><strong>Term:</strong> ${term} months</p>
            `;
            document.getElementById('approveModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            // Focus on notes field after a short delay
            setTimeout(() => {
                document.getElementById('approve_notes').focus();
            }, 100);
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('approve_notes').value = '';
        }

        // Reject Modal Functions
        function openRejectModal(loanId) {
            document.getElementById('rejectLoanId').value = loanId;
            document.getElementById('rejectForm').action = '<?php echo BASE_URL; ?>/views/admin/process_loan.php?id=' + loanId;
            document.getElementById('rejectModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            // Focus on notes field after a short delay
            setTimeout(() => {
                document.getElementById('reject_notes').focus();
            }, 100);
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('reject_notes').value = '';
            document.getElementById('reject_notes_error').classList.add('hidden');
        }

        // Delete Modal Functions
        function confirmDelete(id) {
            loanIdToDelete = id;
            document.getElementById('deleteError').classList.add('hidden');
            document.getElementById('confirmDeleteBtn').disabled = false;
            document.getElementById('deleteButtonText').textContent = 'Delete';
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            loanIdToDelete = null;
            document.getElementById('deleteError').classList.add('hidden');
        }
        
        // AJAX Delete Function
        function deleteLoanAjax() {
            if (!loanIdToDelete) return;
            
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            const deleteText = document.getElementById('deleteButtonText');
            const errorDiv = document.getElementById('deleteError');
            const errorMsg = document.getElementById('deleteErrorMessage');
            
            // Disable button and show loading
            deleteBtn.disabled = true;
            deleteText.textContent = 'Deleting...';
            errorDiv.classList.add('hidden');
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('loan_id', loanIdToDelete);
            
            // Send AJAX request
            fetch('<?php echo BASE_URL; ?>/controllers/loan_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and reload page
                    closeDeleteModal();
                    location.reload();
                } else {
                    // Show error message
                    errorMsg.textContent = data.message || 'Failed to delete loan application';
                    errorDiv.classList.remove('hidden');
                    deleteBtn.disabled = false;
                    deleteText.textContent = 'Delete';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMsg.textContent = 'An error occurred while deleting the loan application';
                errorDiv.classList.remove('hidden');
                deleteBtn.disabled = false;
                deleteText.textContent = 'Delete';
            });
        }

        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('importProgress').classList.add('hidden');
            document.getElementById('importResult').classList.add('hidden');
        }

        function exportLoans() {
            // Get current filters
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search') || document.querySelector('input[name="search"]')?.value || '';
            const status = urlParams.get('status') || document.querySelector('select[name="status"]')?.value || '';
            const sortBy = urlParams.get('sort_by') || document.querySelector('select[name="sort_by"]')?.value || 'application_date';
            const sortOrder = urlParams.get('sort_order') || document.querySelector('select[name="sort_order"]')?.value || 'DESC';
            
            // Build export URL with filters
            let exportUrl = '<?php echo BASE_URL; ?>/controllers/loan_export_controller.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (status) params.push('status=' + encodeURIComponent(status));
            if (sortBy) params.push('sort_by=' + encodeURIComponent(sortBy));
            if (sortOrder) params.push('sort_order=' + encodeURIComponent(sortOrder));
            
            exportUrl += params.join('&');
            
            // Open export URL
            window.open(exportUrl, '_blank');
        }

        function printLoans() {
            // Get current filters
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search') || document.querySelector('input[name="search"]')?.value || '';
            const status = urlParams.get('status') || document.querySelector('select[name="status"]')?.value || '';
            const sortBy = urlParams.get('sort_by') || document.querySelector('select[name="sort_by"]')?.value || 'application_date';
            const sortOrder = urlParams.get('sort_order') || document.querySelector('select[name="sort_order"]')?.value || 'DESC';
            
            // Build print URL with filters
            let printUrl = '<?php echo BASE_URL; ?>/views/admin/print_loans.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (status) params.push('status=' + encodeURIComponent(status));
            if (sortBy) params.push('sort_by=' + encodeURIComponent(sortBy));
            if (sortOrder) params.push('sort_order=' + encodeURIComponent(sortOrder));
            
            printUrl += params.join('&');
            
            // Open print URL in new window
            window.open(printUrl, '_blank');
        }

        // Set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            document.getElementById('approveModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeApproveModal();
                }
            });
            
            document.getElementById('rejectModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeRejectModal();
                }
            });
            
            document.getElementById('deleteModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
            
            document.getElementById('importModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImportModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (!document.getElementById('approveModal').classList.contains('hidden')) {
                        closeApproveModal();
                    }
                    if (!document.getElementById('rejectModal').classList.contains('hidden')) {
                        closeRejectModal();
                    }
                    if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                        closeDeleteModal();
                    }
                    if (!document.getElementById('importModal').classList.contains('hidden')) {
                        closeImportModal();
                    }
                }
            });
            
            // Form validation for reject modal
            const rejectForm = document.getElementById('rejectForm');
            const rejectNotesField = document.getElementById('reject_notes');
            const rejectNotesError = document.getElementById('reject_notes_error');
            
            if (rejectForm) {
                rejectForm.addEventListener('submit', function(event) {
                    if (!rejectNotesField.value.trim()) {
                        event.preventDefault();
                        event.stopPropagation();
                        rejectNotesField.classList.add('is-invalid');
                        rejectNotesError.classList.remove('hidden');
                        return false;
                    }
                    rejectNotesField.classList.remove('is-invalid');
                    rejectNotesError.classList.add('hidden');
                });
                
                // Real-time validation
                rejectNotesField.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.classList.remove('is-invalid');
                        rejectNotesError.classList.add('hidden');
                    } else {
                        this.classList.add('is-invalid');
                        rejectNotesError.classList.remove('hidden');
                    }
                });
            }
            
            // Confirmation for approve form
            const approveForm = document.getElementById('approveForm');
            if (approveForm) {
                approveForm.addEventListener('submit', function(event) {
                    if (!confirm('Are you sure you want to approve this loan application?')) {
                        event.preventDefault();
                        return false;
                    }
                });
            }

            // Handle import form submission
            document.getElementById('importForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                const fileInput = document.getElementById('csvFile');
                
                if (!fileInput.files[0]) {
                    alert('Please select a CSV file.');
                    return;
                }
                
                formData.append('csvFile', fileInput.files[0]);
                
                // Show progress
                document.getElementById('importProgress').classList.remove('hidden');
                document.getElementById('importResult').classList.add('hidden');
                
                // Send AJAX request
                fetch('loan_import_controller.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('importProgress').classList.add('hidden');
                    document.getElementById('importResult').classList.remove('hidden');
                    
                    if (data.success) {
                        document.getElementById('importResult').innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle mr-2"></i>
                                Successfully imported ${data.imported_count} loans.
                            </div>
                        `;
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        let errorHtml = `
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Import failed: ${data.message}
                        `;
                        
                        if (data.errors && data.errors.length > 0) {
                            errorHtml += '<ul class="mt-2 ml-4">';
                            data.errors.forEach(error => {
                                errorHtml += `<li>• ${error}</li>`;
                            });
                            errorHtml += '</ul>';
                        }
                        
                        errorHtml += '</div>';
                        document.getElementById('importResult').innerHTML = errorHtml;
                    }
                })
                .catch(error => {
                    document.getElementById('importProgress').classList.add('hidden');
                    document.getElementById('importResult').classList.remove('hidden');
                    document.getElementById('importResult').innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            An error occurred during import.
                        </div>
                    `;
                });
            });
        });
    </script>
    <script>
      // Prevent automatic jump on load; only scroll when explicitly requested
      window.addEventListener('DOMContentLoaded', function() {
        var sec = document.getElementById('loanApplicationsSection');
        var params = new URLSearchParams(window.location.search);
        var explicitJump = (window.location.hash === '#loanApplicationsSection') || (params.get('jump') === 'applications');
        if (sec && explicitJump) {
          sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    </script>
</body>
</html>
