<?php
require_once 'config/database.php';

try {
    // Check if settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'settings'");
    $tableExists = $result->num_rows > 0;
    
    if (!$tableExists) {
        echo "Creating settings table...\n";
        
        $sql = "
        CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $conn->query($sql);
        echo "Settings table created successfully.\n";
        
        // Insert default settings
        $defaultSettings = [
            ['system_name', 'CSIMS'],
            ['system_email', 'admin@csims.com'],
            ['system_phone', ''],
            ['system_address', ''],
            ['default_membership_fee', '50.00'],
            ['membership_duration', '12'],
            ['late_payment_penalty', '5.00'],
            ['max_loan_amount', '10000.00'],
            ['default_interest_rate', '10.00'],
            ['max_loan_duration', '24'],
            ['min_contribution_months', '6']
        ];
        
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->bind_param('ss', $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        echo "Default settings inserted successfully.\n";
    } else {
        echo "Settings table already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>