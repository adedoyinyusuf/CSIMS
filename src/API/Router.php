<?php

namespace CSIMS\API;

use CSIMS\Container\Container;
use CSIMS\Services\LoanService;
use CSIMS\Services\ContributionService;
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
        
        // Contribution routes
        $this->get('/api/contributions', [$this, 'getContributions']);
        $this->get('/api/contributions/{id}', [$this, 'getContribution']);
        $this->post('/api/contributions', [$this, 'createContribution']);
        $this->put('/api/contributions/{id}', [$this, 'updateContribution']);
        $this->delete('/api/contributions/{id}', [$this, 'deleteContribution']);
        $this->post('/api/contributions/{id}/confirm', [$this, 'confirmContribution']);
        $this->post('/api/contributions/{id}/reject', [$this, 'rejectContribution']);
        $this->get('/api/contributions/statistics', [$this, 'getContributionStatistics']);
        $this->post('/api/contributions/bulk-import', [$this, 'bulkImportContributions']);
        $this->get('/api/contributions/report', [$this, 'generateContributionReport']);
        
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
            
            // Execute handler
            $result = call_user_func_array($route['handler'], array_merge($matches, [$requestData]));
            
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
            
        } catch (\Exception $e) {
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
        $loanService = $this->container->resolve(LoanService::class);
        $stats = $loanService->getLoanStatistics();
        
        return [
            'success' => true,
            'data' => $stats
        ];
    }
    
    /**
     * Get contributions with filtering and pagination
     */
    public function getContributions(array $requestData): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        
        $page = (int)($requestData['page'] ?? 1);
        $limit = min((int)($requestData['limit'] ?? 10), 100);
        $filters = [];
        $orderBy = ['contribution_date' => 'DESC'];
        
        // Apply filters
        if (isset($requestData['member_id'])) {
            $filters['member_id'] = $requestData['member_id'];
        }
        if (isset($requestData['type'])) {
            $filters['contribution_type'] = $requestData['type'];
        }
        if (isset($requestData['status'])) {
            $filters['status'] = $requestData['status'];
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
        
        if (isset($requestData['search'])) {
            $result = $contributionService->searchContributions($requestData['search'], $filters, $page, $limit);
        } else {
            $result = $contributionService->getContributions($filters, $page, $limit, $orderBy);
        }
        
        return [
            'success' => true,
            'data' => array_map(fn($contribution) => $contribution->toArray(), $result['data']),
            'pagination' => $result['pagination']
        ];
    }
    
    /**
     * Get single contribution
     */
    public function getContribution(string $id): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        $contribution = $contributionService->getContribution((int)$id);
        
        if (!$contribution) {
            return [
                'success' => false,
                'message' => 'Contribution not found',
                'error' => 'NOT_FOUND'
            ];
        }
        
        return [
            'success' => true,
            'data' => $contribution->toArray()
        ];
    }
    
    /**
     * Create new contribution
     */
    public function createContribution(array $requestData): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        $contribution = $contributionService->createContribution($requestData);
        
        return [
            'success' => true,
            'message' => 'Contribution created successfully',
            'data' => $contribution->toArray(),
            'id' => $contribution->getId()
        ];
    }
    
    /**
     * Update contribution
     */
    public function updateContribution(string $id, array $requestData): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        $contribution = $contributionService->updateContribution((int)$id, $requestData);
        
        return [
            'success' => true,
            'message' => 'Contribution updated successfully',
            'data' => $contribution->toArray()
        ];
    }
    
    /**
     * Delete contribution
     */
    public function deleteContribution(string $id, array $requestData): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        $deleted = $contributionService->deleteContribution((int)$id);
        
        return [
            'success' => $deleted,
            'message' => $deleted ? 'Contribution deleted successfully' : 'Failed to delete contribution'
        ];
    }
    
    /**
     * Get contribution statistics
     */
    public function getContributionStatistics(): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        $stats = $contributionService->getContributionStatistics();
        
        return [
            'success' => true,
            'data' => $stats
        ];
    }
    
    /**
     * Generate contribution report
     */
    public function generateContributionReport(array $requestData): array
    {
        $contributionService = $this->container->resolve(ContributionService::class);
        
        $startDate = $requestData['start_date'] ?? date('Y-m-01');
        $endDate = $requestData['end_date'] ?? date('Y-m-t');
        $type = $requestData['type'] ?? null;
        
        $report = $contributionService->generateContributionReport($startDate, $endDate, $type);
        
        return [
            'success' => true,
            'data' => $report
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $loanService = $this->container->resolve(LoanService::class);
        $contributionService = $this->container->resolve(ContributionService::class);
        
        $loanStats = $loanService->getLoanStatistics();
        $contributionStats = $contributionService->getContributionStatistics();
        
        return [
            'success' => true,
            'data' => [
                'loans' => $loanStats,
                'contributions' => $contributionStats,
                'summary' => [
                    'total_members' => $loanStats['total_loans'] + $contributionStats['contributing_members'] ?? 0,
                    'total_portfolio' => ($loanStats['active_amount'] ?? 0) + ($contributionStats['confirmed_amount'] ?? 0),
                    'monthly_collections' => $contributionStats['contributions_last_30_days'] ?? 0,
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
        // TODO: Implement proper authentication
        return [
            'success' => true,
            'message' => 'Login successful',
            'token' => 'placeholder_token'
        ];
    }
    
    public function logout(array $requestData): array
    {
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    public function getCurrentUser(): array
    {
        return [
            'success' => true,
            'data' => [
                'user_id' => 1,
                'username' => 'admin',
                'role' => 'Admin'
            ]
        ];
    }
    
    // Placeholder member methods
    public function getMembers(array $requestData): array
    {
        return ['success' => true, 'message' => 'Members endpoint - to be implemented'];
    }
    
    public function getMember(string $id): array
    {
        return ['success' => true, 'message' => 'Get member endpoint - to be implemented'];
    }
    
    public function createMember(array $requestData): array
    {
        return ['success' => true, 'message' => 'Create member endpoint - to be implemented'];
    }
    
    public function updateMember(string $id, array $requestData): array
    {
        return ['success' => true, 'message' => 'Update member endpoint - to be implemented'];
    }
    
    public function deleteMember(string $id, array $requestData): array
    {
        return ['success' => true, 'message' => 'Delete member endpoint - to be implemented'];
    }
    
    public function getMemberSummary(string $id): array
    {
        return ['success' => true, 'message' => 'Member summary endpoint - to be implemented'];
    }
    
    public function searchMembers(array $requestData): array
    {
        return ['success' => true, 'message' => 'Search members endpoint - to be implemented'];
    }
    
    public function confirmContribution(string $id, array $requestData): array
    {
        return ['success' => true, 'message' => 'Confirm contribution endpoint - to be implemented'];
    }
    
    public function rejectContribution(string $id, array $requestData): array
    {
        return ['success' => true, 'message' => 'Reject contribution endpoint - to be implemented'];
    }
    
    public function bulkImportContributions(array $requestData): array
    {
        return ['success' => true, 'message' => 'Bulk import endpoint - to be implemented'];
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
