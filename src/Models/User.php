<?php

namespace CSIMS\Models;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Services\SecurityService;
use CSIMS\DTOs\ValidationResult;

/**
 * User Model
 * 
 * Represents system users with authentication and authorization
 */
class User implements ModelInterface
{
    private ?int $userId = null;
    private string $username;
    private string $email;
    private string $passwordHash;
    private string $firstName;
    private string $lastName;
    private string $role = 'Officer';
    private string $status = 'Active';
    private ?string $lastLogin = null;
    private int $failedLoginAttempts = 0;
    private ?string $lockedUntil = null;
    private ?string $passwordResetToken = null;
    private ?string $passwordResetExpires = null;
    private bool $twoFactorEnabled = false;
    private ?string $twoFactorSecret = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    // Constants
    public const ROLES = [
        'Admin' => 'Administrator - Full system access',
        'Manager' => 'Manager - Management functions',
        'Officer' => 'Officer - Daily operations',
        'Viewer' => 'Viewer - Read-only access'
    ];
    
    public const STATUSES = [
        'Active',
        'Inactive',
        'Suspended'
    ];
    
    public const PERMISSIONS = [
        'Admin' => [
            'users.create', 'users.read', 'users.update', 'users.delete',
            'members.create', 'members.read', 'members.update', 'members.delete',
            'loans.create', 'loans.read', 'loans.update', 'loans.delete', 'loans.approve', 'loans.disburse',
            'contributions.create', 'contributions.read', 'contributions.update', 'contributions.delete', 'contributions.confirm',
            'reports.generate', 'settings.manage', 'system.admin'
        ],
        'Manager' => [
            'members.create', 'members.read', 'members.update',
            'loans.create', 'loans.read', 'loans.update', 'loans.approve', 'loans.disburse',
            'contributions.create', 'contributions.read', 'contributions.update', 'contributions.confirm',
            'reports.generate'
        ],
        'Officer' => [
            'members.create', 'members.read', 'members.update',
            'loans.create', 'loans.read', 'loans.update',
            'contributions.create', 'contributions.read', 'contributions.update',
            'reports.generate'
        ],
        'Viewer' => [
            'members.read', 'loans.read', 'contributions.read', 'reports.generate'
        ]
    ];
    
    /**
     * Get user ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->userId;
    }
    
    /**
     * Set user ID
     * 
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->userId = $id;
        return $this;
    }
    
    /**
     * Get username
     * 
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }
    
    /**
     * Set username
     * 
     * @param string $username
     * @return self
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }
    
    /**
     * Get email
     * 
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }
    
    /**
     * Set email
     * 
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
    
    /**
     * Get password hash
     * 
     * @return string
     */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
    
    /**
     * Set password hash
     * 
     * @param string $passwordHash
     * @return self
     */
    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }
    
    /**
     * Set password (will be hashed)
     * 
     * @param string $password
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
        return $this;
    }
    
    /**
     * Verify password
     * 
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }
    
    /**
     * Get first name
     * 
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    
    /**
     * Set first name
     * 
     * @param string $firstName
     * @return self
     */
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }
    
    /**
     * Get last name
     * 
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }
    
    /**
     * Set last name
     * 
     * @param string $lastName
     * @return self
     */
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
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
     * Get role
     * 
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }
    
    /**
     * Set role
     * 
     * @param string $role
     * @return self
     */
    public function setRole(string $role): self
    {
        $this->role = $role;
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
     * Get last login
     * 
     * @return string|null
     */
    public function getLastLogin(): ?string
    {
        return $this->lastLogin;
    }
    
    /**
     * Set last login
     * 
     * @param string|null $lastLogin
     * @return self
     */
    public function setLastLogin(?string $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }
    
    /**
     * Get failed login attempts
     * 
     * @return int
     */
    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }
    
    /**
     * Set failed login attempts
     * 
     * @param int $attempts
     * @return self
     */
    public function setFailedLoginAttempts(int $attempts): self
    {
        $this->failedLoginAttempts = $attempts;
        return $this;
    }
    
    /**
     * Increment failed login attempts
     * 
     * @return self
     */
    public function incrementFailedLogins(): self
    {
        $this->failedLoginAttempts++;
        return $this;
    }
    
    /**
     * Reset failed login attempts
     * 
     * @return self
     */
    public function resetFailedLogins(): self
    {
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
        return $this;
    }
    
    /**
     * Get locked until timestamp
     * 
     * @return string|null
     */
    public function getLockedUntil(): ?string
    {
        return $this->lockedUntil;
    }
    
    /**
     * Set locked until timestamp
     * 
     * @param string|null $lockedUntil
     * @return self
     */
    public function setLockedUntil(?string $lockedUntil): self
    {
        $this->lockedUntil = $lockedUntil;
        return $this;
    }
    
    /**
     * Check if account is locked
     * 
     * @return bool
     */
    public function isLocked(): bool
    {
        if (!$this->lockedUntil) {
            return false;
        }
        
        return strtotime($this->lockedUntil) > time();
    }
    
    /**
     * Lock account for specified minutes
     * 
     * @param int $minutes
     * @return self
     */
    public function lockAccount(int $minutes = 30): self
    {
        $this->lockedUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));
        return $this;
    }
    
    /**
     * Check if user is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'Active' && !$this->isLocked();
    }
    
    /**
     * Check if user has permission
     * 
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $rolePermissions = self::PERMISSIONS[$this->role] ?? [];
        return in_array($permission, $rolePermissions);
    }
    
    /**
     * Get all permissions for user's role
     * 
     * @return array
     */
    public function getPermissions(): array
    {
        return self::PERMISSIONS[$this->role] ?? [];
    }
    
    /**
     * Check if user is admin
     * 
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }
    
    /**
     * Check if user is manager or above
     * 
     * @return bool
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['Admin', 'Manager']);
    }
    
    /**
     * Get password reset token
     * 
     * @return string|null
     */
    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }
    
    /**
     * Set password reset token
     * 
     * @param string|null $token
     * @return self
     */
    public function setPasswordResetToken(?string $token): self
    {
        $this->passwordResetToken = $token;
        return $this;
    }
    
    /**
     * Generate password reset token
     * 
     * @param int $expiryMinutes
     * @return string
     */
    public function generatePasswordResetToken(int $expiryMinutes = 60): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetExpires = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
        return $this->passwordResetToken;
    }
    
    /**
     * Clear password reset token
     * 
     * @return self
     */
    public function clearPasswordResetToken(): self
    {
        $this->passwordResetToken = null;
        $this->passwordResetExpires = null;
        return $this;
    }
    
    /**
     * Check if password reset token is valid
     * 
     * @param string $token
     * @return bool
     */
    public function isValidPasswordResetToken(string $token): bool
    {
        if (!$this->passwordResetToken || !$this->passwordResetExpires) {
            return false;
        }
        
        if ($this->passwordResetToken !== $token) {
            return false;
        }
        
        return strtotime($this->passwordResetExpires) > time();
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
     * Convert model to array
     * 
     * @param bool $includePassword
     * @return array
     */
    public function toArray(bool $includePassword = false): array
    {
        $data = [
            'user_id' => $this->userId,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'role' => $this->role,
            'status' => $this->status,
            'last_login' => $this->lastLogin,
            'failed_login_attempts' => $this->failedLoginAttempts,
            'locked_until' => $this->lockedUntil,
            'two_factor_enabled' => $this->twoFactorEnabled,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_active' => $this->isActive(),
            'is_locked' => $this->isLocked(),
            'permissions' => $this->getPermissions()
        ];
        
        if ($includePassword) {
            $data['password_hash'] = $this->passwordHash;
            $data['password_reset_token'] = $this->passwordResetToken;
            $data['password_reset_expires'] = $this->passwordResetExpires;
            $data['two_factor_secret'] = $this->twoFactorSecret;
        }
        
        return $data;
    }
    
    /**
     * Create model from array
     * 
     * @param array $data
     * @return self
     */
    public function fromArray(array $data): self
    {
        if (isset($data['user_id'])) {
            $this->userId = (int)$data['user_id'];
        }
        
        if (isset($data['username'])) {
            $this->username = $data['username'];
        }
        
        if (isset($data['email'])) {
            $this->email = $data['email'];
        }
        
        if (isset($data['password_hash'])) {
            $this->passwordHash = $data['password_hash'];
        }
        
        if (isset($data['password']) && !isset($data['password_hash'])) {
            $this->setPassword($data['password']);
        }
        
        if (isset($data['first_name'])) {
            $this->firstName = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $this->lastName = $data['last_name'];
        }
        
        if (isset($data['role'])) {
            $this->role = $data['role'];
        }
        
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
        
        if (isset($data['last_login'])) {
            $this->lastLogin = $data['last_login'];
        }
        
        if (isset($data['failed_login_attempts'])) {
            $this->failedLoginAttempts = (int)$data['failed_login_attempts'];
        }
        
        if (isset($data['locked_until'])) {
            $this->lockedUntil = $data['locked_until'];
        }
        
        if (isset($data['password_reset_token'])) {
            $this->passwordResetToken = $data['password_reset_token'];
        }
        
        if (isset($data['password_reset_expires'])) {
            $this->passwordResetExpires = $data['password_reset_expires'];
        }
        
        if (isset($data['two_factor_enabled'])) {
            $this->twoFactorEnabled = (bool)$data['two_factor_enabled'];
        }
        
        if (isset($data['two_factor_secret'])) {
            $this->twoFactorSecret = $data['two_factor_secret'];
        }
        
        if (isset($data['created_at'])) {
            $this->createdAt = $data['created_at'];
        }
        
        if (isset($data['updated_at'])) {
            $this->updatedAt = $data['updated_at'];
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
     * Validate user data
     * 
     * @param SecurityService|null $securityService
     * @return ValidationResult
     */
    public function validate(?SecurityService $securityService = null): ValidationResult
    {
        $errors = [];
        $securityService = $securityService ?? new SecurityService();
        
        // Validate username
        if (!isset($this->username) || empty($this->username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (strlen($this->username) > 50) {
            $errors[] = 'Username cannot exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $this->username)) {
            $errors[] = 'Username can only contain letters, numbers, dots, hyphens, and underscores';
        }
        
        // Validate email
        if (!isset($this->email) || empty($this->email)) {
            $errors[] = 'Email is required';
        } elseif (!$securityService->isValidEmail($this->email)) {
            $errors[] = 'Invalid email format';
        }
        
        // Validate password (only if being set)
        if (!isset($this->passwordHash) || empty($this->passwordHash)) {
            $errors[] = 'Password is required';
        }
        
        // Validate first name
        if (!isset($this->firstName) || empty($this->firstName)) {
            $errors[] = 'First name is required';
        } elseif (strlen($this->firstName) > 100) {
            $errors[] = 'First name cannot exceed 100 characters';
        }
        
        // Validate last name
        if (!isset($this->lastName) || empty($this->lastName)) {
            $errors[] = 'Last name is required';
        } elseif (strlen($this->lastName) > 100) {
            $errors[] = 'Last name cannot exceed 100 characters';
        }
        
        // Validate role
        if (!isset($this->role) || !array_key_exists($this->role, self::ROLES)) {
            $errors[] = 'Invalid role selected';
        }
        
        // Validate status
        if (isset($this->status) && !in_array($this->status, self::STATUSES)) {
            $errors[] = 'Invalid status';
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
    
    /**
     * Get role description
     * 
     * @return string
     */
    public function getRoleDescription(): string
    {
        return self::ROLES[$this->role] ?? 'Unknown role';
    }
    
    /**
     * Get formatted last login
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedLastLogin(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->lastLogin ? date($format, strtotime($this->lastLogin)) : 'Never';
    }
    
    /**
     * Update last login timestamp
     * 
     * @return self
     */
    public function updateLastLogin(): self
    {
        $this->lastLogin = date('Y-m-d H:i:s');
        return $this;
    }
    
    /**
     * Get time since last login in human readable format
     * 
     * @return string
     */
    public function getTimeSinceLastLogin(): string
    {
        if (!$this->lastLogin) {
            return 'Never logged in';
        }
        
        $diff = time() - strtotime($this->lastLogin);
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } else {
            return floor($diff / 86400) . ' days ago';
        }
    }
}
