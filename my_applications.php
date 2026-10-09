<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

// Cloudinary helpers (defaultAvatarUrl)
require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];
$message = "";
$messageType = "";

try {
    // Get user ID
    $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $userStmt->execute([$username]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found.");
    }
    
    $userId = $user['id'];
    
    // Handle Unapply Request
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['unapply'])) {
        $electionId = intval($_POST['election_id']);
        $postname = trim($_POST['postname']);
        
        try {
            $conn->beginTransaction();
            
            $deleteStmt = $conn->prepare("DELETE FROM contesters WHERE user_id = ? AND election_id = ? AND postname = ?");
            $deleteStmt->execute([$userId, $electionId, $postname]);
            
            $logStmt = $conn->prepare("INSERT INTO event_log (username, event_type, event_description) VALUES (?, 'Withdraw Application', ?)");
            $logStmt->execute([$username, "Withdrew application for $postname in election ID: $electionId"]);
            
            $conn->commit();
            
            $message = "Your application for '" . htmlspecialchars($postname) . "' has been withdrawn successfully.";
            $messageType = "success";
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Unapply error: " . $e->getMessage());
            $message = "Error withdrawing application. Please try again.";
            $messageType = "error";
        }
    }
    
    // Fetch User's Registered Elections
    $registeredStmt = $conn->prepare("SELECT ue.election_id, e.title AS election_title, e.status FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
    $registeredStmt->execute([$userId]);
    $registeredElections = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch User's Contester Applications
    $applicationsStmt = $conn->prepare("SELECT id, election_id, postname FROM contesters WHERE user_id = ?");
    $applicationsStmt->execute([$userId]);
    $applications = $applicationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge Applications into Registered Elections
    $mergedElections = [];
    foreach ($registeredElections as $election) {
        $electionId = $election['election_id'];
        if (!isset($mergedElections[$electionId])) {
            $mergedElections[$electionId] = $election;
            $mergedElections[$electionId]['contested_posts'] = [];
        }
    }
    
    foreach ($applications as $application) {
        $electionId = $application['election_id'];
        if (isset($mergedElections[$electionId])) {
            $mergedElections[$electionId]['contested_posts'][] = [
                'postname' => $application['postname'],
                'id' => $application['id']
            ];
        } else {
            $electionInfo = $conn->prepare("SELECT title, status FROM elections WHERE id = ?");
            $electionInfo->execute([$electionId]);
            $electionData = $electionInfo->fetch(PDO::FETCH_ASSOC);
            
            if (!isset($mergedElections[$electionId])) {
                $mergedElections[$electionId] = [
                    'election_id' => $electionId,
                    'election_title' => $electionData['title'] ?? 'Unknown Election',
                    'status' => $electionData['status'] ?? 'unknown',
                    'contested_posts' => []
                ];
            }
            $mergedElections[$electionId]['contested_posts'][] = [
                'postname' => $application['postname'],
                'id' => $application['id']
            ];
        }
    }
    
    $finalElections = array_values($mergedElections);
    
} catch (Exception $e) {
    error_log("Error in my_applications.php: " . $e->getMessage());
    $message = "Error loading your applications. Please try again later.";
    $messageType = "error";
    $finalElections = [];
}
?>

<?php include("header.php"); ?>

<style>
    .applications-container {
        max-width: 1000px;
        margin: 40px auto;
        background-color: #ffffff;
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .applications-container {
        background-color: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .applications-container h1 {
        color: #1f2937;
        margin-bottom: 10px;
        text-align: center;
        font-size: 32px;
        transition: color 0.3s ease;
    }

    body.dark-theme .applications-container h1 { color: #f3f4f6; }

    .applications-container h1 i {
        color: #2c7a7b;
        margin-right: 10px;
    }

    .applications-container .subtitle {
        text-align: center;
        color: #6b7280;
        margin-bottom: 30px;
        font-size: 14px;
    }

    body.dark-theme .applications-container .subtitle { color: #9ca3af; }

    .section {
        border: 1px solid #e5e7eb;
        padding: 25px;
        margin-bottom: 25px;
        border-radius: 16px;
        background: #f9fafb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .section {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .section h2 {
        color: #1f2937;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
        font-size: 22px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .section h2 {
        color: #f3f4f6;
        border-bottom-color: #3d3d4d;
    }

    .section h2 i {
        color: #2c7a7b;
    }

    .election-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .election-list li {
        background-color: #ffffff;
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #2c7a7b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.3s;
    }

    body.dark-theme .election-list li {
        background-color: #1e1e2e;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .election-list li:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .election-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }

    .election-title {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    body.dark-theme .election-title { color: #f3f4f6; }

    .election-title i {
        color: #2c7a7b;
    }

    .election-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    body.dark-theme .status-active {
        background: #064e3b;
        color: #a7f3d0;
    }

    .status-upcoming {
        background: #fef3c7;
        color: #92400e;
    }

    body.dark-theme .status-upcoming {
        background: #78350f;
        color: #fde68a;
    }

    .status-completed {
        background: #fee2e2;
        color: #991b1b;
    }

    body.dark-theme .status-completed {
        background: #7f1d1d;
        color: #fecaca;
    }

    .badge-contesting {
        background: #e6f4f4;
        color: #2c7a7b;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    body.dark-theme .badge-contesting {
        background: rgba(44, 122, 123, 0.25);
        color: #a7f3d0;
    }

    .contest-posts {
        list-style: none;
        padding: 0;
        margin: 15px 0 0 0;
    }

    .contest-post-item {
        background: #f3f4f6;
        border-radius: 10px;
        padding: 12px 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        transition: all 0.3s;
    }

    body.dark-theme .contest-post-item {
        background: #2d2d3d;
    }

    .contest-post-item:hover {
        background: #e5e7eb;
    }

    body.dark-theme .contest-post-item:hover {
        background: #3d3d4d;
    }

    .post-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .post-icon {
        color: #2c7a7b;
        font-size: 18px;
    }

    .post-name {
        font-weight: 600;
        color: #374151;
    }

    body.dark-theme .post-name { color: #e5e7eb; }

    .unapply-btn {
        background-color: #dc2626;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: inherit;
    }

    .unapply-btn:hover {
        background-color: #b91c1c;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }

    .unapply-btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .unapply-btn.loading:hover {
        transform: none;
        box-shadow: none;
    }

    .unapply-btn .spinner {
        display: none;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.8s linear infinite;
    }

    .unapply-btn.loading .spinner { display: inline-block; }

    @keyframes spin { to { transform: rotate(360deg); } }

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

    .no-data {
        text-align: center;
        padding: 50px;
        color: #9ca3af;
    }

    .no-data i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
        color: #d1d5db;
    }

    body.dark-theme .no-data i { color: #4b5563; }

    .register-link {
        display: inline-block;
        margin-top: 15px;
        color: #2c7a7b;
        text-decoration: none;
        font-weight: 500;
    }

    .register-link:hover {
        color: #236162;
        text-decoration: underline;
    }

    .apply-link {
        color: #2c7a7b;
        font-size: 13px;
        text-decoration: none;
    }

    .apply-link:hover { text-decoration: underline; }

    .info-text {
        color: #6b7280;
        margin-top: 15px;
        font-size: 14px;
    }

    body.dark-theme .info-text { color: #9ca3af; }

    .info-text i { color: #2c7a7b; }

    @media (max-width: 768px) {
        .applications-container {
            margin: 20px;
            padding: 20px;
        }
        .applications-container h1 { font-size: 24px; }
        .election-title { font-size: 18px; }
        .contest-post-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .unapply-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="applications-container">
    <h1> My Applications</h1>
    <div class="subtitle">View your election registrations and candidacy applications</div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>My Election Activities</h2>

        <?php if (empty($finalElections)): ?>
            <div class="no-data">
                <p>You are not registered for any elections.</p>
                <p>You haven't applied for any candidacy positions yet.</p>
                <a href="apply.php" class="register-link"><i class="fas fa-user-plus"></i> Apply for Candidacy</a>
            </div>
        <?php else: ?>
            <ul class="election-list">
                <?php foreach ($finalElections as $election): ?>
                    <li>
                        <div class="election-header">
                            <div class="election-title">
                                <?php echo htmlspecialchars($election['election_title']); ?>
                                <?php if (!empty($election['contested_posts'])): ?>
                                    <span class="badge-contesting"> Contesting</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="election-status status-<?php echo htmlspecialchars($election['status'] ?? 'upcoming'); ?>">
                                    <i class="fas <?php echo $election['status'] == 'active' ? 'fa-play' : ($election['status'] == 'completed' ? 'fa-flag-checkered' : 'fa-clock'); ?>"></i>
                                    <?php echo ucfirst(htmlspecialchars($election['status'] ?? 'Upcoming')); ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($election['contested_posts'])): ?>
                            <div style="margin-top: 15px;">
                                <strong style="color: #374151; display: block; margin-bottom: 10px;">
                                    My Candidacy Applications:
                                </strong>
                                <ul class="contest-posts">
                                    <?php foreach ($election['contested_posts'] as $post): ?>
                                        <li class="contest-post-item">
                                            <div class="post-info">
                                                <span class="post-name"><?php echo htmlspecialchars($post['postname']); ?></span>
                                            </div>
                                            <button class="unapply-btn" data-election-id="<?php echo $election['election_id']; ?>" data-postname="<?php echo htmlspecialchars($post['postname']); ?>" data-post-id="<?php echo $post['id']; ?>">
                                                <span class="spinner"></span>
                                                <span class="btn-text">Withdraw Application</span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p class="info-text">
                                <i class="fas fa-info-circle"></i> You are registered as a voter for this election but not contesting any position.
                            </p>
                            <a href="apply.php?election_id=<?php echo $election['election_id']; ?>" class="apply-link">
                                <i class="fas fa-user-plus"></i> Apply for a position
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const unapplyBtns = document.querySelectorAll('.unapply-btn');

    unapplyBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const electionId = this.dataset.electionId;
            const postname = this.dataset.postname;

            if (confirm('Are you sure you want to withdraw your application for "' + postname + '"?\n\nThis action cannot be undone.')) {
                this.classList.add('loading');
                this.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'my_applications.php';

                const unapplyInput = document.createElement('input');
                unapplyInput.type = 'hidden';
                unapplyInput.name = 'unapply';
                unapplyInput.value = '1';

                const electionIdInput = document.createElement('input');
                electionIdInput.type = 'hidden';
                electionIdInput.name = 'election_id';
                electionIdInput.value = electionId;

                const postnameInput = document.createElement('input');
                postnameInput.type = 'hidden';
                postnameInput.name = 'postname';
                postnameInput.value = postname;

                form.appendChild(unapplyInput);
                form.appendChild(electionIdInput);
                form.appendChild(postnameInput);

                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
</script>

<?php include("footer.php"); ?>
