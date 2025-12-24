<?php
require_once '../../config/config.php';
$session = Session::getInstance();

// Simple and robust authentication check aligned with Session
if (!$session->isLoggedIn() || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Please login to access the dashboard';
    header("Location: ../../index.php");
    exit();
}

// Clear redirect check flag since we're successfully accessing dashboard
unset($_SESSION['redirect_check']);

// Get current user info from session (reliable method)
$current_user = [
    'admin_id' => $_SESSION['admin_id'],
    'username' => $_SESSION['username'] ?? 'admin',
    'first_name' => $_SESSION['first_name'] ?? 'Admin',
    'last_name' => $_SESSION['last_name'] ?? 'User',
    'role' => $_SESSION['role'] ?? 'Administrator'
];

// Initialize database connection
try {
    require_once '../../config/database.php';
    require_once '../../includes/db.php';
    require_once '../../includes/utilities.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log('Database connection failed in dashboard: ' . $e->getMessage());
    $db_error = 'Database connection failed: ' . $e->getMessage();
    $conn = null;
}

// Initialize statistics with safe defaults
$stats = [
    'total_members' => 0,
    'active_members' => 0,
    'total_loans' => 0,
    'new_members_this_month' => 0,
    'active_loans' => 0,
    'loan_amount' => 0,
    'total_deposits' => 0,
    'total_savings_balance' => 0,
    'deposits_this_month' => 0,
    'loan_outstanding' => 0,
    'repayments_this_month' => 0
];

$membership_stats = [];
$expiring_memberships = [];
$pending_notifications = 0;
$business_rule_alerts = [];

// Try to get statistics from database if connection exists
if ($conn) {
    try {
        // Check what tables exist first
        $existing_tables = [];
        $tables_to_check = ['members', 'loans', 'savings_transactions', 'savings_accounts', 'loan_repayments'];
        
        foreach ($tables_to_check as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $existing_tables[] = $table;
            }
        }
        
        // Get member statistics if table exists
        if (in_array('members', $existing_tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM members");
            if ($result) {
                $stats['total_members'] = $result->fetch_assoc()['count'] ?? 0;
            }
            
            $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'Active'");
            if ($result) {
                $stats['active_members'] = $result->fetch_assoc()['count'] ?? 0;
            }
            
            $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
            if ($result) {
                $stats['new_members_this_month'] = $result->fetch_assoc()['count'] ?? 0;
            }
        }
        
        // Get loan statistics if table exists
        if (in_array('loans', $existing_tables)) {
            $result = $conn->query("SELECT COUNT(*) as count FROM loans");
            if ($result) {
                $stats['total_loans'] = $result->fetch_assoc()['count'] ?? 0;
            }
            
            $result = $conn->query("SELECT COUNT(*) as count FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
            if ($result) {
                $stats['active_loans'] = $result->fetch_assoc()['count'] ?? 0;
            }
            
            $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
            if ($result) {
                $loan_result = $result->fetch_assoc();
                $stats['loan_amount'] = $loan_result['total'] ?? 0;
            }
            
            // Compute outstanding loan balance robustly across schema variations
            $has_amount_paid = false;
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
            if ($col && $col->num_rows > 0) { $has_amount_paid = true; }
            
            $has_remaining_balance = false;
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'");
            if ($col && $col->num_rows > 0) { $has_remaining_balance = true; }
            
            $has_total_repaid = false;
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
            if ($col && $col->num_rows > 0) { $has_total_repaid = true; }
            
            if ($has_amount_paid) {
                $result = $conn->query("SELECT SUM(amount - amount_paid) as total FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
            } elseif ($has_remaining_balance) {
                $result = $conn->query("SELECT SUM(remaining_balance) as total FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
            } elseif ($has_total_repaid) {
                $result = $conn->query("SELECT SUM(amount - total_repaid) as total FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
            } else {
                $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
            }
            if ($result) {
                $stats['loan_outstanding'] = (float)($result->fetch_assoc()['total'] ?? 0);
            }
            // Loans disbursed this month (sum and count)
            $has_disbursement_date = false; $has_approved_date = false; $has_application_date = false;
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'disbursement_date'");
            if ($col && $col->num_rows > 0) { $has_disbursement_date = true; }
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'approved_date'");
            if ($col && $col->num_rows > 0) { $has_approved_date = true; }
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'application_date'");
            if ($col && $col->num_rows > 0) { $has_application_date = true; }
            $dateCol = $has_disbursement_date ? 'disbursement_date' : ($has_approved_date ? 'approved_date' : ($has_application_date ? 'application_date' : null));
            if ($dateCol) {
                $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE LOWER(status) = 'disbursed' AND YEAR($dateCol) = YEAR(CURDATE()) AND MONTH($dateCol) = MONTH(CURDATE())");
                if ($result) { $stats['loans_disbursed_this_month'] = (float)($result->fetch_assoc()['total'] ?? 0); }
                $result = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE LOWER(status) = 'disbursed' AND YEAR($dateCol) = YEAR(CURDATE()) AND MONTH($dateCol) = MONTH(CURDATE())");
                if ($result) { $stats['loans_disbursed_count_this_month'] = (int)($result->fetch_assoc()['cnt'] ?? 0); }
                // Disbursed last month (sum)
                $result = $conn->query("SELECT SUM(amount) as total FROM loans WHERE LOWER(status) = 'disbursed' AND YEAR($dateCol) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH($dateCol) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
                if ($result) { $stats['loans_disbursed_last_month'] = (float)($result->fetch_assoc()['total'] ?? 0); }

                // Disbursed by Type (this month and last month)
                $has_loan_type_id = false;
                $colType = $conn->query("SHOW COLUMNS FROM loans LIKE 'loan_type_id'");
                if ($colType && $colType->num_rows > 0) { $has_loan_type_id = true; }
                $has_loan_types_table = false;
                $ltTbl = $conn->query("SHOW TABLES LIKE 'loan_types'");
                if ($ltTbl && $ltTbl->num_rows > 0) { $has_loan_types_table = true; }
                if ($has_loan_type_id) {
                    $curByType = [];
                    $resT = $conn->query("SELECT loan_type_id, SUM(amount) as total, COUNT(*) as cnt FROM loans WHERE LOWER(status) = 'disbursed' AND YEAR($dateCol) = YEAR(CURDATE()) AND MONTH($dateCol) = MONTH(CURDATE()) GROUP BY loan_type_id");
                    if ($resT) { while ($row = $resT->fetch_assoc()) { $tid = $row['loan_type_id']; $curByType[$tid] = ['amount' => (float)($row['total'] ?? 0), 'count' => (int)($row['cnt'] ?? 0)]; } }
                    $prevByType = [];
                    $resP = $conn->query("SELECT loan_type_id, SUM(amount) as total FROM loans WHERE LOWER(status) = 'disbursed' AND YEAR($dateCol) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH($dateCol) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) GROUP BY loan_type_id");
                    if ($resP) { while ($row = $resP->fetch_assoc()) { $tid = $row['loan_type_id']; $prevByType[$tid] = (float)($row['total'] ?? 0); } }
                    $typeNames = [];
                    if ($has_loan_types_table) {
                        $resN = $conn->query("SELECT id, type_name FROM loan_types");
                        if ($resN) { while ($r = $resN->fetch_assoc()) { $typeNames[$r['id']] = $r['type_name']; } }
                    }
                    $breakdown = [];
                    foreach ($curByType as $tid => $vals) {
                        $amt = (float)($vals['amount'] ?? 0);
                        $cnt = (int)($vals['count'] ?? 0);
                        $prevAmt = (float)($prevByType[$tid] ?? 0);
                        $momPct = $prevAmt > 0 ? (($amt - $prevAmt) / $prevAmt) * 100 : null;
                        $label = $typeNames[$tid] ?? ('Type ' . (string)$tid);
                        $breakdown[] = [
                            'loan_type_id' => $tid,
                            'type_name' => $label,
                            'amount' => $amt,
                            'count' => $cnt,
                            'mom_pct' => $momPct
                        ];
                    }
                    usort($breakdown, function($a,$b){ return ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0); });
                    $stats['disbursed_by_type_this_month'] = $breakdown;
                }
            }

            // Loan approval rate this month
            $has_application_date = false; $has_approved_date = false; $has_disbursement_date = false;
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'application_date'");
            if ($col && $col->num_rows > 0) { $has_application_date = true; }
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'approved_date'");
            if ($col && $col->num_rows > 0) { $has_approved_date = true; }
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'disbursement_date'");
            if ($col && $col->num_rows > 0) { $has_disbursement_date = true; }

            if ($has_application_date) {
                $res = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE YEAR(application_date) = YEAR(CURDATE()) AND MONTH(application_date) = MONTH(CURDATE())");
                if ($res) { $stats['loan_applications_this_month_count'] = (int)($res->fetch_assoc()['cnt'] ?? 0); }
            }
            if ($has_approved_date || $has_disbursement_date) {
                $apprDateCol = $has_approved_date ? 'approved_date' : 'disbursement_date';
                $res = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE LOWER(status) IN ('approved','disbursed','active') AND YEAR($apprDateCol) = YEAR(CURDATE()) AND MONTH($apprDateCol) = MONTH(CURDATE())");
                if ($res) { $stats['loan_approvals_this_month_count'] = (int)($res->fetch_assoc()['cnt'] ?? 0); }
            }
            $apps = (int)($stats['loan_applications_this_month_count'] ?? 0);
            $appr = (int)($stats['loan_approvals_this_month_count'] ?? 0);
            $stats['loan_approval_rate_this_month'] = $apps > 0 ? ($appr / $apps) * 100 : null;

            // Approval rate last month (MoM)
            if ($has_application_date) {
                $res = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE YEAR(application_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(application_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
                if ($res) { $stats['loan_applications_last_month_count'] = (int)($res->fetch_assoc()['cnt'] ?? 0); }
            }
            if ($has_approved_date || $has_disbursement_date) {
                $apprDateCol = $has_approved_date ? 'approved_date' : 'disbursement_date';
                $res = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE LOWER(status) IN ('approved','disbursed','active') AND YEAR($apprDateCol) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH($apprDateCol) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
                if ($res) { $stats['loan_approvals_last_month_count'] = (int)($res->fetch_assoc()['cnt'] ?? 0); }
            }
            $appsPrev = (int)($stats['loan_applications_last_month_count'] ?? 0);
            $apprPrev = (int)($stats['loan_approvals_last_month_count'] ?? 0);
            $stats['loan_approval_rate_last_month'] = $appsPrev > 0 ? ($apprPrev / $appsPrev) * 100 : null;

            // Approval Rate by Loan Type (this month)
            $has_loan_type_id = false;
            $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'loan_type_id'");
            if ($col && $col->num_rows > 0) { $has_loan_type_id = true; }
            $has_loan_types_table = false;
            $ltTbl = $conn->query("SHOW TABLES LIKE 'loan_types'");
            if ($ltTbl && $ltTbl->num_rows > 0) { $has_loan_types_table = true; }

            if ($has_loan_type_id) {
                $appsByType = [];
                if ($has_application_date) {
                    $resT = $conn->query("SELECT loan_type_id, COUNT(*) as cnt FROM loans WHERE YEAR(application_date) = YEAR(CURDATE()) AND MONTH(application_date) = MONTH(CURDATE()) GROUP BY loan_type_id");
                    if ($resT) { while ($row = $resT->fetch_assoc()) { $appsByType[$row['loan_type_id'] ?? ''] = (int)($row['cnt'] ?? 0); } }
                }
                $apprByType = [];
                if ($has_approved_date || $has_disbursement_date) {
                    $apprDateCol = $has_approved_date ? 'approved_date' : 'disbursement_date';
                    $resT = $conn->query("SELECT loan_type_id, COUNT(*) as cnt FROM loans WHERE LOWER(status) IN ('approved','disbursed','active') AND YEAR($apprDateCol) = YEAR(CURDATE()) AND MONTH($apprDateCol) = MONTH(CURDATE()) GROUP BY loan_type_id");
                    if ($resT) { while ($row = $resT->fetch_assoc()) { $apprByType[$row['loan_type_id'] ?? ''] = (int)($row['cnt'] ?? 0); } }
                }
                $typeNames = [];
                if ($has_loan_types_table) {
                    $resT = $conn->query("SELECT id, type_name FROM loan_types");
                    if ($resT) { while ($row = $resT->fetch_assoc()) { $typeNames[$row['id']] = $row['type_name']; } }
                }
                $breakdown = [];
                foreach ($appsByType as $tid => $appsCnt) {
                    $apprCnt = (int)($apprByType[$tid] ?? 0);
                    $rateT = $appsCnt > 0 ? ($apprCnt / $appsCnt) * 100 : null;
                    $label = $typeNames[$tid] ?? ('Type ' . (string)$tid);
                    $breakdown[] = [
                        'loan_type_id' => $tid,
                        'type_name' => $label,
                        'applications' => $appsCnt,
                        'approvals' => $apprCnt,
                        'rate' => $rateT
                    ];
                }
                usort($breakdown, function($a, $b) { return ($b['applications'] ?? 0) <=> ($a['applications'] ?? 0); });
                $stats['approval_by_type_this_month'] = $breakdown;
            }

            // Overdue loans and PAR buckets (15/30/60) based on last repayment date
            if (in_array('loan_repayments', $existing_tables)) {
                // Detect loans id column and outstanding expression
                $has_id = false; $has_loan_id_col = false;
                $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'id'");
                if ($col && $col->num_rows > 0) { $has_id = true; }
                $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'loan_id'");
                if ($col && $col->num_rows > 0) { $has_loan_id_col = true; }
                $idCol = $has_id ? 'id' : ($has_loan_id_col ? 'loan_id' : null);

                $outExpr = 'amount';
                if ($has_amount_paid) { $outExpr = '(amount - amount_paid)'; }
                elseif ($has_remaining_balance) { $outExpr = 'remaining_balance'; }
                elseif ($has_total_repaid) { $outExpr = '(amount - total_repaid)'; }

                if ($idCol) {
                    $sql = "SELECT l.".$idCol." AS id, ".$outExpr." AS outstanding, r.last_date FROM loans l LEFT JOIN (SELECT loan_id, MAX(payment_date) AS last_date FROM loan_repayments GROUP BY loan_id) r ON r.loan_id = l.".$idCol." WHERE LOWER(l.status) IN ('active','disbursed','approved')";
                    $res = $conn->query($sql);
                    $par15_count = 0; $par30_count = 0; $par60_count = 0;
                    $par15_amount = 0.0; $par30_amount = 0.0; $par60_amount = 0.0;
                    if ($res) {
                        while ($row = $res->fetch_assoc()) {
                            $last = $row['last_date'] ?? null;
                            $out = (float)($row['outstanding'] ?? 0);
                            $is15 = (!isset($last) || $last < date('Y-m-d', strtotime('-15 days')));
                            $is30 = (!isset($last) || $last < date('Y-m-d', strtotime('-30 days')));
                            $is60 = (!isset($last) || $last < date('Y-m-d', strtotime('-60 days')));
                            if ($is15) { $par15_count++; $par15_amount += $out; }
                            if ($is30) { $par30_count++; $par30_amount += $out; }
                            if ($is60) { $par60_count++; $par60_amount += $out; }
                        }
                        // Set overdue (aligned to PAR30)
                        $stats['overdue_loans_count'] = $par30_count;
                        $stats['overdue_loans_amount'] = $par30_amount;
                    }
                    $activeLoansCount = (int)($stats['active_loans'] ?? 0);
                    $loanOutstandingTotal = (float)($stats['loan_outstanding'] ?? 0);
                    // Count-based rates
                    $stats['par15_rate'] = $activeLoansCount > 0 ? ($par15_count / $activeLoansCount) * 100 : null;
                    $stats['par30_rate'] = $activeLoansCount > 0 ? ($par30_count / $activeLoansCount) * 100 : null;
                    $stats['par60_rate'] = $activeLoansCount > 0 ? ($par60_count / $activeLoansCount) * 100 : null;
                    // Value-weighted rates
                    $stats['par15_rate_weighted'] = $loanOutstandingTotal > 0 ? ($par15_amount / $loanOutstandingTotal) * 100 : null;
                    $stats['par30_rate_weighted'] = $loanOutstandingTotal > 0 ? ($par30_amount / $loanOutstandingTotal) * 100 : null;
                    $stats['par60_rate_weighted'] = $loanOutstandingTotal > 0 ? ($par60_amount / $loanOutstandingTotal) * 100 : null;
                }
            }
        }
        
        // Get savings statistics via unified helper
        if (in_array('savings_accounts', $existing_tables) || in_array('savings_transactions', $existing_tables)) {
            if (class_exists('Utilities')) {
                $kpi = Utilities::getUnifiedSavingsKPIs($conn);

                $stats['total_savings_balance'] = (float)($kpi['total_savings_balance'] ?? 0);
                $stats['total_deposits'] = (float)($kpi['total_deposits'] ?? 0);
                $stats['deposits_this_month'] = (float)($kpi['deposits_this_month'] ?? 0);
                $stats['withdrawals_this_month'] = (float)($kpi['withdrawals_this_month'] ?? 0);
                $stats['deposits_last_month'] = (float)($kpi['deposits_last_month'] ?? 0);
                $stats['withdrawals_last_month'] = (float)($kpi['withdrawals_last_month'] ?? 0);
                $stats['net_savings_flow_this_month'] = (float)(($stats['deposits_this_month'] ?? 0) - ($stats['withdrawals_this_month'] ?? 0));
            } else {
                // Fallback: keep previous stats if Utilities not available
                $stats['net_savings_flow_this_month'] = (float)(($stats['deposits_this_month'] ?? 0) - ($stats['withdrawals_this_month'] ?? 0));
            }
        }
        // Repayments this month and last month
        if (in_array('loan_repayments', $existing_tables)) {
            $result = $conn->query("SELECT SUM(amount) as total FROM loan_repayments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())");
            if ($result) {
                $stats['repayments_this_month'] = (float)($result->fetch_assoc()['total'] ?? 0);
            }
            $result = $conn->query("SELECT COUNT(*) as cnt FROM loan_repayments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())");
            if ($result) {
                $stats['repayments_count_this_month'] = (int)($result->fetch_assoc()['cnt'] ?? 0);
            }
            $result = $conn->query("SELECT SUM(amount) as total FROM loan_repayments WHERE YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
            if ($result) {
                $stats['repayments_last_month'] = (float)($result->fetch_assoc()['total'] ?? 0);
            }
        }
        // Expected vs Actual: scheduled payments due this month (if schedule table exists)
        $stats['expected_repayments_this_month'] = null; $stats['repayment_gap_this_month'] = null; $stats['repayment_achievement_rate_this_month'] = null;
        $has_schedule = false; $schedule_amount_col = null;
        $chk = $conn->query("SHOW TABLES LIKE 'loan_payment_schedule'");
        if ($chk && $chk->num_rows > 0) {
            $has_schedule = true;
            $c1 = $conn->query("SHOW COLUMNS FROM loan_payment_schedule LIKE 'total_amount'");
            if ($c1 && $c1->num_rows > 0) { $schedule_amount_col = 'total_amount'; }
            else {
                $c2 = $conn->query("SHOW COLUMNS FROM loan_payment_schedule LIKE 'amount'");
                if ($c2 && $c2->num_rows > 0) { $schedule_amount_col = 'amount'; }
            }
        }
        if ($has_schedule && $schedule_amount_col) {
            $col = $schedule_amount_col;
            $sql = "SELECT SUM(s.$col) as total FROM loan_payment_schedule s JOIN loans l ON l.loan_id = s.loan_id WHERE l.status IN ('active','disbursed') AND YEAR(s.due_date) = YEAR(CURDATE()) AND MONTH(s.due_date) = MONTH(CURDATE())";
            $res = $conn->query($sql);
            if ($res) {
                $exp = (float)($res->fetch_assoc()['total'] ?? 0);
                $stats['expected_repayments_this_month'] = $exp;
                $act = (float)($stats['repayments_this_month'] ?? 0);
                $stats['repayment_gap_this_month'] = $exp - $act;
                $stats['repayment_achievement_rate_this_month'] = ($exp > 0) ? (($act / $exp) * 100) : null;
            }
            // Overdue scheduled payments (due date passed and still pending)
            $status_col = null;
            $c3 = $conn->query("SHOW COLUMNS FROM loan_payment_schedule LIKE 'payment_status'");
            if ($c3 && $c3->num_rows > 0) { $status_col = 'payment_status'; }
            else {
                $c4 = $conn->query("SHOW COLUMNS FROM loan_payment_schedule LIKE 'status'");
                if ($c4 && $c4->num_rows > 0) { $status_col = 'status'; }
            }
            $stats['scheduled_overdue_count'] = null; $stats['scheduled_overdue_amount'] = null;
            $sql2 = "SELECT COUNT(*) as cnt, SUM(s.$col) as total FROM loan_payment_schedule s JOIN loans l ON l.loan_id = s.loan_id WHERE l.status IN ('active','disbursed') AND s.due_date < CURDATE()";
            if (!empty($status_col)) { $sql2 .= " AND s.".$status_col." IN ('pending','due','unpaid')"; }
            $res2 = $conn->query($sql2);
            if ($res2) {
                $row2 = $res2->fetch_assoc();
                $stats['scheduled_overdue_count'] = (int)($row2['cnt'] ?? 0);
                $stats['scheduled_overdue_amount'] = (float)($row2['total'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log('Dashboard statistics query failed: ' . $e->getMessage());
        // Stats remain at default values
    }
}

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Font Awesome -->
    
    
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">

    <!-- Tailwind CSS (local build) -->
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #1A5599;
            --secondary-color: #336699;
            --accent-color: #EA8C55;
            --success: var(--success);
            --warning: #d97706;
            --error: #dc2626;
            --text-primary: #1f2937;
            --text-muted: #6b7280;
            --lapis-lazuli: #1A5599;
            --true-blue: #336699;
            --persian-orange: #EA8C55;
            --jasper: #C75146;
        }
        
        .bg-admin { background: var(--admin-bg); }
        .card { background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .card-admin { background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1); }
        .card-body { padding: 1.5rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 500; transition: all 0.2s ease-in-out; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: #ffffff; }
        .btn-primary:hover { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1); }
        .btn-outline { border: 2px solid #d1d5db; color: #374151; }
        .btn-outline:hover { background-color: #f9fafb; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-warning { background-color: #fffbeb; border: 1px solid #fef3c7; color: #92400e; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
        .animate-slide-in { animation: slideIn 0.5s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .sidebar { position: fixed; left: 0; top: 4rem; height: 100%; width: 16rem; background-color: #ffffff; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); transform: translateX(-100%); transition: transform 0.3s ease; z-index: 40; }
        @media (min-width: 768px) { .sidebar { transform: translateX(0); } }
        .sidebar-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); z-index: 30; }
        @media (min-width: 768px) { .sidebar-overlay { display: none; } }
        
        /* Enhanced Card Styling */
        .card {
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        /* Progress Bar Animation */
        @keyframes progressLoad {
            from { width: 0%; }
            to { width: var(--target-width); }
        }
        
        .progress-bar {
            animation: progressLoad 1.5s ease-out;
        }
        
        /* Glassmorphism Effects */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Hover Effects for Quick Actions */
        .quick-action {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .quick-action:hover {
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body class="bg-admin min-h-screen">
<?php include '../../views/includes/header.php'; ?>
<div class="flex">
    <?php include '../../views/includes/sidebar.php'; ?>

    
    <!-- Main Content -->
    <main class="main-content md:ml-64 mt-16 p-6" id="mainContent">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">Admin Dashboard</h1>
                    <p style="color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>! Here's what's happening with your cooperative society.</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <button type="button" class="btn btn-standard btn-outline" onclick="exportDashboardData()">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-standard btn-outline" onclick="printDashboard()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <div class="relative">
                        <button type="button" class="btn btn-standard btn-primary" id="dateRangeBtn" onclick="toggleDateRange()">
                            <i class="fas fa-calendar mr-2"></i> This Month
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        <!-- Date Range Dropdown -->
                        <div id="dateRangeDropdown" class="dropdown-menu absolute right-0 mt-3 w-48 hidden z-50">
                            <a href="#" class="dropdown-item" onclick="setDateRange('today')">Today</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('week')">This Week</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('month')">This Month</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('quarter')">This Quarter</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('year')">This Year</a>
                        </div>
                    </div>
                </div>
            </div>
                
            <!-- Enhanced Alert Messages -->
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
            
            <!-- Database Error Alert -->
            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 icon-warning"></i>
                        <div>
                            <strong>Database Notice:</strong>
                            <span>Some statistics may be limited due to database configuration.</span>
                        </div>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
                
            <!-- Enhanced Statistics Cards with CSIMS Colors -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="dashboardStatsGrid">
                <!-- Total Members Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: #3b28cc;">Total Members</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo number_format($stats['total_members'] ?? 0); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--success);">+<?php echo $stats['new_members_this_month'] ?? 0; ?> this month</span>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: #3b28cc;" onmouseover="this.style.color='var(--true-blue)'" onmouseout="this.style.color='#3b28cc'">
                                    View All Members <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #3b28cc 0%, #3b28cc 100%);">
                                <i class="fas fa-users text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Members Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: #cb0b0a;">Active Members</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo number_format($stats['active_members'] ?? 0); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--success);">Engagement Rate: <?php echo number_format(($stats['active_members'] ?? 0) / max(($stats['total_members'] ?? 1), 1) * 100, 1); ?>%</span>
                                </div>
                                <a href="members.php?status=Active" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: #cb0b0a;">
                                    View Active Members <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #cb0b0a 0%, #cb0b0a 100%);">
                                <i class="fas fa-user-check text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Loans Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: #07beb8;">Active Loans</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo number_format($stats['active_loans'] ?? 0); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--success);">₦<?php echo number_format($stats['loan_outstanding'] ?? 0); ?> outstanding</span>
                                </div>
                                <a href="loans.php" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: #07beb8;">
                                    Manage Loans <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #07beb8 0%, #07beb8 100%);">
                                <i class="fas fa-hand-holding-usd text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Status Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: #214e34;">System Status</p>
                                <p class="text-3xl font-bold text-green-600" style="color: #214e34;">Online</p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: #214e34;">All systems operational</span>
                                </div>
                                <a href="settings.php" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: #214e34;">
                                    System Settings <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #214e34 0%, #214e34 100%);">
                                <i class="fas fa-server text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="dashboardQuickActions">
                <a href="members.php" class="card card-admin quick-action">
                    <div class="card-body p-6 text-center">
                        <i class="fas fa-users text-3xl mb-4 icon-primary" style="color: #3b28cc;"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Manage Members</h3>
                        <p class="text-gray-600 text-sm">Add, edit, and manage member accounts</p>
                    </div>
                </a>

                <a href="loans.php" class="card card-admin quick-action">
                    <div class="card-body p-6 text-center">
                        <i class="fas fa-hand-holding-usd text-3xl mb-4 icon-accent" style="color: #07beb8;"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Loan Management</h3>
                        <p class="text-gray-600 text-sm">Process and track member loans</p>
                    </div>
                </a>

                <a href="savings_accounts.php" class="card card-admin quick-action">
                    <div class="card-body p-6 text-center">
                        <i class="fas fa-piggy-bank text-3xl mb-4 text-emerald-600" style="color: #214e34;"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Savings</h3>
                        <p class="text-gray-600 text-sm">Track member savings and accounts</p>
                    </div>
                </a>

                <a href="reports.php" class="card card-admin quick-action">
                    <div class="card-body p-6 text-center">
                        <i class="fas fa-chart-bar text-3xl mb-4 text-purple-600" style="color: #cb0b0a;"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Reports</h3>
                        <p class="text-gray-600 text-sm">Generate financial and member reports</p>
                    </div>
                </a>
            </div>
            
            <!-- Enhanced Dashboard Insights -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Financial Overview Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <i class="fas fa-chart-pie mr-2 icon-accent" style="color: #07beb8;"></i>
                            Financial Overview
                        </h3>
                        <style>
                        /* Compact Financial Overview table styles */
                        .fo-table-container { max-height: 420px; overflow-y: auto; overscroll-behavior: contain; }
                        .fo-table thead th { position: sticky; top: 0; background: #fff; z-index: 5; }
                        .fo-table td { padding-top: 4px; padding-bottom: 4px; }
                        .fo-table th { padding-top: 6px; padding-bottom: 6px; }
                        </style>
                        <div class="overflow-x-auto fo-table-container">
                            <table class="min-w-full text-xs fo-table">
                                <thead>
                                    <tr class="text-xs text-gray-500">
                                        <th class="text-left py-2">Metric</th>
                                        <th class="text-right py-2">Value</th>
                                        <th class="text-right py-2">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr>
                                        <td class="py-2 text-gray-700">Outstanding Loans</td>
                                        <td class="py-2 text-right font-semibold">₦<?php echo number_format($stats['loan_outstanding'] ?? 0); ?></td>
                                        <td class="py-2 text-right text-gray-500"><?php echo number_format($stats['active_loans'] ?? 0); ?> active</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">Total Savings</td>
                                        <td class="py-2 text-right font-semibold">₦<?php echo number_format($stats['total_savings_balance'] ?? 0); ?></td>
                                        <td class="py-2 text-right text-gray-500"></td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">Active Members</td>
                                        <td class="py-2 text-right font-semibold"><?php echo number_format($stats['active_members'] ?? 0); ?></td>
                                        <td class="py-2 text-right text-gray-500"></td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">Repayments This Month</td>
                                        <td class="py-2 text-right font-semibold">₦<?php echo number_format($stats['repayments_this_month'] ?? 0); ?></td>
                                        <td class="py-2 text-right">
                                            <?php 
                                                $cur = (float)($stats['repayments_this_month'] ?? 0);
                                                $prev = (float)($stats['repayments_last_month'] ?? 0);
                                                $deltaPct = $prev > 0 ? (($cur - $prev) / $prev) * 100 : 0;
                                                $isUp = $cur >= $prev;
                                            ?>
                                            <span class="text-xs mr-2" style="color: <?php echo $isUp ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                <?php echo $isUp ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPct), 1); ?>%
                                            </span>
                                            <span class="text-xs text-gray-500"><?php echo number_format($stats['repayments_count_this_month'] ?? 0); ?> payments</span>
                                        </td>
                                    </tr>
                                    <?php if (isset($stats['expected_repayments_this_month'])) { ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">Expected vs Actual (This Month)</td>
                                        <td class="py-2 text-right font-semibold">
                                            <?php $exp = (float)($stats['expected_repayments_this_month'] ?? 0); $act = (float)($stats['repayments_this_month'] ?? 0); $gap = $exp - $act; ?>
                                            Target ₦<?php echo number_format($exp); ?>
                                        </td>
                                        <td class="py-2 text-right">
                                            <?php $ach = $stats['repayment_achievement_rate_this_month'] ?? null; $isOnTrack = isset($ach) ? ($ach >= 90) : true; $isShortfall = $gap > 0; ?>
                                            <span class="text-xs" style="color: <?php echo $isShortfall ? 'var(--danger)' : 'var(--success)'; ?>;">Gap ₦<?php echo number_format(abs($gap)); ?></span>
                                            <?php if (isset($ach)) { ?>
                                            <span class="text-xs ml-2" style="color: <?php echo $isOnTrack ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                <?php echo number_format($ach, 1); ?>% achieved
                                            </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">Deposits This Month</td>
                                        <td class="py-2 text-right font-semibold">₦<?php echo number_format($stats['deposits_this_month'] ?? 0); ?></td>
                                        <td class="py-2 text-right">
                                            <?php 
                                                $curDep = (float)($stats['deposits_this_month'] ?? 0);
                                                $prevDep = (float)($stats['deposits_last_month'] ?? 0);
                                                $deltaPctDep = $prevDep > 0 ? (($curDep - $prevDep) / $prevDep) * 100 : 0;
                                                $isUpDep = $curDep >= $prevDep;
                                            ?>
                                            <span class="text-xs" style="color: <?php echo $isUpDep ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                <?php echo $isUpDep ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctDep), 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">Withdrawals This Month</td>
                                        <td class="py-2 text-right font-semibold">₦<?php echo number_format($stats['withdrawals_this_month'] ?? 0); ?></td>
                                        <td class="py-2 text-right">
                                            <?php 
                                                $curW = (float)($stats['withdrawals_this_month'] ?? 0);
                                                $prevW = (float)($stats['withdrawals_last_month'] ?? 0);
                                                $deltaPctW = $prevW > 0 ? (($curW - $prevW) / $prevW) * 100 : 0;
                                                $isUpW = $curW >= $prevW;
                                            ?>
                                            <span class="text-xs" style="color: <?php echo $isUpW ? 'var(--danger)' : 'var(--success)'; ?>;">
                                                <?php echo $isUpW ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctW), 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">Overdue Loans</td>
                                        <td class="py-2 text-right font-semibold text-red-600">₦<?php echo number_format($stats['overdue_loans_amount'] ?? 0); ?></td>
                                        <td class="py-2 text-right text-xs text-red-500"><?php echo number_format($stats['overdue_loans_count'] ?? 0); ?> loans</td>
                                    </tr>
                                    <?php if (isset($stats['scheduled_overdue_count'])) { ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">Overdue Scheduled Payments</td>
                                        <td class="py-2 text-right font-semibold text-red-600">₦<?php echo number_format($stats['scheduled_overdue_amount'] ?? 0); ?></td>
                                        <td class="py-2 text-right text-xs text-red-500"><?php echo number_format($stats['scheduled_overdue_count'] ?? 0); ?> payments</td>
                                    </tr>
                                    <?php } ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">Net Savings Flow</td>
                                        <?php $netFlow = (float)($stats['net_savings_flow_this_month'] ?? 0); $isInflow = $netFlow >= 0; ?>
                                        <td class="py-2 text-right font-semibold" style="color: <?php echo $isInflow ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo $isInflow ? 'Inflow' : 'Outflow'; ?> • ₦<?php echo number_format(abs($netFlow)); ?>
                                        </td>
                                        <td class="py-2 text-right text-xs text-gray-500">Deposits: ₦<?php echo number_format($stats['deposits_this_month'] ?? 0); ?> • Withdrawals: ₦<?php echo number_format($stats['withdrawals_this_month'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">Disbursed This Month</td>
                                        <td class="py-2 text-right font-semibold">₦<?php echo number_format($stats['loans_disbursed_this_month'] ?? 0); ?></td>
                                        <td class="py-2 text-right">
                                            <?php 
                                                $curD = (float)($stats['loans_disbursed_this_month'] ?? 0);
                                                $prevD = (float)($stats['loans_disbursed_last_month'] ?? 0);
                                                $deltaPctD = $prevD > 0 ? (($curD - $prevD) / $prevD) * 100 : 0;
                                                $isUpD = $curD >= $prevD;
                                            ?>
                                            <span class="text-xs mr-2" style="color: <?php echo $isUpD ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                <?php echo $isUpD ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctD), 1); ?>%
                                            </span>
                                            <span class="text-xs text-gray-500"><?php echo number_format($stats['loans_disbursed_count_this_month'] ?? 0); ?> loans</span>
                                        </td>
                                    </tr>
                                    <?php $dist = $stats['disbursed_by_type_this_month'] ?? []; if (!empty($dist)) { $topDist = array_slice($dist, 0, 3); ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">Disbursed by Type</td>
                                        <td class="py-2"></td>
                                        <td class="py-2 text-right">
                                            <a href="#" class="text-xs cursor-pointer text-gray-700" onclick="toggleSection('disbursed-type-details', this); return false;">Show top types</a>
                                            <div id="disbursed-type-details" class="mt-2 space-y-1" data-open="false" style="display:none;">
                                                <?php foreach ($topDist as $row) { $mom = isset($row['mom_pct']) ? (float)$row['mom_pct'] : null; $isUpT = isset($mom) ? ($mom >= 0) : true; ?>
                                                    <div class="text-right">
                                                        <span class="text-xs text-gray-600 mr-2"><?php echo htmlspecialchars($row['type_name'] ?? 'Unknown'); ?></span>
                                                        <span class="text-xs font-medium mr-2">₦<?php echo number_format($row['amount'] ?? 0); ?></span>
                                                        <span class="text-[10px] text-gray-500 mr-2"><?php echo number_format($row['count'] ?? 0); ?> loans</span>
                                                        <?php if (isset($mom)) { ?>
                                                        <span class="text-[10px]" style="color: <?php echo $isUpT ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                            <?php echo $isUpT ? '▲' : '▼'; ?> <?php echo number_format(abs($mom), 1); ?>% MoM
                                                        </span>
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">Approval Rate This Month</td>
                                        <td class="py-2 text-right font-semibold">
                                            <?php $rate = $stats['loan_approval_rate_this_month'] ?? null; echo isset($rate) ? (number_format($rate, 1) . '%') : 'N/A'; ?>
                                        </td>
                                        <td class="py-2 text-right">
                                            <?php $prevRate = $stats['loan_approval_rate_last_month'] ?? null; $curR = isset($rate) ? (float)$rate : null; $prevR = isset($prevRate) ? (float)$prevRate : null; $deltaPctR = (isset($prevR) && $prevR > 0 && isset($curR)) ? (($curR - $prevR) / $prevR) * 100 : 0; $isUpR = (isset($curR) && isset($prevR)) ? ($curR >= $prevR) : true; ?>
                                            <span class="text-xs mr-2" style="color: <?php echo $isUpR ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                <?php echo $isUpR ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctR), 1); ?>%
                                            </span>
                                            <span class="text-xs text-gray-500">Approved: <?php echo number_format($stats['loan_approvals_this_month_count'] ?? 0); ?> / Apps: <?php echo number_format($stats['loan_applications_this_month_count'] ?? 0); ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">PAR30 Default Rate</td>
                                        <?php $parW = $stats['par30_rate_weighted'] ?? null; $parC = $stats['par30_rate'] ?? null; $overC = $stats['overdue_loans_count'] ?? 0; $actC = $stats['active_loans'] ?? 0; ?>
                                        <td class="py-2 text-right font-semibold text-red-600"><?php echo isset($parW) ? (number_format($parW, 1) . '%') : 'N/A'; ?></td>
                                        <td class="py-2 text-right text-xs text-gray-500">Count-based: <?php echo isset($parC) ? number_format($parC, 1).'%' : 'N/A'; ?> • Overdue: <?php echo number_format($overC); ?> / Active: <?php echo number_format($actC); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-gray-700">PAR Buckets (15/30/60)</td>
                                        <td class="py-2"></td>
                                        <td class="py-2 text-right">
                                            <?php $p15w = $stats['par15_rate_weighted'] ?? null; $p15c = $stats['par15_rate'] ?? null; ?>
                                            <div class="text-xs text-gray-700">PAR15: <?php echo isset($p15w) ? number_format($p15w,1).'%' : 'N/A'; ?> • Count: <?php echo isset($p15c) ? number_format($p15c,1).'%' : 'N/A'; ?></div>
                                            <?php $p30w = $stats['par30_rate_weighted'] ?? null; $p30c = $stats['par30_rate'] ?? null; ?>
                                            <div class="text-xs text-gray-700">PAR30: <?php echo isset($p30w) ? number_format($p30w,1).'%' : 'N/A'; ?> • Count: <?php echo isset($p30c) ? number_format($p30c,1).'%' : 'N/A'; ?></div>
                                            <?php $p60w = $stats['par60_rate_weighted'] ?? null; $p60c = $stats['par60_rate'] ?? null; ?>
                                            <div class="text-xs text-gray-700">PAR60: <?php echo isset($p60w) ? number_format($p60w,1).'%' : 'N/A'; ?> • Count: <?php echo isset($p60c) ? number_format($p60c,1).'%' : 'N/A'; ?></div>
                                        </td>
                                    </tr>
                                    <?php $distPar = $stats['par_by_type'] ?? []; if (!empty($distPar)) { $topPar = array_slice($distPar, 0, 3); ?>
                                    <tr>
                                        <td class="py-2 text-gray-700">PAR by Type</td>
                                        <td class="py-2"></td>
                                        <td class="py-2 text-right">
                                            <a href="#" class="text-xs cursor-pointer text-gray-700" onclick="toggleSection('par-type-details', this); return false;">Show top PAR types</a>
                                            <div id="par-type-details" class="mt-2 space-y-1" data-open="false" style="display:none;">
                                                <?php foreach ($topPar as $row) { $r = isset($row['rate']) ? (float)$row['rate'] : null; ?>
                                                    <div class="text-right">
                                                        <span class="text-xs text-gray-600 mr-2"><?php echo htmlspecialchars($row['type_name'] ?? 'Unknown'); ?></span>
                                                        <span class="text-xs font-medium mr-2"><?php echo isset($r) ? number_format($r,1).'%' : 'N/A'; ?></span>
                                                        <span class="text-[10px] text-gray-500">Appr: <?php echo number_format($row['approvals'] ?? 0); ?> / Apps: <?php echo number_format($row['applications'] ?? 0); ?></span>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <tr>
                                        <td class="py-3 font-bold text-gray-800">Total Value</td>
                                        <td class="py-3 text-right font-bold" style="color: var(--success);">₦<?php echo number_format(($stats['loan_outstanding'] ?? 0) + ($stats['total_savings_balance'] ?? 0)); ?></td>
                                        <td class="py-3"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <script>
                        function toggleSection(id, link) {
                            var el = document.getElementById(id);
                            if (!el) return;
                            var isOpen = el.getAttribute('data-open') === 'true';
                            el.style.display = isOpen ? 'none' : 'block';
                            el.setAttribute('data-open', isOpen ? 'false' : 'true');
                            if (link) {
                                link.textContent = isOpen ? 'Show details' : 'Hide details';
                            }
                        }
                        </script>

                        <div class="space-y-3 hidden">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Outstanding Loans</span>
                                <span class="font-semibold">₦<?php echo number_format($stats['loan_outstanding'] ?? 0); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Savings</span>
                                <span class="font-semibold">₦<?php echo number_format($stats['total_savings_balance'] ?? 0); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Active Members</span>
                                <span class="font-semibold"><?php echo number_format($stats['active_members'] ?? 0); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Repayments This Month</span>
                                <div class="text-right">
                                    <div class="font-semibold">
                                        ₦<?php echo number_format($stats['repayments_this_month'] ?? 0); ?>
                                        <?php 
                                            $cur = (float)($stats['repayments_this_month'] ?? 0);
                                            $prev = (float)($stats['repayments_last_month'] ?? 0);
                                            $deltaPct = $prev > 0 ? (($cur - $prev) / $prev) * 100 : 0;
                                            $isUp = $cur >= $prev;
                                        ?>
                                        <span class="text-xs ml-2" style="color: <?php echo $isUp ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo $isUp ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPct), 1); ?>%
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo number_format($stats['repayments_count_this_month'] ?? 0); ?> payments</div>
                                </div>
                            </div>
                            <?php if (isset($stats['expected_repayments_this_month'])) { ?>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Expected vs Actual (This Month)</span>
                                <div class="text-right">
                                    <?php $exp = (float)($stats['expected_repayments_this_month'] ?? 0); $act = (float)($stats['repayments_this_month'] ?? 0); $gap = $exp - $act; $ach = $stats['repayment_achievement_rate_this_month'] ?? null; $isOnTrack = isset($ach) ? ($ach >= 90) : true; ?>
                                    <div class="font-semibold">
                                        Target ₦<?php echo number_format($exp); ?> • Gap ₦<?php echo number_format(abs($gap)); ?>
                                    </div>
                                    <?php if (isset($ach)) { ?>
                                    <div class="text-xs" style="color: <?php echo $isOnTrack ? 'var(--success)' : 'var(--danger)'; ?>;">
                                        <?php echo number_format($ach, 1); ?>% achieved
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>
                            <!-- Deposits This Month -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Deposits This Month</span>
                                <div class="text-right">
                                    <div class="font-semibold">
                                        ₦<?php echo number_format($stats['deposits_this_month'] ?? 0); ?>
                                        <?php 
                                            $curDep = (float)($stats['deposits_this_month'] ?? 0);
                                            $prevDep = (float)($stats['deposits_last_month'] ?? 0);
                                            $deltaPctDep = $prevDep > 0 ? (($curDep - $prevDep) / $prevDep) * 100 : 0;
                                            $isUpDep = $curDep >= $prevDep;
                                        ?>
                                        <span class="text-xs ml-2" style="color: <?php echo $isUpDep ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo $isUpDep ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctDep), 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Withdrawals This Month</span>
                                <div class="text-right">
                                    <div class="font-semibold">
                                        ₦<?php echo number_format($stats['withdrawals_this_month'] ?? 0); ?>
                                        <?php 
                                            $curW = (float)($stats['withdrawals_this_month'] ?? 0);
                                            $prevW = (float)($stats['withdrawals_last_month'] ?? 0);
                                            $deltaPctW = $prevW > 0 ? (($curW - $prevW) / $prevW) * 100 : 0;
                                            $isUpW = $curW >= $prevW;
                                        ?>
                                        <span class="text-xs ml-2" style="color: <?php echo $isUpW ? 'var(--danger)' : 'var(--success)'; ?>;">
                                            <?php echo $isUpW ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctW), 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- Overdue Loans -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Overdue Loans</span>
                                <div class="text-right">
                                    <div class="font-semibold text-red-600">
                                        ₦<?php echo number_format($stats['overdue_loans_amount'] ?? 0); ?>
                                    </div>
                                    <div class="text-xs text-red-500"><?php echo number_format($stats['overdue_loans_count'] ?? 0); ?> loans</div>
                                </div>
                            </div>
                            <?php if (isset($stats['scheduled_overdue_count'])) { ?>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Overdue Scheduled Payments</span>
                                <div class="text-right">
                                    <div class="font-semibold text-red-600">
                                        ₦<?php echo number_format($stats['scheduled_overdue_amount'] ?? 0); ?>
                                    </div>
                                    <div class="text-xs text-red-500"><?php echo number_format($stats['scheduled_overdue_count'] ?? 0); ?> payments</div>
                                </div>
                            </div>
                            <?php } ?>
                            <!-- Net Savings Flow (This Month) -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Net Savings Flow</span>
                                <?php $netFlow = (float)($stats['net_savings_flow_this_month'] ?? 0); $isInflow = $netFlow >= 0; ?>
                                <div class="text-right">
                                    <div class="font-semibold" style="color: <?php echo $isInflow ? 'var(--success)' : 'var(--danger)'; ?>;">
                                        <?php echo $isInflow ? 'Inflow' : 'Outflow'; ?> • ₦<?php echo number_format(abs($netFlow)); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Deposits: ₦<?php echo number_format($stats['deposits_this_month'] ?? 0); ?> • Withdrawals: ₦<?php echo number_format($stats['withdrawals_this_month'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Disbursed This Month</span>
                                <div class="text-right">
                                    <div class="font-semibold">
                                        ₦<?php echo number_format($stats['loans_disbursed_this_month'] ?? 0); ?>
                                        <?php 
                                            $curD = (float)($stats['loans_disbursed_this_month'] ?? 0);
                                            $prevD = (float)($stats['loans_disbursed_last_month'] ?? 0);
                                            $deltaPctD = $prevD > 0 ? (($curD - $prevD) / $prevD) * 100 : 0;
                                            $isUpD = $curD >= $prevD;
                                        ?>
                                        <span class="text-xs ml-2" style="color: <?php echo $isUpD ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo $isUpD ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctD), 1); ?>%
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo number_format($stats['loans_disbursed_count_this_month'] ?? 0); ?> loans</div>
                                </div>
                            </div>
                            <!-- Disbursed by Type (Top 3) -->
                            <?php $dist = $stats['disbursed_by_type_this_month'] ?? []; if (!empty($dist)) { $topDist = array_slice($dist, 0, 3); ?>
                            <div class="mt-2 space-y-1">
                                <div class="text-sm text-gray-600">Disbursed by Type</div>
                                <?php foreach ($topDist as $row) { $mom = isset($row['mom_pct']) ? (float)$row['mom_pct'] : null; $isUpT = isset($mom) ? ($mom >= 0) : true; ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600"><?php echo htmlspecialchars($row['type_name'] ?? 'Unknown'); ?></span>
                                    <div class="text-right">
                                        <div class="text-xs font-medium">₦<?php echo number_format($row['amount'] ?? 0); ?></div>
                                        <div class="text-[10px] text-gray-500"><?php echo number_format($row['count'] ?? 0); ?> loans</div>
                                        <?php if (isset($mom)) { ?>
                                        <div class="text-[10px] mt-0.5" style="color: <?php echo $isUpT ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo $isUpT ? '▲' : '▼'; ?> <?php echo number_format(abs($mom), 1); ?>% MoM
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <!-- Approval Rate This Month -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Approval Rate This Month</span>
                                <div class="text-right">
                                    <?php $rate = $stats['loan_approval_rate_this_month'] ?? null; $prevRate = $stats['loan_approval_rate_last_month'] ?? null; $curR = isset($rate) ? (float)$rate : null; $prevR = isset($prevRate) ? (float)$prevRate : null; $deltaPctR = (isset($prevR) && $prevR > 0 && isset($curR)) ? (($curR - $prevR) / $prevR) * 100 : 0; $isUpR = (isset($curR) && isset($prevR)) ? ($curR >= $prevR) : true; ?>
                                    <div class="font-semibold">
                                        <?php echo isset($rate) ? (number_format($rate, 1) . '%') : 'N/A'; ?>
                                        <span class="text-xs ml-2" style="color: <?php echo $isUpR ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo $isUpR ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPctR), 1); ?>%
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500">Approved: <?php echo number_format($stats['loan_approvals_this_month_count'] ?? 0); ?> / Applications: <?php echo number_format($stats['loan_applications_this_month_count'] ?? 0); ?></div>
                                </div>
                            </div>
                            <!-- PAR30 Default Rate -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">PAR30 Default Rate</span>
                                <div class="text-right">
                                    <?php $parW = $stats['par30_rate_weighted'] ?? null; $parC = $stats['par30_rate'] ?? null; $overC = $stats['overdue_loans_count'] ?? 0; $actC = $stats['active_loans'] ?? 0; ?>
                                    <div class="font-semibold text-red-600">
                                        <?php echo isset($parW) ? (number_format($parW, 1) . '%') : 'N/A'; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Value-weighted • Count-based: <?php echo isset($parC) ? number_format($parC, 1).'%' : 'N/A'; ?> • Overdue: <?php echo number_format($overC); ?> / Active: <?php echo number_format($actC); ?></div>
                                </div>
                            </div>
                            <!-- PAR Buckets (15/30/60) -->
                            <div class="mt-2 space-y-1">
                                <div class="text-sm text-gray-600">PAR Buckets</div>
                                <?php $p15w = $stats['par15_rate_weighted'] ?? null; $p15c = $stats['par15_rate'] ?? null; ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">PAR15</span>
                                    <div class="text-right">
                                        <div class="text-xs font-medium"><?php echo isset($p15w) ? number_format($p15w,1).'%' : 'N/A'; ?></div>
                                        <div class="text-[10px] text-gray-500">Count-based: <?php echo isset($p15c) ? number_format($p15c,1).'%' : 'N/A'; ?></div>
                                    </div>
                                </div>
                                <?php $p30w = $stats['par30_rate_weighted'] ?? null; $p30c = $stats['par30_rate'] ?? null; ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">PAR30</span>
                                    <div class="text-right">
                                        <div class="text-xs font-medium"><?php echo isset($p30w) ? number_format($p30w,1).'%' : 'N/A'; ?></div>
                                        <div class="text-[10px] text-gray-500">Count-based: <?php echo isset($p30c) ? number_format($p30c,1).'%' : 'N/A'; ?></div>
                                    </div>
                                </div>
                                <?php $p60w = $stats['par60_rate_weighted'] ?? null; $p60c = $stats['par60_rate'] ?? null; ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">PAR60</span>
                                    <div class="text-right">
                                        <div class="text-xs font-medium"><?php echo isset($p60w) ? number_format($p60w,1).'%' : 'N/A'; ?></div>
                                        <div class="text-[10px] text-gray-500">Count-based: <?php echo isset($p60c) ? number_format($p60c,1).'%' : 'N/A'; ?></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Approval Rate by Type (Top 3) -->
                            <?php $byType = $stats['approval_by_type_this_month'] ?? []; if (!empty($byType)) { $top = array_slice($byType, 0, 3); ?>
                            <div class="mt-2 space-y-1">
                                <div class="text-sm text-gray-600">Approval Rate by Type</div>
                                <?php foreach ($top as $row) { $r = $row['rate'] ?? null; ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600"><?php echo htmlspecialchars($row['type_name'] ?? 'Unknown'); ?></span>
                                    <div class="text-right">
                                        <div class="text-xs font-medium"><?php echo isset($r) ? number_format($r,1).'%' : 'N/A'; ?></div>
                                        <div class="text-[10px] text-gray-500">Appr: <?php echo number_format($row['approvals'] ?? 0); ?> / Apps: <?php echo number_format($row['applications'] ?? 0); ?></div>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <hr class="my-3">
                            <div class="flex justify-between items-center font-bold">
                                <span class="text-gray-800">Total Value</span>
                                <span style="color: var(--success);">₦<?php echo number_format(($stats['loan_outstanding'] ?? 0) + ($stats['total_savings_balance'] ?? 0)); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Repayments This Month Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <i class="fas fa-money-check-alt mr-2 icon-secondary"></i>
                            Repayments This Month
                        </h3>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-3xl font-bold" style="color: var(--accent-color);">₦<?php echo number_format($stats['repayments_this_month'] ?? 0); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo number_format($stats['repayments_count_this_month'] ?? 0); ?> payments this month</div>
                                <?php 
                                    $cur = (float)($stats['repayments_this_month'] ?? 0);
                                    $prev = (float)($stats['repayments_last_month'] ?? 0);
                                    $deltaPct = $prev > 0 ? (($cur - $prev) / $prev) * 100 : 0;
                                    $isUp = $cur >= $prev;
                                ?>
                                <div class="text-xs mt-1" style="color: <?php echo $isUp ? 'var(--success)' : 'var(--danger)'; ?>;">
                                    <?php echo $isUp ? '▲' : '▼'; ?> <?php echo number_format(abs($deltaPct), 1); ?>% vs last month
                                </div>
                            </div>
                            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--accent-color), #f59e0b);">
                                <i class="fas fa-receipt text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 text-sm text-gray-600">Sum of all loan repayments recorded this month.</div>
                        <a href="reports.php" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: var(--accent-color);">
                            View repayment report <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>

                <!-- System Metrics Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-tachometer-alt mr-2 icon-primary"></i>
                            System Metrics
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Member Engagement</span>
                                    <span class="font-medium"><?php echo number_format(($stats['active_members'] ?? 0) / max(($stats['total_members'] ?? 1), 1) * 100, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="background: var(--success); width: <?php echo min(($stats['active_members'] ?? 0) / max(($stats['total_members'] ?? 1), 1) * 100, 100); ?>%;"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Loan Utilization</span>
                                    <span class="font-medium"><?php echo number_format(($stats['active_loans'] ?? 0) / max(($stats['total_loans'] ?? 1), 1) * 100, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="background: var(--accent-color); width: <?php echo min(($stats['active_loans'] ?? 0) / max(($stats['total_loans'] ?? 1), 1) * 100, 100); ?>%;"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">System Health</span>
                                    <span class="font-medium">98.5%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="background: var(--success); width: 98.5%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Card -->
            <div class="card card-admin">
                <div class="card-body p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-history mr-2 icon-secondary"></i>
                        Recent Activity
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs text-white" style="background: var(--success);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">New member registered</p>
                                <p class="text-xs text-gray-500"><?php echo date('M d, H:i'); ?></p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs text-white" style="background: var(--accent-color);">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Loan payment received</p>
                                <p class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime('-2 hours')); ?></p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs text-white" style="background: var(--primary-color);">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Monthly savings</p>
                                <p class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime('-4 hours')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="reports.php" class="text-sm font-medium" style="color: var(--primary-color);">View all activity →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section removed per request -->
    </main>
</body>
</html>
