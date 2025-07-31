<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/CSIMS/config/config.php';

class MemberController {
    private $db;
    private $conn;
    private $session;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->session = Session::getInstance();
    }
    
    // Add new member
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
        $duration = $membership['duration']; // Duration in months
        
        $join_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime("+$duration months"));
        
        // Insert member
        $stmt = $this->conn->prepare("INSERT INTO members (first_name, last_name, dob, gender, address, phone, email, occupation, photo, membership_type_id, join_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssssis", $first_name, $last_name, $dob, $gender, $address, $phone, $email, $occupation, $photo_path, $membership_type_id, $join_date, $expiry_date);
        
        if ($stmt->execute()) {
            $member_id = $this->conn->insert_id;
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
            $duration = $membership['duration']; // Duration in months
            
            $join_date = date('Y-m-d');
            $expiry_date = date('Y-m-d', strtotime("+$duration months"));
            $update_expiry = true;
        }
        
        // Update member
        if ($update_expiry) {
            $stmt = $this->conn->prepare("UPDATE members SET first_name = ?, last_name = ?, dob = ?, gender = ?, address = ?, phone = ?, email = ?, occupation = ?, photo = ?, membership_type_id = ?, expiry_date = ?, status = ? WHERE member_id = ?");
            $stmt->bind_param("sssssssssissi", $first_name, $last_name, $dob, $gender, $address, $phone, $email, $occupation, $photo_path, $membership_type_id, $expiry_date, $status, $member_id);
        } else {
            $stmt = $this->conn->prepare("UPDATE members SET first_name = ?, last_name = ?, dob = ?, gender = ?, address = ?, phone = ?, email = ?, occupation = ?, photo = ?, membership_type_id = ?, status = ? WHERE member_id = ?");
            $stmt->bind_param("sssssssssssi", $first_name, $last_name, $dob, $gender, $address, $phone, $email, $occupation, $photo_path, $membership_type_id, $status, $member_id);
        }
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Member updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update member: ' . $stmt->error];
        }
    }
    
    // Get member by ID
    public function getMemberById($member_id) {
        $stmt = $this->conn->prepare("SELECT m.*, mt.name as membership_type, mt.fee as membership_fee 
                                    FROM members m 
                                    JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
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
        $query = "SELECT m.*, mt.name as membership_type 
                FROM members m 
                JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
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
            
            $contribStmt = $this->conn->prepare("INSERT INTO contributions (member_id, amount, contribution_date, contribution_type, description, received_by) 
                                            VALUES (?, ?, ?, 'Dues', ?, ?)");
            $description = "Membership renewal for " . $member['first_name'] . " " . $member['last_name'] . " - Payment method: " . $payment_method;
            $contribStmt->bind_param("idssi", $member_id, $amount, $payment_date, $description, $received_by);
            $contribStmt->execute();
            
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
    
    // Get membership types
    public function getMembershipTypes() {
        $stmt = $this->conn->prepare("SELECT * FROM membership_types ORDER BY fee");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        
        return $types;
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
}
?>