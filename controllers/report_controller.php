<?php
require_once __DIR__ . '/../includes/db.php';

class ReportController
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getReportTypes(): array
    {
        return [
            'member' => 'Member Analytics',
            'financial' => 'Financial Summary',
            'loan' => 'Loan Performance',
            'activity' => 'System Activity',
        ];
    }

    public function getDateRangePresets(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_quarter' => 'This Quarter',
            'this_year' => 'This Year',
            'custom' => 'Custom',
        ];
    }

    public function getMemberReport(?string $start = null, ?string $end = null): array
    {
        $dateFilter = $this->buildDateFilter('members', 'created_at', $start, $end);
        $totalMembers = $this->scalar("SELECT COUNT(*) AS c FROM members") ?? 0;

        // Status distribution
        $statusRows = $this->rows("SELECT status, COUNT(*) AS c FROM members GROUP BY status");
        $memberStatus = [];
        foreach ($statusRows as $r) { $memberStatus[$r['status'] ?? 'Unknown'] = (int)($r['c'] ?? 0); }

        // Registration trends (daily counts within range)
        $registrationTrends = $this->rows("SELECT DATE(created_at) AS day, COUNT(*) AS count FROM members {$dateFilter['where']} GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 12", $dateFilter['params'], $dateFilter['types']);

        // Age distribution (if DOB exists)
        $ageDistribution = [];
        $ageRows = $this->rows("SELECT YEAR(CURDATE()) - YEAR(date_of_birth) AS age FROM members WHERE date_of_birth IS NOT NULL");
        if (!empty($ageRows)) {
            $buckets = ['<25' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55+' => 0];
            foreach ($ageRows as $ar) {
                $age = (int)($ar['age'] ?? 0);
                if ($age < 25) $buckets['<25']++; elseif ($age <= 34) $buckets['25-34']++; elseif ($age <= 44) $buckets['35-44']++; elseif ($age <= 54) $buckets['45-54']++; else $buckets['55+']++;
            }
            foreach ($buckets as $label => $count) { $ageDistribution[] = ['label' => $label, 'count' => $count]; }
        }

        // New metrics in range
        $newMembers = $this->scalar("SELECT COUNT(*) AS c FROM members {$dateFilter['where']}", $dateFilter['params'], $dateFilter['types']) ?? 0;
        $newContributions = 0; // placeholder: requires savings/transactions table
        $newLoans = $this->scalar("SELECT COUNT(*) AS c FROM loans {$this->buildDateFilter('loans','created_at',$start,$end)['where']}") ?? 0;
        $newInvestments = $this->scalar("SELECT COUNT(*) AS c FROM investments {$this->buildDateFilter('investments','created_at',$start,$end)['where']}") ?? 0;

        // Financial summary sections
        $totalContributions = $this->scalar("SELECT COALESCE(SUM(amount),0) AS s FROM savings_transactions") ?? 0;
        $totalContributionTx = $this->scalar("SELECT COUNT(*) AS c FROM savings_transactions") ?? 0;
        $avgContribution = $totalContributionTx > 0 ? ($totalContributions / $totalContributionTx) : 0;

        $totalInvestments = $this->scalar("SELECT COALESCE(SUM(amount),0) AS s FROM investments") ?? 0;
        $investmentCount = $this->scalar("SELECT COUNT(*) AS c FROM investments") ?? 0;
        $expectedReturns = $this->scalar("SELECT COALESCE(SUM(expected_return),0) AS s FROM investments") ?? 0;

        $totalLoans = $this->scalar("SELECT COALESCE(SUM(amount),0) AS s FROM loans") ?? 0;
        $loanCount = $this->scalar("SELECT COUNT(*) AS c FROM loans") ?? 0;

        // Loan breakdowns
        $loanStatus = $this->rows("SELECT status AS name, COUNT(*) AS count FROM loans GROUP BY status");
        $amountRanges = [
            ['range' => '0-100k', 'count' => (int)$this->scalar("SELECT COUNT(*) FROM loans WHERE amount <= 100000")],
            ['range' => '100k-500k', 'count' => (int)$this->scalar("SELECT COUNT(*) FROM loans WHERE amount > 100000 AND amount <= 500000")],
            ['range' => '500k-1M', 'count' => (int)$this->scalar("SELECT COUNT(*) FROM loans WHERE amount > 500000 AND amount <= 1000000")],
            ['range' => '1M+', 'count' => (int)$this->scalar("SELECT COUNT(*) FROM loans WHERE amount > 1000000")],
        ];
        $topBorrowers = $this->rows("SELECT m.member_id, CONCAT(m.first_name,' ',m.last_name) AS name, COALESCE(SUM(l.amount),0) AS total_amount FROM loans l LEFT JOIN members m ON l.member_id = m.member_id GROUP BY l.member_id ORDER BY total_amount DESC LIMIT 5");

        return [
            'total_members' => (int)$totalMembers,
            'member_status' => $memberStatus,
            'registration_trends' => $registrationTrends,
            'age_distribution' => $ageDistribution,
            'contributions' => [
                'total_contributions' => (float)$totalContributions,
                'total_transactions' => (int)$totalContributionTx,
                'average_contribution' => (float)$avgContribution,
            ],
            'investments' => [
                'total_investments' => (float)$totalInvestments,
                'total_investment_count' => (int)$investmentCount,
                'total_expected_returns' => (float)$expectedReturns,
            ],
            'loans' => [
                'total_loans' => (float)$totalLoans,
                'total_loan_count' => (int)$loanCount,
            ],
            'loan_status' => $loanStatus,
            'amount_ranges' => $amountRanges,
            'top_borrowers' => $topBorrowers,
            'new_members' => (int)$newMembers,
            'new_contributions' => (int)$newContributions,
            'new_loans' => (int)$newLoans,
            'new_investments' => (int)$newInvestments,
        ];
    }

    public function getFinancialReport(?string $start = null, ?string $end = null): array
    {
        // Reuse parts of member report financial sections
        return $this->getMemberReport($start, $end);
    }

    public function getLoanReport(?string $start = null, ?string $end = null): array
    {
        // Focused loan metrics
        $loanCount = (int)($this->scalar("SELECT COUNT(*) FROM loans") ?? 0);
        $totalLoans = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans") ?? 0.0);
        $loanStatus = $this->rows("SELECT status AS name, COUNT(*) AS count FROM loans GROUP BY status");
        return [
            'loans' => [
                'total_loan_count' => $loanCount,
                'total_loans' => $totalLoans,
            ],
            'loan_status' => $loanStatus,
            'top_borrowers' => $this->rows("SELECT m.member_id, CONCAT(m.first_name,' ',m.last_name) AS name, COALESCE(SUM(l.amount),0) AS total_amount FROM loans l LEFT JOIN members m ON l.member_id = m.member_id GROUP BY l.member_id ORDER BY total_amount DESC LIMIT 5"),
        ];
    }

    public function getActivityReport(?string $start = null, ?string $end = null): array
    {
        // Minimal activity metrics
        return [
            'new_members' => (int)($this->scalar("SELECT COUNT(*) FROM members {$this->buildDateFilter('members','created_at',$start,$end)['where']}") ?? 0),
            'new_contributions' => 0,
            'new_loans' => (int)($this->scalar("SELECT COUNT(*) FROM loans {$this->buildDateFilter('loans','created_at',$start,$end)['where']}") ?? 0),
            'new_investments' => (int)($this->scalar("SELECT COUNT(*) FROM investments {$this->buildDateFilter('investments','created_at',$start,$end)['where']}") ?? 0),
        ];
    }

    // Helpers
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

    private function rows(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        $stmt->close();
        return $rows;
    }

    private function buildDateFilter(string $table, string $col, ?string $start, ?string $end): array
    {
        if (empty($start) || empty($end)) { return ['where' => '', 'params' => [], 'types' => '']; }
        return ['where' => "WHERE $col BETWEEN ? AND ?", 'params' => [$start, $end], 'types' => 'ss'];
    }
}
?>