<?php
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
function resolveAdminImageUrl($value) {
    if (!empty($value) && preg_match('#^https?://#i', $value)) {
        return $value;
    }
    return defaultAvatarUrl();
}

/* =====================================================================
   AJAX: get_admin
   ===================================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'get_admin') {
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
        $check = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $check->execute([$username]);
        if ($check->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
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
        // Reject if the new username is taken by someone else
        $check = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id <> ?");
        $check->execute([$username, $adminId]);
        if ($check->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already taken by another admin']);
            exit();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header {
            text-align: center;
            padding: 20px 10px;
            color: black;
            background: #f5f5f5;
        }
        .header h2 { font-size: 30px; font-weight: 600; }
        .header p  { opacity: 0.9; font-size: 16px; }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .content { padding: 30px; }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-bar h3 {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
        }

        .add-btn {
            background: #2c7a7b;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .add-btn:hover { background: #236162; transform: translateY(-1px); }

        .table-responsive { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f5f5f5;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        tr:hover { background-color: #f5f5f5; }

        .admin-photo-cell img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #2c7a7b;
            background: #f4f7f9;
            vertical-align: middle;
        }

        .you-badge {
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
        }

        .btn {
            border: none;
            color: white;
            padding: 6px 12px;
            text-align: center;
            display: inline-block;
            font-size: 12px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
            margin: 2px;
            font-family: inherit;
        }

        .btn-view   { background: #2c7a7b; }
        .btn-edit   { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; }

        .btn:hover { transform: translateY(-1px); opacity: 0.9; }

        .btn:disabled,
        .btn.disabled {
            background: #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
            transform: none;
            opacity: 1;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
            overflow: hidden;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }

        .modal-header {
            background: #2c7a7b;
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h4 { margin: 0; font-size: 20px; }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
            line-height: 20px;
        }
        .close:hover { opacity: 0.8; }

        .modal-body {
            padding: 25px;
            max-height: 65vh;
            overflow-y: auto;
        }

        .form-group { margin-bottom: 15px; }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2c7a7b;
        }

        .form-group small {
            color: #6b7280;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }

        .submit-btn {
            background: #2c7a7b;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-family: inherit;
            transition: background 0.2s;
        }
        .submit-btn:hover { background: #236162; }

        .profile-photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #2c7a7b;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            background: #f4f7f9;
        }

        .info-section {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-section:last-child { border-bottom: none; }

        .info-section strong {
            color: #2c7a7b;
            display: inline-block;
            min-width: 140px;
            font-size: 14px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #2c7a7b;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2c7a7b;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 1100;
            animation: slideIn 0.3s;
        }
        .toast.success { background: #28a745; }
        .toast.error   { background: #dc3545; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        @media screen and (max-width: 768px) {
            .content { padding: 15px; }
            th, td { padding: 10px 12px; }
            .btn { padding: 4px 8px; font-size: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Manage Admins</h2>
        <p>View, add, edit, and delete administrator accounts</p>
    </div>

    <div class="container">
        <div class="content">
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
                                            <button class="btn btn-view" onclick="showAdminInfo(<?php echo $a['id']; ?>)">
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
                                                <button class="btn btn-delete" onclick="deleteAdmin(<?php echo $a['id']; ?>, <?php echo htmlspecialchars(json_encode($a['username'])); ?>)">
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
        </div>
    </div>

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
                    <button type="submit" class="submit-btn">Add Admin</button>
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
                    <button type="submit" class="submit-btn">Update Admin</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    /* ───────────────────────────────────────────────────────────
       View admin
       ─────────────────────────────────────────────────────────── */
    async function showAdminInfo(adminId) {
        const modal      = document.getElementById('adminModal');
        const modalBody  = document.getElementById('adminModalBody');
        const modalTitle = document.getElementById('adminModalTitle');

        modal.style.display = 'block';
        modalBody.innerHTML = `<div class="loading"><div class="spinner"></div>Loading admin information...</div>`;
        modalTitle.textContent = `Admin Information - ID: ${adminId}`;

        try {
            const response = await fetch('manage_admins.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_admin&admin_id=' + adminId
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
        }
    }

    /* ───────────────────────────────────────────────────────────
       Add admin
       ─────────────────────────────────────────────────────────── */
    function showAddModal() {
        document.getElementById('addForm').reset();
        document.getElementById('addModal').style.display = 'block';
    }
    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    document.getElementById('addForm').addEventListener('submit', async (e) => {
        e.preventDefault();

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
                body: body.toString()
            });
            const data = await res.json();

            if (data.success) {
                showToast('Admin added successfully', 'success');
                closeAddModal();
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Error adding admin', 'error');
            }
        } catch (err) {
            showToast('Network error', 'error');
        }
    });

    /* ───────────────────────────────────────────────────────────
       Edit admin
       ─────────────────────────────────────────────────────────── */
    function editAdmin(id, username, fullname, email) {
        document.getElementById('edit_admin_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_fullname').value = fullname;
        document.getElementById('edit_email').value    = email;
        document.getElementById('edit_password').value = '';
        document.getElementById('editModal').style.display = 'block';
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();

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
                body: body.toString()
            });
            const data = await res.json();

            if (data.success) {
                showToast('Admin updated successfully', 'success');
                closeEditModal();
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Error updating admin', 'error');
            }
        } catch (err) {
            showToast('Network error', 'error');
        }
    });

    /* ───────────────────────────────────────────────────────────
       Delete admin
       ─────────────────────────────────────────────────────────── */
    function deleteAdmin(adminId, username) {
        if (!confirm(`Are you sure you want to delete the admin "${username}"?\n\nThis action cannot be undone.`)) return;

        const body = new URLSearchParams({
            action:   'delete_admin',
            admin_id: adminId
        });

        fetch('manage_admins.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Admin deleted successfully', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Error deleting admin', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'));
    }

    /* ───────────────────────────────────────────────────────────
       Helpers
       ─────────────────────────────────────────────────────────── */
    function closeAdminModal() {
        document.getElementById('adminModal').style.display = 'none';
    }

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

    // Close modal when clicking the dark overlay
    window.addEventListener('click', function(event) {
        if (event.target.classList && event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    // Close modals on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        }
    });
    </script>
</body>
</html>
