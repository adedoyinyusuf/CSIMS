<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Services\SecurityService;
use CSIMS\DTOs\ValidationResult;

/**
 * Contribution Model
 * 
 * Represents member contributions in the cooperative society
 */
class Contribution implements ModelInterface
{
    private ?int $contributionId = null;
    private int $memberId;
    private float $amount;
    private string $contributionDate;
    private string $contributionType;
    private string $paymentMethod;
    private ?string $receiptNumber = null;
    private ?string $notes = null;
    private string $status = 'Confirmed';
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    // Member information (joined from members table)
    private ?string $memberFirstName = null;
    private ?string $memberLastName = null;
    private ?string $memberEmail = null;
    
    // Constants
    public const CONTRIBUTION_TYPES = [
        'Monthly',
        'Special',
        'Share Capital',
        'Development Levy',
        'Entrance Fee',
        'Other'
    ];
    
    public const PAYMENT_METHODS = [
        'Cash',
        'Bank Transfer',
        'Mobile Money',
        'Cheque',
        'Direct Debit',
        'Card Payment'
    ];
    
    public const STATUSES = [
        'Pending',
        'Confirmed',
        'Rejected',
        'Reversed'
    ];
    
    /**
     * Get contribution ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->contributionId;
    }
    
    /**
     * Set contribution ID
     * 
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->contributionId = $id;
        return $this;
    }
    
    /**
     * Get member ID
     * 
     * @return int
     */
    public function getMemberId(): int
    {
        return $this->memberId;
    }
    
    /**
     * Set member ID
     * 
     * @param int $memberId
     * @return self
     */
    public function setMemberId(int $memberId): self
    {
        $this->memberId = $memberId;
        return $this;
    }
    
    /**
     * Get amount
     * 
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }
    
    /**
     * Set amount
     * 
     * @param float $amount
     * @return self
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }
    
    /**
     * Get contribution date
     * 
     * @return string
     */
    public function getContributionDate(): string
    {
        return $this->contributionDate;
    }
    
    /**
     * Set contribution date
     * 
     * @param string $date
     * @return self
     */
    public function setContributionDate(string $date): self
    {
        $this->contributionDate = $date;
        return $this;
    }
    
    /**
     * Get contribution type
     * 
     * @return string
     */
    public function getContributionType(): string
    {
        return $this->contributionType;
    }
    
    /**
     * Set contribution type
     * 
     * @param string $type
     * @return self
     */
    public function setContributionType(string $type): self
    {
        $this->contributionType = $type;
        return $this;
    }
    
    /**
     * Get payment method
     * 
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }
    
    /**
     * Set payment method
     * 
     * @param string $method
     * @return self
     */
    public function setPaymentMethod(string $method): self
    {
        $this->paymentMethod = $method;
        return $this;
    }
    
    /**
     * Get receipt number
     * 
     * @return string|null
     */
    public function getReceiptNumber(): ?string
    {
        return $this->receiptNumber;
    }
    
    /**
     * Set receipt number
     * 
     * @param string|null $receiptNumber
     * @return self
     */
    public function setReceiptNumber(?string $receiptNumber): self
    {
        $this->receiptNumber = $receiptNumber;
        return $this;
    }
    
    /**
     * Get notes
     * 
     * @return string|null
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }
    
    /**
     * Set notes
     * 
     * @param string|null $notes
     * @return self
     */
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }
    
    /**
     * Get status
     * 
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }
    
    /**
     * Set status
     * 
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
    
    /**
     * Get created at timestamp
     * 
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    
    /**
     * Set created at timestamp
     * 
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    /**
     * Get updated at timestamp
     * 
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
    
    /**
     * Set updated at timestamp
     * 
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    
    /**
     * Get member first name
     * 
     * @return string|null
     */
    public function getMemberFirstName(): ?string
    {
        return $this->memberFirstName;
    }
    
    /**
     * Set member first name
     * 
     * @param string|null $firstName
     * @return self
     */
    public function setMemberFirstName(?string $firstName): self
    {
        $this->memberFirstName = $firstName;
        return $this;
    }
    
    /**
     * Get member last name
     * 
     * @return string|null
     */
    public function getMemberLastName(): ?string
    {
        return $this->memberLastName;
    }
    
    /**
     * Set member last name
     * 
     * @param string|null $lastName
     * @return self
     */
    public function setMemberLastName(?string $lastName): self
    {
        $this->memberLastName = $lastName;
        return $this;
    }
    
    /**
     * Get member email
     * 
     * @return string|null
     */
    public function getMemberEmail(): ?string
    {
        return $this->memberEmail;
    }
    
    /**
     * Set member email
     * 
     * @param string|null $email
     * @return self
     */
    public function setMemberEmail(?string $email): self
    {
        $this->memberEmail = $email;
        return $this;
    }
    
    /**
     * Get member full name
     * 
     * @return string
     */
    public function getMemberFullName(): string
    {
        return trim(($this->memberFirstName ?? '') . ' ' . ($this->memberLastName ?? ''));
    }
    
    /**
     * Convert model to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'contribution_id' => $this->contributionId,
            'member_id' => $this->memberId,
            'amount' => $this->amount,
            'contribution_date' => $this->contributionDate,
            'contribution_type' => $this->contributionType,
            'payment_method' => $this->paymentMethod,
            'receipt_number' => $this->receiptNumber,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'member_first_name' => $this->memberFirstName,
            'member_last_name' => $this->memberLastName,
            'member_email' => $this->memberEmail,
            'member_full_name' => $this->getMemberFullName()
        ];
    }
    
    /**
     * Create model from array
     * 
     * @param array $data
     * @return self
     */
    public function fromArray(array $data): self
    {
        if (isset($data['contribution_id'])) {
            $this->contributionId = (int)$data['contribution_id'];
        }
        
        if (isset($data['member_id'])) {
            $this->memberId = (int)$data['member_id'];
        }
        
        if (isset($data['amount'])) {
            $this->amount = (float)$data['amount'];
        }
        
        if (isset($data['contribution_date'])) {
            $this->contributionDate = $data['contribution_date'];
        }
        
        if (isset($data['contribution_type'])) {
            $this->contributionType = $data['contribution_type'];
        }
        
        if (isset($data['payment_method'])) {
            $this->paymentMethod = $data['payment_method'];
        }
        
        if (isset($data['receipt_number'])) {
            $this->receiptNumber = $data['receipt_number'];
        }
        
        if (isset($data['notes'])) {
            $this->notes = $data['notes'];
        }
        
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
        
        if (isset($data['created_at'])) {
            $this->createdAt = $data['created_at'];
        }
        
        if (isset($data['updated_at'])) {
            $this->updatedAt = $data['updated_at'];
        }
        
        // Member information
        if (isset($data['member_first_name'])) {
            $this->memberFirstName = $data['member_first_name'];
        }
        
        if (isset($data['member_last_name'])) {
            $this->memberLastName = $data['member_last_name'];
        }
        
        if (isset($data['member_email'])) {
            $this->memberEmail = $data['member_email'];
        }
        
        return $this;
    }
    
    /**
     * Create instance from array
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return (new static())->fromArray($data);
    }
    
    /**
     * Validate contribution data
     * 
     * @param SecurityService|null $securityService
     * @return ValidationResult
     */
    public function validate(?SecurityService $securityService = null): ValidationResult
    {
        $errors = [];
        $securityService = $securityService ?? new SecurityService();
        
        // Validate member ID
        if (!isset($this->memberId) || $this->memberId <= 0) {
            $errors[] = 'Valid member ID is required';
        }
        
        // Validate amount
        if (!isset($this->amount) || $this->amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if (isset($this->amount) && $this->amount > 1000000) {
            $errors[] = 'Amount cannot exceed 1,000,000';
        }
        
        // Validate contribution date
        if (!isset($this->contributionDate) || empty($this->contributionDate)) {
            $errors[] = 'Contribution date is required';
        } elseif (!$securityService->isValidDate($this->contributionDate)) {
            $errors[] = 'Invalid contribution date format';
        } elseif (strtotime($this->contributionDate) > time()) {
            $errors[] = 'Contribution date cannot be in the future';
        }
        
        // Validate contribution type
        if (!isset($this->contributionType) || empty($this->contributionType)) {
            $errors[] = 'Contribution type is required';
        } elseif (!in_array($this->contributionType, self::CONTRIBUTION_TYPES)) {
            $errors[] = 'Invalid contribution type';
        }
        
        // Validate payment method
        if (!isset($this->paymentMethod) || empty($this->paymentMethod)) {
            $errors[] = 'Payment method is required';
        } elseif (!in_array($this->paymentMethod, self::PAYMENT_METHODS)) {
            $errors[] = 'Invalid payment method';
        }
        
        // Validate status
        if (isset($this->status) && !in_array($this->status, self::STATUSES)) {
            $errors[] = 'Invalid status';
        }
        
        // Validate receipt number if provided
        if (isset($this->receiptNumber) && !empty($this->receiptNumber)) {
            if (strlen($this->receiptNumber) > 50) {
                $errors[] = 'Receipt number cannot exceed 50 characters';
            }
        }
        
        // Validate notes if provided
        if (isset($this->notes) && strlen($this->notes) > 500) {
            $errors[] = 'Notes cannot exceed 500 characters';
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
    
    /**
     * Check if contribution can be edited
     * 
     * @return bool
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['Pending', 'Rejected']);
    }
    
    /**
     * Check if contribution can be deleted
     * 
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['Pending', 'Rejected']);
    }
    
    /**
     * Check if contribution is confirmed
     * 
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'Confirmed';
    }
    
    /**
     * Check if contribution is pending
     * 
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }
    
    /**
     * Get formatted amount
     * 
     * @param string $currency
     * @return string
     */
    public function getFormattedAmount(string $currency = '$'): string
    {
        return $currency . number_format($this->amount, 2);
    }
    
    /**
     * Get formatted contribution date
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedContributionDate(string $format = 'Y-m-d'): string
    {
        return date($format, strtotime($this->contributionDate));
    }
    
    /**
     * Generate receipt number if not provided
     * 
     * @param string $prefix
     * @return string
     */
    public function generateReceiptNumber(string $prefix = 'CONT'): string
    {
        if (empty($this->receiptNumber)) {
            $this->receiptNumber = $prefix . date('Ymd') . sprintf('%06d', $this->contributionId ?? rand(100000, 999999));
        }
        
        return $this->receiptNumber;
    }
    
    /**
     * Check if this is a monthly contribution
     * 
     * @return bool
     */
    public function isMonthlyContribution(): bool
    {
        return $this->contributionType === 'Monthly';
    }
    
    /**
     * Check if this is a special contribution
     * 
     * @return bool
     */
    public function isSpecialContribution(): bool
    {
        return $this->contributionType === 'Special';
    }
    
    /**
     * Get contribution month and year
     * 
     * @return array
     */
    public function getContributionPeriod(): array
    {
        $date = new \DateTime($this->contributionDate);
        return [
            'month' => $date->format('n'),
            'year' => $date->format('Y'),
            'month_name' => $date->format('F'),
            'formatted' => $date->format('F Y')
        ];
    }
}
