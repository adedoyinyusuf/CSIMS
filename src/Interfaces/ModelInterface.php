<?php

namespace CSIMS\Interfaces;

/**
 * Base Model Interface
 * 
 * All models must implement this interface to ensure consistent behavior
 */
interface ModelInterface
{
    /**
     * Convert model to array representation
     * 
     * @return array
     */
    public function toArray(): array;
    
    /**
     * Create model from array data
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static;
    
    /**
     * Get the primary key value
     * 
     * @return mixed
     */
    public function getId(): mixed;
    
    /**
     * Validate the model data
     * 
     * @return ValidationResult
     */
    public function validate(): ValidationResult;
}
