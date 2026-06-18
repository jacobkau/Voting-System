<?php
session_start();
include("conn.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $conn->prepare("SELECT id, username, name, email, profile_photo_blob, profile_photo_type, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['name'];
                $_SESSION['email'] = $row['email'];
                
                if (!empty($row['profile_photo_blob'])) {
                    $_SESSION['profile_photo'] = 'data:image/' . $row['profile_photo_type'] . ';base64,' . base64_encode($row['profile_photo_blob']);
                } else {
                    $_SESSION['profile_photo'] = null;
                }
                
                $_SESSION['user_logged_in'] = true;

                header("Location: profile.php");
                exit();
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "Username not found. Please check your username or <a href='register.php'>register here</a>.";
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred during login. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Light Theme Support */
        body.light-theme {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        body.dark-theme {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .login-container {
            width: 100%;
            max-width: 480px;
            animation: fadeInUp 0.5s ease-out;
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
        
        .login-form-wrapper {
            background: white;
            border-radius: 24px;
            padding: 45px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: background 0.3s ease;
        }
        
        body.dark-theme .login-form-wrapper {
            background: #1e1e2e;
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 10px;
            color: #1f2937;
            font-size: 32px;
            font-weight: 700;
        }
        
        body.dark-theme .login-title {
            color: #f3f4f6;
        }
        
        .login-subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 35px;
            font-size: 14px;
        }
        
        body.dark-theme .login-subtitle {
            color: #9ca3af;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }
        
        body.dark-theme .form-label {
            color: #e5e7eb;
        }
        
        .form-label i {
            margin-right: 8px;
            color: #667eea;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
            background: white;
        }
        
        body.dark-theme .form-input {
            background: #2d2d3d;
            border-color: #3d3d4d;
            color: #f3f4f6;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .form-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .form-button:active {
            transform: translateY(0);
        }
        
        .form-button.loading {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-button.loading:hover {
            transform: none;
            box-shadow: none;
        }
        
        .form-button .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        .form-button.loading .spinner {
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #dc2626;
        }
        
        .error-message i {
            font-size: 16px;
        }
        
        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #10b981;
        }
        
        .divider {
            text-align: center;
            margin: 25px 0 20px;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }
        
        body.dark-theme .divider::before {
            background: #3d3d4d;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #9ca3af;
            font-size: 13px;
        }
        
        body.dark-theme .divider span {
            background: #1e1e2e;
            color: #6b7280;
        }
        
        .register-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .register-link a:hover {
            color: #4f46e5;
            text-decoration: underline;
        }
        
        .theme-toggle-container {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        body.dark-theme .theme-toggle-container {
            border-top-color: #3d3d4d;
        }
        
        .theme-toggle-btn {
            background: none;
            border: 1px solid #e5e7eb;
            padding: 8px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            color: #6b7280;
        }
        
        body.dark-theme .theme-toggle-btn {
            border-color: #3d3d4d;
            color: #9ca3af;
        }
        
        .theme-toggle-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }
        
        @media (max-width: 480px) {
            .login-form-wrapper {
                padding: 30px 25px;
            }
            .login-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-wrapper">
            <h1 class="login-title">
                <i class="fas fa-vote-yea"></i> Welcome Back!
            </h1>
            <p class="login-subtitle">Login to access your voting dashboard</p>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    Registration successful! Please login with your credentials.
                </div>
            <?php endif; ?>
            
            <form method="post" action="login.php" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" name="username" id="username" class="form-input" placeholder="Enter your username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="form-button" id="loginBtn">
                    <span class="spinner"></span>
                    <span><i class="fas fa-sign-in-alt"></i> Login</span>
                </button>
                
                <div class="divider">
                    <span>or</span>
                </div>
                
                <div class="register-link">
                    <a href="register.php"><i class="fas fa-user-plus"></i> Create New Account</a>
                </div>
                <div class="register-link">
                    <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                </div>
            </form>
            
            <div class="theme-toggle-container">
                <button id="themeToggleBtn" class="theme-toggle-btn">
                    <i class="fas fa-moon"></i>
                    <span>Switch to Dark Mode</span>
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        
        // Theme management
        function setTheme(theme) {
            if (theme === 'light') {
                document.body.classList.add('light-theme');
                document.body.classList.remove('dark-theme');
                localStorage.setItem('voting_theme', 'light');
                const themeBtn = document.getElementById('themeToggleBtn');
                if (themeBtn) {
                    themeBtn.innerHTML = '<i class="fas fa-sun"></i> <span>Switch to Dark Mode</span>';
                }
            } else {
                document.body.classList.remove('light-theme');
                document.body.classList.add('dark-theme');
                localStorage.setItem('voting_theme', 'dark');
                const themeBtn = document.getElementById('themeToggleBtn');
                if (themeBtn) {
                    themeBtn.innerHTML = '<i class="fas fa-moon"></i> <span>Switch to Light Mode</span>';
                }
            }
        }
        
        // Load saved theme
        const savedTheme = localStorage.getItem('voting_theme') || 'dark';
        setTheme(savedTheme);
        
        // Theme toggle button
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                const isLight = document.body.classList.contains('light-theme');
                if (isLight) {
                    setTheme('dark');
                } else {
                    setTheme('light');
                }
            });
        }
        
        // Form submission with loading state
        if (form && loginBtn) {
            form.addEventListener('submit', function(e) {
                // Validate fields
                if (!usernameInput.value.trim()) {
                    e.preventDefault();
                    showError('Please enter your username.');
                    return false;
                }
                
                if (!passwordInput.value.trim()) {
                    e.preventDefault();
                    showError('Please enter your password.');
                    return false;
                }
                
                // Show loading state
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                return true;
            });
        }
        
        function showError(message) {
            // Remove any existing error message
            const existingError = document.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            // Create new error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
            
            // Insert after the subtitle
            const subtitle = document.querySelector('.login-subtitle');
            subtitle.insertAdjacentElement('afterend', errorDiv);
            
            // Remove after 5 seconds
            setTimeout(function() {
                if (errorDiv && errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 5000);
        }
        
        // Reset loading state if user navigates back
        window.addEventListener('pageshow', function() {
            if (loginBtn) {
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
            }
        });
        
        // Remove error message when user starts typing
        if (usernameInput) {
            usernameInput.addEventListener('focus', function() {
                const error = document.querySelector('.error-message');
                if (error) error.remove();
            });
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('focus', function() {
                const error = document.querySelector('.error-message');
                if (error) error.remove();
            });
        }
    });
    </script>
</body>
</html>
