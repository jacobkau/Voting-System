<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("conn.php");

if (file_exists(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
}

// Handle AJAX request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $email = trim($_POST['email']);
    $response = ['success' => false, 'message' => ''];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $user['id'];
            $username = $user['username'];
            
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                expiry DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            
            $insertStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
            $insertStmt->execute([$user_id, $token, $expiry]);
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $uri = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $reset_link = "$protocol://$host$uri/test_reset.php?token=$token";
            
            // Try Resend first
            $emailSent = false;
            if (function_exists('sendEmailWithResend')) {
                $result = sendEmailWithResend($email, $username, $reset_link);
                if ($result['success']) {
                    $emailSent = true;
                }
            }
            
            if (!$emailSent) {
                $subject = "Password Reset Request";
                $body = "<h2>Password Reset</h2><p>Hello $username,</p><p><a href='$reset_link'>$reset_link</a></p><p>Expires in 1 hour.</p>";
                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                $emailSent = mail($email, $subject, $body, $headers);
            }
            
            if ($emailSent) {
                $response['success'] = true;
                $response['message'] = '✓ Reset link sent! Check your email.';
            } else {
                $response['message'] = '⚠️ Email sending failed. Try again.';
            }
        } else {
            $response['message'] = '❌ Email not found in our records.';
        }
    } catch (PDOException $e) {
        $response['message'] = '❌ Database error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .container { max-width: 500px; width: 100%; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { text-align: center; color: #333; margin-bottom: 10px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .message { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; display: none; align-items: center; gap: 10px; }
        .message.show { display: flex; }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; }
        input[type="email"] { width: 100%; padding: 14px 16px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; }
        input:focus { outline: none; border-color: #667eea; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102,126,234,0.4); }
        button:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .links { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .links a { color: #667eea; text-decoration: none; margin: 0 10px; }
        .links a:hover { text-decoration: underline; }
        small { color: #666; font-size: 12px; display: block; margin-top: 5px; }
        .spinner { display: none; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite; }
        button.loading .spinner { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-envelope"></i> Forgot Password</h1>
        <p class="subtitle">Enter your email to receive a password reset link</p>
        
        <div id="messageBox" class="message">
            <i class="fas"></i>
            <span id="messageText"></span>
        </div>
        
        <form id="resetForm">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" id="email" placeholder="your@email.com" required>
                <small>We'll send a password reset link to this email</small>
            </div>
            <button type="submit" id="submitBtn">
                <span class="spinner"></span>
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
        
        <div class="links">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <span>|</span>
            <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
        </div>
    </div>
    
    <script>
    document.getElementById('resetForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');
        const messageBox = document.getElementById('messageBox');
        const messageText = document.getElementById('messageText');
        const icon = messageBox.querySelector('i');
        
        // Validate
        if (email.value.trim() === '') {
            showMessage('Please enter your email address!', 'error');
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
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Link';
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
