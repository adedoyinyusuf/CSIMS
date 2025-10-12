<?php

/**
 * Simple PSR-4 Autoloader for CSIMS
 * 
 * This autoloader handles the new refactored classes
 */

spl_autoload_register(function ($className) {
    // Only handle CSIMS namespace
    if (strpos($className, 'CSIMS\\') !== 0) {
        return;
    }
    
    // Remove CSIMS\ prefix
    $className = substr($className, 6);
    
    // Convert namespace separators to directory separators
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    // Build the file path
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $classPath . '.php';
    
    // Load the file if it exists
    if (file_exists($filePath)) {
        require_once $filePath;
        return true;
    }
    
    return false;
});

/**
 * Helper function to initialize the container with default bindings
 */
function initializeContainer(): \CSIMS\Container\Container
{
    $container = \CSIMS\Container\Container::getInstance();
    
    // Bind interfaces to implementations
    $container->singleton(\CSIMS\Services\SecurityService::class, \CSIMS\Services\SecurityService::class);
    $container->singleton(\CSIMS\Services\ConfigurationManager::class, function() {
        return \CSIMS\Services\ConfigurationManager::getInstance();
    });
    
    // Bind NotificationService
    $container->singleton(\CSIMS\Services\NotificationService::class, function($container) {
        $database = $container->resolve('database');
        return new \CSIMS\Services\NotificationService($database);
    });
    
    // Bind database connection
    $container->singleton('database', function($container) {
        $config = $container->resolve(\CSIMS\Services\ConfigurationManager::class);
        $dbConfig = $config->getDatabaseConfig();
        
        $connection = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database']
        );
        
        if ($connection->connect_error) {
            throw new \CSIMS\Exceptions\DatabaseException('Database connection failed: ' . $connection->connect_error);
        }
        
        return $connection;
    });
    
    // Bind repositories
    $container->bind(\CSIMS\Repositories\MemberRepository::class, function($container) {
        $database = $container->resolve('database');
        return new \CSIMS\Repositories\MemberRepository($database);
    });
    
    return $container;
}

/**
 * Bootstrap the new architecture
 */
function bootstrapNewArchitecture(): \CSIMS\Container\Container
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Initialize container
    $container = initializeContainer();
    
    // Set security headers
    $security = $container->resolve(\CSIMS\Services\SecurityService::class);
    $security->setSecurityHeaders();
    
    return $container;
}

/**
 * Get global container instance
 */
function container(): \CSIMS\Container\Container
{
    static $container = null;
    
    if ($container === null) {
        $container = bootstrapNewArchitecture();
    }
    
    return $container;
}

/**
 * Helper function to resolve dependencies from container
 */
function resolve(string $abstract): mixed
{
    return container()->resolve($abstract);
}
