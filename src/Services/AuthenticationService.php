<?php

namespace CSIMS\Services;

use CSIMS\Models\User;
use CSIMS\Repositories\UserRepository;
use CSIMS\Services\SecurityService;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\CSIMSException;
use mysqli;

/**
 * Authentication Service
 * 
 * Handles user authentication, session management, and authorization
 */
class AuthenticationService
{
    private UserRepository $userRepository;
    private SecurityService $securityService;
    private mysqli $connection;
    private int $sessionTimeout;
    private int $maxLoginAttempts;
    private int $lockoutDuration;
    
    public function __construct(
        UserRepository $userRepository, 
        SecurityService $securityService,
        mysqli $connection,
        int $sessionTimeout = 3600, // 1 hour default
        int $maxLoginAttempts = 5,
        int $lockoutDuration = 1800 // 30 minutes default
    ) {
        $this->userRepository = $userRepository;
        $this->securityService = $securityService;
        $this->connection = $connection;
        $this->sessionTimeout = $sessionTimeout;
        $this->maxLoginAttempts = $maxLoginAttempts;
        $this->lockoutDuration = $lockoutDuration;
    }
    
    /**
     * Authenticate user with username/email and password
     * 
     * @param string $identifier Username or email
     * @param string $password Plain text password
     * @param string $ipAddress Client IP address
     * @param string $userAgent Client user agent
     * @return array Authentication result
     * @throws ValidationException
     * @throws SecurityException
     * @throws DatabaseException
     */
    public function login(string $identifier, string $password, string $ipAddress = '', string $userAgent = ''): array
    {
        // Sanitize inputs
        $identifier = $this->securityService->sanitizeString($identifier);
        $ipAddress = $this->securityService->sanitizeString($ipAddress);
        $userAgent = $this->securityService->sanitizeString($userAgent);
        
        // Rate limiting check
        if (!$this->securityService->checkRateLimit('login', $ipAddress, 5, 300)) { // 5 attempts per 5 minutes
            throw new SecurityException('Too many login attempts. Please try again later.');
        }
        
        // Find user by username or email
        $user = $this->findUserByIdentifier($identifier);
        
        if (!$user) {
            $this->recordFailedLogin($identifier, $ipAddress, 'User not found');
            throw new ValidationException('Invalid credentials');
        }
        
        // Check if account is locked
        if ($user->isLocked()) {
            $this->recordFailedLogin($identifier, $ipAddress, 'Account locked');
            throw new SecurityException('Account is temporarily locked. Please try again later.');
        }
        
        // Check if account is active
        if (!$user->isActive()) {
            $this->recordFailedLogin($identifier, $ipAddress, 'Account inactive');
            throw new SecurityException('Account is inactive. Please contact administrator.');
        }
        
        // Verify password
        if (!$user->verifyPassword($password)) {
            $this->handleFailedLogin($user, $ipAddress, 'Invalid password');
            throw new ValidationException('Invalid credentials');
        }
        
        // Successful login
        $this->handleSuccessfulLogin($user, $ipAddress, $userAgent);
        
        // Create session
        $sessionData = $this->createSession($user, $ipAddress, $userAgent);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user->toArray(),
            'session' => $sessionData,
            'permissions' => $user->getPermissions()
        ];
    }
    
    /**
     * Logout user and invalidate session
     * 
     * @param string $sessionId Session ID to invalidate
     * @return array Logout result
     * @throws DatabaseException
     */
    public function logout(string $sessionId): array
    {
        $sessionId = $this->securityService->sanitizeString($sessionId);
        
        // Invalidate session in database
        $this->invalidateSession($sessionId);
        
        // Clear PHP session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    /**
     * Validate session and return user information
     * 
     * @param string $sessionId Session ID to validate
     * @return array|null User data if session is valid, null otherwise
     * @throws DatabaseException
     */
    public function validateSession(string $sessionId): ?array
    {
        $sessionId = $this->securityService->sanitizeString($sessionId);
        
        // Clean up expired sessions first
        $this->cleanupExpiredSessions();
        
        // Check session in database
        $sessionData = $this->getSessionData($sessionId);
        
        if (!$sessionData) {
            return null;
        }
        
        // Get user data
        $user = $this->userRepository->find($sessionData['user_id']);
        
        if (!$user || !$user->isActive()) {
            $this->invalidateSession($sessionId);
            return null;
        }
        
        // Update session last activity
        $this->updateSessionActivity($sessionId);
        
        return [
            'user' => $user->toArray(),
            'session' => $sessionData,
            'permissions' => $user->getPermissions()
        ];
    }
    
    /**
     * Check if user has specific permission
     * 
     * @param int $userId User ID
     * @param string $permission Permission to check
     * @return bool
     * @throws DatabaseException
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user || !$user->isActive()) {
            return false;
        }
        
        return $user->hasPermission($permission);
    }
    
    /**
     * Require specific permission (throws exception if not authorized)
     * 
     * @param int $userId User ID
     * @param string $permission Permission to require
     * @throws SecurityException
     * @throws DatabaseException
     */
    public function requirePermission(int $userId, string $permission): void
    {
        if (!$this->hasPermission($userId, $permission)) {
            throw new SecurityException('Insufficient permissions');
        }
    }
    
    /**
     * Generate password reset token
     * 
     * @param string $identifier Username or email
     * @return array Reset token result
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function generatePasswordResetToken(string $identifier): array
    {
        $identifier = $this->securityService->sanitizeString($identifier);
        
        $user = $this->findUserByIdentifier($identifier);
        
        if (!$user) {
            throw new ValidationException('User not found');
        }
        
        if (!$user->isActive()) {
            throw new ValidationException('Account is inactive');
        }
        
        // Generate token
        $token = $user->generatePasswordResetToken(60); // 1 hour expiry
        $this->userRepository->update($user);
        
        return [
            'success' => true,
            'message' => 'Password reset token generated',
            'token' => $token,
            'user_id' => $user->getId(),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600)
        ];
    }
    
    /**
     * Reset password using token
     * 
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array Reset result
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        $token = $this->securityService->sanitizeString($token);
        
        // Validate password strength
        if (!$this->securityService->isStrongPassword($newPassword)) {
            throw new ValidationException('Password does not meet security requirements');
        }
        
        $user = $this->userRepository->findByPasswordResetToken($token);
        
        if (!$user) {
            throw new ValidationException('Invalid or expired reset token');
        }
        
        if (!$user->isValidPasswordResetToken($token)) {
            throw new ValidationException('Invalid or expired reset token');
        }
        
        // Update password and clear token
        $user->setPassword($newPassword);
        $user->clearPasswordResetToken();
        $user->resetFailedLogins(); // Clear any lockouts
        
        $this->userRepository->update($user);
        
        // Invalidate all sessions for this user
        $this->invalidateUserSessions($user->getId());
        
        return [
            'success' => true,
            'message' => 'Password reset successfully'
        ];
    }
    
    /**
     * Change password for authenticated user
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Change result
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            throw new ValidationException('User not found');
        }
        
        // Verify current password
        if (!$user->verifyPassword($currentPassword)) {
            throw new ValidationException('Current password is incorrect');
        }
        
        // Validate new password strength
        if (!$this->securityService->isStrongPassword($newPassword)) {
            throw new ValidationException('Password does not meet security requirements');
        }
        
        // Check if new password is different from current
        if ($user->verifyPassword($newPassword)) {
            throw new ValidationException('New password must be different from current password');
        }
        
        // Update password
        $user->setPassword($newPassword);
        $this->userRepository->update($user);
        
        // Invalidate other sessions for this user (keep current session active)
        $currentSessionId = session_id();
        $this->invalidateUserSessions($user->getId(), $currentSessionId);
        
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    }
    
    /**
     * Get current user from session
     * 
     * @return User|null
     * @throws DatabaseException
     */
    public function getCurrentUser(): ?User
    {
        $sessionId = session_id();
        
        if (!$sessionId) {
            return null;
        }
        
        $sessionData = $this->validateSession($sessionId);
        
        if (!$sessionData) {
            return null;
        }
        
        return User::fromArray($sessionData['user']);
    }
    
    /**
     * Get active sessions for user
     * 
     * @param int $userId User ID
     * @return array
     * @throws DatabaseException
     */
    public function getUserSessions(int $userId): array
    {
        $sql = "SELECT session_id, ip_address, user_agent, created_at, expires_at, is_active 
                FROM user_sessions 
                WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
                ORDER BY created_at DESC";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        
        return $sessions;
    }
    
    /**
     * Find user by username or email
     * 
     * @param string $identifier
     * @return User|null
     * @throws DatabaseException
     */
    private function findUserByIdentifier(string $identifier): ?User
    {
        // Try to find by username first
        $user = $this->userRepository->findByUsername($identifier);
        
        // If not found, try by email
        if (!$user && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $this->userRepository->findByEmail($identifier);
        }
        
        return $user;
    }
    
    /**
     * Handle successful login
     * 
     * @param User $user
     * @param string $ipAddress
     * @param string $userAgent
     * @throws DatabaseException
     */
    private function handleSuccessfulLogin(User $user, string $ipAddress, string $userAgent): void
    {
        // Update user login information
        $this->userRepository->updateLoginAttempt($user->getId(), true);
        
        // Log successful login
        $this->logAuthEvent('login_success', $user->getId(), $ipAddress, $userAgent);
    }
    
    /**
     * Handle failed login attempt
     * 
     * @param User $user
     * @param string $ipAddress
     * @param string $reason
     * @throws DatabaseException
     */
    private function handleFailedLogin(User $user, string $ipAddress, string $reason): void
    {
        // Update failed login attempts
        $this->userRepository->updateLoginAttempt(
            $user->getId(), 
            false, 
            $this->maxLoginAttempts, 
            $this->lockoutDuration / 60
        );
        
        // Log failed attempt
        $this->recordFailedLogin($user->getUsername(), $ipAddress, $reason);
    }
    
    /**
     * Record failed login attempt
     * 
     * @param string $identifier
     * @param string $ipAddress
     * @param string $reason
     */
    private function recordFailedLogin(string $identifier, string $ipAddress, string $reason): void
    {
        $this->logAuthEvent('login_failed', null, $ipAddress, '', [
            'identifier' => $identifier,
            'reason' => $reason
        ]);
    }
    
    /**
     * Create user session
     * 
     * @param User $user
     * @param string $ipAddress
     * @param string $userAgent
     * @return array
     * @throws DatabaseException
     */
    private function createSession(User $user, string $ipAddress, string $userAgent): array
    {
        // Generate session ID
        $sessionId = bin2hex(random_bytes(64));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionTimeout);
        
        // Store session in database
        $sql = "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, created_at, expires_at, is_active) 
                VALUES (?, ?, ?, ?, NOW(), ?, 1)";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('sisss', $sessionId, $user->getId(), $ipAddress, $userAgent, $expiresAt);
        
        if (!$stmt->execute()) {
            throw new DatabaseException('Failed to create session: ' . $stmt->error);
        }
        
        // Start PHP session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store session data
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['role'] = $user->getRole();
        $_SESSION['expires_at'] = $expiresAt;
        
        return [
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'timeout' => $this->sessionTimeout
        ];
    }
    
    /**
     * Get session data from database
     * 
     * @param string $sessionId
     * @return array|null
     * @throws DatabaseException
     */
    private function getSessionData(string $sessionId): ?array
    {
        $sql = "SELECT * FROM user_sessions 
                WHERE session_id = ? AND is_active = 1 AND expires_at > NOW()";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Update session activity timestamp
     * 
     * @param string $sessionId
     * @throws DatabaseException
     */
    private function updateSessionActivity(string $sessionId): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionTimeout);
        
        $sql = "UPDATE user_sessions SET expires_at = ? WHERE session_id = ?";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ss', $expiresAt, $sessionId);
        $stmt->execute();
    }
    
    /**
     * Invalidate specific session
     * 
     * @param string $sessionId
     * @throws DatabaseException
     */
    private function invalidateSession(string $sessionId): void
    {
        $sql = "UPDATE user_sessions SET is_active = 0 WHERE session_id = ?";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
    }
    
    /**
     * Invalidate all sessions for a user
     * 
     * @param int $userId
     * @param string|null $keepSessionId Session to keep active (current session)
     * @throws DatabaseException
     */
    private function invalidateUserSessions(int $userId, ?string $keepSessionId = null): void
    {
        if ($keepSessionId) {
            $sql = "UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND session_id != ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bind_param('is', $userId, $keepSessionId);
        } else {
            $sql = "UPDATE user_sessions SET is_active = 0 WHERE user_id = ?";
            $stmt = $this->connection->prepare($sql);
            $stmt->bind_param('i', $userId);
        }
        
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->execute();
    }
    
    /**
     * Clean up expired sessions
     * 
     * @throws DatabaseException
     */
    private function cleanupExpiredSessions(): void
    {
        $sql = "DELETE FROM user_sessions WHERE expires_at < NOW() OR is_active = 0";
        
        if (!$this->connection->query($sql)) {
            throw new DatabaseException('Failed to cleanup sessions: ' . $this->connection->error);
        }
    }
    
    /**
     * Log authentication event
     * 
     * @param string $event
     * @param int|null $userId
     * @param string $ipAddress
     * @param string $userAgent
     * @param array $data
     */
    private function logAuthEvent(string $event, ?int $userId, string $ipAddress, string $userAgent = '', array $data = []): void
    {
        // This would integrate with a logging system
        // For now, we'll use error_log
        error_log(sprintf(
            'AUTH_EVENT: %s - User: %s, IP: %s, UA: %s, Data: %s',
            $event,
            $userId ?? 'unknown',
            $ipAddress,
            substr($userAgent, 0, 100),
            json_encode($data)
        ));
    }
    
    /**
     * Clean up maintenance tasks
     * 
     * @return array Cleanup results
     * @throws DatabaseException
     */
    public function performMaintenance(): array
    {
        $results = [];
        
        // Clean expired sessions
        $this->cleanupExpiredSessions();
        $results['expired_sessions_cleaned'] = true;
        
        // Clean expired password reset tokens
        $resetTokensCleared = $this->userRepository->cleanExpiredPasswordResetTokens();
        $results['reset_tokens_cleared'] = $resetTokensCleared;
        
        // Unlock expired locked accounts
        $accountsUnlocked = $this->userRepository->unlockExpiredAccounts();
        $results['accounts_unlocked'] = $accountsUnlocked;
        
        return $results;
    }
}
