<?php

namespace CSIMS\Core;

use CSIMS\Config\Config;
use Throwable;

class ErrorHandler
{
    /**
     * Register the error and exception handlers
     */
    public static function register(): void
    {
        error_reporting(E_ALL);
        
        // Hide errors from user output
        ini_set('display_errors', 1);
        
        // Ensure errors are logged
        ini_set('log_errors', 1);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert PHP errors to Exceptions
     */
    public static function handleError($severity, $message, $file, $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(Throwable $e): void
    {
        self::logException($e);
        self::renderResponse($e);
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $e = new \ErrorException(
                $error['message'], 
                0, 
                $error['type'], 
                $error['file'], 
                $error['line']
            );
            self::handleException($e);
        }
    }

    /**
     * Log the exception securely
     */
    private static function logException(Throwable $e): void
    {
        $logMessage = sprintf(
            "[%s] %s: %s in %s:%d\nStack Trace:\n%s",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        error_log($logMessage);
    }

    /**
     * Render the response to the user
     */
    private static function renderResponse(Throwable $e): void
    {
        $isDebug = Config::getInstance()->isDebug();
        $isJson = self::isAjaxRequest();

        http_response_code(500);

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $isDebug ? $e->getMessage() : 'An internal server error occurred.',
                'error_code' => $e->getCode(),
                'debug' => $isDebug ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ] : null
            ]);
        } else {
            // HTML Response
            if ($isDebug) {
                // In debug mode, show everything
                echo "<h1>Error: " . htmlspecialchars($e->getMessage()) . "</h1>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            } else {
                // Production mode: Generic page
                // Check if a dedicated error view exists, otherwise echo generic HTML
                $errorView = __DIR__ . '/../../views/errors/500.php';
                if (file_exists($errorView)) {
                    include $errorView;
                } else {
                    echo "<h1>Server Error</h1>";
                    echo "<p>Something went wrong. Please try again later.</p>";
                }
            }
        }
    }

    /**
     * Check if request expects JSON
     */
    private static function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }
}
