<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Include database connection
include("conn.php");

// Load mail configuration if exists
if (file_exists(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
}

// Load SendGrid autoloader if exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Handle password reset via token
if (isset($_GET['token']) && !isset($_POST['ajax'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $conn->prepare("SELECT user_id, expiry FROM password_reset_tokens WHERE token = ? AND expiry > NOW()");
        $stmt->execute([$token]);
        
        if ($stmt->rowCount() == 1) {
            $resetData = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $resetData['user_id'];
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Reset Password | Voting System</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }

                    body {
                        font-family: 'Inter', sans-serif;
                        background-color: #2c7a7b;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                        transition: background-color 0.3s ease;
                    }

                    body.light-theme { background-color: #2c7a7b; }
                    body.dark-theme  { background-color: #1e293b; }

                    .container {
                        max-width: 500px;
                        width: 100%;
                        background: white;
                        padding: 45px 40px;
                        border-radius: 24px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        animation: fadeInUp 0.5s ease-out;
                        transition: background 0.3s ease;
                    }

                    body.dark-theme .container { background: #1e1e2e; }

                    @keyframes fadeInUp {
                        from { opacity: 0; transform: translateY(30px); }
                        to   { opacity: 1; transform: translateY(0); }
                    }

                    h1 {
                        text-align: center;
                        color: #1f2937;
                        margin-bottom: 10px;
                        font-size: 28px;
                        font-weight: 700;
                        transition: color 0.3s ease;
                    }

                    body.dark-theme h1 { color: #f3f4f6; }

                    h1 i {
                        color: #2c7a7b;
                        margin-right: 10px;
                    }

                    .subtitle {
                        text-align: center;
                        color: #6b7280;
                        margin-bottom: 35px;
                        font-size: 14px;
                        transition: color 0.3s ease;
                    }

                    body.dark-theme .subtitle { color: #9ca3af; }

                    .message {
                        padding: 14px 18px;
                        border-radius: 12px;
                        margin-bottom: 20px;
                        font-weight: 500;
                        font-size: 14px;
                        display: none;
                        align-items: center;
                        gap: 10px;
                    }

                    .message.show { display: flex; }

                    .message.success {
                        background: #d1fae5;
                        color: #065f46;
                        border-left: 4px solid #10b981;
                    }

                    body.dark-theme .message.success {
                        background: #064e3b;
                        color: #a7f3d0;
                    }

                    .message.error {
                        background: #fee2e2;
                        color: #991b1b;
                        border-left: 4px solid #dc2626;
                    }

                    body.dark-theme .message.error {
                        background: #7f1d1d;
                        color: #fecaca;
                    }

                    .form-group { margin-bottom: 20px; }

                    label {
                        display: block;
                        font-weight: 500;
                        color: #374151;
                        margin-bottom: 8px;
                        font-size: 14px;
                        transition: color 0.3s ease;
                    }

                    body.dark-theme label { color: #e5e7eb; }

                    label i {
                        color: #2c7a7b;
                        margin-right: 8px;
                    }

                    input[type="password"] {
                        width: 100%;
                        padding: 14px 16px;
                        border: 2px solid #e5e7eb;
                        border-radius: 12px;
                        font-size: 15px;
                        font-family: inherit;
                        transition: all 0.3s;
                        background: #ffffff;
                        color: #1f2937;
                        -webkit-text-fill-color: #1f2937;
                    }

                    input[type="password"]::placeholder {
                        color: #9ca3af;
                        opacity: 1;
                    }

                    input[type="password"]:focus {
                        outline: none;
                        border-color: #2c7a7b;
                        box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
                    }

                    body.dark-theme input[type="password"] {
                        background: #2d2d3d;
                        border-color: #3d3d4d;
                        color: #f3f4f6;
                        -webkit-text-fill-color: #f3f4f6;
                    }

                    body.dark-theme input[type="password"]::placeholder {
                        color: #6b7280;
                    }

                    input:-webkit-autofill,
                    input:-webkit-autofill:hover,
                    input:-webkit-autofill:focus {
                        -webkit-text-fill-color: #1f2937;
                        -webkit-box-shadow: 0 0 0px 1000px #ffffff inset;
                        transition: background-color 5000s ease-in-out 0s;
                    }

                    body.dark-theme input:-webkit-autofill,
                    body.dark-theme input:-webkit-autofill:hover,
                    body.dark-theme input:-webkit-autofill:focus {
                        -webkit-text-fill-color: #f3f4f6;
                        -webkit-box-shadow: 0 0 0px 1000px #2d2d3d inset;
                    }

                    button[type="submit"] {
                        width: 100%;
                        padding: 14px;
                        background-color: #2c7a7b;
                        color: white;
                        border: none;
                        border-radius: 12px;
                        font-size: 16px;
                        font-weight: 600;
                        font-family: inherit;
                        cursor: pointer;
                        transition: all 0.3s;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                    }

                    button[type="submit"]:hover:not(:disabled) {
                        background-color: #236162;
                        transform: translateY(-2px);
                        box-shadow: 0 5px 20px rgba(44, 122, 123, 0.4);
                    }

                    button[type="submit"]:disabled {
                        opacity: 0.7;
                        cursor: not-allowed;
                        transform: none;
                    }

                    .spinner {
                        display: none;
                        width: 20px;
                        height: 20px;
                        border: 2px solid rgba(255,255,255,0.3);
                        border-radius: 50%;
                        border-top-color: white;
                        animation: spin 0.8s linear infinite;
                    }

                    button.loading .spinner { display: inline-block; }

                    @keyframes spin { to { transform: rotate(360deg); } }

                    .password-strength {
                        margin-top: 10px;
                        padding: 8px 12px;
                        border-radius: 8px;
                        display: none;
                        font-size: 13px;
                        font-weight: 600;
                        text-align: center;
                    }

                    .strength-weak { background: #fee2e2; color: #991b1b; }
                    .strength-medium { background: #fef3c7; color: #92400e; }
                    .strength-strong { background: #d1fae5; color: #065f46; }

                    body.dark-theme .strength-weak { background: #7f1d1d; color: #fecaca; }
                    body.dark-theme .strength-medium { background: #78350f; color: #fde68a; }
                    body.dark-theme .strength-strong { background: #064e3b; color: #a7f3d0; }

                    .links {
                        text-align: center;
                        margin-top: 25px;
                        padding-top: 20px;
                        border-top: 1px solid #e5e7eb;
                        font-size: 14px;
                        color: #6b7280;
                        transition: all 0.3s ease;
                    }

                    body.dark-theme .links {
                        border-top-color: #3d3d4d;
                        color: #9ca3af;
                    }

                    .links a {
                        color: #2c7a7b;
                        text-decoration: none;
                        margin: 0 10px;
                        font-weight: 500;
                        transition: color 0.3s;
                    }

                    .links a:hover {
                        color: #236162;
                        text-decoration: underline;
                    }

                    small {
                        color: #9ca3af;
                        font-size: 12px;
                        display: block;
                        margin-top: 6px;
                    }

                    body.dark-theme small { color: #6b7280; }

                    .theme-toggle-container {
                        text-align: center;
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #e5e7eb;
                        transition: all 0.3s ease;
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

                    @media (max-width: 480px) {
                        .container { padding: 30px 25px; }
                        h1 { font-size: 22px; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1><i class="fas fa-key"></i> Reset Password</h1>
                    <p class="subtitle">Enter your new password below</p>

                    <div id="messageBox" class="message">
                        <i class="fas"></i>
                        <span id="messageText"></span>
                    </div>

                    <form id="resetForm">
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> New Password</label>
                            <input type="password" name="password" id="password" placeholder="Enter new password" required minlength="6">
                            <small>Minimum 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                        </div>

                        <div id="strengthIndicator" class="password-strength"></div>

                        <button type="submit" id="submitBtn">
                            <span class="spinner"></span>
                            <span><i class="fas fa-sync-alt"></i> Reset Password</span>
                        </button>
                    </form>

                    <div class="links">
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <span>|</span>
                        <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                    </div>

                    <div class="theme-toggle-container">
                        <button id="themeToggleBtn" class="theme-toggle-btn" type="button">
                            <i class="fas fa-moon"></i>
                            <span>Switch to Dark Mode</span>
                        </button>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // ---------- Theme ----------
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

                    // ---------- Password strength ----------
                    const passwordField = document.getElementById('password');
                    if (passwordField) {
                        passwordField.addEventListener('input', function() {
                            const password = this.value;
                            const indicator = document.getElementById('strengthIndicator');

                            if (password.length === 0) {
                                indicator.style.display = 'none';
                                return;
                            }

                            let strength = 'weak';
                            let text = 'Weak';

                            if (password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                                strength = 'strong';
                                text = 'Strong';
                            } else if (password.length >= 6 && (/[A-Z]/.test(password) || /[0-9]/.test(password))) {
                                strength = 'medium';
                                text = 'Medium';
                            }

                            indicator.style.display = 'block';
                            indicator.className = 'password-strength strength-' + strength;
                            indicator.textContent = 'Password Strength: ' + text;
                        });
                    }

                    // ---------- Form submission ----------
                    document.getElementById('resetForm').addEventListener('submit', async function(e) {
                        e.preventDefault();

                        const password = document.getElementById('password');
                        const confirm = document.getElementById('confirm_password');
                        const submitBtn = document.getElementById('submitBtn');

                        if (password.value.length < 6) {
                            showMessage('Password must be at least 6 characters.', 'error');
                            return;
                        }

                        if (password.value !== confirm.value) {
                            showMessage('Passwords do not match.', 'error');
                            return;
                        }

                        submitBtn.classList.add('loading');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner"></span> <span>Resetting...</span>';

                        try {
                            const formData = new FormData(this);
                            formData.append('ajax', '1');

                            const response = await fetch('test_reset.php', {
                                method: 'POST',
                                body: formData
                            });

                            const data = await response.json();
                            showMessage(data.message, data.success ? 'success' : 'error');

                            if (data.success) {
                                setTimeout(() => {
                                    window.location.href = 'login.php';
                                }, 3000);
                            }
                        } catch (error) {
                            showMessage('An error occurred. Please try again.', 'error');
                        }

                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span><i class="fas fa-sync-alt"></i> Reset Password</span>';
                    });

                    function showMessage(text, type) {
                        const messageBox = document.getElementById('messageBox');
                        const messageText = document.getElementById('messageText');
                        const icon = messageBox.querySelector('i');

                        messageText.textContent = text;
                        messageBox.className = 'message show ' + type;
                        icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');

                        setTimeout(() => {
                            messageBox.classList.remove('show');
                        }, 10000);
                    }
                });
                </script>
            </body>
            </html>
            <?php
            exit();
        } else {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Invalid Token | Voting System</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }

                    body {
                        font-family: 'Inter', sans-serif;
                        background-color: #2c7a7b;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                        transition: background-color 0.3s ease;
                    }

                    body.light-theme { background-color: #2c7a7b; }
                    body.dark-theme  { background-color: #1e293b; }

                    .container {
                        max-width: 500px;
                        width: 100%;
                        background: white;
                        padding: 45px 40px;
                        border-radius: 24px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        text-align: center;
                        animation: fadeInUp 0.5s ease-out;
                        transition: background 0.3s ease;
                    }

                    body.dark-theme .container { background: #1e1e2e; }

                    @keyframes fadeInUp {
                        from { opacity: 0; transform: translateY(30px); }
                        to   { opacity: 1; transform: translateY(0); }
                    }

                    .icon {
                        font-size: 60px;
                        color: #dc2626;
                        margin-bottom: 20px;
                    }

                    h1 {
                        color: #1f2937;
                        font-size: 24px;
                        font-weight: 700;
                        margin-bottom: 15px;
                        transition: color 0.3s ease;
                    }

                    body.dark-theme h1 { color: #f3f4f6; }

                    p {
                        color: #6b7280;
                        font-size: 14px;
                        line-height: 1.6;
                        margin-bottom: 10px;
                        transition: color 0.3s ease;
                    }

                    body.dark-theme p { color: #9ca3af; }

                    .links {
                        margin-top: 25px;
                        padding-top: 20px;
                        border-top: 1px solid #e5e7eb;
                        font-size: 14px;
                        transition: all 0.3s ease;
                    }

                    body.dark-theme .links { border-top-color: #3d3d4d; }

                    .links a {
                        color: #2c7a7b;
                        text-decoration: none;
                        margin: 0 10px;
                        font-weight: 500;
                        transition: color 0.3s;
                    }

                    .links a:hover {
                        color: #236162;
                        text-decoration: underline;
                    }

                    .theme-toggle-container {
                        text-align: center;
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #e5e7eb;
                        transition: all 0.3s ease;
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

                    @media (max-width: 480px) {
                        .container { padding: 30px 25px; }
                        h1 { font-size: 20px; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                    <h1>Invalid or Expired Token</h1>
                    <p>The password reset link is invalid or has expired.</p>
                    <p>Please request a new password reset link.</p>

                    <div class="links">
                        <a href="forgot_password.php"><i class="fas fa-envelope"></i> Request New Link</a>
                        <span>|</span>
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    </div>

                    <div class="theme-toggle-container">
                        <button id="themeToggleBtn" class="theme-toggle-btn" type="button">
                            <i class="fas fa-moon"></i>
                            <span>Switch to Dark Mode</span>
                        </button>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
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
                });
                </script>
            </body>
            </html>
            <?php
            exit();
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Handle AJAX requests (password reset or test email)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // ---------- Password reset submission ----------
    if (isset($_POST['user_id']) && isset($_POST['token']) && isset($_POST['password'])) {
        $userId = intval($_POST['user_id']);
        $token = $_POST['token'];
        $password = $_POST['password'];
        
        try {
            $stmt = $conn->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND user_id = ? AND expiry > NOW()");
            $stmt->execute([$token, $userId]);
            
            if ($stmt->rowCount() == 1) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $userId]);
                
                $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                $deleteStmt->execute([$token]);
                
                echo json_encode(['success' => true, 'message' => 'Password reset successful. Redirecting to login...']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired token. Please request a new reset link.']);
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
        exit();
    }
    
    // ---------- Test email ----------
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        $response = ['success' => false, 'message' => ''];
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format.';
            echo json_encode($response);
            exit();
        }
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $reset_link = "$protocol://$host/test_reset.php?token=test_token_123";
        
        $emailSent = false;
        $method = 'None';
        
        // Try SendGrid
        $sendgridApiKey = getenv('SENDGRID_API_KEY');
        if ($sendgridApiKey && class_exists('SendGrid\Mail\Mail')) {
            try {
                $fromEmail = getenv('FROM_EMAIL') ?: 'noreply@' . $_SERVER['HTTP_HOST'];
                $fromName = getenv('FROM_NAME') ?: 'Voting System';
                
                $emailObj = new \SendGrid\Mail\Mail();
                $emailObj->setFrom($fromEmail, $fromName);
                $emailObj->setSubject("Test Email from Voting System");
                $emailObj->addTo($email);
                $emailObj->addContent("text/html", "
                    <h2>Test Email Successful</h2>
                    <p>This is a test email from your Voting System.</p>
                    <p>If you received this, your email configuration is working correctly.</p>
                    <p><a href='$reset_link'>Test Password Reset Link</a></p>
                ");
                
                $sendgrid = new \SendGrid($sendgridApiKey);
                $response = $sendgrid->send($emailObj);
                
                if ($response->statusCode() == 202) {
                    $emailSent = true;
                    $method = 'SendGrid';
                }
            } catch (Exception $e) {
                error_log("SendGrid error: " . $e->getMessage());
            }
        }
        
        // Try Resend
        if (!$emailSent) {
            $resendApiKey = getenv('RESEND_API_KEY');
            if ($resendApiKey) {
                try {
                    $fromEmail = getenv('FROM_EMAIL') ?: 'onboarding@resend.dev';
                    
                    $data = [
                        'from' => $fromEmail,
                        'to' => [$email],
                        'subject' => 'Test Email from Voting System',
                        'html' => "
                            <h2>Test Email Successful</h2>
                            <p>This is a test email from your Voting System.</p>
                            <p>If you received this, your email configuration is working correctly.</p>
                            <p><a href='$reset_link'>Test Password Reset Link</a></p>
                        "
                    ];
                    
                    $ch = curl_init('https://api.resend.com/emails');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $resendApiKey,
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode == 200) {
                        $emailSent = true;
                        $method = 'Resend';
                    }
                } catch (Exception $e) {
                    error_log("Resend error: " . $e->getMessage());
                }
            }
        }
        
        if ($emailSent) {
            $response['success'] = true;
            $response['message'] = "Test email sent successfully via $method. Check your inbox and spam folder.";
        } else {
            $response['message'] = "Failed to send test email. Check your API keys and from email configuration.";
        }
        
        echo json_encode($response);
        exit();
    }
}

// Display test page (GET request)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Email System | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #2c7a7b;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s ease;
        }

        body.light-theme { background-color: #2c7a7b; }
        body.dark-theme  { background-color: #1e293b; }

        .container {
            max-width: 700px;
            width: 100%;
            background: white;
            padding: 45px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeInUp 0.5s ease-out;
            transition: background 0.3s ease;
        }

        body.dark-theme .container { background: #1e1e2e; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        h1 {
            text-align: center;
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 700;
            transition: color 0.3s ease;
        }

        body.dark-theme h1 { color: #f3f4f6; }

        h1 i {
            color: #2c7a7b;
            margin-right: 10px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 35px;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        body.dark-theme .subtitle { color: #9ca3af; }

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0 30px;
        }

        .status-card {
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            text-align: center;
            transition: all 0.3s ease;
        }

        body.dark-theme .status-card { border-color: #3d3d4d; }

        .status-card.success { border-color: #10b981; background: #d1fae5; }
        .status-card.error   { border-color: #dc2626; background: #fee2e2; }

        body.dark-theme .status-card.success { background: #064e3b; border-color: #10b981; }
        body.dark-theme .status-card.error   { background: #7f1d1d; border-color: #dc2626; }

        .status-card i { font-size: 22px; margin-bottom: 6px; }
        .status-card.success i { color: #065f46; }
        .status-card.error i { color: #991b1b; }
        body.dark-theme .status-card.success i { color: #a7f3d0; }
        body.dark-theme .status-card.error i { color: #fecaca; }

        .status-card .label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        body.dark-theme .status-card .label { color: #9ca3af; }

        .status-card .value {
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
        }

        body.dark-theme .status-card .value { color: #f3f4f6; }

        .message {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .message.show { display: flex; }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        body.dark-theme .message.success {
            background: #064e3b;
            color: #a7f3d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        body.dark-theme .message.error {
            background: #7f1d1d;
            color: #fecaca;
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        body.dark-theme label { color: #e5e7eb; }

        label i {
            color: #2c7a7b;
            margin-right: 8px;
        }

        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
            background: #ffffff;
            color: #1f2937;
            -webkit-text-fill-color: #1f2937;
        }

        input[type="email"]::placeholder {
            color: #9ca3af;
            opacity: 1;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
        }

        body.dark-theme input[type="email"] {
            background: #2d2d3d;
            border-color: #3d3d4d;
            color: #f3f4f6;
            -webkit-text-fill-color: #f3f4f6;
        }

        body.dark-theme input[type="email"]::placeholder { color: #6b7280; }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #1f2937;
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        body.dark-theme input:-webkit-autofill,
        body.dark-theme input:-webkit-autofill:hover,
        body.dark-theme input:-webkit-autofill:focus {
            -webkit-text-fill-color: #f3f4f6;
            -webkit-box-shadow: 0 0 0px 1000px #2d2d3d inset;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background-color: #2c7a7b;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button[type="submit"]:hover:not(:disabled) {
            background-color: #236162;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(44, 122, 123, 0.4);
        }

        button[type="submit"]:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        button.loading .spinner { display: inline-block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .env-list {
            background: #f9fafb;
            padding: 15px 18px;
            border-radius: 12px;
            margin: 25px 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            color: #374151;
            line-height: 1.7;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        body.dark-theme .env-list {
            background: #2d2d3d;
            border-color: #3d3d4d;
            color: #e5e7eb;
        }

        .env-list strong {
            color: #2c7a7b;
            display: block;
            margin-bottom: 8px;
        }

        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            transition: all 0.3s ease;
        }

        body.dark-theme .links {
            border-top-color: #3d3d4d;
            color: #9ca3af;
        }

        .links a {
            color: #2c7a7b;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
            transition: color 0.3s;
        }

        .links a:hover {
            color: #236162;
            text-decoration: underline;
        }

        small {
            color: #9ca3af;
            font-size: 12px;
            display: block;
            margin-top: 6px;
        }

        body.dark-theme small { color: #6b7280; }

        .theme-toggle-container {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            transition: all 0.3s ease;
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

        @media (max-width: 480px) {
            .container { padding: 30px 25px; }
            h1 { font-size: 22px; }
            .status-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-envelope"></i> Email Test System</h1>
        <p class="subtitle">Test your email configuration</p>

        <div class="status-grid">
            <?php
            $sendgridKey = getenv('SENDGRID_API_KEY');
            $resendKey = getenv('RESEND_API_KEY');
            $fromEmail = getenv('FROM_EMAIL');
            ?>
            <div class="status-card <?php echo $sendgridKey ? 'success' : 'error'; ?>">
                <i class="fas fa-paper-plane"></i>
                <div class="label">SendGrid</div>
                <div class="value"><?php echo $sendgridKey ? 'Configured' : 'Not Set'; ?></div>
            </div>
            <div class="status-card <?php echo $resendKey ? 'success' : 'error'; ?>">
                <i class="fas fa-envelope"></i>
                <div class="label">Resend</div>
                <div class="value"><?php echo $resendKey ? 'Configured' : 'Not Set'; ?></div>
            </div>
            <div class="status-card <?php echo $fromEmail ? 'success' : 'error'; ?>">
                <i class="fas fa-at"></i>
                <div class="label">From Email</div>
                <div class="value"><?php echo $fromEmail ? htmlspecialchars($fromEmail) : 'Not Set'; ?></div>
            </div>
            <div class="status-card success">
                <i class="fas fa-database"></i>
                <div class="label">Database</div>
                <div class="value">Connected</div>
            </div>
        </div>

        <div id="messageBox" class="message">
            <i class="fas"></i>
            <span id="messageText"></span>
        </div>

        <form id="testForm">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Test Email Address</label>
                <input type="email" name="email" id="email" placeholder="your@email.com" required>
                <small>Enter an email address to receive a test message</small>
            </div>
            <button type="submit" id="submitBtn">
                <span class="spinner"></span>
                <span><i class="fas fa-paper-plane"></i> Send Test Email</span>
            </button>
        </form>

        <div class="env-list">
            <strong>Environment Variables:</strong>
            <?php
            $vars = ['RESEND_API_KEY', 'SENDGRID_API_KEY', 'FROM_EMAIL', 'FROM_NAME', 'AIVEN_DATABASE_URL'];
            foreach ($vars as $var) {
                $value = getenv($var);
                $display = $value ? substr($value, 0, 20) . '...' : 'Not Set';
                echo htmlspecialchars($var) . " = " . htmlspecialchars($display) . "<br>";
            }
            ?>
        </div>

        <div class="links">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <span>|</span>
            <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password</a>
            <span>|</span>
            <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
        </div>

        <div class="theme-toggle-container">
            <button id="themeToggleBtn" class="theme-toggle-btn" type="button">
                <i class="fas fa-moon"></i>
                <span>Switch to Dark Mode</span>
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ---------- Theme ----------
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

        // ---------- Test email form ----------
        document.getElementById('testForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email');
            const submitBtn = document.getElementById('submitBtn');

            if (email.value.trim() === '') {
                showMessage('Please enter an email address.', 'error');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> <span>Sending...</span>';

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('email', email.value);

                const response = await fetch('test_reset.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                showMessage(data.message, data.success ? 'success' : 'error');
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            }

            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span><i class="fas fa-paper-plane"></i> Send Test Email</span>';
        });

        function showMessage(text, type) {
            const messageBox = document.getElementById('messageBox');
            const messageText = document.getElementById('messageText');
            const icon = messageBox.querySelector('i');

            messageText.textContent = text;
            messageBox.className = 'message show ' + type;
            icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');

            setTimeout(() => {
                messageBox.classList.remove('show');
            }, 15000);
        }
    });
    </script>
</body>
</html>
