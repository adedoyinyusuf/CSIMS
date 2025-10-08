<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\DTOs\ValidationResult;

/**
 * Share Capital Model
 * 
 * Represents share capital holdings in the cooperative society
 */
class ShareCapital implements ModelInterface
{
    private ?int $id = null;
    private int $memberId;
    private string $shareType = 'Ordinary';
    private int $numberOfShares = 0;
    private float $shareValue = 0.0;
    private float $totalValue = 0.0;
    private string $purchaseDate;
    private string $status = 'Active';
    private ?string $certificateNumber = null;
    private ?string $transferFromMemberId = null;
    private ?string $notes = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    // Related data for joins
    private ?string $memberFirstName = null;
    private ?string $memberLastName = null;
    private ?string $memberEmail = null;
    
    public const SHARE_TYPES = [
        'Ordinary',
        'Preference',
        'Deferred',
        'Special'
    ];
    
    public const STATUSES = [
        'Active',
        'Inactive',
        'Transferred',
        'Redeemed'
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
            'share_id' => $this->id,
            'member_id' => $this->memberId,
            'share_type' => $this->shareType,
            'number_of_shares' => $this->numberOfShares,
            'share_value' => $this->shareValue,
            'total_value' => $this->totalValue,
            'purchase_date' => $this->purchaseDate,
            'status' => $this->status,
            'certificate_number' => $this->certificateNumber,
            'transfer_from_member_id' => $this->transferFromMemberId,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            // Related data
            'member_first_name' => $this->memberFirstName,
            'member_last_name' => $this->memberLastName,
            'member_email' => $this->memberEmail,
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
        $this->id = $data['share_id'] ?? $data['id'] ?? null;
        $this->memberId = (int)($data['member_id'] ?? 0);
        $this->shareType = $data['share_type'] ?? 'Ordinary';
        $this->numberOfShares = (int)($data['number_of_shares'] ?? 0);
        $this->shareValue = (float)($data['share_value'] ?? 0.0);
        $this->totalValue = (float)($data['total_value'] ?? 0.0);
        $this->purchaseDate = $data['purchase_date'] ?? '';
        $this->status = $data['status'] ?? 'Active';
        $this->certificateNumber = $data['certificate_number'] ?? null;
        $this->transferFromMemberId = $data['transfer_from_member_id'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        
        // Related data
        $this->memberFirstName = $data['member_first_name'] ?? null;
        $this->memberLastName = $data['member_last_name'] ?? null;
        $this->memberEmail = $data['member_email'] ?? null;
        
        // Auto-calculate total value if not provided
        if ($this->totalValue <= 0 && $this->numberOfShares > 0 && $this->shareValue > 0) {
            $this->totalValue = $this->numberOfShares * $this->shareValue;
        }
    }
    
    /**
     * Validate the model data
     */
    public function validate(): ValidationResult
    {
        $errors = [];
        
        // Validate member ID
        if ($this->memberId <= 0) {
            $errors[] = 'Valid member ID is required';
        }
        
        // Validate share type
        if (!in_array($this->shareType, self::SHARE_TYPES)) {
            $errors[] = 'Invalid share type';
        }
        
        // Validate number of shares
        if ($this->numberOfShares <= 0) {
            $errors[] = 'Number of shares must be greater than 0';
        }
        
        if ($this->numberOfShares > 10000) {
            $errors[] = 'Number of shares cannot exceed 10,000';
        }
        
        // Validate share value
        if ($this->shareValue <= 0) {
            $errors[] = 'Share value must be greater than 0';
        }
        
        // Validate purchase date
        if (empty($this->purchaseDate)) {
            $errors[] = 'Purchase date is required';
        } elseif (!strtotime($this->purchaseDate)) {
            $errors[] = 'Invalid purchase date format';
        } elseif (strtotime($this->purchaseDate) > time()) {
            $errors[] = 'Purchase date cannot be in the future';
        }
        
        // Validate status
        if (!in_array($this->status, self::STATUSES)) {
            $errors[] = 'Invalid status';
        }
        
        // Validate certificate number format if provided
        if ($this->certificateNumber && !preg_match('/^[A-Z0-9-]+$/', $this->certificateNumber)) {
            $errors[] = 'Certificate number must contain only letters, numbers, and hyphens';
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
    
    /**
     * Calculate total value
     */
    public function calculateTotalValue(): float
    {
        return $this->numberOfShares * $this->shareValue;
    }
    
    /**
     * Update total value based on current shares and value
     */
    public function updateTotalValue(): self
    {
        $this->totalValue = $this->calculateTotalValue();
        return $this;
    }
    
    /**
     * Get member full name
     */
    public function getMemberFullName(): string
    {
        if ($this->memberFirstName && $this->memberLastName) {
            return trim($this->memberFirstName . ' ' . $this->memberLastName);
        }
        return 'Unknown Member';
    }
    
    /**
     * Get formatted total value
     */
    public function getFormattedTotalValue(string $currency = '₦'): string
    {
        return $currency . number_format($this->totalValue, 2);
    }
    
    /**
     * Get formatted share value
     */
    public function getFormattedShareValue(string $currency = '₦'): string
    {
        return $currency . number_format($this->shareValue, 2);
    }
    
    /**
     * Check if shares are active
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
    
    /**
     * Check if shares have been transferred
     */
    public function isTransferred(): bool
    {
        return $this->status === 'Transferred';
    }
    
    /**
     * Generate certificate number
     */
    public function generateCertificateNumber(string $prefix = 'CERT'): string
    {
        if (empty($this->certificateNumber)) {
            $year = date('Y', strtotime($this->purchaseDate ?: 'now'));
            $memberId = str_pad($this->memberId, 4, '0', STR_PAD_LEFT);
            $shareId = str_pad($this->id ?: rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            $this->certificateNumber = "{$prefix}-{$year}-{$memberId}-{$shareId}";
        }
        
        return $this->certificateNumber;
    }
    
    /**
     * Get share ownership percentage in the cooperative
     */
    public function getOwnershipPercentage(float $totalCooperativeShares): float
    {
        if ($totalCooperativeShares <= 0) {
            return 0.0;
        }
        
        return round(($this->numberOfShares / $totalCooperativeShares) * 100, 4);
    }
    
    /**
     * Calculate potential dividend based on dividend rate
     */
    public function calculateDividend(float $dividendRatePercentage): float
    {
        return ($dividendRatePercentage / 100) * $this->totalValue;
    }
    
    // Getters
    public function getMemberId(): int { return $this->memberId; }
    public function getShareType(): string { return $this->shareType; }
    public function getNumberOfShares(): int { return $this->numberOfShares; }
    public function getShareValue(): float { return $this->shareValue; }
    public function getTotalValue(): float { return $this->totalValue; }
    public function getPurchaseDate(): string { return $this->purchaseDate; }
    public function getStatus(): string { return $this->status; }
    public function getCertificateNumber(): ?string { return $this->certificateNumber; }
    public function getTransferFromMemberId(): ?string { return $this->transferFromMemberId; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    
    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setMemberId(int $memberId): self { $this->memberId = $memberId; return $this; }
    public function setShareType(string $shareType): self { $this->shareType = $shareType; return $this; }
    public function setNumberOfShares(int $numberOfShares): self { 
        $this->numberOfShares = $numberOfShares;
        $this->updateTotalValue();
        return $this;
    }
    public function setShareValue(float $shareValue): self { 
        $this->shareValue = $shareValue;
        $this->updateTotalValue();
        return $this;
    }
    public function setTotalValue(float $totalValue): self { $this->totalValue = $totalValue; return $this; }
    public function setPurchaseDate(string $purchaseDate): self { $this->purchaseDate = $purchaseDate; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setCertificateNumber(?string $certificateNumber): self { $this->certificateNumber = $certificateNumber; return $this; }
    public function setTransferFromMemberId(?string $transferFromMemberId): self { $this->transferFromMemberId = $transferFromMemberId; return $this; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
}