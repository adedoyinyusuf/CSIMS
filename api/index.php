<?php

/**
 * CSIMS API Router
 * 
 * Main API entry point with authentication, authorization,
 * and comprehensive endpoint handling
 */

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
session_start();

try {
    // Bootstrap the application
    require_once __DIR__ . '/../src/bootstrap.php';
    $container = CSIMS\bootstrap();
    
    // Get services
    $securityService = $container->resolve(\CSIMS\Services\SecurityService::class);
    $authService = $container->resolve(\CSIMS\Services\AuthenticationService::class);
    $authController = $container->resolve(\CSIMS\Controllers\AuthenticationController::class);
    
    // Set security headers
    $securityService->setSecurityHeaders();
    
    // Get request information
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Remove /api prefix if present
    $path = preg_replace('#^/api#', '', $path);
    $path = $path === '' ? '/' : $path;
    
    // Route definitions
    $routes = [
        // Authentication routes (public)
        'POST /auth/login' => [$authController, 'login'],
        'POST /auth/logout' => [$authController, 'logout'],
        'GET /auth/user' => [$authController, 'getCurrentUser'],
        'POST /auth/password/reset' => [$authController, 'requestPasswordReset'],
        'POST /auth/password/reset/confirm' => [$authController, 'resetPassword'],
        'POST /auth/password/change' => [$authController, 'changePassword'],
        'GET /auth/csrf' => [$authController, 'getCSRFToken'],
        'GET /auth/sessions' => [$authController, 'getUserSessions'],
        
        // System routes (protected)
        'GET /system/info' => function() use ($authService) {
            // Require authentication
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['success' => false, 'message' => 'Authentication required'];
            }
            
            // Require admin permission
            if (!$currentUser->hasPermission('system:view')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            return [
                'success' => true,
                'data' => [
                    'version' => '1.0.0',
                    'system' => 'CSIMS',
                    'php_version' => phpversion(),
                    'timestamp' => time(),
                    'current_user' => $currentUser->getUsername(),
                    'permissions' => $currentUser->getPermissions()
                ]
            ];
        },
        
        'POST /system/maintenance' => function() use ($authService) {
            // Require authentication and admin permission
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('system:maintenance')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            // Perform maintenance tasks
            $results = $authService->performMaintenance();
            
            return [
                'success' => true,
                'message' => 'Maintenance completed',
                'data' => $results
            ];
        },
        
        // Member routes (protected)
        'GET /members' => function() use ($container, $authService) {
            // Require authentication
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('members:read')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            $memberRepository = $container->resolve(\CSIMS\Repositories\MemberRepository::class);
            
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $search = $_GET['search'] ?? '';
            
            $members = $memberRepository->paginate($page, $limit, $search ? ['name' => $search] : []);
            
            return [
                'success' => true,
                'data' => $members
            ];
        },
        
        'GET /members/{id}' => function($id) use ($container, $authService) {
            // Require authentication
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('members:read')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            $memberRepository = $container->resolve(\CSIMS\Repositories\MemberRepository::class);
            $member = $memberRepository->find((int)$id);
            
            if (!$member) {
                http_response_code(404);
                return ['success' => false, 'message' => 'Member not found'];
            }
            
            return ['success' => true, 'data' => $member->toArray()];
        },
        
        'POST /members' => function() use ($container, $authService, $securityService) {
            // Require authentication and permission
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('members:create')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            // Get and validate input
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                return ['success' => false, 'message' => 'Invalid JSON input'];
            }
            
            // Validate CSRF token
            if (!$securityService->validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(400);
                return ['success' => false, 'message' => 'CSRF token validation failed'];
            }
            
            // Create member (this would use a MemberService)
            $memberRepository = $container->resolve(\CSIMS\Repositories\MemberRepository::class);
            
            // Basic validation - in real implementation, use validation service
            $requiredFields = ['name', 'email', 'phone'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    return ['success' => false, 'message' => "Field {$field} is required"];
                }
            }
            
            $member = new \CSIMS\Models\Member(
                null,
                $securityService->sanitizeString($input['name']),
                $securityService->sanitizeString($input['email']),
                $securityService->sanitizeString($input['phone']),
                $securityService->sanitizeString($input['address'] ?? ''),
                new DateTime()
            );
            
            $createdMember = $memberRepository->create($member);
            
            http_response_code(201);
            return [
                'success' => true,
                'message' => 'Member created successfully',
                'data' => $createdMember->toArray()
            ];
        },
        
        // Loan routes (protected)
        'GET /loans' => function() use ($container, $authService) {
            // Require authentication
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('loans:read')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            $loanService = $container->resolve(\CSIMS\Services\LoanService::class);
            
            $filters = [];
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            
            // Add filters from query parameters
            if (!empty($_GET['member_id'])) {
                $filters['member_id'] = (int)$_GET['member_id'];
            }
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            $result = $loanService->getLoans($filters, $page, $limit);
            
            return $result;
        },
        
        'GET /loans/{id}' => function($id) use ($container, $authService) {
            // Require authentication
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('loans:read')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            $loanService = $container->resolve(\CSIMS\Services\LoanService::class);
            $loan = $loanService->getLoan((int)$id);
            
            if (!$loan) {
                http_response_code(404);
                return ['success' => false, 'message' => 'Loan not found'];
            }
            
            return ['success' => true, 'data' => $loan->toArray()];
        },
        
        'POST /loans' => function() use ($container, $authService, $securityService) {
            // Require authentication and permission
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('loans:create')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            // Get and validate input
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                return ['success' => false, 'message' => 'Invalid JSON input'];
            }
            
            // Validate CSRF token
            if (!$securityService->validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(400);
                return ['success' => false, 'message' => 'CSRF token validation failed'];
            }
            
            $loanService = $container->resolve(\CSIMS\Services\LoanService::class);
            $loan = $loanService->createLoan($input);
            
            http_response_code(201);
            return [
                'success' => true,
                'message' => 'Loan created successfully',
                'data' => $loan->toArray()
            ];
        },
        
        'PUT /loans/{id}' => function($id) use ($container, $authService, $securityService) {
            // Require authentication and permission
            $currentUser = $authService->getCurrentUser();
            if (!$currentUser || !$currentUser->hasPermission('loans:update')) {
                http_response_code(403);
                return ['success' => false, 'message' => 'Insufficient permissions'];
            }
            
            // Get and validate input
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                return ['success' => false, 'message' => 'Invalid JSON input'];
            }
            
            // Validate CSRF token
            if (!$securityService->validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(400);
                return ['success' => false, 'message' => 'CSRF token validation failed'];
            }
            
            $loanService = $container->resolve(\CSIMS\Services\LoanService::class);
            $loan = $loanService->updateLoan((int)$id, $input);
            
            if (!$loan) {
                http_response_code(404);
                return ['success' => false, 'message' => 'Loan not found'];
            }
            
            return [
                'success' => true,
                'message' => 'Loan updated successfully',
                'data' => $loan->toArray()
            ];
        },
        
        // Health check (public)
        'GET /' => function() {
            return [
                'success' => true,
                'message' => 'CSIMS API is running',
                'version' => '1.0.0',
                'timestamp' => time()
            ];
        },
        
        'GET /health' => function() {
            return [
                'success' => true,
                'status' => 'healthy',
                'timestamp' => time(),
                'checks' => [
                    'api' => 'OK',
                    'session' => session_status() === PHP_SESSION_ACTIVE ? 'OK' : 'WARNING'
                ]
            ];
        }
    ];
    
    // Route matching and execution
    $route = $requestMethod . ' ' . $path;
    $matched = false;
    
    foreach ($routes as $pattern => $handler) {
        // Convert route pattern to regex
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        if (preg_match($regex, $route, $matches)) {
            $matched = true;
            array_shift($matches); // Remove full match
            
            try {
                // Execute route handler
                if (is_array($handler) && is_callable($handler)) {
                    // Controller method
                    $result = call_user_func_array($handler, $matches);
                } elseif (is_callable($handler)) {
                    // Closure
                    $result = call_user_func_array($handler, $matches);
                } else {
                    throw new Exception('Invalid route handler');
                }
                
                // Return JSON response
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                exit;
                
            } catch (\CSIMS\Exceptions\ValidationException $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [$e->getMessage()]
                ]);
                exit;
                
            } catch (\CSIMS\Exceptions\SecurityException $e) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [$e->getMessage()]
                ]);
                exit;
                
            } catch (\CSIMS\Exceptions\DatabaseException $e) {
                http_response_code(500);
                error_log('Database error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'A database error occurred',
                    'errors' => ['Database error']
                ]);
                exit;
                
            } catch (\Exception $e) {
                http_response_code(500);
                error_log('API error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'An internal server error occurred',
                    'errors' => ['Internal server error']
                ]);
                exit;
            }
        }
    }
    
    // Route not found
    if (!$matched) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found',
            'errors' => ['Route not found'],
            'available_endpoints' => array_keys($routes)
        ]);
        exit;
    }
    
} catch (\Exception $e) {
    // Global error handler
    http_response_code(500);
    error_log('Fatal API error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'A critical system error occurred',
        'errors' => ['System error']
    ]);
    exit;
}
