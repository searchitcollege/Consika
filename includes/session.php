<?php
// Load configuration FIRST before session starts
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class SessionManager {
    private $db;
    private $user = null;
    private $logged_in = false;
    private $login_time = null;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->checkLogin();
        $this->checkTimeout();
    }
    
    // Check if user is logged in
    private function checkLogin() {
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $sql = "SELECT u.*, c.company_name, c.company_type 
                    FROM users u 
                    LEFT JOIN companies c ON u.company_id = c.company_id 
                    WHERE u.user_id = ? AND u.status = 'Active'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->user = $row;
                $this->logged_in = true;
                $this->login_time = $_SESSION['login_time'] ?? time();
                
                // Update last activity
                $_SESSION['last_activity'] = time();
            } else {
                $this->logout();
            }
        }
    }
    
    // Check session timeout
    private function checkTimeout() {
        if ($this->logged_in && defined('SESSION_TIMEOUT') && SESSION_TIMEOUT > 0) {
            if (isset($_SESSION['last_activity'])) {
                $inactive = time() - $_SESSION['last_activity'];
                if ($inactive >= SESSION_TIMEOUT) {
                    $this->logout();
                    redirect('login.php?timeout=1');
                }
            }
        }
    }
    
    // Login user
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? AND status = 'Active'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // Check login attempts
                if ($this->isAccountLocked($row['user_id'])) {
                    return ['success' => false, 'message' => 'Account is locked. Try again later.'];
                }
                
                // Set session
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['company_id'] = $row['company_id'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Update last login
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $update = "UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?";
                $stmt2 = $this->db->prepare($update);
                $stmt2->bind_param("si", $ip, $row['user_id']);
                $stmt2->execute();
                
                // Reset login attempts
                $this->resetLoginAttempts($row['user_id']);
                
                // Log activity using function from functions.php
                if (function_exists('log_activity')) {
                    log_activity($row['user_id'], 'Login', 'User logged in successfully');
                }
                
                return ['success' => true, 'user' => $row];
            } else {
                // Record failed attempt
                $this->recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Invalid password'];
            }
        }
        
        return ['success' => false, 'message' => 'Username not found'];
    }
    
    // Logout user
    public function logout() {
        if (isset($_SESSION['user_id']) && function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'Logout', 'User logged out');
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        $this->logged_in = false;
        $this->user = null;
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return $this->logged_in;
    }
    
    // Require login
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            redirect('login.php');
        }
    }
    
    // Get current user
    public function getCurrentUser() {
        return $this->user;
    }
    
    // Get user role
    public function getRole() {
        return $this->user['role'] ?? null;
    }
    
    // Get company ID
    public function getCompanyId() {
        return $this->user['company_id'] ?? null;
    }
    
    // Get company type
    public function getCompanyType() {
        return $this->user['company_type'] ?? null;
    }
    
    // Check if user has permission
    public function hasPermission($module, $action) {
        if (!$this->logged_in) {
            return false;
        }
        
        // SuperAdmin has all permissions
        if ($this->user['role'] == 'SuperAdmin') {
            return true;
        }
        
        // Check company admin has all permissions for their company
        if ($this->user['role'] == 'CompanyAdmin' && $this->user['company_id'] == $module) {
            return true;
        }
        
        // Check specific permissions
        $sql = "SELECT * FROM user_permissions WHERE user_id = ? AND module_name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $this->user['user_id'], $module);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $permission_field = 'can_' . $action;
            return isset($row[$permission_field]) && $row[$permission_field] == 1;
        }
        
        return false;
    }
    
    // Check if user can access company
    public function canAccessCompany($company_id) {
        if (!$this->logged_in) {
            return false;
        }
        
        if ($this->user['role'] == 'SuperAdmin') {
            return true;
        }
        
        return $this->user['company_id'] == $company_id;
    }
    
    // Get accessible companies
    public function getAccessibleCompanies() {
        if (!$this->logged_in) {
            return [];
        }
        
        if ($this->user['role'] == 'SuperAdmin') {
            $sql = "SELECT * FROM companies WHERE status = 'Active' ORDER BY company_name";
            $result = $this->db->query($sql);
            $companies = [];
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row;
            }
            return $companies;
        }
        
        $sql = "SELECT * FROM companies WHERE company_id = ? AND status = 'Active'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->user['company_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $companies = [];
        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }
        return $companies;
    }
    
    // Record failed login attempt
    private function recordFailedAttempt($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $sql = "INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();
    }
    
    // Check if account is locked
    private function isAccountLocked($user_id) {
        if (!defined('MAX_LOGIN_ATTEMPTS') || MAX_LOGIN_ATTEMPTS <= 0) {
            return false;
        }
        
        if (!defined('LOCKOUT_TIME')) {
            return false;
        }
        
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE user_id = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $user_id, LOCKOUT_TIME);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['attempts'] >= MAX_LOGIN_ATTEMPTS;
    }
    
    // Reset login attempts
    private function resetLoginAttempts($user_id) {
        $sql = "DELETE FROM login_attempts WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    // Get session data
    public function getSessionData() {
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? null,
            'login_time' => $this->login_time,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ];
    }
    
    // Refresh session
    public function refreshSession() {
        $_SESSION['last_activity'] = time();
    }
}

// Create global session instance
$session = new SessionManager();

// REMOVED HELPER FUNCTIONS - These are now in functions.php
// The following functions have been moved to functions.php:
// - isLoggedIn()
// - currentUser()
// - hasPermission()
// - requirePermission()
?>