<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? $user['id'] : null;

// Fetch Open Elections
$openElectionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$openElectionsStmt->execute();
$openElections = $openElectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get Election ID from URL or session
$electionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_SESSION['selected_election']) ? $_SESSION['selected_election'] : null);

if (isset($_GET['election_id'])) {
    $_SESSION['selected_election'] = $electionId;
}

$showVoteForm = false;
$errorMessage = "";
$successMessage = "";
$election = null;

if ($electionId !== null) {
    $electionCheckStmt = $conn->prepare("SELECT id, title, status FROM elections WHERE id = ?");
    $electionCheckStmt->execute([$electionId]);
    $election = $electionCheckStmt->fetch(PDO::FETCH_ASSOC);

    if (!$election) {
        $errorMessage = "The selected election does not exist.";
    } elseif ($election['status'] !== 'active') {
        $errorMessage = "Voting is currently closed for this election. Status: " . $election['status'];
    } else {
        $userRegisteredStmt = $conn->prepare("SELECT 1 FROM user_elections WHERE user_id = ? AND election_id = ?");
        $userRegisteredStmt->execute([$userId, $electionId]);

        if ($userRegisteredStmt->rowCount() === 0) {
            $errorMessage = "You are not registered for this election. Please register first.";
        } else {
            $alreadyVotedStmt = $conn->prepare("SELECT 1 FROM votes WHERE username = ? AND election_id = ?");
            $alreadyVotedStmt->execute([$username, $electionId]);

            if ($alreadyVotedStmt->rowCount() > 0) {
                $errorMessage = "You have already voted in this election.";
            } else {
                $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postsStmt->execute([$electionId]);
                $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($posts)) {
                    $errorMessage = "No positions have been set up for this election yet.";
                } else {
                    $showVoteForm = true;
                }
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit']) && $showVoteForm) {
    verifyCsrf();
    $votes = $_POST;
    unset($votes['submit']);

    $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
    $postsStmt->execute([$electionId]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

    $allPostsVoted = true;
    $missingPosts = [];

    foreach ($posts as $post) {
        $postKey = strtolower(str_replace(' ', '_', $post['postname']));
        if (!isset($votes[$postKey]) || empty($votes[$postKey])) {
            $allPostsVoted = false;
            $missingPosts[] = $post['postname'];
        }
    }

    if (!$allPostsVoted) {
        $errorMessage = "Please vote for all positions: " . implode(', ', $missingPosts);
    } else {
        $conn->beginTransaction();

        try {
            foreach ($votes as $postKey => $candidateId) {
                $candidateStmt = $conn->prepare("SELECT name FROM contesters WHERE id = ?");
                $candidateStmt->execute([$candidateId]);
                $candidate = $candidateStmt->fetch(PDO::FETCH_ASSOC);
                $candidateName = $candidate ? $candidate['name'] : '';
                $originalPostName = str_replace('_', ' ', $postKey);

                $voteStmt = $conn->prepare("INSERT INTO votes (username, election_id, postname, candidate_name, voted_at) VALUES (?, ?, ?, ?, NOW())");
                if (!$voteStmt->execute([$username, $electionId, $originalPostName, $candidateName])) {
                    throw new Exception("Failed to insert vote");
                }

                $updateStmt = $conn->prepare("UPDATE contesters SET votes = votes + 1 WHERE id = ?");
                $updateStmt->execute([$candidateId]);
            }

            $conn->commit();
            $successMessage = "Vote submitted successfully. Thank you for voting.";
            $showVoteForm = false;

        } catch (Exception $e) {
            $conn->rollBack();
            $errorMessage = "Error submitting your vote. Please try again.";
            error_log("Vote error: " . $e->getMessage());
        }
    }
}


function getCandidateImage($candidate, $conn) {
    if (!empty($candidate['user_id'])) {
        try {
            $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $stmt->execute([$candidate['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow && !empty($userRow['profile_photo']) && preg_match('#^https?://#i', $userRow['profile_photo'])) {
                return $userRow['profile_photo'];
            }
        } catch (PDOException $e) {
            error_log("Candidate live-avatar lookup error: " . $e->getMessage());
        }
    }

    if (!empty($candidate['profile_photo']) && preg_match('#^https?://#i', $candidate['profile_photo'])) {
        return $candidate['profile_photo'];
    }

    return defaultAvatarUrl();
}
?>

<?php include("header.php"); ?>

<style>
    .vote-container {
        max-width: 1400px;
        margin: 0 auto 40px;
        padding: 0 20px;
    }

    .vote-hero {
        background-color: #2c7a7b;
        border-radius: 16px;
        padding: 22px 26px;
        color: white;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(44, 122, 123, 0.15);
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }

    body.dark-theme .vote-hero {
        background-color: #0f172a;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    .vote-hero-icon {
        font-size: 26px;
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .vote-hero-text {
        flex: 1;
        min-width: 200px;
    }

    .vote-hero-text h1 {
        font-size: 20px;
        margin-bottom: 4px;
        font-weight: 700;
    }

    .vote-hero-text p {
        font-size: 13px;
        opacity: 0.9;
        line-height: 1.5;
    }

    .election-selector-wrapper {
        background: #ffffff;
        border-radius: 14px;
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .election-selector-wrapper {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .selector-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    body.dark-theme .selector-label { color: #cbd5e1; }

    .selector-label i {
        color: #2c7a7b;
        font-size: 14px;
    }

    .election-select {
        width: 100%;
        padding: 11px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        font-family: inherit;
        background: #ffffff;
        color: #1f2937;
        -webkit-text-fill-color: #1f2937;
        cursor: pointer;
        transition: all 0.3s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%232c7a7b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        background-size: 18px;
    }

    .election-select:focus {
        outline: none;
        border-color: #2c7a7b;
        box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
    }

    body.dark-theme .election-select {
        background-color: #2d2d3d;
        border-color: #3d3d4d;
        color: #f3f4f6;
        -webkit-text-fill-color: #f3f4f6;
    }

    .position-card {
        background: #ffffff;
        border-radius: 14px;
        margin-bottom: 20px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .position-card {
        background: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .position-header {
        background: #f9fafb;
        padding: 14px 20px;
        border-bottom: 1px solid #e5e7eb;
    }

    body.dark-theme .position-header {
        background: #2d2d3d;
        border-bottom-color: #3d3d4d;
    }

    .position-title {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    body.dark-theme .position-title { color: #f3f4f6; }

    .position-title i {
        color: #2c7a7b;
        font-size: 16px;
    }

    .position-description {
        color: #6b7280;
        font-size: 12px;
        margin-top: 4px;
        margin-left: 26px;
    }

    body.dark-theme .position-description { color: #9ca3af; }

    .candidates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
        padding: 18px;
    }

    .candidate-item {
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s ease;
        position: relative;
        overflow: hidden;
    }

    body.dark-theme .candidate-item {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .candidate-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background-color: #2c7a7b;
        transform: scaleX(0);
        transition: transform 0.3s;
    }

    .candidate-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        border-color: #2c7a7b;
    }

    .candidate-item:hover::before {
        transform: scaleX(1);
    }

    .candidate-item.selected {
        background-color: #2c7a7b;
        border-color: #2c7a7b;
        color: white;
        box-shadow: 0 6px 15px rgba(44, 122, 123, 0.3);
    }

    .candidate-image {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 10px;
        border: 2px solid #ffffff;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        transition: transform 0.25s;
        background: #f4f7f9;
        display: block;
    }

    body.dark-theme .candidate-image {
        border-color: #3d3d4d;
    }

    .candidate-item:hover .candidate-image {
        transform: scale(1.05);
    }

    .candidate-item.selected .candidate-image {
        border-color: #fbbf24;
    }

    .candidate-name {
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 3px;
        color: #1f2937;
    }

    body.dark-theme .candidate-name { color: #f3f4f6; }

    .candidate-item.selected .candidate-name { color: white; }

    .candidate-party {
        font-size: 11px;
        color: #6b7280;
        margin-top: 3px;
    }

    body.dark-theme .candidate-party { color: #9ca3af; }

    .candidate-item.selected .candidate-party {
        color: rgba(255, 255, 255, 0.8);
    }

    .selected-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #10b981;
        color: white;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        opacity: 0;
        transform: scale(0);
        transition: all 0.25s;
    }

    .candidate-item.selected .selected-badge {
        opacity: 1;
        transform: scale(1);
    }

    .empty-candidates {
        grid-column: 1/-1;
        text-align: center;
        padding: 30px;
        color: #9ca3af;
        font-size: 13px;
    }

    .empty-candidates i {
        font-size: 36px;
        margin-bottom: 8px;
        display: block;
        color: #d1d5db;
    }

    body.dark-theme .empty-candidates i { color: #4b5563; }

    .submit-section {
        background: #ffffff;
        border-radius: 14px;
        padding: 20px;
        text-align: center;
        margin-top: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .submit-section {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .submit-btn {
        background-color: #2c7a7b;
        color: white;
        padding: 13px 32px;
        border: none;
        border-radius: 40px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-family: inherit;
    }

    .submit-btn:hover {
        background-color: #236162;
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(44, 122, 123, 0.35);
    }

    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .submit-note {
        color: #6b7280;
        font-size: 11px;
        margin-top: 10px;
    }

    body.dark-theme .submit-note { color: #9ca3af; }

    .message {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
    }

    .message.success {
        background: #d1fae5;
        color: #065f46;
        border-left: 3px solid #10b981;
    }

    body.dark-theme .message.success {
        background: #064e3b;
        color: #a7f3d0;
    }

    .message.error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 3px solid #dc2626;
    }

    body.dark-theme .message.error {
        background: #7f1d1d;
        color: #fecaca;
    }

    .message.info {
        background: #eff6ff;
        color: #1e40af;
        border-left: 3px solid #3b82f6;
    }

    body.dark-theme .message.info {
        background: #1e3a8a;
        color: #dbeafe;
    }

    @media (max-width: 768px) {
        .vote-hero { padding: 18px 20px; gap: 14px; }
        .vote-hero-icon { width: 42px; height: 42px; font-size: 22px; }
        .vote-hero-text h1 { font-size: 18px; }
        .vote-hero-text p { font-size: 12px; }
        .vote-container { padding: 0 15px; }
        .candidates-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; padding: 14px; }
        .candidate-image { width: 64px; height: 64px; }
        .candidate-name { font-size: 13px; }
        .position-title { font-size: 15px; }
    }
</style>

<div class="vote-container">
    <div class="vote-hero">
        <div class="vote-hero-icon">
            <i class="fas fa-vote-yea"></i>
        </div>
        <div class="vote-hero-text">
            <h1>Cast Your Vote</h1>
            <p>Your voice matters. Select your preferred candidates for each position.</p>
        </div>
    </div>

    <!-- Election selector -->
    <div class="election-selector-wrapper">
        <div class="selector-label">
            <i class="fas fa-calendar-alt"></i>
            <span>Select Election</span>
        </div>
        <select id="electionSelect" class="election-select" onchange="window.location.href='vote.php?election_id=' + this.value;">
            <option value="">-- Choose an Election --</option>
            <?php foreach ($openElections as $electionOption): ?>
                <option value="<?php echo $electionOption['id']; ?>" <?php echo ($electionId == $electionOption['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($electionOption['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <span><?php echo htmlspecialchars($errorMessage); ?></span></div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($successMessage); ?></span></div>
    <?php endif; ?>

    <?php if ($showVoteForm && $electionId && $election): ?>
        <form method="post" action="vote.php?election_id=<?php echo $electionId; ?>" id="voteForm">
             <?= csrfField() ?>
            <?php
            $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
            $postsStmt->execute([$electionId]);
            $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($posts as $post):
                $postName = $post['postname'];
                $postKey = strtolower(str_replace(' ', '_', $postName));

                $candidateStmt = $conn->prepare("
                    SELECT id, user_id, name, profile_photo
                    FROM contesters
                    WHERE postname = ? AND election_id = ?
                    ORDER BY name
                ");
                $candidateStmt->execute([$postName, $electionId]);
                $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="position-card">
                    <div class="position-header">
                        <div class="position-title">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo htmlspecialchars($postName); ?></span>
                        </div>
                        <div class="position-description">
                            Select one candidate for this position
                        </div>
                    </div>

                    <div class="candidates-grid" id="candidate-group-<?php echo $postKey; ?>">
                        <?php if (empty($candidates)): ?>
                            <div class="empty-candidates">
                                <i class="fas fa-user-slash"></i>
                                No candidates available for this position
                            </div>
                        <?php else: ?>
                            <?php foreach ($candidates as $candidate): ?>
                                <?php $imgSrc = getCandidateImage($candidate, $conn); ?>
                                <div class="candidate-item" data-post="<?php echo $postKey; ?>" data-candidate-id="<?php echo $candidate['id']; ?>" onclick="selectCandidate('<?php echo $postKey; ?>', <?php echo $candidate['id']; ?>, this)">
                                    <div class="selected-badge">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <img class="candidate-image" src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                                    <div class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></div>
                                    <div class="candidate-party">Candidate</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="<?php echo $postKey; ?>" id="hidden-<?php echo $postKey; ?>" value="">
                </div>
            <?php endforeach; ?>

            <div class="submit-section">
                <button type="submit" name="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-check-double"></i>
                    <span>Submit Your Vote</span>
                </button>
                <p class="submit-note">
                    <i class="fas fa-lock"></i> Your vote is secure and anonymous
                </p>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!$electionId && !empty($openElections)): ?>
        <div class="message info">
            <i class="fas fa-info-circle"></i>
            <span>Please select an election from the dropdown above to cast your vote.</span>
        </div>
    <?php endif; ?>

    <?php if (empty($openElections)): ?>
        <div class="message info">
            <i class="fas fa-calendar-times"></i>
            <span>No active elections available at this time. Please check back later.</span>
        </div>
    <?php endif; ?>
</div>

<script>
    let selectedCandidates = {};

    function selectCandidate(postKey, candidateId, element) {
        const container = document.getElementById('candidate-group-' + postKey);
        const cards = container.querySelectorAll('.candidate-item');
        cards.forEach(card => {
            card.classList.remove('selected');
        });

        element.classList.add('selected');
        selectedCandidates[postKey] = candidateId;

        const hiddenInput = document.getElementById('hidden-' + postKey);
        if (hiddenInput) {
            hiddenInput.value = candidateId;
        }
    }

    document.getElementById('voteForm')?.addEventListener('submit', function(e) {
        const hiddenInputs = document.querySelectorAll('input[type="hidden"][id^="hidden-"]');
        let allSelected = true;
        let missingSelections = [];

        hiddenInputs.forEach(input => {
            if (!input.value) {
                allSelected = false;
                const postKey = input.id.replace('hidden-', '');
                missingSelections.push(postKey.replace(/_/g, ' '));
            }
        });

        if (!allSelected) {
            e.preventDefault();
            alert('Please select a candidate for all positions:\n\n- ' + missingSelections.join('\n- '));
            return false;
        }

        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> <span>Submitting...</span>';
        submitBtn.disabled = true;

        return true;
    });
</script>

<?php include("footer.php"); ?>
