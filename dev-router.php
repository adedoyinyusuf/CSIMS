<?php
// Development router for PHP built-in server
// Routes /api/* to api/index.php; everything else to index.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = __DIR__ . $uri;

// Allow built-in server to serve existing files
if (php_sapi_name() === 'cli-server') {
    if (is_file($path)) {
        return false;
    }
}

// Route API requests to the unified Router entry
if (preg_match('#^/api($|/)#', $uri)) {
    require __DIR__ . '/api.php';
    return;
}

// Default: provide a simple notice to avoid DB connection errors in dev
http_response_code(200);
header('Content-Type: text/plain');
echo "CSIMS dev server is running (API-only mode).\n";
echo "Use /api/... endpoints, e.g.:\n";
echo "  - /api/system/health\n";
echo "  - /api/auth/me\n";
echo "To run the full UI, start your web server (Apache/Nginx) with proper DB configuration.";
return;