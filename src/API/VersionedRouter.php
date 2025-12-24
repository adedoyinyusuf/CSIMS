<?php

namespace CSIMS\API;

/**
 * Versioned API Router
 * Supports API versioning (e.g., /api/v1/endpoint)
 */
class VersionedRouter
{
    private $container;
    private $middleware;
    private $version = 'v1'; // Default version
    private $routes = [];
    
    public function __construct($container)
    {
        $this->container = $container;
        $this->middleware = new APIMiddleware($container);
        $this->registerRoutes();
    }
    
    /**
     * Handle incoming API request
     */
    public function handleRequest()
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Parse path and extract version
        $parsedPath = $this->parsePath($path);
        $this->version = $parsedPath['version'];
        $endpoint = $parsedPath['endpoint'];
        
        // Create request object
        $request = [
            'method' => $method,
            'path' => $path,
            'endpoint' => $endpoint,
            'version' => $this->version,
            'query' => $_GET,
            'body' => $this->getRequestBody()
        ];
        
        // Process through middleware
        $request = $this->middleware->process($request);
        
        // Route to appropriate handler
        $this->route($request);
    }
    
    /**
     * Parse request path to extract version and endpoint
     */
    private function parsePath($path)
    {
        // Remove query string
        $path = strtok($path, '?');
        
        // Match pattern: /api/vX/endpoint
        if (preg_match('#^/api/(v\d+)/(.+)$#', $path, $matches)) {
            return [
                'version' => $matches[1],
                'endpoint' => '/' . $matches[2]
            ];
        }
        
        // Legacy pattern: /api/endpoint (default to v1)
        if (preg_match('#^/api/(.+)$#', $path, $matches)) {
            return [
                'version' => 'v1',
                'endpoint' => '/' .  $matches[1]
            ];
        }
        
        // No version specified, use default
        return [
            'version' => 'v1',
            'endpoint' => $path
        ];
    }
    
    /**
     * Route request to appropriate handler
     */
    private function route($request)
    {
        $method = $request['method'];
        $endpoint = $request['endpoint'];
        $version = $request['version'];
        
        // Build route key
        $routeKey = strtoupper($method) . ' ' . $endpoint;
        
        // Check if route exists for this version
        if (isset($this->routes[$version][$routeKey])) {
            $handler = $this->routes[$version][$routeKey];
            $this->executeHandler($handler, $request);
            return;
        }
        
        // Check for wildcard/regex routes
        foreach ($this->routes[$version] ?? [] as $pattern => $handler) {
            if ($this->matchesPattern($pattern, $routeKey)) {
                $this->executeHandler($handler, $request);
                return;
            }
        }
        
        // Route not found
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Not Found',
            'message' => "Endpoint not found: $endpoint",
            'version' => $version,
            'available_versions' => array_keys($this->routes)
        ]);
    }
    
    /**
     * Execute route handler  
     */
    private function executeHandler($handler, $request)
    {
        try {
            if (is_callable($handler)) {
                $response = call_user_func($handler, $request, $this->container);
            } elseif (is_array($handler)) {
                // [ControllerClass, 'method'] format
                list($class, $method) = $handler;
                $controller = new $class($this->container);
                $response = $controller->$method($request);
            } else {
                throw new \Exception("Invalid handler format");
            }
            
            $this->sendResponse($response);
            
        } catch (\Exception $e) {
            $this->sendError($e);
        }
    }
    
    /**
     * Register API routes
     */
    private function registerRoutes()
    {
        // V1 Routes
        $this->routes['v1'] = [
            // Health & Status
            'GET /health' => [$this, 'healthCheck'],
            'GET /status' => [$this, 'statusCheck'],
            
            // Authentication
            'POST /auth/login' => [$this, 'login'],
            '  POST /auth/logout' => [$this, 'logout'],
            'POST /auth/refresh' => [$this, 'refreshToken'],
            
            // API Tokens
            'POST /tokens' => [$this, 'createToken'],
            'GET /tokens' => [$this, 'listTokens'],
            'DELETE /tokens/*' => [$this, 'revokeToken'],
            
            // Add your existing routes here
            // 'GET /loans' => [LoanController::class, 'index'],
            // 'POST /loans' => [LoanController::class, 'create'],
            // etc.
        ];
        
        // V2 Routes (for future use)
        $this->routes['v2'] = [
            'GET /health' => [$this, 'healthCheck'],
            'GET /status' => [$this, 'statusCheck'],
            // V2 specific routes...
        ];
    }
    
    /**
     * Health check endpoint
     */
    public function healthCheck($request)
    {
        return [
            'success' => true,
            'status' => 'healthy',
            'version' => $request['version'],
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Status check endpoint
     */
    public function statusCheck($request)
    {
        $conn = $this->container->get('db');
        
        return [
            'success' => true,
            'status' => 'operational',
            'version' => $request['version'],
            'services' => [
                'database' => $conn->ping() ? 'connected' : 'disconnected',
                'api' => 'online'
            ],
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Create API token
     */
    public function createToken($request)
    {
        if (!isset($request['auth']['id'])) {
            return [
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'You must be logged in to create API tokens'
            ];
        }
        
        $userId = $request['auth']['id'];
        $name = $request['body']['name'] ?? 'API Token';
        $expiresIn = $request['body']['expires_in'] ?? 365; // days
        
        $token = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresIn days"));
        
        $conn = $this->container->get('db');
        $stmt = $conn->prepare("
            INSERT INTO api_tokens (user_id, token, name, expires_at, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param('isss', $userId, $token, $name, $expiresAt);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'token' => $token,
                'name' => $name,
                'expires_at' => $expiresAt,
                'message' => 'API token created successfully. Store it securely - you won\'t be able to see it again.'
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Token Creation Failed',
            'message' => 'Unable to create API token'
        ];
    }
    
    /**
     * Generate secure API token
     */
    private function generateToken()
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get request body
     */
    private function getRequestBody()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($response, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        // Add API version header
        header('X-API-Version: ' . $this->version);
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
    
    /**
     * Send error response
     */
    private function sendError($exception, $statusCode = 500)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => 'Server Error',
            'message' => $exception->getMessage()
        ];
        
        if (\CSIMS\isDebugMode()) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
    
    /**
     * Check if route pattern matches
     */
    private function matchesPattern($pattern, $route)
    {
        // Convert wildcard to regex
        $regex = str_replace('*', '.*', preg_quote($pattern, '#'));
        return preg_match("#^$regex$#", $route);
    }
}
