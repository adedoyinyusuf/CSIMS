<?php

namespace CSIMS\Controllers;

use CSIMS\Services\SecurityService;
use CSIMS\Services\ConfigurationManager;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\CSIMSException;

/**
 * Base Controller
 * 
 * Provides common functionality for all controllers including security,
 * validation, error handling, and response formatting
 */
abstract class BaseController
{
    protected SecurityService $security;
    protected ConfigurationManager $config;
    protected array $data = [];
    protected array $errors = [];
    protected string $viewPath = '';
    
    public function __construct(
        SecurityService $security,
        ConfigurationManager $config
    ) {
        $this->security = $security;
        $this->config = $config;
        $this->initialize();
    }
    
    /**
     * Initialize controller - override in child classes
     */
    protected function initialize(): void
    {
        // Override in child classes for controller-specific initialization
    }
    
    /**
     * Validate CSRF token for POST requests
     * 
     * @throws SecurityException
     */
    protected function validateCSRF(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->security->validateCSRFForRequest();
        }
    }
    
    /**
     * Sanitize and validate input data
     * 
     * @param array $data Input data
     * @param array $rules Validation rules
     * @return array Sanitized data
     * @throws ValidationException
     */
    protected function validateInput(array $data, array $rules = []): array
    {
        // Sanitize all input data
        $sanitizedData = $this->security->sanitizeArray($data);
        
        // Apply validation rules if provided
        if (!empty($rules)) {
            $validation = $this->security->validateInput($sanitizedData, $rules);
            
            if (!$validation->isValid()) {
                throw new ValidationException('Validation failed', 0, null, [
                    'errors' => $validation->getErrors(),
                    'data' => $sanitizedData
                ]);
            }
        }
        
        return $sanitizedData;
    }
    
    /**
     * Handle exceptions and return appropriate response
     * 
     * @param \Exception $e
     * @return array
     */
    protected function handleException(\Exception $e): array
    {
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'error_type' => get_class($e)
        ];
        
        // Add specific error data for certain exception types
        if ($e instanceof ValidationException) {
            $response['errors'] = $e->getContext()['errors'] ?? [];
            $response['data'] = $e->getContext()['data'] ?? [];
        }
        
        // Log error if not in production
        if ($this->config->getEnvironment() !== 'production') {
            $response['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        // Log to error log
        error_log("Controller Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        
        return $response;
    }
    
    /**
     * Create success response
     * 
     * @param string $message Success message
     * @param mixed $data Additional data
     * @param array $meta Meta information
     * @return array
     */
    protected function successResponse(string $message, $data = null, array $meta = []): array
    {
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        return $response;
    }
    
    /**
     * Create error response
     * 
     * @param string $message Error message
     * @param array $errors Validation errors
     * @param mixed $data Additional data
     * @return array
     */
    protected function errorResponse(string $message, array $errors = [], $data = null): array
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $response;
    }
    
    /**
     * Get paginated results
     * 
     * @param callable $queryCallback Callback that returns paginated data
     * @param int $page Current page
     * @param int $limit Items per page
     * @return array
     */
    protected function getPaginatedResults(callable $queryCallback, int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit)); // Limit between 1 and 100
        
        try {
            $result = $queryCallback($page, $limit);
            
            return $this->successResponse('Data retrieved successfully', $result['data'], [
                'pagination' => $result['pagination'] ?? []
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Require authentication - throws exception if not authenticated
     * 
     * @param string $userType Required user type ('admin' or 'member')
     * @throws SecurityException
     */
    protected function requireAuthentication(string $userType = 'member'): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $sessionKey = $userType === 'admin' ? 'admin_user' : 'member_user';
        
        if (!isset($_SESSION[$sessionKey])) {
            throw new SecurityException('Authentication required');
        }
        
        // Additional security checks can be added here
        // For example, session timeout, IP validation, etc.
    }
    
    /**
     * Get current user information
     * 
     * @param string $userType User type ('admin' or 'member')
     * @return array|null
     */
    protected function getCurrentUser(string $userType = 'member'): ?array
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $sessionKey = $userType === 'admin' ? 'admin_user' : 'member_user';
        
        return $_SESSION[$sessionKey] ?? null;
    }
    
    /**
     * Check if user has specific permission
     * 
     * @param string $permission Permission to check
     * @param string $userType User type
     * @return bool
     */
    protected function hasPermission(string $permission, string $userType = 'member'): bool
    {
        $user = $this->getCurrentUser($userType);
        
        if (!$user) {
            return false;
        }
        
        // Admin users have all permissions
        if ($userType === 'admin') {
            return true;
        }
        
        // Add specific permission logic here based on your requirements
        // For now, return true for active members
        return isset($user['status']) && $user['status'] === 'Active';
    }
    
    /**
     * Render JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     */
    protected function renderJson(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Render view template (for backward compatibility)
     * 
     * @param string $template Template file
     * @param array $data Data to pass to template
     */
    protected function renderView(string $template, array $data = []): void
    {
        // Extract data for use in template
        extract($data);
        
        // Include the template file
        $templatePath = $this->getTemplatePath($template);
        
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            throw new \Exception("Template not found: {$template}");
        }
    }
    
    /**
     * Get template path
     * 
     * @param string $template Template name
     * @return string Full template path
     */
    protected function getTemplatePath(string $template): string
    {
        $basePath = dirname(dirname(__DIR__)) . '/views/';
        
        // Remove .php extension if provided
        $template = str_replace('.php', '', $template);
        
        return $basePath . $template . '.php';
    }
    
    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Get request method
     * 
     * @return string
     */
    protected function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    protected function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get request data based on method
     * 
     * @return array
     */
    protected function getRequestData(): array
    {
        switch ($this->getRequestMethod()) {
            case 'POST':
                return $_POST;
            case 'GET':
                return $_GET;
            case 'PUT':
            case 'PATCH':
                parse_str(file_get_contents('php://input'), $data);
                return $data;
            default:
                return [];
        }
    }
    
    /**
     * Log activity
     * 
     * @param string $action Action performed
     * @param string $entity Entity affected
     * @param mixed $entityId Entity ID
     * @param array $details Additional details
     */
    protected function logActivity(string $action, string $entity, $entityId = null, array $details = []): void
    {
        $user = $this->getCurrentUser('admin') ?? $this->getCurrentUser('member');

        // Attempt to derive actor from provided details; fall back to session user
        $actorFields = ['approved_by', 'rejected_by', 'disbursed_by', 'created_by', 'updated_by', 'performed_by'];
        $actorFromDetails = null;
        foreach ($actorFields as $field) {
            if (isset($details[$field]) && is_string($details[$field]) && trim($details[$field]) !== '') {
                $actorFromDetails = trim($details[$field]);
                break;
            }
        }

        $sessionActor = $user['username']
            ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
            ?? null;
        $actorName = $actorFromDetails ?: ($sessionActor ?: 'System');
        $actorSource = $actorFromDetails ? 'details' : 'session';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user['member_id'] ?? $user['admin_id'] ?? null,
            'user_type' => isset($user['admin_id']) ? 'admin' : 'member',
            'actor_name' => $actorName,
            'actor_source' => $actorSource,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $basePath = dirname(__DIR__, 2);
        $logsDir = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0775, true);
        }
        $logFile = $logsDir . DIRECTORY_SEPARATOR . 'audit.log';
        // Rotate logs if exceeding max size (5 MB)
        $maxSizeBytes = 5 * 1024 * 1024; // 5 MB
        try {
            if (file_exists($logFile)) {
                $size = @filesize($logFile);
                if (is_int($size) && $size >= $maxSizeBytes) {
                    $timestamp = date('Ymd-His');
                    $archiveFile = $logsDir . DIRECTORY_SEPARATOR . 'audit-' . $timestamp . '.log';
                    // Attempt rotation; if rename fails, continue writing to current file
                    @rename($logFile, $archiveFile);
                    // Seed new log with rotation marker
                    @file_put_contents($logFile, json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'action' => 'log_rotated',
                        'entity' => 'audit',
                        'details' => ['from' => basename($archiveFile), 'max_size_bytes' => $maxSizeBytes]
                    ]) . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal; continue with normal append
        }
        $line = json_encode($logData) . PHP_EOL;
        if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log("Activity Log (fallback): " . $line);
        }
    }
}