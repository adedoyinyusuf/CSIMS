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
        $newSavings = $this->scalar("SELECT COUNT(*) AS c FROM savings_transactions {$this->buildDateFilter('savings_transactions','created_at',$start,$end)['where']}") ?? 0;
        $newLoans = $this->scalar("SELECT COUNT(*) AS c FROM loans {$this->buildDateFilter('loans','created_at',$start,$end)['where']}") ?? 0;

        // Financial summary sections
        $totalSavings = $this->scalar("SELECT COALESCE(SUM(amount),0) AS s FROM savings_transactions") ?? 0;
        $totalSavingsTx = $this->scalar("SELECT COUNT(*) AS c FROM savings_transactions") ?? 0;
        $avgSavings = $totalSavingsTx > 0 ? ($totalSavings / $totalSavingsTx) : 0;

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
            'savings' => [
                'total_savings' => (float)$totalSavings,
                'total_transactions' => (int)$totalSavingsTx,
                'average_savings' => (float)$avgSavings,
            ],
            'loans' => [
                'total_loans' => (float)$totalLoans,
                'total_loan_count' => (int)$loanCount,
            ],
            'loan_status' => $loanStatus,
            'amount_ranges' => $amountRanges,
            'top_borrowers' => $topBorrowers,
            'new_members' => (int)$newMembers,
            'new_savings' => (int)$newSavings,
            'new_loans' => (int)$newLoans,
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
            'new_savings' => (int)($this->scalar("SELECT COUNT(*) FROM savings_transactions {$this->buildDateFilter('savings_transactions','created_at',$start,$end)['where']}") ?? 0),
            'new_loans' => (int)($this->scalar("SELECT COUNT(*) FROM loans {$this->buildDateFilter('loans','created_at',$start,$end)['where']}") ?? 0),
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

    // Dashboard Support Methods
    
    public function getKPIs(): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $yearStart = date('Y-01-01');

        $activeMembers = (int)($this->scalar("SELECT COUNT(*) FROM members WHERE status = 'Active'") ?? 0);
        $newMembersMonth = (int)($this->scalar("SELECT COUNT(*) FROM members WHERE join_date >= ?", [$monthStart], 's') ?? 0);
        $outstandingLoans = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status IN ('Active', 'Disbursed')") ?? 0.0);
        $savingsYTD = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE type = 'deposit' AND created_at >= ?", [$yearStart], 's') ?? 0.0);

        $totalLoansCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status IN ('Active', 'Disbursed', 'Overdue')") ?? 0);
        $overdueLoansCount = (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status = 'Overdue'") ?? 0);
        $par = $totalLoansCount > 0 ? round(($overdueLoansCount / $totalLoansCount) * 100, 1) : 0;

        return [
            'total_active_members' => $activeMembers,
            'new_members_this_month' => $newMembersMonth,
            'outstanding_loans' => $outstandingLoans,
            'savings_this_year' => $savingsYTD,
            'portfolio_at_risk' => $par,
            'overdue_loans' => $overdueLoansCount
        ];
    }

    public function getFinancialSummary(?string $start = null, ?string $end = null): array
    {
        $totalSavings = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE type = 'deposit'") ?? 0);
        $totalWithdrawals = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM savings_transactions WHERE type = 'withdrawal'") ?? 0);
        $netSavings = $totalSavings - $totalWithdrawals;
        $outstandingLoans = (float)($this->scalar("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status IN ('Active', 'Disbursed', 'Overdue')") ?? 0);
        
        return [
            'net_position' => [
                'total_assets' => $netSavings, 
                'outstanding_loans' => $outstandingLoans,
                'liquid_reserves' => $netSavings - $outstandingLoans
            ],
            'savings' => [
                'total_savings' => (int)($this->scalar("SELECT COUNT(*) FROM savings_transactions WHERE type = 'deposit'") ?? 0),
                'total_amount' => $totalSavings
            ],
            'shares' => [
                'total_share_purchases' => 0,
                'total_paid' => 0
            ],
            'withdrawals' => [
                'total_withdrawals' => (int)($this->scalar("SELECT COUNT(*) FROM savings_transactions WHERE type = 'withdrawal'") ?? 0),
                'net_amount' => $totalWithdrawals
            ],
            'loans' => [
                 'total_loans' => (int)($this->scalar("SELECT COUNT(*) FROM loans WHERE status IN ('Active', 'Disbursed')") ?? 0)
            ]
        ];
    }

    public function getLoanPortfolioAnalysis(string $period): array
    {
        $statusCounts = $this->rows("SELECT status, COUNT(*) as c FROM loans GROUP BY status");
        $labels = [];
        $data = [];
        foreach($statusCounts as $row) {
            $labels[] = $row['status'];
            $data[] = (int)$row['c'];
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function getMemberStatistics(string $period): array
    {
         $total = (int)($this->scalar("SELECT COUNT(*) FROM members") ?? 0);
         $rows = $this->rows("SELECT status, COUNT(*) as c FROM members GROUP BY status");
         $dist = [];
         foreach($rows as $r) {
             $c = (int)$r['c'];
             $dist[] = [
                 'status' => $r['status'],
                 'count' => $c,
                 'percentage' => $total > 0 ? round(($c / $total) * 100, 1) : 0
             ];
         }
         
         $activeSavers = (int)($this->scalar("SELECT COUNT(DISTINCT member_id) FROM savings_transactions") ?? 0);
         $totalSavings = (float)($this->scalar("SELECT SUM(amount) FROM savings_transactions WHERE type='deposit'") ?? 0);
         $avgSavings = $activeSavers > 0 ? $totalSavings / $activeSavers : 0;
         $maxSavings = (float)($this->scalar("SELECT MAX(amount) FROM savings_transactions WHERE type='deposit'") ?? 0);
         
         return [
             'status_distribution' => $dist,
             'savings_participation' => [
                 'active_savers' => $activeSavers,
                 'avg_savings' => $avgSavings,
                 'max_savings' => $maxSavings
             ]
         ];
    }

    public function getSavingsPerformance(string $period): array
    {
        $rows = $this->rows("SELECT DATE_FORMAT(created_at, '%Y-%m') as m, SUM(amount) as total FROM savings_transactions WHERE type='deposit' GROUP BY m ORDER BY m DESC LIMIT 12");
        $labels = [];
        $data = [];
        foreach(array_reverse($rows) as $row) {
            $labels[] = date('M Y', strtotime($row['m'] . '-01'));
            $data[] = (float)$row['total'];
        }

        $typeAnalysis = $this->rows("SELECT type as savings_type, COUNT(*) as transaction_count, SUM(amount) as total_amount, AVG(amount) as avg_amount, COUNT(DISTINCT member_id) as unique_savers FROM savings_transactions GROUP BY type");
        
        $topSavers = $this->rows("SELECT m.member_id, CONCAT(m.first_name,' ',m.last_name) AS member_name, COUNT(*) as savings_count, SUM(amount) as total_saved, AVG(amount) as avg_savings FROM savings_transactions st JOIN members m ON st.member_id = m.member_id WHERE st.type='deposit' GROUP BY st.member_id ORDER BY total_saved DESC LIMIT 10");

        return [
            'labels' => $labels, 
            'data' => $data,
            'type_analysis' => $typeAnalysis,
            'top_savers' => $topSavers
        ];
    }

    public function getRecentTransactions(int $limit = 10): array
    {
        return $this->rows("SELECT t.id, t.amount, t.type, t.created_at, m.first_name, m.last_name FROM savings_transactions t LEFT JOIN members m ON t.member_id = m.member_id ORDER BY t.created_at DESC LIMIT ?", [$limit], 'i');
    }
    
    public function exportToCSV(array $data, string $filename): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        // Basic CSV dump - in real app would need specific formatting per report type
        foreach ($data as $row) {
            if (is_array($row)) fputcsv($out, $row);
            else fputcsv($out, [$row]);
        }
        fclose($out);
    }

    private function buildDateFilter(string $table, string $col, ?string $start, ?string $end): array
    {
        if (empty($start) || empty($end)) { return ['where' => '', 'params' => [], 'types' => '']; }
        return ['where' => "WHERE $col BETWEEN ? AND ?", 'params' => [$start, $end], 'types' => 'ss'];
    }
}
?>