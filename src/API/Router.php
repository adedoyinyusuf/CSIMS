<?php

namespace CSIMS\API;

use CSIMS\Container\Container;
use CSIMS\Services\AuthService;
use CSIMS\Services\LoanService;
use CSIMS\Services\AuthenticationService;

use CSIMS\Services\SecurityService;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\CSIMSException;

/**
 * API Router
 * 
 * Handles HTTP routing for the CSIMS REST API
 */
class Router
{
    private Container $container;
    private array $routes = [];
    private array $middleware = [];
    
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->setupRoutes();
    }
    
    /**
     * Setup API routes
     */
    private function setupRoutes(): void
    {
        // Authentication routes
        $this->post('/api/auth/login', [$this, 'login']);
        $this->post('/api/auth/logout', [$this, 'logout']);
        $this->get('/api/auth/me', [$this, 'getCurrentUser']);
        
        // Member routes
        $this->get('/api/members', [$this, 'getMembers']);
        $this->get('/api/members/{id}', [$this, 'getMember']);
        $this->post('/api/members', [$this, 'createMember']);
        $this->put('/api/members/{id}', [$this, 'updateMember']);
        $this->delete('/api/members/{id}', [$this, 'deleteMember']);
        $this->get('/api/members/{id}/summary', [$this, 'getMemberSummary']);
        $this->get('/api/members/search', [$this, 'searchMembers']);
        
        // Loan routes
        $this->get('/api/loans', [$this, 'getLoans']);
        $this->get('/api/loans/{id}', [$this, 'getLoan']);
        $this->post('/api/loans', [$this, 'createLoan']);
        $this->put('/api/loans/{id}', [$this, 'updateLoan']);
        $this->delete('/api/loans/{id}', [$this, 'deleteLoan']);
        $this->post('/api/loans/{id}/approve', [$this, 'approveLoan']);
        $this->post('/api/loans/{id}/reject', [$this, 'rejectLoan']);
        $this->post('/api/loans/{id}/disburse', [$this, 'disburseLoan']);
        $this->post('/api/loans/{id}/payment', [$this, 'processLoanPayment']);
        $this->get('/api/loans/{id}/schedule', [$this, 'getLoanSchedule']);
        $this->get('/api/loans/overdue', [$this, 'getOverdueLoans']);
        $this->get('/api/loans/statistics', [$this, 'getLoanStatistics']);
        
        // Contribution routes removed
        // (All contribution endpoints have been removed as part of migration to Savings)
        
        // Dashboard routes
        $this->get('/api/dashboard/stats', [$this, 'getDashboardStats']);
        $this->get('/api/dashboard/recent-activities', [$this, 'getRecentActivities']);
        
        // System routes
        $this->get('/api/system/health', [$this, 'healthCheck']);
        $this->get('/api/system/settings', [$this, 'getSystemSettings']);
        $this->put('/api/system/settings', [$this, 'updateSystemSettings']);
    }
    
    /**
     * Add GET route
     */
    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Add POST route
     */
    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Add PUT route
     */
    public function put(string $path, callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Add DELETE route
     */
    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Add route to routes array
     */
    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    /**
     * Handle HTTP request
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $method, $path)) {
                $this->executeRoute($route, $method, $path);
                return;
            }
        }
        
        // No route found
        $this->sendResponse(['error' => 'Route not found'], 404);
    }
    
    /**
     * Check if route matches request
     */
    private function matchRoute(array $route, string $method, string $path): bool
    {
        if ($route['method'] !== $method) {
            return false;
        }
        
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']);
        $routePattern = '#^' . $routePattern . '$#';
        
        return preg_match($routePattern, $path);
    }
    
    /**
     * Execute matched route
     */
    private function executeRoute(array $route, string $method, string $path): void
    {
        try {
            // Extract path parameters
            $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']);
            $routePattern = '#^' . $routePattern . '$#';
            
            preg_match($routePattern, $path, $matches);
            array_shift($matches); // Remove full match
            
            // Get request data
            $requestData = $this->getRequestData();
            
            // Validate CSRF for state-changing operations
            if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
                $this->validateCSRF($requestData);
            }
            
            // Execute handler, only pass request data if handler expects it
            $handler = $route['handler'];
            $args = $matches;

            try {
                if (is_array($handler) && isset($handler[0], $handler[1])) {
                    $ref = new \ReflectionMethod($handler[0], $handler[1]);
                    $expectedParams = $ref->getNumberOfParameters();
                } else {
                    $ref = new \ReflectionFunction($handler);
                    $expectedParams = $ref->getNumberOfParameters();
                }
            } catch (\Throwable $e) {
                // Fallback: assume handler accepts request data to be safe for POST/PUT/DELETE
                $expectedParams = count($matches) + (in_array($method, ['POST', 'PUT', 'DELETE']) ? 1 : 0);
            }

            if ($expectedParams > count($matches)) {
                $args[] = $requestData;
            }

            $result = call_user_func_array($handler, $args);
            
            if (is_array($result)) {
                $this->sendResponse($result);
            }
            
        } catch (ValidationException $e) {
            $this->sendResponse([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 400);
            
        } catch (SecurityException $e) {
            $this->sendResponse([
                'error' => 'Security error',
                'message' => $e->getMessage()
            ], 403);
            
        } catch (DatabaseException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->sendResponse([
                'error' => 'Database error',
                'message' => 'A database error occurred'
            ], 500);
            
        } catch (CSIMSException $e) {
            $this->sendResponse([
                'error' => 'Application error',
                'message' => $e->getMessage()
            ], 500);
            
        } catch (\Throwable $e) {
            error_log('Unexpected error: ' . $e->getMessage());
            $this->sendResponse([
                'error' => 'Internal error',
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
    
    /**
     * Get request data based on content type
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            return $data ?: [];
        }
        
        return array_merge($_GET, $_POST);
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRF(array $requestData): void
    {
        $securityService = $this->container->resolve(SecurityService::class);
        
        if (!isset($requestData['csrf_token']) || !$securityService->validateCsrfToken($requestData['csrf_token'])) {
            throw new SecurityException('Invalid CSRF token');
        }
    }

    /**
     * Require authentication and return current user ID
     */
    private function requireAuthenticatedUserId(): int
    {
        if (!isset($_SESSION)) { @session_start(); }
        $uid = null;
        // Prefer unified session keys; fall back to admin/member
        if (isset($_SESSION['user_id'])) { $uid = (int)$_SESSION['user_id']; }
        elseif (isset($_SESSION['admin_user']['admin_id'])) { $uid = (int)$_SESSION['admin_user']['admin_id']; }
        elseif (isset($_SESSION['member_user']['member_id'])) { $uid = (int)$_SESSION['member_user']['member_id']; }
        if (!$uid) { throw new SecurityException('Not authenticated'); }
        return $uid;
    }

    /**
     * Enforce permission for current user
     */
    private function requirePermission(string $permission): int
    {
        $userId = $this->requireAuthenticatedUserId();
        $authz = $this->container->resolve(AuthenticationService::class);
        $authz->requirePermission($userId, $permission);
        return $userId;
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    // =============================================================================
    // ROUTE HANDLERS
    // =============================================================================
    
    /**
     * Health check endpoint
     */
    public function healthCheck(): array
    {
        return [
            'status' => 'OK',
            'timestamp' => date('c'),
            'version' => '2.0.0'
        ];
    }
    
    /**
     * Get loans with filtering and pagination
     */
    public function getLoans(array $requestData): array
    {
        $this->requirePermission('view_loans');
        $loanService = $this->container->resolve(LoanService::class);
        
        $page = (int)($requestData['page'] ?? 1);
        $limit = min((int)($requestData['limit'] ?? 10), 100);
        $filters = [];
        $orderBy = ['created_at' => 'DESC'];
        
        // Apply filters
        if (isset($requestData['status'])) {
            $filters['status'] = $requestData['status'];
        }
        if (isset($requestData['member_id'])) {
            $filters['member_id'] = $requestData['member_id'];
        }
        if (isset($requestData['date_from'])) {
            $filters['date_from'] = $requestData['date_from'];
        }
        if (isset($requestData['date_to'])) {
            $filters['date_to'] = $requestData['date_to'];
        }
        
        // Apply sorting
        if (isset($requestData['sort_by'])) {
            $orderBy = [$requestData['sort_by'] => $requestData['sort_order'] ?? 'DESC'];
        }
        
        $result = $loanService->getLoans($filters, $page, $limit, $orderBy);
        
        return [
            'success' => true,
            'data' => array_map(fn($loan) => $loan->toArray(), $result['data']),
            'pagination' => $result['pagination']
        ];
    }
    
    /**
     * Get single loan
     */
    public function getLoan(string $id): array
    {
        $this->requirePermission('view_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $loan = $loanService->getLoan((int)$id);
        
        if (!$loan) {
            return [
                'success' => false,
                'message' => 'Loan not found',
                'error' => 'NOT_FOUND'
            ];
        }
        
        return [
            'success' => true,
            'data' => $loan->toArray()
        ];
    }
    
    /**
     * Create new loan
     */
    public function createLoan(array $requestData): array
    {
        $this->requirePermission('create_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $loan = $loanService->createLoan($requestData);
        
        return [
            'success' => true,
            'message' => 'Loan created successfully',
            'data' => $loan->toArray(),
            'id' => $loan->getId()
        ];
    }
    
    /**
     * Update loan
     */
    public function updateLoan(string $id, array $requestData): array
    {
        $this->requirePermission('update_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $loan = $loanService->updateLoan((int)$id, $requestData);
        
        return [
            'success' => true,
            'message' => 'Loan updated successfully',
            'data' => $loan->toArray()
        ];
    }
    
    /**
     * Delete loan
     */
    public function deleteLoan(string $id, array $requestData): array
    {
        $this->requirePermission('delete_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $deleted = $loanService->deleteLoan((int)$id);
        
        return [
            'success' => $deleted,
            'message' => $deleted ? 'Loan deleted successfully' : 'Failed to delete loan'
        ];
    }
    
    /**
     * Approve loan
     */
    public function approveLoan(string $id, array $requestData): array
    {
        $this->requirePermission('approve_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $approved = $loanService->approveLoan((int)$id, $requestData['approved_by'] ?? 'System');
        
        return [
            'success' => $approved,
            'message' => $approved ? 'Loan approved successfully' : 'Failed to approve loan'
        ];
    }
    
    /**
     * Reject loan
     */
    public function rejectLoan(string $id, array $requestData): array
    {
        $this->requirePermission('reject_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $rejected = $loanService->rejectLoan(
            (int)$id, 
            $requestData['rejected_by'] ?? 'System',
            $requestData['reason'] ?? 'No reason provided'
        );
        
        return [
            'success' => $rejected,
            'message' => $rejected ? 'Loan rejected successfully' : 'Failed to reject loan'
        ];
    }
    
    /**
     * Disburse loan
     */
    public function disburseLoan(string $id, array $requestData): array
    {
        $this->requirePermission('disburse_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $disbursed = $loanService->disburseLoan((int)$id, $requestData['disbursed_by'] ?? 'System');
        
        return [
            'success' => $disbursed,
            'message' => $disbursed ? 'Loan disbursed successfully' : 'Failed to disburse loan'
        ];
    }
    
    /**
     * Process loan payment
     */
    public function processLoanPayment(string $id, array $requestData): array
    {
        $this->requirePermission('process_payments');
        $loanService = $this->container->resolve(LoanService::class);
        $processed = $loanService->processPayment(
            (int)$id,
            (float)$requestData['amount'],
            $requestData['payment_method'] ?? 'Cash'
        );
        
        return [
            'success' => $processed,
            'message' => $processed ? 'Payment processed successfully' : 'Failed to process payment'
        ];
    }
    
    /**
     * Get loan payment schedule
     */
    public function getLoanSchedule(string $id): array
    {
        $this->requirePermission('view_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $schedule = $loanService->calculatePaymentSchedule((int)$id);
        
        return [
            'success' => true,
            'data' => $schedule
        ];
    }
    
    /**
     * Get overdue loans
     */
    public function getOverdueLoans(): array
    {
        $this->requirePermission('view_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $loans = $loanService->getOverdueLoans();
        
        return [
            'success' => true,
            'data' => array_map(fn($loan) => $loan->toArray(), $loans)
        ];
    }
    
    /**
     * Get loan statistics
     */
    public function getLoanStatistics(): array
    {
        $this->requirePermission('view_loans');
        $loanService = $this->container->resolve(LoanService::class);
        $stats = $loanService->getLoanStatistics();
        
        return [
            'success' => true,
            'data' => $stats
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $this->requirePermission('view_dashboard');
        $loanService = $this->container->resolve(LoanService::class);
        
        $loanStats = $loanService->getLoanStatistics();
        
        return [
            'success' => true,
            'data' => [
                'loans' => $loanStats,
                'summary' => [
                    // Without contributions, portfolio reflects loans only for now
                    'total_portfolio' => ($loanStats['active_amount'] ?? 0),
                    'overdue_loans' => $loanStats['overdue_loans'] ?? 0
                ]
            ]
        ];
    }
    
    /**
     * Placeholder authentication methods
     */
    public function login(array $requestData): array
    {
        $auth = $this->container->resolve(AuthService::class);

        $username = (string)($requestData['username'] ?? $requestData['email'] ?? '');
        $password = (string)($requestData['password'] ?? '');
        $twoFactor = isset($requestData['two_factor_code']) ? (string)$requestData['two_factor_code'] : null;

        if ($username === '' || $password === '') {
            throw new ValidationException('Username/email and password are required');
        }

        $result = $auth->authenticate($username, $password, $twoFactor);
        return $result;
    }
    
    public function logout(array $requestData): array
    {
        $auth = $this->container->resolve(AuthService::class);
        return $auth->logout();
    }
    
    public function getCurrentUser(): array
    {
        $auth = $this->container->resolve(AuthService::class);
        $member = $auth->getCurrentUser();
        if (!$member) {
            return [
                'success' => false,
                'error' => 'UNAUTHORIZED',
                'message' => 'Not authenticated'
            ];
        }
        $data = $member->toArray();
        unset($data['password']);
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    // Placeholder member methods
    public function getMembers(array $requestData): array
    {
        $this->requirePermission('view_members');
        $repo = $this->container->resolve(\CSIMS\Repositories\MemberRepository::class);
        $page = (int)($requestData['page'] ?? 1);
        $limit = min((int)($requestData['limit'] ?? 10), 100);
        $filters = [];
        if (isset($requestData['status'])) {
            $filters['status'] = $requestData['status'];
        }
        $result = $repo->getPaginated($page, $limit, $filters);
        return [
            'success' => true,
            'data' => array_map(fn($m) => $m->toArray(), $result['data']),
            'pagination' => $result['pagination']
        ];
    }
    
    public function getMember(string $id): array
    {
        $this->requirePermission('view_members');
        $repo = $this->container->resolve(\CSIMS\Repositories\MemberRepository::class);
        $member = $repo->find((int)$id);
        if (!$member) {
            return [
                'success' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Member not found'
            ];
        }
        $data = $member->toArray();
        unset($data['password']);
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    public function createMember(array $requestData): array
    {
        $this->requirePermission('create_members');
        // Reuse AuthService::register for validation and hashing
        $auth = $this->container->resolve(AuthService::class);
        $result = $auth->register($requestData);
        return $result;
    }
    
    public function updateMember(string $id, array $requestData): array
    {
        $this->requirePermission('update_members');
        $repo = $this->container->resolve(\CSIMS\Repositories\MemberRepository::class);
        $security = $this->container->resolve(SecurityService::class);
        $existing = $repo->find((int)$id);
        if (!$existing) {
            return [
                'success' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Member not found'
            ];
        }
        // Sanitize and merge
        $sanitized = [];
        foreach ($requestData as $k => $v) { $sanitized[$k] = $security->sanitizeInput($v); }
        $data = array_merge($existing->toArray(), $sanitized);
        // Avoid overwriting password unless explicitly provided
        if (!isset($sanitized['password'])) { unset($data['password']); }
        $member = \CSIMS\Models\Member::fromArray($data);
        $member->setId((int)$id);
        $validation = $member->validate();
        if (!$validation->isValid()) {
            throw new ValidationException('Validation failed: ' . implode(', ', $validation->getErrors()));
        }
        $updated = $repo->update($member);
        $out = $updated->toArray();
        unset($out['password']);
        return [
            'success' => true,
            'message' => 'Member updated successfully',
            'data' => $out
        ];
    }
    
    public function deleteMember(string $id, array $requestData): array
    {
        $this->requirePermission('delete_members');
        $repo = $this->container->resolve(\CSIMS\Repositories\MemberRepository::class);
        $deleted = $repo->delete((int)$id);
        return [
            'success' => $deleted,
            'message' => $deleted ? 'Member deleted successfully' : 'Failed to delete member'
        ];
    }
    
    public function getMemberSummary(string $id): array
    {
        $this->requirePermission('view_members');
        $repo = $this->container->resolve(\CSIMS\Repositories\MemberRepository::class);
        $loanService = $this->container->resolve(LoanService::class);
        $member = $repo->find((int)$id);
        if (!$member) {
            return [
                'success' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Member not found'
            ];
        }
        $loans = $loanService->getLoansByMember((int)$id);
        $guaranteed = method_exists($loanService, 'getLoansGuaranteedByMember') ? $loanService->getLoansGuaranteedByMember((int)$id) : [];
        $totalAmount = array_reduce($loans, fn($sum, $loan) => $sum + ($loan->toArray()['amount'] ?? 0), 0.0);
        return [
            'success' => true,
            'data' => [
                'member' => (function($m){ $d = $m->toArray(); unset($d['password']); return $d; })($member),
                'loan_count' => count($loans),
                'total_loan_amount' => $totalAmount,
                'guaranteed_loan_count' => is_array($guaranteed) ? count($guaranteed) : 0
            ]
        ];
    }
    
    public function searchMembers(array $requestData): array
    {
        $this->requirePermission('view_members');
        $repo = $this->container->resolve(\CSIMS\Repositories\MemberRepository::class);
        $q = (string)($requestData['q'] ?? $requestData['query'] ?? '');
        $page = (int)($requestData['page'] ?? 1);
        $limit = min((int)($requestData['limit'] ?? 10), 100);
        $filters = [];
        if (isset($requestData['status'])) { $filters['status'] = $requestData['status']; }
        if ($q === '') {
            // Fallback to paginated listing when no query provided
            $result = $repo->getPaginated($page, $limit, $filters);
        } else {
            $result = $repo->search($q, $page, $limit, $filters);
        }
        return [
            'success' => true,
            'data' => array_map(fn($m) => $m->toArray(), $result['data']),
            'pagination' => $result['pagination']
        ];
    }
    
    public function confirmContribution(string $id, array $requestData): array
    {
        // This legacy endpoint has been removed
        return ['success' => false, 'error' => 'NOT_FOUND', 'message' => 'Endpoint removed'];
    }
    
    public function rejectContribution(string $id, array $requestData): array
    {
        // This legacy endpoint has been removed
        return ['success' => false, 'error' => 'NOT_FOUND', 'message' => 'Endpoint removed'];
    }
    
    public function bulkImportContributions(array $requestData): array
    {
        // This legacy endpoint has been removed
        return ['success' => false, 'error' => 'NOT_FOUND', 'message' => 'Endpoint removed'];
    }
    
    public function getRecentActivities(): array
    {
        return ['success' => true, 'message' => 'Recent activities endpoint - to be implemented'];
    }
    
    public function getSystemSettings(): array
    {
        return ['success' => true, 'message' => 'System settings endpoint - to be implemented'];
    }
    
    public function updateSystemSettings(array $requestData): array
    {
        return ['success' => true, 'message' => 'Update settings endpoint - to be implemented'];
    }
}
