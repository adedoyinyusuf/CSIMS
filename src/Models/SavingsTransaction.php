<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Exceptions\ValidationException;
use DateTime;

/**
 * Savings Transaction Model
 * 
 * Represents a transaction in a savings account
 */
class SavingsTransaction implements ModelInterface
{
    private ?int $transactionId = null;
    private int $accountId;
    private int $memberId;
    private string $transactionType;
    private float $amount;
    private float $balanceBefore;
    private float $balanceAfter;
    private DateTime $transactionDate;
    private string $transactionTime;
    private string $paymentMethod = 'Cash';
    private ?string $referenceNumber = null;
    private ?string $description = null;
    private int $processedBy;
    private ?int $approvedBy = null;
    private string $transactionStatus = 'Pending';
    private ?string $reversalReference = null;
    private float $feesCharged = 0.00;
    private ?string $receiptNumber = null;
    private ?string $externalReference = null;
    private ?string $notes = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    // Valid transaction types
    private const TRANSACTION_TYPES = [
        'Deposit', 'Withdrawal', 'Interest', 'Fee', 
        'Transfer_In', 'Transfer_Out', 'Adjustment'
    ];
    
    // Valid payment methods
    private const PAYMENT_METHODS = [
        'Cash', 'Bank_Transfer', 'Cheque', 'Salary_Deduction', 
        'Mobile_Money', 'Online', 'Transfer'
    ];
    
    // Valid transaction statuses
    private const TRANSACTION_STATUSES = [
        'Pending', 'Completed', 'Failed', 'Reversed', 'Cancelled'
    ];

    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->fromArray($data);
        }
        
        if (!isset($this->transactionDate)) {
            $this->transactionDate = new DateTime();
        }
        
        if (!isset($this->transactionTime)) {
            $this->transactionTime = date('H:i:s');
        }
    }

    /**
     * Create instance from array data
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->fillFromArray($data);
        return $instance;
    }

    /**
     * Fill instance properties from array
     */
    public function fromArray(array $data): void
    {
        $this->fillFromArray($data);
    }

    private function fillFromArray(array $data): void
    {
        $this->transactionId = $data['transaction_id'] ?? null;
        $this->accountId = $data['account_id'] ?? 0;
        $this->memberId = $data['member_id'] ?? 0;
        $this->transactionType = $data['transaction_type'] ?? '';
        $this->amount = (float)($data['amount'] ?? 0.00);
        $this->balanceBefore = (float)($data['balance_before'] ?? 0.00);
        $this->balanceAfter = (float)($data['balance_after'] ?? 0.00);
        $this->transactionDate = $data['transaction_date'] ? new DateTime($data['transaction_date']) : new DateTime();
        $this->transactionTime = $data['transaction_time'] ?? date('H:i:s');
        $this->paymentMethod = $data['payment_method'] ?? 'Cash';
        $this->referenceNumber = $data['reference_number'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->processedBy = $data['processed_by'] ?? 0;
        $this->approvedBy = $data['approved_by'] ?? null;
        $this->transactionStatus = $data['transaction_status'] ?? 'Pending';
        $this->reversalReference = $data['reversal_reference'] ?? null;
        $this->feesCharged = (float)($data['fees_charged'] ?? 0.00);
        $this->receiptNumber = $data['receipt_number'] ?? null;
        $this->externalReference = $data['external_reference'] ?? null;
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
            'transaction_id' => $this->transactionId,
            'account_id' => $this->accountId,
            'member_id' => $this->memberId,
            'transaction_type' => $this->transactionType,
            'amount' => $this->amount,
            'balance_before' => $this->balanceBefore,
            'balance_after' => $this->balanceAfter,
            'transaction_date' => $this->transactionDate->format('Y-m-d'),
            'transaction_time' => $this->transactionTime,
            'payment_method' => $this->paymentMethod,
            'reference_number' => $this->referenceNumber,
            'description' => $this->description,
            'processed_by' => $this->processedBy,
            'approved_by' => $this->approvedBy,
            'transaction_status' => $this->transactionStatus,
            'reversal_reference' => $this->reversalReference,
            'fees_charged' => $this->feesCharged,
            'receipt_number' => $this->receiptNumber,
            'external_reference' => $this->externalReference,
            'notes' => $this->notes,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Validate the model
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->accountId)) {
            $errors[] = 'Account ID is required';
        }

        if (empty($this->memberId)) {
            $errors[] = 'Member ID is required';
        }

        if (empty($this->transactionType)) {
            $errors[] = 'Transaction type is required';
        } elseif (!in_array($this->transactionType, self::TRANSACTION_TYPES)) {
            $errors[] = 'Invalid transaction type. Must be one of: ' . implode(', ', self::TRANSACTION_TYPES);
        }

        if ($this->amount <= 0) {
            $errors[] = 'Amount must be positive';
        }

        if (!in_array($this->paymentMethod, self::PAYMENT_METHODS)) {
            $errors[] = 'Invalid payment method. Must be one of: ' . implode(', ', self::PAYMENT_METHODS);
        }

        if (!in_array($this->transactionStatus, self::TRANSACTION_STATUSES)) {
            $errors[] = 'Invalid transaction status. Must be one of: ' . implode(', ', self::TRANSACTION_STATUSES);
        }

        if ($this->balanceBefore < 0) {
            $errors[] = 'Balance before cannot be negative';
        }

        if ($this->balanceAfter < 0) {
            $errors[] = 'Balance after cannot be negative';
        }

        if ($this->feesCharged < 0) {
            $errors[] = 'Fees charged cannot be negative';
        }

        if (empty($this->processedBy)) {
            $errors[] = 'Processed by is required';
        }

        return $errors;
    }

    /**
     * Check if the model is valid
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    // Getters
    public function getId(): ?int
    {
        return $this->transactionId;
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionId;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBalanceBefore(): float
    {
        return $this->balanceBefore;
    }

    public function getBalanceAfter(): float
    {
        return $this->balanceAfter;
    }

    public function getTransactionDate(): DateTime
    {
        return $this->transactionDate;
    }

    public function getTransactionTime(): string
    {
        return $this->transactionTime;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getProcessedBy(): int
    {
        return $this->processedBy;
    }

    public function getApprovedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function getTransactionStatus(): string
    {
        return $this->transactionStatus;
    }

    public function getReversalReference(): ?string
    {
        return $this->reversalReference;
    }

    public function getFeesCharged(): float
    {
        return $this->feesCharged;
    }

    public function getReceiptNumber(): ?string
    {
        return $this->receiptNumber;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
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
        $this->transactionId = $id;
    }

    public function setTransactionId(int $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function setMemberId(int $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function setTransactionType(string $transactionType): void
    {
        if (!in_array($transactionType, self::TRANSACTION_TYPES)) {
            throw new ValidationException('Invalid transaction type');
        }
        $this->transactionType = $transactionType;
    }

    public function setAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new ValidationException('Amount must be positive');
        }
        $this->amount = $amount;
    }

    public function setBalanceBefore(float $balanceBefore): void
    {
        if ($balanceBefore < 0) {
            throw new ValidationException('Balance before cannot be negative');
        }
        $this->balanceBefore = $balanceBefore;
    }

    public function setBalanceAfter(float $balanceAfter): void
    {
        if ($balanceAfter < 0) {
            throw new ValidationException('Balance after cannot be negative');
        }
        $this->balanceAfter = $balanceAfter;
    }

    public function setTransactionDate(DateTime $transactionDate): void
    {
        $this->transactionDate = $transactionDate;
    }

    public function setTransactionTime(string $transactionTime): void
    {
        $this->transactionTime = $transactionTime;
    }

    public function setPaymentMethod(string $paymentMethod): void
    {
        if (!in_array($paymentMethod, self::PAYMENT_METHODS)) {
            throw new ValidationException('Invalid payment method');
        }
        $this->paymentMethod = $paymentMethod;
    }

    public function setReferenceNumber(?string $referenceNumber): void
    {
        $this->referenceNumber = $referenceNumber;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setProcessedBy(int $processedBy): void
    {
        $this->processedBy = $processedBy;
    }

    public function setApprovedBy(?int $approvedBy): void
    {
        $this->approvedBy = $approvedBy;
    }

    public function setTransactionStatus(string $transactionStatus): void
    {
        if (!in_array($transactionStatus, self::TRANSACTION_STATUSES)) {
            throw new ValidationException('Invalid transaction status');
        }
        $this->transactionStatus = $transactionStatus;
    }

    public function setReversalReference(?string $reversalReference): void
    {
        $this->reversalReference = $reversalReference;
    }

    public function setFeesCharged(float $feesCharged): void
    {
        if ($feesCharged < 0) {
            throw new ValidationException('Fees charged cannot be negative');
        }
        $this->feesCharged = $feesCharged;
    }

    public function setReceiptNumber(?string $receiptNumber): void
    {
        $this->receiptNumber = $receiptNumber;
    }

    public function setExternalReference(?string $externalReference): void
    {
        $this->externalReference = $externalReference;
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
     * Check if transaction is a credit (increases balance)
     */
    public function isCredit(): bool
    {
        return in_array($this->transactionType, ['Deposit', 'Interest', 'Transfer_In', 'Adjustment']);
    }

    /**
     * Check if transaction is a debit (decreases balance)
     */
    public function isDebit(): bool
    {
        return in_array($this->transactionType, ['Withdrawal', 'Fee', 'Transfer_Out']);
    }

    /**
     * Check if transaction can be reversed
     */
    public function canBeReversed(): bool
    {
        return $this->transactionStatus === 'Completed' && 
               empty($this->reversalReference) &&
               in_array($this->transactionType, ['Deposit', 'Withdrawal', 'Transfer_In', 'Transfer_Out']);
    }

    /**
     * Check if transaction requires approval
     */
    public function requiresApproval(): bool
    {
        return in_array($this->transactionType, ['Withdrawal', 'Adjustment', 'Transfer_Out']) ||
               $this->amount > 50000; // Large amounts require approval
    }

    /**
     * Get net amount (amount minus fees)
     */
    public function getNetAmount(): float
    {
        return $this->amount - $this->feesCharged;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2);
    }

    /**
     * Get formatted net amount
     */
    public function getFormattedNetAmount(): string
    {
        return number_format($this->getNetAmount(), 2);
    }

    /**
     * Get transaction type display name
     */
    public function getTransactionTypeDisplayName(): string
    {
        return match($this->transactionType) {
            'Deposit' => 'Deposit',
            'Withdrawal' => 'Withdrawal',
            'Interest' => 'Interest Credit',
            'Fee' => 'Fee Charge',
            'Transfer_In' => 'Transfer In',
            'Transfer_Out' => 'Transfer Out',
            'Adjustment' => 'Balance Adjustment',
            default => $this->transactionType
        };
    }

    /**
     * Get payment method display name
     */
    public function getPaymentMethodDisplayName(): string
    {
        return match($this->paymentMethod) {
            'Bank_Transfer' => 'Bank Transfer',
            'Salary_Deduction' => 'Salary Deduction',
            'Mobile_Money' => 'Mobile Money',
            default => $this->paymentMethod
        };
    }

    /**
     * Get status display info with color class
     */
    public function getStatusDisplayInfo(): array
    {
        return match($this->transactionStatus) {
            'Pending' => ['name' => 'Pending', 'class' => 'warning'],
            'Completed' => ['name' => 'Completed', 'class' => 'success'],
            'Failed' => ['name' => 'Failed', 'class' => 'danger'],
            'Reversed' => ['name' => 'Reversed', 'class' => 'secondary'],
            'Cancelled' => ['name' => 'Cancelled', 'class' => 'secondary'],
            default => ['name' => $this->transactionStatus, 'class' => 'secondary']
        ];
    }

    /**
     * Get transaction icon based on type
     */
    public function getTransactionIcon(): string
    {
        return match($this->transactionType) {
            'Deposit' => 'fa-plus-circle text-success',
            'Withdrawal' => 'fa-minus-circle text-danger',
            'Interest' => 'fa-percentage text-info',
            'Fee' => 'fa-exclamation-triangle text-warning',
            'Transfer_In' => 'fa-arrow-right text-success',
            'Transfer_Out' => 'fa-arrow-left text-danger',
            'Adjustment' => 'fa-edit text-secondary',
            default => 'fa-circle'
        };
    }

    /**
     * Get full transaction datetime
     */
    public function getTransactionDateTime(): DateTime
    {
        $dateTime = clone $this->transactionDate;
        $timeParts = explode(':', $this->transactionTime);
        $dateTime->setTime(
            (int)$timeParts[0], 
            (int)$timeParts[1], 
            isset($timeParts[2]) ? (int)$timeParts[2] : 0
        );
        return $dateTime;
    }

    /**
     * Get available transaction types for forms
     */
    public static function getTransactionTypes(): array
    {
        return self::TRANSACTION_TYPES;
    }

    /**
     * Get available payment methods for forms
     */
    public static function getPaymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    /**
     * Get available transaction statuses for forms
     */
    public static function getTransactionStatuses(): array
    {
        return self::TRANSACTION_STATUSES;
    }
}