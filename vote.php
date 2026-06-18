<?php
session_start();
include("conn.php");

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

// Get Election ID from URL
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
        $voteSuccess = true;
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
            $successMessage = "✓ Vote submitted successfully! Thank you for voting.";
            $showVoteForm = false;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errorMessage = "Error submitting your vote. Please try again.";
            error_log("Vote error: " . $e->getMessage());
        }
    }
}

function getCandidateImage($candidate) {
    if (!empty($candidate['profile_photo_blob'])) {
        return 'data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']);
    } elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
        return 'faces/' . htmlspecialchars($candidate['profile_photo']);
    }
    return 'faces/default.jpg';
}
?>

<?php include("header.php"); ?>

<style>
    .vote-container {
        max-width: 1400px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    /* Hero Section */
    .vote-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .vote-hero h2 {
        font-size: 36px;
        margin-bottom: 10px;
    }
    
    .vote-hero p {
        font-size: 16px;
        opacity: 0.95;
    }
    
    /* Professional Election Selector */
    .election-selector-wrapper {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .selector-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 12px;
        font-size: 16px;
    }
    
    .selector-label i {
        color: #667eea;
        font-size: 18px;
    }
    
    .election-select {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 16px;
        font-family: inherit;
        background: white;
        cursor: pointer;
        transition: all 0.3s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23667eea'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 20px;
    }
    
    .election-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    
    /* Position Card */
    .position-card {
        background: white;
        border-radius: 20px;
        margin-bottom: 40px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .position-card:hover {
        transform: translateY(-3px);
    }
    
    .position-header {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        padding: 18px 25px;
        border-bottom: 2px solid #e0e0e0;
    }
    
    .position-title {
        font-size: 22px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .position-title i {
        color: #667eea;
        font-size: 24px;
    }
    
    .position-description {
        color: #6b7280;
        font-size: 14px;
        margin-top: 5px;
        margin-left: 36px;
    }
    
    /* Candidate Grid - Professional Image Row */
    .candidates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        padding: 25px;
    }
    
    .candidate-item {
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .candidate-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.3s;
    }
    
    .candidate-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border-color: #667eea;
    }
    
    .candidate-item:hover::before {
        transform: scaleX(1);
    }
    
    .candidate-item.selected {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
    }
    
    .candidate-item.selected .candidate-vote-count {
        color: rgba(255,255,255,0.8);
    }
    
    .candidate-image {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 15px;
        border: 3px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        transition: transform 0.3s;
    }
    
    .candidate-item:hover .candidate-image {
        transform: scale(1.05);
    }
    
    .candidate-item.selected .candidate-image {
        border-color: #fbbf24;
    }
    
    .candidate-name {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .candidate-item.selected .candidate-name {
        color: white;
    }
    
    .candidate-party {
        font-size: 12px;
        color: #6b7280;
        margin-top: 5px;
    }
    
    .candidate-item.selected .candidate-party {
        color: rgba(255,255,255,0.8);
    }
    
    .candidate-vote-count {
        font-size: 13px;
        color: #9ca3af;
        margin-top: 8px;
        display: none; /* Hidden until after voting */
    }
    
    .selected-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #10b981;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        opacity: 0;
        transform: scale(0);
        transition: all 0.3s;
    }
    
    .candidate-item.selected .selected-badge {
        opacity: 1;
        transform: scale(1);
    }
    
    /* Submit Button */
    .submit-section {
        background: white;
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        margin-top: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .submit-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 40px;
        border: none;
        border-radius: 50px;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 12px;
    }
    
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102,126,234,0.4);
    }
    
    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Messages */
    .message {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .message.success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    
    .message.error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #dc2626;
    }
    
    .message.info {
        background: #e0e7ff;
        color: #3730a3;
        border-left: 4px solid #6366f1;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .vote-hero { padding: 25px; }
        .vote-hero h2 { font-size: 24px; }
        .candidates-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; padding: 15px; }
        .candidate-image { width: 80px; height: 80px; }
        .candidate-name { font-size: 14px; }
        .position-title { font-size: 18px; }
    }
</style>

<div class="vote-container">
    <!-- Hero Section -->
    <div class="vote-hero">
        <i class="fas fa-vote-yea" style="font-size: 48px; margin-bottom: 15px;"></i>
        <h2>Cast Your Vote</h2>
        <p>Your voice matters! Select your preferred candidates for each position</p>
    </div>
    
    <!-- Election Selector -->
    <div class="election-selector-wrapper">
        <div class="selector-label">
            <i class="fas fa-calendar-alt"></i>
            <span>Select Election</span>
        </div>
        <select id="electionSelect" class="election-select" onchange="window.location.href='vote.php?election_id=' + this.value;">
            <option value="">-- Choose an Election --</option>
            <?php foreach ($openElections as $electionOption): ?>
                <option value="<?php echo $electionOption['id']; ?>" <?php echo ($electionId == $electionOption['id']) ? 'selected' : ''; ?>>
                    🗳️ <?php echo htmlspecialchars($electionOption['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Messages -->
    <?php if (!empty($errorMessage)): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($successMessage)): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
    <?php endif; ?>
    
    <!-- Voting Form -->
    <?php if ($showVoteForm && $electionId && $election): ?>
        <form method="post" action="vote.php?election_id=<?php echo $electionId; ?>" id="voteForm">
            <?php
            $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
            $postsStmt->execute([$electionId]);
            $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($posts as $index => $post):
                $postName = $post['postname'];
                $postKey = strtolower(str_replace(' ', '_', $postName));
                
                $candidateStmt = $conn->prepare("
                    SELECT id, name, profile_photo, profile_photo_blob, profile_photo_type 
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
                            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No candidates available for this position
                            </div>
                        <?php else: ?>
                            <?php foreach ($candidates as $candidate): ?>
                                <div class="candidate-item" data-post="<?php echo $postKey; ?>" data-candidate-id="<?php echo $candidate['id']; ?>" onclick="selectCandidate('<?php echo $postKey; ?>', <?php echo $candidate['id']; ?>, this)">
                                    <div class="selected-badge">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <?php
                                    if (!empty($candidate['profile_photo_blob'])) {
                                        echo '<img class="candidate-image" src="data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']) . '" alt="' . htmlspecialchars($candidate['name']) . '">';
                                    } elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
                                        echo '<img class="candidate-image" src="faces/' . htmlspecialchars($candidate['profile_photo']) . '" alt="' . htmlspecialchars($candidate['name']) . '">';
                                    } else {
                                        echo '<img class="candidate-image" src="faces/default.jpg" alt="' . htmlspecialchars($candidate['name']) . '">';
                                    }
                                    ?>
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
                <p style="color: #6b7280; font-size: 12px; margin-top: 12px;">
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
        const hiddenInputs = document.querySelectorAll('input[type="hidden"][name^="hidden-"]');
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
            alert('⚠️ Please select a candidate for all positions:\n\n• ' + missingSelections.join('\n• '));
            return false;
        }
        
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Submitting...';
        submitBtn.disabled = true;
        
        return true;
    });
</script>

<?php include("footer.php"); ?>
