<?php

namespace CSIMS\Services;

/**
 * Configuration Manager
 * 
 * Manages environment-specific configuration loading and access
 */
class ConfigurationManager
{
    private static ?ConfigurationManager $instance = null;
    private array $config = [];
    private string $environment;
    
    private function __construct(string $environment = 'development')
    {
        $this->environment = $environment;
        $this->loadConfiguration();
    }
    
    /**
     * Get singleton instance
     * 
     * @param string $environment
     * @return ConfigurationManager
     */
    public static function getInstance(string $environment = 'development'): ConfigurationManager
    {
        if (self::$instance === null) {
            self::$instance = new self($environment);
        }
        
        return self::$instance;
    }
    
    /**
     * Load configuration files
     * 
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configDir = dirname(__DIR__, 2) . '/config';
        
        // Load base configuration
        $baseConfigFile = $configDir . '/base.php';
        if (file_exists($baseConfigFile)) {
            $baseConfig = require $baseConfigFile;
            $this->config = is_array($baseConfig) ? $baseConfig : [];
        }
        
        // Load environment-specific configuration
        $envConfigFile = $configDir . '/' . $this->environment . '.php';
        if (file_exists($envConfigFile)) {
            $envConfig = require $envConfigFile;
            if (is_array($envConfig)) {
                $this->config = array_replace_recursive($this->config, $envConfig);
            }
        }
        
        // Load legacy config for backward compatibility
        $legacyConfigFile = $configDir . '/config.php';
        if (file_exists($legacyConfigFile) && empty($this->config)) {
            require_once $legacyConfigFile;
            $this->loadLegacyConstants();
        }
    }
    
    /**
     * Load legacy constants into config array
     * 
     * @return void
     */
    private function loadLegacyConstants(): void
    {
        $constants = [
            'app' => [
                'name' => defined('APP_NAME') ? APP_NAME : 'CSIMS',
                'short_name' => defined('APP_SHORT_NAME') ? APP_SHORT_NAME : 'CSIMS',
                'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
                'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'development',
                'base_url' => defined('BASE_URL') ? BASE_URL : '',
            ],
            'database' => [
                'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
                'username' => defined('DB_USER') ? DB_USER : 'root',
                'password' => defined('DB_PASS') ? DB_PASS : '',
                'database' => defined('DB_NAME') ? DB_NAME : 'csims_db',
            ],
            'session' => [
                'timeout' => defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800,
                'regenerate_interval' => defined('SESSION_REGENERATE_INTERVAL') ? SESSION_REGENERATE_INTERVAL : 1800,
            ],
            'security' => [
                'force_https' => defined('FORCE_HTTPS') ? FORCE_HTTPS : false,
                'secure_cookies' => defined('SECURE_COOKIES') ? SECURE_COOKIES : false,
                'max_login_attempts' => defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5,
                'login_lockout_time' => defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900,
                'password_min_length' => defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8,
            ],
            'mail' => [
                'from' => defined('MAIL_FROM') ? MAIL_FROM : 'noreply@csims.com',
                'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'CSIMS',
            ],
            'pagination' => [
                'items_per_page' => defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10,
            ],
            'uploads' => [
                'max_size' => defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 2097152,
                'allowed_types' => defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/png', 'image/gif'],
            ]
        ];
        
        $this->config = array_replace_recursive($this->config, $constants);
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Dot notation key (e.g., 'database.host')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Check if configuration key exists
     * 
     * @param string $key Dot notation key
     * @return bool
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
    
    /**
     * Get all configuration
     * 
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * Get current environment
     * 
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }
    
    /**
     * Check if environment is production
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
    
    /**
     * Check if environment is development
     * 
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }
    
    /**
     * Check if environment is testing
     * 
     * @return bool
     */
    public function isTesting(): bool
    {
        return $this->environment === 'testing';
    }
    
    /**
     * Get database configuration
     * 
     * @return array
     */
    public function getDatabaseConfig(): array
    {
        return $this->get('database', [
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'database' => 'csims_db'
        ]);
    }
    
    /**
     * Get mail configuration
     * 
     * @return array
     */
    public function getMailConfig(): array
    {
        return $this->get('mail', [
            'from' => 'noreply@csims.com',
            'from_name' => 'CSIMS'
        ]);
    }
    
    /**
     * Get security configuration
     * 
     * @return array
     */
    public function getSecurityConfig(): array
    {
        return $this->get('security', [
            'force_https' => false,
            'secure_cookies' => false,
            'max_login_attempts' => 5,
            'login_lockout_time' => 900,
            'password_min_length' => 8,
            'two_factor_enabled' => false,
            'test_2fa_code' => '000000'
        ]);
    }
}
