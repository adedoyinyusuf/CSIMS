<?php

namespace CSIMS\Services;

use CSIMS\Models\Contribution;
use CSIMS\Repositories\ContributionRepository;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Services\SecurityService;
use CSIMS\DTOs\ValidationResult;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\CSIMSException;

/**
 * Contribution Service
 * 
 * Handles contribution-related business logic and operations
 */
class ContributionService
{
    private ContributionRepository $contributionRepository;
    private MemberRepository $memberRepository;
    private SecurityService $securityService;
    
    public function __construct(
        ContributionRepository $contributionRepository,
        MemberRepository $memberRepository,
        SecurityService $securityService
    ) {
        $this->contributionRepository = $contributionRepository;
        $this->memberRepository = $memberRepository;
        $this->securityService = $securityService;
    }
    
    /**
     * Create new contribution with validation
     * 
     * @param array $data
     * @return Contribution
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function createContribution(array $data): Contribution
    {
        // Sanitize input data
        $data = $this->securityService->sanitizeArray($data);
        
        // Validate member exists
        if (!isset($data['member_id']) || !$this->memberRepository->find($data['member_id'])) {
            throw new ValidationException('Invalid member ID');
        }
        
        // Create contribution instance
        $contribution = new Contribution();
        $contribution->fromArray($data);
        
        // Validate contribution data
        $validation = $contribution->validate($this->securityService);
        if (!$validation->isValid()) {
            throw new ValidationException($validation->getFirstError());
        }
        
        // Set default values
        $contribution->setContributionDate($data['contribution_date'] ?? date('Y-m-d'));
        $contribution->setStatus($data['status'] ?? 'Confirmed');
        
        // Generate receipt number if not provided
        if (empty($contribution->getReceiptNumber())) {
            $contribution->setReceiptNumber($this->generateReceiptNumber());
        }
        
        // Validate business rules
        $this->validateContributionBusinessRules($contribution);
        
        // Save contribution
        return $this->contributionRepository->create($contribution);
    }
    
    /**
     * Update existing contribution
     * 
     * @param int $id
     * @param array $data
     * @return Contribution
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function updateContribution(int $id, array $data): Contribution
    {
        // Find existing contribution
        $contribution = $this->contributionRepository->find($id);
        if (!$contribution) {
            throw new ValidationException('Contribution not found');
        }
        
        // Check if contribution can be edited
        if (!$contribution->canBeEdited()) {
            throw new ValidationException('This contribution cannot be edited');
        }
        
        // Sanitize input data
        $data = $this->securityService->sanitizeArray($data);
        
        // Update contribution properties
        $contribution->fromArray($data);
        
        // Validate updated contribution
        $validation = $contribution->validate($this->securityService);
        if (!$validation->isValid()) {
            throw new ValidationException($validation->getFirstError());
        }
        
        // Validate business rules
        $this->validateContributionBusinessRules($contribution);
        
        // Save updated contribution
        return $this->contributionRepository->update($contribution);
    }
    
    /**
     * Get contribution by ID with member information
     * 
     * @param int $id
     * @return Contribution|null
     * @throws DatabaseException
     */
    public function getContribution(int $id): ?Contribution
    {
        return $this->contributionRepository->find($id);
    }
    
    /**
     * Get contributions with filtering and pagination
     * 
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @param array $orderBy
     * @return array
     * @throws DatabaseException
     */
    public function getContributions(array $filters = [], int $page = 1, int $limit = 10, array $orderBy = ['contribution_date' => 'DESC']): array
    {
        // Sanitize filters
        $filters = $this->securityService->sanitizeArray($filters);
        
        return $this->contributionRepository->getPaginated($page, $limit, $filters, $orderBy);
    }
    
    /**
     * Get contributions by member
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function getContributionsByMember(int $memberId): array
    {
        return $this->contributionRepository->findByMember($memberId);
    }
    
    /**
     * Get contributions by type
     * 
     * @param string $type
     * @return array
     * @throws DatabaseException
     */
    public function getContributionsByType(string $type): array
    {
        return $this->contributionRepository->findByType($type);
    }
    
    /**
     * Get contribution statistics
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getContributionStatistics(): array
    {
        return $this->contributionRepository->getStatistics();
    }
    
    /**
     * Get member contribution summary
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function getMemberContributionSummary(int $memberId): array
    {
        $summary = $this->contributionRepository->getMemberSummary($memberId);
        $contributions = $this->contributionRepository->findByMember($memberId);
        
        $summary['contributions'] = $contributions;
        $summary['monthly_average'] = $summary['monthly_count'] > 0 ? 
            $summary['monthly_total'] / $summary['monthly_count'] : 0;
        $summary['special_average'] = $summary['special_count'] > 0 ? 
            $summary['special_total'] / $summary['special_count'] : 0;
        
        return $summary;
    }
    
    /**
     * Confirm contribution
     * 
     * @param int $contributionId
     * @param string $confirmedBy
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function confirmContribution(int $contributionId, string $confirmedBy): bool
    {
        $contribution = $this->contributionRepository->find($contributionId);
        if (!$contribution) {
            throw new ValidationException('Contribution not found');
        }
        
        if ($contribution->getStatus() !== 'Pending') {
            throw new ValidationException('Can only confirm pending contributions');
        }
        
        $contribution->setStatus('Confirmed');
        $this->contributionRepository->update($contribution);
        
        return true;
    }
    
    /**
     * Reject contribution
     * 
     * @param int $contributionId
     * @param string $rejectedBy
     * @param string $reason
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function rejectContribution(int $contributionId, string $rejectedBy, string $reason): bool
    {
        $contribution = $this->contributionRepository->find($contributionId);
        if (!$contribution) {
            throw new ValidationException('Contribution not found');
        }
        
        if ($contribution->getStatus() !== 'Pending') {
            throw new ValidationException('Can only reject pending contributions');
        }
        
        $contribution->setStatus('Rejected');
        $contribution->setNotes(($contribution->getNotes() ?? '') . ' [Rejected: ' . $reason . ']');
        $this->contributionRepository->update($contribution);
        
        return true;
    }
    
    /**
     * Delete contribution
     * 
     * @param int $id
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function deleteContribution(int $id): bool
    {
        $contribution = $this->contributionRepository->find($id);
        if (!$contribution) {
            throw new ValidationException('Contribution not found');
        }
        
        // Only allow deletion of pending or rejected contributions
        if (!$contribution->canBeDeleted()) {
            throw new ValidationException('This contribution cannot be deleted');
        }
        
        return $this->contributionRepository->delete($id);
    }
    
    /**
     * Search contributions
     * 
     * @param string $searchTerm
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     * @throws DatabaseException
     */
    public function searchContributions(string $searchTerm, array $filters = [], int $page = 1, int $limit = 10): array
    {
        $searchTerm = $this->securityService->sanitizeString($searchTerm);
        $filters = $this->securityService->sanitizeArray($filters);
        
        return $this->contributionRepository->search($searchTerm, $filters, $page, $limit);
    }
    
    /**
     * Get monthly contributions for a specific period
     * 
     * @param int $month
     * @param int $year
     * @return array
     * @throws DatabaseException
     */
    public function getMonthlyContributions(int $month, int $year): array
    {
        if ($month < 1 || $month > 12) {
            throw new ValidationException('Invalid month');
        }
        
        if ($year < 2000 || $year > date('Y') + 1) {
            throw new ValidationException('Invalid year');
        }
        
        return $this->contributionRepository->findMonthlyContributions($month, $year);
    }
    
    /**
     * Bulk import contributions
     * 
     * @param array $contributions
     * @return array
     * @throws DatabaseException
     */
    public function bulkImportContributions(array $contributions): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($contributions as $index => $contributionData) {
            try {
                $this->createContribution($contributionData);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row {$index}: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Generate contribution report for a period
     * 
     * @param string $startDate
     * @param string $endDate
     * @param string|null $contributionType
     * @return array
     * @throws DatabaseException
     */
    public function generateContributionReport(string $startDate, string $endDate, ?string $contributionType = null): array
    {
        $filters = [
            'date_from' => $startDate,
            'date_to' => $endDate,
            'status' => 'Confirmed'
        ];
        
        if ($contributionType) {
            $filters['contribution_type'] = $contributionType;
        }
        
        $contributions = $this->contributionRepository->findBy($filters);
        
        // Calculate summary statistics
        $totalAmount = 0;
        $memberContributions = [];
        $typeBreakdown = [];
        $methodBreakdown = [];
        
        foreach ($contributions as $contribution) {
            $totalAmount += $contribution->getAmount();
            
            // Member contributions
            $memberId = $contribution->getMemberId();
            if (!isset($memberContributions[$memberId])) {
                $memberContributions[$memberId] = [
                    'member_name' => $contribution->getMemberFullName(),
                    'total_amount' => 0,
                    'count' => 0
                ];
            }
            $memberContributions[$memberId]['total_amount'] += $contribution->getAmount();
            $memberContributions[$memberId]['count']++;
            
            // Type breakdown
            $type = $contribution->getContributionType();
            if (!isset($typeBreakdown[$type])) {
                $typeBreakdown[$type] = ['amount' => 0, 'count' => 0];
            }
            $typeBreakdown[$type]['amount'] += $contribution->getAmount();
            $typeBreakdown[$type]['count']++;
            
            // Payment method breakdown
            $method = $contribution->getPaymentMethod();
            if (!isset($methodBreakdown[$method])) {
                $methodBreakdown[$method] = ['amount' => 0, 'count' => 0];
            }
            $methodBreakdown[$method]['amount'] += $contribution->getAmount();
            $methodBreakdown[$method]['count']++;
        }
        
        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'type_filter' => $contributionType
            ],
            'summary' => [
                'total_contributions' => count($contributions),
                'total_amount' => $totalAmount,
                'average_amount' => count($contributions) > 0 ? $totalAmount / count($contributions) : 0,
                'contributing_members' => count($memberContributions)
            ],
            'contributions' => $contributions,
            'member_breakdown' => $memberContributions,
            'type_breakdown' => $typeBreakdown,
            'method_breakdown' => $methodBreakdown
        ];
    }
    
    /**
     * Check member contribution compliance
     * 
     * @param int $memberId
     * @param int $requiredMonthlyAmount
     * @return array
     * @throws DatabaseException
     */
    public function checkMemberCompliance(int $memberId, int $requiredMonthlyAmount = 100): array
    {
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        $compliance = [];
        
        for ($month = 1; $month <= $currentMonth; $month++) {
            $monthlyContributions = $this->contributionRepository->findBy([
                'member_id' => $memberId,
                'contribution_type' => 'Monthly',
                'date_from' => "{$currentYear}-{$month:02d}-01",
                'date_to' => "{$currentYear}-{$month:02d}-" . cal_days_in_month(CAL_GREGORIAN, $month, $currentYear),
                'status' => 'Confirmed'
            ]);
            
            $monthTotal = array_sum(array_map(fn($c) => $c->getAmount(), $monthlyContributions));
            
            $compliance[] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'required_amount' => $requiredMonthlyAmount,
                'contributed_amount' => $monthTotal,
                'is_compliant' => $monthTotal >= $requiredMonthlyAmount,
                'deficit' => max(0, $requiredMonthlyAmount - $monthTotal),
                'contributions' => $monthlyContributions
            ];
        }
        
        $totalRequired = $currentMonth * $requiredMonthlyAmount;
        $totalContributed = array_sum(array_column($compliance, 'contributed_amount'));
        $compliantMonths = count(array_filter($compliance, fn($c) => $c['is_compliant']));
        
        return [
            'member_id' => $memberId,
            'year' => $currentYear,
            'summary' => [
                'total_required' => $totalRequired,
                'total_contributed' => $totalContributed,
                'total_deficit' => max(0, $totalRequired - $totalContributed),
                'compliance_rate' => $currentMonth > 0 ? ($compliantMonths / $currentMonth) * 100 : 0,
                'compliant_months' => $compliantMonths,
                'total_months' => $currentMonth
            ],
            'monthly_breakdown' => $compliance
        ];
    }
    
    /**
     * Validate contribution business rules
     * 
     * @param Contribution $contribution
     * @throws ValidationException
     */
    private function validateContributionBusinessRules(Contribution $contribution): void
    {
        // Check minimum amount
        if ($contribution->getAmount() < 1) {
            throw new ValidationException('Minimum contribution amount is $1');
        }
        
        // Check maximum amount for safety
        if ($contribution->getAmount() > 100000) {
            throw new ValidationException('Maximum contribution amount is $100,000');
        }
        
        // Check for duplicate receipt number
        if ($contribution->getReceiptNumber()) {
            $existing = $this->contributionRepository->findOneBy([
                'receipt_number' => $contribution->getReceiptNumber()
            ]);
            
            if ($existing && $existing->getId() !== $contribution->getId()) {
                throw new ValidationException('Receipt number already exists');
            }
        }
        
        // Check if member exists and is active
        $member = $this->memberRepository->find($contribution->getMemberId());
        if (!$member || $member->getStatus() !== 'Active') {
            throw new ValidationException('Member must be active to make contributions');
        }
        
        // Business rule: Monthly contributions should not exceed certain frequency
        if ($contribution->isMonthlyContribution()) {
            $period = $contribution->getContributionPeriod();
            $existingMonthly = $this->contributionRepository->findBy([
                'member_id' => $contribution->getMemberId(),
                'contribution_type' => 'Monthly',
                'date_from' => "{$period['year']}-{$period['month']:02d}-01",
                'date_to' => "{$period['year']}-{$period['month']:02d}-" . cal_days_in_month(CAL_GREGORIAN, $period['month'], $period['year']),
                'status' => ['Confirmed', 'Pending']
            ]);
            
            // Filter out current contribution if updating
            $existingMonthly = array_filter($existingMonthly, fn($c) => $c->getId() !== $contribution->getId());
            
            if (count($existingMonthly) >= 5) { // Allow up to 5 monthly contributions per month
                throw new ValidationException('Maximum monthly contributions per month exceeded');
            }
        }
    }
    
    /**
     * Generate unique receipt number
     * 
     * @param string $prefix
     * @return string
     */
    private function generateReceiptNumber(string $prefix = 'CONT'): string
    {
        return $prefix . date('Ymd') . sprintf('%06d', rand(100000, 999999));
    }
}
