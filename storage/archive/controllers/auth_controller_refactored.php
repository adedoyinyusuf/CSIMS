<?php

/**
 * Refactored Auth Controller Example
 * 
 * This demonstrates how to use the new architecture in controllers
 * This is a migration example - the old controller can gradually be replaced
 */

// Load the new architecture
require_once __DIR__ . '/../src/autoload.php';

use CSIMS\Services\AuthService;
use CSIMS\Services\SecurityService;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Services\ConfigurationManager;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\ValidationException;

class AuthControllerRefactored
{
    private AuthService $authService;
    private SecurityService $securityService;
    
    public function __construct()
    {
        // Get dependencies from container
        $container = container();
        
        $this->securityService = $container->resolve(SecurityService::class);
        
        // Create AuthService with dependencies
        $this->authService = new AuthService(
            $container->resolve(SecurityService::class),
            $container->resolve(MemberRepository::class),
            $container->resolve(ConfigurationManager::class)
        );
    }
    
    /**
     * Handle login request
     */
    public function login()
    {
        try {
            // Validate CSRF token for POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->securityService->validateCSRFForRequest();
                
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $twoFactorCode = $_POST['two_factor_code'] ?? null;
                
                // Validate required fields
                if (empty($username) || empty($password)) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Username and password are required'
                    ], 400);
                }
                
                // Attempt authentication
                $result = $this->authService->authenticate($username, $password, $twoFactorCode);
                
                if ($result['success']) {
                    return $this->jsonResponse($result, 200);
                } else {
                    return $this->jsonResponse($result, 401);
                }
            }
            
            // Return login form for GET requests
            return $this->renderLoginForm();
            
        } catch (SecurityException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => 'An error occurred during login'
            ], 500);
        }
    }
    
    /**
     * Handle registration request
     */
    public function register()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->securityService->validateCSRFForRequest();
                
                $data = $this->getPostData();
                
                // Attempt registration
                $result = $this->authService->register($data);
                
                return $this->jsonResponse($result, $result['success'] ? 201 : 400);
            }
            
            // Return registration form for GET requests
            return $this->renderRegistrationForm();
            
        } catch (ValidationException $e) {
            $context = $e->getContext();
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $context['errors'] ?? []
            ], 422);
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => 'An error occurred during registration'
            ], 500);
        }
    }
    
    /**
     * Handle logout request
     */
    public function logout()
    {
        try {
            $result = $this->authService->logout();
            
            // Redirect to login page after logout
            if ($this->isAjaxRequest()) {
                return $this->jsonResponse($result, 200);
            } else {
                header('Location: /login.php');
                exit;
            }
            
        } catch (Exception $e) {
            error_log('Logout error: ' . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => 'An error occurred during logout'
            ], 500);
        }
    }
    
    /**
     * Handle password change request
     */
    public function changePassword()
    {
        try {
            if (!$this->authService->isAuthenticated()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->securityService->validateCSRFForRequest();
                
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                // Validate inputs
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'All password fields are required'
                    ], 400);
                }
                
                if ($newPassword !== $confirmPassword) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'New passwords do not match'
                    ], 400);
                }
                
                $currentUser = $this->authService->getCurrentUser();
                $result = $this->authService->changePassword(
                    $currentUser->getId(),
                    $currentPassword,
                    $newPassword
                );
                
                return $this->jsonResponse($result, $result['success'] ? 200 : 400);
            }
            
        } catch (SecurityException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (ValidationException $e) {
            $context = $e->getContext();
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $context['errors'] ?? []
            ], 422);
        } catch (Exception $e) {
            error_log('Password change error: ' . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => 'An error occurred while changing password'
            ], 500);
        }
    }
    
    /**
     * Get current user information
     */
    public function getCurrentUser()
    {
        try {
            if (!$this->authService->isAuthenticated()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Not authenticated'
                ], 401);
            }
            
            $user = $this->authService->getCurrentUser();
            
            if ($user) {
                $userData = $user->toArray();
                unset($userData['password']); // Remove password from response
                
                return $this->jsonResponse([
                    'success' => true,
                    'user' => $userData
                ], 200);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
        } catch (Exception $e) {
            error_log('Get current user error: ' . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Helper method to get POST data
     */
    private function getPostData(): array
    {
        return [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'ippis_no' => $_POST['ippis_no'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'dob' => $_POST['dob'] ?? '',
            'address' => $_POST['address'] ?? '',
            'occupation' => $_POST['occupation'] ?? '',
            'membership_type_id' => $_POST['membership_type_id'] ?? null
        ];
    }
    
    /**
     * Helper method to return JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Render login form
     */
    private function renderLoginForm(): void
    {
        $csrfToken = $this->securityService->generateCSRFToken();
        
        // Here you would include/render your login form view
        // For example:
        include __DIR__ . '/../views/auth/login.php';
    }
    
    /**
     * Render registration form
     */
    private function renderRegistrationForm(): void
    {
        $csrfToken = $this->securityService->generateCSRFToken();
        
        // Here you would include/render your registration form view
        // For example:
        include __DIR__ . '/../views/auth/register.php';
    }
}

// Example usage (this would typically be in a router or front controller):
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $controller = new AuthControllerRefactored();
    
    // Simple routing based on action parameter
    $action = $_GET['action'] ?? 'login';
    
    switch ($action) {
        case 'login':
            $controller->login();
            break;
        case 'register':
            $controller->register();
            break;
        case 'logout':
            $controller->logout();
            break;
        case 'change-password':
            $controller->changePassword();
            break;
        case 'current-user':
            $controller->getCurrentUser();
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action not found']);
    }
}
