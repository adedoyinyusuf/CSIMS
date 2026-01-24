<?php
// Backwards-compat shim for legacy views expecting AuthController
// Bridges to modern AuthenticationService under src/

require_once __DIR__ . '/../src/bootstrap.php';

use CSIMS\Core\BaseController;

class AuthController extends BaseController {
    private $authService;

    public function __construct() {
        // Initialize BaseController (Sets up DB, Session, CSRF, Security Headers)
        parent::__construct();
        
        $container = \CSIMS\bootstrap();
        $this->authService = $container->resolve(\CSIMS\Services\AuthenticationService::class);
    }

    public function isLoggedIn(): bool {
        // Prefer modern session validation
        $sessionId = $_SESSION['session_id'] ?? null;
        if ($sessionId && $this->authService->validateSession($sessionId)) {
            return true;
        }
        
        // Check for legacy admin session
        if ($this->session->get('admin_id')) {
            return true;
        }
        
        // BaseController already initializes Session, so we can check it
        if (class_exists('Session')) {
            if (method_exists($this->session, 'isLoggedIn')) {
                return (bool)$this->session->isLoggedIn();
            }
        }
        return false;
    }

    /**
     * Returns an array with 'first_name' and 'last_name' for legacy views.
     */
    public function getCurrentUser() {
        // First try the new AuthenticationService
        $user = $this->authService->getCurrentUser();
        if ($user) {
            $first = method_exists($user, 'getFirstName') ? $user->getFirstName() : '';
            $last = method_exists($user, 'getLastName') ? $user->getLastName() : '';
            return [
                'first_name' => $first,
                'last_name' => $last,
                'admin_id' => method_exists($user, 'getId') ? $user->getId() : null,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                'username' => method_exists($user, 'getUsername') ? $user->getUsername() : (method_exists($user, 'getEmail') ? $user->getEmail() : ''),
            ];
        }
        
        // Fallback to legacy admin session check
        if ($this->session->get('admin_id')) {
            return [
                'first_name' => $this->session->get('first_name') ?? '',
                'last_name' => $this->session->get('last_name') ?? '',
                'admin_id' => $this->session->get('admin_id'),
                'user_id' => $this->session->get('admin_id'), // Alias for compatibility
                'username' => $this->session->get('username') ?? '',
            ];
        }
        
        return null;
    }

    public function login(string $identifier, string $password): array {
        // First try admin login
        $adminResult = $this->adminLogin($identifier, $password);
        if ($adminResult['success']) {
            return $adminResult;
        }
        
        // Fallback to regular user login
        $result = $this->authService->login($identifier, $password);
        // Bridge modern session to legacy admin session keys for legacy views
        if (!empty($result['success']) && !empty($result['user'])) {
            $u = $result['user'];
            // Map to legacy admin structure
            $admin = [
                'admin_id' => $u['user_id'] ?? null,
                'username' => $u['username'] ?? '',
                'role' => $u['role'] ?? 'Admin',
                'first_name' => $u['first_name'] ?? '',
                'last_name' => $u['last_name'] ?? '',
            ];
            $this->session->login($admin);
        }
        return $result;
    }

    public function logout(): void {
        $sessionId = $_SESSION['session_id'] ?? null;
        if ($sessionId) {
            $this->authService->logout($sessionId);
        } else {
            // Fallback: clear PHP session
            $this->session->logout();
        }
        // Also clear legacy admin session keys for backwards compatibility
        unset($_SESSION['admin_id'], $_SESSION['username'], $_SESSION['role'], 
              $_SESSION['first_name'], $_SESSION['last_name'], $_SESSION['user_type'],
              $_SESSION['session_id']);
     }

    /**
     * Admin login using the admins table
     * REFACTORED: Uses BaseController ($this->db) instead of global $conn
     */
    public function adminLogin(string $username, string $password): array {
        // Use $this->db from BaseController (mysqli instance)
        if (!$this->db) {
             return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        // Query the admins table (Allow Login by Username OR Email)
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        
        if (!$admin) {
            return ['success' => false, 'message' => 'Debug: User not found in database (TiDB)'];
        }
        
        // Verify password
        if (!password_verify($password, $admin['password'])) {
            return ['success' => false, 'message' => 'Debug: User found, but password verification failed'];
        }
        
        // Start session and store admin data using Session wrapper
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['role'] = $admin['role'];
        $_SESSION['first_name'] = $admin['first_name'];
        $_SESSION['last_name'] = $admin['last_name'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'admin_id' => $admin['admin_id'],
                'username' => $admin['username'],
                'role' => $admin['role'],
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name']
            ]
        ];
    }

    /**
     * Check if current user is Super Admin
     * Supports both modern users (via AuthenticationService) and legacy admins
     */
    public function isSuperAdmin(): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        // Prefer modern user permission check
        try {
            $userObj = $this->authService->getCurrentUser();
            if ($userObj && method_exists($userObj, 'getId')) {
                $userId = $userObj->getId();
                if ($userId !== null) {
                    // Modern super admin indicated by 'system.admin' permission
                    return (bool)$this->authService->hasPermission($userId, 'system.admin');
                }
            }
        } catch (\Throwable $e) {
            // fall through to legacy check
        }

        // Legacy admin role check
        return isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin';
    }

    /**
     * Check if current user has a given permission key used by legacy views
     * Maps legacy permission names to modern permissions when available
     */
    public function hasPermission(string $permission): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        // Super Admin always allowed
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Attempt modern permission mapping via AuthenticationService
        try {
            $userObj = $this->authService->getCurrentUser();
            if ($userObj && method_exists($userObj, 'getId')) {
                $userId = $userObj->getId();
                if ($userId !== null) {
                    // Map legacy permission keys to modern ones
                    $map = [
                        'manage_users' => 'users.read',
                        'manage_settings' => 'settings.manage',
                        'view_financial_analytics' => 'reports.generate',
                        'view_security_dashboard' => 'system.admin',
                        'manage_two_factor' => 'settings.manage',
                        // General legacy keys used in various views
                        'view_dashboard' => 'reports.generate',
                        'manage_members' => 'members.read',
                        'manage_loans' => 'loans.read',
                        'send_messages' => 'members.read',
                        'send_notifications' => 'members.read',
                        'view_reports' => 'reports.generate',
                        'manage_profile' => 'members.read',
                    ];

                    $modernKey = $map[$permission] ?? null;
                    if ($modernKey) {
                        return (bool)$this->authService->hasPermission($userId, $modernKey);
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to legacy mapping
        }

        // Legacy permissions based on Admin role
        $role = $_SESSION['role'] ?? null;
        if ($role === 'Admin') {
            $adminPermissions = [
                'view_dashboard',
                'manage_members',
                'manage_loans',
                'send_messages',
                'send_notifications',
                'view_reports',
                'view_financial_analytics',
                'manage_profile'
            ];
            return in_array($permission, $adminPermissions, true);
        }

        // Other legacy roles: deny by default
        return false;
    }

    /**
     * Check if current user has a given role used by legacy views
     * Supports both modern users (via AuthenticationService) and legacy admins
     */
    public function hasRole(string $role): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $requested = strtolower(trim($role));

        // Prefer modern user role check
        try {
            $userObj = $this->authService->getCurrentUser();
            if ($userObj && method_exists($userObj, 'getRole')) {
                $currentRole = strtolower((string)$userObj->getRole());
                if ($requested === $currentRole) {
                    return true;
                }
                // Treat Super Admin as admin for legacy checks
                if ($requested === 'admin' && in_array($currentRole, ['admin', 'super admin'], true)) {
                    return true;
                }
                return false;
            }
        } catch (\Throwable $e) {
            // fall through to legacy session role check
        }

        // Legacy session role check
        $legacyRole = strtolower((string)($_SESSION['role'] ?? ''));
        if ($requested === $legacyRole) {
            return true;
        }
        if ($requested === 'admin' && in_array($legacyRole, ['admin', 'super admin'], true)) {
            return true;
        }
        return false;
    }
}
