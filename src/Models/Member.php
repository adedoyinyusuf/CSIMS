<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\DTOs\ValidationResult;
use CSIMS\Services\SecurityService;

/**
 * Member Model
 * 
 * Represents a cooperative society member
 */
class Member implements ModelInterface
{
    private ?int $id = null;
    private string $firstName = '';
    private string $lastName = '';
    private string $email = '';
    private ?string $phone = null;
    private ?string $ippis = null;
    private ?string $username = null;
    private ?string $passwordHash = null;
    private string $status = 'pending';
    private ?string $memberType = null;
    private ?int $memberTypeId = null;
    private ?string $gender = null;
    private ?string $dateOfBirth = null;
    private ?string $address = null;
    private ?string $occupation = null;
    private ?int $membershipTypeId = null;
    private ?string $joinDate = null;
    private ?string $expiryDate = null;
    private ?string $photo = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
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
            'member_id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'ippis_no' => $this->ippis,
            'username' => $this->username,
            'password' => $this->passwordHash,
            'status' => $this->status,
            'member_type' => $this->memberType,
            'member_type_id' => $this->memberTypeId,
            'gender' => $this->gender,
            'dob' => $this->dateOfBirth,
            'address' => $this->address,
            'occupation' => $this->occupation,
            'membership_type_id' => $this->membershipTypeId,
            'join_date' => $this->joinDate,
            'expiry_date' => $this->expiryDate,
            'photo' => $this->photo,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
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
        $this->id = $data['member_id'] ?? $data['id'] ?? null;
        $this->firstName = $data['first_name'] ?? '';
        $this->lastName = $data['last_name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->phone = $data['phone'] ?? null;
        $this->ippis = $data['ippis_no'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->passwordHash = $data['password'] ?? null;
        $this->status = $data['status'] ?? 'pending';
        $this->memberType = $data['member_type'] ?? $data['member_type_label'] ?? null;
        $this->memberTypeId = $data['member_type_id'] ?? null;
        $this->gender = $data['gender'] ?? null;
        $this->dateOfBirth = $data['dob'] ?? $data['date_of_birth'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->occupation = $data['occupation'] ?? null;
        $this->membershipTypeId = $data['membership_type_id'] ?? null;
        $this->joinDate = $data['join_date'] ?? null;
        $this->expiryDate = $data['expiry_date'] ?? null;
        $this->photo = $data['photo'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
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
            'first_name' => 'required|min:2|max:50',
            'last_name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'phone' => 'min:10|max:15',
            'ippis_no' => 'min:6|max:10',
            'username' => 'min:3|max:50',
            'status' => 'required'
        ];
        
        return $securityService->validateInput($this->toArray(), $rules);
    }
    
    /**
     * Get full name
     * 
     * @return string
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
    
    /**
     * Check if member is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
    
    /**
     * Check if membership is expired
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expiryDate) {
            return false;
        }
        
        return strtotime($this->expiryDate) < time();
    }
    
    /**
     * Get days until expiry
     * 
     * @return int|null
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiryDate) {
            return null;
        }
        
        $expiryTimestamp = strtotime($this->expiryDate);
        $today = strtotime('today');
        
        return (int) round(($expiryTimestamp - $today) / (60 * 60 * 24));
    }
    
    // Getters
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getIppis(): ?string { return $this->ippis; }
    public function getUsername(): ?string { return $this->username; }
    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function getStatus(): string { return $this->status; }
    public function getMemberType(): ?string { return $this->memberType; }
    public function getMemberTypeId(): ?int { return $this->memberTypeId; }
    public function getGender(): ?string { return $this->gender; }
    public function getDateOfBirth(): ?string { return $this->dateOfBirth; }
    public function getAddress(): ?string { return $this->address; }
    public function getOccupation(): ?string { return $this->occupation; }
    public function getMembershipTypeId(): ?int { return $this->membershipTypeId; }
    public function getJoinDate(): ?string { return $this->joinDate; }
    public function getExpiryDate(): ?string { return $this->expiryDate; }
    public function getPhoto(): ?string { return $this->photo; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    
    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
    public function setIppis(?string $ippis): self { $this->ippis = $ippis; return $this; }
    public function setUsername(?string $username): self { $this->username = $username; return $this; }
    public function setPasswordHash(?string $passwordHash): self { $this->passwordHash = $passwordHash; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setMemberType(?string $memberType): self { $this->memberType = $memberType; return $this; }
    public function setMemberTypeId(?int $memberTypeId): self { $this->memberTypeId = $memberTypeId; return $this; }
    public function setGender(?string $gender): self { $this->gender = $gender; return $this; }
    public function setDateOfBirth(?string $dateOfBirth): self { $this->dateOfBirth = $dateOfBirth; return $this; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function setOccupation(?string $occupation): self { $this->occupation = $occupation; return $this; }
    public function setMembershipTypeId(?int $membershipTypeId): self { $this->membershipTypeId = $membershipTypeId; return $this; }
    public function setJoinDate(?string $joinDate): self { $this->joinDate = $joinDate; return $this; }
    public function setExpiryDate(?string $expiryDate): self { $this->expiryDate = $expiryDate; return $this; }
    public function setPhoto(?string $photo): self { $this->photo = $photo; return $this; }
}
