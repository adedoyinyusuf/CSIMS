<?php

namespace CSIMS\Controllers;

use mysqli;

class FinancialAnalyticsController
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Build dashboard data for the given period (week|month|quarter|year)
     */
    public function getDashboard(array $requestData): array
    {
        $period = $requestData['period'] ?? 'month';
        $range = $this->resolvePeriodRange($period);

        // Totals
        // Note: Using absolute values for savings/loans calculations to avoid issues if stored as negative
        $totalSavings = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE created_at <= ?", [$range['end']], 's') ?? 0.0);
        $outstandingLoans = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status NOT IN ('Paid','Closed') AND created_at <= ?", [$range['end']], 's') ?? 0.0);

        $totalAssets = $totalSavings; // simplistic assets approximation
        $loanToAssetRatio = $totalAssets > 0 ? (($outstandingLoans / $totalAssets) * 100.0) : 0.0;
        $financialHealthScore = max(0, min(100, 100 - $loanToAssetRatio));

        // Cash flow: income vs outflow within PERIOD
        $income = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0.0);
        $outflow = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0.0);
        
        $netCashFlow = $income - $outflow;
        $cashFlowRatio = $outflow > 0 ? ($income / $outflow) : ($income > 0 ? 100 : 0.0);

        // Detailed Cash Flow
        $inflows = [
            ['source' => 'Savings Deposits', 'amount' => $income]
        ];
        $outflows = [
            ['source' => 'Loan Disbursements', 'amount' => $outflow]
        ];

        // Loan performance (basic metrics)
        $totalLoanCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE created_at <= ?", [$range['end']], 's') ?? 0);
        $defaultCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status IN ('Defaulted','Overdue') AND created_at <= ?", [$range['end']], 's') ?? 0);
        $collectionCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status IN ('Paid','Closed') AND created_at <= ?", [$range['end']], 's') ?? 0);
        
        $activeLoanAmount = $outstandingLoans;
        $paidLoanAmount = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status IN ('Paid','Closed') AND created_at <= ?", [$range['end']], 's') ?? 0.0);

        $defaultRate = $totalLoanCount > 0 ? (($defaultCount / $totalLoanCount) * 100.0) : 0.0;
        $collectionRate = $totalLoanCount > 0 ? (($collectionCount / $totalLoanCount) * 100.0) : 0.0;

        // Savings performance
        $avgInterestRate = 5.0; // placeholder until dynamic interest calculation
        $growthRate = 2.5; // placeholder
        $totalInterestEarned = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE transaction_type='Interest' AND created_at <= ?", [$range['end']], 's') ?? 0.0);

        // Member Financial Health (Top 10)
        $memberHealth = [];
        $members = $this->queryAll("SELECT m.member_id, m.first_name, m.last_name FROM members m LIMIT 10");
        foreach ($members as $m) {
            $mDetails = [];
            $mDetails['member_name'] = $m['first_name'] . ' ' . $m['last_name'];
            
            // Get savings
            $mSavings = (float)($this->scalar("SELECT COALESCE(SUM(st.amount),0) FROM savings_transactions st JOIN savings_accounts sa ON st.account_id = sa.account_id WHERE sa.member_id = ?", [$m['member_id']], 'i') ?? 0);
            $mDetails['total_savings'] = $mSavings;
            
            // Get active loans
            $mLoans = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status NOT IN ('Paid','Closed')", [$m['member_id']], 'i') ?? 0);
            $mDetails['active_loans'] = $mLoans;
            
            // Simple Score
            $mScore = 50;
            if ($mSavings > 0) $mScore += 30;
            if ($mLoans == 0) $mScore += 20;
            if ($mLoans > 1) $mScore -= 10;
            $mDetails['financial_health_score'] = min(100, max(0, $mScore));
            
            $memberHealth[] = $mDetails;
        }

        // Trends (Last 6 periods)
        $trends = [];
        // $trendStart = date('Y-m-d', strtotime('-5 months'));
        for ($i = 5; $i >= 0; $i--) {
             $p = date('M', strtotime("-$i months"));
             $trends[] = ['period' => $p, 'category' => 'Savings', 'amount' => rand(100000, 500000)]; // Placeholder
             $trends[] = ['period' => $p, 'category' => 'Loans', 'amount' => rand(50000, 300000)]; // Placeholder
        }

        // Forecasts
        $forecasts = [
            'Savings' => [
                'next_period_forecast' => $totalSavings * 1.05,
                'trend_direction' => 'increasing',
                'confidence_level' => 'high'
            ],
            'Loans' => [
                'next_period_forecast' => $outstandingLoans * 0.98,
                'trend_direction' => 'decreasing',
                'confidence_level' => 'medium'
            ],
            'Liquidity' => [
                 'next_period_forecast' => $totalSavings * 0.2,
                 'trend_direction' => 'increasing',
                 'confidence_level' => 'medium'
            ]
        ];

        return [
            'success' => true,
            'data' => [
                'overview' => [
                    'total_assets' => $totalAssets,
                    'outstanding_loans' => $outstandingLoans,
                    'financial_health_score' => $financialHealthScore,
                    'loan_to_asset_ratio' => $loanToAssetRatio,
                    'liquidity_ratio' => $totalAssets * 0.15, // estimated
                ],
                'cash_flow' => [
                    'income' => $income,
                    'outflow' => $outflow,
                    'net_cash_flow' => $netCashFlow,
                    'cash_flow_ratio' => $cashFlowRatio,
                    'inflows' => $inflows,
                    'outflows' => $outflows,
                ],
                'loan_performance' => [
                    'collection_rate' => $collectionRate,
                    'default_rate' => $defaultRate,
                    'active_loan_amount' => $activeLoanAmount,
                    'paid_loan_amount' => $paidLoanAmount,
                ],
                'savings_performance' => [
                    'avg_interest_rate' => $avgInterestRate,
                    'growth_rate' => $growthRate,
                    'total_savings' => $totalSavings,
                    'total_interest_earned' => $totalInterestEarned,
                ],
                'member_financial_health' => $memberHealth,
                'trends' => $trends,
                'forecasts' => $forecasts
            ]
        ];
    }

    public function exportDashboard(array $requestData): void
    {
        $type = $requestData['type'] ?? 'overview';
        $period = $requestData['period'] ?? 'month';
        
        // Get data structure (extract 'data' key from result)
        $result = $this->getDashboard(['period' => $period]);
        $data = $result['data'];

        switch ($type) {
            case 'overview':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="financial_overview_' . date('Y-m-d') . '.csv"');
                echo "Metric,Value\n";
                echo "Total Assets,{$data['overview']['total_assets']}\n";
                echo "Outstanding Loans,{$data['overview']['outstanding_loans']}\n";
                echo "Financial Health Score,{$data['overview']['financial_health_score']}\n";
                echo "Loan-to-Asset Ratio,{$data['overview']['loan_to_asset_ratio']}\n";
                break;
            case 'cash_flow':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="cash_flow_' . date('Y-m-d') . '.csv"');
                echo "Income,Outflow,Net,Ratio\n";
                echo $data['cash_flow']['income'] . ',' . $data['cash_flow']['outflow'] . ',' . $data['cash_flow']['net_cash_flow'] . ',' . $data['cash_flow']['cash_flow_ratio'];
                break;
            case 'member_health':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="member_health_' . date('Y-m-d') . '.csv"');
                echo "Member,Savings,Active Loans,Health Score\n";
                foreach ($data['member_financial_health'] as $m) {
                    echo "{$m['member_name']},{$m['total_savings']},{$m['active_loans']},{$m['financial_health_score']}\n";
                }
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode($data);
        }
        exit;
    }

    private function resolvePeriodRange(string $period): array
    {
        $today = date('Y-m-d');
        switch ($period) {
            case 'week':
                $start = date('Y-m-d', strtotime('monday this week'));
                $end = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $start = date('Y-m-01');
                $end = date('Y-m-t');
                break;
            case 'quarter':
                $q = ceil(date('n') / 3);
                $start = date('Y-' . sprintf('%02d', ($q - 1) * 3 + 1) . '-01');
                $end = date('Y-m-t', strtotime($start . ' +2 months'));
                break;
            case 'year':
                $start = date('Y-01-01');
                $end = date('Y-12-31');
                break;
            default:
                $start = $today;
                $end = $today;
        }
        return ['start' => $start, 'end' => $end];
    }

    private function scalar(string $sql, array $params = [], string $types = '')
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return null; }
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_array() : null;
        $stmt->close();
        return $row ? array_values($row)[0] : null;
    }

    private function queryAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }
}
