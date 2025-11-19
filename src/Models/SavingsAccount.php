<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Exceptions\ValidationException;
use DateTime;
use CSIMS\DTOs\ValidationResult;

/**
 * Savings Account Model
 * 
 * Represents a savings account in the cooperative system
 */
class SavingsAccount implements ModelInterface
{
    private ?int $accountId = null;
    private int $memberId;
    private string $accountNumber;
    private string $accountType;
    private string $accountName;
    private float $balance = 0.00;
    private float $minimumBalance = 0.00;
    private float $interestRate = 0.00;
    private string $interestCalculation = 'monthly';
    private ?DateTime $lastInterestDate = null;
    private ?DateTime $maturityDate = null;
    private ?float $targetAmount = null;
    private ?float $monthlyTarget = null;
    private bool $autoDeduct = false;
    private string $accountStatus = 'Active';
    private DateTime $openingDate;
    private ?DateTime $closingDate = null;
    private int $createdBy;
    private ?int $updatedBy = null;
    private ?string $notes = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    // Valid account types
    private const ACCOUNT_TYPES = ['Regular', 'Fixed', 'Target', 'Emergency', 'Retirement'];
    
    // Valid account statuses
    private const ACCOUNT_STATUSES = ['Active', 'Inactive', 'Suspended', 'Closed', 'Matured'];
    
    // Valid interest calculation methods
    private const INTEREST_CALCULATIONS = ['simple', 'compound', 'daily', 'monthly', 'quarterly', 'annually'];

    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->loadFromArray($data);
        }
        
        if (!isset($this->openingDate)) {
            $this->openingDate = new DateTime();
        }
    }

    /**
     * Create instance from array data
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        $instance->fillFromArray($data);
        return $instance;
    }

    /**
     * Fill instance properties from array
     */
    public function loadFromArray(array $data): void
    {
        $this->fillFromArray($data);
    }

    private function fillFromArray(array $data): void
    {
        $this->accountId = $data['account_id'] ?? null;
        $this->memberId = $data['member_id'] ?? 0;
        $this->accountNumber = $data['account_number'] ?? '';
        $this->accountType = $data['account_type'] ?? 'Regular';
        $this->accountName = $data['account_name'] ?? '';
        $this->balance = (float)($data['balance'] ?? 0.00);
        $this->minimumBalance = (float)($data['minimum_balance'] ?? 0.00);
        $this->interestRate = (float)($data['interest_rate'] ?? 0.00);
        $this->interestCalculation = $data['interest_calculation'] ?? 'monthly';
        $this->lastInterestDate = $data['last_interest_date'] ? new DateTime($data['last_interest_date']) : null;
        $this->maturityDate = $data['maturity_date'] ? new DateTime($data['maturity_date']) : null;
        $this->targetAmount = $data['target_amount'] ? (float)$data['target_amount'] : null;
        $this->monthlyTarget = $data['monthly_target'] ? (float)$data['monthly_target'] : null;
        $this->autoDeduct = (bool)($data['auto_deduct'] ?? false);
        $this->accountStatus = $data['account_status'] ?? 'Active';
        $this->openingDate = $data['opening_date'] ? new DateTime($data['opening_date']) : new DateTime();
        $this->closingDate = $data['closing_date'] ? new DateTime($data['closing_date']) : null;
        $this->createdBy = $data['created_by'] ?? 0;
        $this->updatedBy = $data['updated_by'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->createdAt = $data['created_at'] ? new DateTime($data['created_at']) : null;
        $this->updatedAt = $data['updated_at'] ? new DateTime($data['updated_at']) : null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'member_id' => $this->memberId,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType,
            'account_name' => $this->accountName,
            'balance' => $this->balance,
            'minimum_balance' => $this->minimumBalance,
            'interest_rate' => $this->interestRate,
            'interest_calculation' => $this->interestCalculation,
            'last_interest_date' => $this->lastInterestDate?->format('Y-m-d'),
            'maturity_date' => $this->maturityDate?->format('Y-m-d'),
            'target_amount' => $this->targetAmount,
            'monthly_target' => $this->monthlyTarget,
            'auto_deduct' => $this->autoDeduct,
            'account_status' => $this->accountStatus,
            'opening_date' => $this->openingDate->format('Y-m-d'),
            'closing_date' => $this->closingDate?->format('Y-m-d'),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'notes' => $this->notes,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Validate the model
     */
    public function validate(): ValidationResult
    {
        $vr = new ValidationResult(true);
    
        if (empty($this->memberId)) {
            $vr->addError('member_id', 'Member ID is required');
        }
    
        if (empty($this->accountNumber)) {
            $vr->addError('account_number', 'Account number is required');
        }
    
        if (empty($this->accountName)) {
            $vr->addError('account_name', 'Account name is required');
        }
    
        if (!in_array($this->accountType, self::ACCOUNT_TYPES)) {
            $vr->addError('account_type', 'Invalid account type. Must be one of: ' . implode(', ', self::ACCOUNT_TYPES));
        }
    
        if (!in_array($this->accountStatus, self::ACCOUNT_STATUSES)) {
            $vr->addError('account_status', 'Invalid account status. Must be one of: ' . implode(', ', self::ACCOUNT_STATUSES));
        }
    
        if (!in_array($this->interestCalculation, self::INTEREST_CALCULATIONS)) {
            $vr->addError('interest_calculation', 'Invalid interest calculation method. Must be one of: ' . implode(', ', self::INTEREST_CALCULATIONS));
        }
    
        if ($this->balance < 0) {
            $vr->addError('balance', 'Balance cannot be negative');
        }
    
        if ($this->minimumBalance < 0) {
            $vr->addError('minimum_balance', 'Minimum balance cannot be negative');
        }
    
        if ($this->interestRate < 0 || $this->interestRate > 100) {
            $vr->addError('interest_rate', 'Interest rate must be between 0 and 100');
        }
    
        if ($this->targetAmount !== null && $this->targetAmount <= 0) {
            $vr->addError('target_amount', 'Target amount must be positive if specified');
        }
    
        if ($this->monthlyTarget !== null && $this->monthlyTarget <= 0) {
            $vr->addError('monthly_target', 'Monthly target must be positive if specified');
        }
    
        if (empty($this->createdBy)) {
            $vr->addError('created_by', 'Created by is required');
        }
    
        return $vr;
    }

    /**
     * Check if the model is valid
     */
    public function isValid(): bool
    {
        return $this->validate()->isValid();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->accountId;
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getMinimumBalance(): float
    {
        return $this->minimumBalance;
    }

    public function getInterestRate(): float
    {
        return $this->interestRate;
    }

    public function getInterestCalculation(): string
    {
        return $this->interestCalculation;
    }

    public function getLastInterestDate(): ?DateTime
    {
        return $this->lastInterestDate;
    }

    public function getMaturityDate(): ?DateTime
    {
        return $this->maturityDate;
    }

    public function getTargetAmount(): ?float
    {
        return $this->targetAmount;
    }

    public function getMonthlyTarget(): ?float
    {
        return $this->monthlyTarget;
    }

    public function isAutoDeduct(): bool
    {
        return $this->autoDeduct;
    }

    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }

    public function getOpeningDate(): DateTime
    {
        return $this->openingDate;
    }

    public function getClosingDate(): ?DateTime
    {
        return $this->closingDate;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->accountId = $id;
    }

    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function setMemberId(int $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function setAccountNumber(string $accountNumber): void
    {
        $this->accountNumber = $accountNumber;
    }

    public function setAccountType(string $accountType): void
    {
        if (!in_array($accountType, self::ACCOUNT_TYPES)) {
            throw new ValidationException('Invalid account type');
        }
        $this->accountType = $accountType;
    }

    public function setAccountName(string $accountName): void
    {
        $this->accountName = $accountName;
    }

    public function setBalance(float $balance): void
    {
        if ($balance < 0) {
            throw new ValidationException('Balance cannot be negative');
        }
        $this->balance = $balance;
    }

    public function setMinimumBalance(float $minimumBalance): void
    {
        if ($minimumBalance < 0) {
            throw new ValidationException('Minimum balance cannot be negative');
        }
        $this->minimumBalance = $minimumBalance;
    }

    public function setInterestRate(float $interestRate): void
    {
        if ($interestRate < 0 || $interestRate > 100) {
            throw new ValidationException('Interest rate must be between 0 and 100');
        }
        $this->interestRate = $interestRate;
    }

    public function setInterestCalculation(string $interestCalculation): void
    {
        if (!in_array($interestCalculation, self::INTEREST_CALCULATIONS)) {
            throw new ValidationException('Invalid interest calculation method');
        }
        $this->interestCalculation = $interestCalculation;
    }

    public function setLastInterestDate(?DateTime $lastInterestDate): void
    {
        $this->lastInterestDate = $lastInterestDate;
    }

    public function setMaturityDate(?DateTime $maturityDate): void
    {
        $this->maturityDate = $maturityDate;
    }

    public function setTargetAmount(?float $targetAmount): void
    {
        if ($targetAmount !== null && $targetAmount <= 0) {
            throw new ValidationException('Target amount must be positive if specified');
        }
        $this->targetAmount = $targetAmount;
    }

    public function setMonthlyTarget(?float $monthlyTarget): void
    {
        if ($monthlyTarget !== null && $monthlyTarget <= 0) {
            throw new ValidationException('Monthly target must be positive if specified');
        }
        $this->monthlyTarget = $monthlyTarget;
    }

    public function setAutoDeduct(bool $autoDeduct): void
    {
        $this->autoDeduct = $autoDeduct;
    }

    public function setAccountStatus(string $accountStatus): void
    {
        if (!in_array($accountStatus, self::ACCOUNT_STATUSES)) {
            throw new ValidationException('Invalid account status');
        }
        $this->accountStatus = $accountStatus;
    }

    public function setOpeningDate(DateTime $openingDate): void
    {
        $this->openingDate = $openingDate;
    }

    public function setClosingDate(?DateTime $closingDate): void
    {
        $this->closingDate = $closingDate;
    }

    public function setCreatedBy(int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function setUpdatedBy(?int $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function setCreatedAt(?DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    // Business logic methods

    /**
     * Check if account can be closed
     */
    public function canBeClosed(): bool
    {
        return in_array($this->accountStatus, ['Active', 'Inactive', 'Suspended']);
    }

    /**
     * Check if account allows withdrawals
     */
    public function allowsWithdrawals(): bool
    {
        return $this->accountStatus === 'Active';
    }

    /**
     * Check if account allows deposits
     */
    public function allowsDeposits(): bool
    {
        return in_array($this->accountStatus, ['Active', 'Inactive']);
    }

    /**
     * Check if account has matured (for fixed deposits)
     */
    public function hasMatured(): bool
    {
        if ($this->accountType !== 'Fixed' || !$this->maturityDate) {
            return false;
        }
        
        return $this->maturityDate <= new DateTime();
    }

    /**
     * Check if account meets target (for target savings)
     */
    public function hasMetTarget(): bool
    {
        if ($this->accountType !== 'Target' || !$this->targetAmount) {
            return false;
        }
        
        return $this->balance >= $this->targetAmount;
    }

    /**
     * Get days until maturity
     */
    public function getDaysUntilMaturity(): ?int
    {
        if (!$this->maturityDate) {
            return null;
        }
        
        $today = new DateTime();
        $interval = $today->diff($this->maturityDate);
        
        return $interval->invert ? 0 : $interval->days;
    }

    /**
     * Calculate progress towards target (percentage)
     */
    public function getTargetProgress(): ?float
    {
        if (!$this->targetAmount || $this->targetAmount <= 0) {
            return null;
        }
        
        return min(100, ($this->balance / $this->targetAmount) * 100);
    }

    /**
     * Get formatted balance
     */
    public function getFormattedBalance(): string
    {
        return number_format($this->balance, 2);
    }

    /**
     * Get account type display name
     */
    public function getAccountTypeDisplayName(): string
    {
        return match($this->accountType) {
            'Regular' => 'Regular Savings',
            'Fixed' => 'Fixed Deposit',
            'Target' => 'Target Savings',
            'Emergency' => 'Emergency Fund',
            'Retirement' => 'Retirement Savings',
            default => $this->accountType
        };
    }

    /**
     * Get status display name with color class
     */
    public function getStatusDisplayInfo(): array
    {
        return match($this->accountStatus) {
            'Active' => ['name' => 'Active', 'class' => 'success'],
            'Inactive' => ['name' => 'Inactive', 'class' => 'warning'],
            'Suspended' => ['name' => 'Suspended', 'class' => 'danger'],
            'Closed' => ['name' => 'Closed', 'class' => 'secondary'],
            'Matured' => ['name' => 'Matured', 'class' => 'info'],
            default => ['name' => $this->accountStatus, 'class' => 'secondary']
        };
    }

    /**
     * Get available account types for forms
     */
    public static function getAccountTypes(): array
    {
        return self::ACCOUNT_TYPES;
    }

    /**
     * Get available account statuses for forms
     */
    public static function getAccountStatuses(): array
    {
        return self::ACCOUNT_STATUSES;
    }

    /**
     * Get available interest calculation methods for forms
     */
    public static function getInterestCalculationMethods(): array
    {
        return self::INTEREST_CALCULATIONS;
    }
}