<?php

namespace CSIMS\Controllers;

use CSIMS\Controllers\BaseController;
use CSIMS\Services\SecurityService;
use CSIMS\Services\ConfigurationManager;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Models\Member;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Enhanced Member Controller
 * 
 * Modern implementation using the new architecture while maintaining
 * backward compatibility with existing functionality
 */
class MemberController extends BaseController
{
    private MemberRepository $memberRepository;
    private mysqli $connection;
    
    public function __construct(
        SecurityService $security,
        ConfigurationManager $config,
        MemberRepository $memberRepository,
        mysqli $connection
    ) {
        parent::__construct($security, $config);
        $this->memberRepository = $memberRepository;
        $this->connection = $connection;
    }
    
    /**
     * Register new member (self-registration)
     * 
     * @param array $data Member registration data
     * @return array Response array
     */
    public function registerMember(array $data): array
    {
        try {
            // Validate CSRF token
            $this->validateCSRF();
            
            // Define validation rules
            $rules = [
                'ippis_no' => 'required|alnum',
                'username' => 'required|min:3|max:50',
                'password' => 'required|min:8',
                'first_name' => 'required|min:2|max:100',
                'last_name' => 'required|min:2|max:100',
                'email' => 'required|email',
                'phone' => 'required|min:10|max:15',
                'dob' => 'required|date',
                'gender' => 'required',
                'address' => 'required|min:10|max:500',
                'occupation' => 'required|min:2|max:100',
                'membership_type_id' => 'required|int'
            ];
            
            // Validate and sanitize input
            $validatedData = $this->validateInput($data, $rules);
            
            // Additional business logic validation
            $this->validateMemberRegistration($validatedData);
            
            // Validate password strength
            $passwordValidation = $this->security->validatePassword($validatedData['password']);
            if (!$passwordValidation->isValid()) {
                throw new ValidationException('Password requirements not met', 0, null, [
                    'errors' => ['password' => $passwordValidation->getErrors()]
                ]);
            }
            
            // Create member model
            $memberData = $this->prepareMemberData($validatedData);
            $member = new Member($memberData);
            
            // Validate member model
            $memberValidation = $member->validate();
            if (!$memberValidation->isValid()) {
                throw new ValidationException('Member data validation failed', 0, null, [
                    'errors' => $memberValidation->getErrors()
                ]);
            }
            
            // Create member in database
            $createdMember = $this->memberRepository->create($member);
            
            // Log activity
            $this->logActivity('register', 'member', $createdMember->getId(), [
                'username' => $validatedData['username'],
                'email' => $validatedData['email']
            ]);
            
            return $this->successResponse(
                'Member registration submitted successfully. Please wait for admin approval.',
                ['member_id' => $createdMember->getId()]
            );
            
        } catch (ValidationException $e) {
            return $this->handleException($e);
        } catch (DatabaseException $e) {
            return $this->handleException($e);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Authenticate member login
     * 
     * @param string $username
     * @param string $password
     * @return array
     */
    public function authenticateMember(string $username, string $password): array
    {
        try {
            // Sanitize inputs
            $username = $this->security->sanitizeInput($username, 'string');
            
            // Rate limiting check (implement as needed)
            // $this->security->checkRateLimit('member_login', $username);
            
            // Find member by username
            $member = $this->memberRepository->findOneBy(['username' => $username]);
            
            if (!$member) {
                return $this->errorResponse('Invalid credentials');
            }
            
            // Verify password
            if (!password_verify($password, $member->getPasswordHash())) {
                return $this->errorResponse('Invalid credentials');
            }
            
            // Check member status
            if ($member->getStatus() !== 'Active') {
                return $this->errorResponse(
                    'Account not active',
                    [],
                    ['status' => $member->getStatus()]
                );
            }
            
            // Create session
            $this->createMemberSession($member);
            
            // Log activity
            $this->logActivity('login', 'member', $member->getId());
            
            return $this->successResponse('Login successful', [
                'member' => $member->toArray(),
                'redirect' => '/views/member_dashboard.php'
            ]);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get pending members for admin approval
     * 
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPendingMembers(int $page = 1, int $limit = 10): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            
            return $this->getPaginatedResults(
                fn($p, $l) => $this->memberRepository->getPaginated($p, $l, ['status' => 'Pending']),
                $page,
                $limit
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Approve member registration
     * 
     * @param int $memberId
     * @return array
     */
    public function approveMember(int $memberId): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            $this->validateCSRF();
            
            // Find member
            $member = $this->memberRepository->find($memberId);
            if (!$member) {
                return $this->errorResponse('Member not found');
            }
            
            if ($member->getStatus() !== 'Pending') {
                return $this->errorResponse('Only pending members can be approved');
            }
            
            // Approve member
            $member->setStatus('Active');
            $this->memberRepository->update($member);
            
            // Log activity
            $this->logActivity('approve', 'member', $memberId, [
                'previous_status' => 'Pending',
                'new_status' => 'Active'
            ]);
            
            return $this->successResponse('Member approved successfully');
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Reject member registration
     * 
     * @param int $memberId
     * @param string $reason
     * @return array
     */
    public function rejectMember(int $memberId, string $reason = ''): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            $this->validateCSRF();
            
            // Find member
            $member = $this->memberRepository->find($memberId);
            if (!$member) {
                return $this->errorResponse('Member not found');
            }
            
            if ($member->getStatus() !== 'Pending') {
                return $this->errorResponse('Only pending members can be rejected');
            }
            
            // Reject member
            $member->setStatus('Rejected');
            if ($reason) {
                $member->setNotes($reason);
            }
            $this->memberRepository->update($member);
            
            // Log activity
            $this->logActivity('reject', 'member', $memberId, [
                'previous_status' => 'Pending',
                'new_status' => 'Rejected',
                'reason' => $reason
            ]);
            
            return $this->successResponse('Member registration rejected');
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Update member profile
     * 
     * @param int $memberId
     * @param array $data
     * @return array
     */
    public function updateMemberProfile(int $memberId, array $data): array
    {
        try {
            // Require authentication
            $this->requireAuthentication('member');
            $this->validateCSRF();
            
            // Check if user can update this profile
            $currentUser = $this->getCurrentUser('member');
            if ($currentUser['member_id'] != $memberId && !$this->hasPermission('edit_any_member', 'admin')) {
                return $this->errorResponse('Permission denied');
            }
            
            // Define validation rules for profile update
            $rules = [
                'first_name' => 'min:2|max:100',
                'last_name' => 'min:2|max:100',
                'phone' => 'min:10|max:15',
                'address' => 'min:10|max:500',
                'occupation' => 'min:2|max:100'
            ];
            
            // Validate and sanitize input
            $validatedData = $this->validateInput($data, $rules);
            
            // Find member
            $member = $this->memberRepository->find($memberId);
            if (!$member) {
                return $this->errorResponse('Member not found');
            }
            
            // Update member data
            foreach ($validatedData as $field => $value) {
                $setter = 'set' . ucfirst(str_replace('_', '', ucwords($field, '_')));
                if (method_exists($member, $setter)) {
                    $member->$setter($value);
                }
            }
            
            // Save changes
            $this->memberRepository->update($member);
            
            // Log activity
            $this->logActivity('update_profile', 'member', $memberId, $validatedData);
            
            return $this->successResponse('Profile updated successfully');
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get member by ID
     * 
     * @param int $memberId
     * @return array
     */
    public function getMemberById(int $memberId): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            
            $member = $this->memberRepository->find($memberId);
            if (!$member) {
                return $this->errorResponse('Member not found');
            }
            
            // Remove sensitive data if not admin
            if (!$this->hasPermission('view_sensitive_data', 'admin')) {
                $memberData = $member->toArray();
                unset($memberData['password_hash'], $memberData['created_at']);
                return $this->successResponse('Member found', $memberData);
            }
            
            return $this->successResponse('Member found', $member->toArray());
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get membership types
     * 
     * @return array
     */
    public function getMembershipTypes(): array
    {
        try {
            // Use raw SQL for now (can be moved to a repository later)
            $stmt = $this->connection->prepare("SELECT * FROM membership_types ORDER BY fee ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $types = [];
            while ($row = $result->fetch_assoc()) {
                $types[] = $row;
            }
            
            return $this->successResponse('Membership types retrieved', $types);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Search members (admin only)
     * 
     * @param string $searchTerm
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function searchMembers(string $searchTerm, array $filters = [], int $page = 1, int $limit = 10): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            
            $searchTerm = $this->security->sanitizeInput($searchTerm, 'string');
            $filters = $this->security->sanitizeArray($filters);
            
            return $this->getPaginatedResults(
                fn($p, $l) => $this->memberRepository->search($searchTerm, $filters, $p, $l),
                $page,
                $limit
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    // ================== PRIVATE HELPER METHODS ==================
    
    /**
     * Validate member registration business rules
     * 
     * @param array $data
     * @throws ValidationException
     */
    private function validateMemberRegistration(array $data): void
    {
        // Check if email already exists
        if ($this->memberRepository->findOneBy(['email' => $data['email']])) {
            throw new ValidationException('Email already registered', 0, null, [
                'errors' => ['email' => ['Email address is already in use']]
            ]);
        }
        
        // Check if username already exists
        if ($this->memberRepository->findOneBy(['username' => $data['username']])) {
            throw new ValidationException('Username already taken', 0, null, [
                'errors' => ['username' => ['Username is already taken']]
            ]);
        }
        
        // Check if IPPIS number already exists
        if ($this->memberRepository->findOneBy(['ippis_no' => $data['ippis_no']])) {
            throw new ValidationException('IPPIS number already registered', 0, null, [
                'errors' => ['ippis_no' => ['IPPIS number is already registered']]
            ]);
        }
    }
    
    /**
     * Prepare member data for creation
     * 
     * @param array $validatedData
     * @return array
     */
    private function prepareMemberData(array $validatedData): array
    {
        // Hash password
        $validatedData['password_hash'] = password_hash($validatedData['password'], PASSWORD_DEFAULT);
        unset($validatedData['password']); // Remove plain password
        
        // Set default values
        $validatedData['status'] = 'Pending';
        $validatedData['join_date'] = date('Y-m-d');
        
        // Calculate expiry date based on membership type
        // This would typically be moved to a service
        $membershipStmt = $this->connection->prepare(
            "SELECT duration FROM membership_types WHERE membership_type_id = ?"
        );
        $membershipStmt->bind_param("i", $validatedData['membership_type_id']);
        $membershipStmt->execute();
        $membershipResult = $membershipStmt->get_result();
        
        if ($membershipResult->num_rows > 0) {
            $membership = $membershipResult->fetch_assoc();
            $duration = $membership['duration'];
            $validatedData['expiry_date'] = date('Y-m-d', strtotime("+{$duration} months"));
        }
        
        return $validatedData;
    }
    
    /**
     * Create member session
     * 
     * @param Member $member
     */
    private function createMemberSession(Member $member): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['member_user'] = [
            'member_id' => $member->getId(),
            'username' => $member->getUsername(),
            'first_name' => $member->getFirstName(),
            'last_name' => $member->getLastName(),
            'email' => $member->getEmail(),
            'status' => $member->getStatus(),
            'login_time' => time()
        ];
    }
}