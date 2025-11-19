<?php

// Legacy-compatible EnhancedLoanController shim
// Delegates to existing LoanController to satisfy views/admin/loans.php include

require_once __DIR__ . '/loan_controller.php';

class EnhancedLoanController
{
    private LoanController $base;

    public function __construct()
    {
        $this->base = new LoanController();
    }

    /**
     * Legacy signature used by views/admin/loans.php
     *
     * @param int $page
     * @param int $per_page
     * @param string|null $search
     * @param string|null $sort_by
     * @param string|null $sort_order
     * @param string|null $status
     * @param string|null $loan_type
     * @param string|null $amount_range e.g. "1000-5000" or null
     * @return array
     */
    public function getAllLoans(
        int $page = 1,
        int $per_page = 10,
        ?string $search = null,
        ?string $sort_by = null,
        ?string $sort_order = null,
        ?string $status = null,
        ?string $loan_type = null,
        ?string $amount_range = null
    ): array {
        $filters = [];
        if (!empty($status)) {
            $filters['status'] = $status;
        }
        if (!empty($loan_type)) {
            $filters['loan_type'] = $loan_type;
        }
        if (!empty($amount_range)) {
            // Parse "min-max"
            $parts = explode('-', $amount_range);
            if (count($parts) === 2) {
                $min = is_numeric($parts[0]) ? (float)$parts[0] : null;
                $max = is_numeric($parts[1]) ? (float)$parts[1] : null;
                if ($min !== null) { $filters['min_amount'] = $min; }
                if ($max !== null) { $filters['max_amount'] = $max; }
            }
        }
        $result = $this->base->getAllLoans(
            $page,
            $per_page,
            $search,
            $sort_by,
            $sort_order,
            $filters
        );

        // Wrap into legacy-friendly pagination structure expected by views/admin/loans.php
        return [
            'loans' => $result['loans'] ?? [],
            'pagination' => [
                'total_items' => (int)($result['total'] ?? 0),
                'total_pages' => (int)($result['pages'] ?? 1),
                'current_page' => (int)($result['current_page'] ?? $page),
                'per_page' => (int)$per_page
            ]
        ];
    }

    public function getLoanStatistics(): array
    {
        $stats = $this->base->getLoanStatistics();

        // Provide legacy-friendly keys used by the view
        $stats['pending_count'] = (int)($stats['pending_loans'] ?? 0);
        $stats['approved_count'] = (int)($stats['approved_loans'] ?? 0);
        $stats['overdue_count'] = (int)($stats['overdue_loans'] ?? 0);

        // Ensure approved_amount key exists for amount display
        if (!array_key_exists('approved_amount', $stats)) {
            $stats['approved_amount'] = 0;
        }

        return $stats;
    }

    public function getLoanTypes(): array
    {
        return $this->base->getLoanTypes();
    }

    public function getLoanStatuses(): array
    {
        return $this->base->getLoanStatuses();
    }

    public function getLoanById(int $loanId): ?array
    {
        return $this->base->getLoanById($loanId);
    }

    /**
     * Provide members-with-loans aggregation to the admin loans view
     */
    public function getMembersWithLoans(int $limit = 10, ?string $search = null): array
    {
        return $this->base->getMembersWithLoans($limit, $search);
    }
}