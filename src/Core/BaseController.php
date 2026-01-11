<?php

namespace CSIMS\Core;

use CSRFProtection; // Fallback for legacy class in global namespace if needed, or CSIMS\Security\CSRFProtection
use Database; // Legacy database wrapper if strictly needed, but better to use DI
use Session;

/**
 * BaseController
 * 
 * All controllers should extend this class to ensure consistent security,
 * error handling, and response formatting.
 */
abstract class BaseController
{
    /** @var \mysqli */
    protected $db;

    /** @var Session */
    protected $session;

    /** @var int */
    protected $userId;

    public function __construct()
    {
        // 1. Initialize Core Services
        $this->session = Session::getInstance();
        $this->db = \Database::getInstance()->getConnection(); // Using the singleton wrapper
        
        // 2. Security Headers (redundant if handled in bootstrap, but safe to enforce)
        $this->setSecurityHeaders();

        // 3. User Identification
        if ($this->session->isLoggedIn()) {
            $this->userId = $_SESSION['user_id'] ?? 0;
        }
    }

    /**
     * Enforce authentication for the current request.
     * Redirects to login if not authenticated.
     */
    protected function requireAuth(): void
    {
        if (!$this->session->isLoggedIn()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['error' => 'Unauthorized', 'redirect' => BASE_URL . '/login.php'], 401);
            } else {
                $this->session->setFlash('error', 'Please log in to access this page.');
                $this->redirect('/login.php');
            }
        }
    }

    /**
     * Enforce CSRF protection for POST requests.
     */
    protected function validateCSRF(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check if legacy CSRFProtection class exists
            if (class_exists('CSRFProtection')) {
                \CSRFProtection::validateRequest();
            } else {
                // Fallback implementation if class missing (should not happen in prod)
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                    $this->jsonResponse(['error' => 'Invalid CSRF Token'], 403);
                }
            }
        }
    }

    /**
     * Send a JSON response and exit.
     */
    protected function jsonResponse(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Render a view properly.
     * Prevents direct access to view files from URL if structure allows.
     */
    protected function render(string $viewPath, array $data = []): void
    {
        extract($data);
        $viewFile = ROOT_DIR . '/views/' . $viewPath;
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            throw new \Exception("View file not found: $viewPath");
        }
    }

    /**
     * Redirect helper
     */
    protected function redirect(string $url): never
    {
        // Ensure URL is absolute or relative to BASE_URL
        if (strpos($url, 'http') !== 0) {
            $url = BASE_URL . '/' . ltrim($url, '/');
        }
        header("Location: $url");
        exit;
    }

    /**
     * XSS Protection helper
     */
    protected function e($string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Set basic security headers
     */
    private function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }
}
