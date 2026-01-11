<?php

/**
 * CSIMS Application Bootstrap
 * 
 * This file initializes the refactored CSIMS system with dependency injection,
 * proper error handling, and modern PHP practices.
 * 
 * Usage:
 * require_once 'src/bootstrap.php';
 * $container = CSIMS\bootstrap();
 */

namespace CSIMS;

use CSIMS\Container\Container;
use CSIMS\Config\Config;
use CSIMS\Services\SecurityService;
use CSIMS\Services\LoanService;
use CSIMS\Services\AuthService;
use CSIMS\Services\ConfigurationManager;
use CSIMS\Services\AuthenticationService;
use CSIMS\Repositories\LoanRepository;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Repositories\UserRepository;
use CSIMS\Controllers\AuthenticationController;
use CSIMS\Database\QueryBuilder;
use CSIMS\Cache\CacheInterface;
use CSIMS\Cache\FileCache;
use CSIMS\Exceptions\CSIMSException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\ContainerException;
use mysqli;

// Autoloader (you'll need Composer or a custom autoloader)
spl_autoload_register(function ($className) {
    if (strpos($className, 'CSIMS\\') === 0) {
        $classPath = str_replace('CSIMS\\', '', $className);
        $classPath = str_replace('\\', '/', $classPath);
        $file = __DIR__ . '/' . $classPath . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Bootstrap the CSIMS application
 * 
 * @return Container
 * @throws CSIMSException
 */
function bootstrap(): Container
{
    static $bootstrapped = false;
    
    // Get container instance
    $container = Container::getInstance();

    if ($bootstrapped) {
        return $container;
    }
    $bootstrapped = true;
    
    try {
        // Register configuration
        $config = Config::getInstance();
        $container->instance(Config::class, $config);
        
        // Register cache
        $container->bind(CacheInterface::class, function(Container $c) {
            return new FileCache($c->resolve(Config::class));
        }, true);
        
        // Setup database connection
        $container->bind(mysqli::class, function() use ($config) {
            $dbConfig = $config->getDatabase();
            // Runtime instrumentation to verify DB credentials being used
            // error_log('DB_CONFIG: ' . json_encode($dbConfig));
        
            $connection = new mysqli(
                $dbConfig['host'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                $dbConfig['port']
            );
            
            if ($connection->connect_error) {
                throw new DatabaseException('Database connection failed: ' . $connection->connect_error);
            }
            
            $connection->set_charset($dbConfig['charset']);
            
            return $connection;
        });
        
        // Register core services
        // SecurityService does not require a database connection in its constructor.
        // Passing arguments to a class without a constructor causes an ArgumentCountError (Throwable),
        // which led to the "Internal error" response on endpoints resolving AuthService.
        $container->bind(SecurityService::class, function(Container $c) {
            return new SecurityService();
        });

        // Register configuration manager (singleton)
        $container->bind(ConfigurationManager::class, function(Container $c) {
            return ConfigurationManager::getInstance();
        }, true);
        
        // Register repositories
        $container->bind(MemberRepository::class, function(Container $c) {
            return new MemberRepository($c->resolve(mysqli::class));
        });
        
        $container->bind(LoanRepository::class, function(Container $c) {
            return new LoanRepository($c->resolve(mysqli::class));
        });
        
        $container->bind(UserRepository::class, function(Container $c) {
            return new UserRepository($c->resolve(mysqli::class));
        });
        
        // Register services
        $container->bind(LoanService::class, function(Container $c) {
            return new LoanService(
                $c->resolve(LoanRepository::class),
                $c->resolve(MemberRepository::class),
                $c->resolve(SecurityService::class)
            );
        });

        // Register new AuthService for member authentication flows
        $container->bind(AuthService::class, function(Container $c) {
            return new AuthService(
                $c->resolve(SecurityService::class),
                $c->resolve(MemberRepository::class),
                $c->resolve(ConfigurationManager::class)
            );
        });
        
        $container->bind(AuthenticationService::class, function(Container $c) {
            $config = $c->resolve(Config::class);
            return new AuthenticationService(
                $c->resolve(UserRepository::class),
                $c->resolve(SecurityService::class),
                $c->resolve(mysqli::class),
                $config->get('session.timeout', 3600),
                $config->get('security.lockout.max_attempts', 5),
                $config->get('security.lockout.duration', 1800)
            );
        });
        
        // Register controllers
        $container->bind(AuthenticationController::class, function(Container $c) {
            return new AuthenticationController(
                $c->resolve(AuthenticationService::class),
                $c->resolve(SecurityService::class)
            );
        });
        
        // Setup error handling
        setupErrorHandling();
        
        return $container;
        
    } catch (\Exception $e) {
        throw new ContainerException('Bootstrap failed: ' . $e->getMessage(), 0, $e);
    }
}

/**
 * Get configuration instance
 * 
 * @return Config
 */
function getConfig(): Config
{
    return Config::getInstance();
}

/**
 * Setup error handling
 */
function setupErrorHandling(): void
{
    // Set error reporting level
    error_reporting(E_ALL);
    ini_set('display_errors', 1); // Enable error display for debugging
    ini_set('log_errors', 1);     // Log errors
    
    // Set custom error handler
    set_error_handler(function($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    
    // Set custom exception handler
    set_exception_handler(function($exception) {
        error_log('Uncaught exception: ' . $exception->getMessage());
        error_log('Stack trace: ' . $exception->getTraceAsString());
        
        // In production, show a generic error message
        if (!isDebugMode()) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'An internal server error occurred. Please try again later.',
                'errors' => ['Internal server error']
            ]);
        } else {
            // In debug mode, show detailed error
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => [$exception->getMessage()],
                'debug' => [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ]
            ]);
        }
        exit;
    });
}

/**
 * Check if application is in debug mode
 * 
 * @return bool
 */
function isDebugMode(): bool
{
    return Config::getInstance()->isDebug();
}

/**
 * Create a simple router for API endpoints
 * 
 * @param Container $container
 * @return void
 */
function handleApiRequest(Container $container): void
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Remove query string
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Basic routing
    $routes = [
        'GET /api/loans' => function() use ($container) {
            $service = $container->resolve(LoanService::class);
            $filters = $_GET;
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 10), 100);
            
            $result = $service->getLoans($filters, $page, $limit);
            return $result;
        },
        
        'GET /api/loans/{id}' => function($id) use ($container) {
            $service = $container->resolve(LoanService::class);
            $loan = $service->getLoan((int)$id);
            
            if (!$loan) {
                http_response_code(404);
                return ['success' => false, 'message' => 'Loan not found'];
            }
            
            return ['success' => true, 'data' => $loan->toArray()];
        },
        
        'POST /api/loans' => function() use ($container) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                return ['success' => false, 'message' => 'Invalid JSON input'];
            }
            
            $service = $container->resolve(LoanService::class);
            $loan = $service->createLoan($input);
            
            http_response_code(201);
            return ['success' => true, 'data' => $loan->toArray(), 'id' => $loan->getId()];
        }
    ];
    
    // Simple route matching
    $route = $requestMethod . ' ' . $path;
    
    // Check for parameterized routes
    foreach ($routes as $pattern => $handler) {
        if (preg_match('#^' . str_replace(['{id}'], ['(\d+)'], $pattern) . '$#', $route, $matches)) {
            array_shift($matches); // Remove full match
            
            try {
                $result = call_user_func_array($handler, $matches);
                
                header('Content-Type: application/json');
                echo json_encode($result);
                return;
                
            } catch (\Exception $e) {
                throw $e; // Let exception handler deal with it
            }
        }
    }
    
    // Route not found
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Route not found',
        'errors' => ['Route not found']
    ]);
}
