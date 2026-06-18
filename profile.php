<?php
session_start();
include("conn.php");

// Redirect if not logged in
if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";
$username = $_SESSION["username"];

// Fetch user data using PDO
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("User not found.");
    }
    $stmt->closeCursor();
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $errorMessage = "Error fetching profile: " . htmlspecialchars($e->getMessage());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Handle password update
    $password = $row['password'];
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    // Handle profile photo upload using BLOB storage
    $profilePhotoBlob = $row['profile_photo_blob'] ?? null;
    $profilePhotoType = $row['profile_photo_type'] ?? null;

    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowedTypes)) {
            if ($_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) {
                $profilePhotoBlob = file_get_contents($_FILES['profile_photo']['tmp_name']);
                $profilePhotoType = $fileExt;
                $message = "Profile photo updated successfully!";
                $messageType = "success";
            } else {
                $message = "File is too large. Maximum size is 2MB.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid image type. Only JPG, PNG, and GIF are allowed.";
            $messageType = "error";
        }
    }

    // Update user data
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo_blob'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE users ADD COLUMN profile_photo_blob LONGBLOB");
            $conn->exec("ALTER TABLE users ADD COLUMN profile_photo_type VARCHAR(10)");
        }
        
        $stmt_update = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, profile_photo_blob = ?, profile_photo_type = ? WHERE username = ?");
        
        if ($stmt_update->execute([$name, $email, $password, $profilePhotoBlob, $profilePhotoType, $username])) {
            $message = "Profile updated successfully!";
            $messageType = "success";
            $_SESSION['full_name'] = $name;
            $_SESSION['email'] = $email;
            
            // Refresh user data
            $refreshStmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $refreshStmt->execute([$username]);
            $row = $refreshStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Update failed");
        }
        $stmt_update->closeCursor();
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $message = "Error updating profile: " . htmlspecialchars($e->getMessage());
        $messageType = "error";
    }
}

function getProfileImage($row) {
    if (!empty($row['profile_photo_blob'])) {
        return 'data:image/' . $row['profile_photo_type'] . ';base64,' . base64_encode($row['profile_photo_blob']);
    }
    return 'default-avatar.png';
}
?>

<?php include("header.php"); ?>

<style>
    .profile-container {
        max-width: 600px;
        margin: 40px auto;
        background-color: white;
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    .profile-container h1 {
        color: #333;
        margin-bottom: 20px;
        text-align: center;
        font-size: 28px;
    }

    .profile-image {
        text-align: center;
        margin-bottom: 25px;
    }
    
    .profile-container img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #667eea;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .profile-form {
        display: flex;
        flex-direction: column;
    }

    .profile-form label {
        margin-top: 18px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 5px;
    }

    .profile-form input[type="text"],
    .profile-form input[type="email"],
    .profile-form input[type="password"],
    .profile-form input[type="file"] {
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
    }
    
    .profile-form input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    
    .profile-form input[readonly] {
        background-color: #f9fafb;
        cursor: not-allowed;
    }

    .profile-form input[type="file"] {
        padding: 10px 15px;
        background: #f9fafb;
    }

    .submit-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 14px 20px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        margin-top: 25px;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102,126,234,0.4);
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
    
    .submit-btn.loading .spinner {
        display: inline-block;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .file-hint {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 5px;
    }
    
    .message {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .message.success {
        background-color: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    
    .message.error {
        background-color: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #dc2626;
    }
    
    @media (max-width: 768px) {
        .profile-container {
            margin: 20px;
            padding: 20px;
        }
        .profile-container h1 {
            font-size: 24px;
        }
        .profile-container img {
            width: 100px;
            height: 100px;
        }
    }
</style>

<div class="profile-container">
    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-image">
        <img src="<?php echo getProfileImage($row); ?>" alt="Profile Photo" id="profilePreview">
    </div>
    
    <form method="post" action="profile.php" class="profile-form" enctype="multipart/form-data" id="profileForm">
        <label for="profile_photo"><i class="fas fa-camera"></i> Profile Photo:</label>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
        <div class="file-hint"><i class="fas fa-info-circle"></i> Accepted formats: JPG, PNG, GIF (Max 2MB)</div>
        
        <label for="username"><i class="fas fa-user"></i> Username:</label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
        
        <label for="name"><i class="fas fa-signature"></i> Full Name:</label>
        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($row["name"] ?? ''); ?>" required>
        
        <label for="email"><i class="fas fa-envelope"></i> Email:</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($row["email"] ?? ''); ?>" required>
        
        <label for="password"><i class="fas fa-lock"></i> New Password:</label>
        <input type="password" name="password" id="password" placeholder="Leave blank to keep current password">
        <div class="file-hint">Enter a new password only if you want to change it</div>
        
        <button type="submit" class="submit-btn" id="submitBtn">
            <i class="fas fa-save"></i>
            <span class="btn-text">Update Profile</span>
            <span class="spinner"></span>
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');
    const fileInput = document.getElementById('profile_photo');
    const profilePreview = document.getElementById('profilePreview');
    
    // Image preview
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && profilePreview) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    profilePreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Form submission with loading state
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            return true;
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

<?php include("footer.php"); ?>
