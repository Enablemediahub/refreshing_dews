<?php
require_once '../includes/config.php';
require_once '../includes/db-connection.php'; // This must come before functions
require_once '../includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if $conn is properly initialized
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        
        if ($stmt) {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Set session variables
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_username'] = $row['username'];
                    $_SESSION['admin_logged_in'] = true;
                    
                    // Log the login action (check if function exists)
                    if (function_exists('logAdminAction')) {
                        logAdminAction('login', 'Admin logged in successfully');
                    }
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password';
                    if (function_exists('logAdminAction')) {
                        logAdminAction('failed_login', 'Failed login attempt for username: ' . $username);
                    }
                }
            } else {
                $error = 'User not found';
                if (function_exists('logAdminAction')) {
                    logAdminAction('failed_login', 'Failed login attempt - user not found: ' . $username);
                }
            }
            
            $stmt->close();
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}

// Get site settings for branding
$site_title = 'Refreshing Dews';
$site_logo = '../assets/logo/refreshing-dews-logo.png';

// Try to get settings from database if available
if (isset($conn) && !$conn->connect_error && function_exists('getSetting')) {
    $site_title = getSetting('site_title', 'Refreshing Dews');
    $site_logo = getSetting('site_logo', '../assets/logo/refreshing-dews-logo.png');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo htmlspecialchars($site_title); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background */
        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .bg-bubbles li {
            position: absolute;
            list-style: none;
            display: block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.15);
            bottom: -160px;
            animation: square 25s infinite;
            transition-timing-function: linear;
            border-radius: 50%;
        }
        
        .bg-bubbles li:nth-child(1) {
            left: 10%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
            animation-duration: 12s;
        }
        
        .bg-bubbles li:nth-child(2) {
            left: 20%;
            width: 20px;
            height: 20px;
            animation-delay: 2s;
            animation-duration: 20s;
        }
        
        .bg-bubbles li:nth-child(3) {
            left: 25%;
            width: 60px;
            height: 60px;
            animation-delay: 4s;
            animation-duration: 15s;
        }
        
        .bg-bubbles li:nth-child(4) {
            left: 40%;
            width: 40px;
            height: 40px;
            animation-delay: 0s;
            animation-duration: 18s;
        }
        
        .bg-bubbles li:nth-child(5) {
            left: 70%;
            width: 50px;
            height: 50px;
            animation-delay: 0s;
            animation-duration: 14s;
        }
        
        .bg-bubbles li:nth-child(6) {
            left: 80%;
            width: 30px;
            height: 30px;
            animation-delay: 3s;
            animation-duration: 22s;
        }
        
        .bg-bubbles li:nth-child(7) {
            left: 32%;
            width: 100px;
            height: 100px;
            animation-delay: 7s;
            animation-duration: 25s;
        }
        
        .bg-bubbles li:nth-child(8) {
            left: 55%;
            width: 70px;
            height: 70px;
            animation-delay: 1s;
            animation-duration: 17s;
        }
        
        .bg-bubbles li:nth-child(9) {
            left: 15%;
            width: 45px;
            height: 45px;
            animation-delay: 5s;
            animation-duration: 19s;
        }
        
        .bg-bubbles li:nth-child(10) {
            left: 90%;
            width: 90px;
            height: 90px;
            animation-delay: 9s;
            animation-duration: 21s;
        }
        
        @keyframes square {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }
        
        /* Login Container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            margin: 20px;
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header img {
            max-width: 120px;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .login-header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3a3;
            border: 1px solid #cfc;
        }
        
        .alert i {
            font-size: 18px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            color: #999;
            font-size: 16px;
            transition: color 0.3s;
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4a7c59;
            box-shadow: 0 0 0 4px rgba(74, 124, 89, 0.1);
        }
        
        .form-control:focus + .input-icon {
            color: #4a7c59;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            color: #999;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s;
            z-index: 1;
        }
        
        .password-toggle:hover {
            color: #4a7c59;
        }
        
        /* Options Row */
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #4a7c59;
        }
        
        .remember-me span {
            color: #666;
            font-size: 14px;
        }
        
        .forgot-password {
            color: #4a7c59;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #2c4a3b;
            text-decoration: underline;
        }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #4a7c59, #2c4a3b);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(74, 124, 89, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            font-size: 18px;
            transition: transform 0.3s;
        }
        
        .btn-login:hover i {
            transform: translateX(5px);
        }
        
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn-login.loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Divider */
        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: calc(50% - 70px);
            height: 1px;
            background: #e0e0e0;
        }
        
        .divider::before {
            left: 0;
        }
        
        .divider::after {
            right: 0;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            color: #999;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Social Login */
        .social-login {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .social-btn {
            width: 45px;
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 18px;
        }
        
        .social-btn:hover {
            border-color: #4a7c59;
            color: #4a7c59;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 25px;
            color: #999;
            font-size: 13px;
        }
        
        .login-footer a {
            color: #4a7c59;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        /* Back to Site Link */
        .back-to-site {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-site a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.8;
            transition: opacity 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-to-site a:hover {
            opacity: 1;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .login-header img {
                max-width: 100px;
            }
            
            .login-header h1 {
                font-size: 20px;
            }
            
            .options-row {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Bubbles -->
    <ul class="bg-bubbles">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_title); ?>">
                <h1>Admin Login</h1>
                <p>Welcome back! Please login to your account.</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username or email"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               required 
                               autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>
                
                <div class="options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span>Login to Dashboard</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <div class="login-footer">
                <p>Default admin credentials: admin / admin123</p>
                <p style="font-size: 11px; margin-top: 5px; color: #f00;">Please change after first login</p>
                <p style="font-size: 11px; margin-top: 12px; color: #888;">Designed and Developed by <strong>DALE QUIST</strong> [Enable Technologies]</p>
            </div>
        </div>
        
        <div class="back-to-site">
            <a href="../index.php">
                <i class="fas fa-arrow-left"></i>
                Back to Website
            </a>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Form submission animation
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        
        loginForm.addEventListener('submit', function(e) {
            loginBtn.classList.add('loading');
            loginBtn.innerHTML = '<span>Logging in...</span><i class="fas fa-spinner"></i>';
        });
        
        // Remember me functionality
        const rememberCheckbox = document.getElementById('remember');
        const usernameInput = document.getElementById('username');
        
        // Check if there's a saved username
        const savedUsername = localStorage.getItem('rememberedUsername');
        if (savedUsername) {
            usernameInput.value = savedUsername;
            rememberCheckbox.checked = true;
        }
        
        // Save username when form is submitted with remember me checked
        loginForm.addEventListener('submit', function() {
            if (rememberCheckbox.checked) {
                localStorage.setItem('rememberedUsername', usernameInput.value);
            } else {
                localStorage.removeItem('rememberedUsername');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Add floating label effect
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('.input-icon').style.color = '#4a7c59';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.querySelector('.input-icon').style.color = '#999';
            });
        });
        
        // Prevent multiple form submissions
        let formSubmitted = false;
        loginForm.addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return;
            }
            formSubmitted = true;
        });
        
        // Add keyboard shortcut (Ctrl+Enter) to submit form
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                loginForm.submit();
            }
        });
    </script>
</body>
</html>