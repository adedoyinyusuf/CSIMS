<?php

namespace CSIMS\Services;

use CSIMS\DTOs\ValidationResult;
use CSIMS\Exceptions\SecurityException;
use CSIMS\Exceptions\ValidationException;

/**
 * Consolidated Security Service
 * 
 * Handles all security-related operations including validation, sanitization,
 * CSRF protection, and rate limiting
 */
class SecurityService
{
    private const CSRF_TOKEN_NAME = 'csrf_token';
    private const RATE_LIMIT_PREFIX = 'csims_rate_limit_';
    
    /**
     * Sanitize input data
     * 
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public function sanitizeInput(mixed $data, string $type = 'string'): mixed
    {
        if (is_array($data)) {
            return array_map(fn($item) => $this->sanitizeInput($item, $type), $data);
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        $data = trim($data);
        $data = stripslashes($data);
        
        return match ($type) {
            'email' => filter_var($data, FILTER_SANITIZE_EMAIL),
            'int' => filter_var($data, FILTER_SANITIZE_NUMBER_INT),
            'float' => filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'url' => filter_var($data, FILTER_SANITIZE_URL),
            'html' => htmlspecialchars($data, ENT_QUOTES, 'UTF-8'),
            'string' => htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8'),
            default => htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8')
        };
    }
    
    /**
     * Validate input data
     * 
     * @param array $data
     * @param array $rules
     * @return ValidationResult
     */
    public function validateInput(array $data, array $rules): ValidationResult
    {
        $result = new ValidationResult();
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                if (!$this->validateField($value, $rule)) {
                    $result->addError($field, $this->getValidationMessage($field, $rule));
                    break; // Stop at first error per field
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Validate individual field
     * 
     * @param mixed $value
     * @param string $rule
     * @return bool
     */
    private function validateField(mixed $value, string $rule): bool
    {
        // Parse rule with parameters (e.g., 'min:5', 'max:100')
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;
        
        return match ($ruleName) {
            'required' => !empty($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'min' => strlen((string)$value) >= (int)$ruleParam,
            'max' => strlen((string)$value) <= (int)$ruleParam,
            'regex' => preg_match($ruleParam, (string)$value) === 1,
            'numeric' => is_numeric($value),
            'alpha' => ctype_alpha((string)$value),
            'alnum' => ctype_alnum((string)$value),
            'date' => $this->validateDate($value),
            'unique' => $this->validateUnique($value, $ruleParam),
            default => true
        };
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password
     * @return ValidationResult
     */
    public function validatePassword(string $password): ValidationResult
    {
        $result = new ValidationResult();
        
        if (strlen($password) < 8) {
            $result->addError('password', 'Password must be at least 8 characters long');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $result->addError('password', 'Password must contain at least one uppercase letter');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $result->addError('password', 'Password must contain at least one lowercase letter');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $result->addError('password', 'Password must contain at least one number');
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $result->addError('password', 'Password must contain at least one special character');
        }
        
        return $result;
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string
     */
    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            $_SESSION[self::CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[self::CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token
     * @return bool
     */
    public function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            return false;
        }
        
        return hash_equals($_SESSION[self::CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Get CSRF token HTML field
     * 
     * @return string
     */
    public function getCSRFField(): string
    {
        $token = $this->generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF for current request
     * 
     * @throws SecurityException
     */
    public function validateCSRFForRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!$this->validateCSRFToken($token)) {
                throw new SecurityException('CSRF token validation failed');
            }
        }
    }
    
    /**
     * Check rate limit with action type
     * 
     * @param string $action
     * @param string $identifier
     * @param int $maxAttempts
     * @param int $timeWindow
     * @return bool
     */
    public function checkRateLimit(string $action, string $identifier, int $maxAttempts = 5, int $timeWindow = 300): bool
    {
        $file = sys_get_temp_dir() . '/' . self::RATE_LIMIT_PREFIX . md5($action . '_' . $identifier) . '.json';
        $now = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? ['attempts' => []];
            
            // Clean old attempts
            $data['attempts'] = array_filter($data['attempts'], 
                fn($timestamp) => ($now - $timestamp) < $timeWindow
            );
        } else {
            $data = ['attempts' => []];
        }
        
        if (count($data['attempts']) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        $data['attempts'][] = $now;
        file_put_contents($file, json_encode($data));
        
        return true;
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    public function getClientIP(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Generate secure random string
     * 
     * @param int $length
     * @param string $type
     * @return string
     */
    public function generateRandomString(int $length = 10, string $type = 'alphanumeric'): string
    {
        return match ($type) {
            'hex' => bin2hex(random_bytes($length / 2)),
            'base64' => base64_encode(random_bytes($length)),
            default => $this->generateAlphanumeric($length, $type)
        };
    }
    
    /**
     * Set security headers
     * 
     * @return void
     */
    public function setSecurityHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self';";
               
        header("Content-Security-Policy: $csp");
        
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Validate date
     * 
     * @param mixed $date
     * @param string $format
     * @return bool
     */
    private function validateDate(mixed $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate unique constraint (placeholder - would need database access)
     * 
     * @param mixed $value
     * @param string $rule
     * @return bool
     */
    private function validateUnique(mixed $value, string $rule): bool
    {
        // This would typically check the database
        // For now, return true as a placeholder
        return true;
    }
    
    /**
     * Get validation error message
     * 
     * @param string $field
     * @param string $rule
     * @return string
     */
    private function getValidationMessage(string $field, string $rule): string
    {
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;
        
        return match ($ruleName) {
            'required' => "The {$field} field is required",
            'email' => "The {$field} must be a valid email address",
            'int' => "The {$field} must be an integer",
            'float' => "The {$field} must be a number",
            'url' => "The {$field} must be a valid URL",
            'min' => "The {$field} must be at least {$ruleParam} characters",
            'max' => "The {$field} must not exceed {$ruleParam} characters",
            'numeric' => "The {$field} must be numeric",
            'alpha' => "The {$field} must contain only letters",
            'alnum' => "The {$field} must contain only letters and numbers",
            'date' => "The {$field} must be a valid date",
            default => "The {$field} is invalid"
        };
    }
    
    /**
     * Generate alphanumeric string
     * 
     * @param int $length
     * @param string $type
     * @return string
     */
    private function generateAlphanumeric(int $length, string $type): string
    {
        $characters = match ($type) {
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numeric' => '0123456789',
            default => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
        };
        
        $randomString = '';
        $charactersLength = strlen($characters);
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Sanitize string input (compatibility method for AuthenticationService)
     * 
     * @param string $input
     * @return string
     */
    public function sanitizeString(string $input): string
    {
        return $this->sanitizeInput($input, 'string');
    }
    
    /**
     * Check if password meets strength requirements (compatibility method)
     * 
     * @param string $password
     * @return bool
     */
    public function isStrongPassword(string $password): bool
    {
        $result = $this->validatePassword($password);
        return $result->isValid();
    }
}
