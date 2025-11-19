<?php
// CSIMS Cache and Session Flush Script
// Usage: php scripts/flush_cache.php --force
// Clears file cache entries and deletes PHP session files to ensure a fresh start.

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$force = in_array('--force', $argv, true) || getenv('FLUSH_ALL') === '1';
if (!$force) {
    echo "Safety check: pass --force or set FLUSH_ALL=1 to proceed.\n";
    echo "Example: php scripts/flush_cache.php --force\n";
    exit(1);
}

// Bootstrap minimal dependencies for Config and Cache
require_once __DIR__ . '/../src/Config/Config.php';
require_once __DIR__ . '/../src/Cache/CacheInterface.php';
require_once __DIR__ . '/../src/Cache/FileCache.php';

use CSIMS\Config\Config;
use CSIMS\Cache\FileCache;

echo "\n=== CSIMS Cache & Session Flush ===\n";

// Initialize config and file cache
$config = Config::getInstance();
$cache = new FileCache($config);

// Determine cache directory and prefix
$cachePath = $config->get('cache.stores.file.path', __DIR__ . '/../storage/cache');
$prefix = $config->get('cache.prefix', 'csims');

// Count existing cache files
$existingCacheFiles = glob(rtrim($cachePath, '/\\') . DIRECTORY_SEPARATOR . $prefix . '_*');
$existingCount = is_array($existingCacheFiles) ? count($existingCacheFiles) : 0;
echo "Cache directory: " . $cachePath . "\n";
echo "Cache prefix   : " . $prefix . "\n";
echo "Found " . $existingCount . " cache file(s).\n";

// Flush cache
$cacheResult = $cache->flush();
if ($cacheResult) {
    echo "✓ Cache flushed successfully.\n";
} else {
    echo "⚠ Cache flush reported partial failures (some files may remain).\n";
}

// Attempt to clear PHP session files
$sessionPath = ini_get('session.save_path');
if (!$sessionPath) {
    // On some environments, session.save_path may be empty, try common XAMPP/WAMP fallback
    $sessionPath = getenv('SESSION_SAVE_PATH') ?: (DIRECTORY_SEPARATOR === '\\' ? 'C:\\xampp\\tmp' : '/tmp');
}

$sessionPath = rtrim($sessionPath, '/\\');
echo "Session save path: " . $sessionPath . "\n";

$sessionCount = 0;
$sessionErrors = 0;
if (is_dir($sessionPath) && is_readable($sessionPath)) {
    $sessFiles = glob($sessionPath . DIRECTORY_SEPARATOR . 'sess_*');
    if (is_array($sessFiles)) {
        foreach ($sessFiles as $sf) {
            // Best-effort delete; ignore failures (permissions/locks)
            if (@unlink($sf)) {
                $sessionCount++;
            } else {
                $sessionErrors++;
            }
        }
    }
    echo "Deleted " . $sessionCount . " session file(s)." . ($sessionErrors ? " (" . $sessionErrors . " failed)" : "") . "\n";
} else {
    echo "⚠ Session path not accessible; skipping session file deletion.\n";
}

echo "\n=== Flush Complete. Cache and sessions cleared. ===\n";

?>