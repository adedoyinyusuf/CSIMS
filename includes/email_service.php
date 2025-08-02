<?php
/**
 * Email Service Class
 * 
 * Handles email sending functionality using PHPMailer
 * Supports SMTP configuration and HTML/plain text emails
 */

// Fallback to PHP's built-in mail() function if PHPMailer is not available
// To use PHPMailer, install via Composer: composer require phpmailer/phpmailer
// Then uncomment the lines below and comment out the fallback

// require_once __DIR__ . '/../vendor/autoload.php';
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\SMTP;
// use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $config;
    
    public function __construct() {
        $this->loadConfig();
    }
    
    /**
     * Load email configuration
     */
    private function loadConfig() {
        // Load from config file or environment variables
        $this->config = [
            'smtp_host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'smtp_port' => $_ENV['SMTP_PORT'] ?? 587,
            'smtp_username' => $_ENV['SMTP_USERNAME'] ?? 'your-email@gmail.com',
            'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? 'your-app-password',
            'smtp_encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
            'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@csims.com',
            'from_name' => $_ENV['FROM_NAME'] ?? 'CSIMS - Member Management System',
            'reply_to_email' => $_ENV['REPLY_TO_EMAIL'] ?? 'support@csims.com',
            'reply_to_name' => $_ENV['REPLY_TO_NAME'] ?? 'CSIMS Support'
        ];
    }
    
    /**
     * Get email headers for built-in mail() function
     */
    private function getEmailHeaders($toName = '') {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>';
        $headers[] = 'Reply-To: ' . $this->config['reply_to_name'] . ' <' . $this->config['reply_to_email'] . '>';
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        return implode("\r\n", $headers);
    }
    
    /**
     * Send email
     * 
     * @param string $toEmail Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $toName Recipient name (optional)
     * @param string $altBody Plain text alternative (optional)
     * @return bool True on success, false on failure
     */
    public function send($toEmail, $subject, $body, $toName = '', $altBody = '') {
        try {
            // Format recipient
            $to = !empty($toName) ? "$toName <$toEmail>" : $toEmail;
            
            // Get headers
            $headers = $this->getEmailHeaders($toName);
            
            // Format email body
            $formattedBody = $this->formatEmailBody($body);
            
            // Send email using PHP's built-in mail() function
            $result = mail($to, $subject, $formattedBody, $headers);
            
            if ($result) {
                $this->logEmail($toEmail, $subject, 'sent');
                return true;
            } else {
                $this->logEmail($toEmail, $subject, 'failed', 'mail() function returned false');
                return false;
            }
            
        } catch (Exception $e) {
            $this->logEmail($toEmail, $subject, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send bulk emails
     * 
     * @param array $recipients Array of recipient data
     * @param string $subject Email subject
     * @param string $body Email body template
     * @return array Results with success/failure counts
     */
    public function sendBulk($recipients, $subject, $body) {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($recipients as $recipient) {
            $personalizedBody = $this->personalizeContent($body, $recipient);
            $personalizedSubject = $this->personalizeContent($subject, $recipient);
            
            if ($this->send(
                $recipient['email'],
                $personalizedSubject,
                $personalizedBody,
                $recipient['name'] ?? $recipient['email']
            )) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $recipient['email'],
                    'error' => 'Failed to send email'
                ];
            }
            
            // Small delay to avoid overwhelming the SMTP server
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
    
    /**
     * Format email body with HTML template
     */
    private function formatEmailBody($content) {
        $template = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CSIMS Notification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #007bff;
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    background-color: #f8f9fa;
                    padding: 30px;
                    border-radius: 0 0 5px 5px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    padding: 20px;
                    font-size: 12px;
                    color: #666;
                    border-top: 1px solid #ddd;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #007bff;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 10px 0;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>CSIMS - Member Management System</h1>
            </div>
            <div class="content">
                {CONTENT}
            </div>
            <div class="footer">
                <p>This is an automated message from CSIMS Member Management System.</p>
                <p>If you have any questions, please contact our support team.</p>
                <p>&copy; ' . date('Y') . ' CSIMS. All rights reserved.</p>
            </div>
        </body>
        </html>';
        
        return str_replace('{CONTENT}', nl2br($content), $template);
    }
    
    /**
     * Personalize content with recipient data
     */
    private function personalizeContent($content, $recipient) {
        $placeholders = [
            '{name}' => $recipient['name'] ?? '',
            '{first_name}' => $recipient['first_name'] ?? '',
            '{last_name}' => $recipient['last_name'] ?? '',
            '{email}' => $recipient['email'] ?? '',
            '{member_id}' => $recipient['member_id'] ?? '',
            '{membership_type}' => $recipient['membership_type'] ?? '',
            '{expiry_date}' => $recipient['expiry_date'] ?? '',
            '{current_date}' => date('F j, Y'),
            '{current_year}' => date('Y')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }
    
    /**
     * Test email configuration
     */
    public function testConnection() {
        try {
            // Since we're using PHP's built-in mail() function, we can't test SMTP directly
            // Instead, we'll validate the configuration and check if mail() function is available
            if (!function_exists('mail')) {
                return ['success' => false, 'message' => 'PHP mail() function is not available'];
            }
            
            // Validate configuration
            if (empty($this->config['from_email']) || !filter_var($this->config['from_email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid from email configuration'];
            }
            
            return ['success' => true, 'message' => 'Email configuration is valid and mail() function is available'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Email configuration test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail($toEmail, $toName = 'Test User') {
        $subject = 'CSIMS Email Test';
        $body = '
            <h2>Email Test Successful!</h2>
            <p>This is a test email from the CSIMS Member Management System.</p>
            <p>If you received this email, your email configuration is working correctly.</p>
            <p><strong>Test Details:</strong></p>
            <ul>
                <li>Sent to: ' . $toEmail . '</li>
                <li>Sent at: ' . date('Y-m-d H:i:s') . '</li>
                <li>SMTP Host: ' . $this->config['smtp_host'] . '</li>
                <li>SMTP Port: ' . $this->config['smtp_port'] . '</li>
            </ul>
        ';
        
        return $this->send($toEmail, $toName, $subject, $body);
    }
    
    /**
     * Log email activity
     */
    private function logEmail($toEmail, $subject, $status, $error = null) {
        $logFile = __DIR__ . '/../logs/email.log';
        
        // Ensure log directory exists
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] TO: $toEmail | SUBJECT: $subject | STATUS: $status";
        
        if ($error) {
            $logEntry .= " | ERROR: $error";
        }
        
        $logEntry .= PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats() {
        $logFile = __DIR__ . '/../logs/email.log';
        
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
     * Create email template
     */
    public function createTemplate($name, $subject, $body, $variables = []) {
        $template = [
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'variables' => $variables,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $templateFile = __DIR__ . '/../templates/email/' . sanitizeFilename($name) . '.json';
        
        // Ensure template directory exists
        if (!file_exists(dirname($templateFile))) {
            mkdir(dirname($templateFile), 0755, true);
        }
        
        return file_put_contents($templateFile, json_encode($template, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load email template
     */
    public function loadTemplate($name) {
        $templateFile = __DIR__ . '/../templates/email/' . sanitizeFilename($name) . '.json';
        
        if (!file_exists($templateFile)) {
            return null;
        }
        
        $content = file_get_contents($templateFile);
        return json_decode($content, true);
    }
}

/**
 * Sanitize filename for template storage
 */
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($filename));
}
?>