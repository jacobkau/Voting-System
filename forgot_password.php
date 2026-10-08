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

/**
 * Send a password reset email via EmailJS.
 * Returns true on success, false on failure.
 */
function sendPasswordResetEmail(string $email, string $username, string $resetLink): bool {
    $serviceId  = getenv('EMAILJS_SERVICE_ID');
    $templateId = getenv('EMAILJS_TEMPLATE_ID');
    $publicKey  = getenv('EMAILJS_PUBLIC_KEY');
    $privateKey = getenv('EMAILJS_PRIVATE_KEY');

    if (!$serviceId || !$templateId || !$publicKey) {
        error_log("EmailJS: missing required environment variables.");
        return false;
    }

    $payload = [
        'service_id'      => $serviceId,
        'template_id'     => $templateId,
        'user_id'         => $publicKey,
        'template_params' => [
            'user_name'  => $username,
            'user_email' => $email,
            'reset_link' => $resetLink,
            'expiry'     => '1 hour'
        ]
    ];

    if ($privateKey) {
        $payload['accessToken'] = $privateKey;
    }

    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && !$curlError) {
        return true;
    }

    error_log("EmailJS error: HTTP {$httpCode} — " . ($response ?: $curlError));
    return false;
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
            $user     = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id  = $user['id'];
            $username = $user['username'];

            $token  = bin2hex(random_bytes(32));
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

            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $uri  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $reset_link = "$protocol://$host$uri/test_reset.php?token=$token";

            // Send via EmailJS
            if (sendPasswordResetEmail($email, $username, $reset_link)) {
                $response['success'] = true;
                $response['message'] = 'Reset link sent! Check your email.';
            } else {
                $response['message'] = 'Email sending failed. Please try again.';
            }
        } else {
            // Don't reveal whether the email exists
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
            max-width: 480px;
            width: 100%;
            background: white;
            padding: 45px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
            background: white;
        }

        body.dark-theme input[type="email"] {
            background: #2d2d3d;
            border-color: #3d3d4d;
            color: #f3f4f6;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
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

        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
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

        .theme-toggle-container {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
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

        <div class="theme-toggle-container">
            <button id="themeToggleBtn" class="theme-toggle-btn">
                <i class="fas fa-moon"></i>
                <span>Switch to Dark Mode</span>
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme management
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

        // Form submission
        document.getElementById('resetForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email');
            const submitBtn = document.getElementById('submitBtn');

            if (email.value.trim() === '') {
                showMessage('Please enter your email address.', 'error');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> <span>Sending...</span>';

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('email', email.value);

                const response = await fetch('forgot_password.php', {
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
            submitBtn.innerHTML = '<span><i class="fas fa-paper-plane"></i> Send Reset Link</span>';
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
