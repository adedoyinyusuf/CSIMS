<?php

namespace CSIMS\Controllers;

use CSIMS\Services\ContributionService;
use CSIMS\Services\SecurityService;
use CSIMS\Container\Container;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\CSIMSException;

/**
 * Refactored Contribution Controller
 * 
 * Modern controller using dependency injection, services, and proper error handling
 */
class ContributionControllerRefactored
{
    private ContributionService $contributionService;
    private SecurityService $securityService;
    
    public function __construct(ContributionService $contributionService, SecurityService $securityService)
    {
        $this->contributionService = $contributionService;
        $this->securityService = $securityService;
    }
    
    /**
     * Create new contribution
     * 
     * @param array $requestData
     * @return array
     */
    public function create(array $requestData): array
    {
        try {
            // Validate CSRF token
            if (!$this->securityService->validateCsrfToken($requestData['csrf_token'] ?? '')) {
                throw new ValidationException('Invalid CSRF token');
            }
            
            // Create contribution
            $contribution = $this->contributionService->createContribution($requestData);
            
            return [
                'success' => true,
                'message' => 'Contribution created successfully',
                'data' => $contribution->toArray(),
                'id' => $contribution->getId()
            ];
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::create - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (CSIMSException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::create - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Update existing contribution
     * 
     * @param int $id
     * @param array $requestData
     * @return array
     */
    public function update(int $id, array $requestData): array
    {
        try {
            // Validate CSRF token
            if (!$this->securityService->validateCsrfToken($requestData['csrf_token'] ?? '')) {
                throw new ValidationException('Invalid CSRF token');
            }
            
            // Update contribution
            $contribution = $this->contributionService->updateContribution($id, $requestData);
            
            return [
                'success' => true,
                'message' => 'Contribution updated successfully',
                'data' => $contribution->toArray()
            ];
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::update - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (CSIMSException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::update - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Get contribution by ID
     * 
     * @param int $id
     * @return array
     */
    public function show(int $id): array
    {
        try {
            $contribution = $this->contributionService->getContribution($id);
            
            if (!$contribution) {
                return [
                    'success' => false,
                    'message' => 'Contribution not found',
                    'errors' => ['Contribution not found']
                ];
            }
            
            return [
                'success' => true,
                'data' => $contribution->toArray()
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::show - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::show - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Get all contributions with filtering and pagination
     * 
     * @param array $requestData
     * @return array
     */
    public function index(array $requestData): array
    {
        try {
            // Extract parameters
            $page = (int)($requestData['page'] ?? 1);
            $limit = min((int)($requestData['limit'] ?? 10), 100); // Cap at 100
            $search = $requestData['search'] ?? '';
            $sortBy = $requestData['sort_by'] ?? 'created_at';
            $sortOrder = $requestData['sort_order'] ?? 'DESC';
            $typeFilter = $requestData['type_filter'] ?? '';
            $dateFrom = $requestData['date_from'] ?? '';
            $dateTo = $requestData['date_to'] ?? '';
            
            // Build filters
            $filters = [];
            if ($typeFilter) {
                $filters['contribution_type'] = $typeFilter;
            }
            if ($dateFrom) {
                $filters['date_from'] = $dateFrom;
            }
            if ($dateTo) {
                $filters['date_to'] = $dateTo;
            }
            
            // Build ordering
            $orderBy = [$sortBy => strtoupper($sortOrder)];
            
            // Get contributions
            if ($search) {
                $result = $this->contributionService->searchContributions($search, $filters, $page, $limit);
            } else {
                $result = $this->contributionService->getContributions($filters, $page, $limit, $orderBy);
            }
            
            return [
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::index - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::index - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Delete contribution
     * 
     * @param int $id
     * @param array $requestData
     * @return array
     */
    public function delete(int $id, array $requestData): array
    {
        try {
            // Validate CSRF token
            if (!$this->securityService->validateCsrfToken($requestData['csrf_token'] ?? '')) {
                throw new ValidationException('Invalid CSRF token');
            }
            
            $deleted = $this->contributionService->deleteContribution($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete contribution',
                    'errors' => ['Delete failed']
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Contribution deleted successfully'
            ];
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::delete - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (CSIMSException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::delete - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Get contributions by member
     * 
     * @param int $memberId
     * @param array $requestData
     * @return array
     */
    public function getByMember(int $memberId, array $requestData = []): array
    {
        try {
            $contributions = $this->contributionService->getContributionsByMember($memberId);
            
            return [
                'success' => true,
                'data' => array_map(fn($contribution) => $contribution->toArray(), $contributions)
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::getByMember - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::getByMember - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Get contribution statistics
     * 
     * @param array $requestData
     * @return array
     */
    public function getStatistics(array $requestData = []): array
    {
        try {
            $statistics = $this->contributionService->getContributionStatistics();
            
            return [
                'success' => true,
                'data' => $statistics
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::getStatistics - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::getStatistics - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
    
    /**
     * Bulk import contributions
     * 
     * @param array $requestData
     * @return array
     */
    public function bulkImport(array $requestData): array
    {
        try {
            // Validate CSRF token
            if (!$this->securityService->validateCsrfToken($requestData['csrf_token'] ?? '')) {
                throw new ValidationException('Invalid CSRF token');
            }
            
            if (!isset($requestData['contributions']) || !is_array($requestData['contributions'])) {
                throw new ValidationException('Invalid contributions data');
            }
            
            $results = $this->contributionService->bulkImportContributions($requestData['contributions']);
            
            return [
                'success' => true,
                'message' => 'Bulk import completed',
                'data' => $results
            ];
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (DatabaseException $e) {
            error_log('Database error in ContributionController::bulkImport - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred. Please try again.',
                'errors' => ['Database error']
            ];
            
        } catch (CSIMSException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
            
        } catch (\Exception $e) {
            error_log('Unexpected error in ContributionController::bulkImport - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => ['Unexpected error']
            ];
        }
    }
}

/**
 * Factory function to create controller with dependencies
 * 
 * This shows how the controller would be instantiated using the container
 */
function createContributionController(): ContributionControllerRefactored 
{
    $container = Container::getInstance();
    
    return new ContributionControllerRefactored(
        $container->resolve(ContributionService::class),
        $container->resolve(SecurityService::class)
    );
}
