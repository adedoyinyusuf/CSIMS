<?php
/**
 * Setup .env Configuration Helper
 * 
 * Helps create and validate .env file for CSIMS
 * Run: php scripts/setup_env.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   CSIMS - Environment Configuration Helper                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$env_file = '.env';
$env_example = '.env.example';

// Check if .env exists
if (file_exists($env_file)) {
    echo "✓ .env file exists\n\n";
    echo "Reading configuration...\n\n";
    
    // Load and parse .env
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value, '"\'');
        }
    }
    
    // Check required variables
    $required = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE'];
    $missing = [];
    
    foreach ($required as $var) {
        if (empty($config[$var])) {
            $missing[] = $var;
        } else {
            // Mask password
            $display_value = $var === 'DB_PASSWORD' ? '***' : $config[$var];
            echo "  ✓ $var = $display_value\n";
        }
    }
    
    if (!empty($missing)) {
        echo "\n⚠️  Missing required variables:\n";
        foreach ($missing as $var) {
            echo "    - $var\n";
        }
        echo "\nPlease add these to your .env file\n\n";
    } else {
        echo "\n✅ All required database variables are set!\n\n";
        
        // Test connection
        echo "Testing database connection...\n";
        $conn = @new mysqli(
            $config['DB_HOST'],
            $config['DB_USERNAME'],
            $config['DB_PASSWORD'],
            $config['DB_DATABASE']
        );
        
        if ($conn->connect_error) {
            echo "❌ Connection failed: " . $conn->connect_error . "\n\n";
            echo "Please verify your database credentials\n\n";
        } else {
            echo "✅ Database connection successful!\n\n";
            $conn->close();
        }
    }
    
} else {
    echo "⚠️  .env file not found\n\n";
    
    if (file_exists($env_example)) {
        echo "Creating .env from .env.example...\n";
        copy($env_example, $env_file);
        echo "✅ Created .env file\n\n";
        echo "📝 Please edit .env and configure:\n";
        echo "   - DB_HOST (usually 'localhost')\n";
        echo "   - DB_USERNAME (your MySQL username)\n";
        echo "   - DB_PASSWORD (your MySQL password)\n";
        echo "   - DB_DATABASE (usually 'csims_db')\n\n";
    } else {
        echo "❌ .env.example not found\n";
        echo "Creating basic .env file...\n\n";
        
        $basic_env = <<<ENV
# Database Configuration
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
DB_DATABASE=csims_db

# Application
APP_NAME=CSIMS
APP_ENV=production
APP_DEBUG=false

# API
API_ALLOWED_ORIGINS=http://localhost,https://yoursite.com
ENV;
        
        file_put_contents($env_file, $basic_env);
        echo "✅ Created basic .env file\n\n";
        echo "📝 Please edit it with your actual credentials\n\n";
    }
}

echo "══════════════════════════════════════════════════════════════\n\n";
?>
