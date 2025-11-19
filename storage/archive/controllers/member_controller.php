<?php
require_once __DIR__ . '/../config/config.php';

class MemberController {
    private $db;
    public $conn;
    private $session;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->session = Session::getInstance();
    }
    
    // Register new member (self-registration)
    public function registerMember($data) {
        // Sanitize inputs
        $ippis_no = Utilities::sanitizeInput($data['ippis_no']);
        $username = Utilities::sanitizeInput($data['username']);
        $password = $data['password'];
        $first_name = Utilities::sanitizeInput($data['first_name']);
        $last_name = Utilities::sanitizeInput($data['last_name']);
        $dob = Utilities::sanitizeInput($data['dob']);
        $gender = Utilities::sanitizeInput($data['gender']);
        $address = Utilities::sanitizeInput($data['address']);
        $phone = Utilities::sanitizeInput($data['phone']);
        $email = Utilities::sanitizeInput($data['email']);
        $occupation = Utilities::sanitizeInput($data['occupation']);
        $membership_type_id = (int)$data['membership_type_id'];
        $monthly_contribution = isset($data['monthly_contribution']) ? (int)$data['monthly_contribution'] : 0;
        
        // Validate email
        if (!Utilities::validateEmail($email)) {
            return false;
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Calculate expiry date based on membership type
        $membershipStmt = $this->conn->prepare("SELECT duration FROM membership_types WHERE membership_type_id = ?");
        $membershipStmt->bind_param("i", $membership_type_id);
        $membershipStmt->execute();
        $membershipResult = $membershipStmt->get_result();
        
        if ($membershipResult->num_rows == 0) {
            return false;
        }
        
        $membership = $membershipResult->fetch_assoc();
        $duration = $membership['duration'];
        
        $join_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime("+$duration months"));
        
        // Insert member with pending approval status
        $stmt = $this->conn->prepare("INSERT INTO members (ippis_no, username, password, first_name, last_name, dob, gender, address, phone, email, occupation, membership_type_id, monthly_contribution, join_date, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        
        $stmt->bind_param("ssssssssssssssss", $ippis_no, $username, $password_hash, $first_name, $last_name, $dob, $gender, $address, $phone, $email, $occupation, $membership_type_id, $monthly_contribution, $join_date, $expiry_date);
        return $stmt->execute();
    }
    
    // Check if email already exists
    public function checkExistingMember($email) {
        $stmt = $this->conn->prepare("SELECT member_id FROM members WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    // Retrieve member by email
    public function getMemberByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        return false;
    }

    // Check if username already exists
    public function checkExistingUsername($username) {
        $stmt = $this->conn->prepare("SELECT member_id FROM members WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    // Check if IPPIS number already exists
    public function checkExistingIppis($ippis_no) {
        $stmt = $this->conn->prepare("SELECT member_id FROM members WHERE ippis_no = ?");
        $stmt->bind_param("s", $ippis_no);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    // Authenticate member login using username and password
    public function authenticateMember($username, $password) {
        $stmt = $this->conn->prepare("SELECT member_id, username, password, first_name, last_name, email, status FROM members WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $member = $result->fetch_assoc();
            // Verify password
            if (password_verify($password, $member['password'])) {
                // Remove password from returned data for security
                unset($member['password']);
                
                // Return member data with status for login feedback
                if ($member['status'] === 'Active') {
                    return $member; // Allow login
                } else {
                    // Return status info for feedback but don't allow login
                    return ['status' => $member['status'], 'login_allowed' => false];
                }
            }
        }
        
        return false;
    }
    
    // Get membership types
    public function getMembershipTypes() {
        $stmt = $this->conn->prepare("SELECT * FROM membership_types ORDER BY fee ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get pending members for admin approval
    public function getPendingMembers() {
        $stmt = $this->conn->prepare("SELECT m.*, mt.name as membership_type FROM members m JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id WHERE m.status = 'Pending' ORDER BY m.join_date DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Approve member registration
    public function approveMember($member_id) {
        $stmt = $this->conn->prepare("UPDATE members SET status = 'Active' WHERE member_id = ? AND status = 'Pending'");
        $stmt->bind_param("i", $member_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Member approved successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to approve member or member not found'];
        }
    }
    
    // Reject member registration
    public function rejectMember($member_id) {
        $stmt = $this->conn->prepare("UPDATE members SET status = 'Rejected' WHERE member_id = ? AND status = 'Pending'");
        $stmt->bind_param("i", $member_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Member registration rejected'];
        } else {
            return ['success' => false, 'message' => 'Failed to reject member or member not found'];
        }
    }
    
    // Update member profile (member self-update)
    public function updateMemberProfile($member_id, $data) {
        // Sanitize basic inputs
        $updates = [];
        $types = "";
        $values = [];
        
        // Basic profile fields
        if (isset($data['first_name'])) {
            $updates[] = "first_name = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $updates[] = "last_name = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['last_name']);
        }
        if (isset($data['dob'])) {
            $updates[] = "dob = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['dob']);
        }
        if (isset($data['gender'])) {
            $updates[] = "gender = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['gender']);
        }
        if (isset($data['address'])) {
            $updates[] = "address = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['address']);
        }
        if (isset($data['phone'])) {
            $updates[] = "phone = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['phone']);
        }
        if (isset($data['email'])) {
            $email = Utilities::sanitizeInput($data['email']);
            // Validate email
            if (!empty($email) && !Utilities::validateEmail($email)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Check if email already exists for another member
            if (!empty($email)) {
                $checkStmt = $this->conn->prepare("SELECT member_id FROM members WHERE email = ? AND member_id != ?");
                $checkStmt->bind_param("si", $email, $member_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    return ['success' => false, 'message' => 'Email already exists for another member'];
                }
            }
            
            $updates[] = "email = ?";
            $types .= "s";
            $values[] = $email;
        }
        if (isset($data['occupation'])) {
            $updates[] = "occupation = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['occupation']);
        }
        
        // Extended profile fields
        if (isset($data['middle_name'])) {
            $updates[] = "middle_name = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['middle_name']);
        }
        if (isset($data['marital_status'])) {
            $updates[] = "marital_status = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['marital_status']);
        }
        if (isset($data['highest_qualification'])) {
            $updates[] = "highest_qualification = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['highest_qualification']);
        }
        if (isset($data['years_of_residence'])) {
            $updates[] = "years_of_residence = ?";
            $types .= "i";
            $values[] = (int)$data['years_of_residence'];
        }
        
        // Employment fields
        if (isset($data['employee_rank'])) {
            $updates[] = "employee_rank = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['employee_rank']);
        }
        if (isset($data['grade_level'])) {
            $updates[] = "grade_level = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['grade_level']);
        }
        if (isset($data['position'])) {
            $updates[] = "position = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['position']);
        }
        if (isset($data['department'])) {
            $updates[] = "department = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['department']);
        }
        if (isset($data['date_of_first_appointment'])) {
            $updates[] = "date_of_first_appointment = ?";
            $types .= "s";
            $values[] = $data['date_of_first_appointment'] ?: null;
        }
        if (isset($data['date_of_retirement'])) {
            $updates[] = "date_of_retirement = ?";
            $types .= "s";
            $values[] = $data['date_of_retirement'] ?: null;
        }
        
        // Banking fields
        if (isset($data['bank_name'])) {
            $updates[] = "bank_name = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['bank_name']);
        }
        if (isset($data['account_number'])) {
            $updates[] = "account_number = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['account_number']);
        }
        if (isset($data['account_name'])) {
            $updates[] = "account_name = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['account_name']);
        }
        
        // Next of kin fields
        if (isset($data['next_of_kin_name'])) {
            $updates[] = "next_of_kin_name = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['next_of_kin_name']);
        }
        if (isset($data['next_of_kin_relationship'])) {
            $updates[] = "next_of_kin_relationship = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['next_of_kin_relationship']);
        }
        if (isset($data['next_of_kin_phone'])) {
            $updates[] = "next_of_kin_phone = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['next_of_kin_phone']);
        }
        if (isset($data['next_of_kin_address'])) {
            $updates[] = "next_of_kin_address = ?";
            $types .= "s";
            $values[] = Utilities::sanitizeInput($data['next_of_kin_address']);
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No fields to update'];
        }
        
        // Add member_id to the end
        $types .= "i";
        $values[] = $member_id;
        
        // Build and execute the query
        $sql = "UPDATE members SET " . implode(", ", $updates) . " WHERE member_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error];
        }
    }
    
    // Change member password
    public function changePassword($member_id, $current_password, $new_password) {
        // Get current password hash
        $stmt = $this->conn->prepare("SELECT password FROM members WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            return ['success' => false, 'message' => 'Member not found'];
        }
        
        $member = $result->fetch_assoc();
        
        // Verify current password
        if (!password_verify($current_password, $member['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $this->conn->prepare("UPDATE members SET password = ? WHERE member_id = ?");
        $updateStmt->bind_param("si", $new_password_hash, $member_id);
        
        if ($updateStmt->execute()) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to change password: ' . $updateStmt->error];
        }
    }

    // Admin reset member password (no current password required)
    public function adminResetPassword($member_id, $new_password) {
        // Verify member exists
        $stmt = $this->conn->prepare("SELECT member_id, first_name, last_name, email FROM members WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            return ['success' => false, 'message' => 'Member not found'];
        }
        
        $member = $result->fetch_assoc();
        
        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $this->conn->prepare("UPDATE members SET password = ?, updated_at = NOW() WHERE member_id = ?");
        $updateStmt->bind_param("si", $new_password_hash, $member_id);
        
        if ($updateStmt->execute()) {
            // Log the password reset action
            $this->logPasswordReset($member_id, $member['first_name'] . ' ' . $member['last_name']);
            
            return [
                'success' => true, 
                'message' => 'Password reset successfully for ' . $member['first_name'] . ' ' . $member['last_name'],
                'member_name' => $member['first_name'] . ' ' . $member['last_name'],
                'member_email' => $member['email']
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to reset password: ' . $updateStmt->error];
        }
    }
    
    // Log password reset action for audit trail
    private function logPasswordReset($member_id, $member_name) {
        $admin_id = $this->session->get('user_id');
        $admin_name = $this->session->get('first_name') . ' ' . $this->session->get('last_name');
        
        $log_message = "Password reset by admin {$admin_name} (ID: {$admin_id}) for member {$member_name} (ID: {$member_id})";
        
        // Log to security log file
        $log_file = __DIR__ . '/../logs/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] PASSWORD_RESET: {$log_message}" . PHP_EOL;
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    // Resolve or create member_types entry and return its ID (type_id)
    private function getOrCreateMemberTypeId($typeName) {
        $typeName = Utilities::sanitizeInput($typeName);
        if (empty($typeName)) {
            return null;
        }
        // Try to find existing type
        $stmt = $this->conn->prepare("SELECT type_id FROM member_types WHERE type_name = ?");
        $stmt->bind_param("s", $typeName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['type_id'];
        }
        // Insert new type if not found
        $ins = $this->conn->prepare("INSERT INTO member_types (type_name) VALUES (?)");
        $ins->bind_param("s", $typeName);
        if ($ins->execute()) {
            return (int)$this->conn->insert_id;
        }
        return null;
    }

    // Add new member (admin function)
    public function addMember($data, $photo = null) {
        // Sanitize inputs
        $first_name = Utilities::sanitizeInput($data['first_name']);
        $last_name = Utilities::sanitizeInput($data['last_name']);
        $dob = Utilities::sanitizeInput($data['dob']);
        $gender = Utilities::sanitizeInput($data['gender']);
        $address = Utilities::sanitizeInput($data['address']);
        $phone = Utilities::sanitizeInput($data['phone']);
        $email = Utilities::sanitizeInput($data['email']);
        $occupation = Utilities::sanitizeInput($data['occupation']);
        $membership_type_id = (int)$data['membership_type_id'];
        $member_type = isset($data['member_type']) ? Utilities::sanitizeInput($data['member_type']) : 'member';
        
        // Validate email if provided
        if (!empty($email) && !Utilities::validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email already exists
        if (!empty($email)) {
            $checkStmt = $this->conn->prepare("SELECT member_id FROM members WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
        }
        
        // Upload photo if provided
        $photo_path = null;
        if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
            $upload_result = Utilities::uploadFile($photo, UPLOADS_DIR, ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE);
            
            if ($upload_result['success']) {
                $photo_path = $upload_result['filename'];
            } else {
                return ['success' => false, 'message' => $upload_result['message']];
            }
        }
        
        // Calculate expiry date based on membership type
        $membershipStmt = $this->conn->prepare("SELECT duration FROM membership_types WHERE membership_type_id = ?");
        $membershipStmt->bind_param("i", $membership_type_id);
        $membershipStmt->execute();
        $membershipResult = $membershipStmt->get_result();
        
        if ($membershipResult->num_rows == 0) {
            return ['success' => false, 'message' => 'Invalid membership type'];
        }
        
        $membership = $membershipResult->fetch_assoc();
        $duration = $membership['duration'];
        
        $join_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime("+$duration months"));
        
        // Insert member
        $stmt = $this->conn->prepare("INSERT INTO members (first_name, last_name, dob, gender, address, phone, email, occupation, photo, membership_type_id, member_type, join_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssssisss", $first_name, $last_name, $dob, $gender, $address, $phone, $email, $occupation, $photo_path, $membership_type_id, $member_type, $join_date, $expiry_date);
        
        if ($stmt->execute()) {
            $member_id = $this->conn->insert_id;
            // Backfill member_type_id for FK consistency
            $member_type_id_fk = $this->getOrCreateMemberTypeId($member_type);
            if ($member_type_id_fk !== null) {
                $updType = $this->conn->prepare("UPDATE members SET member_type_id = ? WHERE member_id = ?");
                $updType->bind_param("ii", $member_type_id_fk, $member_id);
                $updType->execute();
            }
            return ['success' => true, 'message' => 'Member added successfully', 'member_id' => $member_id];
        } else {
            return ['success' => false, 'message' => 'Failed to add member: ' . $stmt->error];
        }
    }
    
    // Update member
    public function updateMember($member_id, $data, $photo = null) {
        // Sanitize inputs
        $first_name = Utilities::sanitizeInput($data['first_name']);
        $last_name = Utilities::sanitizeInput($data['last_name']);
        $dob = Utilities::sanitizeInput($data['dob']);
        $gender = Utilities::sanitizeInput($data['gender']);
        $address = Utilities::sanitizeInput($data['address']);
        $phone = Utilities::sanitizeInput($data['phone']);
        $email = Utilities::sanitizeInput($data['email']);
        $occupation = Utilities::sanitizeInput($data['occupation']);
        $membership_type_id = (int)$data['membership_type_id'];
        $status = Utilities::sanitizeInput($data['status']);
        
        // Validate email if provided
        if (!empty($email) && !Utilities::validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email already exists for another member
        if (!empty($email)) {
            $checkStmt = $this->conn->prepare("SELECT member_id FROM members WHERE email = ? AND member_id != ?");
            $checkStmt->bind_param("si", $email, $member_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                return ['success' => false, 'message' => 'Email already exists for another member'];
            }
        }
        
        // Get current member data
        $currentStmt = $this->conn->prepare("SELECT photo, membership_type_id FROM members WHERE member_id = ?");
        $currentStmt->bind_param("i", $member_id);
        $currentStmt->execute();
        $currentResult = $currentStmt->get_result();
        
        if ($currentResult->num_rows == 0) {
            return ['success' => false, 'message' => 'Member not found'];
        }
        
        $currentMember = $currentResult->fetch_assoc();
        $photo_path = $currentMember['photo'];
        
        // Upload new photo if provided
        if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
            $upload_result = Utilities::uploadFile($photo, UPLOADS_DIR, ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE);
            
            if ($upload_result['success']) {
                $photo_path = $upload_result['filename'];
                
                // Delete old photo if exists
                if ($currentMember['photo'] && file_exists(UPLOADS_DIR . $currentMember['photo'])) {
                    unlink(UPLOADS_DIR . $currentMember['photo']);
                }
            } else {
                return ['success' => false, 'message' => $upload_result['message']];
            }
        }
        
        // Check if membership type changed
        $update_expiry = false;
        if ($membership_type_id != $currentMember['membership_type_id']) {
            $membershipStmt = $this->conn->prepare("SELECT duration FROM membership_types WHERE membership_type_id = ?");
            $membershipStmt->bind_param("i", $membership_type_id);
            $membershipStmt->execute();
            $membershipResult = $membershipStmt->get_result();
            
            if ($membershipResult->num_rows == 0) {
                return ['success' => false, 'message' => 'Invalid membership type'];
            }
            
            $membership = $membershipResult->fetch_assoc();
            $duration = $membership['duration'];
            
            $join_date = date('Y-m-d');
            $expiry_date = date('Y-m-d', strtotime("+$duration months"));
            $update_expiry = true;
        }
        
        // Update member
        $member_type = isset($data['member_type']) ? Utilities::sanitizeInput($data['member_type']) : null;
        $member_type_id_fk = null;
        if ($member_type !== null && $member_type !== '') {
            $member_type_id_fk = $this->getOrCreateMemberTypeId($member_type);
        }

        $sql = "UPDATE members SET first_name = ?, last_name = ?, dob = ?, gender = ?, address = ?, phone = ?, email = ?, occupation = ?, photo = ?, membership_type_id = ?";
        $params = [$first_name, $last_name, $dob, $gender, $address, $phone, $email, $occupation, $photo_path, $membership_type_id];
        $types = "sssssssss" . "i"; // 9 strings + 1 int

        if ($update_expiry) {
            $sql .= ", expiry_date = ?";
            $params[] = $expiry_date;
            $types .= "s";
        }

        $sql .= ", status = ?";
        $params[] = $status;
        $types .= "s";

        if ($member_type !== null) {
            $sql .= ", member_type = ?";
            $params[] = $member_type;
            $types .= "s";
            if ($member_type_id_fk !== null) {
                $sql .= ", member_type_id = ?";
                $params[] = $member_type_id_fk;
                $types .= "i";
            }
        }

        $sql .= " WHERE member_id = ?";
        $params[] = $member_id;
        $types .= "i";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Member updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update member: ' . $stmt->error];
        }
    }
    
    // Get member by ID
    public function getMemberById($member_id) {
        $stmt = $this->conn->prepare("SELECT m.*, mt.name as membership_type, mt2.type_name AS member_type_label 
                                    FROM members m 
                                    LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                                    LEFT JOIN member_types mt2 ON mt2.type_id = m.member_type_id 
                                    WHERE m.member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    }
    
    // Get all active members
    public function getAllActiveMembers() {
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE status = 'Active' ORDER BY last_name, first_name");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    // Check if email is already taken by another member
    public function isEmailTaken($email, $exclude_member_id = null) {
        if ($exclude_member_id) {
            $stmt = $this->conn->prepare("SELECT member_id FROM members WHERE email = ? AND member_id != ?");
            $stmt->bind_param("si", $email, $exclude_member_id);
        } else {
            $stmt = $this->conn->prepare("SELECT member_id FROM members WHERE email = ?");
            $stmt->bind_param("s", $email);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    // Validate date format
    public function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    // Get all members with pagination
    public function getAllMembers($page = 1, $search = '', $status = '', $membership_type = '') {
        $itemsPerPage = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $itemsPerPage;
        
        // Build query
        // ... existing code ...
        $query = "SELECT m.*, mt.name as membership_type, mt2.type_name AS member_type_label 
                FROM members m 
                JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                LEFT JOIN member_types mt2 ON mt2.type_id = m.member_type_id 
                WHERE 1=1";
        $countQuery = "SELECT COUNT(*) as total FROM members m WHERE 1=1";
        $params = [];
        $types = "";
        
        // Add search condition
        if (!empty($search)) {
            $searchTerm = "%$search%";
            $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)"; 
            $countQuery .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)"; 
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= "ssss";
        }
        
        // Add status filter
        if (!empty($status)) {
            $query .= " AND m.status = ?";
            $countQuery .= " AND m.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        // Add membership type filter
        if (!empty($membership_type)) {
            $query .= " AND m.membership_type_id = ?";
            $countQuery .= " AND m.membership_type_id = ?";
            $params[] = $membership_type;
            $types .= "i";
        }
        
        // Add order and limit
        $query .= " ORDER BY m.last_name, m.first_name LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $itemsPerPage;
        $types .= "ii";
        
        // Get total count (without LIMIT parameters)
        $countParams = array_slice($params, 0, -2); // Remove last 2 params (offset and limit)
        $countTypes = substr($types, 0, -2); // Remove last 2 type chars (ii)
        
        $countStmt = $this->conn->prepare($countQuery);
        if (!empty($countTypes)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalItems = $countResult->fetch_assoc()['total'];
        
        // Get members
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        // Calculate pagination
        $pagination = Utilities::paginate($totalItems, $itemsPerPage, $page);
        
        return [
            'members' => $members,
            'pagination' => $pagination
        ];
    }
    
    // Delete member (soft delete)
    public function deleteMember($member_id) {
        $stmt = $this->conn->prepare("UPDATE members SET status = 'Inactive' WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Member deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete member'];
        }
    }
    
    // Renew membership
    public function renewMembership($member_id, $payment_data) {
        // Get member and membership type
        $memberStmt = $this->conn->prepare("SELECT m.*, mt.duration 
                                        FROM members m 
                                        JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                                        WHERE m.member_id = ?");
        $memberStmt->bind_param("i", $member_id);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        
        if ($memberResult->num_rows == 0) {
            return ['success' => false, 'message' => 'Member not found'];
        }
        
        $member = $memberResult->fetch_assoc();
        $duration = $member['duration']; // Duration in months
        
        // Calculate new expiry date
        $current_date = date('Y-m-d');
        $expiry_date = $member['expiry_date'];
        
        // If membership has expired, start from current date
        if (strtotime($expiry_date) < strtotime($current_date)) {
            $new_expiry_date = date('Y-m-d', strtotime("+$duration months"));
        } else {
            // If not expired, add duration to current expiry date
            $new_expiry_date = date('Y-m-d', strtotime($expiry_date . " +$duration months"));
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Update member expiry date and status
            $updateStmt = $this->conn->prepare("UPDATE members SET expiry_date = ?, status = 'Active' WHERE member_id = ?");
            $updateStmt->bind_param("si", $new_expiry_date, $member_id);
            $updateStmt->execute();
            
            // Record contribution
            $amount = $payment_data['amount'];
            $payment_date = $payment_data['payment_date'] ?? date('Y-m-d');
            $payment_method = $payment_data['payment_method'] ?? 'Cash';
            $received_by = $this->session->get('admin_id');
            
            // Migrate: record as savings deposit into member's Regular account
            $description = "Membership renewal for " . $member['first_name'] . " " . $member['last_name'] . " - Payment method: " . $payment_method;
            
            // Find member Regular savings account
            $acctStmt = $this->conn->prepare("SELECT account_id FROM savings_accounts WHERE member_id = ? AND account_type = 'Regular' AND account_status = 'Active' ORDER BY opening_date ASC LIMIT 1");
            $acctStmt->bind_param("i", $member_id);
            $acctStmt->execute();
            $acctRes = $acctStmt->get_result();
            $account_id = null;
            if ($row = $acctRes->fetch_assoc()) {
                $account_id = (int)$row['account_id'];
            }
            
            // If no Regular account, create one minimal for deposit
            if (!$account_id) {
                $acctCreate = $this->conn->prepare("INSERT INTO savings_accounts (member_id, account_number, account_type, account_name, balance, minimum_balance, interest_rate, interest_calculation, account_status, opening_date, created_by) VALUES (?, CONCAT('REG-', ?, '-', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), 'Regular', 'Regular Savings', 0, 0, 0, 'monthly', 'Active', CURDATE(), ?) ");
                $created_by = $received_by ?: 1;
                $acctCreate->bind_param("iii", $member_id, $member_id, $created_by);
                $acctCreate->execute();
                $account_id = $acctCreate->insert_id;
            }
            
            // Insert savings transaction as deposit
            $txnStmt = $this->conn->prepare("INSERT INTO savings_transactions (account_id, member_id, transaction_type, amount, transaction_date, description, transaction_status, created_by) VALUES (?, ?, 'Deposit', ?, ?, ?, 'Completed', ?) ");
            $txnStmt->bind_param("iidssi", $account_id, $member_id, $amount, $payment_date, $description, $received_by);
            $txnStmt->execute();
            
            // Update account balance
            $balStmt = $this->conn->prepare("UPDATE savings_accounts SET balance = balance + ? WHERE account_id = ?");
            $balStmt->bind_param("di", $amount, $account_id);
            $balStmt->execute();
            
            // Legacy contributions recording removed; renewal is now recorded as a savings deposit
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true, 
                'message' => 'Membership renewed successfully', 
                'new_expiry_date' => $new_expiry_date
            ];
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Failed to renew membership: ' . $e->getMessage()];
        }
    }
    
    // Get members with expiring membership
    public function getExpiringMemberships($days = 30) {
        $current_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime("+$days days"));
        
        $stmt = $this->conn->prepare("SELECT m.*, mt.name as membership_type 
                                    FROM members m 
                                    JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                                    WHERE m.expiry_date BETWEEN ? AND ? 
                                    AND m.status = 'Active' 
                                    ORDER BY m.expiry_date");
        $stmt->bind_param("ss", $current_date, $expiry_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    

    
    // Advanced member search
    public function searchMembersAdvanced($filters = [], $itemsPerPage = 20) {
        $page = $filters['page'] ?? 1;
        $offset = ($page - 1) * $itemsPerPage;
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? '';
        $membership_type = $filters['membership_type'] ?? '';
        
        $query = "SELECT m.*, mt.name as membership_type, mt2.type_name AS member_type_label 
                FROM members m 
                LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                LEFT JOIN member_types mt2 ON mt2.type_id = m.member_type_id 
                WHERE 1=1";
        $countQuery = "SELECT COUNT(*) as total FROM members m WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $searchTerm = "%$search%";
            $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)"; 
            $countQuery .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)"; 
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= "ssss";
        }
        
        if (!empty($status)) {
            $query .= " AND m.status = ?";
            $countQuery .= " AND m.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($membership_type)) {
            $query .= " AND m.membership_type_id = ?";
            $countQuery .= " AND m.membership_type_id = ?";
            $params[] = $membership_type;
            $types .= "i";
        }
        
        $countStmt = $this->conn->prepare($countQuery);
        if (!empty($types)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalMembers = $countResult->fetch_assoc()['total'];
        $countStmt->close();
        
        $query .= " ORDER BY m.last_name, m.first_name LIMIT ? OFFSET ?";
        $params[] = $itemsPerPage;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        $totalPages = ceil($totalMembers / $itemsPerPage);
        
        return [
            'members' => $members,
            'total_members' => $totalMembers,
            'total_pages' => $totalPages,
            'current_page' => $page
        ];
    }
    
    // Get member statistics
    public function getMemberStatistics() {
        $stats = [];
        
        // Total members
        $totalStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM members");
        $totalStmt->execute();
        $stats['total_members'] = $totalStmt->get_result()->fetch_assoc()['total'];
        
        // Active members
        $activeStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM members WHERE status = 'Active'");
        $activeStmt->execute();
        $stats['active_members'] = $activeStmt->get_result()->fetch_assoc()['total'];
        
        // Inactive members
        $inactiveStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM members WHERE status = 'Inactive'");
        $inactiveStmt->execute();
        $stats['inactive_members'] = $inactiveStmt->get_result()->fetch_assoc()['total'];
        
        // Expired members
        $expiredStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM members WHERE status = 'Expired'");
        $expiredStmt->execute();
        $stats['expired_members'] = $expiredStmt->get_result()->fetch_assoc()['total'];
        
        // Members by gender
        $genderStmt = $this->conn->prepare("SELECT gender, COUNT(*) as total FROM members GROUP BY gender");
        $genderStmt->execute();
        $genderResult = $genderStmt->get_result();
        
        $stats['members_by_gender'] = [];
        while ($row = $genderResult->fetch_assoc()) {
            $stats['members_by_gender'][$row['gender']] = $row['total'];
        }
        
        // Members by membership type
        $typeStmt = $this->conn->prepare("SELECT mt.name, COUNT(*) as total 
                                        FROM members m 
                                        JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                                        GROUP BY mt.membership_type_id");
        $typeStmt->execute();
        $typeResult = $typeStmt->get_result();
        
        $stats['members_by_type'] = [];
        while ($row = $typeResult->fetch_assoc()) {
            $stats['members_by_type'][$row['name']] = $row['total'];
        }
        
        // New members this month
        $firstDayOfMonth = date('Y-m-01');
        $lastDayOfMonth = date('Y-m-t');
        
        $newStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM members WHERE join_date BETWEEN ? AND ?");
        $newStmt->bind_param("ss", $firstDayOfMonth, $lastDayOfMonth);
        $newStmt->execute();
        $stats['new_members_this_month'] = $newStmt->get_result()->fetch_assoc()['total'];
        
        return $stats;
    }
    
    // Update member status
    public function updateMemberStatus($memberId, $status) {
        $validStatuses = ['Active', 'Inactive', 'Suspended', 'Expired'];
        $statusFormatted = ucfirst(strtolower($status));
        if (!in_array($statusFormatted, $validStatuses)) {
            return false;
        }
        $stmt = $this->conn->prepare("UPDATE members SET status = ? WHERE member_id = ?");
        $stmt->bind_param("si", $statusFormatted, $memberId);
        return $stmt->execute();
    }
    
    // Extend membership
    public function extendMembership($memberId, $months) {
        $member = $this->getMemberById($memberId);
        if (!$member) {
            return false;
        }
        $currentExpiry = $member['expiry_date'] ?? null;
        if (empty($currentExpiry) || strtotime($currentExpiry) < time()) {
            $newExpiry = date('Y-m-d', strtotime("+$months months"));
        } else {
            $newExpiry = date('Y-m-d', strtotime($currentExpiry . " +$months months"));
        }
        $stmt = $this->conn->prepare("UPDATE members SET expiry_date = ?, status = 'Active' WHERE member_id = ?");
        $stmt->bind_param("si", $newExpiry, $memberId);
        if ($stmt->execute()) {
            $this->recordMembershipExtension($memberId, $months, $newExpiry);
            return true;
        }
        return false;
    }
    
    // Record membership extension transaction
    private function recordMembershipExtension($memberId, $months, $newExpiry) {
        $description = "Membership extended by $months month(s). New expiry: $newExpiry";
        
        $stmt = $this->conn->prepare(
            "INSERT INTO member_transactions (member_id, transaction_type, description, transaction_date) 
             VALUES (?, 'membership_extension', ?, NOW())"
        );
        $stmt->bind_param("is", $memberId, $description);
        $stmt->execute();
    }
    
    // Get members by IDs (for bulk operations)
    public function getMembersByIds($memberIds) {
        if (empty($memberIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
        $query = "SELECT m.*, mt.name as membership_type 
                  FROM members m 
                  LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                  WHERE m.member_id IN ($placeholders)";
        
        $stmt = $this->conn->prepare($query);
        $types = str_repeat('i', count($memberIds));
        $stmt->bind_param($types, ...$memberIds);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    // Bulk update member statuses
    public function bulkUpdateStatus($memberIds, $status) {
        if (empty($memberIds)) {
            return false;
        }
        
        $validStatuses = ['active', 'inactive', 'suspended', 'expired'];
        if (!in_array(strtolower($status), $validStatuses)) {
            return false;
        }
        
        $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
        $query = "UPDATE members SET status = ? WHERE id IN ($placeholders)";
        
        $stmt = $this->conn->prepare($query);
        $types = 's' . str_repeat('i', count($memberIds));
        $params = array_merge([$status], $memberIds);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    
    // Bulk extend memberships
    public function bulkExtendMembership($memberIds, $months) {
        if (empty($memberIds)) {
            return false;
        }
        
        $successCount = 0;
        foreach ($memberIds as $memberId) {
            if ($this->extendMembership($memberId, $months)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }
    
    // Get members by status
    public function getMembersByStatus($status) {
        $stmt = $this->conn->prepare("SELECT m.*, mt.name as membership_type 
                                    FROM members m 
                                    JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                                    WHERE m.status = ? 
                                    ORDER BY m.last_name, m.first_name");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    // Get expiring members
    public function getExpiringMembers($days = 30) {
        $current_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime("+$days days"));
        
        $stmt = $this->conn->prepare("SELECT m.*, mt.name as membership_type 
                                    FROM members m 
                                    JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
                                    WHERE m.expiry_date BETWEEN ? AND ? 
                                    AND m.status = 'Active' 
                                    ORDER BY m.expiry_date");
        $stmt->bind_param("ss", $current_date, $expiry_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    // Get loans by member ID (wrapper method for compatibility)
    public function getLoansByMember($member_id, $limit = 0) {
        require_once __DIR__ . '/loan_controller.php';
        $loanController = new LoanController();
        return $loanController->getLoansByMemberId($member_id, $limit);
    }
    
    // Get contributions by member ID (wrapper method for compatibility)
    public function getContributionsByMember($member_id, $limit = 0) {
        // Use savings transactions only
        $limit = (int)$limit;
        $sql = "SELECT st.*, sa.account_number, sa.account_type, st.transaction_type AS contribution_type, st.transaction_date AS contribution_date FROM savings_transactions st JOIN savings_accounts sa ON st.account_id = sa.account_id WHERE st.member_id = ? ORDER BY st.transaction_date DESC" . ($limit > 0 ? " LIMIT $limit" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $transactions = [];
        while ($row = $res->fetch_assoc()) { $transactions[] = $row; }

        // Remove legacy fallback: always use savings_transactions only
        return $transactions;
    }

    // Update monthly contribution for a member
    public function updateMonthlyContribution($member_id, $new_contribution) {
        // Migrate: store as monthly_target on Regular Target account if present; otherwise update members table for backward compatibility
        $stmt = $this->conn->prepare("UPDATE members SET monthly_contribution = ? WHERE member_id = ?");
        $stmt->bind_param("di", $new_contribution, $member_id);
        return $stmt->execute();
    }
    
    /**
     * Get active members for guarantor selection (excluding specified member)
     * 
     * @param int $exclude_member_id Member ID to exclude from results
     * @return array Active members data
     */
    public function getActiveMembers($exclude_member_id = null) {
        $query = "SELECT member_id, first_name, last_name, email, phone 
                 FROM members 
                 WHERE status = 'Active'";
        
        $params = [];
        $types = "";
        
        if ($exclude_member_id !== null) {
            $query .= " AND member_id != ?";
            $params[] = (int)$exclude_member_id;
            $types .= "i";
        }
        
        $query .= " ORDER BY first_name ASC, last_name ASC";
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        return $members;
    }
    }