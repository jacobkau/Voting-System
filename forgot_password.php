<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("conn.php");

// Load composer autoload if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
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
            
            // ---------- Email sending ----------
            $emailSent = false;
            
            // Try Resend first (if available)
            if (function_exists('sendEmailWithResend')) {
                $result = sendEmailWithResend($email, $username, $reset_link);
                if ($result['success']) {
                    $emailSent = true;
                }
            }
            
            // If Resend failed or not available, try SendGrid
            if (!$emailSent && class_exists('SendGrid\Mail\Mail')) {
                try {
                    $sendgridApiKey = getenv('SENDGRID_API_KEY');
                    if ($sendgridApiKey) {
                        $emailObj = new \SendGrid\Mail\Mail();
                        $fromEmail = getenv('FROM_EMAIL') ?: 'noreply@' . $_SERVER['HTTP_HOST'];
                        $fromName = getenv('FROM_NAME') ?: 'Voting System';
                        $emailObj->setFrom($fromEmail, $fromName);
                        $emailObj->setSubject("Password Reset Request");
                        $emailObj->addTo($email, $username);
                        
                        $htmlContent = "
                        <html>
                        <body>
                            <h2>Password Reset</h2>
                            <p>Hello $username,</p>
                            <p>Click the link below to reset your password:</p>
                            <p><a href='$reset_link'>$reset_link</a></p>
                            <p>This link expires in 1 hour.</p>
                            <p>If you didn't request this, please ignore this email.</p>
                        </body>
                        </html>
                        ";
                        $emailObj->addContent("text/html", $htmlContent);
                        
                        $sendgrid = new \SendGrid($sendgridApiKey);
                        $sendgridResponse = $sendgrid->send($emailObj);
                        
                        if ($sendgridResponse->statusCode() == 202) {
                            $emailSent = true;
                        }
                    }
                } catch (Exception $e) {
                    error_log("SendGrid error: " . $e->getMessage());
                }
            }
            
            // Fallback to PHP mail (last resort)
            if (!$emailSent) {
                $subject = "Password Reset Request";
                $body = "<h2>Password Reset</h2><p>Hello $username,</p><p><a href='$reset_link'>$reset_link</a></p><p>Expires in 1 hour.</p>";
                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                $emailSent = mail($email, $subject, $body, $headers);
            }
            
            if ($emailSent) {
                $response['success'] = true;
                $response['message'] = 'Reset link sent! Check your email.';
            } else {
                $response['message'] = 'Email sending failed. Please try again.';
            }
        } else {
            // Don't reveal whether email exists — generic message for security
            $response['success'] = true;
            $response['message'] = 'If that email exists in our records, a reset link has been sent.';
        }
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again.';
    }
    
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password | Voting System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #2c7a7b; /* calm teal */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 480px;
            width: 100%;
            background: white;
            padding: 45px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.5s ease-out;
        }

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
        }

        h1 i {
            color: #2c7a7b;
            margin-right: 10px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 35px;
            font-size: 14px;
        }

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

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

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
            background: white;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
        }

        button {
            width: 100%;
            padding: 14px;
            background-color: #2c7a7b; /* calm teal */
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

        button:hover:not(:disabled) {
            background-color: #236162;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(44, 122, 123, 0.4);
        }

        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .container { padding: 30px 25px; }
            h1 { font-size: 22px; }
        }
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
                <span><i class="fas fa-paper-plane"></i> Send Reset Link</span>
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
        
        // Validate
        if (email.value.trim() === '') {
            showMessage('Please enter your email address.', 'error');
            return;
        }
        
        // Show loading
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
        
        // Reset button
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span><i class="fas fa-paper-plane"></i> Send Reset Link</span>';
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
