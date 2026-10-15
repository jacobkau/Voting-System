<?php
// Buffer output from the very first line — this prevents any stray
// PHP warning from polluting JSON responses later in the file.
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conn.php';

// Cloudinary helpers (extractPublicIdFromUrl, defaultAvatarUrl)
require_once __DIR__ . '/../cloudinary.php';

// Admin Authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header("Location: admin_login.php");
    exit();
}

// Helper: resolve to a Cloudinary URL or the system default
if (!function_exists('resolveAdminImageUrl')) {
    function resolveAdminImageUrl($value) {
        if (!empty($value) && preg_match('#^https?://#i', $value)) {
            return $value;
        }
        return defaultAvatarUrl();
    }
}

/* =====================================================================
   AJAX: get_admin
   ===================================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'get_admin') {
    ob_clean();                              // discard any stray warnings
    header('Content-Type: application/json');

    $adminId = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;

    if ($adminId <= 0) {
        echo json_encode(['error' => 'Invalid admin ID']);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            SELECT id, username, name AS fullname, email, profile_photo, created_on
            FROM admin
            WHERE id = ?
        ");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            echo json_encode(['error' => 'Admin not found']);
            exit();
        }

        echo json_encode([
            'success'        => true,
            'id'             => $admin['id'],
            'username'       => $admin['username'],
            'fullname'       => $admin['fullname'] ?? 'N/A',
            'email'          => $admin['email'],
            'profile_photo'  => resolveAdminImageUrl($admin['profile_photo']),
            'created_on'     => $admin['created_on'],
        ]);

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

/* =====================================================================
   AJAX: add_admin
   ===================================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_admin') {
    ob_clean();
    header('Content-Type: application/json');

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
    $password = isset($_POST['password']) ? $_POST['password']        : '';

    if (empty($username) || empty($fullname) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit();
    }

    try {
        // Check both username AND email collisions with specific messages
        $check = $conn->prepare("SELECT id, username, email FROM admin WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (strcasecmp($existing['username'], $username) === 0) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                exit();
            }
            if (strcasecmp($existing['email'], $email) === 0) {
                echo json_encode(['success' => false, 'message' => 'Email already in use by another admin']);
                exit();
            }
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO admin (username, password, name, email)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $hash, $fullname, $email]);

        echo json_encode(['success' => true, 'message' => 'Admin added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

/* =====================================================================
   AJAX: update_admin
   ===================================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_admin') {
    ob_clean();
    header('Content-Type: application/json');

    $adminId  = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
    $password = isset($_POST['password']) ? $_POST['password']        : '';

    if ($adminId <= 0 || empty($username) || empty($fullname) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }

    try {
        // Reject if the new username or email is taken by someone else
        $check = $conn->prepare("SELECT id, username, email FROM admin WHERE (username = ? OR email = ?) AND id <> ?");
        $check->execute([$username, $email, $adminId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (strcasecmp($existing['username'], $username) === 0) {
                echo json_encode(['success' => false, 'message' => 'Username already taken by another admin']);
                exit();
            }
            if (strcasecmp($existing['email'], $email) === 0) {
                echo json_encode(['success' => false, 'message' => 'Email already in use by another admin']);
                exit();
            }
        }

        if (!empty($password)) {
            if (strlen($password) < 8) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
                exit();
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE admin
                SET username = ?, name = ?, email = ?, password = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullname, $email, $hash, $adminId]);
        } else {
            $stmt = $conn->prepare("
                UPDATE admin
                SET username = ?, name = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullname, $email, $adminId]);
        }

        echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

/* =====================================================================
   AJAX: delete_admin
   ===================================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_admin') {
    ob_clean();
    header('Content-Type: application/json');

    $adminId = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;

    if ($adminId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
        exit();
    }

    // Prevent self-deletion
    if (isset($_SESSION['admin_id']) && $adminId === intval($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit();
    }

    try {
        // Prevent deleting the last admin
        $countStmt = $conn->query("SELECT COUNT(*) FROM admin");
        $totalAdmins = (int)$countStmt->fetchColumn();

        if ($totalAdmins <= 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the last remaining admin']);
            exit();
        }

        // Fetch the profile photo so we can remove it from Cloudinary after deletion
        $photoStmt = $conn->prepare("SELECT profile_photo FROM admin WHERE id = ?");
        $photoStmt->execute([$adminId]);
        $photo = $photoStmt->fetchColumn();

        $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);

        // Clean up Cloudinary photo (best-effort, after successful delete)
        if (!empty($photo) && preg_match('#^https?://#i', $photo)) {
            $publicId = function_exists('extractPublicIdFromUrl') ? extractPublicIdFromUrl($photo) : null;
            if ($publicId) {
                try {
                    $upload = new \Cloudinary\Api\Upload\UploadApi();
                    $upload->destroy($publicId);
                } catch (Exception $ce) {
                    error_log("Cloudinary delete error for {$publicId}: " . $ce->getMessage());
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

/* =====================================================================
   Page data
   ===================================================================== */
$admins = [];
$errorMessage = "";

try {
    $stmt = $conn->prepare("
        SELECT id, username, name AS full_name, email, profile_photo, created_on
        FROM admin
        ORDER BY id
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching admins: " . $e->getMessage());
    $errorMessage = "Error loading admins. Please try again later.";
}

$currentAdminId = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
$totalAdmins    = count($admins);

// Flush any warnings we captured so far — they should never reach the browser
ob_end_clean();
?>

<!-- ============================ STYLES ============================ -->
<style>
    .adm-page .header {
        text-align: left;
        padding: 4px 0 18px 0;
        color: #1f2937;
        background: transparent;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 22px;
    }
    .adm-page .header h2 {
        font-size: 22px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 4px 0;
    }
    .adm-page .header h2 i { color: #2c7a7b; }
    .adm-page .header p {
        opacity: 0.85;
        font-size: 13px;
        color: #6b7280;
        margin: 0;
    }

    .adm-page .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
        gap: 12px;
        flex-wrap: wrap;
    }

    .adm-page .top-bar h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #4b5563;
    }

    .adm-page .add-btn {
        background: #2c7a7b;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s;
        font-family: inherit;
        min-width: 150px;
    }
    .adm-page .add-btn:hover { background: #236162; transform: translateY(-1px); }

    .adm-page .table-responsive {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .adm-page table {
        width: 100%;
        border-collapse: collapse;
    }

    .adm-page th,
    .adm-page td {
        padding: 13px 16px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        font-size: 13px;
        color: #374151;
    }

    .adm-page th {
        background: #f9fafb;
        color: #6b7280;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }

    .adm-page tr:last-child td { border-bottom: none; }
    .adm-page tr:hover td { background: #f9fafb; }

    .adm-page .admin-photo-cell img {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #2c7a7b;
        background: #f4f7f9;
        vertical-align: middle;
    }

    .adm-page .you-badge {
        display: inline-block;
        background: #2c7a7b;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        vertical-align: middle;
    }

    .adm-page .btn {
        border: none;
        color: white;
        padding: 6px 12px;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s;
        margin: 2px;
        font-family: inherit;
        font-weight: 600;
        position: relative;
        min-width: 72px;
        min-height: 30px;
    }

    .adm-page .btn-view   { background: #2c7a7b; }
    .adm-page .btn-edit   { background: #ffc107; color: #333; }
    .adm-page .btn-delete { background: #dc3545; }

    .adm-page .btn:hover:not(:disabled):not(.loading) {
        transform: translateY(-1px);
        opacity: 0.92;
    }

    .adm-page .btn:disabled,
    .adm-page .btn.disabled {
        background: #cbd5e1;
        color: #64748b;
        cursor: not-allowed;
        transform: none;
        opacity: 1;
    }

    .adm-page .btn.loading {
        cursor: wait;
        opacity: 0.85;
        transform: none;
        color: transparent !important;
        pointer-events: none;
    }

    .adm-page .btn.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 14px;
        height: 14px;
        margin: -7px 0 0 -7px;
        border: 2px solid rgba(255, 255, 255, 0.4);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: admBtnSpin 0.7s linear infinite;
    }

    .adm-page .btn-edit.loading::after {
        border-color: rgba(0, 0, 0, 0.15);
        border-top-color: #333;
    }

    @keyframes admBtnSpin {
        to { transform: rotate(360deg); }
    }

    .adm-page .submit-btn {
        background: #2c7a7b;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        width: 100%;
        font-size: 15px;
        font-weight: 600;
        font-family: inherit;
        transition: background 0.2s;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
    }
    .adm-page .submit-btn:hover:not(:disabled):not(.loading) { background: #236162; }

    .adm-page .submit-btn.loading {
        cursor: wait;
        background: #4a9d9e;
        color: transparent !important;
        pointer-events: none;
    }

    .adm-page .submit-btn.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 18px;
        height: 18px;
        margin: -9px 0 0 -9px;
        border: 2px solid rgba(255, 255, 255, 0.4);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: admBtnSpin 0.7s linear infinite;
    }

    .adm-page .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        animation: admFadeIn 0.3s;
    }

    @keyframes admFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    .adm-page .modal-content {
        background-color: white;
        margin: 3% auto;
        padding: 0;
        border-radius: 16px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: admSlideDown 0.3s;
        overflow: hidden;
    }

    @keyframes admSlideDown {
        from { transform: translateY(-50px); opacity: 0; }
        to   { transform: translateY(0);     opacity: 1; }
    }

    .adm-page .modal-header {
        background: #2c7a7b;
        color: white;
        padding: 18px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .adm-page .modal-header h4 { margin: 0; font-size: 17px; font-weight: 700; }

    .adm-page .close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: opacity 0.3s;
        line-height: 20px;
    }
    .adm-page .close:hover { opacity: 0.8; }

    .adm-page .modal-body {
        padding: 22px;
        max-height: 65vh;
        overflow-y: auto;
    }

    .adm-page .form-group { margin-bottom: 15px; }

    .adm-page .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
        font-size: 13px;
    }

    .adm-page .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
        box-sizing: border-box;
    }

    .adm-page .form-group input:focus {
        outline: none;
        border-color: #2c7a7b;
        box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.12);
    }

    .adm-page .form-group small {
        color: #6b7280;
        font-size: 12px;
        display: block;
        margin-top: 4px;
    }

    .adm-page .profile-photo {
        text-align: center;
        margin-bottom: 20px;
    }

    .adm-page .profile-photo img {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #2c7a7b;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        background: #f4f7f9;
    }

    .adm-page .info-section {
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e0e0e0;
        font-size: 14px;
        color: #374151;
    }
    .adm-page .info-section:last-child { border-bottom: none; }

    .adm-page .info-section strong {
        color: #2c7a7b;
        display: inline-block;
        min-width: 130px;
        font-size: 13px;
    }

    .adm-page .loading {
        text-align: center;
        padding: 40px;
        color: #2c7a7b;
    }

    .adm-page .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #2c7a7b;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: admSpin 1s linear infinite;
        margin: 0 auto 15px;
    }

    @keyframes admSpin {
        0%   { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .adm-page .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 14px;
        border-radius: 8px;
        text-align: center;
        font-size: 13px;
    }

    .adm-page .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .adm-page .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #333;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 1100;
        animation: admSlideIn 0.3s;
        font-size: 13px;
        font-weight: 500;
    }
    .adm-page .toast.success { background: #28a745; }
    .adm-page .toast.error   { background: #dc3545; }

    @keyframes admSlideIn {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }

    @media screen and (max-width: 768px) {
        .adm-page th, .adm-page td { padding: 10px 12px; font-size: 12px; }
        .adm-page .btn { padding: 4px 8px; font-size: 10px; min-width: 60px; }
        .adm-page .admin-photo-cell img { width: 32px; height: 32px; }
    }
</style>

<!-- ============================ CONTENT ============================ -->
<div class="adm-page">

    <div class="header">
        <h2><i class="fas fa-user-shield"></i> Manage Admins</h2>
        <p>View, add, edit, and delete administrator accounts</p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php else: ?>
        <div class="top-bar">
            <h3><?php echo $totalAdmins; ?> admin<?php echo $totalAdmins === 1 ? '' : 's'; ?></h3>
            <button class="add-btn" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Add New Admin
            </button>
        </div>

        <?php if (empty($admins)): ?>
            <div class="empty-state">
                <div style="font-size: 48px;">🛡️</div>
                <p>No admins found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $a): ?>
                            <?php $isSelf = ($a['id'] == $currentAdminId); ?>
                            <tr>
                                <td class="admin-photo-cell">
                                    <img src="<?php echo htmlspecialchars(resolveAdminImageUrl($a['profile_photo'])); ?>"
                                         alt="<?php echo htmlspecialchars($a['username']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($a['id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($a['username']); ?>
                                    <?php if ($isSelf): ?><span class="you-badge">You</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($a['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($a['email'] ?? 'N/A'); ?></td>
                                <td><?php echo $a['created_on'] ? date('M d, Y', strtotime($a['created_on'])) : 'N/A'; ?></td>
                                <td>
                                    <button class="btn btn-view" onclick="showAdminInfo(<?php echo $a['id']; ?>, this)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-edit"
                                            onclick="editAdmin(
                                                <?php echo $a['id']; ?>,
                                                <?php echo htmlspecialchars(json_encode($a['username'])); ?>,
                                                <?php echo htmlspecialchars(json_encode($a['full_name'] ?? '')); ?>,
                                                <?php echo htmlspecialchars(json_encode($a['email'] ?? '')); ?>
                                            )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($isSelf): ?>
                                        <button class="btn disabled" disabled title="You cannot delete your own account">
                                            <i class="fas fa-lock"></i> Delete
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-delete" onclick="deleteAdmin(<?php echo $a['id']; ?>, <?php echo htmlspecialchars(json_encode($a['username'])); ?>, this)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Admin Info Modal -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="adminModalTitle">Admin Information</h4>
                <span class="close" onclick="closeAdminModal()">&times;</span>
            </div>
            <div class="modal-body" id="adminModalBody">
                <div class="loading">
                    <div class="spinner"></div>
                    Loading admin information...
                </div>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Add New Admin</h4>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addForm">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" id="add_username" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" id="add_fullname" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" id="add_email" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" id="add_password" required minlength="8">
                        <small>Minimum 8 characters</small>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-plus"></i> Add Admin
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Edit Admin</h4>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit_admin_id">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" id="edit_username" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" id="edit_fullname" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label>New Password (optional):</label>
                        <input type="password" id="edit_password" minlength="8" placeholder="Leave blank to keep current password">
                        <small>Only fill this if you want to change the password</small>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Update Admin
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================ SCRIPTS ============================ -->
<script>
(function() {
    function forceReload(delayMs) {
        setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('_t', Date.now());
            window.location.replace(url.toString());
        }, delayMs || 900);
    }

    window.showAdminInfo = async function(adminId, btnEl) {
        const modal      = document.getElementById('adminModal');
        const modalBody  = document.getElementById('adminModalBody');
        const modalTitle = document.getElementById('adminModalTitle');

        if (btnEl) setBtnLoading(btnEl, true);

        modal.style.display = 'block';
        modalBody.innerHTML = `<div class="loading"><div class="spinner"></div>Loading admin information...</div>`;
        modalTitle.textContent = `Admin Information - ID: ${adminId}`;

        try {
            const response = await fetch('manage_admins.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_admin&admin_id=' + adminId,
                cache: 'no-store'
            });

            const data = await response.json();

            if (data.error) {
                modalBody.innerHTML = `<div class="error-message">${escapeHtml(data.error)}</div>`;
            } else if (data.success) {
                modalBody.innerHTML = `
                    <div class="profile-photo">
                        <img src="${escapeHtml(data.profile_photo)}" alt="Profile Photo">
                    </div>
                    <div class="info-section"><strong><i class="fas fa-user"></i> Username:</strong> ${escapeHtml(data.username)}</div>
                    <div class="info-section"><strong><i class="fas fa-id-card"></i> Full Name:</strong> ${escapeHtml(data.fullname)}</div>
                    <div class="info-section"><strong><i class="fas fa-envelope"></i> Email:</strong> ${escapeHtml(data.email || 'N/A')}</div>
                    <div class="info-section"><strong><i class="fas fa-calendar-plus"></i> Created:</strong> ${escapeHtml(data.created_on || 'N/A')}</div>
                `;
            }
        } catch (error) {
            modalBody.innerHTML = `<div class="error-message">Failed to load admin information.</div>`;
        } finally {
            if (btnEl) setBtnLoading(btnEl, false);
        }
    };

    window.showAddModal = function() {
        document.getElementById('addForm').reset();
        document.getElementById('addModal').style.display = 'block';
    };
    window.closeAddModal = function() {
        document.getElementById('addModal').style.display = 'none';
    };

    document.getElementById('addForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = e.target.querySelector('.submit-btn');
        setBtnLoading(submitBtn, true);

        const body = new URLSearchParams({
            action:   'add_admin',
            username: document.getElementById('add_username').value,
            fullname: document.getElementById('add_fullname').value,
            email:    document.getElementById('add_email').value,
            password: document.getElementById('add_password').value
        });

        try {
            const res  = await fetch('manage_admins.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                cache: 'no-store'
            });

            const raw = await res.text();
            console.log('RAW RESPONSE:', raw);

            let data;
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                console.error('Server did not return valid JSON:', raw);
                showToast('Server error — check console', 'error');
                setBtnLoading(submitBtn, false);
                return;
            }

            if (data.success) {
                showToast('Admin added successfully', 'success');
                closeAddModal();
                forceReload(900);
                return;
            } else {
                showToast(data.message || 'Error adding admin', 'error');
            }
        } catch (err) {
            console.error('Network error:', err);
            showToast('Network error', 'error');
        }

        setBtnLoading(submitBtn, false);
    });

    window.editAdmin = function(id, username, fullname, email) {
        document.getElementById('edit_admin_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_fullname').value = fullname;
        document.getElementById('edit_email').value    = email;
        document.getElementById('edit_password').value = '';
        document.getElementById('editModal').style.display = 'block';
    };
    window.closeEditModal = function() {
        document.getElementById('editModal').style.display = 'none';
    };

    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = e.target.querySelector('.submit-btn');
        setBtnLoading(submitBtn, true);

        const body = new URLSearchParams({
            action:   'update_admin',
            admin_id: document.getElementById('edit_admin_id').value,
            username: document.getElementById('edit_username').value,
            fullname: document.getElementById('edit_fullname').value,
            email:    document.getElementById('edit_email').value,
            password: document.getElementById('edit_password').value
        });

        try {
            const res  = await fetch('manage_admins.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                cache: 'no-store'
            });

            const raw = await res.text();
            console.log('RAW RESPONSE:', raw);

            let data;
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                console.error('Server did not return valid JSON:', raw);
                showToast('Server error — check console', 'error');
                setBtnLoading(submitBtn, false);
                return;
            }

            if (data.success) {
                showToast('Admin updated successfully', 'success');
                closeEditModal();
                forceReload(900);
                return;
            } else {
                showToast(data.message || 'Error updating admin', 'error');
            }
        } catch (err) {
            console.error('Network error:', err);
            showToast('Network error', 'error');
        }

        setBtnLoading(submitBtn, false);
    });

    window.deleteAdmin = function(adminId, username, btnEl) {
        if (!confirm(`Are you sure you want to delete the admin "${username}"?\n\nThis action cannot be undone.`)) return;

        if (btnEl) setBtnLoading(btnEl, true);

        const body = new URLSearchParams({
            action:   'delete_admin',
            admin_id: adminId
        });

        fetch('manage_admins.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            cache: 'no-store'
        })
        .then(res => res.text())
        .then(raw => {
            console.log('RAW RESPONSE:', raw);
            let data;
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                console.error('Server did not return valid JSON:', raw);
                showToast('Server error — check console', 'error');
                if (btnEl) setBtnLoading(btnEl, false);
                return;
            }

            if (data.success) {
                showToast('Admin deleted successfully', 'success');
                forceReload(900);
                return;
            } else {
                showToast(data.message || 'Error deleting admin', 'error');
                if (btnEl) setBtnLoading(btnEl, false);
            }
        })
        .catch(() => {
            showToast('Network error', 'error');
            if (btnEl) setBtnLoading(btnEl, false);
        });
    };

    function setBtnLoading(btn, isLoading) {
        if (!btn) return;
        if (isLoading) {
            btn.classList.add('loading');
            btn.disabled = true;
        } else {
            btn.classList.remove('loading');
            btn.disabled = false;
        }
    }

    window.closeAdminModal = function() {
        document.getElementById('adminModal').style.display = 'none';
    };

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast ${type || ''}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

    window.addEventListener('click', function(event) {
        if (event.target.classList && event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        }
    });
})();
</script>
