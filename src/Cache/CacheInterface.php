<?php

namespace CSIMS\Cache;

/**
 * Cache Interface
 * 
 * Defines the contract for cache implementations
 */
interface CacheInterface
{
    /**
     * Get an item from the cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;
    
    /**
     * Store an item in the cache
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Time to live in seconds
     * @return bool
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool;
    
    /**
     * Store an item in the cache with tags
     * 
     * @param string $key
     * @param mixed $value
     * @param array $tags
     * @param int|null $ttl
     * @return bool
     */
    public function putWithTags(string $key, mixed $value, array $tags = [], ?int $ttl = null): bool;
    
    /**
     * Remove an item from the cache
     * 
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool;
    
    /**
     * Remove items from the cache by tag
     * 
     * @param string $tag
     * @return bool
     */
    public function forgetByTag(string $tag): bool;
    
    /**
     * Check if an item exists in the cache
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;
    
    /**
     * Clear all items from the cache
     * 
     * @return bool
     */
    public function flush(): bool;
    
    /**
     * Get and remove an item from the cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;
    
    /**
     * Store an item in the cache indefinitely
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool;
    
    /**
     * Get multiple items from the cache
     * 
     * @param array $keys
     * @return array
     */
    public function many(array $keys): array;
    
    /**
     * Store multiple items in the cache
     * 
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function putMany(array $values, ?int $ttl = null): bool;
}
