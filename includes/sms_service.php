<?php
/**
 * SMS Service Class
 * 
 * Handles SMS sending functionality using Twilio API
 * Can be easily adapted for other SMS providers like Nexmo, TextLocal, etc.
 */

class SMSService {
    private $config;
    private $client;
    
    public function __construct() {
        $this->loadConfig();
        $this->initializeClient();
    }
    
    /**
     * Load SMS configuration
     */
    private function loadConfig() {
        // Load from config file or environment variables
        $this->config = [
            'provider' => $_ENV['SMS_PROVIDER'] ?? 'twilio', // twilio, nexmo, textlocal
            'twilio_sid' => $_ENV['TWILIO_SID'] ?? 'your-twilio-sid',
            'twilio_token' => $_ENV['TWILIO_TOKEN'] ?? 'your-twilio-token',
            'twilio_from' => $_ENV['TWILIO_FROM'] ?? '+1234567890',
            'nexmo_key' => $_ENV['NEXMO_KEY'] ?? 'your-nexmo-key',
            'nexmo_secret' => $_ENV['NEXMO_SECRET'] ?? 'your-nexmo-secret',
            'nexmo_from' => $_ENV['NEXMO_FROM'] ?? 'CSIMS',
            'textlocal_username' => $_ENV['TEXTLOCAL_USERNAME'] ?? 'your-username',
            'textlocal_hash' => $_ENV['TEXTLOCAL_HASH'] ?? 'your-hash',
            'textlocal_sender' => $_ENV['TEXTLOCAL_SENDER'] ?? 'CSIMS',
            'max_length' => 160, // Standard SMS length
            'enabled' => $_ENV['SMS_ENABLED'] ?? true
        ];
    }
    
    /**
     * Initialize SMS client based on provider
     */
    private function initializeClient() {
        if (!$this->config['enabled']) {
            return;
        }
        
        switch ($this->config['provider']) {
            case 'twilio':
                $this->initializeTwilio();
                break;
            case 'nexmo':
                $this->initializeNexmo();
                break;
            case 'textlocal':
                $this->initializeTextLocal();
                break;
            default:
                throw new Exception('Unsupported SMS provider: ' . $this->config['provider']);
        }
    }
    
    /**
     * Initialize Twilio client
     */
    private function initializeTwilio() {
        // If using Composer: require_once __DIR__ . '/../vendor/autoload.php';
        // If not using Composer, download Twilio PHP SDK manually
        
        try {
            // Uncomment when Twilio SDK is available
            // $this->client = new \Twilio\Rest\Client(
            //     $this->config['twilio_sid'],
            //     $this->config['twilio_token']
            // );
            
            // For now, we'll use a mock client
            $this->client = new MockSMSClient('twilio');
        } catch (Exception $e) {
            throw new Exception('Failed to initialize Twilio client: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize Nexmo client
     */
    private function initializeNexmo() {
        // Mock implementation - replace with actual Nexmo SDK
        $this->client = new MockSMSClient('nexmo');
    }
    
    /**
     * Initialize TextLocal client
     */
    private function initializeTextLocal() {
        // Mock implementation - replace with actual TextLocal API
        $this->client = new MockSMSClient('textlocal');
    }
    
    /**
     * Send SMS message
     * 
     * @param string $toPhone Recipient phone number
     * @param string $message SMS message content
     * @param array $options Additional options
     * @return bool True on success, false on failure
     */
    public function send($toPhone, $message, $options = []) {
        try {
            if (!$this->config['enabled']) {
                $this->logSMS($toPhone, $message, 'disabled', 'SMS service is disabled');
                return false;
            }
            
            // Validate phone number
            $toPhone = $this->formatPhoneNumber($toPhone);
            if (!$this->isValidPhoneNumber($toPhone)) {
                $this->logSMS($toPhone, $message, 'failed', 'Invalid phone number');
                return false;
            }
            
            // Truncate message if too long
            if (strlen($message) > $this->config['max_length']) {
                $message = substr($message, 0, $this->config['max_length'] - 3) . '...';
            }
            
            // Send based on provider
            $result = false;
            switch ($this->config['provider']) {
                case 'twilio':
                    $result = $this->sendViaTwilio($toPhone, $message, $options);
                    break;
                case 'nexmo':
                    $result = $this->sendViaNexmo($toPhone, $message, $options);
                    break;
                case 'textlocal':
                    $result = $this->sendViaTextLocal($toPhone, $message, $options);
                    break;
            }
            
            if ($result) {
                $this->logSMS($toPhone, $message, 'sent');
                return true;
            } else {
                $this->logSMS($toPhone, $message, 'failed', 'Provider send failed');
                return false;
            }
            
        } catch (Exception $e) {
            $this->logSMS($toPhone, $message, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio($toPhone, $message, $options = []) {
        try {
            // Mock implementation - replace with actual Twilio API call
            return $this->client->send($toPhone, $message, $this->config['twilio_from']);
            
            // Actual Twilio implementation would be:
            // $message = $this->client->messages->create(
            //     $toPhone,
            //     [
            //         'from' => $this->config['twilio_from'],
            //         'body' => $message
            //     ]
            // );
            // return $message->sid !== null;
            
        } catch (Exception $e) {
            throw new Exception('Twilio send failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send SMS via Nexmo
     */
    private function sendViaNexmo($toPhone, $message, $options = []) {
        try {
            // Mock implementation - replace with actual Nexmo API call
            return $this->client->send($toPhone, $message, $this->config['nexmo_from']);
            
        } catch (Exception $e) {
            throw new Exception('Nexmo send failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send SMS via TextLocal
     */
    private function sendViaTextLocal($toPhone, $message, $options = []) {
        try {
            // Mock implementation - replace with actual TextLocal API call
            return $this->client->send($toPhone, $message, $this->config['textlocal_sender']);
            
        } catch (Exception $e) {
            throw new Exception('TextLocal send failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send bulk SMS messages
     * 
     * @param array $recipients Array of recipient data
     * @param string $message SMS message template
     * @return array Results with success/failure counts
     */
    public function sendBulk($recipients, $message) {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($recipients as $recipient) {
            $personalizedMessage = $this->personalizeContent($message, $recipient);
            
            if ($this->send($recipient['phone'], $personalizedMessage)) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'phone' => $recipient['phone'],
                    'error' => 'Send failed'
                ];
            }
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second delay
        }
        
        return $results;
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (assuming US +1 for this example)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        // Add + prefix
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Validate phone number format
     */
    private function isValidPhoneNumber($phone) {
        // Basic validation - should be enhanced based on requirements
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }
    
    /**
     * Personalize content with recipient data
     */
    private function personalizeContent($content, $recipient) {
        $placeholders = [
            '{name}' => $recipient['name'] ?? '',
            '{first_name}' => $recipient['first_name'] ?? '',
            '{last_name}' => $recipient['last_name'] ?? '',
            '{member_id}' => $recipient['member_id'] ?? '',
            '{membership_type}' => $recipient['membership_type'] ?? '',
            '{expiry_date}' => $recipient['expiry_date'] ?? '',
            '{current_date}' => date('M j, Y'),
            '{current_year}' => date('Y')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }
    
    /**
     * Test SMS configuration
     */
    public function testConnection() {
        try {
            if (!$this->config['enabled']) {
                return ['success' => false, 'message' => 'SMS service is disabled'];
            }
            
            // Test based on provider
            switch ($this->config['provider']) {
                case 'twilio':
                    return ['success' => true, 'message' => 'Twilio configuration loaded successfully'];
                case 'nexmo':
                    return ['success' => true, 'message' => 'Nexmo configuration loaded successfully'];
                case 'textlocal':
                    return ['success' => true, 'message' => 'TextLocal configuration loaded successfully'];
                default:
                    return ['success' => false, 'message' => 'Unknown SMS provider'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'SMS connection test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send test SMS
     */
    public function sendTestSMS($toPhone, $toName = 'Test User') {
        $message = "CSIMS SMS Test: Hello {$toName}! This is a test message sent at " . date('Y-m-d H:i:s') . ". If you received this, SMS is working correctly.";
        
        return $this->send($toPhone, $message);
    }
    
    /**
     * Log SMS activity
     */
    private function logSMS($toPhone, $message, $status, $error = null) {
        $logFile = __DIR__ . '/../logs/sms.log';
        
        // Ensure log directory exists
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] TO: $toPhone | MESSAGE: " . substr($message, 0, 50) . "... | STATUS: $status";
        
        if ($error) {
            $logEntry .= " | ERROR: $error";
        }
        
        $logEntry .= PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get SMS statistics
     */
    public function getSMSStats() {
        $logFile = __DIR__ . '/../logs/sms.log';
        
        if (!file_exists($logFile)) {
            return [
                'total_sent' => 0,
                'total_failed' => 0,
                'success_rate' => 0
            ];
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalSent = 0;
        $totalFailed = 0;
        
        foreach ($logs as $log) {
            if (strpos($log, 'STATUS: sent') !== false) {
                $totalSent++;
            } elseif (strpos($log, 'STATUS: failed') !== false) {
                $totalFailed++;
            }
        }
        
        $total = $totalSent + $totalFailed;
        $successRate = $total > 0 ? round(($totalSent / $total) * 100, 2) : 0;
        
        return [
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'success_rate' => $successRate
        ];
    }
    
    /**
     * Get delivery status (if supported by provider)
     */
    public function getDeliveryStatus($messageId) {
        // Implementation depends on SMS provider
        // This is a placeholder for future enhancement
        return [
            'status' => 'unknown',
            'message' => 'Delivery status not implemented for current provider'
        ];
    }
}

/**
 * Mock SMS Client for testing purposes
 * Replace with actual SMS provider implementations
 */
class MockSMSClient {
    private $provider;
    
    public function __construct($provider) {
        $this->provider = $provider;
    }
    
    public function send($toPhone, $message, $from) {
        // Simulate SMS sending
        // In production, this would make actual API calls
        
        // Simulate random success/failure for testing
        $success = rand(1, 10) > 1; // 90% success rate
        
        if ($success) {
            // Log successful send
            error_log("[MOCK SMS] Sent via {$this->provider} to {$toPhone}: " . substr($message, 0, 50) . "...");
            return true;
        } else {
            // Log failed send
            error_log("[MOCK SMS] Failed to send via {$this->provider} to {$toPhone}");
            return false;
        }
    }
}
?>