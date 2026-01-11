<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\DTOs\ValidationResult;
use CSIMS\Services\SecurityService;

/**
 * Loan Model
 * 
 * Represents a loan application/record in the cooperative society
 */
class Loan implements ModelInterface
{
    private ?int $id = null;
    private int $memberId;
    private float $amount = 0.0;
    private string $purpose = '';
    private int $termMonths = 12;
    private float $interestRate = 0.0;
    private ?float $monthlyPayment = null;
    private string $status = 'pending';
    private ?string $applicationDate = null;
    private ?string $approvalDate = null;
    private ?string $disbursementDate = null;
    private ?string $collateral = null;
    private ?string $guarantor = null;
    private ?string $notes = null;
    private float $totalRepaid = 0.0;
    private float $remainingBalance = 0.0;
    private ?string $nextPaymentDate = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    // Related member data (for joins)
    private ?string $memberFirstName = null;
    private ?string $memberLastName = null;
    private ?string $memberEmail = null;
    
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->fillFromArray($data);
        }
    }
    
    /**
     * Get the primary key value
     * 
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->id;
    }
    
    /**
     * Convert model to array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'loan_id' => $this->id,
            'member_id' => $this->memberId,
            'amount' => $this->amount,
            'purpose' => $this->purpose,
            'term' => $this->termMonths,
            'term_months' => $this->termMonths, // Alias for compatibility
            'interest_rate' => $this->interestRate,
            'monthly_payment' => $this->monthlyPayment,
            'status' => $this->status,
            'application_date' => $this->applicationDate,
            'approval_date' => $this->approvalDate,
            'disbursement_date' => $this->disbursementDate,
            'collateral' => $this->collateral,
            'guarantor' => $this->guarantor,
            'notes' => $this->notes,
            'total_repaid' => $this->totalRepaid,
            'remaining_balance' => $this->remainingBalance,
            'next_payment_date' => $this->nextPaymentDate,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            // Member data (for display purposes)
            'member_first_name' => $this->memberFirstName,
            'member_last_name' => $this->memberLastName,
            'member_email' => $this->memberEmail,
        ];
    }
    
    /**
     * Create model from array data
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }
    
    /**
     * Fill model from array data
     * 
     * @param array $data
     * @return void
     */
    private function fillFromArray(array $data): void
    {
        $this->id = $data['loan_id'] ?? $data['id'] ?? null;
        $this->memberId = (int)($data['member_id'] ?? 0);
        $this->amount = (float)($data['amount'] ?? 0.0);
        $this->purpose = $data['purpose'] ?? '';
        $this->termMonths = (int)($data['term'] ?? $data['term_months'] ?? 12);
        $this->interestRate = (float)($data['interest_rate'] ?? 0.0);
        $this->monthlyPayment = isset($data['monthly_payment']) ? (float)$data['monthly_payment'] : null;
        $this->status = $data['status'] ?? 'pending';
        $this->applicationDate = $data['application_date'] ?? null;
        $this->approvalDate = $data['approval_date'] ?? null;
        $this->disbursementDate = $data['disbursement_date'] ?? null;
        $this->collateral = $data['collateral'] ?? null;
        $this->guarantor = $data['guarantor'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->totalRepaid = (float)($data['total_repaid'] ?? 0.0);
        $this->remainingBalance = (float)($data['remaining_balance'] ?? 0.0);
        $this->nextPaymentDate = $data['next_payment_date'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        
        // Member data
        $this->memberFirstName = $data['member_first_name'] ?? $data['first_name'] ?? null;
        $this->memberLastName = $data['member_last_name'] ?? $data['last_name'] ?? null;
        $this->memberEmail = $data['member_email'] ?? $data['email'] ?? null;
    }
    
    /**
     * Validate the model data
     * 
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $securityService = new SecurityService();
        
        $rules = [
            'member_id' => 'required|int',
            'amount' => 'required|numeric|min_value:100',
            'purpose' => 'required|min:5|max:500',
            'term_months' => 'required|int|min_value:1|max_value:240', // Max 20 years
            'interest_rate' => 'required|numeric|min_value:0|max_value:50', // Max 50% interest
            'status' => 'required'
        ];
        
        return $securityService->validateInput($this->toArray(), $rules);
    }
    
    /**
     * Calculate monthly payment
     * 
     * @return float
     */
    public function calculateMonthlyPayment(): float
    {
        if ($this->amount <= 0 || $this->termMonths <= 0) {
            return 0.0;
        }
        
        if ($this->interestRate <= 0) {
            // Simple division for 0% interest
            return round($this->amount / $this->termMonths, 2);
        }
        
        // Calculate using compound interest formula
        $monthlyRate = $this->interestRate / 100 / 12;
        $payment = $this->amount * ($monthlyRate * pow(1 + $monthlyRate, $this->termMonths)) / 
                   (pow(1 + $monthlyRate, $this->termMonths) - 1);
        
        return round($payment, 2);
    }
    
    /**
     * Calculate total interest
     * 
     * @return float
     */
    public function calculateTotalInterest(): float
    {
        $monthlyPayment = $this->monthlyPayment ?: $this->calculateMonthlyPayment();
        $totalPayments = $monthlyPayment * $this->termMonths;
        return round($totalPayments - $this->amount, 2);
    }
    
    /**
     * Calculate remaining balance
     * 
     * @return float
     */
    public function calculateRemainingBalance(): float
    {
        $totalAmount = $this->amount + $this->calculateTotalInterest();
        return round($totalAmount - $this->totalRepaid, 2);
    }
    
    /**
     * Check if loan is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return in_array(strtolower($this->status), ['active', 'disbursed', 'approved']);
    }
    
    /**
     * Check if loan is fully paid
     * 
     * @return bool
     */
    public function isFullyPaid(): bool
    {
        return strtolower($this->status) === 'paid' || $this->calculateRemainingBalance() <= 0;
    }
    
    /**
     * Check if loan is overdue
     * 
     * @return bool
     */
    public function isOverdue(): bool
    {
        if (!$this->nextPaymentDate || !$this->isActive()) {
            return false;
        }
        
        return strtotime($this->nextPaymentDate) < strtotime('today');
    }
    
    /**
     * Get loan status badge color
     * 
     * @return string
     */
    public function getStatusColor(): string
    {
        return match (strtolower($this->status)) {
            'pending' => 'warning',
            'approved' => 'info',
            'active', 'disbursed' => 'success',
            'paid' => 'primary',
            'rejected', 'cancelled' => 'danger',
            'overdue' => 'dark',
            default => 'secondary'
        };
    }
    
    /**
     * Get member full name
     * 
     * @return string
     */
    public function getMemberFullName(): string
    {
        if ($this->memberFirstName && $this->memberLastName) {
            return trim($this->memberFirstName . ' ' . $this->memberLastName);
        }
        return 'Unknown Member';
    }
    
    /**
     * Get formatted amount
     * 
     * @param string $currency
     * @return string
     */
    public function getFormattedAmount(string $currency = '₦'): string
    {
        return $currency . number_format($this->amount, 2);
    }
    
    /**
     * Get formatted monthly payment
     * 
     * @param string $currency
     * @return string
     */
    public function getFormattedMonthlyPayment(string $currency = '₦'): string
    {
        $payment = $this->monthlyPayment ?: $this->calculateMonthlyPayment();
        return $currency . number_format($payment, 2);
    }
    
    /**
     * Set monthly payment automatically
     * 
     * @return self
     */
    public function autoCalculatePayment(): self
    {
        $this->monthlyPayment = $this->calculateMonthlyPayment();
        return $this;
    }
    
    // Getters
    public function getMemberId(): int { return $this->memberId; }
    public function getAmount(): float { return $this->amount; }
    public function getPurpose(): string { return $this->purpose; }
    public function getTermMonths(): int { return $this->termMonths; }
    public function getInterestRate(): float { return $this->interestRate; }
    public function getMonthlyPayment(): ?float { return $this->monthlyPayment; }
    public function getStatus(): string { return $this->status; }
    public function getApplicationDate(): ?string { return $this->applicationDate; }
    public function getApprovalDate(): ?string { return $this->approvalDate; }
    public function getDisbursementDate(): ?string { return $this->disbursementDate; }
    public function getCollateral(): ?string { return $this->collateral; }
    public function getGuarantor(): ?string { return $this->guarantor; }
    public function getNotes(): ?string { return $this->notes; }
    public function getTotalRepaid(): float { return $this->totalRepaid; }
    public function getRemainingBalance(): float { return $this->remainingBalance; }
    public function getNextPaymentDate(): ?string { return $this->nextPaymentDate; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    
    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setMemberId(int $memberId): self { $this->memberId = $memberId; return $this; }
    public function setAmount(float $amount): self { $this->amount = $amount; return $this; }
    public function setPurpose(string $purpose): self { $this->purpose = $purpose; return $this; }
    public function setTermMonths(int $termMonths): self { $this->termMonths = $termMonths; return $this; }
    public function setInterestRate(float $interestRate): self { $this->interestRate = $interestRate; return $this; }
    public function setMonthlyPayment(?float $monthlyPayment): self { $this->monthlyPayment = $monthlyPayment; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setApplicationDate(?string $applicationDate): self { $this->applicationDate = $applicationDate; return $this; }
    public function setApprovalDate(?string $approvalDate): self { $this->approvalDate = $approvalDate; return $this; }
    public function setDisbursementDate(?string $disbursementDate): self { $this->disbursementDate = $disbursementDate; return $this; }
    public function setCollateral(?string $collateral): self { $this->collateral = $collateral; return $this; }
    public function setGuarantor(?string $guarantor): self { $this->guarantor = $guarantor; return $this; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function setTotalRepaid(float $totalRepaid): self { $this->totalRepaid = $totalRepaid; return $this; }
    public function setRemainingBalance(float $remainingBalance): self { $this->remainingBalance = $remainingBalance; return $this; }
    public function setNextPaymentDate(?string $nextPaymentDate): self { $this->nextPaymentDate = $nextPaymentDate; return $this; }
}
