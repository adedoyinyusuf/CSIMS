<?php

/**
 * CSIMS API Entry Point - Enhanced Version
 * 
 * Features:
 * - API Versioning (/api/v1/, /api/v2/)
 * - Token-based authentication
 * - Rate limiting
 * - Request/response logging
 * - CORS configuration
 * 
 * Version: 2.0.0
 * Updated: 2025-12-24
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include bootstrap
require_once __DIR__ . '/src/bootstrap.php';

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $allowed_origins = explode(',', $_ENV['API_ALLOWED_ORIGINS'] ?? getenv('API_ALLOWED_ORIGINS') ?: $_SERVER['HTTP_HOST'] ?? 'localhost');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed_origins) || in_array('*', $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }
    
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
    header('Access-Control-Max-Age: 86400 ');
    http_response_code(200);
    exit;
}

try {
    // Bootstrap application
    $container = CSIMS\bootstrap();
    
    // Use versioned router
    $router = new \CSIMS\API\VersionedRouter($container);
    $router->handleRequest();
    
} catch (\CSIMS\Exceptions\CSIMSException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => 'System Error',
        'message' => 'A system error occurred. Please try again later.'
    ];
    
    if (\CSIMS\isDebugMode()) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    error_log('CSIMS API Error: ' . $e->getMessage());
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
    
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred. Please try again later.'
    ];
    
    if (\CSIMS\isDebugMode()) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    error_log('Unexpected API Error: ' . $e->getMessage());
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
