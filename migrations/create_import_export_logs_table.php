<?php
/**
 * Migration: Create import_export_logs table
 * 
 * This table stores logs of all import and export activities
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Creating import_export_logs table...\n";
    
    // Create import_export_logs table
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS import_export_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('immediate', 'scheduled', 'export') NOT NULL,
            action VARCHAR(50) NOT NULL,
            total_records INT DEFAULT 0,
            success_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            duplicate_count INT DEFAULT 0,
            filename VARCHAR(255) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            user_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($db->query($createTableSQL)) {
        echo "✓ import_export_logs table created successfully\n";
    } else {
        throw new Exception("Error creating import_export_logs table: " . $db->error);
    }
    
    // Create temp directories if they don't exist
    $directories = [
        __DIR__ . '/../temp',
        __DIR__ . '/../temp/imports',
        __DIR__ . '/../temp/imports/archive',
        __DIR__ . '/../temp/exports',
        __DIR__ . '/../temp/uploads',
        __DIR__ . '/../temp/cache'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "✓ Created directory: " . basename($dir) . "\n";
            } else {
                echo "⚠ Warning: Could not create directory: " . basename($dir) . "\n";
            }
        } else {
            echo "✓ Directory already exists: " . basename($dir) . "\n";
        }
    }
    
    // Create .htaccess file to protect temp directory
    $htaccessContent = "# Deny access to temp files\nDeny from all";
    $htaccessPath = __DIR__ . '/../temp/.htaccess';
    
    if (!file_exists($htaccessPath)) {
        if (file_put_contents($htaccessPath, $htaccessContent)) {
            echo "✓ Created .htaccess protection for temp directory\n";
        }
    }
    
    echo "\n=== Migration completed successfully ===\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>