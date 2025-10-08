<?php

namespace CSIMS\Cache;

use CSIMS\Config\Config;

/**
 * File Cache Implementation
 * 
 * Simple file-based cache with TTL support and tagging
 */
class FileCache implements CacheInterface
{
    private string $cachePath;
    private string $prefix;
    private int $defaultTtl;
    
    public function __construct(?Config $config = null)
    {
        $config = $config ?? Config::getInstance();
        $this->cachePath = $config->get('cache.stores.file.path', __DIR__ . '/../../storage/cache');
        $this->prefix = $config->get('cache.prefix', 'csims');
        $this->defaultTtl = $config->get('cache.default_ttl', 3600);
        
        $this->ensureCacheDirectory();
    }
    
    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = $this->readCacheFile($file);
        
        if ($data === null) {
            return $default;
        }
        
        // Check if expired
        if ($data['expires'] > 0 && time() > $data['expires']) {
            $this->forget($key);
            return $default;
        }
        
        return $data['value'];
    }
    
    /**
     * {@inheritdoc}
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->putWithTags($key, $value, [], $ttl);
    }
    
    /**
     * {@inheritdoc}
     */
    public function putWithTags(string $key, mixed $value, array $tags = [], ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        $data = [
            'value' => $value,
            'expires' => $expires,
            'tags' => $tags,
            'created' => time()
        ];
        
        $file = $this->getFilePath($key);
        $success = $this->writeCacheFile($file, $data);
        
        // Update tag index
        if ($success && !empty($tags)) {
            $this->updateTagIndex($key, $tags);
        }
        
        return $success;
    }
    
    /**
     * {@inheritdoc}
     */
    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            // Remove from tag index
            $this->removeFromTagIndex($key);
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function forgetByTag(string $tag): bool
    {
        $keys = $this->getKeysForTag($tag);
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->forget($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__CACHE_MISS__') !== '__CACHE_MISS__';
    }
    
    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        $files = glob($this->cachePath . '/' . $this->prefix . '_*');
        $success = true;
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        // Clear tag index
        $tagFile = $this->getTagIndexFile();
        if (file_exists($tagFile)) {
            unlink($tagFile);
        }
        
        return $success;
    }
    
    /**
     * {@inheritdoc}
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }
    
    /**
     * {@inheritdoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function many(array $keys): array
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        
        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function putMany(array $values, ?int $ttl = null): bool
    {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->put($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Remember a value in cache or execute callback
     * 
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key, '__CACHE_MISS__');
        
        if ($value !== '__CACHE_MISS__') {
            return $value;
        }
        
        $value = $callback();
        $this->put($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Clean up expired cache files
     * 
     * @return int Number of files cleaned
     */
    public function cleanup(): int
    {
        $files = glob($this->cachePath . '/' . $this->prefix . '_*');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (strpos(basename($file), 'tags_') !== false) {
                continue; // Skip tag index files
            }
            
            $data = $this->readCacheFile($file);
            
            if ($data === null || ($data['expires'] > 0 && time() > $data['expires'])) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        // Clean tag index
        $this->cleanTagIndex();
        
        return $cleaned;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $files = glob($this->cachePath . '/' . $this->prefix . '_*');
        $totalSize = 0;
        $validFiles = 0;
        $expiredFiles = 0;
        
        foreach ($files as $file) {
            if (strpos(basename($file), 'tags_') !== false) {
                continue;
            }
            
            $totalSize += filesize($file);
            $data = $this->readCacheFile($file);
            
            if ($data === null || ($data['expires'] > 0 && time() > $data['expires'])) {
                $expiredFiles++;
            } else {
                $validFiles++;
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_files' => $validFiles,
            'expired_files' => $expiredFiles,
            'total_size' => $totalSize,
            'cache_path' => $this->cachePath
        ];
    }
    
    /**
     * Get file path for cache key
     * 
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        $hashedKey = md5($this->prefix . '_' . $key);
        return $this->cachePath . '/' . $this->prefix . '_' . $hashedKey . '.cache';
    }
    
    /**
     * Read cache file
     * 
     * @param string $file
     * @return array|null
     */
    private function readCacheFile(string $file): ?array
    {
        try {
            $content = file_get_contents($file);
            if ($content === false) {
                return null;
            }
            
            $data = unserialize($content);
            if ($data === false) {
                return null;
            }
            
            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Write cache file
     * 
     * @param string $file
     * @param array $data
     * @return bool
     */
    private function writeCacheFile(string $file, array $data): bool
    {
        try {
            $content = serialize($data);
            return file_put_contents($file, $content, LOCK_EX) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get tag index file path
     * 
     * @return string
     */
    private function getTagIndexFile(): string
    {
        return $this->cachePath . '/' . $this->prefix . '_tags_index.cache';
    }
    
    /**
     * Update tag index with key-tag relationships
     * 
     * @param string $key
     * @param array $tags
     * @return void
     */
    private function updateTagIndex(string $key, array $tags): void
    {
        $indexFile = $this->getTagIndexFile();
        $index = [];
        
        if (file_exists($indexFile)) {
            $index = $this->readCacheFile($indexFile) ?? [];
        }
        
        foreach ($tags as $tag) {
            if (!isset($index[$tag])) {
                $index[$tag] = [];
            }
            
            if (!in_array($key, $index[$tag])) {
                $index[$tag][] = $key;
            }
        }
        
        $this->writeCacheFile($indexFile, $index);
    }
    
    /**
     * Remove key from tag index
     * 
     * @param string $key
     * @return void
     */
    private function removeFromTagIndex(string $key): void
    {
        $indexFile = $this->getTagIndexFile();
        
        if (!file_exists($indexFile)) {
            return;
        }
        
        $index = $this->readCacheFile($indexFile) ?? [];
        
        foreach ($index as $tag => $keys) {
            $index[$tag] = array_filter($keys, function($k) use ($key) {
                return $k !== $key;
            });
            
            if (empty($index[$tag])) {
                unset($index[$tag]);
            }
        }
        
        $this->writeCacheFile($indexFile, $index);
    }
    
    /**
     * Get keys associated with a tag
     * 
     * @param string $tag
     * @return array
     */
    private function getKeysForTag(string $tag): array
    {
        $indexFile = $this->getTagIndexFile();
        
        if (!file_exists($indexFile)) {
            return [];
        }
        
        $index = $this->readCacheFile($indexFile) ?? [];
        
        return $index[$tag] ?? [];
    }
    
    /**
     * Clean tag index by removing non-existent keys
     * 
     * @return void
     */
    private function cleanTagIndex(): void
    {
        $indexFile = $this->getTagIndexFile();
        
        if (!file_exists($indexFile)) {
            return;
        }
        
        $index = $this->readCacheFile($indexFile) ?? [];
        $cleaned = false;
        
        foreach ($index as $tag => $keys) {
            $validKeys = [];
            
            foreach ($keys as $key) {
                if ($this->has($key)) {
                    $validKeys[] = $key;
                } else {
                    $cleaned = true;
                }
            }
            
            if (empty($validKeys)) {
                unset($index[$tag]);
                $cleaned = true;
            } else {
                $index[$tag] = $validKeys;
            }
        }
        
        if ($cleaned) {
            $this->writeCacheFile($indexFile, $index);
        }
    }
    
    /**
     * Ensure cache directory exists
     * 
     * @return void
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
}
