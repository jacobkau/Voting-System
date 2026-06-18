<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$adminId = $_SESSION['admin_id'];

// Fetch Admin Details
try {
    $stmt = $conn->prepare("SELECT username, name, email FROM admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $username = $result['username'];
        $name = $result['name'];
        $email = $result['email'];
    }
} catch (Exception $e) {
    error_log("Admin settings fetch error: " . $e->getMessage());
    $errorMsg = "Error fetching admin details. Please try again.";
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $newName = $_POST['name'];
    $newEmail = $_POST['email'];
    $newPassword = $_POST['password'];

    try {
        if (!empty($newPassword)) { // Update password if provided
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$newName, $newEmail, $hashedPassword, $adminId]);
        } else { // Update name and email only
            $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$newName, $newEmail, $adminId]);
        }

        if ($stmt->rowCount() > 0) {
            $successMsg = "Admin settings updated successfully.";
        } else {
            $successMsg = "No changes were made.";
        }

        // Refetch admin details
        $stmt = $conn->prepare("SELECT username, name, email FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $username = $result['username'];
            $name = $result['name'];
            $email = $result['email'];
        }

    } catch (Exception $e) {
        error_log("Admin settings update error: " . $e->getMessage());
        $errorMsg = "Error updating admin settings. Please try again.";
    }
}
?>

<!-- Rest of your HTML remains the same -->
<style>
    h2 { text-align: center; }
    label { display: block; margin-bottom: 5px; }
    input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    button { background-color: #3498db; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
    .message { text-align: center; margin-bottom: 10px; }
    .success { color: green; }
    .error { color: red; }
</style>
<h2>Admin Settings</h2>

<?php if (isset($successMsg)): ?>
    <p class="message success"><?php echo $successMsg; ?></p>
<?php endif; ?>

<?php if (isset($errorMsg)): ?>
    <p class="message error"><?php echo $errorMsg; ?></p>
<?php endif; ?>

<form method="post">
    <label for="username">Username:</label>
    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" readonly>

    <label for="name">Name:</label>
    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>

    <label for="password">New Password (leave blank to keep current):</label>
    <input type="password" id="password" name="password">

    <button type="submit" name="update_settings">Update Settings</button>
</form>
