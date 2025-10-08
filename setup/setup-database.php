<?php

/**
 * CSIMS Database Setup Script
 * 
 * Runs all database migrations and sets up the initial system
 */

// Include bootstrap
require_once __DIR__ . '/../src/bootstrap.php';

try {
    echo "CSIMS Database Setup\n";
    echo "===================\n\n";
    
    // Bootstrap the application
    $container = CSIMS\bootstrap();
    $config = $container->resolve(\CSIMS\Config\Config::class);
    $connection = $container->resolve(mysqli::class);
    
    echo "Connected to database: " . $config->get('database.connections.mysql.database') . "\n";
    echo "Host: " . $config->get('database.connections.mysql.host') . "\n\n";
    
    // Get migration files
    $migrationPath = __DIR__ . '/../database/migrations';
    $migrations = glob($migrationPath . '/*.sql');
    
    if (empty($migrations)) {
        echo "No migration files found in {$migrationPath}\n";
        exit(1);
    }
    
    sort($migrations);
    
    echo "Found " . count($migrations) . " migration files:\n";
    foreach ($migrations as $migration) {
        echo "  - " . basename($migration) . "\n";
    }
    echo "\n";
    
    // Run migrations
    $connection->autocommit(false);
    
    foreach ($migrations as $migration) {
        $filename = basename($migration);
        echo "Running migration: {$filename} ... ";
        
        try {
            $sql = file_get_contents($migration);
            
            if ($sql === false) {
                throw new Exception("Could not read migration file: {$filename}");
            }
            
            // Split SQL into individual statements
            $statements = array_filter(
                array_map('trim', preg_split('/;[\r\n]+/', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt);
                }
            );
            
            foreach ($statements as $statement) {
                if (!$connection->query($statement)) {
                    throw new Exception("SQL Error: " . $connection->error);
                }
            }
            
            echo "✓ SUCCESS\n";
            
        } catch (Exception $e) {
            echo "✗ FAILED: " . $e->getMessage() . "\n";
            $connection->rollback();
            exit(1);
        }
    }
    
    $connection->commit();
    echo "\n✓ All migrations completed successfully!\n\n";
    
    // Create default admin user
    echo "Setting up default admin user...\n";
    
    $userRepository = $container->resolve(\CSIMS\Repositories\UserRepository::class);
    $securityService = $container->resolve(\CSIMS\Services\SecurityService::class);
    
    // Check if admin user already exists
    $adminUser = $userRepository->findByUsername('admin');
    
    if (!$adminUser) {
        $defaultPassword = 'Admin123!';
        
        $adminUser = new \CSIMS\Models\User(
            null,
            'admin',
            'admin@csims.local',
            'System Administrator',
            'administrator',
            ['*'], // All permissions
            true, // active
            new DateTime()
        );
        
        $adminUser->setPassword($defaultPassword);
        
        $createdUser = $userRepository->create($adminUser);
        
        echo "✓ Default admin user created:\n";
        echo "  Username: admin\n";
        echo "  Password: {$defaultPassword}\n";
        echo "  Email: admin@csims.local\n";
        echo "  ⚠️  IMPORTANT: Change this password after first login!\n\n";
    } else {
        echo "ℹ️  Admin user already exists, skipping creation.\n\n";
    }
    
    // Setup cache directories
    echo "Setting up cache directories...\n";
    $cachePath = $config->get('cache.stores.file.path');
    if (!is_dir($cachePath)) {
        if (mkdir($cachePath, 0755, true)) {
            echo "✓ Cache directory created: {$cachePath}\n";
        } else {
            echo "⚠️  Could not create cache directory: {$cachePath}\n";
        }
    } else {
        echo "ℹ️  Cache directory already exists: {$cachePath}\n";
    }
    
    // Setup log directories
    echo "Setting up log directories...\n";
    $logPath = dirname($config->get('logging.channels.file.path'));
    if (!is_dir($logPath)) {
        if (mkdir($logPath, 0755, true)) {
            echo "✓ Log directory created: {$logPath}\n";
        } else {
            echo "⚠️  Could not create log directory: {$logPath}\n";
        }
    } else {
        echo "ℹ️  Log directory already exists: {$logPath}\n";
    }
    
    // Create .env file if it doesn't exist
    echo "\nChecking environment configuration...\n";
    $envFile = __DIR__ . '/../.env';
    $envExampleFile = __DIR__ . '/../.env.example';
    
    if (!file_exists($envFile) && file_exists($envExampleFile)) {
        if (copy($envExampleFile, $envFile)) {
            echo "✓ Created .env file from .env.example\n";
            echo "  ⚠️  Please review and update the configuration in .env file\n";
        } else {
            echo "⚠️  Could not create .env file from .env.example\n";
        }
    } else if (file_exists($envFile)) {
        echo "ℹ️  .env file already exists\n";
    } else {
        echo "⚠️  No .env.example file found\n";
    }
    
    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "SETUP COMPLETE!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "Your CSIMS installation is ready to use:\n\n";
    echo "📁 Database: " . count($migrations) . " migrations applied\n";
    echo "👤 Admin User: Created (username: admin)\n";
    echo "📂 Directories: Cache and logs setup\n";
    echo "⚙️  Configuration: Environment files checked\n\n";
    
    echo "Next steps:\n";
    echo "1. Update your .env file with correct database credentials\n";
    echo "2. Change the default admin password\n";
    echo "3. Access the API at: " . $config->get('app.url') . "/api/\n";
    echo "4. Test login at: " . $config->get('app.url') . "/api/auth/login\n\n";
    
    echo "API Endpoints Available:\n";
    echo "- GET    /api/health          - System health check\n";
    echo "- GET    /api/auth/csrf       - Get CSRF token\n";
    echo "- POST   /api/auth/login      - User login\n";
    echo "- POST   /api/auth/logout     - User logout\n";
    echo "- GET    /api/auth/user       - Current user info\n";
    echo "- GET    /api/members         - List members (protected)\n";
    echo "- GET    /api/loans           - List loans (protected)\n";
    echo "- GET    /api/system/info     - System information (admin only)\n";
    
    echo "\nFor more information, check the README.md file.\n\n";
    
} catch (Exception $e) {
    echo "\n✗ SETUP FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
