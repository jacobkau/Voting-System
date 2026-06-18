<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Elections and their Posts
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections ORDER BY id DESC");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

    $postsByElection = [];
    foreach ($elections as $election) {
        $electionId = $election['id'];
        $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
        $postsStmt->execute([$electionId]);
        $postsByElection[$electionId] = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Election/Post fetch error: " . $e->getMessage());
    echo "Error fetching election data. Please try again.";
    exit();
}

// Handle AJAX Requests for Adding and Deleting Posts
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] == "add_post") {
        $electionId = intval($_POST['election_id']);
        $postName = trim($_POST['postname']);

        if (!empty($postName)) {
            try {
                // Check if post already exists for this election
                $checkStmt = $conn->prepare("SELECT id FROM election_posts WHERE election_id = ? AND postname = ?");
                $checkStmt->execute([$electionId, $postName]);
                if ($checkStmt->rowCount() > 0) {
                    echo json_encode(["status" => "error", "message" => "This post already exists for this election."]);
                    exit();
                }
                
                $addStmt = $conn->prepare("INSERT INTO election_posts (election_id, postname) VALUES (?, ?)");
                if ($addStmt->execute([$electionId, $postName])) {
                    $newId = $conn->lastInsertId();
                    echo json_encode(["status" => "success", "id" => $newId, "postname" => htmlspecialchars($postName)]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error adding post. Please try again."]);
                }
            } catch (Exception $e) {
                error_log("Post insert error: " . $e->getMessage());
                echo json_encode(["status" => "error", "message" => "Error adding post. Please try again."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Post name cannot be empty."]);
        }
        exit();
    }

    if ($_POST['action'] == "delete_post") {
        $deleteId = intval($_POST['delete_id']);
        if ($deleteId > 0) {
            try {
                // Check if post has any candidates before deleting
                $checkCandidatesStmt = $conn->prepare("SELECT id FROM contesters WHERE postname = (SELECT postname FROM election_posts WHERE id = ?) AND election_id = (SELECT election_id FROM election_posts WHERE id = ?)");
                $checkCandidatesStmt->execute([$deleteId, $deleteId]);
                if ($checkCandidatesStmt->rowCount() > 0) {
                    echo json_encode(["status" => "error", "message" => "Cannot delete post with existing candidates. Remove candidates first."]);
                    exit();
                }
                
                $deleteStmt = $conn->prepare("DELETE FROM election_posts WHERE id = ?");
                if ($deleteStmt->execute([$deleteId])) {
                    echo json_encode(["status" => "success"]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Database error"]);
                }
            } catch (Exception $e) {
                error_log("Post delete error: " . $e->getMessage());
                echo json_encode(["status" => "error", "message" => "Database error"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Manage Election Posts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 90%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h3 { color: #555; margin: 15px 0 10px 0; }
        .election-section { margin-bottom: 30px; border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px; background-color: #fafafa; }
        .election-section:hover { box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .post-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .post-table th, .post-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .post-table th { background-color: #3498db; color: white; font-weight: 600; }
        .post-table tr:hover { background-color: #f5f5f5; }
        input[type="text"] { 
            padding: 8px 12px; 
            margin-top: 5px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            width: 200px;
            font-size: 14px;
        }
        button { 
            padding: 8px 16px; 
            margin-top: 5px; 
            margin-left: 10px;
            background-color: #3498db; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        button:hover { background-color: #2980b9; }
        .delete-btn { 
            color: #e74c3c; 
            text-decoration: none; 
            cursor: pointer; 
            font-weight: 500;
            transition: color 0.3s;
        }
        .delete-btn:hover { color: #c0392b; text-decoration: underline; }
        .add-post-form { margin-top: 10px; }
        .empty-posts { color: #999; font-style: italic; padding: 10px; text-align: center; }
        
        @media (max-width: 768px) {
            .admin-container { width: 95%; padding: 15px; }
            input[type="text"] { width: 100%; margin-bottom: 10px; }
            button { width: 100%; margin-left: 0; }
            .post-table th, .post-table td { padding: 6px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Manage Election Posts</h2>
        
        <?php if (empty($elections)): ?>
            <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 8px;">
                <p style="color: #666;">No elections found. Please create an election first.</p>
                <button onclick="window.location.href='manage_elections.php'" style="margin-top: 10px;">Create Election</button>
            </div>
        <?php else: ?>
            <?php foreach ($elections as $election): ?>
                <div class="election-section">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <table class="post-table">
                        <thead>
                            <tr>
                                <th width="70%">Post Name</th>
                                <th width="30%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="post-list-<?php echo $election['id']; ?>">
                            <?php 
                            $posts = $postsByElection[$election['id']] ?? [];
                            if (empty($posts)): 
                            ?>
                                <tr class="empty-row-<?php echo $election['id']; ?>">
                                    <td colspan="2" class="empty-posts">No posts created yet. Add your first post below.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($posts as $post): ?>
                                    <tr id="post-<?php echo $post['id']; ?>">
                                        <td><?php echo htmlspecialchars($post['postname']); ?></td>
                                        <td>
                                            <span class="delete-btn" data-id="<?php echo $post['id']; ?>">🗑️ Delete</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <form class="add-post-form" data-election="<?php echo $election['id']; ?>">
                        <input type="text" name="postname" placeholder="Enter new post name (e.g., President, Secretary, Treasurer)" required>
                        <button type="submit">+ Add Post</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    $(document).ready(function() {
        // Delete Post
        $(document).on("click", ".delete-btn", function() {
            if (!confirm("⚠️ Are you sure you want to delete this post?\n\nThis action cannot be undone.")) return;
            
            let postId = $(this).data("id");
            let $row = $("#post-" + postId);
            
            $.post("admin_manage_posts.php", { action: "delete_post", delete_id: postId }, function(response) {
                if (response.status === "success") {
                    $row.fadeOut(400, function() { 
                        $(this).remove();
                        
                        // Check if this election section now has no posts
                        let electionSection = $row.closest('.election-section');
                        let postCount = electionSection.find('#post-list-' + electionSection.find('h3').text() + ' tr').not('.empty-row').length;
                        
                        if (postCount === 0) {
                            // Add empty message if no posts remain
                            let electionId = electionSection.find('.add-post-form').data('election');
                            let emptyRow = `<tr class="empty-row-${electionId}">
                                                <td colspan="2" class="empty-posts">No posts created yet. Add your first post below.</td>
                                            </tr>`;
                            $("#post-list-" + electionId).append(emptyRow);
                        }
                    });
                } else {
                    alert("❌ Error: " + (response.message || "Unknown error occurred"));
                }
            }, "json").fail(function(xhr) {
                alert("❌ Request failed: " + xhr.responseText);
            });
        });

        // Add Post
        $(".add-post-form").on("submit", function(e) {
            e.preventDefault(); // Prevent default form submission
            
            let electionId = $(this).data("election");
            let postNameInput = $(this).find("input[name='postname']");
            let postName = postNameInput.val().trim();
            
            if (postName === "") {
                alert("Please enter a post name.");
                return;
            }
            
            // Disable submit button to prevent double submission
            let submitBtn = $(this).find("button[type='submit']");
            submitBtn.prop("disabled", true).text("Adding...");
            
            $.post("admin_manage_posts.php", { 
                action: "add_post", 
                election_id: electionId, 
                postname: postName 
            }, function(response) {
                if (response.status === "success") {
                    let newRow = `<tr id="post-${response.id}">
                                    <td>${response.postname}</td>
                                    <td><span class="delete-btn" data-id="${response.id}">🗑️ Delete</span></td>
                                  </tr>`;
                    
                    let tbody = $("#post-list-" + electionId);
                    
                    // Remove empty message if it exists
                    tbody.find(".empty-posts").closest('tr').remove();
                    
                    // Append new row
                    tbody.append(newRow);
                    
                    // Clear input
                    postNameInput.val("");
                    
                    // Show success message briefly
                    let successMsg = $('<div style="color: green; font-size: 12px; margin-top: 5px;">✓ Post added successfully!</div>');
                    $(this).append(successMsg);
                    setTimeout(function() { successMsg.fadeOut(300, function() { $(this).remove(); }); }, 2000);
                    
                } else {
                    alert("❌ Error: " + (response.message || "Failed to add post"));
                }
            }, "json").fail(function(xhr) {
                alert("❌ Request failed: " + xhr.responseText);
            }).always(function() {
                // Re-enable submit button
                submitBtn.prop("disabled", false).text("+ Add Post");
            });
        });
    });
    </script>
</body>
</html>
