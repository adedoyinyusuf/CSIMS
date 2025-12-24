<?php

namespace CSIMS\API;

/**
 * API Middleware - Handles authentication, rate limiting, logging
 */
class APIMiddleware
{
    private $container;
    private $rateLimiter;
    private $apiLogger;
    
    public function __construct($container)
    {
        $this->container = $container;
        $this->rateLimiter = new APIRateLimiter();
        $this->apiLogger = new APILogger();
    }
    
    /**
     * Process request through middleware pipeline
     */
    public function process($request)
    {
        // 1. Log request
        $requestId = $this->apiLogger->logRequest($request);
        
        // 2. Check rate limit
        if (!$this->rateLimiter->check($request)) {
            $this->apiLogger->logResponse($requestId, 429, ['error' => 'Rate limit exceeded']);
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Rate Limit Exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $this->rateLimiter->getRetryAfter()
            ]);
            exit;
        }
        
        // 3. Authenticate request (token or session)
        $auth = $this->authenticate($request);
        if (!$auth['success']) {
            $this->apiLogger->logResponse($requestId, 401, $auth);
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode($auth);
            exit;
        }
        
        // Add auth info to request
        $request['auth'] = $auth['user'];
        $request['request_id'] = $requestId;
        
        return $request;
    }
    
    /**
     * Authenticate API request
     * Supports both API tokens and session-based auth
     */
    private function authenticate($request)
    {
        // Check for API token in Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            // API Token authentication
            $token = $matches[1];
            return $this->authenticateToken($token);
        }
        
        // Fall back to session-based authentication
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'type' => $_SESSION['user_type'],
                    'auth_method' => 'session'
                ]
            ];
        }
        
        // Check for public endpoints
        if ($this->isPublicEndpoint($request)) {
            return [
                'success' => true,
                'user' => [
                    'id' => null,
                    'type' => 'guest',
                    'auth_method' => 'public'
                ]  
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Authentication required. Provide a valid API token or login.'
        ];
    }
    
    /**
     * Authenticate using API token
     */
    private function authenticateToken($token)
    {
        $conn = $this->container->get('db');
        
        $stmt = $conn->prepare("
            SELECT t.*, u.username, u.email, u.role 
            FROM api_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = ? AND t.is_active = 1 AND t.expires_at > NOW()
        ");
        
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Invalid Token',
                'message' => 'The provided API token is invalid or expired.'
            ];
        }
        
        $tokenData = $result->fetch_assoc();
        
        // Update last used
        $updateStmt = $conn->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?");
        $updateStmt->bind_param('i', $tokenData['id']);
        $updateStmt->execute();
        
        return [
            'success' => true,
            'user' => [
                'id' => $tokenData['user_id'],
                'username' => $tokenData['username'],
                'email' => $tokenData['email'],
                'role' => $tokenData['role'],
                'type' => 'user',
                'auth_method' => 'token'
            ]
        ];
    }
    
    /**
     * Check if endpoint is public (doesn't require auth)
     */
    private function isPublicEndpoint($request)
    {
        $publicEndpoints = [
            '/api/v1/health',
            '/api/v1/status',
            '/api/health',
            '/api/status'
        ];
        
        $path = $request['path'] ?? '';
        return in_array($path, $publicEndpoints);
    }
}

/**
 * API Rate Limiter
 */
class APIRateLimiter
{
    private $maxRequests;
    private $timeWindow;
    private $storage;
    
    public function __construct()
    {
        // Get from environment or use defaults
        $this->maxRequests = (int)($_ENV['API_RATE_LIMIT'] ?? getenv('API_RATE_LIMIT') ?: 100);
        $this->timeWindow = (int)($_ENV['API_RATE_LIMIT_PERIOD'] ?? getenv('API_RATE_LIMIT_PERIOD') ?: 3600);
        $this->storage = __DIR__ . '/../../cache/rate_limits/';
        
        if (!is_dir($this->storage)) {
            mkdir($this->storage, 0755, true);
        }
    }
    
    /**
     * Check if request is within rate limit
     */
    public function check($request)
    {
        $identifier = $this->getIdentifier($request);
        $key = md5($identifier);
        $file = $this->storage . $key;
        
        $now = time();
        $requests = [];
        
        // Load existing requests
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $requests = $data['requests'] ?? [];
        }
        
        // Remove old requests outside time window
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return $timestamp > ($now - $this->timeWindow);
        });
        
        // Check if limit exceeded
        if (count($requests) >= $this->maxRequests) {
            return false;
        }
        
        // Add current request
        $requests[] = $now;
        
        // Save
        file_put_contents($file, json_encode([
            'requests' => $requests,
            'identifier' => $identifier
        ]));
        
        return true;
    }
    
    /**
     * Get unique identifier for rate limiting
     */
    private function getIdentifier($request)
    {
        // Use API token if available
        if (isset($request['auth']['id']) && $request['auth']['auth_method'] === 'token') {
            return 'token_' . $request['auth']['id'];
        }
        
        // Use session ID if available
        if (session_id()) {
            return 'session_' . session_id();
        }
        
        // Fall back to IP address
        return 'ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    
    /**
     * Get retry-after seconds
     */
    public function getRetryAfter()
    {
        return $this->timeWindow;
    }
}

/**
 * API Logger - Logs requests and responses
 */
class APILogger
{
    private $logDir;
    
    public function __construct()
    {
        $this->logDir = __DIR__ . '/../../logs/api/';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log API request
     * Returns request ID
     */
    public function logRequest($request)
    {
        $requestId = uniqid('req_', true);
        
        $logEntry = [
            'request_id' => $requestId,
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'path' => $request['path'] ?? $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'auth_method' => $request['auth']['auth_method'] ?? 'none',
            'user_id' => $request['auth']['id'] ?? null,
            'headers' => $this->getSafeHeaders(),
            'query' => $_GET,
            'body_size' => $_SERVER['CONTENT_LENGTH'] ?? 0
        ];
        
        $this->writeLog('requests', $logEntry);
        
        return $requestId;
    }
    
    /**
     * Log API response
     */
    public function logResponse($requestId, $statusCode, $response)
    {
        $logEntry = [
            'request_id' => $requestId,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $statusCode,
            'response_size' => strlen(json_encode($response)),
            'success' => $statusCode >= 200 && $statusCode < 300
        ];
        
        $this->writeLog('responses', $logEntry);
    }
    
    /**
     * Write log entry
     */
    private function writeLog($type, $entry)
    {
        $date = date('Y-m-d');
        $file = $this->logDir . "{$type}_{$date}.log";
        
        $line = json_encode($entry) . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get safe headers (exclude sensitive data)
     */
    private function getSafeHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                // Exclude sensitive headers
                if (!in_array($headerName, ['AUTHORIZATION', 'COOKIE', 'PASSWORD'])) {
                    $headers[$headerName] = $value;
                }
            }
        }
        return $headers;
    }
}
