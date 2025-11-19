<?php
require_once __DIR__ . '/../includes/config/database.php';

class NotificationService {
    private $db;
    
    // Email configuration - these should be moved to a config file
    private $smtpHost = 'smtp.gmail.com';
    private $smtpPort = 587;
    private $smtpUsername = ''; // Configure in production
    private $smtpPassword = ''; // Configure in production
    private $fromEmail = 'noreply@csims.local';
    private $fromName = 'CSIMS System';
    
    // SMS configuration (placeholder - implement with actual SMS provider)
    private $smsEnabled = false;
    private $smsApiKey = '';
    private $smsApiUrl = '';
    
    public function __construct() {
        $this->db = (new PdoDatabase())->getConnection();

        
        // Load configuration from database or config file
        $this->loadNotificationConfig();
    }
    
    /**
     * Send approval request notification
     */
    public function sendApprovalRequest($email, $username, $workflow, $level) {
        $subject = "Approval Request - {$workflow['template_name']}";
        
        $entityDetails = $this->getEntityDetails($workflow['entity_type'], $workflow['entity_id']);
        
        $body = $this->buildApprovalRequestEmail($username, $workflow, $level, $entityDetails);
        
        $this->sendEmail($email, $subject, $body);
        
        $this->log("approval_request_sent", [
            'workflow_id' => $workflow['id'],
            'recipient' => $email,
            'level' => $level
        ]);
    }
    
    /**
     * Send timeout notification
     */
    public function sendTimeoutNotification($userId, $workflow) {
        $user = $this->getUserById($userId);
        if (!$user) return;
        
        $subject = "Workflow Timeout - {$workflow['template_name']}";
        $body = $this->buildTimeoutNotificationEmail($user, $workflow);
        
        $this->sendEmail($user['email'], $subject, $body);
    }
    
    /**
     * Send admin timeout alert
     */
    public function sendAdminTimeoutAlert($workflow, $level) {
        $admins = $this->getAdminUsers();
        
        $subject = "Workflow Timeout Alert - {$workflow['template_name']}";
        $body = $this->buildAdminTimeoutAlert($workflow, $level);
        
        foreach ($admins as $admin) {
            $this->sendEmail($admin['email'], $subject, $body);
        }
    }
    
    /**
     * Send workflow completion notification
     */
    public function sendCompletionNotification($workflow, $status) {
        if ($workflow['requested_by']) {
            $user = $this->getUserById($workflow['requested_by']);
            if ($user) {
                $subject = "Workflow {$status} - {$workflow['template_name']}";
                $body = $this->buildCompletionNotificationEmail($user, $workflow, $status);
                
                $this->sendEmail($user['email'], $subject, $body);
            }
        }
    }
    
    /**
     * Send welcome email for new member
     */
    public function sendWelcomeEmail($memberId) {
        $member = $this->getMemberById($memberId);
        if (!$member) return;
        
        $subject = "Welcome to CSIMS!";
        $body = $this->buildWelcomeEmail($member);
        
        $this->sendEmail($member['email'], $subject, $body);
    }
    
    /**
     * Send loan disbursement notification
     */
    public function sendDisbursementNotification($loanId) {
        $loan = $this->getLoanById($loanId);
        if (!$loan) return;
        
        $member = $this->getMemberById($loan['member_id']);
        if (!$member) return;
        
        $subject = "Loan Disbursement - #{$loan['id']}";
        $body = $this->buildDisbursementEmail($member, $loan);
        
        $this->sendEmail($member['email'], $subject, $body);
    }
    
    /**
     * Build approval request email
     */
    private function buildApprovalRequestEmail($username, $workflow, $level, $entityDetails) {
        $approvalUrl = "http://" . $_SERVER['HTTP_HOST'] . "/CSIMS/admin/workflow_approval.php?id=" . $workflow['id'];
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Approval Request</h2>
            
            <p>Dear {$username},</p>
            
            <p>You have a new approval request that requires your attention:</p>
            
            <div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>
                <h3>Workflow Details</h3>
                <p><strong>Type:</strong> {$workflow['template_name']}</p>
                <p><strong>Requested by:</strong> {$workflow['requested_by_name']}</p>
                <p><strong>Amount:</strong> " . ($workflow['amount'] ? number_format($workflow['amount'], 2) : 'N/A') . "</p>
                <p><strong>Level:</strong> {$level}</p>
                <p><strong>Submitted:</strong> {$workflow['created_at']}</p>
            </div>
            
            " . ($entityDetails ? "<div style='background-color: #f9f9f9; padding: 15px; margin: 20px 0;'>
                <h3>Entity Details</h3>
                {$entityDetails}
            </div>" : "") . "
            
            <div style='margin: 30px 0;'>
                <a href='{$approvalUrl}' 
                   style='background-color: #007cba; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                   Review & Approve
                </a>
            </div>
            
            <p style='color: #666; font-size: 12px;'>
                This is an automated message. Please do not reply to this email.
            </p>
        </body>
        </html>
        ";
    }
    
    /**
     * Build timeout notification email
     */
    private function buildTimeoutNotificationEmail($user, $workflow) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Workflow Timeout</h2>
            
            <p>Dear {$user['first_name']},</p>
            
            <p>Unfortunately, your workflow request has timed out due to no response from the approvers within the specified timeframe.</p>
            
            <div style='background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
                <h3>Workflow Details</h3>
                <p><strong>Type:</strong> {$workflow['template_name']}</p>
                <p><strong>Amount:</strong> " . ($workflow['amount'] ? number_format($workflow['amount'], 2) : 'N/A') . "</p>
                <p><strong>Submitted:</strong> {$workflow['created_at']}</p>
                <p><strong>Status:</strong> Timeout</p>
            </div>
            
            <p>You may resubmit your request if needed. Please contact support if you have any questions.</p>
            
            <p>Best regards,<br>CSIMS Support Team</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Build admin timeout alert
     */
    private function buildAdminTimeoutAlert($workflow, $level) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Workflow Timeout Alert</h2>
            
            <p>A workflow has timed out at approval level {$level}:</p>
            
            <div style='background-color: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
                <h3>Workflow Details</h3>
                <p><strong>Type:</strong> {$workflow['template_name']}</p>
                <p><strong>Requested by:</strong> {$workflow['requested_by_name']}</p>
                <p><strong>Amount:</strong> " . ($workflow['amount'] ? number_format($workflow['amount'], 2) : 'N/A') . "</p>
                <p><strong>Level:</strong> {$level}</p>
                <p><strong>Submitted:</strong> {$workflow['created_at']}</p>
            </div>
            
            <p>Please review the workflow configuration and approver assignments.</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Build completion notification email
     */
    private function buildCompletionNotificationEmail($user, $workflow, $status) {
        $statusColor = [
            'approved' => '#28a745',
            'rejected' => '#dc3545',
            'changes_requested' => '#ffc107'
        ][$status] ?? '#007cba';
        
        $statusText = ucfirst(str_replace('_', ' ', $status));
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Workflow {$statusText}</h2>
            
            <p>Dear {$user['first_name']},</p>
            
            <p>Your workflow request has been {$statusText}.</p>
            
            <div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid {$statusColor}; margin: 20px 0;'>
                <h3>Workflow Details</h3>
                <p><strong>Type:</strong> {$workflow['template_name']}</p>
                <p><strong>Amount:</strong> " . ($workflow['amount'] ? number_format($workflow['amount'], 2) : 'N/A') . "</p>
                <p><strong>Status:</strong> {$statusText}</p>
                <p><strong>Completed:</strong> {$workflow['completed_at']}</p>
                " . ($workflow['final_comments'] ? "<p><strong>Comments:</strong> {$workflow['final_comments']}</p>" : "") . "
            </div>
            
            " . ($status === 'approved' ? "<p>Your request has been approved and will be processed accordingly.</p>" : 
                ($status === 'rejected' ? "<p>Your request was not approved. If you have questions, please contact support.</p>" : 
                "<p>Changes have been requested. Please review the comments and resubmit if needed.</p>")) . "
            
            <p>Best regards,<br>CSIMS Team</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Build welcome email
     */
    private function buildWelcomeEmail($member) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Welcome to CSIMS!</h2>
            
            <p>Dear {$member['first_name']} {$member['last_name']},</p>
            
            <p>Congratulations! Your membership application has been approved and your account is now active.</p>
            
            <div style='background-color: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                <h3>Your Membership Details</h3>
                <p><strong>Member ID:</strong> {$member['member_number']}</p>
                <p><strong>Email:</strong> {$member['email']}</p>
                <p><strong>Phone:</strong> {$member['phone']}</p>
                <p><strong>Join Date:</strong> {$member['created_at']}</p>
            </div>
            
            <p>You can now:</p>
            <ul>
                <li>Apply for loans</li>
                <li>Make savings deposits</li>
                <li>View your account statements</li>
                <li>Update your profile information</li>
            </ul>
            
            <div style='margin: 30px 0;'>
                <a href='http://" . $_SERVER['HTTP_HOST'] . "/CSIMS/member_dashboard.php' 
                   style='background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                   Access Your Dashboard
                </a>
            </div>
            
            <p>If you have any questions, please don't hesitate to contact our support team.</p>
            
            <p>Welcome aboard!</p>
            <p>The CSIMS Team</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Build disbursement email
     */
    private function buildDisbursementEmail($member, $loan) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Loan Disbursement Notification</h2>
            
            <p>Dear {$member['first_name']} {$member['last_name']},</p>
            
            <p>Your loan has been successfully disbursed!</p>
            
            <div style='background-color: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                <h3>Loan Details</h3>
                <p><strong>Loan ID:</strong> #{$loan['id']}</p>
                <p><strong>Amount:</strong> " . number_format($loan['amount'], 2) . "</p>
                <p><strong>Interest Rate:</strong> {$loan['interest_rate']}%</p>
                <p><strong>Term:</strong> {$loan['term_months']} months</p>
                <p><strong>Disbursed:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <p><strong>Important:</strong> Your first payment is due on your next payment date. Please ensure you have sufficient funds in your account for automatic deduction.</p>
            
            <p>Thank you for choosing CSIMS for your financial needs.</p>
            
            <p>Best regards,<br>CSIMS Loans Team</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Send email using PHP mail or SMTP
     */
    private function sendEmail($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        try {
            // Use PHP's mail() function - in production, consider using PHPMailer or similar
            $success = mail($to, $subject, $body, implode("\r\n", $headers));
            
            if ($success) {
                $this->log("email_sent", [
                    'to' => $to,
                    'subject' => $subject
                ]);
            } else {
                $this->log("email_failed", [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => 'mail() function returned false'
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->log("email_failed", [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send SMS notification (placeholder)
     */
    private function sendSMS($phone, $message) {
        if (!$this->smsEnabled) {
            return false;
        }
        
        // Implement SMS sending logic here
        // This would typically involve calling an SMS API service
        
        try {
            // Placeholder for SMS API call
            $this->log("sms_placeholder", [
                'phone' => $phone,
                'message' => substr($message, 0, 100)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->log("sms_failed", [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send loan application confirmation to member
     */
    public function sendLoanApplicationConfirmation($memberId, $loanId, $amount) {
        $member = $this->getMemberById($memberId);
        if (!$member) { return; }

        $loan = $this->getLoanById($loanId);
        $subject = "Loan Application Submitted - #" . sprintf('%06d', $loanId);

        $body = "\n            <html>\n            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>\n                <h2>Loan Application Received</h2>\n                <p>Dear " . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . ",</p>\n                <p>Your loan application has been received and is now in review.</p>\n                <div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>\n                    <p><strong>Application ID:</strong> #" . sprintf('%06d', $loanId) . "</p>\n                    <p><strong>Amount Requested:</strong> ₦" . number_format($amount, 2) . "</p>\n                    <p><strong>Loan Type:</strong> " . ($loan ? htmlspecialchars($loan['loan_type_id']) : 'N/A') . "</p>\n                    <p><strong>Status:</strong> pending</p>\n                </div>\n                <p>You will receive notifications as your application progresses through the approval process.</p>\n                <p>Thank you,<br>CSIMS</p>\n            </body>\n            </html>\n        ";

        $this->sendEmail($member['email'], $subject, $body);

        $this->log("loan_application_confirmation_sent", [
            'loan_id' => $loanId,
            'member_id' => $memberId,
            'amount' => $amount,
        ]);
    }

    /**
     * Send loan approval/rejection notification to member
     */
    public function sendLoanApprovalNotification($memberId, $loanId, $amount, $status) {
        $member = $this->getMemberById($memberId);
        if (!$member) { return; }

        $loan = $this->getLoanById($loanId);
        $subject = "Loan Application " . ucfirst($status) . " - #" . sprintf('%06d', $loanId);

        $body = "\n            <html>\n            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>\n                <h2>Loan Application " . ucfirst($status) . "</h2>\n                <p>Dear " . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . ",</p>\n                <div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>\n                    <p><strong>Application ID:</strong> #" . sprintf('%06d', $loanId) . "</p>\n                    <p><strong>Amount:</strong> ₦" . number_format($amount, 2) . "</p>\n                    <p><strong>Status:</strong> " . htmlspecialchars($status) . "</p>\n                </div>\n                <p>" . ($status === 'approved' ? 'Please visit our office for disbursement.' : 'Please contact support for more details.') . "</p>\n                <p>Thank you,<br>CSIMS</p>\n            </body>\n            </html>\n        ";

        $this->sendEmail($member['email'], $subject, $body);

        $this->log("loan_approval_notification_sent", [
            'loan_id' => $loanId,
            'member_id' => $memberId,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    /**
     * Lightweight internal logger to avoid external LogService dependency
     */
    private function log(string $event, array $data = []): void {
        try {
            error_log('[CSIMS] ' . json_encode(['event' => $event, 'data' => $data, 'ts' => date('c')]));
        } catch (\Throwable $e) {
            // no-op
        }
    }
    
    // Schema helper methods for dynamic primary/foreign keys
    private function hasColumn(string $table, string $column): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    private function getMembersPrimaryKey(): string {
        if ($this->hasColumn('members', 'id')) return 'id';
        if ($this->hasColumn('members', 'member_id')) return 'member_id';
        return 'id';
    }
    
    private function getLoansPrimaryKey(): string {
        if ($this->hasColumn('loans', 'id')) return 'id';
        if ($this->hasColumn('loans', 'loan_id')) return 'loan_id';
        return 'id';
    }
    
    /**
     * Get entity details for email context
     */
    private function getEntityDetails($entityType, $entityId) {
        switch ($entityType) {
            case 'loan':
                return $this->getLoanDetailsForEmail($entityId);
            case 'member_registration':
                return $this->getMemberDetailsForEmail($entityId);
            case 'withdrawal':
                return $this->getWithdrawalDetailsForEmail($entityId);
            default:
                return null;
        }
    }
    
    /**
     * Get loan details for email
     */
    private function getLoanDetailsForEmail($loanId) {
        $loanPk = $this->getLoansPrimaryKey();
        $memberPk = $this->getMembersPrimaryKey();
        $memberFk = $this->hasColumn('loans', 'member_id') ? 'member_id' : 'memberId';
        $sql = "SELECT l.*, m.first_name, m.last_name, m.member_number, 
                       lt.type_name as loan_type
                FROM loans l
                JOIN members m ON l.$memberFk = m.$memberPk
                LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
                WHERE l.$loanPk = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) return null;
        
        return "
            <p><strong>Applicant:</strong> {$loan['first_name']} {$loan['last_name']} (#{$loan['member_number']})</p>
            <p><strong>Loan Type:</strong> {$loan['loan_type']}</p>
            <p><strong>Amount:</strong> " . number_format($loan['amount'], 2) . "</p>
            <p><strong>Term:</strong> {$loan['term_months']} months</p>
            <p><strong>Interest Rate:</strong> {$loan['interest_rate']}%</p>
            <p><strong>Purpose:</strong> {$loan['purpose']}</p>
        ";
    }
    
    /**
     * Get member details for email
     */
    private function getMemberDetailsForEmail($memberId) {
        $memberPk = $this->getMembersPrimaryKey();
        $sql = "SELECT * FROM members WHERE $memberPk = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) return null;
        
        return "
            <p><strong>Name:</strong> {$member['first_name']} {$member['last_name']}</p>
            <p><strong>Email:</strong> {$member['email']}</p>
            <p><strong>Phone:</strong> {$member['phone']}</p>
            <p><strong>Address:</strong> {$member['address']}</p>
            <p><strong>Registration Date:</strong> {$member['created_at']}</p>
        ";
    }
    
    /**
     * Get withdrawal details for email
     */
    private function getWithdrawalDetailsForEmail($withdrawalId) {
        // Placeholder - implement based on your withdrawal table structure
        return "<p><strong>Withdrawal ID:</strong> #{$withdrawalId}</p>";
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get member by ID
     */
    private function getMemberById($memberId) {
        $memberPk = $this->getMembersPrimaryKey();
        $sql = "SELECT * FROM members WHERE $memberPk = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$memberId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get loan by ID
     */
    private function getLoanById($loanId) {
        $loanPk = $this->getLoansPrimaryKey();
        $sql = "SELECT l.*, lt.type_name 
                FROM loans l
                LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
                WHERE l.$loanPk = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loanId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get admin users
     */
    private function getAdminUsers() {
        $sql = "SELECT u.*, r.role_name 
                FROM users u
                JOIN roles r ON u.role = r.id
                WHERE r.role_name IN ('admin', 'super_admin') 
                AND u.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Load notification configuration
     */
    private function loadNotificationConfig() {
        // Load from database settings or config file
        // This is a placeholder - implement based on your configuration system
        
        try {
            $sql = "SELECT setting_name, setting_value FROM system_settings 
                    WHERE setting_name LIKE 'notification_%' OR setting_name LIKE 'email_%'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            if (isset($settings['email_smtp_host'])) {
                $this->smtpHost = $settings['email_smtp_host'];
            }
            if (isset($settings['email_smtp_port'])) {
                $this->smtpPort = $settings['email_smtp_port'];
            }
            if (isset($settings['email_from'])) {
                $this->fromEmail = $settings['email_from'];
            }
            if (isset($settings['notification_sms_enabled'])) {
                $this->smsEnabled = (bool)$settings['notification_sms_enabled'];
            }
            
        } catch (Exception $e) {
            // Silently continue with defaults if settings table doesn't exist
            $this->log("notification_config_load_failed", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
?>