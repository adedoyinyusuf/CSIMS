<?php

namespace CSIMS\Exceptions;

use Exception;

/**
 * Base CSIMS Exception
 * 
 * All CSIMS exceptions should extend this class
 */
abstract class CSIMSException extends Exception
{
    protected array $context = [];
    
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    /**
     * Get exception context
     * 
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
    
    /**
     * Set exception context
     * 
     * @param array $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
}
