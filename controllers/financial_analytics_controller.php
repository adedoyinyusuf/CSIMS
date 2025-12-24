<?php
require_once __DIR__ . '/../includes/db.php';

class FinancialAnalyticsController
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Build dashboard data for the given period (week|month|quarter|year)
     */
    public function getFinancialDashboard(string $period = 'month'): array
    {
        $range = $this->resolvePeriodRange($period);

        // Totals
        $totalSavings = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0.0);
        $outstandingLoans = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status NOT IN ('Paid','Closed') AND created_at <= ?", [$range['end']], 's') ?? 0.0);

        $totalAssets = $totalSavings; // simplistic assets approximation
        $loanToAssetRatio = $totalAssets > 0 ? (($outstandingLoans / $totalAssets) * 100.0) : 0.0;
        $financialHealthScore = max(0, min(100, 100 - $loanToAssetRatio));

        // Cash flow: income vs outflow
        $income = $totalSavings;
        $outflow = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0.0);
        $netCashFlow = $income - $outflow;
        $cashFlowRatio = $outflow > 0 ? ($income / $outflow) : 0.0;

        // Loan performance (basic metrics)
        $totalLoanCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0);
        $defaultCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status IN ('Defaulted','Overdue') AND created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0);
        $collectionCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status IN ('Paid','Closed') AND created_at BETWEEN ? AND ?", [$range['start'], $range['end']], 'ss') ?? 0);
        $defaultRate = $totalLoanCount > 0 ? (($defaultCount / $totalLoanCount) * 100.0) : 0.0;
        $collectionRate = $totalLoanCount > 0 ? (($collectionCount / $totalLoanCount) * 100.0) : 0.0;

        // Savings performance
        $avgInterestRate = 3.5; // placeholder
        $growthRate = $totalSavings > 0 ? 5.0 : 0.0; // placeholder

        // Build chart-ready arrays (minimal placeholders)
        $cashFlowChart = [
            'labels' => ['Income', 'Outflow'],
            'income' => [$income],
            'outflow' => [$outflow],
        ];
        $loanPerformanceChart = [
            'labels' => ['Collected', 'Defaulted'],
            'data' => [$collectionCount, $defaultCount],
        ];
        $savingsChart = [
            'labels' => ['Savings'],
            'data' => [$totalSavings],
        ];

        return [
            'overview' => [
                'total_assets' => $totalAssets,
                'outstanding_loans' => $outstandingLoans,
                'financial_health_score' => $financialHealthScore,
                'loan_to_asset_ratio' => $loanToAssetRatio,
                'liquidity_ratio' => $totalSavings, // simple proxy
            ],
            'cash_flow' => [
                'income' => $income,
                'outflow' => $outflow,
                'net_cash_flow' => $netCashFlow,
                'cash_flow_ratio' => $cashFlowRatio,
                'chart' => $cashFlowChart,
            ],
            'loan_performance' => [
                'collection_rate' => $collectionRate,
                'default_rate' => $defaultRate,
                'chart' => $loanPerformanceChart,
            ],
            'savings_performance' => [
                'avg_interest_rate' => $avgInterestRate,
                'growth_rate' => $growthRate,
                'chart' => $savingsChart,
            ],
        ];
    }

    public function exportAnalytics(array $data, string $type): void
    {
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
                header('Content-Type: application/json');
                echo json_encode($data['savings_performance']);
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode($data);
        }
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
}
?>