<?php

namespace CSIMS\Container;

use ReflectionClass;
use ReflectionParameter;
use InvalidArgumentException;
use CSIMS\Exceptions\ContainerException;

/**
 * Simple Dependency Injection Container
 * 
 * Manages class dependencies and provides automatic resolution
 */
class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    
    private function __construct() {}
    
    /**
     * Get container instance (singleton)
     * 
     * @return Container
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Bind an interface to a concrete implementation
     * 
     * @param string $abstract
     * @param callable|string $concrete
     * @param bool $singleton
     * @return void
     */
    public function bind(string $abstract, callable|string $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
        
        if ($singleton) {
            $this->singletons[] = $abstract;
        }
    }
    
    /**
     * Bind a singleton
     * 
     * @param string $abstract
     * @param callable|string $concrete
     * @return void
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }
    
    /**
     * Bind an existing instance
     * 
     * @param string $abstract
     * @param object $instance
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
    
    /**
     * Resolve a class or interface
     * 
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     */
    public function resolve(string $abstract, array $parameters = []): mixed
    {
        // Check for existing instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // Check for binding
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];
            
            // If it's a singleton and already resolved, return cached instance
            if ($binding['singleton'] && isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }
            
            // Resolve the concrete implementation
            if (is_callable($concrete)) {
                $instance = $concrete($this, $parameters);
            } else {
                $instance = $this->build($concrete, $parameters);
            }
            
            // Cache singleton instances
            if ($binding['singleton']) {
                $this->instances[$abstract] = $instance;
            }
            
            return $instance;
        }
        
        // Try to build the class directly
        return $this->build($abstract, $parameters);
    }
    
    /**
     * Build a concrete class
     * 
     * @param string $concrete
     * @param array $parameters
     * @return object
     * @throws ContainerException
     */
    private function build(string $concrete, array $parameters = []): object
    {
        try {
            $reflection = new ReflectionClass($concrete);
            
            if (!$reflection->isInstantiable()) {
                throw new ContainerException("Class {$concrete} is not instantiable");
            }
            
            $constructor = $reflection->getConstructor();
            
            if ($constructor === null) {
                return new $concrete();
            }
            
            $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);
            
            return $reflection->newInstanceArgs($dependencies);
            
        } catch (\ReflectionException $e) {
            throw new ContainerException("Unable to resolve {$concrete}: " . $e->getMessage());
        }
    }
    
    /**
     * Resolve constructor dependencies
     * 
     * @param ReflectionParameter[] $parameters
     * @param array $primitives
     * @return array
     * @throws ContainerException
     */
    private function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            
            // Check if primitive value provided
            if (isset($primitives[$name])) {
                $dependencies[] = $primitives[$name];
                continue;
            }
            
            // Get the parameter type
            $type = $parameter->getType();
            
            if ($type === null) {
                // No type hint, check for default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException("Cannot resolve parameter {$name}");
                }
                continue;
            }
            
            // Handle union types (PHP 8.0+)
            if ($type instanceof \ReflectionUnionType) {
                throw new ContainerException("Union types not supported for parameter {$name}");
            }
            
            $typeName = $type->getName();
            
            // Handle built-in types
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException("Cannot resolve built-in type {$typeName} for parameter {$name}");
                }
                continue;
            }
            
            // Resolve class dependency
            try {
                $dependencies[] = $this->resolve($typeName);
            } catch (ContainerException $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw $e;
                }
            }
        }
        
        return $dependencies;
    }
    
    /**
     * Check if abstract is bound
     * 
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
    
    /**
     * Clear all bindings and instances
     * 
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->singletons = [];
    }
}
