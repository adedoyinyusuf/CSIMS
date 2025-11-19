<?php

/**
 * CSIMS System Configuration Service
 * 
 * Centralized configuration management for business rules and system settings.
 * Provides type-safe configuration access with caching and validation.
 * 
 * @package CSIMS\Config
 * @version 1.0.0
 */

class SystemConfigService 
{
    private static $instance = null;
    private $pdo;
    private $cache = [];
    private $cacheExpiry = 300; // 5 minutes
    private $lastCacheTime = 0;

    private function __construct($pdo) 
    {
        $this->pdo = $pdo;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance($pdo = null): SystemConfigService 
    {
        if (self::$instance === null) {
            if ($pdo === null) {
                throw new InvalidArgumentException('Database connection (PDO or mysqli) required for initialization');
            }
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    /**
     * Get configuration value with type conversion and validation
     */
    public function get(string $key, $default = null) 
    {
        try {
            $config = $this->getConfig($key);
            if ($config === null) {
                return $default;
            }

            return $this->convertValue($config['config_value'], $config['config_type']);
        } catch (Exception $e) {
            error_log("SystemConfigService::get() error for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Get multiple configuration values by category
     */
    public function getByCategory(string $category): array 
    {
        $this->refreshCacheIfNeeded();
        
        $configs = [];
        foreach ($this->cache as $key => $config) {
            if ($config['category'] === $category) {
                $configs[$key] = $this->convertValue($config['config_value'], $config['config_type']);
            }
        }
        
        return $configs;
    }

    /**
     * Set configuration value with validation
     */
    public function set(string $key, $value, int $userId = null): bool 
    {
        try {
            $config = $this->getConfig($key);
            if ($config === null) {
                throw new InvalidArgumentException("Configuration key '{$key}' not found");
            }

            if (!$config['is_editable']) {
                throw new InvalidArgumentException("Configuration key '{$key}' is not editable");
            }

            // Validate value
            $validatedValue = $this->validateAndConvertValue($value, $config);
            
            // Update database
            $stmt = $this->pdo->prepare("
                UPDATE system_config 
                SET config_value = ?, updated_by = ?, updated_at = NOW()
                WHERE config_key = ?
            ");
            
            $result = $stmt->execute([$validatedValue, $userId, $key]);
            
            if ($result) {
                // Clear cache to force refresh
                $this->clearCache();
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("SystemConfigService::set() error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all configuration values as associative array
     */
    public function getAll(): array 
    {
        $this->refreshCacheIfNeeded();
        
        $configs = [];
        foreach ($this->cache as $key => $config) {
            $configs[$key] = $this->convertValue($config['config_value'], $config['config_type']);
        }
        
        return $configs;
    }

    /**
     * Check if configuration key exists
     */
    public function exists(string $key): bool 
    {
        return $this->getConfig($key) !== null;
    }

    /**
     * Get configuration metadata (type, description, etc.)
     */
    public function getMetadata(string $key): ?array 
    {
        $config = $this->getConfig($key);
        if ($config === null) {
            return null;
        }

        return [
            'key' => $config['config_key'],
            'type' => $config['config_type'],
            'description' => $config['description'],
            'category' => $config['category'],
            'is_editable' => (bool)$config['is_editable'],
            'requires_restart' => (bool)$config['requires_restart'],
            'min_value' => $config['min_value'],
            'max_value' => $config['max_value'],
            'validation_regex' => $config['validation_regex']
        ];
    }

    /**
     * Validate configuration value against rules
     */
    public function validate(string $key, $value): array 
    {
        $errors = [];
        
        try {
            $config = $this->getConfig($key);
            if ($config === null) {
                return ['Configuration key not found'];
            }

            $this->validateAndConvertValue($value, $config);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Reset configuration value to default
     */
    public function resetToDefault(string $key, int $userId = null): bool 
    {
        // This would require storing default values separately
        // For now, we'll throw an exception
        throw new InvalidArgumentException("Reset to default not implemented yet");
    }

    /**
     * Clear configuration cache
     */
    public function clearCache(): void 
    {
        $this->cache = [];
        $this->lastCacheTime = 0;
    }

    /**
     * Business rule specific convenience methods
     */

    // Savings Configuration
    public function getMinMandatorySavings(): float 
    {
        return (float)$this->get('MIN_MANDATORY_SAVINGS', 5000.00);
    }

    public function getMaxMandatorySavings(): float 
    {
        return (float)$this->get('MAX_MANDATORY_SAVINGS', 200000.00);
    }

    public function getSavingsInterestRate(): float 
    {
        return (float)$this->get('SAVINGS_INTEREST_RATE', 6.00);
    }

    public function getWithdrawalMaxPercentage(): float 
    {
        return (float)$this->get('WITHDRAWAL_MAX_PERCENTAGE', 80.00);
    }

    public function getWithdrawalProcessingDays(): int 
    {
        return (int)$this->get('WITHDRAWAL_PROCESSING_DAYS', 5);
    }

    // Loan Configuration
    public function getLoanToSavingsMultiplier(): float 
    {
        return (float)$this->get('LOAN_TO_SAVINGS_MULTIPLIER', 3.00);
    }

    public function getMinMembershipMonths(): int 
    {
        return (int)$this->get('MIN_MEMBERSHIP_MONTHS', 6);
    }

    public function getMaxActiveLoansPer(): int 
    {
        return (int)$this->get('MAX_ACTIVE_LOANS_PER_MEMBER', 3);
    }

    public function getLoanPenaltyRate(): float 
    {
        return (float)$this->get('LOAN_PENALTY_RATE', 2.00);
    }

    public function getDefaultGracePeriod(): int 
    {
        return (int)$this->get('DEFAULT_GRACE_PERIOD', 7);
    }

    public function getMaxLoanAmount(): float 
    {
        return (float)$this->get('MAX_LOAN_AMOUNT', 5000000.00);
    }

    public function getGuarantorRequirementThreshold(): float 
    {
        return (float)$this->get('GUARANTOR_REQUIREMENT_THRESHOLD', 500000.00);
    }

    public function getMinGuarantorsRequired(): int 
    {
        return (int)$this->get('MIN_GUARANTORS_REQUIRED', 2);
    }

    // Workflow Configuration
    public function getApprovalLevels(): int 
    {
        return (int)$this->get('ADMIN_APPROVAL_WORKFLOW', 3);
    }

    public function getAutoApprovalLimit(): float 
    {
        return (float)$this->get('AUTO_APPROVAL_LIMIT', 100000.00);
    }

    public function getApprovalTimeoutDays(): int 
    {
        return (int)$this->get('APPROVAL_TIMEOUT_DAYS', 7);
    }

    // System Operations
    public function getAutoDeductionDay(): int 
    {
        return (int)$this->get('AUTO_DEDUCTION_DAY', 28);
    }

    public function getInterestPostingDay(): int 
    {
        return (int)$this->get('INTEREST_POSTING_DAY', 1);
    }

    public function getPenaltyCalculationDay(): int 
    {
        return (int)$this->get('PENALTY_CALCULATION_DAY', 2);
    }

    public function getDefaultFlagAfterMissedPayments(): int 
    {
        return (int)$this->get('DEFAULT_FLAG_AFTER_MISSED_PAYMENTS', 3);
    }

    // ============ PRIVATE METHODS ============

    /**
     * Get raw configuration from cache or database
     */
    private function getConfig(string $key): ?array 
    {
        $this->refreshCacheIfNeeded();
        return $this->cache[$key] ?? null;
    }

    /**
     * Refresh cache if expired
     */
    private function refreshCacheIfNeeded(): void 
    {
        if (empty($this->cache) || (time() - $this->lastCacheTime) > $this->cacheExpiry) {
            $this->loadCache();
        }
    }

    /**
     * Load all configurations into cache
     */
    private function loadCache(): void 
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT config_key, config_value, config_type, description, category,
                       is_editable, requires_restart, validation_regex, min_value, max_value
                FROM system_config
            ");
            $stmt->execute();
            
            $this->cache = [];
            if (method_exists($stmt, 'get_result')) {
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $this->cache[$row['config_key']] = $row;
                    }
                }
            } else {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $this->cache[$row['config_key']] = $row;
                }
            }
            
            $this->lastCacheTime = time();
        } catch (Exception $e) {
            error_log("SystemConfigService::loadCache() error: " . $e->getMessage());
            // Keep existing cache on error
        }
    }

    /**
     * Convert string value to appropriate type
     */
    private function convertValue(string $value, string $type) 
    {
        switch ($type) {
            case 'integer':
                return (int)$value;
            case 'decimal':
                return (float)$value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Validate value and convert to string for storage
     */
    private function validateAndConvertValue($value, array $config): string 
    {
        $type = $config['config_type'];
        $regex = $config['validation_regex'];
        $minValue = $config['min_value'];
        $maxValue = $config['max_value'];

        // Type-specific validation
        switch ($type) {
            case 'integer':
                if (!is_numeric($value) || (int)$value != $value) {
                    throw new InvalidArgumentException("Value must be an integer");
                }
                $numValue = (int)$value;
                if ($minValue !== null && $numValue < $minValue) {
                    throw new InvalidArgumentException("Value must be at least {$minValue}");
                }
                if ($maxValue !== null && $numValue > $maxValue) {
                    throw new InvalidArgumentException("Value must not exceed {$maxValue}");
                }
                $value = (string)$numValue;
                break;

            case 'decimal':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Value must be a number");
                }
                $numValue = (float)$value;
                if ($minValue !== null && $numValue < $minValue) {
                    throw new InvalidArgumentException("Value must be at least {$minValue}");
                }
                if ($maxValue !== null && $numValue > $maxValue) {
                    throw new InvalidArgumentException("Value must not exceed {$maxValue}");
                }
                $value = number_format($numValue, 2, '.', '');
                break;

            case 'boolean':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                break;

            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new InvalidArgumentException("Value must be valid JSON");
                    }
                } else {
                    $value = json_encode($value);
                }
                break;

            case 'string':
                $value = (string)$value;
                break;
        }

        // Regex validation
        if ($regex && !preg_match('/' . $regex . '/', $value)) {
            throw new InvalidArgumentException("Value does not match required format");
        }

        return $value;
    }
}