<?php
/**
 * Create API Tokens Table
 * Run: php scripts/create_api_tokens_table.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   Creating API Tokens Table                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if (!$conn) {
    die("❌ Database connection failed\n");
}

echo "✅ Connected to database: " . DB_NAME . "\n\n";

// Create api_tokens table
$sql = "CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `name` VARCHAR(255) NOT NULL COMMENT 'Human-readable name for the token',
    `is_active` TINYINT(1) DEFAULT 1,
    `expires_at` DATETIME NOT NULL,
    `last_used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `revoked_by` INT(11) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token` (`token`),
    KEY `user_id` (`user_id`),
    KEY `is_active` (`is_active`, `expires_at`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API authentication tokens'";

if ($conn->query($sql)) {
    echo "✅ api_tokens table created successfully\n\n";
} else {
    if (strpos($conn->error, 'already exists') !== false) {
        echo "ℹ️  api_tokens table already exists\n\n";
    } else {
        echo "❌ Error creating table: " . $conn->error . "\n\n";
        exit(1);
    }
}

echo "✅ API Tokens table setup complete!\n";
echo "You can now use API token authentication.\n\n";

$conn->close();
?>
