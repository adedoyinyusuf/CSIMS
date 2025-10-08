<?php
/**
 * Financial Analytics Controller
 * 
 * Enhanced financial analytics and forecasting for NPC CTLStaff Loan Society
 * Provides advanced financial insights, trends analysis, and predictive analytics
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utilities.php';

class FinancialAnalyticsController {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Get comprehensive financial dashboard data
     * 
     * @param string $period Period for analysis (month, quarter, year)
     * @return array Financial dashboard metrics
     */
    public function getFinancialDashboard($period = 'month') {
        $date_filter = $this->getDateFilter($period);
        
        return [
            'overview' => $this->getFinancialOverview($date_filter),
            'cash_flow' => $this->getCashFlowAnalysis($date_filter),
            'loan_performance' => $this->getLoanPerformanceMetrics($date_filter),
            'investment_returns' => $this->getInvestmentReturns($date_filter),
            'member_financial_health' => $this->getMemberFinancialHealth(),
            'trends' => $this->getFinancialTrends($period),
            'forecasts' => $this->getFinancialForecasts($period)
        ];
    }
    
    /**
     * Get financial overview metrics
     */
    private function getFinancialOverview($date_filter) {
        $sql = "SELECT 
                    -- Total Assets
                    (SELECT COALESCE(SUM(amount), 0) FROM contributions WHERE {$date_filter['where']}) as total_contributions,
                    (SELECT COALESCE(SUM(amount), 0) FROM investments WHERE status = 'Active' AND {$date_filter['where']}) as active_investments,
                    (SELECT COALESCE(SUM(amount - COALESCE((SELECT SUM(amount) FROM loan_repayments WHERE loan_id = loans.loan_id), 0)), 0) 
                     FROM loans WHERE status IN ('Approved', 'Disbursed') AND {$date_filter['where']}) as outstanding_loans,
                    
                    -- Liquidity Metrics
                    (SELECT COALESCE(SUM(amount), 0) FROM contributions WHERE {$date_filter['where']}) - 
                    (SELECT COALESCE(SUM(amount), 0) FROM loans WHERE status IN ('Approved', 'Disbursed') AND {$date_filter['where']}) as liquidity_ratio,
                    
                    -- Growth Metrics
                    (SELECT COUNT(*) FROM members WHERE {$date_filter['where']}) as new_members,
                    (SELECT COALESCE(AVG(amount), 0) FROM contributions WHERE {$date_filter['where']}) as avg_contribution";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($date_filter['params'])) {
            $stmt->bind_param(str_repeat('s', count($date_filter['params']) * 6), 
                            ...array_merge(
                                $date_filter['params'], $date_filter['params'], $date_filter['params'],
                                $date_filter['params'], $date_filter['params'], $date_filter['params']
                            ));
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate derived metrics
        $total_assets = $result['total_contributions'] + $result['active_investments'];
        $loan_to_asset_ratio = $total_assets > 0 ? ($result['outstanding_loans'] / $total_assets) * 100 : 0;
        
        $metrics = array_merge($result, [
            'total_assets' => $total_assets,
            'loan_to_asset_ratio' => $loan_to_asset_ratio
        ]);
        
        return array_merge($metrics, [
            'financial_health_score' => $this->calculateFinancialHealthScore($metrics)
        ]);
    }
    
    /**
     * Get cash flow analysis
     */
    private function getCashFlowAnalysis($date_filter) {
        // Cash inflows
        $inflow_sql = "SELECT 
                        'Contributions' as source,
                        COALESCE(SUM(amount), 0) as amount,
                        COUNT(*) as transaction_count
                       FROM contributions 
                       WHERE {$date_filter['where']}
                       UNION ALL
                       SELECT 
                        'Loan Payments' as source,
                        COALESCE(SUM(amount), 0) as amount,
                        COUNT(*) as transaction_count
                       FROM loan_repayments 
                       WHERE {$date_filter['where']}
                       UNION ALL
                       SELECT 
                        'Investment Returns' as source,
                        COALESCE(SUM(expected_return), 0) as amount,
                        COUNT(*) as transaction_count
                       FROM investments 
                       WHERE status = 'Matured' AND {$date_filter['where']}";
        
        $stmt = $this->conn->prepare($inflow_sql);
        if (!empty($date_filter['params'])) {
            $stmt->bind_param(str_repeat('s', count($date_filter['params']) * 3), 
                            ...array_merge($date_filter['params'], $date_filter['params'], $date_filter['params']));
        }
        $stmt->execute();
        $inflows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Cash outflows
        $outflow_sql = "SELECT 
                         'Loan Disbursements' as source,
                         COALESCE(SUM(amount), 0) as amount,
                         COUNT(*) as transaction_count
                        FROM loans 
                        WHERE status IN ('Approved', 'Disbursed', 'Paid') AND {$date_filter['where']}
                        UNION ALL
                        SELECT 
                         'New Investments' as source,
                         COALESCE(SUM(amount), 0) as amount,
                         COUNT(*) as transaction_count
                        FROM investments 
                        WHERE {$date_filter['where']}";
        
        $stmt = $this->conn->prepare($outflow_sql);
        if (!empty($date_filter['params'])) {
            $stmt->bind_param(str_repeat('s', count($date_filter['params']) * 2), 
                            ...array_merge($date_filter['params'], $date_filter['params']));
        }
        $stmt->execute();
        $outflows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $total_inflow = array_sum(array_column($inflows, 'amount'));
        $total_outflow = array_sum(array_column($outflows, 'amount'));
        
        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'total_inflow' => $total_inflow,
            'total_outflow' => $total_outflow,
            'net_cash_flow' => $total_inflow - $total_outflow,
            'cash_flow_ratio' => $total_outflow > 0 ? ($total_inflow / $total_outflow) : 0
        ];
    }
    
    /**
     * Get loan performance metrics
     */
    private function getLoanPerformanceMetrics($date_filter) {
        $sql = "SELECT 
                    COUNT(*) as total_loans,
                    COALESCE(SUM(amount), 0) as total_loan_amount,
                    COALESCE(SUM(CASE WHEN status IN ('Approved', 'Disbursed') THEN amount ELSE 0 END), 0) as active_loan_amount,
                    COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as paid_loan_amount,
                    COALESCE(AVG(interest_rate), 0) as avg_interest_rate,
                    COALESCE(AVG(DATEDIFF(CURDATE(), created_at)), 0) as avg_loan_age_days,
                    
                    -- Delinquency metrics
                    (SELECT COUNT(*) FROM loans l 
                     WHERE l.status IN ('Approved', 'Disbursed')
                     AND DATEDIFF(CURDATE(), l.created_at) > (l.term * 30)
                     AND {$date_filter['where']}) as overdue_loans,
                     
                    -- Payment performance
                    (SELECT COALESCE(SUM(lp.amount), 0) FROM loan_repayments lp 
                     JOIN loans l ON lp.loan_id = l.loan_id 
                     WHERE lp.{$date_filter['where']}) as total_payments_received
                     
                FROM loans 
                WHERE {$date_filter['where']}";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($date_filter['params'])) {
            $stmt->bind_param(str_repeat('s', count($date_filter['params']) * 3), 
                            ...array_merge($date_filter['params'], $date_filter['params'], $date_filter['params']));
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate derived metrics
        $default_rate = $result['total_loans'] > 0 ? ($result['overdue_loans'] / $result['total_loans']) * 100 : 0;
        $collection_rate = $result['total_loan_amount'] > 0 ? ($result['total_payments_received'] / $result['total_loan_amount']) * 100 : 0;
        
        return array_merge($result, [
            'default_rate' => $default_rate,
            'collection_rate' => $collection_rate,
            'loan_utilization' => $result['total_loan_amount'] > 0 ? ($result['active_loan_amount'] / $result['total_loan_amount']) * 100 : 0
        ]);
    }
    
    /**
     * Get investment returns analysis
     */
    private function getInvestmentReturns($date_filter) {
        $sql = "SELECT 
                    COUNT(*) as total_investments,
                    COALESCE(SUM(amount), 0) as total_invested,
                    COALESCE(SUM(expected_return), 0) as total_expected_returns,
                    COALESCE(SUM(CASE WHEN status = 'Matured' THEN expected_return ELSE 0 END), 0) as realized_returns,
                    COALESCE(AVG(CASE WHEN amount > 0 THEN (expected_return / amount) * 100 ELSE 0 END), 0) as avg_roi_percentage,
                    COALESCE(AVG(DATEDIFF(maturity_date, investment_date)), 0) as avg_investment_period_days
                FROM investments 
                WHERE {$date_filter['where']}";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($date_filter['params'])) {
            $stmt->bind_param(str_repeat('s', count($date_filter['params'])), ...$date_filter['params']);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate performance metrics
        $unrealized_returns = $result['total_expected_returns'] - $result['realized_returns'];
        $realization_rate = $result['total_expected_returns'] > 0 ? ($result['realized_returns'] / $result['total_expected_returns']) * 100 : 0;
        
        return array_merge($result, [
            'unrealized_returns' => $unrealized_returns,
            'realization_rate' => $realization_rate,
            'investment_efficiency' => $result['total_invested'] > 0 ? ($result['realized_returns'] / $result['total_invested']) * 100 : 0
        ]);
    }
    
    /**
     * Get member financial health analysis
     */
    private function getMemberFinancialHealth() {
        $sql = "SELECT 
                    m.member_id,
                    CONCAT(m.first_name, ' ', m.last_name) as member_name,
                    COALESCE(SUM(c.amount), 0) as total_contributions,
                    COALESCE(SUM(l.amount), 0) as total_loans,
                    COALESCE(SUM(CASE WHEN l.status IN ('Approved', 'Disbursed') THEN l.amount ELSE 0 END), 0) as active_loans,
                    COALESCE(SUM(lp.amount), 0) as total_payments,
                    COUNT(DISTINCT l.loan_id) as loan_count,
                    
                    -- Financial health score calculation
                    CASE 
                        WHEN COALESCE(SUM(l.amount), 0) = 0 THEN 100
                        WHEN COALESCE(SUM(c.amount), 0) = 0 THEN 0
                        ELSE LEAST(100, (COALESCE(SUM(c.amount), 0) / COALESCE(SUM(l.amount), 1)) * 50 + 
                                       (COALESCE(SUM(lp.amount), 0) / COALESCE(SUM(l.amount), 1)) * 50)
                    END as financial_health_score
                    
                FROM members m
                LEFT JOIN contributions c ON m.member_id = c.member_id
                LEFT JOIN loans l ON m.member_id = l.member_id
                LEFT JOIN loan_repayments lp ON l.loan_id = lp.loan_id
                GROUP BY m.member_id, m.first_name, m.last_name
                HAVING total_contributions > 0 OR total_loans > 0
                ORDER BY financial_health_score DESC
                LIMIT 20";
        
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get financial trends over time
     */
    private function getFinancialTrends($period) {
        $date_format = $period === 'year' ? '%Y' : ($period === 'quarter' ? '%Y-Q%q' : '%Y-%m');
        
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '$date_format') as period,
                    'Contributions' as category,
                    COALESCE(SUM(amount), 0) as amount,
                    COUNT(*) as transaction_count
                FROM contributions 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '$date_format')
                
                UNION ALL
                
                SELECT 
                    DATE_FORMAT(created_at, '$date_format') as period,
                    'Loans' as category,
                    COALESCE(SUM(amount), 0) as amount,
                    COUNT(*) as transaction_count
                FROM loans 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '$date_format')
                
                UNION ALL
                
                SELECT 
                    DATE_FORMAT(created_at, '$date_format') as period,
                    'Investments' as category,
                    COALESCE(SUM(amount), 0) as amount,
                    COUNT(*) as transaction_count
                FROM investments 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '$date_format')
                
                ORDER BY period DESC, category";
        
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Generate financial forecasts
     */
    private function getFinancialForecasts($period) {
        // Simple linear regression for forecasting
        $trends = $this->getFinancialTrends($period);
        
        $forecasts = [];
        $categories = ['Contributions', 'Loans', 'Investments'];
        
        foreach ($categories as $category) {
            $category_data = array_filter($trends, function($item) use ($category) {
                return $item['category'] === $category;
            });
            
            if (count($category_data) >= 3) {
                $amounts = array_column($category_data, 'amount');
                $forecast = $this->calculateLinearForecast($amounts);
                
                $forecasts[$category] = [
                    'next_period_forecast' => $forecast,
                    'trend_direction' => $this->getTrendDirection($amounts),
                    'confidence_level' => $this->calculateConfidenceLevel($amounts)
                ];
            }
        }
        
        return $forecasts;
    }
    
    /**
     * Calculate financial health score
     */
    private function calculateFinancialHealthScore($metrics) {
        $score = 0;
        
        // Liquidity component (30%)
        if ($metrics['liquidity_ratio'] > 0) {
            $score += 30;
        } elseif ($metrics['liquidity_ratio'] > -50000) {
            $score += 15;
        }
        
        // Growth component (25%)
        if ($metrics['new_members'] > 10) {
            $score += 25;
        } elseif ($metrics['new_members'] > 5) {
            $score += 15;
        } elseif ($metrics['new_members'] > 0) {
            $score += 10;
        }
        
        // Asset utilization (25%)
        if (isset($metrics['loan_to_asset_ratio']) && $metrics['loan_to_asset_ratio'] > 60 && $metrics['loan_to_asset_ratio'] < 80) {
            $score += 25;
        } elseif (isset($metrics['loan_to_asset_ratio']) && $metrics['loan_to_asset_ratio'] > 40 && $metrics['loan_to_asset_ratio'] < 90) {
            $score += 15;
        }
        
        // Contribution stability (20%)
        if ($metrics['avg_contribution'] > 10000) {
            $score += 20;
        } elseif ($metrics['avg_contribution'] > 5000) {
            $score += 15;
        } elseif ($metrics['avg_contribution'] > 0) {
            $score += 10;
        }
        
        return min(100, $score);
    }
    
    /**
     * Get date filter for SQL queries
     */
    private function getDateFilter($period) {
        switch ($period) {
            case 'week':
                return [
                    'where' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
                    'params' => []
                ];
            case 'month':
                return [
                    'where' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
                    'params' => []
                ];
            case 'quarter':
                return [
                    'where' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)',
                    'params' => []
                ];
            case 'year':
                return [
                    'where' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
                    'params' => []
                ];
            default:
                return [
                    'where' => '1=1',
                    'params' => []
                ];
        }
    }
    
    /**
     * Calculate linear forecast
     */
    private function calculateLinearForecast($data) {
        $n = count($data);
        if ($n < 2) return end($data);
        
        $sum_x = array_sum(range(1, $n));
        $sum_y = array_sum($data);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $data[$i];
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        return $slope * ($n + 1) + $intercept;
    }
    
    /**
     * Get trend direction
     */
    private function getTrendDirection($data) {
        if (count($data) < 2) return 'stable';
        
        $first_half = array_slice($data, 0, floor(count($data) / 2));
        $second_half = array_slice($data, floor(count($data) / 2));
        
        $first_avg = array_sum($first_half) / count($first_half);
        $second_avg = array_sum($second_half) / count($second_half);
        
        $change_percent = $first_avg > 0 ? (($second_avg - $first_avg) / $first_avg) * 100 : 0;
        
        if ($change_percent > 10) return 'increasing';
        if ($change_percent < -10) return 'decreasing';
        return 'stable';
    }
    
    /**
     * Calculate confidence level for forecasts
     */
    private function calculateConfidenceLevel($data) {
        if (count($data) < 3) return 'low';
        
        $mean = array_sum($data) / count($data);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $data)) / count($data);
        
        $coefficient_of_variation = $mean > 0 ? sqrt($variance) / $mean : 1;
        
        if ($coefficient_of_variation < 0.2) return 'high';
        if ($coefficient_of_variation < 0.5) return 'medium';
        return 'low';
    }
    
    /**
     * Export financial analytics to CSV
     */
    public function exportAnalytics($data, $type) {
        $filename = "financial_analytics_{$type}_" . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        switch ($type) {
            case 'overview':
                fputcsv($output, ['Metric', 'Value']);
                foreach ($data['overview'] as $key => $value) {
                    fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
                }
                break;
                
            case 'cash_flow':
                fputcsv($output, ['Type', 'Source', 'Amount', 'Transaction Count']);
                foreach ($data['cash_flow']['inflows'] as $inflow) {
                    fputcsv($output, ['Inflow', $inflow['source'], $inflow['amount'], $inflow['transaction_count']]);
                }
                foreach ($data['cash_flow']['outflows'] as $outflow) {
                    fputcsv($output, ['Outflow', $outflow['source'], $outflow['amount'], $outflow['transaction_count']]);
                }
                break;
                
            case 'member_health':
                fputcsv($output, ['Member Name', 'Total Contributions', 'Total Loans', 'Active Loans', 'Financial Health Score']);
                foreach ($data['member_financial_health'] as $member) {
                    fputcsv($output, [
                        $member['member_name'],
                        $member['total_contributions'],
                        $member['total_loans'],
                        $member['active_loans'],
                        $member['financial_health_score']
                    ]);
                }
                break;
        }
        
        fclose($output);
        exit;
    }
}
?>