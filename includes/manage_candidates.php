<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Cloudinary helpers (uploadToCloudinary, extractPublicIdFromUrl, defaultAvatarUrl)
require_once __DIR__ . '/../cloudinary.php';

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Elections
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Election fetch error: " . $e->getMessage());
    echo "Error fetching election data. Please try again.";
    exit();
}

function fetchUsersForElection($conn, $electionId) {
    if (empty($electionId)) return [];
    $users = [];
    try {
        $stmt = $conn->prepare("SELECT u.id, u.username FROM users u JOIN user_elections er ON u.id = er.user_id WHERE er.election_id = ?");
        $stmt->execute([$electionId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Fetch users for election error: " . $e->getMessage());
    }
    return $users;
}

function fetchPositionsForElection($conn, $electionId) {
    if (empty($electionId)) return [];
    $positions = [];
    try {
        $stmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Fetch positions for election error: " . $e->getMessage());
    }
    return $positions;
}

$selectedElectionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_POST['election_id']) ? intval($_POST['election_id']) : null);

$usersForSelectedElection = [];
$positionsForSelectedElection = [];
if ($selectedElectionId) {
    $usersForSelectedElection = fetchUsersForElection($conn, $selectedElectionId);
    $positionsForSelectedElection = fetchPositionsForElection($conn, $selectedElectionId);
}

// Handle Candidate Addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "add_candidate") {
    verifyCsrf();
    $userId = intval($_POST['name']);
    $electionId = intval($_POST['election_id']);
    $position = trim($_POST['position']);
    $bio = trim($_POST['bio']);
    $profilePhotoUrl = "";

    // Upload image to Cloudinary
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $profilePhotoUrl = uploadToCloudinary($_FILES['profile_photo']['tmp_name'], 'candidates');
        if (!$profilePhotoUrl) {
            echo "<p style='color:red;'>Error uploading profile photo to Cloudinary.</p>";
        }
    }

    try {
        $checkRegStmt = $conn->prepare("SELECT 1 FROM user_elections WHERE user_id = ? AND election_id = ?");
        $checkRegStmt->execute([$userId, $electionId]);
        if ($checkRegStmt->rowCount() === 0) {
            throw new Exception("User is not registered for the selected election.");
        }

        $checkPostStmt = $conn->prepare("SELECT 1 FROM election_posts WHERE postname = ? AND election_id = ?");
        $checkPostStmt->execute([$position, $electionId]);
        if ($checkPostStmt->rowCount() === 0) {
            throw new Exception("Position not found for the selected election.");
        }

        $checkCandidateStmt = $conn->prepare("SELECT 1 FROM contesters WHERE user_id = ? AND election_id = ? AND postname = ?");
        $checkCandidateStmt->execute([$userId, $electionId, $position]);
        if ($checkCandidateStmt->rowCount() > 0) {
            throw new Exception("User is already a candidate for this position in this election.");
        }

        $stmt = $conn->prepare("INSERT INTO contesters (user_id, election_id, postname, bio, profile_photo) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $electionId, $position, $bio, $profilePhotoUrl])) {
            echo "<p style='color:green;'>Candidate added successfully.</p>";
            echo "<script>setTimeout(function(){ window.location.href = window.location.pathname + '?election_id=' + $electionId; }, 1000);</script>";
        } else {
            throw new Exception("Failed to insert candidate.");
        }
    } catch (Exception $e) {
        error_log("Candidate insert error: " . $e->getMessage());
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// Handle AJAX Requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    verifyCsrf();
    header('Content-Type: application/json');

    if ($_POST['action'] == "delete_candidate") {
        $candidateId = intval($_POST['delete_id']);
        if ($candidateId > 0) {
            try {
                // Fetch profile photo URL
                $stmt_select = $conn->prepare("SELECT profile_photo FROM contesters WHERE id = ?");
                $stmt_select->execute([$candidateId]);
                $candidate = $stmt_select->fetch(PDO::FETCH_ASSOC);
                
                if ($candidate && !empty($candidate['profile_photo'])) {
                    // Delete image from Cloudinary
                    $publicId = extractPublicIdFromUrl($candidate['profile_photo']);
                    if ($publicId) {
                        try {
                            $upload = new \Cloudinary\Api\Upload\UploadApi();
                            $upload->destroy($publicId);
                        } catch (Exception $ce) {
                            error_log("Cloudinary delete error: " . $ce->getMessage());
                        }
                    }
                }

                $stmt_delete = $conn->prepare("DELETE FROM contesters WHERE id = ?");
                if ($stmt_delete->execute([$candidateId])) {
                    echo json_encode(["status" => "success"]);
                } else {
                    throw new Exception("Error deleting candidate");
                }
            } catch (Exception $e) {
                error_log("Candidate delete error: " . $e->getMessage());
                echo json_encode(["status" => "error", "message" => "Database error"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        }
        exit();
    }
}

// Fetch Candidates
try {
    $candidatesStmt = $conn->prepare("SELECT c.*, e.title AS election_title, u.username FROM contesters c JOIN elections e ON c.election_id = e.id JOIN users u ON c.user_id = u.id ORDER BY c.id DESC");
    $candidatesStmt->execute();
    $candidates = $candidatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Candidate fetch error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo "Error fetching candidate data. Please try again.";
    $candidates = [];
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Manage Candidates</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 90%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .form-container { margin-bottom: 20px; padding: 15px; background: #e9ecef; border-radius: 8px; }
        .form-container input, .form-container select, .form-container textarea { width: 98%; padding: 8px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px; }
        .form-container button { background: #2c7a7b; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; }
        .form-container button:hover { background: #236162; }
        .candidate-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .candidate-table th, .candidate-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .candidate-table th { background-color: #2c7a7b; color: white; }
        .delete-btn { color: red; text-decoration: none; cursor: pointer; }
        .delete-btn:hover { text-decoration: underline; }
        .profile-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #2c7a7b; background: #f4f7f9; }
        .success-msg { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error-msg { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .upload-note { font-size: 12px; color: #666; margin-top: -3px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Manage Candidates</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-msg">Candidate added successfully!</div>
        <?php endif; ?>

        <div class="form-container">
            <h3>Add Candidate</h3>
            <form id="add-candidate-form" enctype="multipart/form-data" method="POST">
                 <?= csrfField() ?>
                <select name="election_id" id="election-select" required onchange="this.form.submit();">
                    <option value="">Select Election</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>" <?php if ($selectedElectionId == $election['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($election['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="name" id="candidate-select" required>
                    <option value="">Select Candidate</option>
                    <?php foreach ($usersForSelectedElection as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="position" id="position-select" required>
                    <option value="">Select Position</option>
                    <?php foreach ($positionsForSelectedElection as $position): ?>
                        <option value="<?php echo htmlspecialchars($position['postname']); ?>">
                            <?php echo htmlspecialchars($position['postname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <textarea name="bio" placeholder="Candidate Bio" rows="4" required></textarea>
                <input type="file" name="profile_photo" accept="image/*" required>
                <div class="upload-note">Image will be uploaded to Cloudinary (max 500x500, face-centered crop).</div>
                <br>
                <button type="submit" name="action" value="add_candidate">Add Candidate</button>
            </form>
        </div>

        <h3>Existing Candidates</h3>
        <?php if (empty($candidates)): ?>
            <p>No candidates found.</p>
        <?php else: ?>
            <table class="candidate-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Profile Picture</th>
                        <th>Name</th>
                        <th>Election</th>
                        <th>Position</th>
                        <th>Bio</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="candidate-list">
                    <?php foreach ($candidates as $candidate): ?>
                        <?php
                        $imgSrc = (!empty($candidate['profile_photo']) && preg_match('#^https?://#i', $candidate['profile_photo']))
                            ? $candidate['profile_photo']
                            : defaultAvatarUrl();
                        ?>
                        <tr id="candidate-<?php echo $candidate['id']; ?>">
                            <td><?php echo $candidate['id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Profile Photo" class="profile-img">
                            </td>
                            <td><?php echo htmlspecialchars($candidate['username']); ?></td>
                            <td><?php echo htmlspecialchars($candidate['election_title']); ?></td>
                            <td><?php echo htmlspecialchars($candidate['postname']); ?></td>
                            <td><?php echo htmlspecialchars(substr($candidate['bio'], 0, 100)) . (strlen($candidate['bio']) > 100 ? '...' : ''); ?></td>
                            <td><span class="delete-btn" data-id="<?php echo $candidate['id']; ?>">Delete</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    $(document).ready(function() {
        $(document).on("click", ".delete-btn", function() {
            if (!confirm("Are you sure you want to delete this candidate?")) return;
            let candidateId = $(this).data("id");
            $.post("manage_candidates.php", { action: "delete_candidate", delete_id: candidateId }, function(response) {
                if (response.status === "success") {
                    $("#candidate-" + candidateId).fadeOut(500, function() { 
                        $(this).remove();
                        if ($("#candidate-list tr").length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert("Error: " + (response.message || "Unknown error"));
                }
            }, "json").fail(function(xhr) {
                alert("Request failed: " + xhr.responseText);
            });
        });
    });
    </script>
</body>
</html>
