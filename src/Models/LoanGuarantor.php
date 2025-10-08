<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\DTOs\ValidationResult;
use CSIMS\Services\SecurityService;

/**
 * Loan Guarantor Model
 * 
 * Represents a loan guarantor in the cooperative society
 */
class LoanGuarantor implements ModelInterface
{
    private ?int $id = null;
    private int $loanId;
    private int $guarantorMemberId;
    private float $guaranteeAmount = 0.0;
    private float $guaranteePercentage = 0.0;
    private string $guarantorType = 'Individual'; // Individual, Corporate
    private string $status = 'Active';
    private ?string $notes = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    // Related data for joins
    private ?string $loanAmount = null;
    private ?string $guarantorFirstName = null;
    private ?string $guarantorLastName = null;
    private ?string $guarantorEmail = null;
    private ?string $guarantorPhone = null;
    
    public const GUARANTOR_TYPES = [
        'Individual',
        'Corporate',
        'Family',
        'Group'
    ];
    
    public const STATUSES = [
        'Active',
        'Inactive',
        'Released',
        'Defaulted'
    ];
    
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->fillFromArray($data);
        }
    }
    
    /**
     * Get the primary key value
     */
    public function getId(): mixed
    {
        return $this->id;
    }
    
    /**
     * Convert model to array representation
     */
    public function toArray(): array
    {
        return [
            'guarantor_id' => $this->id,
            'loan_id' => $this->loanId,
            'guarantor_member_id' => $this->guarantorMemberId,
            'guarantee_amount' => $this->guaranteeAmount,
            'guarantee_percentage' => $this->guaranteePercentage,
            'guarantor_type' => $this->guarantorType,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            // Related data
            'loan_amount' => $this->loanAmount,
            'guarantor_first_name' => $this->guarantorFirstName,
            'guarantor_last_name' => $this->guarantorLastName,
            'guarantor_email' => $this->guarantorEmail,
            'guarantor_phone' => $this->guarantorPhone,
        ];
    }
    
    /**
     * Create model from array data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }
    
    /**
     * Fill model from array data
     */
    private function fillFromArray(array $data): void
    {
        $this->id = $data['guarantor_id'] ?? $data['id'] ?? null;
        $this->loanId = (int)($data['loan_id'] ?? 0);
        $this->guarantorMemberId = (int)($data['guarantor_member_id'] ?? 0);
        $this->guaranteeAmount = (float)($data['guarantee_amount'] ?? 0.0);
        $this->guaranteePercentage = (float)($data['guarantee_percentage'] ?? 0.0);
        $this->guarantorType = $data['guarantor_type'] ?? 'Individual';
        $this->status = $data['status'] ?? 'Active';
        $this->notes = $data['notes'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        
        // Related data
        $this->loanAmount = $data['loan_amount'] ?? null;
        $this->guarantorFirstName = $data['guarantor_first_name'] ?? null;
        $this->guarantorLastName = $data['guarantor_last_name'] ?? null;
        $this->guarantorEmail = $data['guarantor_email'] ?? null;
        $this->guarantorPhone = $data['guarantor_phone'] ?? null;
    }
    
    /**
     * Validate the model data
     */
    public function validate(): ValidationResult
    {
        $errors = [];
        
        // Validate loan ID
        if ($this->loanId <= 0) {
            $errors[] = 'Valid loan ID is required';
        }
        
        // Validate guarantor member ID
        if ($this->guarantorMemberId <= 0) {
            $errors[] = 'Valid guarantor member ID is required';
        }
        
        // Validate guarantee amount
        if ($this->guaranteeAmount < 0) {
            $errors[] = 'Guarantee amount cannot be negative';
        }
        
        // Validate guarantee percentage
        if ($this->guaranteePercentage < 0 || $this->guaranteePercentage > 100) {
            $errors[] = 'Guarantee percentage must be between 0 and 100';
        }
        
        // Validate guarantor type
        if (!in_array($this->guarantorType, self::GUARANTOR_TYPES)) {
            $errors[] = 'Invalid guarantor type';
        }
        
        // Validate status
        if (!in_array($this->status, self::STATUSES)) {
            $errors[] = 'Invalid status';
        }
        
        // At least one of amount or percentage must be set
        if ($this->guaranteeAmount <= 0 && $this->guaranteePercentage <= 0) {
            $errors[] = 'Either guarantee amount or percentage must be specified';
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
    
    /**
     * Get guarantor full name
     */
    public function getGuarantorFullName(): string
    {
        if ($this->guarantorFirstName && $this->guarantorLastName) {
            return trim($this->guarantorFirstName . ' ' . $this->guarantorLastName);
        }
        return 'Unknown Guarantor';
    }
    
    /**
     * Get formatted guarantee amount
     */
    public function getFormattedGuaranteeAmount(string $currency = '₦'): string
    {
        return $currency . number_format($this->guaranteeAmount, 2);
    }
    
    /**
     * Check if guarantor is active
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
    
    /**
     * Calculate actual guarantee amount based on loan amount
     */
    public function calculateActualAmount(?float $loanAmount = null): float
    {
        $loanAmount = $loanAmount ?: (float)$this->loanAmount;
        
        if ($this->guaranteeAmount > 0) {
            return $this->guaranteeAmount;
        }
        
        if ($this->guaranteePercentage > 0 && $loanAmount > 0) {
            return ($this->guaranteePercentage / 100) * $loanAmount;
        }
        
        return 0.0;
    }
    
    // Getters
    public function getLoanId(): int { return $this->loanId; }
    public function getGuarantorMemberId(): int { return $this->guarantorMemberId; }
    public function getGuaranteeAmount(): float { return $this->guaranteeAmount; }
    public function getGuaranteePercentage(): float { return $this->guaranteePercentage; }
    public function getGuarantorType(): string { return $this->guarantorType; }
    public function getStatus(): string { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    
    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setLoanId(int $loanId): self { $this->loanId = $loanId; return $this; }
    public function setGuarantorMemberId(int $guarantorMemberId): self { $this->guarantorMemberId = $guarantorMemberId; return $this; }
    public function setGuaranteeAmount(float $guaranteeAmount): self { $this->guaranteeAmount = $guaranteeAmount; return $this; }
    public function setGuaranteePercentage(float $guaranteePercentage): self { $this->guaranteePercentage = $guaranteePercentage; return $this; }
    public function setGuarantorType(string $guarantorType): self { $this->guarantorType = $guarantorType; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
}