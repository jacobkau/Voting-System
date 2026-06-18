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
    
    // Verify token
    try {
        $stmt = $conn->prepare("SELECT user_id, expiry FROM password_reset_tokens WHERE token = ? AND expiry > NOW()");
        $stmt->execute([$token]);
        
        if ($stmt->rowCount() == 1) {
            $resetData = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $resetData['user_id'];
            
            // Show password reset form
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Reset Password</title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    * { box-sizing: border-box; }
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        max-width: 500px;
                        width: 100%;
                        background: white;
                        padding: 40px;
                        border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    }
                    h1 { text-align: center; color: #333; margin-bottom: 10px; }
                    .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
                    .message {
                        padding: 15px 20px;
                        border-radius: 10px;
                        margin-bottom: 20px;
                        font-weight: 500;
                        display: none;
                        align-items: center;
                        gap: 10px;
                    }
                    .message.show { display: flex; }
                    .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
                    .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
                    .form-group { margin-bottom: 20px; }
                    label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; }
                    input[type="password"] {
                        width: 100%;
                        padding: 14px 16px;
                        border: 2px solid #ddd;
                        border-radius: 10px;
                        font-size: 16px;
                    }
                    input:focus { outline: none; border-color: #667eea; }
                    button {
                        width: 100%;
                        padding: 14px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        border: none;
                        border-radius: 10px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                    }
                    button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 5px 20px rgba(102,126,234,0.4);
                    }
                    button:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
                    .links { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
                    .links a { color: #667eea; text-decoration: none; margin: 0 10px; }
                    .links a:hover { text-decoration: underline; }
                    small { color: #666; font-size: 12px; display: block; margin-top: 5px; }
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
                        padding: 10px;
                        border-radius: 5px;
                        display: none;
                    }
                    .strength-weak { background: #ff6b6b; color: white; }
                    .strength-medium { background: #feca57; color: #333; }
                    .strength-strong { background: #51cf66; color: white; }
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
                            <i class="fas fa-sync-alt"></i> Reset Password
                        </button>
                    </form>
                    
                    <div class="links">
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <span>|</span>
                        <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                    </div>
                </div>
                
                <script>
                // Password strength checker
                document.getElementById('password').addEventListener('input', function() {
                    const password = this.value;
                    const indicator = document.getElementById('strengthIndicator');
                    
                    if (password.length === 0) {
                        indicator.style.display = 'none';
                        return;
                    }
                    
                    let strength = 'weak';
                    let color = '#ff6b6b';
                    let text = 'Weak';
                    
                    if (password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                        strength = 'strong';
                        color = '#51cf66';
                        text = 'Strong';
                    } else if (password.length >= 6 && (/[A-Z]/.test(password) || /[0-9]/.test(password))) {
                        strength = 'medium';
                        color = '#feca57';
                        text = 'Medium';
                    }
                    
                    indicator.style.display = 'block';
                    indicator.className = 'password-strength strength-' + strength;
                    indicator.textContent = 'Password Strength: ' + text;
                });
                
                // Form submission
                document.getElementById('resetForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const password = document.getElementById('password');
                    const confirm = document.getElementById('confirm_password');
                    const submitBtn = document.getElementById('submitBtn');
                    const messageBox = document.getElementById('messageBox');
                    const messageText = document.getElementById('messageText');
                    
                    // Validate
                    if (password.value.length < 6) {
                        showMessage('Password must be at least 6 characters!', 'error');
                        return;
                    }
                    
                    if (password.value !== confirm.value) {
                        showMessage('Passwords do not match!', 'error');
                        return;
                    }
                    
                    // Show loading
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner"></span> Resetting...';
                    
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
                            // Redirect to login after 3 seconds
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 3000);
                        }
                    } catch (error) {
                        showMessage('An error occurred. Please try again.', 'error');
                    }
                    
                    // Reset button
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Reset Password';
                });
                
                function showMessage(text, type) {
                    const messageBox = document.getElementById('messageBox');
                    const messageText = document.getElementById('messageText');
                    const icon = messageBox.querySelector('i');
                    
                    messageText.textContent = text;
                    messageBox.className = 'message show ' + type;
                    icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
                    
                    // Auto hide after 10 seconds
                    setTimeout(() => {
                        messageBox.classList.remove('show');
                    }, 10000);
                }
                </script>
            </body>
            </html>
            <?php
            exit();
        } else {
            // Invalid or expired token
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Invalid Token</title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        max-width: 500px;
                        width: 100%;
                        background: white;
                        padding: 40px;
                        border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        text-align: center;
                    }
                    h1 { color: #dc3545; }
                    .icon { font-size: 60px; color: #dc3545; margin-bottom: 20px; }
                    .links { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
                    .links a { color: #667eea; text-decoration: none; margin: 0 10px; }
                    .links a:hover { text-decoration: underline; }
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
                </div>
            </body>
            </html>
            <?php
            exit();
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Handle AJAX requests (test email or password reset)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Check if this is a password reset submission
    if (isset($_POST['user_id']) && isset($_POST['token']) && isset($_POST['password'])) {
        // Process password reset
        $userId = intval($_POST['user_id']);
        $token = $_POST['token'];
        $password = $_POST['password'];
        
        try {
            // Verify token again
            $stmt = $conn->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND user_id = ? AND expiry > NOW()");
            $stmt->execute([$token, $userId]);
            
            if ($stmt->rowCount() == 1) {
                // Update password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $userId]);
                
                // Delete used token
                $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                $deleteStmt->execute([$token]);
                
                echo json_encode(['success' => true, 'message' => '✅ Password reset successful! Redirecting to login...']);
            } else {
                echo json_encode(['success' => false, 'message' => '❌ Invalid or expired token. Please request a new reset link.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Handle test email request
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
                    <h2>✅ Test Email Successful!</h2>
                    <p>This is a test email from your Voting System.</p>
                    <p>If you received this, your email configuration is working correctly!</p>
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
        
        // If SendGrid failed, try Resend
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
                            <h2>✅ Test Email Successful!</h2>
                            <p>This is a test email from your Voting System.</p>
                            <p>If you received this, your email configuration is working correctly!</p>
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
            $response['message'] = "✅ Test email sent successfully via $method! Check your inbox and spam folder.";
        } else {
            $response['message'] = "❌ Failed to send test email. Check your API keys and from email configuration.";
        }
        
        echo json_encode($response);
        exit();
    }
}

// Display test page (GET request)
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            width: 100%;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { text-align: center; color: #333; margin-bottom: 10px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; }
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .status-card {
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .status-card.success { border-color: #28a745; background: #d4edda; }
        .status-card.error { border-color: #dc3545; background: #f8d7da; }
        .status-card i { font-size: 24px; margin-bottom: 5px; }
        .status-card .label { font-size: 12px; color: #666; }
        .status-card .value { font-weight: bold; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; }
        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
        }
        input:focus { outline: none; border-color: #667eea; }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        button:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 10px;
        }
        .message.show { display: flex; }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
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
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .env-list strong { color: #667eea; }
        .links { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .links a { color: #667eea; text-decoration: none; margin: 0 10px; }
        .links a:hover { text-decoration: underline; }
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
                <i class="fas fa-send"></i>
                <div class="label">SendGrid</div>
                <div class="value"><?php echo $sendgridKey ? '✅ Configured' : '❌ Not Set'; ?></div>
            </div>
            <div class="status-card <?php echo $resendKey ? 'success' : 'error'; ?>">
                <i class="fas fa-envelope"></i>
                <div class="label">Resend</div>
                <div class="value"><?php echo $resendKey ? '✅ Configured' : '❌ Not Set'; ?></div>
            </div>
            <div class="status-card <?php echo $fromEmail ? 'success' : 'error'; ?>">
                <i class="fas fa-at"></i>
                <div class="label">From Email</div>
                <div class="value"><?php echo $fromEmail ? htmlspecialchars($fromEmail) : '❌ Not Set'; ?></div>
            </div>
            <div class="status-card success">
                <i class="fas fa-database"></i>
                <div class="label">Database</div>
                <div class="value">✅ Connected</div>
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
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </form>
        
        <div class="env-list">
            <strong>Environment Variables:</strong><br>
            <?php
            $vars = ['RESEND_API_KEY', 'SENDGRID_API_KEY', 'FROM_EMAIL', 'FROM_NAME', 'AIVEN_DATABASE_URL'];
            foreach ($vars as $var) {
                $value = getenv($var);
                $display = $value ? substr($value, 0, 20) . '...' : '❌ Not Set';
                echo "$var = " . htmlspecialchars($display) . "<br>";
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
    </div>
    
    <script>
    document.getElementById('testForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');
        const messageBox = document.getElementById('messageBox');
        const messageText = document.getElementById('messageText');
        
        if (email.value.trim() === '') {
            showMessage('Please enter an email address!', 'error');
            return;
        }
        
        // Show loading
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Sending...';
        
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
        
        // Reset button
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test Email';
    });
    
    function showMessage(text, type) {
        const messageBox = document.getElementById('messageBox');
        const messageText = document.getElementById('messageText');
        const icon = messageBox.querySelector('i');
        
        messageText.textContent = text;
        messageBox.className = 'message show ' + type;
        icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
        
        // Auto hide after 15 seconds
        setTimeout(() => {
            messageBox.classList.remove('show');
        }, 15000);
    }
    </script>
</body>
</html>
