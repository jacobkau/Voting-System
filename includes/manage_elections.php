<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ─────────────────────────────────────────────────────────────
// AJAX: manage posts for an election
// ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax_action'] == 'add_post') {
        $electionId = intval($_POST['election_id']);
        $postName   = trim($_POST['postname']);

        if (empty($postName)) {
            echo json_encode(['success' => false, 'message' => 'Post name cannot be empty']);
            exit();
        }

        try {
            $checkStmt = $conn->prepare("SELECT id FROM election_posts WHERE election_id = ? AND postname = ?");
            $checkStmt->execute([$electionId, $postName]);
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'This post already exists for this election']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO election_posts (election_id, postname) VALUES (?, ?)");
            $stmt->execute([$electionId, $postName]);
            $newId = $conn->lastInsertId();

            echo json_encode([
                'success'  => true,
                'message'  => 'Post added successfully',
                'id'       => $newId,
                'postname' => $postName
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['ajax_action'] == 'delete_post') {
        $postId = intval($_POST['post_id']);

        try {
            $checkStmt = $conn->prepare("
                SELECT id FROM contesters
                WHERE postname = (SELECT postname FROM election_posts WHERE id = ?)
                  AND election_id = (SELECT election_id FROM election_posts WHERE id = ?)
            ");
            $checkStmt->execute([$postId, $postId]);
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete post with existing candidates']);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM election_posts WHERE id = ?");
            $stmt->execute([$postId]);

            echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['ajax_action'] == 'get_posts') {
        $electionId = intval($_POST['election_id']);

        try {
            $stmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
            $stmt->execute([$electionId]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'posts' => $posts]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// ─────────────────────────────────────────────────────────────
// Handle Edit Election — extending the period happens here
// ─────────────────────────────────────────────────────────────
if (isset($_POST['edit_election'])) {
    $electionId  = intval($_POST['election_id']);
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];

    if (empty($title)) {
        $errorMsg = "Please enter an election title.";
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $errorMsg = "End date must be later than start date.";
    } else {
        try {
            // Note: editing does NOT touch manual_status / manual_until.
            // The override stays in place until the admin clears it via the dropdown.
            $stmt = $conn->prepare("
                UPDATE elections
                SET title = ?, description = ?, start_date = ?, end_date = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $start_date, $end_date, $electionId]);

            // Detected an extension? Give a nicer message.
            $oldStart = strtotime($_POST['__old_start'] ?? $start_date);
            $oldEnd   = strtotime($_POST['__old_end']   ?? $end_date);
            $extended = strtotime($end_date) > $oldEnd;

            $successMsg = $extended
                ? "Election period extended. The status will update automatically on the next page load."
                : "Election updated successfully.";
        } catch (PDOException $e) {
            $errorMsg = "Error updating election: " . $e->getMessage();
        }
    }
}

// ─────────────────────────────────────────────────────────────
// Handle Delete Election
// ─────────────────────────────────────────────────────────────
if (isset($_POST['delete_election'])) {
    $electionId = intval($_POST['election_id']);

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("DELETE FROM election_posts WHERE election_id = ?");
        $stmt->execute([$electionId]);

        $stmt = $conn->prepare("DELETE FROM user_elections WHERE election_id = ?");
        $stmt->execute([$electionId]);

        $stmt = $conn->prepare("DELETE FROM contesters WHERE election_id = ?");
        $stmt->execute([$electionId]);

        $stmt = $conn->prepare("DELETE FROM votes WHERE election_id = ?");
        $stmt->execute([$electionId]);

        $stmt = $conn->prepare("DELETE FROM elections WHERE id = ?");
        $stmt->execute([$electionId]);

        $conn->commit();
        $successMsg = "Election deleted successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $errorMsg = "Error deleting election: " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────
// Handle election status updates (manual override)
// ─────────────────────────────────────────────────────────────
if (isset($_POST['update_election_status'])) {
    $electionId = intval($_POST['election_id']);
    $override   = $_POST['status'] ?? 'auto';

    try {
        if ($override === 'auto' || $override === '') {
            $stmt = $conn->prepare("UPDATE elections SET manual_status = NULL, manual_until = NULL WHERE id = ?");
            $stmt->execute([$electionId]);
            $successMsg = "Election reverted to automatic scheduling.";
        } elseif (in_array($override, ['upcoming', 'active', 'completed'], true)) {
            $stmt = $conn->prepare("UPDATE elections SET manual_status = ? WHERE id = ?");
            $stmt->execute([$override, $electionId]);
            $successMsg = "Election status manually set to " . ucfirst($override) . ".";
        } else {
            $errorMsg = "Invalid status value.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Error updating election status: " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────
// Add Election
// ─────────────────────────────────────────────────────────────
if (isset($_POST['add_election'])) {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];
    $status      = $_POST['status'] ?? 'upcoming';

    if (empty($title)) {
        $errorMsg = "Please enter an election title.";
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $errorMsg = "End date must be later than start date.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO elections (title, description, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$title, $description, $start_date, $end_date, $status])) {
                $successMsg = "Election added successfully.";
            } else {
                $errorMsg = "Error adding election.";
            }
        } catch (PDOException $e) {
            error_log("Error adding election: " . $e->getMessage());
            $errorMsg = "Error adding election: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────
function getElections($conn) {
    try {
        $result = $conn->query("SELECT * FROM elections ORDER BY id DESC");
        $elections = [];
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $elections[] = $row;
            }
        }
        return $elections;
    } catch (PDOException $e) {
        error_log("Error fetching elections: " . $e->getMessage());
        return [];
    }
}

function getCandidateCountForElection($conn, $electionId) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(c.id) as count FROM contesters c WHERE c.election_id = ?");
        $stmt->execute([$electionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getRegisteredVoterCountForElection($conn, $electionId) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM user_elections WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

$elections = getElections($conn);

$editElection = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    foreach ($elections as $e) {
        if ($e['id'] == $editId) {
            $editElection = $e;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Elections | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error   { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }

        select.status-select {
            padding: 6px 8px;
            margin-right: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            color: #1f2937;
            background-color: #ffffff;
            -webkit-text-fill-color: #1f2937;
            cursor: pointer;
            vertical-align: middle;
        }
        select.status-select option {
            color: #1f2937;
            background-color: #ffffff;
        }
        select.status-select:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
        }

        .election-form-container {
            width: 80%; max-width: 600px; margin: 20px auto;
            background-color: #fff; padding: 20px;
            border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .election-form-container h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .election-form-container form { display: flex; flex-wrap: wrap; gap: 10px; }

        .election-form-container input[type="text"],
        .election-form-container input[type="datetime-local"],
        .election-form-container textarea,
        .election-form-container select {
            flex: 1 1 100%; padding: 10px; border: 1px solid #ccc;
            border-radius: 4px; box-sizing: border-box; font-size: 16px;
            color: #1f2937; background-color: #ffffff;
            -webkit-text-fill-color: #1f2937;
        }
        .election-form-container textarea { resize: vertical; font-family: inherit; }

        .election-form-container button[type="submit"] {
            flex: 1 1 100%; background-color: #3498db; color: white;
            padding: 12px 20px; border: none; border-radius: 4px;
            cursor: pointer; font-size: 16px; transition: background-color 0.3s ease;
            font-family: inherit;
        }
        .election-form-container button[type="submit"]:hover { background-color: #2980b9; }

        .edit-hint {
            width: 100%;
            background: #e0f2fe;
            color: #075985;
            border: 1px solid #bae6fd;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .edit-hint i { margin-right: 6px; }

        .btn { display: inline-block; padding: 5px 10px; margin: 2px;
               border: none; border-radius: 4px; cursor: pointer;
               font-size: 12px; text-decoration: none; font-family: inherit; }
        .btn-edit    { background-color: #ffc107; color: #333; }
        .btn-delete  { background-color: #dc3545; color: white; }
        .btn-posts   { background-color: #17a2b8; color: white; }
        .btn-sm      { padding: 4px 8px; font-size: 11px; }

        td form button {
            background-color: #4CAF50; color: white; border: none;
            border-radius: 4px; cursor: pointer; padding: 6px 12px;
            font-size: 12px; font-family: inherit;
            vertical-align: middle;
        }
        td form button:hover { background-color: #45a049; }

        .manual-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            background: #fef3c7;
            color: #92400e;
            font-size: 10px;
            border-radius: 8px;
            font-weight: 700;
            vertical-align: middle;
        }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0;
                 width: 100%; height: 100%; overflow: auto;
                 background-color: rgba(0, 0, 0, 0.6); }
        .modal-content { background-color: white; margin: 5% auto; padding: 0;
                         border-radius: 8px; width: 90%; max-width: 500px;
                         box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); }
        .modal-header { padding: 15px 20px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white; border-radius: 8px 8px 0 0;
                        display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; }
        .close { color: white; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-body { padding: 20px; max-height: 60vh; overflow-y: auto; }
        .posts-list { margin-bottom: 15px; }
        .post-item { display: flex; justify-content: space-between; align-items: center;
                     padding: 8px; border-bottom: 1px solid #eee; }
        .post-item:hover { background-color: #f5f5f5; }
        .delete-post { color: #dc3545; cursor: pointer; }
        .add-post-form { display: flex; gap: 10px; margin-top: 15px; }
        .add-post-form input { flex: 1; padding: 8px; border: 1px solid #ddd;
                               border-radius: 4px; color: #1f2937;
                               background-color: #ffffff;
                               -webkit-text-fill-color: #1f2937; }
        .add-post-form button { padding: 8px 15px; background-color: #28a745; color: white;
                                border: none; border-radius: 4px; cursor: pointer;
                                font-family: inherit; }

        @media (max-width: 768px) {
            .election-form-container { width: 95%; }
            table { font-size: 12px; }
            th, td { padding: 5px; }
            select.status-select { font-size: 11px; padding: 4px 6px; }
        }
    </style>
</head>
<body>
    <?php if (isset($successMsg)): ?>
        <div class="message success"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="message error"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <div class="election-form-container">
        <h2><?php echo $editElection ? 'Edit Election' : 'Add New Election'; ?></h2>

        <?php if ($editElection): ?>
            <div class="edit-hint">
                <i class="fas fa-info-circle"></i>
                <strong>To extend the voting period</strong>, push the <em>End Date</em> forward and save.
                The status will update automatically — no manual change needed.
                Use the row dropdown below only if you want to <em>override</em> the automatic schedule.
            </div>
        <?php endif; ?>

        <form method="post" action="main.php?page=elections">
            <?php if ($editElection): ?>
                <input type="hidden" name="election_id" value="<?php echo $editElection['id']; ?>">
                <input type="hidden" name="__old_start" value="<?php echo htmlspecialchars($editElection['start_date']); ?>">
                <input type="hidden" name="__old_end"   value="<?php echo htmlspecialchars($editElection['end_date']); ?>">
            <?php endif; ?>

            <input type="text" name="title" placeholder="Election Title" required
                   value="<?php echo $editElection ? htmlspecialchars($editElection['title']) : ''; ?>">

            <textarea name="description" placeholder="Election Description" rows="3"><?php echo $editElection ? htmlspecialchars($editElection['description'] ?? '') : ''; ?></textarea>

            <input type="datetime-local" name="start_date" required
                   value="<?php echo $editElection ? date('Y-m-d\TH:i', strtotime($editElection['start_date'])) : ''; ?>">

            <input type="datetime-local" name="end_date" required
                   value="<?php echo $editElection ? date('Y-m-d\TH:i', strtotime($editElection['end_date'])) : ''; ?>">

            <?php if (!$editElection): ?>
                <select name="status" required>
                    <option value="upcoming" selected>Upcoming</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                </select>
            <?php endif; ?>

            <button type="submit" name="<?php echo $editElection ? 'edit_election' : 'add_election'; ?>">
                <?php echo $editElection ? 'Update Election' : 'Add Election'; ?>
            </button>

            <?php if ($editElection): ?>
                <a href="main.php?page=elections" style="text-align: center; display: block; margin-top: 10px; color: #666;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($elections)): ?>
        <p style="text-align: center; color: #666;">No elections found. Create your first election above.</p>
    <?php else: ?>
        <h2>Existing Elections</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Candidates</th>
                        <th>Registered Voters</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($elections as $election) : ?>
                        <?php
                        $liveStatus  = effectiveElectionStatus($election);
                        $statusColor = $liveStatus === 'active'
                            ? 'green'
                            : ($liveStatus === 'completed' ? 'red' : 'orange');
                        $hasOverride = !empty($election['manual_status'])
                            && (empty($election['manual_until']) || strtotime($election['manual_until']) > time());
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($election['title']); ?></td>
                            <td><?php echo htmlspecialchars(substr($election['description'] ?? '', 0, 50)) . (strlen($election['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($election['start_date']))); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($election['end_date']))); ?></td>
                            <td>
                                <span style="color: <?php echo $statusColor; ?>; font-weight: bold;">
                                    <?php echo ucfirst(htmlspecialchars($liveStatus)); ?>
                                </span>
                                <?php if ($hasOverride): ?>
                                    <span class="manual-badge" title="Manually overridden by admin">MANUAL</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo getCandidateCountForElection($conn, $election['id']); ?></td>
                            <td><?php echo getRegisteredVoterCountForElection($conn, $election['id']); ?></td>
                            <td>
                                <form method="post" action="main.php?page=elections" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                                    <select name="status" class="status-select">
                                        <?php $overrideValue = !empty($election['manual_status']) ? $election['manual_status'] : 'auto'; ?>
                                        <option value="auto"      <?php echo $overrideValue === 'auto'      ? 'selected' : ''; ?>>Auto (from dates)</option>
                                        <option value="upcoming"  <?php echo $overrideValue === 'upcoming'  ? 'selected' : ''; ?>>Upcoming</option>
                                        <option value="active"    <?php echo $overrideValue === 'active'    ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo $overrideValue === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                    <button type="submit" name="update_election_status">Update</button>
                                </form>

                                <a href="main.php?page=elections&edit_id=<?php echo $election['id']; ?>" class="btn btn-edit btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <button onclick="deleteElection(<?php echo $election['id']; ?>)" class="btn btn-delete btn-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </button>

                                <button onclick="managePosts(<?php echo $election['id']; ?>, '<?php echo htmlspecialchars(addslashes($election['title'])); ?>')" class="btn btn-posts btn-sm">
                                    <i class="fas fa-clipboard-list"></i> Manage Posts
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Manage Posts Modal -->
    <div id="postsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="postsModalTitle">Manage Posts</h4>
                <span class="close" onclick="closePostsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="postsList" class="posts-list">
                    <div style="text-align: center; padding: 20px;">Loading posts...</div>
                </div>
                <div class="add-post-form">
                    <input type="text" id="newPostName" placeholder="Enter post name (e.g., President, Secretary)">
                    <button onclick="addPost()">Add Post</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let currentElectionId = null;
    const ajaxUrl     = 'manage_elections.php';
    const mainPageUrl = 'main.php?page=elections';

    async function apiFetch(url, params) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });
    }

    function managePosts(electionId, electionTitle) {
        currentElectionId = electionId;
        document.getElementById('postsModalTitle').innerHTML = 'Manage Posts - ' + electionTitle;
        document.getElementById('postsModal').style.display = 'block';
        loadPosts();
    }

    function closePostsModal() {
        document.getElementById('postsModal').style.display = 'none';
        currentElectionId = null;
    }

    async function loadPosts() {
        if (!currentElectionId) return;

        const postsList = document.getElementById('postsList');
        postsList.innerHTML = '<div style="text-align: center; padding: 20px;">Loading posts...</div>';

        try {
            const formData = new URLSearchParams();
            formData.append('ajax_action', 'get_posts');
            formData.append('election_id', currentElectionId);

            const response = await apiFetch(ajaxUrl, formData);
            const data = await response.json();

            if (data.success && data.posts.length > 0) {
                let html = '';
                data.posts.forEach(post => {
                    html += `
                        <div class="post-item">
                            <span>${escapeHtml(post.postname)}</span>
                            <span class="delete-post" onclick="deletePost(${post.id})">🗑️ Delete</span>
                        </div>
                    `;
                });
                postsList.innerHTML = html;
            } else if (data.success && data.posts.length === 0) {
                postsList.innerHTML = '<p style="text-align: center; color: #999;">No posts created yet. Add your first post above.</p>';
            } else {
                postsList.innerHTML = '<p style="text-align: center; color: red;">Error loading posts: ' + (data.message || 'Unknown error') + '</p>';
            }
        } catch (error) {
            console.error('Error:', error);
            postsList.innerHTML = '<p style="text-align: center; color: red;">Error loading posts.</p>';
        }
    }

    async function addPost() {
        const postName = document.getElementById('newPostName').value.trim();

        if (!postName) {
            alert('Please enter a post name');
            return;
        }
        if (!currentElectionId) return;

        try {
            const formData = new URLSearchParams();
            formData.append('ajax_action', 'add_post');
            formData.append('election_id', currentElectionId);
            formData.append('postname', postName);

            const response = await apiFetch(ajaxUrl, formData);
            const data = await response.json();

            if (data.success) {
                document.getElementById('newPostName').value = '';
                loadPosts();
            } else {
                alert(data.message || 'Error adding post');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error adding post');
        }
    }

    async function deletePost(postId) {
        if (!confirm('Are you sure you want to delete this post?')) return;

        try {
            const formData = new URLSearchParams();
            formData.append('ajax_action', 'delete_post');
            formData.append('post_id', postId);

            const response = await apiFetch(ajaxUrl, formData);
            const data = await response.json();

            if (data.success) {
                loadPosts();
            } else {
                alert(data.message || 'Error deleting post');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error deleting post');
        }
    }

    function deleteElection(electionId) {
        if (confirm('Are you sure you want to delete this election? This will also delete all associated posts, candidates, votes, and user registrations. This action cannot be undone!')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = mainPageUrl;

            const delInput = document.createElement('input');
            delInput.type  = 'hidden';
            delInput.name  = 'delete_election';
            delInput.value = '1';
            form.appendChild(delInput);

            const idInput = document.createElement('input');
            idInput.type  = 'hidden';
            idInput.name  = 'election_id';
            idInput.value = electionId;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    window.onclick = function(event) {
        const modal = document.getElementById('postsModal');
        if (event.target === modal) {
            closePostsModal();
        }
    }
    </script>
</body>
</html>
