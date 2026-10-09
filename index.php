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

// Fetch all elections
try {
    $electionQuery = "SELECT id, title FROM elections ORDER BY id DESC";
    $electionResult = $conn->query($electionQuery);
    $elections = $electionResult->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

/**
 * Candidate image — always prefer the live avatar from users.profile_photo.
 *
 * Priority:
 *   1. users.profile_photo (looked up via contesters.user_id)
 *   2. contesters.profile_photo (snapshot at application time)
 *   3. System default avatar
 */
function getCandidateImage($candidate, $conn) {
    // 1. Live lookup from users table by user_id
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

    // 2. Fall back to the snapshot stored on the contester row
    if (!empty($candidate['profile_photo']) && preg_match('#^https?://#i', $candidate['profile_photo'])) {
        return $candidate['profile_photo'];
    }

    // 3. System default
    return defaultAvatarUrl();
}

// User's activity counts for quick stats at the top
try {
    $voteStmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE username = ?");
    $voteStmt->execute([$username]);
    $totalVotesByUser = (int) $voteStmt->fetchColumn();

    $applyStmt = $conn->prepare("SELECT COUNT(*) FROM contesters WHERE user_id = (SELECT id FROM users WHERE username = ?)");
    $applyStmt->execute([$username]);
    $totalApplicationsByUser = (int) $applyStmt->fetchColumn();

    $regStmt = $conn->prepare("SELECT COUNT(*) FROM user_elections ue JOIN users u ON ue.user_id = u.id WHERE u.username = ?");
    $regStmt->execute([$username]);
    $totalRegistrationsByUser = (int) $regStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
    $totalVotesByUser = 0;
    $totalApplicationsByUser = 0;
    $totalRegistrationsByUser = 0;
}
?>

<?php include("header.php"); ?>

<style>
    .results-hero {
        background-color: #2c7a7b;
        border-radius: 20px;
        padding: 45px 30px;
        text-align: center;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(44, 122, 123, 0.2);
    }

    body.dark-theme .results-hero {
        background-color: #0f172a;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }

    .results-hero i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
    }

    .results-hero h1 {
        font-size: 36px;
        margin-bottom: 10px;
    }

    .results-hero p {
        font-size: 16px;
        opacity: 0.95;
    }

    /* Quick stats */
    .stats-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-box {
        flex: 1;
        min-width: 180px;
        background: #ffffff;
        border-radius: 16px;
        padding: 25px 20px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    body.dark-theme .stat-box {
        background: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .stat-box:hover {
        transform: translateY(-5px);
    }

    .stat-box i {
        font-size: 40px;
        color: #2c7a7b;
        margin-bottom: 10px;
        display: block;
    }

    .stat-box .number {
        font-size: 32px;
        font-weight: 800;
        color: #1f2937;
    }

    body.dark-theme .stat-box .number { color: #f3f4f6; }

    .stat-box .label {
        color: #6b7280;
        font-size: 14px;
        margin-top: 5px;
    }

    body.dark-theme .stat-box .label { color: #9ca3af; }

    /* Votes container */
    .votes-container {
        max-width: 1400px;
        margin: 0 auto 40px;
        background-color: #ffffff;
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .votes-container {
        background-color: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .votes-container h2 {
        color: #1f2937;
        margin-top: 30px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .votes-container h2 {
        color: #f3f4f6;
        border-bottom-color: #3d3d4d;
    }

    .votes-container h2:first-child { margin-top: 0; }

    .votes-container h2 i { color: #2c7a7b; }

    .votes-container h3 {
        color: #374151;
        margin-top: 25px;
        margin-bottom: 15px;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: color 0.3s ease;
    }

    body.dark-theme .votes-container h3 { color: #e5e7eb; }

    .votes-container h3 i { color: #2c7a7b; }

    /* Results table */
    .votes-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border-radius: 12px;
        overflow: hidden;
    }

    .votes-table th,
    .votes-table td {
        border: 1px solid #e5e7eb;
        padding: 14px;
        text-align: left;
        transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
    }

    body.dark-theme .votes-table th,
    body.dark-theme .votes-table td {
        border-color: #3d3d4d;
        color: #e5e7eb;
    }

    .votes-table th {
        background-color: #2c7a7b;
        color: #ffffff;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    body.dark-theme .votes-table th {
        background-color: #0f172a;
    }

    .votes-table tr:hover {
        background-color: #f9fafb;
    }

    body.dark-theme .votes-table tr:hover {
        background-color: #2d2d3d;
    }

    .votes-table tr.winner-row {
        background-color: #d1fae5;
    }

    body.dark-theme .votes-table tr.winner-row {
        background-color: #064e3b;
    }

    .votes-table tr.winner-row:hover {
        background-color: #a7f3d0;
    }

    body.dark-theme .votes-table tr.winner-row:hover {
        background-color: #065f46;
    }

    .candidate-image {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #2c7a7b;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        background: #f4f7f9;
    }

    .winner-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background-color: #f59e0b;
        color: #ffffff;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 20px;
        margin-left: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vote-count {
        font-size: 18px;
        font-weight: 700;
        color: #2c7a7b;
    }

    .percentage-bar {
        background: #e5e7eb;
        border-radius: 10px;
        height: 8px;
        width: 100%;
        overflow: hidden;
        margin-top: 5px;
    }

    body.dark-theme .percentage-bar {
        background: #3d3d4d;
    }

    .percentage-fill {
        background-color: #2c7a7b;
        height: 100%;
        border-radius: 10px;
        transition: width 0.5s ease;
    }

    .percentage-text {
        font-size: 14px;
        color: #6b7280;
        min-width: 45px;
    }

    body.dark-theme .percentage-text { color: #9ca3af; }

    .no-data {
        text-align: center;
        padding: 60px 20px;
        background: #f9fafb;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
    }

    body.dark-theme .no-data {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .no-data i {
        font-size: 48px;
        color: #d1d5db;
        margin-bottom: 15px;
        display: block;
    }

    body.dark-theme .no-data i { color: #4b5563; }

    .no-data p { color: #9ca3af; }

    .inline-info {
        color: #6b7280;
        font-style: italic;
        text-align: center;
        padding: 20px;
    }

    body.dark-theme .inline-info { color: #9ca3af; }

    .error-text {
        color: #dc2626;
        text-align: center;
        padding: 20px;
    }

    body.dark-theme .error-text { color: #fca5a5; }

    @media (max-width: 768px) {
        .results-hero { padding: 30px 20px; }
        .results-hero h1 { font-size: 24px; }
        .results-hero i { font-size: 36px; }
        .votes-container {
            margin: 20px;
            padding: 20px;
        }
        .votes-container h2 { font-size: 18px; }
        .votes-container h3 { font-size: 16px; }
        .votes-table th,
        .votes-table td {
            padding: 8px;
            font-size: 12px;
        }
        .candidate-image {
            width: 40px;
            height: 40px;
        }
        .stat-box { min-width: 100%; }
    }
</style>

<div class="results-hero">
    <i class="fas fa-chart-bar"></i>
    <h1>Election Results</h1>
    <p>Live vote counts and standings across all elections</p>
</div>

<!-- Quick stats -->
<div class="stats-row">
    <div class="stat-box">
        <i class="fas fa-vote-yea"></i>
        <div class="number"><?php echo number_format($totalVotesByUser); ?></div>
        <div class="label">Your Votes Cast</div>
    </div>
    <div class="stat-box">
        <i class="fas fa-user-tie"></i>
        <div class="number"><?php echo number_format($totalApplicationsByUser); ?></div>
        <div class="label">Your Candidacy Applications</div>
    </div>
    <div class="stat-box">
        <i class="fas fa-calendar-check"></i>
        <div class="number"><?php echo number_format($totalRegistrationsByUser); ?></div>
        <div class="label">Your Election Registrations</div>
    </div>
</div>

<div class="votes-container">
    <?php if (empty($elections)): ?>
        <div class="no-data">
            <i class="fas fa-vote-yea"></i>
            <p>No elections available at this time.</p>
            <p>Please check back later for upcoming elections.</p>
        </div>
    <?php else: ?>
        <?php foreach ($elections as $election):
            $electionId = $election['id'];
        ?>
            <h2><i class="fas fa-poll"></i> <?php echo htmlspecialchars($election['title']); ?></h2>

            <?php
            try {
                $postQuery = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postQuery->execute([$electionId]);
                $posts = $postQuery->fetchAll(PDO::FETCH_ASSOC);

                if (empty($posts)) {
                    $postQuery2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                    $postQuery2->execute([$electionId]);
                    $posts = $postQuery2->fetchAll(PDO::FETCH_ASSOC);
                }

                if (empty($posts)):
            ?>
                    <p class="inline-info">No positions available for this election.</p>
                <?php else: ?>
                    <?php foreach ($posts as $post):
                        $postName = $post['postname'];
                    ?>
                        <h3><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($postName); ?></h3>
                        <table class="votes-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Photo</th>
                                    <th>Candidate Name</th>
                                    <th style="width: 150px;">Votes</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $candidateQuery = $conn->prepare("
                                    SELECT id, user_id, name, votes, profile_photo
                                    FROM contesters
                                    WHERE postname = ? AND election_id = ?
                                    ORDER BY votes DESC
                                ");
                                $candidateQuery->execute([$postName, $electionId]);
                                $candidates = $candidateQuery->fetchAll(PDO::FETCH_ASSOC);
                                $totalVotes = array_sum(array_column($candidates, 'votes'));

                                if (empty($candidates)):
                                ?>
                                    <tr>
                                        <td colspan="4" class="inline-info">No candidates for this position</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $index => $candidate):
                                        $isWinner = ($index === 0 && $totalVotes > 0);
                                        $percentage = ($totalVotes > 0) ? ($candidate['votes'] / $totalVotes) * 100 : 0;
                                        $imgSrc = getCandidateImage($candidate, $conn);
                                    ?>
                                        <tr class="<?php echo $isWinner ? 'winner-row' : ''; ?>">
                                            <td style="text-align: center;">
                                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Candidate" class="candidate-image">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($candidate['name']); ?></strong>
                                                <?php if ($isWinner): ?>
                                                    <span class="winner-badge"><i class="fas fa-crown"></i> Winner</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="vote-count"><?php echo number_format($candidate['votes']); ?></span> votes</td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="percentage-bar" style="flex: 1;">
                                                        <div class="percentage-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                    </div>
                                                    <span class="percentage-text"><?php echo number_format($percentage, 1); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php } catch (PDOException $e) {
                error_log("Error fetching posts/candidates: " . $e->getMessage());
                echo "<p class='error-text'>Error loading results for this election.</p>";
            } ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>
