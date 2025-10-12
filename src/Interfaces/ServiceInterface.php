<?php

namespace CSIMS\Interfaces;

/**
 * Base Service Interface
 * 
 * All services must implement this interface for consistent service contracts
 */
interface ServiceInterface
{
    /**
     * Get service status
     * 
     * @return array Status information including service name and current state
     */
    public function getStatus(): array;
    
    /**
     * Get service configuration
     * 
     * @return array Current service configuration
     */
    public function getConfiguration(): array;
}