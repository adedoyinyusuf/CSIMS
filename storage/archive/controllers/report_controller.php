<?php
/**
 * Report Controller
 * 
 * Handles all report generation operations including member reports,
 * financial reports, loan reports, and system analytics.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utilities.php';

class ReportController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Generate member statistics report
     * 
     * @param string $start_date Start date for the report
     * @param string $end_date End date for the report
     * @return array Member statistics
     */
    public function getMemberReport($start_date = null, $end_date = null) {
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($start_date && $end_date) {
            $where_clause .= " AND m.created_at BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            $types = "ss";
        }
        
        // Total members by status
        $status_sql = "SELECT 
                        status,
                        COUNT(*) as count
                       FROM members m 
                       $where_clause 
                       GROUP BY status";
        
        $stmt = $this->conn->prepare($status_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $status_result = $stmt->get_result();
        
        $member_status = [];
        while ($row = $status_result->fetch_assoc()) {
            $member_status[$row['status']] = $row['count'];
        }
        $stmt->close();
        
        // Member registration trends (monthly)
        $trend_sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as registrations
                      FROM members m
                      $where_clause
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      ORDER BY month DESC
                      LIMIT 12";
        
        $stmt = $this->conn->prepare($trend_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $trend_result = $stmt->get_result();
        
        $registration_trends = [];
        while ($row = $trend_result->fetch_assoc()) {
            $registration_trends[] = $row;
        }
        $stmt->close();
        
        // Age distribution
        $age_sql = "SELECT 
                      CASE 
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 25 THEN 'Under 25'
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 25 AND 35 THEN '25-35'
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 36 AND 50 THEN '36-50'
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
                        ELSE 'Over 65'
                      END as age_group,
                      COUNT(*) as count
                    FROM members m
                    $where_clause AND dob IS NOT NULL
                    GROUP BY age_group
                    ORDER BY 
                      CASE age_group
                        WHEN 'Under 25' THEN 1
                        WHEN '25-35' THEN 2
                        WHEN '36-50' THEN 3
                        WHEN '51-65' THEN 4
                        WHEN 'Over 65' THEN 5
                      END";
        
        $stmt = $this->conn->prepare($age_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $age_result = $stmt->get_result();
        
        $age_distribution = [];
        while ($row = $age_result->fetch_assoc()) {
            $age_distribution[] = $row;
        }
        $stmt->close();
        
        return [
            'member_status' => $member_status,
            'registration_trends' => $registration_trends,
            'age_distribution' => $age_distribution,
            'total_members' => array_sum($member_status)
        ];
    }
    
    /**
     * Generate financial report
     * 
     * @param string $start_date Start date for the report
     * @param string $end_date End date for the report
     * @return array Financial statistics
     */
    public function getFinancialReport($start_date = null, $end_date = null) {
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($start_date && $end_date) {
            $where_clause .= " AND transaction_date BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            $types = "ss";
        }
        
        // Savings deposit statistics
        $contribution_sql = "SELECT 
                              COALESCE(SUM(st.amount), 0) as total_contributions,
                              COALESCE(COUNT(*), 0) as total_transactions,
                              COALESCE(AVG(st.amount), 0) as average_contribution
                            FROM savings_transactions st
                            $where_clause AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'";
        
        $stmt = $this->conn->prepare($contribution_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $contribution_stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Investment statistics
        $investment_sql = "SELECT 
                            COALESCE(SUM(amount), 0) as total_investments,
                            COALESCE(COUNT(*), 0) as total_investment_count,
                            COALESCE(SUM(expected_return), 0) as total_expected_returns
                          FROM investments i
                          $where_clause";
        
        $stmt = $this->conn->prepare($investment_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $investment_stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Loan statistics
        $loan_sql = "SELECT 
                      COALESCE(SUM(amount), 0) as total_loans,
                      COALESCE(COUNT(*), 0) as total_loan_count,
                      COALESCE(SUM(CASE WHEN status = 'Active' THEN amount ELSE 0 END), 0) as active_loans,
                      COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as paid_loans
                    FROM loans l
                    $where_clause";
        
        $stmt = $this->conn->prepare($loan_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $loan_stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Monthly financial trends
        $trend_sql = "SELECT 
                        DATE_FORMAT(transaction_date, '%Y-%m') as month,
                        'Savings' as type,
                        COALESCE(SUM(amount), 0) as amount
                      FROM savings_transactions st
                      $where_clause AND st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'
                      GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                      UNION ALL
                      SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        'Investments' as type,
                        COALESCE(SUM(amount), 0) as amount
                      FROM investments
                      $where_clause
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      UNION ALL
                      SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        'Loans' as type,
                        COALESCE(SUM(amount), 0) as amount
                      FROM loans
                      $where_clause
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      ORDER BY month DESC";
        
        $stmt = $this->conn->prepare($trend_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $trend_result = $stmt->get_result();
        
        $financial_trends = [];
        while ($row = $trend_result->fetch_assoc()) {
            $financial_trends[] = $row;
        }
        $stmt->close();
        
        return [
            'contributions' => $contribution_stats,
            'investments' => $investment_stats,
            'loans' => $loan_stats,
            'trends' => $financial_trends
        ];
    }
    
    /**
     * Generate loan performance report
     * 
     * @param string $start_date Start date for the report
     * @param string $end_date End date for the report
     * @return array Loan performance statistics
     */
    public function getLoanReport($start_date = null, $end_date = null) {
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($start_date && $end_date) {
            $where_clause .= " AND l.created_at BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            $types = "ss";
        }
        
        // Loan status distribution
        $status_sql = "SELECT 
                        status,
                        COUNT(*) as count,
                        COALESCE(SUM(amount), 0) as total_amount
                      FROM loans l
                      $where_clause
                      GROUP BY status";
        
        $stmt = $this->conn->prepare($status_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $status_result = $stmt->get_result();
        
        $loan_status = [];
        while ($row = $status_result->fetch_assoc()) {
            $loan_status[] = $row;
        }
        $stmt->close();
        
        // Loan amount ranges
        $range_sql = "SELECT 
                        CASE 
                          WHEN amount < 10000 THEN 'Under ₦10,000'
                          WHEN amount BETWEEN 10000 AND 50000 THEN '₦10,000 - ₦50,000'
                          WHEN amount BETWEEN 50001 AND 100000 THEN '₦50,001 - ₦100,000'
                          WHEN amount BETWEEN 100001 AND 500000 THEN '₦100,001 - ₦500,000'
                          ELSE 'Over ₦500,000'
                        END as amount_range,
                        COUNT(*) as count,
                        COALESCE(SUM(amount), 0) as total_amount
                      FROM loans l
                      $where_clause
                      GROUP BY amount_range
                      ORDER BY 
                        CASE amount_range
                          WHEN 'Under ₦10,000' THEN 1
                          WHEN '₦10,000 - ₦50,000' THEN 2
                          WHEN '₦50,001 - ₦100,000' THEN 3
                          WHEN '₦100,001 - ₦500,000' THEN 4
                          WHEN 'Over ₦500,000' THEN 5
                        END";
        
        $stmt = $this->conn->prepare($range_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $range_result = $stmt->get_result();
        
        $amount_ranges = [];
        while ($row = $range_result->fetch_assoc()) {
            $amount_ranges[] = $row;
        }
        $stmt->close();
        
        // Top borrowers
        $borrower_sql = "SELECT 
                          CONCAT(m.first_name, ' ', m.last_name) as member_name,
                          m.member_id,
                          COUNT(l.loan_id) as loan_count,
                          COALESCE(SUM(l.amount), 0) as total_borrowed,
                          COALESCE(SUM(CASE WHEN l.status = 'Active' THEN l.amount ELSE 0 END), 0) as active_amount
                        FROM loans l
                        JOIN members m ON l.member_id = m.member_id
                        $where_clause
                        GROUP BY l.member_id, m.first_name, m.last_name
                        ORDER BY total_borrowed DESC
                        LIMIT 10";
        
        $stmt = $this->conn->prepare($borrower_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $borrower_result = $stmt->get_result();
        
        $top_borrowers = [];
        while ($row = $borrower_result->fetch_assoc()) {
            $top_borrowers[] = $row;
        }
        $stmt->close();
        
        return [
            'loan_status' => $loan_status,
            'amount_ranges' => $amount_ranges,
            'top_borrowers' => $top_borrowers
        ];
    }
    
    /**
     * Generate system activity report
     * 
     * @param string $start_date Start date for the report
     * @param string $end_date End date for the report
     * @return array System activity statistics
     */
    public function getActivityReport($start_date = null, $end_date = null) {
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($start_date && $end_date) {
            $where_clause .= " AND created_at BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            $types = "ss";
        }
        
        // Recent activities summary
        $activities = [];
        
        // Member registrations
        $member_sql = "SELECT COUNT(*) as count FROM members m $where_clause";
        $stmt = $this->conn->prepare($member_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $activities['new_members'] = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // Savings deposits (replacing legacy contributions)
        $contrib_sql = "SELECT COUNT(*) as count FROM savings_transactions st WHERE st.transaction_type = 'Deposit' AND st.transaction_status = 'Completed'";
        if ($start_date && $end_date) {
            $contrib_sql .= " AND st.transaction_date BETWEEN ? AND ?";
            $stmt = $this->conn->prepare($contrib_sql);
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            $stmt = $this->conn->prepare($contrib_sql);
        }
        $stmt->execute();
        $activities['new_contributions'] = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // Loan applications
        $loan_sql = "SELECT COUNT(*) as count FROM loans l $where_clause";
        $stmt = $this->conn->prepare($loan_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $activities['new_loans'] = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // Investment records
        $invest_sql = "SELECT COUNT(*) as count FROM investments i $where_clause";
        $stmt = $this->conn->prepare($invest_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $activities['new_investments'] = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // Messages/Notifications
        $msg_sql = "SELECT COUNT(*) as count FROM messages m $where_clause";
        $stmt = $this->conn->prepare($msg_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $activities['new_messages'] = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        return $activities;
    }
    
    /**
     * Export report data to CSV format
     * 
     * @param array $data Report data
     * @param string $filename Filename for the export
     * @param array $headers Column headers
     * @return string CSV content
     */
    public function exportToCSV($data, $filename, $headers) {
        $csv_content = "";
        
        // Add headers
        $csv_content .= implode(',', $headers) . "\n";
        
        // Add data rows
        foreach ($data as $row) {
            $csv_row = [];
            foreach ($row as $value) {
                // Escape commas and quotes in CSV
                $csv_row[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $csv_content .= implode(',', $csv_row) . "\n";
        }
        
        return $csv_content;
    }
    
    /**
     * Get available report types
     * 
     * @return array List of available reports
     */
    public function getReportTypes() {
        return [
            'member' => 'Member Statistics Report',
            'financial' => 'Financial Summary Report',
            'loan' => 'Loan Performance Report',
            'activity' => 'System Activity Report'
        ];
    }
    
    /**
     * Get date range presets
     * 
     * @return array List of date range presets
     */
    public function getDateRangePresets() {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_quarter' => 'This Quarter',
            'this_year' => 'This Year',
            'custom' => 'Custom Range'
        ];
    }
}