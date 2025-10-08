<?php

namespace CSIMS\Controllers;

use CSIMS\Services\AuthenticationService;
use CSIMS\Services\SecurityService;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\CSIMSException;

/**
 * Authentication Controller
 * 
 * Handles user authentication endpoints including login, logout,
 * password reset, and session management
 */
class AuthenticationController
{
    private AuthenticationService $authService;
    private SecurityService $securityService;
    
    public function __construct(AuthenticationService $authService, SecurityService $securityService)
    {
        $this->authService = $authService;
        $this->securityService = $securityService;
    }
    
    /**
     * Handle login request
     * 
     * @return array
     */
    public function login(): array
    {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (json_last_error() !== JSON_ERROR_NONE && empty($_POST)) {
                throw new ValidationException('Invalid JSON input');
            }
            
            // Validate CSRF token for POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
                if (!$this->securityService->validateCSRFToken($csrfToken)) {
                    throw new SecurityException('CSRF token validation failed');
                }
            }
            
            // Validate required fields
            if (empty($input['identifier']) || empty($input['password'])) {
                throw new ValidationException('Username/email and password are required');
            }
            
            // Get client information
            $ipAddress = $this->securityService->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Attempt login
            $result = $this->authService->login(
                $input['identifier'],
                $input['password'],
                $ipAddress,
                $userAgent
            );
            
            return $result;
            
        } catch (ValidationException $e) {
            http_response_code(400);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (SecurityException $e) {
            http_response_code(401);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in login: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
                'errors' => ['System error']
            ];
            
        } catch (CSIMSException $e) {
            http_response_code(500);
            error_log('CSIMS error in login: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Handle logout request
     * 
     * @return array
     */
    public function logout(): array
    {
        try {
            // Get session ID
            $sessionId = session_id();
            
            if (empty($sessionId)) {
                return [
                    'success' => true,
                    'message' => 'Already logged out'
                ];
            }
            
            // Logout user
            $result = $this->authService->logout($sessionId);
            
            return $result;
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in logout: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred during logout.',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Get current user information
     * 
     * @return array
     */
    public function getCurrentUser(): array
    {
        try {
            $sessionId = session_id();
            
            if (empty($sessionId)) {
                http_response_code(401);
                return [
                    'success' => false,
                    'message' => 'Not authenticated',
                    'errors' => ['Not authenticated']
                ];
            }
            
            $sessionData = $this->authService->validateSession($sessionId);
            
            if (!$sessionData) {
                http_response_code(401);
                return [
                    'success' => false,
                    'message' => 'Session expired',
                    'errors' => ['Session expired']
                ];
            }
            
            return [
                'success' => true,
                'data' => $sessionData
            ];
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in getCurrentUser: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred.',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Handle password reset request
     * 
     * @return array
     */
    public function requestPasswordReset(): array
    {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (json_last_error() !== JSON_ERROR_NONE && empty($_POST)) {
                throw new ValidationException('Invalid JSON input');
            }
            
            // Validate CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
                if (!$this->securityService->validateCSRFToken($csrfToken)) {
                    throw new SecurityException('CSRF token validation failed');
                }
            }
            
            // Validate required fields
            if (empty($input['identifier'])) {
                throw new ValidationException('Username or email is required');
            }
            
            // Generate reset token
            $result = $this->authService->generatePasswordResetToken($input['identifier']);
            
            // In a real application, you would send this token via email
            // For now, we'll just return success (don't reveal if user exists)
            return [
                'success' => true,
                'message' => 'If a user with that identifier exists, a password reset link has been sent.'
            ];
            
        } catch (ValidationException $e) {
            // Don't reveal if user exists or not
            return [
                'success' => true,
                'message' => 'If a user with that identifier exists, a password reset link has been sent.'
            ];
            
        } catch (SecurityException $e) {
            http_response_code(401);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in requestPasswordReset: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Handle password reset with token
     * 
     * @return array
     */
    public function resetPassword(): array
    {
        try {
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (json_last_error() !== JSON_ERROR_NONE && empty($_POST)) {
                throw new ValidationException('Invalid JSON input');
            }
            
            // Validate CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
                if (!$this->securityService->validateCSRFToken($csrfToken)) {
                    throw new SecurityException('CSRF token validation failed');
                }
            }
            
            // Validate required fields
            if (empty($input['token']) || empty($input['password'])) {
                throw new ValidationException('Reset token and new password are required');
            }
            
            // Validate password confirmation
            if (empty($input['password_confirmation']) || $input['password'] !== $input['password_confirmation']) {
                throw new ValidationException('Password confirmation does not match');
            }
            
            // Reset password
            $result = $this->authService->resetPassword($input['token'], $input['password']);
            
            return $result;
            
        } catch (ValidationException $e) {
            http_response_code(400);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (SecurityException $e) {
            http_response_code(401);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in resetPassword: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Handle password change for authenticated users
     * 
     * @return array
     */
    public function changePassword(): array
    {
        try {
            // Check authentication
            $currentUser = $this->authService->getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return [
                    'success' => false,
                    'message' => 'Not authenticated',
                    'errors' => ['Not authenticated']
                ];
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (json_last_error() !== JSON_ERROR_NONE && empty($_POST)) {
                throw new ValidationException('Invalid JSON input');
            }
            
            // Validate CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
                if (!$this->securityService->validateCSRFToken($csrfToken)) {
                    throw new SecurityException('CSRF token validation failed');
                }
            }
            
            // Validate required fields
            if (empty($input['current_password']) || empty($input['new_password'])) {
                throw new ValidationException('Current password and new password are required');
            }
            
            // Validate password confirmation
            if (empty($input['new_password_confirmation']) || $input['new_password'] !== $input['new_password_confirmation']) {
                throw new ValidationException('New password confirmation does not match');
            }
            
            // Change password
            $result = $this->authService->changePassword(
                $currentUser->getId(),
                $input['current_password'],
                $input['new_password']
            );
            
            return $result;
            
        } catch (ValidationException $e) {
            http_response_code(400);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (SecurityException $e) {
            http_response_code(401);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in changePassword: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Get CSRF token for forms
     * 
     * @return array
     */
    public function getCSRFToken(): array
    {
        try {
            $token = $this->securityService->generateCSRFToken();
            
            return [
                'success' => true,
                'data' => [
                    'csrf_token' => $token
                ]
            ];
            
        } catch (\Exception $e) {
            http_response_code(500);
            error_log('Error generating CSRF token: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to generate security token',
                'errors' => ['System error']
            ];
        }
    }
    
    /**
     * Get user sessions (for authenticated user)
     * 
     * @return array
     */
    public function getUserSessions(): array
    {
        try {
            // Check authentication
            $currentUser = $this->authService->getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return [
                    'success' => false,
                    'message' => 'Not authenticated',
                    'errors' => ['Not authenticated']
                ];
            }
            
            $sessions = $this->authService->getUserSessions($currentUser->getId());
            
            return [
                'success' => true,
                'data' => $sessions
            ];
            
        } catch (DatabaseException $e) {
            http_response_code(500);
            error_log('Database error in getUserSessions: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A system error occurred.',
                'errors' => ['System error']
            ];
        }
    }
}
