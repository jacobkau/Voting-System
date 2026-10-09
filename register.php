<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

// Cloudinary helpers (uploadToCloudinary, defaultAvatarUrl)
require_once __DIR__ . '/cloudinary.php';

// Function to create user_elections table if it doesn't exist
function createUserElectionsTable($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS user_elections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            election_id INT NOT NULL,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_registration (user_id, election_id)
        )";
        $conn->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Error creating user_elections table: " . $e->getMessage());
        return false;
    }
}

createUserElectionsTable($conn);

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrf();
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $selectedElections = isset($_POST['elections']) ? $_POST['elections'] : [];
    $profilePhoto = $_FILES['profile_photo'] ?? null;

    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($selectedElections)) {
        $message = "All fields are required, including selecting at least one election.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $profilePhotoUrl = null;

        // Upload profile photo to Cloudinary (if provided)
        if (!empty($profilePhoto['name']) && $profilePhoto['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExt = strtolower(pathinfo($profilePhoto['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedTypes)) {
                $message = "Invalid image type. Only JPG, PNG, GIF, and WEBP are allowed.";
                $messageType = "error";
            } elseif ($profilePhoto['size'] > 2 * 1024 * 1024) {
                $message = "File is too large. Maximum size is 2MB.";
                $messageType = "error";
            } else {
                $uploadedUrl = uploadToCloudinary($profilePhoto['tmp_name'], 'voters');
                if ($uploadedUrl) {
                    $profilePhotoUrl = $uploadedUrl;
                } else {
                    $message = "Failed to upload profile photo. Please try again.";
                    $messageType = "error";
                }
            }
        }

        if (empty($message)) {
            try {
                $checkUserStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkUserStmt->execute([$username, $email]);
                
                if ($checkUserStmt->rowCount() > 0) {
                    $message = "Username or Email already exists.";
                    $messageType = "error";
                } else {
                    $insertUserStmt = $conn->prepare("INSERT INTO users (username, name, email, password, profile_photo) VALUES (?, ?, ?, ?, ?)");
                    
                    if ($insertUserStmt->execute([$username, $name, $email, $passwordHash, $profilePhotoUrl])) {
                        $userId = $conn->lastInsertId();

                        $insertElectionStmt = $conn->prepare("INSERT IGNORE INTO user_elections (user_id, election_id) VALUES (?, ?)");
                        
                        $registeredCount = 0;
                        foreach ($selectedElections as $electionId) {
                            if ($insertElectionStmt->execute([$userId, $electionId])) {
                                $registeredCount++;
                            }
                        }

                        if ($registeredCount > 0) {
                            $message = "Registration successful! You have been registered for " . $registeredCount . " election(s).";
                            $messageType = "success";
                            echo "<script>setTimeout(function(){ window.location.href = 'login.php?registered=success'; }, 2000);</script>";
                        } else {
                            throw new Exception("Failed to register for elections");
                        }
                    } else {
                        throw new Exception("Failed to insert user");
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $message = "Registration failed. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// Fetch active elections for registration
$electionsStmt = $conn->prepare("SELECT id, title, status FROM elections WHERE status = 'active' OR status = 'upcoming' ORDER BY status DESC, start_date ASC");
$electionsStmt->execute();
$activeElections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Voting System</title>
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
            align-items: flex-start;
            padding: 30px 20px;
            transition: background-color 0.3s ease;
        }
        
        body.light-theme { background-color: #2c7a7b; }
        body.dark-theme  { background-color: #1e293b; }
        
        .registration-container {
            width: 100%;
            max-width: 640px;
            background: white;
            border-radius: 24px;
            padding: 45px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: background 0.3s ease;
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        
        body.dark-theme .registration-container { background: #1e1e2e; }
        
        .registration-title {
            text-align: center;
            margin-bottom: 10px;
            color: #1f2937;
            font-size: 32px;
            font-weight: 700;
        }
        
        body.dark-theme .registration-title { color: #f3f4f6; }
        
        .registration-title i {
            color: #2c7a7b;
            margin-right: 10px;
        }
        
        .registration-subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 35px;
            font-size: 14px;
        }
        
        body.dark-theme .registration-subtitle { color: #9ca3af; }
        
        .form-group { margin-bottom: 25px; }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }
        
        body.dark-theme .form-label { color: #e5e7eb; }
        
        .form-label i {
            margin-right: 8px;
            color: #2c7a7b;
        }
        
        .required:after {
            content: " *";
            color: #dc2626;
        }
        
        /* -------- Input visibility fix -------- */
        .form-input,
        .form-input-file {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
            box-sizing: border-box;
            background: white;
            color: #1f2937;
            -webkit-text-fill-color: #1f2937;
        }

        .form-input::placeholder {
            color: #9ca3af;
            opacity: 1;
        }

        .form-input:focus,
        .form-input-file:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
        }

        body.dark-theme .form-input,
        body.dark-theme .form-input-file {
            background: #2d2d3d;
            border-color: #3d3d4d;
            color: #f3f4f6;
            -webkit-text-fill-color: #f3f4f6;
        }

        body.dark-theme .form-input::placeholder {
            color: #6b7280;
        }

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
        /* ------------------------------------- */
        
        .form-input-file {
            padding: 10px 16px;
            background: #f9fafb;
        }
        
        body.dark-theme .form-input-file {
            background: #2d2d3d;
        }
        
        .file-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 6px;
        }
        
        body.dark-theme .file-hint { color: #6b7280; }
        
        .elections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
            margin-top: 5px;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            background: #f9fafb;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        body.dark-theme .form-check {
            background: #2d2d3d;
            border-color: #3d3d4d;
        }
        
        .form-check:hover {
            background: #f3f4f6;
            border-color: #2c7a7b;
        }
        
        body.dark-theme .form-check:hover {
            background: #3d3d4d;
            border-color: #2c7a7b;
        }
        
        .form-check-input {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #2c7a7b;
        }
        
        .form-check-label {
            color: #374151;
            font-size: 14px;
            cursor: pointer;
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        body.dark-theme .form-check-label { color: #e5e7eb; }
        
        .election-status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        body.dark-theme .status-active {
            background: #064e3b;
            color: #a7f3d0;
        }
        
        .status-upcoming {
            background: #fef3c7;
            color: #92400e;
        }
        
        body.dark-theme .status-upcoming {
            background: #78350f;
            color: #fde68a;
        }
        
        .submit-btn {
            width: 100%;
            background-color: #2c7a7b;
            color: white;
            padding: 14px 20px;
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
            margin-top: 10px;
            font-family: inherit;
        }
        
        .submit-btn:hover {
            background-color: #236162;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(44, 122, 123, 0.4);
        }
        
        .submit-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .submit-btn.loading:hover {
            transform: none;
            box-shadow: none;
        }
        
        .submit-btn .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        .submit-btn.loading .spinner { display: inline-block; }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .message {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        body.dark-theme .message.success {
            background-color: #064e3b;
            color: #a7f3d0;
        }
        
        .message.error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        body.dark-theme .message.error {
            background-color: #7f1d1d;
            color: #fecaca;
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        
        body.dark-theme .login-link {
            border-top-color: #3d3d4d;
            color: #9ca3af;
        }
        
        .login-link a {
            color: #2c7a7b;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            color: #236162;
            text-decoration: underline;
        }
        
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
        
        @media (max-width: 768px) {
            .registration-container { padding: 30px 25px; }
            .registration-title { font-size: 24px; }
            .elections-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <h1 class="registration-title">
            <i class="fas fa-vote-yea"></i> Create Account
        </h1>
        <p class="registration-subtitle">Join our voting system to participate in elections</p>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="post" action="register.php" enctype="multipart/form-data" id="registrationForm">
             <?= csrfField() ?>
            <div class="form-group">
                <label for="username" class="form-label required">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" name="username" id="username" class="form-input" placeholder="Choose a username" required>
            </div>
            
            <div class="form-group">
                <label for="name" class="form-label required">
                    <i class="fas fa-signature"></i> Full Name
                </label>
                <input type="text" name="name" id="name" class="form-input" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label required">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input type="email" name="email" id="email" class="form-input" placeholder="your@email.com" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label required">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" name="password" id="password" class="form-input" placeholder="Create a password" required>
                <div class="file-hint">Password must be at least 6 characters</div>
            </div>
            
            <div class="form-group">
                <label for="profile_photo" class="form-label">
                    <i class="fas fa-camera"></i> Profile Photo
                </label>
                <input type="file" name="profile_photo" id="profile_photo" class="form-input-file" accept="image/*">
                <div class="file-hint">Optional: JPG, PNG, GIF, WEBP (Max 2MB). Uploaded to Cloudinary.</div>
            </div>
            
            <div class="form-group">
                <label class="form-label required">
                    <i class="fas fa-poll"></i> Select Elections
                </label>
                <div class="elections-grid" id="electionsGrid">
                    <?php if (empty($activeElections)): ?>
                        <p style="color: #9ca3af; grid-column: 1/-1; text-align: center;">No active elections available for registration.</p>
                    <?php else: ?>
                        <?php foreach ($activeElections as $election): ?>
                            <div class="form-check">
                                <input type="checkbox" name="elections[]" value="<?php echo $election['id']; ?>" id="election_<?php echo $election['id']; ?>" class="form-check-input">
                                <label class="form-check-label" for="election_<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['title']); ?>
                                    <span class="election-status status-<?php echo $election['status']; ?>">
                                        <?php echo ucfirst($election['status']); ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">
                <span class="spinner"></span>
                <span><i class="fas fa-user-plus"></i> Register Account</span>
            </button>
            
            <div class="login-link">
                Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login here</a>
            </div>
        </form>
        
        <div class="theme-toggle-container">
            <button id="themeToggleBtn" class="theme-toggle-btn" type="button">
                <i class="fas fa-moon"></i>
                <span>Switch to Dark Mode</span>
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registrationForm');
        const submitBtn = document.getElementById('submitBtn');
        const passwordInput = document.getElementById('password');
        const electionsCheckboxes = document.querySelectorAll('input[name="elections[]"]');
        
        // Password validation visual hint
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                if (this.value.length > 0 && this.value.length < 6) {
                    this.style.borderColor = '#dc2626';
                } else {
                    this.style.borderColor = '';
                }
            });
        }
        
        // Form validation and submission
        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                let atLeastOneSelected = false;
                electionsCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) atLeastOneSelected = true;
                });
                
                if (!atLeastOneSelected) {
                    e.preventDefault();
                    alert('Please select at least one election to register for.');
                    return false;
                }
                
                if (passwordInput && passwordInput.value.length > 0 && passwordInput.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
                
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                return true;
            });
        }
        
        // File validation
        const fileInput = document.getElementById('profile_photo');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        alert('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
                        this.value = '';
                    } else if (file.size > 2 * 1024 * 1024) {
                        alert('File is too large. Maximum size is 2MB.');
                        this.value = '';
                    }
                }
            });
        }
        
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
        
        // Reset loading state if user navigates back
        window.addEventListener('pageshow', function() {
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    });
    </script>
</body>
</html>
