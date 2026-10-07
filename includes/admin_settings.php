<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Cloudinary helpers (uploadToCloudinary, extractPublicIdFromUrl, Configuration)
require_once __DIR__ . '/../cloudinary.php';

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$adminId = $_SESSION['admin_id'];

// Helper: resolve an image URL (Cloudinary URL or legacy filename)
function resolveImageUrl($value, $folder = 'faces', $default = 'faces/default.jpg') {
    if (empty($value)) return $default;
    if (preg_match('#^https?://#i', $value)) return $value;
    return $folder . '/' . $value;
}

// Fetch Admin Details
$username = $name = $email = '';
$profilePhoto = '';
try {
    $stmt = $conn->prepare("SELECT username, name, email, profile_photo FROM admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $username = $result['username'];
        $name = $result['name'];
        $email = $result['email'];
        $profilePhoto = $result['profile_photo'];
    }
} catch (Exception $e) {
    error_log("Admin settings fetch error: " . $e->getMessage());
    $errorMsg = "Error fetching admin details. Please try again.";
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $newPassword = $_POST['password'];
    $removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1';

    $newProfilePhotoUrl = $profilePhoto; // default: keep current
    $oldPublicIdToDelete = null;

    try {
        // Handle new photo upload
        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadedUrl = uploadToCloudinary($_FILES['profile_photo']['tmp_name'], 'admins');
            if ($uploadedUrl) {
                // Mark old photo for deletion (if it was a Cloudinary URL)
                if (!empty($profilePhoto)) {
                    $oldPublicIdToDelete = extractPublicIdFromUrl($profilePhoto);
                }
                $newProfilePhotoUrl = $uploadedUrl;
            } else {
                $errorMsg = "Failed to upload profile photo. Please try again.";
            }
        }
        // Handle explicit photo removal
        elseif ($removePhoto && !empty($profilePhoto)) {
            $oldPublicIdToDelete = extractPublicIdFromUrl($profilePhoto);
            $newProfilePhotoUrl = null;
        }

        if (!isset($errorMsg)) {
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, password = ?, profile_photo = ? WHERE id = ?");
                $stmt->execute([$newName, $newEmail, $hashedPassword, $newProfilePhotoUrl, $adminId]);
            } else {
                $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, profile_photo = ? WHERE id = ?");
                $stmt->execute([$newName, $newEmail, $newProfilePhotoUrl, $adminId]);
            }

            if ($stmt->rowCount() > 0) {
                $successMsg = "Admin settings updated successfully.";
            } else {
                $successMsg = "No changes were made.";
            }

            // Delete old Cloudinary image AFTER successful DB update (best-effort)
            if ($oldPublicIdToDelete) {
                try {
                    $upload = new \Cloudinary\Api\Upload\UploadApi();
                    $upload->destroy($oldPublicIdToDelete);
                } catch (Exception $ce) {
                    error_log("Cloudinary delete error for {$oldPublicIdToDelete}: " . $ce->getMessage());
                }
            }

            // Refetch admin details
            $stmt = $conn->prepare("SELECT username, name, email, profile_photo FROM admin WHERE id = ?");
            $stmt->execute([$adminId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $username = $result['username'];
                $name = $result['name'];
                $email = $result['email'];
                $profilePhoto = $result['profile_photo'];
            }
        }

    } catch (Exception $e) {
        error_log("Admin settings update error: " . $e->getMessage());
        $errorMsg = "Error updating admin settings. Please try again.";
    }
}

// Resolve the display URL for the current photo
$profilePhotoDisplay = resolveImageUrl($profilePhoto, 'faces', 'faces/default.jpg');
?>

<style>
    h2 { text-align: center; margin-bottom: 20px; }
    label { display: block; margin-bottom: 5px; font-weight: 600; }
    input[type="text"], input[type="email"], input[type="password"], input[type="file"] {
        width: 100%; padding: 8px; margin-bottom: 12px;
        border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
    }
    button {
        background-color: #2c7a7b; color: white; padding: 10px 15px;
        border: none; border-radius: 4px; cursor: pointer;
        transition: background 0.2s;
    }
    button:hover { background-color: #236162; }
    .message { text-align: center; margin-bottom: 10px; padding: 10px; border-radius: 4px; }
    .success { color: #155724; background: #d4edda; }
    .error { color: #721c24; background: #f8d7da; }
    .current-photo { text-align: center; margin-bottom: 20px; }
    .current-photo img {
        width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
        border: 4px solid #2c7a7b; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .remove-photo {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 15px; font-size: 14px; color: #555;
    }
    .remove-photo input[type="checkbox"] { width: auto; margin: 0; }
    .upload-note { font-size: 12px; color: #666; margin-top: -8px; margin-bottom: 15px; }
</style>

<h2>Admin Settings</h2>

<?php if (isset($successMsg)): ?>
    <p class="message success"><?php echo htmlspecialchars($successMsg); ?></p>
<?php endif; ?>

<?php if (isset($errorMsg)): ?>
    <p class="message error"><?php echo htmlspecialchars($errorMsg); ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <div class="current-photo">
        <img src="<?php echo htmlspecialchars($profilePhotoDisplay); ?>" alt="Admin Photo"
             onerror="this.src='faces/default.jpg'">
    </div>

    <label for="username">Username:</label>
    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" readonly>

    <label for="name">Name:</label>
    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>

    <label for="password">New Password (leave blank to keep current):</label>
    <input type="password" id="password" name="password">

    <label for="profile_photo">Profile Picture:</label>
    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
    <div class="upload-note">Uploaded to Cloudinary (auto-cropped 500×500, face-centered).</div>

    <?php if (!empty($profilePhoto)): ?>
        <div class="remove-photo">
            <input type="checkbox" id="remove_photo" name="remove_photo" value="1">
            <label for="remove_photo">Remove current profile picture</label>
        </div>
    <?php endif; ?>

    <button type="submit" name="update_settings">Update Settings</button>
</form>
