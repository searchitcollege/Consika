<?php
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Get user company type from session
    $role = $_SESSION['role'] ?? '';
    $company_type = $_SESSION['company_type'] ?? '';
    
    if ($role == 'SuperAdmin') {
        header('Location: admin/dashboard.php');
    } else {
        // Redirect to department-specific dashboard
        switch ($company_type) {
            case 'Estate':
                header('Location: modules/estate/dashboard.php');
                break;
            case 'Procurement':
                header('Location: modules/procurement/dashboard.php');
                break;
            case 'Works':
                header('Location: modules/works/dashboard.php');
                break;
            case 'Block Factory':
                header('Location: modules/blockfactory/dashboard.php');
                break;
            default:
                header('Location: admin/dashboard.php');
        }
    }
    exit();
}

$error = '';
$success = '';

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
}

// Check for logout message
if (isset($_GET['loggedout'])) {
    $success = 'You have been successfully logged out.';
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Get user from database
        $sql = "SELECT u.*, c.company_type, c.company_name FROM users u 
                LEFT JOIN companies c ON u.company_id = c.company_id 
                WHERE u.username = ? OR u.email = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // Check if account is active
                if ($row['status'] != 'Active') {
                    $error = 'Your account is not active. Please contact administrator.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['company_id'] = $row['company_id'];
                    $_SESSION['company_type'] = $row['company_type'];
                    $_SESSION['company_name'] = $row['company_name'];
                    $_SESSION['login_time'] = time();
                    
                    // Set remember me cookie if checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (86400 * 30); // 30 days
                        
                        // Store token in database
                        $sql = "INSERT INTO user_tokens (user_id, token, expiry) VALUES (?, ?, FROM_UNIXTIME(?))";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param("isi", $row['user_id'], $token, $expiry);
                        $stmt->execute();
                        
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    // Update last login
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $sql = "UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("si", $ip, $row['user_id']);
                    $stmt->execute();
                    
                    // Log activity
                    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, 'Login', 'User logged in successfully', ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("is", $row['user_id'], $ip);
                    $stmt->execute();
                    
                    // Redirect based on role
                    if ($row['role'] == 'SuperAdmin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        // Redirect to department-specific dashboard
                        switch ($row['company_type']) {
                            case 'Estate':
                                header('Location: modules/estate/dashboard.php');
                                break;
                            case 'Procurement':
                                header('Location: modules/procurement/dashboard.php');
                                break;
                            case 'Works':
                                header('Location: modules/works/dashboard.php');
                                break;
                            case 'Block Factory':
                                header('Location: modules/blockfactory/dashboard.php');
                                break;
                            default:
                                header('Location: admin/dashboard.php');
                        }
                    }
                    exit();
                }
            } else {
                $error = 'Invalid password';
                // Log failed attempt
                $sql = "INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ss", $username, $_SERVER['REMOTE_ADDR']);
                $stmt->execute();
            }
        } else {
            $error = 'Username not found';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo defined('APP_NAME') ? APP_NAME : 'Company Management System'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1e1e2f;
            --light-color: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }
        
        .form-control {
            height: 50px;
            padding-left: 45px;
            border: 2px solid #e1e5f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            height: 50px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .alert-success {
            background-color: #def7ec;
            color: #0e9f6e;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 13px;
        }
        
        .footer-links a {
            color: #666;
            text-decoration: none;
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
        }
        
        .divider {
            margin: 0 8px;
        }
        
        .company-badge {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .company-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo defined('APP_NAME') ? APP_NAME : 'Company Management System'; ?></h1>
                <p>Manage all your companies in one place</p>
                <div class="company-badge">
                    <div class="company-icon" title="Estate">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="company-icon" title="Procurement">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="company-icon" title="Works">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="company-icon" title="Block Factory">
                        <i class="fas fa-cubes"></i>
                    </div>
                </div>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <i class="fas fa-user"></i>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Username or Email" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Password" required>
                    </div>
                    
                    <div class="remember-forgot">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-password">
                            <i class="fas fa-key me-1"></i>Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginBtn">
                        <span class="btn-text">Sign In</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </form>
                
                <div class="footer-links">
                    <span>&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? APP_NAME : 'Company Management System'; ?></span>
                    <span class="divider">|</span>
                    <a href="#">Privacy Policy</a>
                    <span class="divider">|</span>
                    <a href="#">Terms of Use</a>
                </div>
                
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Demo Credentials: admin / Admin@123
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Form submission with loading state
            $('#loginForm').on('submit', function() {
                $('#loginBtn').prop('disabled', true);
                $('.btn-text').text('Signing in...');
                $('.spinner-border').removeClass('d-none');
                return true;
            });
        });
        
        // Prevent double submission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>