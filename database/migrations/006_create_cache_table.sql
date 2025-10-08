-- Cache Entries Table Migration
-- This table stores cache entries for database-based caching

CREATE TABLE IF NOT EXISTS cache_entries (
    `key` VARCHAR(255) PRIMARY KEY,
    value LONGTEXT NOT NULL,
    tags TEXT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache Tags Table (for tag-based cache invalidation)
CREATE TABLE IF NOT EXISTS cache_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag VARCHAR(100) NOT NULL,
    cache_key VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tag (tag),
    INDEX idx_cache_key (cache_key),
    UNIQUE KEY unique_tag_key (tag, cache_key),
    FOREIGN KEY (cache_key) REFERENCES cache_entries(`key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
