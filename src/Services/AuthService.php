<?php

namespace CSIMS\Services;

use CSIMS\Models\Member;
use CSIMS\Repositories\MemberRepository;
use CSIMS\DTOs\ValidationResult;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\ValidationException;

/**
 * Authentication Service
 * 
 * Handles all authentication-related operations using the new architecture
 */
class AuthService
{
    private SecurityService $security;
    private MemberRepository $memberRepository;
    private ConfigurationManager $config;
    
    public function __construct(
        SecurityService $security,
        MemberRepository $memberRepository,
        ConfigurationManager $config
    ) {
        $this->security = $security;
        $this->memberRepository = $memberRepository;
        $this->config = $config;
    }
    
    /**
     * Authenticate user login
     * 
     * @param string $username
     * @param string $password
     * @param string|null $twoFactorCode
     * @return array
     * @throws SecurityException
     */
    public function authenticate(string $username, string $password, ?string $twoFactorCode = null): array
    {
        // Sanitize inputs
        $username = $this->security->sanitizeInput($username);
        $clientId = $this->security->getClientIP() . '_' . $username;
        
        // Rate limiting check (identifier + action)
        $securityConfig = $this->config->getSecurityConfig();
        if (!$this->security->isRateLimitAllowed($clientId, 'login', $securityConfig['max_login_attempts'], $securityConfig['login_lockout_time'])) {
            throw new SecurityException('Too many login attempts. Please try again later.');
        }
        
        // Find user by username or email
        $member = $this->findUserByCredential($username);
        
        if (!$member) {
            $this->logFailedAttempt($username, 'User not found');
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }
        
        // Verify password using model's stored hash
        if (!password_verify($password, $member->getPasswordHash())) {
            $this->logFailedAttempt($username, 'Invalid password');
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }
        
        // Check account status
        if (!$this->isAccountActive($member)) {
            return [
                'success' => false,
                'message' => $this->getAccountStatusMessage($member)
            ];
        }
        
        // Handle 2FA if enabled
        if ($this->isTwoFactorEnabled($member)) {
            if (!$twoFactorCode) {
                return [
                    'success' => false,
                    'message' => 'Two-factor authentication code required',
                    'requires_2fa' => true
                ];
            }
            
            if (!$this->verifyTwoFactorCode($member, $twoFactorCode)) {
                $this->logFailedAttempt($username, 'Invalid 2FA code');
                throw new SecurityException('Invalid two-factor authentication code');
            }
        }
        
        // Successful authentication
        $this->logSuccessfulLogin($member);
        $this->createUserSession($member);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $this->prepareUserData($member)
        ];
    }
    
    /**
     * Register new member
     * 
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function register(array $data): array
    {
        // Sanitize input data
        foreach ($data as $key => $value) {
            $data[$key] = $this->security->sanitizeInput($value);
        }
        
        // Create member model
        $member = new Member($data);
        
        // Validate member data
        $validationResult = $member->validate();
        if (!$validationResult->isValid()) {
            throw new ValidationException('Validation failed', 0, null, [
                'errors' => $validationResult->getErrors()
            ]);
        }
        
        // Check for existing user
        if ($this->isUserExists($member)) {
            throw new ValidationException('User already exists');
        }
        
        // Hash password if provided
        if (!empty($data['password'])) {
            $passwordValidation = $this->security->validatePassword($data['password']);
            if (!$passwordValidation->isValid()) {
                throw new ValidationException('Password validation failed', 0, null, [
                    'errors' => $passwordValidation->getErrors()
                ]);
            }
            
            $member->setPasswordHash(password_hash($data['password'], PASSWORD_DEFAULT));
        }
        
        // Set default values
        $member->setStatus('pending')
              ->setJoinDate(date('Y-m-d'))
              ->setExpiryDate(date('Y-m-d', strtotime('+1 year')));
        
        // Save to database
        try {
            $savedMember = $this->memberRepository->create($member);
            
            return [
                'success' => true,
                'message' => 'Registration successful. Your application is pending approval.',
                'member_id' => $savedMember->getId()
            ];
        } catch (\Exception $e) {
            throw new ValidationException('Failed to create user: ' . $e->getMessage());
        }
    }
    
    /**
     * Logout user
     * 
     * @return array
     */
    public function logout(): array
    {
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            // Log logout event here if needed
        }
        
        // Clear session data
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current authenticated user
     * 
     * @return Member|null
     */
    public function getCurrentUser(): ?Member
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'];
        return $this->memberRepository->find($userId);
    }
    
    /**
     * Change user password
     * 
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return array
     * @throws SecurityException|ValidationException
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $member = $this->memberRepository->find($userId);
        
        if (!$member) {
            throw new SecurityException('User not found');
        }
        // Ensure correct model type for static analysis and safety
        if (!$member instanceof \CSIMS\Models\Member) {
            throw new SecurityException('Invalid user entity');
        }
        
        // Verify current password using model's stored hash
        if (!password_verify($currentPassword, $member->getPasswordHash())) {
            throw new SecurityException('Current password is incorrect');
        }
        
        // Validate new password
        $passwordValidation = $this->security->validatePassword($newPassword);
        if (!$passwordValidation->isValid()) {
            throw new ValidationException('New password validation failed', 0, null, [
                'errors' => $passwordValidation->getErrors()
            ]);
        }
        
        // Update password
        /** @var \CSIMS\Models\Member $member */
        $member->setPasswordHash(password_hash($newPassword, PASSWORD_DEFAULT));
        $this->memberRepository->update($member);
        
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    }
    
    /**
     * Find user by credential (username or email)
     * 
     * @param string $credential
     * @return Member|null
     */
    private function findUserByCredential(string $credential): ?Member
    {
        // Try to find by username first
        $member = $this->memberRepository->findByUsername($credential);
        
        // If not found, try email
        if (!$member && filter_var($credential, FILTER_VALIDATE_EMAIL)) {
            $member = $this->memberRepository->findByEmail($credential);
        }
        
        // If still not found, try member_id
        if (!$member) {
            $member = $this->memberRepository->find($credential);
        }
        
        return $member;
    }
    
    /**
     * Check if account is active
     * 
     * @param Member $member
     * @return bool
     */
    private function isAccountActive(Member $member): bool
    {
        return $member->isActive() && !$member->isExpired();
    }
    
    /**
     * Get account status message
     * 
     * @param Member $member
     * @return string
     */
    private function getAccountStatusMessage(Member $member): string
    {
        $status = $member->getStatus();
        
        return match ($status) {
            'pending' => 'Your account is pending admin approval.',
            'inactive' => 'Your account has been deactivated. Please contact support.',
            'suspended' => 'Your account has been suspended. Please contact support.',
            'expired' => 'Your membership has expired. Please contact support to renew.',
            default => 'Account access denied. Please contact support.'
        };
    }
    
    /**
     * Check if two-factor authentication is enabled
     * 
     * @param Member $member
     * @return bool
     */
    private function isTwoFactorEnabled(Member $member): bool
    {
        // Config-level toggle (per-user support can be added later)
        return (bool)$this->config->get('security.two_factor_enabled', false);
    }
    
    /**
     * Verify two-factor code
     * 
     * @param Member $member
     * @param string $code
     * @return bool
     */
    private function verifyTwoFactorCode(Member $member, string $code): bool
    {
        // In non-production environments, allow a test code for development convenience
        $env = $this->config->getEnvironment();
        if ($env !== 'production') {
            $testCode = (string)$this->config->get('security.test_2fa_code', '000000');
            return hash_equals($testCode, $code);
        }
        
        // Production path should implement TOTP/SMS verification integrated with member data
        // Since per-user secrets are not yet modeled, reject by default when enabled
        return false;
    }
    
    /**
     * Check if user already exists
     * 
     * @param Member $member
     * @return bool
     */
    private function isUserExists(Member $member): bool
    {
        // Check by email
        if ($this->memberRepository->findByEmail($member->getEmail())) {
            return true;
        }
        
        // Check by username if provided
        if ($member->getUsername() && $this->memberRepository->findByUsername($member->getUsername())) {
            return true;
        }
        
        // Check by IPPIS if provided
        if ($member->getIppis() && $this->memberRepository->findByIppis($member->getIppis())) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Create user session
     * 
     * @param Member $member
     * @return void
     */
    private function createUserSession(Member $member): void
    {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $member->getId();
        $_SESSION['username'] = $member->getUsername();
        $_SESSION['email'] = $member->getEmail();
        $_SESSION['full_name'] = $member->getFullName();
        $_SESSION['user_type'] = 'member';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Set security markers
        $_SESSION['user_ip'] = $this->security->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Prepare user data for response
     * 
     * @param Member $member
     * @return array
     */
    private function prepareUserData(Member $member): array
    {
        $data = $member->toArray();
        
        // Remove sensitive data
        unset($data['password']);
        
        return $data;
    }
    
    /**
     * Log failed login attempt
     * 
     * @param string $username
     * @param string $reason
     * @return void
     */
    private function logFailedAttempt(string $username, string $reason): void
    {
        // Log security event
        error_log("Failed login attempt: username={$username}, reason={$reason}, ip=" . $this->security->getClientIP());
    }
    
    /**
     * Log successful login
     * 
     * @param Member $member
     * @return void
     */
    private function logSuccessfulLogin(Member $member): void
    {
        // Log successful login
        error_log("Successful login: user_id={$member->getId()}, username={$member->getUsername()}, ip=" . $this->security->getClientIP());
    }
}
