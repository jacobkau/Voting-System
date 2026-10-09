<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

// Cloudinary helpers 
require_once __DIR__ . '/cloudinary.php';

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
    $errorMessage = "Error fetching profile. Please try again.";
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Password update (optional)
    $password = $row['password'];
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    // Handle profile photo — Cloudinary only
    $profilePhotoUrl = $row['profile_photo'] ?? null;
    $oldPhotoUrlToDelete = null;

    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowedTypes)) {
            if ($_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) {
                $uploadedUrl = uploadToCloudinary($_FILES['profile_photo']['tmp_name'], 'voters');
                if ($uploadedUrl) {
                    // Mark the old image for deletion after successful DB update
                    if (!empty($row['profile_photo'])) {
                        $oldPhotoUrlToDelete = extractPublicIdFromUrl($row['profile_photo']);
                    }
                    $profilePhotoUrl = $uploadedUrl;
                } else {
                    $message = "Failed to upload profile photo. Please try again.";
                    $messageType = "error";
                }
            } else {
                $message = "File is too large. Maximum size is 2MB.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid image type. Only JPG, PNG, GIF, and WEBP are allowed.";
            $messageType = "error";
        }
    }

    // Update user data (only if no upload error)
    if (empty($message)) {
        try {
            $stmt_update = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, profile_photo = ? WHERE username = ?");
            
            if ($stmt_update->execute([$name, $email, $password, $profilePhotoUrl, $username])) {
                $message = "Profile updated successfully!";
                $messageType = "success";
                $_SESSION['full_name'] = $name;
                $_SESSION['email'] = $email;
                $_SESSION['profile_photo'] = $profilePhotoUrl;

                // Delete old Cloudinary image (best-effort)
                if ($oldPhotoUrlToDelete) {
                    try {
                        $upload = new \Cloudinary\Api\Upload\UploadApi();
                        $upload->destroy($oldPhotoUrlToDelete);
                    } catch (Exception $ce) {
                        error_log("Cloudinary delete error for {$oldPhotoUrlToDelete}: " . $ce->getMessage());
                    }
                }

                // Refresh local user data
                $refreshStmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
                $refreshStmt->execute([$username]);
                $row = $refreshStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                throw new Exception("Update failed");
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $message = "Error updating profile. Please try again.";
            $messageType = "error";
        }
    }
}

function getProfileImage($row) {
    if (!empty($row['profile_photo']) && preg_match('#^https?://#i', $row['profile_photo'])) {
        return $row['profile_photo'];
    }
    return defaultAvatarUrl();
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
        transition: background-color 0.3s ease;
    }

    body.dark-theme .profile-container {
        background-color: #1e1e2e;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    }

    .profile-container h1 {
        color: #1f2937;
        margin-bottom: 20px;
        text-align: center;
        font-size: 28px;
        transition: color 0.3s ease;
    }

    body.dark-theme .profile-container h1 { color: #f3f4f6; }

    .profile-container h1 i {
        color: #2c7a7b;
        margin-right: 8px;
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
        border: 4px solid #2c7a7b;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        background: #f4f7f9;
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
        font-size: 14px;
        transition: color 0.3s ease;
    }

    body.dark-theme .profile-form label { color: #e5e7eb; }

    .profile-form label i {
        color: #2c7a7b;
        margin-right: 6px;
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
        background: white;
        color: #1f2937;
        -webkit-text-fill-color: #1f2937;
    }

    .profile-form input[type="text"]::placeholder,
    .profile-form input[type="email"]::placeholder,
    .profile-form input[type="password"]::placeholder {
        color: #9ca3af;
        opacity: 1;
    }

    .profile-form input:focus {
        outline: none;
        border-color: #2c7a7b;
        box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
    }

    body.dark-theme .profile-form input[type="text"],
    body.dark-theme .profile-form input[type="email"],
    body.dark-theme .profile-form input[type="password"],
    body.dark-theme .profile-form input[type="file"] {
        background: #2d2d3d;
        border-color: #3d3d4d;
        color: #f3f4f6;
        -webkit-text-fill-color: #f3f4f6;
    }

    body.dark-theme .profile-form input::placeholder {
        color: #6b7280;
    }

 
    .profile-form input:-webkit-autofill,
    .profile-form input:-webkit-autofill:hover,
    .profile-form input:-webkit-autofill:focus {
        -webkit-text-fill-color: #1f2937;
        -webkit-box-shadow: 0 0 0px 1000px #ffffff inset;
        transition: background-color 5000s ease-in-out 0s;
    }

    body.dark-theme .profile-form input:-webkit-autofill,
    body.dark-theme .profile-form input:-webkit-autofill:hover,
    body.dark-theme .profile-form input:-webkit-autofill:focus {
        -webkit-text-fill-color: #f3f4f6;
        -webkit-box-shadow: 0 0 0px 1000px #2d2d3d inset;
    }

    .profile-form input[readonly] {
        background-color: #f9fafb;
        cursor: not-allowed;
    }

    body.dark-theme .profile-form input[readonly] {
        background-color: #3d3d4d;
        color: #9ca3af;
        -webkit-text-fill-color: #9ca3af;
    }

    .profile-form input[type="file"] {
        padding: 10px 15px;
        background: #f9fafb;
    }

    body.dark-theme .profile-form input[type="file"] {
        background: #2d2d3d;
    }

    .submit-btn {
        background-color: #2c7a7b;
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
    
    .file-hint {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 5px;
    }

    body.dark-theme .file-hint { color: #6b7280; }
    
    .message {
        padding: 15px 20px;
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
    
    @media (max-width: 768px) {
        .profile-container {
            margin: 20px;
            padding: 20px;
        }
        .profile-container h1 { font-size: 24px; }
        .profile-container img { width: 100px; height: 100px; }
    }
</style>

<div class="profile-container">
    <h1> My Profile</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="profile-image">
        <img src="<?php echo htmlspecialchars(getProfileImage($row)); ?>" alt="Profile Photo" id="profilePreview">
    </div>
    
    <form method="post" action="profile.php" class="profile-form" enctype="multipart/form-data" id="profileForm">
        <label for="profile_photo"> Profile Photo</label>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
        <div class="file-hint">Accepted formats: JPG, PNG, GIF, WEBP (Max 2MB). Uploaded to Cloudinary.</div>
        
        <label for="username"> Username</label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
        
        <label for="name"> Full Name</label>
        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($row["name"] ?? ''); ?>" required>
        
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($row["email"] ?? ''); ?>" required>
        
        <label for="password">New Password</label>
        <input type="password" name="password" id="password" placeholder="Leave blank to keep current password">
        <div class="file-hint">Enter a new password only if you want to change it</div>
        
        <button type="submit" class="submit-btn" id="submitBtn">
            <span> Update Profile</span>
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
