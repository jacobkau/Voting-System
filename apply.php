<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

// Cloudinary helpers (uploadToCloudinary)
require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";
$username = $_SESSION["username"];

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? $user['id'] : null;

// Fetch active elections
$electionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : null;
    $postName = isset($_POST['postname']) ? trim($_POST['postname']) : null;
    $bio = isset($_POST['bio']) ? trim($_POST['bio']) : "";

    if ($electionId === null || $postName === null) {
        $message = "Please select an election and a position.";
        $messageType = "error";
    } elseif (empty($_FILES['profile_photo']['name'])) {
        $message = "Please upload a profile photo.";
        $messageType = "error";
    } else {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedTypes)) {
            $message = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
            $messageType = "error";
        } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $message = "File is too large. Maximum size is 2MB.";
            $messageType = "error";
        } else {
            // Check existing application for this election
            $checkExistingStmt = $conn->prepare("SELECT postname FROM contesters WHERE election_id = ? AND user_id = ?");
            $checkExistingStmt->execute([$electionId, $userId]);
            $existingApplication = $checkExistingStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingApplication) {
                $message = "You have already applied for the position of '" . htmlspecialchars($existingApplication['postname']) . "' in this election. You cannot apply for multiple positions in the same election.";
                $messageType = "error";
            } else {
                // Check for same post
                $checkStmt = $conn->prepare("SELECT 1 FROM contesters WHERE election_id = ? AND postname = ? AND user_id = ?");
                $checkStmt->execute([$electionId, $postName, $userId]);

                if ($checkStmt->rowCount() > 0) {
                    $message = "You have already applied for this position in this election.";
                    $messageType = "error";
                } else {
                    // Upload to Cloudinary
                    $profilePhotoUrl = uploadToCloudinary($_FILES['profile_photo']['tmp_name'], 'candidates');

                    if (!$profilePhotoUrl) {
                        $message = "Failed to upload profile photo. Please try again.";
                        $messageType = "error";
                    } else {
                        $insertStmt = $conn->prepare("INSERT INTO contesters (election_id, postname, user_id, name, bio, profile_photo, votes) VALUES (?, ?, ?, ?, ?, ?, 0)");

                        if ($insertStmt->execute([$electionId, $postName, $userId, $username, $bio, $profilePhotoUrl])) {
                            $message = "Application submitted successfully. You are now a candidate for " . htmlspecialchars($postName) . ".";
                            $messageType = "success";
                            $_POST = array();
                        } else {
                            $message = "Error submitting application. Please try again.";
                            $messageType = "error";
                        }
                    }
                }
            }
        }
    }
}

// Get selected election ID for displaying posts
$selectedElectionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : (isset($_GET['election_id']) ? intval($_GET['election_id']) : null);
$availablePosts = [];

if ($selectedElectionId) {
    try {
        $postStmt = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
        $postStmt->execute([$selectedElectionId]);
        $availablePosts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching posts: " . $e->getMessage());
    }
}
?>

<?php include("header.php"); ?>

<style>
    .apply-container {
        max-width: 700px;
        margin: 40px auto;
        background-color: #ffffff;
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .apply-container {
        background-color: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .apply-container h2 {
        color: #1f2937;
        margin-bottom: 10px;
        text-align: center;
        font-size: 28px;
        transition: color 0.3s ease;
    }

    body.dark-theme .apply-container h2 { color: #f3f4f6; }

    .apply-container h2 i { color: #2c7a7b; margin-right: 8px; }

    .apply-container .subtitle {
        text-align: center;
        color: #6b7280;
        margin-bottom: 30px;
        font-size: 14px;
    }

    body.dark-theme .apply-container .subtitle { color: #9ca3af; }

    .form-group { margin-bottom: 25px; }

    label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
        display: block;
        font-size: 14px;
    }

    body.dark-theme label { color: #e5e7eb; }

    label i { color: #2c7a7b; margin-right: 6px; }

    .required:after {
        content: " *";
        color: #dc2626;
    }

    /* -------- Input visibility fix -------- */
    select, textarea, input[type="file"] {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
        box-sizing: border-box;
        background: #ffffff;
        color: #1f2937;
        -webkit-text-fill-color: #1f2937;
    }

    select::placeholder, textarea::placeholder {
        color: #9ca3af;
        opacity: 1;
    }

    select:focus, textarea:focus, input[type="file"]:focus {
        outline: none;
        border-color: #2c7a7b;
        box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
    }

    body.dark-theme select,
    body.dark-theme textarea,
    body.dark-theme input[type="file"] {
        background: #2d2d3d;
        border-color: #3d3d4d;
        color: #f3f4f6;
        -webkit-text-fill-color: #f3f4f6;
    }

    body.dark-theme select::placeholder,
    body.dark-theme textarea::placeholder {
        color: #6b7280;
    }

    select option {
        background: #ffffff;
        color: #1f2937;
    }

    body.dark-theme select option {
        background: #2d2d3d;
        color: #f3f4f6;
    }
    /* ------------------------------------- */

    textarea {
        resize: vertical;
        min-height: 120px;
    }

    input[type="file"] {
        padding: 10px 15px;
        background: #f9fafb;
    }

    body.dark-theme input[type="file"] {
        background: #2d2d3d;
    }

    .file-hint {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 5px;
    }

    body.dark-theme .file-hint { color: #6b7280; }

    .submit-btn {
        width: 100%;
        background-color: #2c7a7b;
        color: white;
        padding: 14px 20px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 15px;
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

    .submit-btn:active { transform: translateY(0); }

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
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.8s linear infinite;
    }

    .submit-btn.loading .spinner { display: inline-block; }

    @keyframes spin { to { transform: rotate(360deg); } }

    .loading-posts {
        text-align: center;
        padding: 20px;
        color: #2c7a7b;
        background: #f9fafb;
        border-radius: 12px;
        margin-top: 5px;
    }

    body.dark-theme .loading-posts {
        background: #2d2d3d;
    }

    .loading-posts i {
        font-size: 24px;
        margin-bottom: 10px;
        display: block;
    }

    .message {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-weight: 500;
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

    .info-box {
        background: #eff6ff;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        font-size: 14px;
        color: #1e40af;
        border-left: 4px solid #3b82f6;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    body.dark-theme .info-box {
        background: #1e3a8a;
        color: #dbeafe;
    }

    .info-box h4 {
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
    }

    .info-box h4 i { color: #2c7a7b; }

    body.dark-theme .info-box h4 i { color: #93c5fd; }

    .info-box ul {
        margin-left: 25px;
        margin-top: 5px;
    }

    .info-box li {
        margin: 8px 0;
    }

    .info-box strong { font-weight: 700; }

    @media (max-width: 768px) {
        .apply-container {
            margin: 20px;
            padding: 20px;
        }
        .apply-container h2 { font-size: 24px; }
    }
</style>

<div class="apply-container">
    <h2> Apply for Candidacy</h2>
    <div class="subtitle">Register as a candidate for an election position</div>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> Important Information:</h4>
        <ul>
            <li>You must be registered for an election before applying</li>
            <li>Each candidate needs a profile photo (JPG, PNG, GIF, WEBP, max 2MB)</li>
            <li>Provide a clear bio and manifesto for voters</li>
            <li><strong>You can only apply for ONE position per election</strong></li>
        </ul>
    </div>

    <form method="post" enctype="multipart/form-data" id="applicationForm">
        <div class="form-group">
            <label for="profile_photo" class="required">Profile Photo:</label>
            <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
            <div class="file-hint"><i class="fas fa-info-circle"></i> Accepted formats: JPG, PNG, GIF, WEBP (Max 2MB).</div>
        </div>

        <div class="form-group">
            <label for="election_id" class="required"> Select Election:</label>
            <select name="election_id" id="election_id" onchange="fetchPosts(this.value);" required>
                <option value="">-- Select Election --</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo $election['id']; ?>" <?php echo ($selectedElectionId == $election['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="postname" class="required"> Select Position:</label>
            <select name="postname" id="postname" required>
                <option value="">Select Election First</option>
                <?php foreach ($availablePosts as $post): ?>
                    <option value="<?php echo htmlspecialchars($post['postname']); ?>">
                        <?php echo htmlspecialchars($post['postname']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="postsLoading" class="loading-posts" style="display: none;">
                <i class="fas fa-spinner fa-pulse"></i>
                Loading positions...
            </div>
        </div>

        <div class="form-group">
            <label for="bio"> Bio and Manifesto:</label>
            <textarea name="bio" id="bio" rows="5" placeholder="Tell voters about yourself, your qualifications, experience, and goals if elected..."></textarea>
            <div class="file-hint"> Be clear and convincing. This will be visible to all voters.</div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
            <span class="btn-text"> Submit Application</span>
            <span class="spinner"></span>
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.fetchPosts = function(electionId) {
        const postSelect = document.getElementById('postname');
        const postsLoading = document.getElementById('postsLoading');

        if (!postSelect) return;

        if (electionId === "") {
            postSelect.innerHTML = '<option value="">Select Election First</option>';
            postSelect.disabled = true;
            if (postsLoading) postsLoading.style.display = 'none';
            return;
        }

        postSelect.style.display = 'none';
        if (postsLoading) postsLoading.style.display = 'block';
        postSelect.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4) {
                postSelect.style.display = 'block';
                if (postsLoading) postsLoading.style.display = 'none';

                if (xhr.status == 200) {
                    postSelect.innerHTML = xhr.responseText;
                    postSelect.disabled = false;
                } else {
                    postSelect.innerHTML = '<option value="">Error loading positions. Please try again.</option>';
                    postSelect.disabled = true;
                }
            }
        };
        xhr.open('GET', 'get_posts.php?election_id=' + electionId, true);
        xhr.send();
    };

    const form = document.getElementById('applicationForm');
    const submitBtn = document.getElementById('submitBtn');
    const fileInput = document.getElementById('profile_photo');
    const electionSelect = document.getElementById('election_id');
    const postSelect = document.getElementById('postname');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                showError('Please upload a profile photo.');
                return false;
            }

            if (!electionSelect || !electionSelect.value) {
                e.preventDefault();
                showError('Please select an election.');
                return false;
            }

            if (!postSelect || !postSelect.value || postSelect.disabled) {
                e.preventDefault();
                showError('Please select a position.');
                return false;
            }

            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            return true;
        });
    }

    function showError(message) {
        const existingError = document.querySelector('.message.error');
        if (existingError) existingError.remove();

        const errorDiv = document.createElement('div');
        errorDiv.className = 'message error';
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span>' + message + '</span>';

        const formContainer = document.querySelector('.apply-container');
        const form = document.getElementById('applicationForm');
        formContainer.insertBefore(errorDiv, form);

        setTimeout(() => {
            if (errorDiv && errorDiv.parentNode) errorDiv.remove();
        }, 5000);
    }

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileHint = document.querySelector('.file-hint');

            if (file) {
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const isValidType = validTypes.includes(file.type);
                const isValidSize = file.size <= 2 * 1024 * 1024;

                if (!isValidType) {
                    alert('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
                    this.value = '';
                    if (fileHint) {
                        fileHint.style.color = '#dc2626';
                        fileHint.innerHTML = '<i class="fas fa-times-circle"></i> Invalid file type. Please upload JPG, PNG, GIF, or WEBP.';
                    }
                } else if (!isValidSize) {
                    alert('File is too large. Maximum size is 2MB.');
                    this.value = '';
                    if (fileHint) {
                        fileHint.style.color = '#dc2626';
                        fileHint.innerHTML = '<i class="fas fa-times-circle"></i> File too large. Maximum size is 2MB.';
                    }
                } else {
                    if (fileHint) {
                        fileHint.style.color = '#10b981';
                        fileHint.innerHTML = '<i class="fas fa-check-circle"></i> File accepted: ' + file.name;
                    }
                    setTimeout(() => {
                        if (fileHint) {
                            fileHint.style.color = '';
                            fileHint.innerHTML = '<i class="fas fa-info-circle"></i> Accepted formats: JPG, PNG, GIF, WEBP (Max 2MB). Uploaded to Cloudinary.';
                        }
                    }, 3000);
                }
            }
        });
    }

    window.addEventListener('pageshow', function() {
        if (submitBtn) {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    });

    if (electionSelect && electionSelect.value) {
        fetchPosts(electionSelect.value);
    }
});
</script>

<?php include("footer.php"); ?>
