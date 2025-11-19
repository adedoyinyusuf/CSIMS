<?php

namespace CSIMS\Config;

use CSIMS\Exceptions\ConfigurationException;
use InvalidArgumentException;

/**
 * Configuration Management Class
 * 
 * Handles environment-based configuration with validation,
 * default values, and secure credential management
 */
class Config
{
    private static ?Config $instance = null;
    private array $config = [];
    private array $environments = ['development', 'testing', 'staging', 'production'];
    private string $currentEnvironment;
    private bool $loaded = false;
    
    private function __construct()
    {
        $this->currentEnvironment = $this->detectEnvironment();
        $this->loadConfiguration();
    }
    
    /**
     * Get singleton instance
     * 
     * @return Config
     */
    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key, $default);
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }
    
    /**
     * Check if configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->getNestedValue($this->config, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }
    
    /**
     * Get current environment
     * 
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->currentEnvironment;
    }
    
    /**
     * Check if running in specific environment
     * 
     * @param string $environment
     * @return bool
     */
    public function isEnvironment(string $environment): bool
    {
        return $this->currentEnvironment === $environment;
    }
    
    /**
     * Check if running in production
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->isEnvironment('production');
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->get('app.debug', false) && !$this->isProduction();
    }
    
    /**
     * Get database configuration
     * 
     * @param string|null $connection Connection name (default uses default connection)
     * @return array
     * @throws ConfigurationException
     */
    public function getDatabase(?string $connection = null): array
    {
        $connection = $connection ?? $this->get('database.default', 'mysql');
        $config = $this->get("database.connections.{$connection}");
        
        if (!$config) {
            throw new ConfigurationException("Database connection '{$connection}' not configured");
        }
        
        return $config;
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
     * Load environment file
     * 
     * @param string $envFile Path to .env file
     * @return void
     * @throws ConfigurationException
     */
    public function loadEnv(string $envFile): void
    {
        if (!file_exists($envFile)) {
            throw new ConfigurationException("Environment file not found: {$envFile}");
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                if (!empty($key)) {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }
    
    /**
     * Validate required configuration keys
     * 
     * @param array $requiredKeys Array of required configuration keys
     * @return void
     * @throws ConfigurationException
     */
    public function validateRequired(array $requiredKeys): void
    {
        $missing = [];
        
        foreach ($requiredKeys as $key) {
            if (!$this->has($key)) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new ConfigurationException(
                'Missing required configuration keys: ' . implode(', ', $missing)
            );
        }
    }
    
    /**
     * Get configuration as JSON
     * 
     * @param bool $pretty Pretty print JSON
     * @return string
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($this->config, $flags);
    }
    
    /**
     * Reload configuration
     * 
     * @return void
     */
    public function reload(): void
    {
        $this->config = [];
        $this->loaded = false;
        $this->loadConfiguration();
    }
    
    /**
     * Detect current environment
     * 
     * @return string
     */
    private function detectEnvironment(): string
    {
        // Check environment variable
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');
        
        if ($env && in_array($env, $this->environments)) {
            return $env;
        }
        
        // Auto-detect based on domain/conditions
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            
            if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
                return 'development';
            }
            
            if (strpos($host, '.test') !== false || strpos($host, '.local') !== false) {
                return 'development';
            }
            
            if (strpos($host, 'staging') !== false) {
                return 'staging';
            }
        }
        
        // Default to production for safety
        return 'production';
    }
    
    /**
     * Load configuration from various sources
     * 
     * @return void
     */
    private function loadConfiguration(): void
    {
        if ($this->loaded) {
            return;
        }
        
        // Load .env file if exists
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            try {
                $this->loadEnv($envFile);
            } catch (ConfigurationException $e) {
                // Log error but continue with defaults
                error_log("Failed to load .env file: " . $e->getMessage());
            }
        }
        
        // Load environment-specific .env file
        $envSpecificFile = __DIR__ . "/../../.env.{$this->currentEnvironment}";
        if (file_exists($envSpecificFile)) {
            try {
                $this->loadEnv($envSpecificFile);
            } catch (ConfigurationException $e) {
                error_log("Failed to load environment-specific .env file: " . $e->getMessage());
            }
        }
        
        // Set default configuration
        $this->setDefaults();
        
        // Load configuration from PHP files
        $this->loadConfigFiles();
        
        $this->loaded = true;
    }
    
    /**
     * Set default configuration values
     * 
     * @return void
     */
    private function setDefaults(): void
    {
        $this->config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'CSIMS',
                'version' => '1.0.0',
                'environment' => $this->currentEnvironment,
                'debug' => $this->parseBoolean($_ENV['APP_DEBUG'] ?? 'false'),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
                'locale' => $_ENV['APP_LOCALE'] ?? 'en',
                'key' => $_ENV['APP_KEY'] ?? $this->generateKey(),
            ],
            
            'database' => [
                'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => $_ENV['DB_HOST'] ?? 'localhost',
                        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                        // Align default DB name with legacy config/database.php to avoid environment mismatches
                        'database' => $_ENV['DB_DATABASE'] ?? 'csims_db',
                        'username' => $_ENV['DB_USERNAME'] ?? 'root',
                        'password' => $_ENV['DB_PASSWORD'] ?? '',
                        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
                        'options' => [
                            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        ]
                    ]
                ]
            ],
            
            'session' => [
                'driver' => $_ENV['SESSION_DRIVER'] ?? 'database',
                'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120), // minutes
                'timeout' => (int)($_ENV['SESSION_TIMEOUT'] ?? 3600), // seconds
                'encrypt' => $this->parseBoolean($_ENV['SESSION_ENCRYPT'] ?? 'false'),
                'cookie' => [
                    'name' => $_ENV['SESSION_COOKIE'] ?? 'csims_session',
                    'path' => '/',
                    'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
                    'secure' => $this->parseBoolean($_ENV['SESSION_SECURE_COOKIE'] ?? 'false'),
                    'http_only' => true,
                    'same_site' => $_ENV['SESSION_SAME_SITE'] ?? 'Lax',
                ]
            ],
            
            'security' => [
                'csrf' => [
                    'enabled' => true,
                    'token_lifetime' => 3600, // 1 hour
                ],
                'rate_limiting' => [
                    'enabled' => true,
                    'login_attempts' => (int)($_ENV['RATE_LIMIT_LOGIN'] ?? 5),
                    'login_window' => (int)($_ENV['RATE_LIMIT_LOGIN_WINDOW'] ?? 300), // 5 minutes
                    'api_requests' => (int)($_ENV['RATE_LIMIT_API'] ?? 100),
                    'api_window' => (int)($_ENV['RATE_LIMIT_API_WINDOW'] ?? 60), // 1 minute
                ],
                'password' => [
                    'min_length' => (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
                    'require_uppercase' => $this->parseBoolean($_ENV['PASSWORD_REQUIRE_UPPERCASE'] ?? 'true'),
                    'require_lowercase' => $this->parseBoolean($_ENV['PASSWORD_REQUIRE_LOWERCASE'] ?? 'true'),
                    'require_numbers' => $this->parseBoolean($_ENV['PASSWORD_REQUIRE_NUMBERS'] ?? 'true'),
                    'require_symbols' => $this->parseBoolean($_ENV['PASSWORD_REQUIRE_SYMBOLS'] ?? 'true'),
                ],
                'lockout' => [
                    'max_attempts' => (int)($_ENV['LOCKOUT_MAX_ATTEMPTS'] ?? 5),
                    'duration' => (int)($_ENV['LOCKOUT_DURATION'] ?? 1800), // 30 minutes
                ]
            ],
            
            'cache' => [
                'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => $_ENV['CACHE_PATH'] ?? __DIR__ . '/../../storage/cache',
                    ],
                    'database' => [
                        'driver' => 'database',
                        'table' => 'cache_entries',
                    ]
                ],
                'prefix' => $_ENV['CACHE_PREFIX'] ?? 'csims',
                'default_ttl' => (int)($_ENV['CACHE_DEFAULT_TTL'] ?? 3600), // 1 hour
            ],
            
            'logging' => [
                'default' => $_ENV['LOG_CHANNEL'] ?? 'file',
                'channels' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => $_ENV['LOG_PATH'] ?? __DIR__ . '/../../storage/logs/csims.log',
                        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                        'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? 30),
                    ],
                    'database' => [
                        'driver' => 'database',
                        'table' => 'logs',
                        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                    ]
                ]
            ],
            
            'mail' => [
                'default' => $_ENV['MAIL_MAILER'] ?? 'smtp',
                'mailers' => [
                    'smtp' => [
                        'transport' => 'smtp',
                        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
                        'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
                        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                        'username' => $_ENV['MAIL_USERNAME'] ?? null,
                        'password' => $_ENV['MAIL_PASSWORD'] ?? null,
                        'timeout' => 60,
                    ]
                ],
                'from' => [
                    'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@csims.local',
                    'name' => $_ENV['MAIL_FROM_NAME'] ?? 'CSIMS',
                ]
            ],
        ];
    }
    
    /**
     * Load configuration from PHP files
     * 
     * @return void
     */
    private function loadConfigFiles(): void
    {
        $configDir = __DIR__ . '/../../config';
        
        if (!is_dir($configDir)) {
            return;
        }
        
        $files = glob($configDir . '/*.php');
        
        // Skip guard/side-effect files that should never be executed during config loading
        $blacklist = [
            'index.php',
            'config.php',
            'database.php',
            'auth_check.php',
            'member_auth_check.php',
            'init_db.php',
            'production_config.php'
        ];
        
        foreach ($files as $file) {
            $basename = basename($file);
            if (in_array($basename, $blacklist, true)) {
                continue;
            }
            
            // Only merge files that return an array configuration
            $config = include $file;
            if (is_array($config)) {
                $key = basename($file, '.php');
                $this->config[$key] = array_merge($this->config[$key] ?? [], $config);
            }
        }
    }
    
    /**
     * Get nested configuration value using dot notation
     * 
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        if (strpos($key, '.') === false) {
            return $default;
        }
        
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $segment) {
            if (!is_array($current) || !isset($current[$segment])) {
                return $default;
            }
            $current = $current[$segment];
        }
        
        return $current;
    }
    
    /**
     * Set nested configuration value using dot notation
     * 
     * @param array &$array
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }
        
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        
        $current = $value;
    }
    
    /**
     * Parse boolean value from string
     * 
     * @param mixed $value
     * @return bool
     */
    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        
        return (bool)$value;
    }
    
    /**
     * Generate application key
     * 
     * @return string
     */
    private function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
