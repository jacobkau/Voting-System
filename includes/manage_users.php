<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Admin Authentication (optional - remove if not needed)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Handle AJAX request for user info
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'get_user') {
    header('Content-Type: application/json');
    
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($userId <= 0) {
        echo json_encode(['error' => 'Invalid user ID']);
        exit();
    }
    
    try {
        $userStmt = $conn->prepare("SELECT id, username, name as fullname, email, profile_photo, profile_photo_blob, profile_photo_type, date as registered_date FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        
        $registrations = [];
        try {
            $regStmt = $conn->prepare("SELECT e.id, e.title, e.status FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
            $regStmt->execute([$userId]);
            $registrations = $regStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $registrations = []; }

        $votes = [];
        try {
            $votesStmt = $conn->prepare("SELECT election_id, postname, candidate_name, voted_at FROM votes WHERE user_id = ? ORDER BY voted_at DESC");
            $votesStmt->execute([$userId]);
            $votes = $votesStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($votes as &$vote) {
                $electionStmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
                $electionStmt->execute([$vote['election_id']]);
                $election = $electionStmt->fetch(PDO::FETCH_ASSOC);
                $vote['title'] = $election['title'] ?? 'Unknown Election';
            }
        } catch (Exception $e) { $votes = []; }
        
        $contests = [];
        try {
            $contestsStmt = $conn->prepare("SELECT c.postname, e.title as election FROM contesters c JOIN elections e ON c.election_id = e.id WHERE c.user_id = ?");
            $contestsStmt->execute([$userId]);
            $contests = $contestsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $contests = []; }
        
        $profilePhoto = null;
        $profilePhotoBlob = null;
        $profilePhotoType = null;
        
        if (!empty($user['profile_photo_blob'])) {
            $profilePhotoBlob = base64_encode($user['profile_photo_blob']);
            $profilePhotoType = $user['profile_photo_type'];
        } elseif (!empty($user['profile_photo'])) {
            $profilePhoto = $user['profile_photo'];
        }
        
        echo json_encode([
            'success' => true,
            'id' => $user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'] ?? 'N/A',
            'email' => $user['email'],
            'profile_photo' => $profilePhoto,
            'profile_photo_blob' => $profilePhotoBlob,
            'profile_photo_type' => $profilePhotoType,
            'registered_date' => $user['registered_date'],
            'registrations' => $registrations,
            'votes' => $votes,
            'contests' => $contests
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle Delete User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    header('Content-Type: application/json');
    
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Delete user's votes
        $stmt = $conn->prepare("DELETE FROM votes WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete user's contest applications
        $stmt = $conn->prepare("DELETE FROM contesters WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete user's election registrations
        $stmt = $conn->prepare("DELETE FROM user_elections WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle Register User to Election
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'register_election') {
    header('Content-Type: application/json');
    
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : 0;
    
    if ($userId <= 0 || $electionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID or election ID']);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO user_elections (user_id, election_id) VALUES (?, ?)");
        $stmt->execute([$userId, $electionId]);
        
        echo json_encode(['success' => true, 'message' => 'User registered to election successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle Update User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_user') {
    header('Content-Type: application/json');
    
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if ($userId <= 0 || empty($username) || empty($fullname) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, email = ? WHERE id = ?");
        $stmt->execute([$username, $fullname, $email, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch all users from the database
$users = [];
$errorMessage = "";

try {
    $stmt = $conn->prepare("SELECT id, username, name as full_name, email, profile_photo, date as registered_date FROM users ORDER BY id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $errorMessage = "Error loading users. Please try again later.";
}

// Fetch all elections for registration dropdown
$elections = [];
try {
    $electionStmt = $conn->prepare("SELECT id, title, status FROM elections ORDER BY title");
    $electionStmt->execute();
    $elections = $electionStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $elections = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header {
            text-align: center;
            padding: 20px 10px;
            color: black;
            background: #f5f5f5;
        }

        .header h2 {
            font-size: 30px;
            font-weight: 600;
        }

        .header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .content {
            padding: 30px;
        }

        .table-responsive {
            overflow-x: auto;
        }

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

        tr:hover {
            background-color: #f5f5f5;
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
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-edit {
            background: #ffc107;
            color: #333;
        }

        .btn-delete {
            background: #dc3545;
        }

        .btn-register {
            background: #28a745;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
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
            to { opacity: 1; }
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
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h4 {
            margin: 0;
            font-size: 20px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
            line-height: 20px;
        }

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 25px;
            max-height: 65vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        .profile-photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-section:last-child {
            border-bottom: none;
        }

        .info-section strong {
            color: #667eea;
            display: inline-block;
            min-width: 140px;
            font-size: 14px;
        }

        .info-list {
            margin: 8px 0 0 20px;
            padding-left: 20px;
        }

        .info-list li {
            margin: 8px 0;
            color: #555;
        }

        .election-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-upcoming {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
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
        .toast.error { background: #dc3545; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media screen and (max-width: 768px) {
            .content {
                padding: 15px;
            }
            th, td {
                padding: 10px 12px;
            }
            .btn {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Manage Users</h2>
        <p>View, edit, delete, and manage user election registrations</p>    
    </div>
    <div class="container">               
        <div class="content">
            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php elseif (empty($users)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px;">👥</div>
                    <p>No users found in the database.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['registered_date'] ? date('M d, Y', strtotime($user['registered_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="btn btn-view" onclick="showUserInfo(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-edit" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <button class="btn btn-register" onclick="showRegisterModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-plus"></i> Register Election
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Info Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="modalTitle">User Information</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">
                    <div class="spinner"></div>
                    Loading user information...
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Edit User</h4>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit_user_id">
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
                    <button type="submit" class="submit-btn">Update User</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Register Election Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Register User to Election</h4>
                <span class="close" onclick="closeRegisterModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="registerForm">
                    <input type="hidden" id="register_user_id">
                    <div class="form-group">
                        <label>User:</label>
                        <input type="text" id="register_username" readonly style="background:#f5f5f5">
                    </div>
                    <div class="form-group">
                        <label>Select Election:</label>
                        <select id="register_election_id" required>
                            <option value="">-- Select Election --</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['title']); ?> (<?php echo $election['status']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="submit-btn">Register User</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    async function showUserInfo(userId) {
        const modal = document.getElementById('userModal');
        const modalBody = document.getElementById('modalBody');
        const modalTitle = document.getElementById('modalTitle');
        
        modal.style.display = 'block';
        modalBody.innerHTML = `<div class="loading"><div class="spinner"></div>Loading user information...</div>`;
        modalTitle.textContent = `User Information - ID: ${userId}`;
        
        try {
            const response = await fetch('manage_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_user&user_id=' + userId
            });
            
            const data = await response.json();
            
            if (data.error) {
                modalBody.innerHTML = `<div class="error-message">❌ ${data.error}</div>`;
            } else if (data.success) {
                displayUserInfo(data);
            }
        } catch (error) {
            modalBody.innerHTML = `<div class="error-message">❌ Failed to load user information.</div>`;
        }
    }
    
    function displayUserInfo(data) {
        const modalBody = document.getElementById('modalBody');
        
        let profilePhotoHtml = '';
        if (data.profile_photo_blob) {
            profilePhotoHtml = `<img src="data:image/${data.profile_photo_type};base64,${data.profile_photo_blob}" alt="Profile Photo">`;
        } else if (data.profile_photo && data.profile_photo !== '') {
            profilePhotoHtml = `<img src="uploads/${data.profile_photo}" alt="Profile Photo" onerror="this.src='faces/default.jpg'">`;
        } else {
            profilePhotoHtml = `<img src="faces/default.jpg" alt="Default Profile Photo">`;
        }
        
        let registrationsHtml = '';
        if (data.registrations && data.registrations.length > 0) {
            registrationsHtml = '<ul class="info-list">';
            data.registrations.forEach(reg => {
                let statusClass = reg.status === 'active' ? 'status-active' : (reg.status === 'upcoming' ? 'status-upcoming' : 'status-completed');
                registrationsHtml += `<li> ${escapeHtml(reg.title)} <span class="election-status ${statusClass}">${escapeHtml(reg.status)}</span></li>`;
            });
            registrationsHtml += '</ul>';
        } else {
            registrationsHtml = '<p style="color:#999;">No election registrations</p>';
        }
        
        modalBody.innerHTML = `
            <div class="profile-photo">${profilePhotoHtml}</div>
            <div class="info-section"><strong> Username:</strong> ${escapeHtml(data.username)}</div>
            <div class="info-section"><strong> Full Name:</strong> ${escapeHtml(data.fullname)}</div>
            <div class="info-section"><strong> Email:</strong> ${escapeHtml(data.email)}</div>
            <div class="info-section"><strong> Registered:</strong> ${escapeHtml(data.registered_date) || 'N/A'}</div>
            <div class="info-section"><strong> Election Registrations:</strong> ${registrationsHtml}</div>
        `;
    }
    
    function editUser(id, username, fullname, email) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_fullname').value = fullname;
        document.getElementById('edit_email').value = email;
        document.getElementById('editModal').style.display = 'block';
    }
    
    function deleteUser(userId) {
        if (confirm('⚠️ Are you sure you want to delete this user? This action cannot be undone and will delete all votes, applications, and registrations associated with this user.')) {
            fetch('manage_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_user&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('User deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'error');
                }
            });
        }
    }
    
    function showRegisterModal(userId, username) {
        document.getElementById('register_user_id').value = userId;
        document.getElementById('register_username').value = username;
        document.getElementById('register_election_id').value = '';
        document.getElementById('registerModal').style.display = 'block';
    }
    
    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const userId = document.getElementById('edit_user_id').value;
        
        const response = await fetch('manage_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_user&user_id=${userId}&username=${encodeURIComponent(document.getElementById('edit_username').value)}&fullname=${encodeURIComponent(document.getElementById('edit_fullname').value)}&email=${encodeURIComponent(document.getElementById('edit_email').value)}`
        });
        
        const data = await response.json();
        if (data.success) {
            showToast('User updated successfully', 'success');
            closeEditModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    });
    
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const userId = document.getElementById('register_user_id').value;
        const electionId = document.getElementById('register_election_id').value;
        
        if (!electionId) {
            showToast('Please select an election', 'error');
            return;
        }
        
        const response = await fetch('manage_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=register_election&user_id=${userId}&election_id=${electionId}`
        });
        
        const data = await response.json();
        if (data.success) {
            showToast('User registered to election successfully', 'success');
            closeRegisterModal();
        } else {
            showToast(data.message, 'error');
        }
    });
    
    function closeModal() { document.getElementById('userModal').style.display = 'none'; }
    function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
    function closeRegisterModal() { document.getElementById('registerModal').style.display = 'none'; }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
</body>
</html>
