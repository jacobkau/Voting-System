<?php
session_start();
include("conn.php");

// Map $conn to $db if your conn.php file sets up the variable as $db
if (!isset($conn) && isset($db)) {
    $conn = $db;
}

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: main.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        try {
            // 1. Prepare query using the PDO format (targeting 'users' table)
            $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = :username");
            
            // 2. Execute query by passing the value inside an array
            $stmt->execute([':username' => $username]);
            
            // 3. Fetch the row entry cleanly
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $admin_id = $row["id"];
                $db_username = $row["username"];
                $db_password = $row["password"];

                // 4. Verify the secure hashed password
                if (password_verify($password, $db_password)) {
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['username'] = $db_username;

                    try {
                        // 5. Log login event using secure PDO parameters (Create table if missing)
                        $conn->exec("CREATE TABLE IF NOT EXISTS event_log (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            username VARCHAR(50),
                            event_type VARCHAR(50),
                            event_description VARCHAR(255),
                            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        );");

                        $eventQuery = "INSERT INTO event_log (username, event_type, event_description) VALUES (:user, :type, :desc)";
                        $eventStmt = $conn->prepare($eventQuery);
                        $eventStmt->execute([
                            ':user' => $db_username,
                            ':type' => "Admin Login",
                            ':desc' => "Admin logged in successfully."
                        ]);
                    } catch (Exception $logError) {
                        // If logging fails, we don't block the user from accessing the main dashboard
                        error_log("Event Log Error: " . $logError->getMessage());
                    }

                    header("Location: main.php");
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "Invalid username.";
            }
        } catch (Exception $dbException) {
            error_log("Login Database Error: " . $dbException->getMessage());
            $error = "An error occurred during login. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob Witty">
    <link rel="icon" href="../logo.jpg" type="image/x-icon">
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
        
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 450px;
            max-width: 100%;
            overflow: hidden;
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
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
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
        
        .form-label i {
            margin-right: 8px;
            color: #667eea;
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
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
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
        
        .login-btn.loading .spinner {
            display: inline-block;
        }
        
        .login-btn.loading .btn-text {
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
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
        
        .error-message i {
            font-size: 18px;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .back-to-site {
            display: inline-block;
            margin-top: 15px;
            color: #9ca3af !important;
            font-size: 13px !important;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-header {
                padding: 30px 20px;
            }
            
            .login-form {
                padding: 25px 20px;
            }
            
            .login-header h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Admin Portal</h2>
            <p>Secure access to voting management system</p>
        </div>
        
        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" action="admin_login.php" id="loginForm">
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
                    <a href="../index.php" class="back-to-site">
                        <i class="fas fa-arrow-left"></i> Back to Voting Site
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        
        if (form && loginBtn) {
            form.addEventListener('submit', function(e) {
                // Validate fields
                let hasError = false;
                
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
                
                // Show loading state
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                // The form will submit normally
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
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>' + message + '</span>';
            
            // Insert after the form header
            const formHeader = document.querySelector('.login-header');
            const loginForm = document.querySelector('.login-form');
            formHeader.insertAdjacentElement('afterend', errorDiv);
            
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
