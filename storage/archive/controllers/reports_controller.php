<?php
require_once '../includes/config/database.php';

class ReportsController {
    private $pdo;
    
    public function __construct() {
        $database = new PdoDatabase();
        $this->pdo = $database->getConnection();
    }
    
    // ===================================================================
    // FINANCIAL REPORTS
    // ===================================================================
    
    /**
     * Generate comprehensive financial summary
     */
    public function getFinancialSummary($start_date = null, $end_date = null) {
        $start_date = $start_date ?? date('Y-01-01');
        $end_date = $end_date ?? date('Y-m-d');
        
        try {
            // Total loans disbursed
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_loans,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN status = 'Active' THEN amount ELSE 0 END), 0) as active_amount,
                    COALESCE(SUM(amount_paid), 0) as total_paid,
                    COALESCE(SUM(amount - amount_paid), 0) as outstanding_balance
                FROM loans 
                WHERE disbursement_date BETWEEN :start_date AND :end_date
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $loan_summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Total savings deposits
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_contributions,
                    COALESCE(SUM(st.amount), 0) as total_amount,
                    COUNT(DISTINCT st.member_id) as contributing_members
                FROM savings_transactions st
                WHERE st.transaction_date BETWEEN :start_date AND :end_date
                AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $contribution_summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Share capital summary
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_share_purchases,
                    COALESCE(SUM(total_value), 0) as total_share_value,
                    COALESCE(SUM(amount_paid), 0) as total_paid,
                    COALESCE(SUM(number_of_shares), 0) as total_shares
                FROM share_capital 
                WHERE purchase_date BETWEEN :start_date AND :end_date
                AND status = 'active'
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $share_summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Withdrawal summary
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_withdrawals,
                    COALESCE(SUM(amount), 0) as gross_amount,
                    COALESCE(SUM(net_amount), 0) as net_amount,
                    COALESCE(SUM(withdrawal_fee), 0) as total_fees
                FROM contribution_withdrawals 
                WHERE withdrawal_date BETWEEN :start_date AND :end_date
                AND approval_status IN ('approved', 'processed')
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $withdrawal_summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'period' => ['start' => $start_date, 'end' => $end_date],
                'loans' => $loan_summary,
                'contributions' => $contribution_summary,
                'shares' => $share_summary,
                'withdrawals' => $withdrawal_summary,
                'net_position' => [
                    'total_assets' => ($contribution_summary['total_amount'] + $share_summary['total_paid']) - $withdrawal_summary['net_amount'],
                    'outstanding_loans' => $loan_summary['outstanding_balance'],
                    'liquid_reserves' => ($contribution_summary['total_amount'] - $withdrawal_summary['net_amount']) - $loan_summary['outstanding_balance']
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in getFinancialSummary: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate loan portfolio analysis
     */
    public function getLoanPortfolioAnalysis($period = '1_year') {
        try {
            $date_condition = $this->getPeriodCondition($period);
            
            // Loan status distribution
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(AVG(amount), 0) as avg_amount
                FROM loans 
                WHERE $date_condition
                GROUP BY status
            ");
            $stmt->execute();
            $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Loan performance by term
            $stmt = $this->pdo->prepare("
                SELECT 
                    CASE 
                        WHEN term <= 6 THEN 'Short-term (≤6 months)'
                        WHEN term <= 12 THEN 'Medium-term (7-12 months)'
                        WHEN term <= 24 THEN 'Long-term (13-24 months)'
                        ELSE 'Extended-term (>24 months)'
                    END as term_category,
                    COUNT(*) as count,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(AVG(interest_rate), 0) as avg_interest_rate,
                    COALESCE(SUM(amount_paid), 0) as total_paid,
                    COALESCE(SUM(amount - amount_paid), 0) as outstanding
                FROM loans 
                WHERE $date_condition
                GROUP BY term_category
                ORDER BY MIN(term)
            ");
            $stmt->execute();
            $term_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Overdue loans analysis
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as overdue_count,
                    COALESCE(SUM(amount - amount_paid), 0) as overdue_amount,
                    COALESCE(AVG(DATEDIFF(CURDATE(), next_payment_date)), 0) as avg_days_overdue
                FROM loans 
                WHERE next_payment_date < CURDATE() 
                AND status = 'Active'
                AND (amount - amount_paid) > 0
            ");
            $stmt->execute();
            $overdue_analysis = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Top borrowers
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.member_id,
                    CONCAT(m.first_name, ' ', m.last_name) as member_name,
                    COUNT(l.loan_id) as loan_count,
                    COALESCE(SUM(l.amount), 0) as total_borrowed,
                    COALESCE(SUM(l.amount_paid), 0) as total_paid,
                    COALESCE(SUM(l.amount - l.amount_paid), 0) as current_outstanding
                FROM members m
                INNER JOIN loans l ON m.member_id = l.member_id
                WHERE $date_condition
                GROUP BY m.member_id, m.first_name, m.last_name
                ORDER BY total_borrowed DESC
                LIMIT 10
            ");
            $stmt->execute();
            $top_borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status_distribution' => $status_distribution,
                'term_analysis' => $term_analysis,
                'overdue_analysis' => $overdue_analysis,
                'top_borrowers' => $top_borrowers
            ];
        } catch (Exception $e) {
            error_log("Error in getLoanPortfolioAnalysis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate member statistics and trends
     */
    public function getMemberStatistics($period = '1_year') {
        try {
            $date_condition = $this->getPeriodCondition($period, 'join_date');
            
            // Member growth
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(join_date, '%Y-%m') as month,
                    COUNT(*) as new_members,
                    SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(join_date, '%Y-%m')) as cumulative_members
                FROM members 
                WHERE $date_condition
                GROUP BY DATE_FORMAT(join_date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute();
            $member_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Member status distribution
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members)), 2) as percentage
                FROM members 
                GROUP BY status
            ");
            $stmt->execute();
            $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Active members with contributions -> use savings deposits
            $stmt = $this->pdo->prepare("\n                SELECT \n                    COUNT(DISTINCT st.member_id) as active_contributors,\n                    COALESCE(AVG(member_totals.total_contributions), 0) as avg_contributions,\n                    COALESCE(MAX(member_totals.total_contributions), 0) as max_contributions,\n                    COALESCE(MIN(member_totals.total_contributions), 0) as min_contributions\n                FROM savings_transactions st\n                INNER JOIN (\n                    SELECT member_id, SUM(amount) as total_contributions\n                    FROM savings_transactions\n                    WHERE transaction_type = 'Deposit' AND transaction_status = 'Completed'\n                    GROUP BY member_id\n                ) member_totals ON st.member_id = member_totals.member_id\n                WHERE st.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)\n                AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'\n            ");
            $stmt->execute();
            $contribution_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Members with loans
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT member_id) as members_with_loans,
                    COALESCE(AVG(loan_count), 0) as avg_loans_per_member,
                    COALESCE(AVG(total_borrowed), 0) as avg_amount_per_member
                FROM (
                    SELECT 
                        member_id,
                        COUNT(*) as loan_count,
                        SUM(amount) as total_borrowed
                    FROM loans
                    WHERE status IN ('Active', 'Completed')
                    GROUP BY member_id
                ) member_loans
            ");
            $stmt->execute();
            $loan_participation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'growth_trend' => $member_growth,
                'status_distribution' => $status_distribution,
                'contribution_participation' => $contribution_stats,
                'loan_participation' => $loan_participation,
                'total_members' => array_sum(array_column($status_distribution, 'count'))
            ];
        } catch (Exception $e) {
            error_log("Error in getMemberStatistics: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate contribution performance report
     */
    public function getContributionPerformance($period = '1_year') {
        try {
            $date_condition = $this->getPeriodCondition($period, 'transaction_date');
            
            // Monthly savings deposit trends
            $stmt = $this->pdo->prepare("\n                SELECT \n                    DATE_FORMAT(st.transaction_date, '%Y-%m') as month,\n                    sa.account_type as savings_type,\n                    COUNT(*) as transaction_count,\n                    COALESCE(SUM(st.amount), 0) as total_amount,\n                    COUNT(DISTINCT st.member_id) as unique_contributors\n                FROM savings_transactions st\n                INNER JOIN savings_accounts sa ON st.account_id = sa.account_id\n                WHERE $date_condition AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'\n                GROUP BY DATE_FORMAT(st.transaction_date, '%Y-%m'), sa.account_type\n                ORDER BY month, savings_type\n            ");
            $stmt->execute();
            $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Savings type analysis
            $stmt = $this->pdo->prepare("\n                SELECT \n                    sa.account_type as savings_type,\n                    COUNT(*) as transaction_count,\n                    COALESCE(SUM(st.amount), 0) as total_amount,\n                    COALESCE(AVG(st.amount), 0) as avg_amount,\n                    COUNT(DISTINCT st.member_id) as unique_contributors\n                FROM savings_transactions st\n                INNER JOIN savings_accounts sa ON st.account_id = sa.account_id\n                WHERE $date_condition AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'\n                GROUP BY sa.account_type\n                ORDER BY total_amount DESC\n            ");
            $stmt->execute();
            $type_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Target achievement analysis (legacy targets retained if present)
            $stmt = $this->pdo->prepare("\n                SELECT \n                    target_type,\n                    COUNT(*) as total_targets,\n                    SUM(CASE WHEN achievement_status = 'achieved' THEN 1 ELSE 0 END) as achieved_targets,\n                    ROUND(AVG(achievement_percentage), 2) as avg_achievement,\n                    COALESCE(SUM(target_amount), 0) as total_target_amount,\n                    COALESCE(SUM(amount_achieved), 0) as total_achieved_amount\n                FROM contribution_targets \n                WHERE status = 'active'\n                AND target_period_start >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)\n                GROUP BY target_type\n            ");
            $stmt->execute();
            $target_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top savers
            $stmt = $this->pdo->prepare("\n                SELECT \n                    m.member_id,\n                    CONCAT(m.first_name, ' ', m.last_name) as member_name,\n                    COUNT(st.transaction_id) as contribution_count,\n                    COALESCE(SUM(st.amount), 0) as total_contributed,\n                    COALESCE(AVG(st.amount), 0) as avg_contribution\n                FROM members m\n                INNER JOIN savings_transactions st ON m.member_id = st.member_id\n                WHERE st.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)\n                AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'\n                GROUP BY m.member_id, m.first_name, m.last_name\n                ORDER BY total_contributed DESC\n                LIMIT 10\n            ");
            $stmt->execute();
            $top_contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'monthly_trends' => $monthly_trends,
                'type_analysis' => $type_analysis,
                'target_performance' => $target_performance,
                'top_contributors' => $top_contributors
            ];
        } catch (Exception $e) {
            error_log("Error in getContributionPerformance: " . $e->getMessage());
            return false;
        }
    }
    
    // ===================================================================
    // DASHBOARD METRICS
    // ===================================================================
    
    /**
     * Get key performance indicators for dashboard
     */
    public function getKPIs() {
        try {
            $kpis = [];
            
            // Total members
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM members WHERE status = 'Active'");
            $stmt->execute();
            $kpis['total_active_members'] = $stmt->fetchColumn();
            
            // Total loan portfolio
            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount - amount_paid), 0) as total FROM loans WHERE status = 'Active'");
            $stmt->execute();
            $kpis['outstanding_loans'] = $stmt->fetchColumn();
            
            // Total contributions this year -> use savings deposits
            $stmt = $this->pdo->prepare("\n                SELECT COALESCE(SUM(amount), 0) as total \n                FROM savings_transactions \n                WHERE YEAR(transaction_date) = YEAR(CURDATE()) \n                AND transaction_type = 'Deposit'\n                AND transaction_status = 'Completed'\n            ");
            $stmt->execute();
            $kpis['contributions_this_year'] = $stmt->fetchColumn();
            
            // Overdue loans count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM loans 
                WHERE next_payment_date < CURDATE() 
                AND status = 'Active'
                AND (amount - amount_paid) > 0
            ");
            $stmt->execute();
            $kpis['overdue_loans'] = $stmt->fetchColumn();
            
            // Portfolio at risk (PAR)
            $stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN next_payment_date < CURDATE() THEN (amount - amount_paid) ELSE 0 END), 0) as overdue_amount,
                    COALESCE(SUM(amount - amount_paid), 0) as total_outstanding
                FROM loans 
                WHERE status = 'Active'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $kpis['portfolio_at_risk'] = $result['total_outstanding'] > 0 ? 
                round(($result['overdue_amount'] / $result['total_outstanding']) * 100, 2) : 0;
            
            // New members this month
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM members 
                WHERE YEAR(join_date) = YEAR(CURDATE()) 
                AND MONTH(join_date) = MONTH(CURDATE())
            ");
            $stmt->execute();
            $kpis['new_members_this_month'] = $stmt->fetchColumn();
            
            return $kpis;
        } catch (Exception $e) {
            error_log("Error in getKPIs: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent transactions for dashboard
     */
    public function getRecentTransactions($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("\n                (SELECT \n                    'savings_deposit' as type,\n                    st.transaction_id as id,\n                    CONCAT(m.first_name, ' ', m.last_name) as member_name,\n                    st.amount,\n                    CONCAT('Savings Deposit - ', sa.account_number) as description,\n                    st.transaction_date as transaction_date,\n                    st.transaction_status as status\n                FROM savings_transactions st\n                INNER JOIN members m ON st.member_id = m.member_id\n                INNER JOIN savings_accounts sa ON st.account_id = sa.account_id\n                WHERE st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'\n                ORDER BY st.transaction_date DESC\n                LIMIT $limit)\n                \n                UNION ALL\n                \n                (SELECT \n                    'loan_payment' as type,\n                    lr.repayment_id as id,\n                    CONCAT(m.first_name, ' ', m.last_name) as member_name,\n                    lr.amount,\n                    CONCAT('Loan Payment - ', l.loan_id) as description,\n                    lr.payment_date as transaction_date,\n                    'Confirmed' as status\n                FROM loan_repayments lr\n                INNER JOIN loans l ON lr.loan_id = l.loan_id\n                INNER JOIN members m ON l.member_id = m.member_id\n                ORDER BY lr.payment_date DESC\n                LIMIT $limit)\n                \n                ORDER BY transaction_date DESC\n                LIMIT $limit\n            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getRecentTransactions: " . $e->getMessage());
            return false;
        }
    }
    
    // ===================================================================
    // UTILITY METHODS
    // ===================================================================
    
    /**
     * Convert period string to SQL condition
     */
    private function getPeriodCondition($period, $date_field = 'application_date') {
        switch ($period) {
            case '1_month':
                return "$date_field >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            case '3_months':
                return "$date_field >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            case '6_months':
                return "$date_field >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
            case '1_year':
                return "$date_field >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            case '2_years':
                return "$date_field >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)";
            default:
                return "$date_field >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        }
    }
    
    /**
     * Export report data to CSV format
     */
    public function exportToCSV($data, $filename, $headers = []) {
        try {
            if (empty($data)) {
                return false;
            }
            
            // Set headers for download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Open output stream
            $output = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for proper Excel encoding
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write headers
            if (!empty($headers)) {
                fputcsv($output, $headers);
            } else if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
            }
            
            // Write data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            return true;
        } catch (Exception $e) {
            error_log("Error in exportToCSV: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get chart data in format suitable for Chart.js
     */
    public function getChartData($data, $label_key, $value_key, $chart_type = 'line') {
        try {
            $labels = [];
            $values = [];
            
            foreach ($data as $row) {
                $labels[] = $row[$label_key];
                $values[] = (float)$row[$value_key];
            }
            
            return [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $values,
                    'backgroundColor' => $this->getChartColors(count($values)),
                    'borderColor' => '#3B82F6',
                    'borderWidth' => 2,
                    'fill' => $chart_type === 'area'
                ]]
            ];
        } catch (Exception $e) {
            error_log("Error in getChartData: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate color palette for charts
     */
    private function getChartColors($count) {
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
            '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'
        ];
        
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $colors[$i % count($colors)];
        }
        
        return $result;
    }
}
