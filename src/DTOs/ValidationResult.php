<?php

namespace CSIMS\DTOs;

/**
 * Validation Result DTO
 * 
 * Represents the result of validation operations
 */
class ValidationResult
{
    private bool $isValid;
    private array $errors;
    private array $warnings;
    
    public function __construct(bool $isValid = true, array $errors = [], array $warnings = [])
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }
    
    /**
     * Check if validation passed
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get validation warnings
     * 
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    /**
     * Add an error
     * 
     * @param string $field
     * @param string $message
     * @return self
     */
    public function addError(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        $this->isValid = false;
        return $this;
    }
    
    /**
     * Add a warning
     * 
     * @param string $field
     * @param string $message
     * @return self
     */
    public function addWarning(string $field, string $message): self
    {
        $this->warnings[$field] = $message;
        return $this;
    }
    
    /**
     * Get first error message
     * 
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
    
    /**
     * Get all error messages as flat array
     * 
     * @return array
     */
    public function getAllErrors(): array
    {
        return array_values($this->errors);
    }
    
    /**
     * Check if has errors for specific field
     * 
     * @param string $field
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
    
    /**
     * Get error for specific field
     * 
     * @param string $field
     * @return string|null
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
