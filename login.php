<?php
session_start();
include("conn.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $conn->prepare("SELECT id, username, name, email, profile_photo, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['name'];
                $_SESSION['email'] = $row['email'];
                
                // Resolve Cloudinary avatar URL
                if (!empty($row['profile_photo']) && preg_match('#^https?://#i', $row['profile_photo'])) {
                    $_SESSION['profile_photo'] = $row['profile_photo'];
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
            background-color: #2c7a7b;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            transition: background-color 0.3s ease;
        }

        body.light-theme { background-color: #2c7a7b; }
        body.dark-theme  { background-color: #1e293b; }

        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 450px;
            max-width: 100%;
            overflow: hidden;
            animation: fadeInUp 0.5s ease-out;
            transition: background 0.3s ease;
        }

        body.dark-theme .login-container { background: #1e1e2e; }

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

        .login-header {
            background-color: #2c7a7b;
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        body.dark-theme .login-header { background-color: #0f172a; }

        .login-header h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .login-form {
            padding: 35px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        body.dark-theme .form-label { color: #e5e7eb; }

        .form-label i {
            margin-right: 8px;
            color: #2c7a7b;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #1f2937;
            -webkit-text-fill-color: #1f2937;
        }

        .form-input::placeholder {
            color: #9ca3af;
            opacity: 1;
        }

        .form-input:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
        }

        body.dark-theme .form-input {
            background: #2d2d3d;
            border-color: #3d3d4d;
            color: #f3f4f6;
            -webkit-text-fill-color: #f3f4f6;
        }

        body.dark-theme .form-input::placeholder { color: #6b7280; }

        /* Autofill overrides */
        .form-input:-webkit-autofill,
        .form-input:-webkit-autofill:hover,
        .form-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #1f2937;
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        body.dark-theme .form-input:-webkit-autofill,
        body.dark-theme .form-input:-webkit-autofill:hover,
        body.dark-theme .form-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #f3f4f6;
            -webkit-box-shadow: 0 0 0px 1000px #2d2d3d inset;
        }

        .login-btn {
            width: 100%;
            background-color: #2c7a7b;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: inherit;
        }

        .login-btn:hover {
            background-color: #236162;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(44, 122, 123, 0.4);
        }

        .login-btn:active { transform: translateY(0); }

        .login-btn.loading {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }

        .login-btn.loading:hover {
            transform: none;
            box-shadow: none;
        }

        .login-btn .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        .login-btn.loading .spinner { display: inline-block; }
        .login-btn.loading .btn-text  { display: inline-block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-theme .error-message {
            background: #7f1d1d;
            color: #fecaca;
        }

        .error-message i { font-size: 18px; }

        .error-message a {
            color: inherit;
            font-weight: 600;
            text-decoration: underline;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #10b981;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-theme .success-message {
            background: #064e3b;
            color: #a7f3d0;
        }

        .success-message i { font-size: 18px; }

        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            transition: border-color 0.3s ease;
        }

        body.dark-theme .footer-links { border-top-color: #3d3d4d; }

        .footer-links a {
            color: #2c7a7b;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0 8px;
        }

        body.dark-theme .footer-links a { color: #4fd1c5; }

        .footer-links a:hover {
            color: #236162;
            text-decoration: underline;
        }

        body.dark-theme .footer-links a:hover { color: #a7f3d0; }

        .back-to-site {
            display: inline-block;
            margin-top: 15px;
            color: #9ca3af !important;
            font-size: 13px !important;
        }

        .theme-toggle-container {
            text-align: center;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
            transition: border-color 0.3s ease;
        }

        body.dark-theme .theme-toggle-container { border-top-color: #3d3d4d; }

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
            font-family: inherit;
        }

        body.dark-theme .theme-toggle-btn {
            border-color: #3d3d4d;
            color: #9ca3af;
        }

        .theme-toggle-btn:hover {
            background: rgba(44, 122, 123, 0.1);
            border-color: #2c7a7b;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-header { padding: 30px 20px; }
            .login-form   { padding: 25px 20px; }
            .login-header h2 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-vote-yea"></i>
            </div>
            <h2>Welcome Back!</h2>
            <p>Login to access your voting dashboard</p>
        </div>

        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span>Registration successful! Please login with your credentials.</span>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <div class="input-wrapper">
                        <input type="text" name="username" id="username" class="form-input" placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-input" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Login</span>
                </button>

                <div class="footer-links">
                    <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                    <span style="color:#d1d5db;">|</span>
                    <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                    <br>
                    <a href="index.php" class="back-to-site">
                        <i class="fas fa-arrow-left"></i> Back to Voting Site
                    </a>
                </div>
            </form>

            <div class="theme-toggle-container">
                <button id="themeToggleBtn" class="theme-toggle-btn" type="button">
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

        // ---------- Theme management ----------
        function setTheme(theme) {
            const themeBtn = document.getElementById('themeToggleBtn');
            if (theme === 'light') {
                document.body.classList.add('light-theme');
                document.body.classList.remove('dark-theme');
                localStorage.setItem('voting_theme', 'light');
                if (themeBtn) {
                    themeBtn.innerHTML = '<i class="fas fa-moon"></i> <span>Switch to Dark Mode</span>';
                }
            } else {
                document.body.classList.remove('light-theme');
                document.body.classList.add('dark-theme');
                localStorage.setItem('voting_theme', 'dark');
                if (themeBtn) {
                    themeBtn.innerHTML = '<i class="fas fa-sun"></i> <span>Switch to Light Mode</span>';
                }
            }
        }

        const savedTheme = localStorage.getItem('voting_theme') || 'light';
        setTheme(savedTheme);

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                const isLight = document.body.classList.contains('light-theme');
                setTheme(isLight ? 'dark' : 'light');
            });
        }

        // ---------- Form submission ----------
        if (form && loginBtn) {
            form.addEventListener('submit', function(e) {
                if (!usernameInput.value.trim()) {
                    showError('Please enter your username.');
                    usernameInput.focus();
                    e.preventDefault();
                    return false;
                }

                if (!passwordInput.value.trim()) {
                    showError('Please enter your password.');
                    passwordInput.focus();
                    e.preventDefault();
                    return false;
                }

                loginBtn.classList.add('loading');
                loginBtn.disabled = true;

                return true;
            });
        }

        function showError(message) {
            const existingError = document.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }

            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>' + message + '</span>';

            const formHeader = document.querySelector('.login-header');
            formHeader.insertAdjacentElement('afterend', errorDiv);

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
