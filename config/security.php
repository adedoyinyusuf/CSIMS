<?php
// Prevent re-declaration when included multiple times
// Security Headers Configuration
if (!class_exists('SecurityHeaders')) {
class SecurityHeaders {
    public static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.tailwindcss.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.datatables.net https://cdn.tailwindcss.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self'; ";
        $csp .= "frame-ancestors 'none';";
        header("Content-Security-Policy: $csp");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
    }
}

// CSRF Protection
class CSRFProtection {
    private static $tokenName = 'csrf_token';
    
    public static function generateToken() {
        if (!isset($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$tokenName];
    }
    
    public static function validateToken($token) {
        if (!isset($_SESSION[self::$tokenName])) {
            return false;
        }
        return hash_equals($_SESSION[self::$tokenName], $token);
    }
    
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    public static function validateRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!self::validateToken($token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
}

// Enhanced Input Validation
class SecurityValidator {
    public static function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $data);
        }
        
        $data = trim($data);
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    public static function validateInput($data, $type, $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL);
            case 'int':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $flags = 0;
                if ($min !== null || $max !== null) {
                    $filter_options = [
                        'options' => ['min_range' => $min, 'max_range' => $max]
                    ];
                    return filter_var($data, FILTER_VALIDATE_INT, $filter_options);
                }
                return filter_var($data, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT);
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL);
            case 'regex':
                return preg_match($options['pattern'], $data);
            case 'length':
                $len = strlen($data);
                $min = $options['min'] ?? 0;
                $max = $options['max'] ?? PHP_INT_MAX;
                return $len >= $min && $len <= $max;
            default:
                return !empty($data);
        }
    }
    
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return empty($errors) ? true : $errors;
    }
}

// Rate Limiting
class RateLimiter {
    private static $redis = null;
    private static $useFile = true;
    
    public static function init() {
        // Try to use Redis if available, otherwise use file-based storage
        if (class_exists('Redis')) {
            try {
                $redisClass = '\\Redis';
                self::$redis = new $redisClass();
                self::$redis->connect('127.0.0.1', 6379);
                self::$useFile = false;
            } catch (\Exception $e) {
                self::$useFile = true;
            }
        }
    }
    
    public static function checkLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        self::init();
        
        if (self::$useFile) {
            return self::checkLimitFile($identifier, $maxAttempts, $timeWindow);
        } else {
            return self::checkLimitRedis($identifier, $maxAttempts, $timeWindow);
        }
    }
    
    private static function checkLimitFile($identifier, $maxAttempts, $timeWindow) {
        $file = sys_get_temp_dir() . '/csims_rate_limit_' . md5($identifier) . '.json';
        $now = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            // Clean old attempts
            $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
            
            if (count($data['attempts']) >= $maxAttempts) {
                return false;
            }
        } else {
            $data = ['attempts' => []];
        }
        
        $data['attempts'][] = $now;
        file_put_contents($file, json_encode($data));
        
        return true;
    }
    
    private static function checkLimitRedis($identifier, $maxAttempts, $timeWindow) {
        $key = 'rate_limit:' . $identifier;
        $now = time();
        
        // Remove old attempts
        self::$redis->zRemRangeByScore($key, 0, $now - $timeWindow);
        
        // Check current count
        $count = self::$redis->zCard($key);
        
        if ($count >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        self::$redis->zAdd($key, $now, $now);
        self::$redis->expire($key, $timeWindow);
        
        return true;
    }
}

// Security Logging
class SecurityLogger {
    private static $logFile = null;
    
    public static function init() {
        if (self::$logFile === null) {
            $logDir = ROOT_DIR . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            self::$logFile = $logDir . '/security.log';
        }
    }
    
    public static function log($level, $message, $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $userId = $_SESSION['admin_id'] ?? 'anonymous';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'user_id' => $userId,
            'context' => $context
        ];
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to database if critical
        if (in_array($level, ['critical', 'alert', 'emergency'])) {
            self::logToDatabase($level, $message, $context);
        }
    }
    
    private static function logToDatabase($level, $message, $context) {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO security_logs (event_type, description, user_id, ip_address, severity, context) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssss', 
                $level, 
                $message, 
                $_SESSION['admin_id'] ?? null, 
                $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
                $level, 
                json_encode($context)
            );
            $stmt->execute();
        } catch (Exception $e) {
            // Fallback to file logging if database fails
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
    
    public static function logSecurityEvent($event, $details = []) {
        self::log('warning', "Security Event: $event", $details);
    }
    
    public static function logSuspiciousActivity($activity, $details = []) {
        self::log('alert', "Suspicious Activity: $activity", $details);
    }
    
    public static function logCriticalSecurity($event, $details = []) {
        self::log('critical', "Critical Security Event: $event", $details);
    }
}
}

// Initialize security on every request
SecurityHeaders::setSecurityHeaders();
?>