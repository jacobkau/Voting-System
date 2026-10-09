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

// Fetch all elections
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections ORDER BY id DESC");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

function getContesterImage($contester, $conn) {
    if (!empty($contester['user_id'])) {
        try {
            $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $stmt->execute([$contester['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow && !empty($userRow['profile_photo']) && preg_match('#^https?://#i', $userRow['profile_photo'])) {
                return $userRow['profile_photo'];
            }
        } catch (PDOException $e) {
            error_log("Contester live-avatar lookup error: " . $e->getMessage());
        }
    }

    if (!empty($contester['profile_photo']) && preg_match('#^https?://#i', $contester['profile_photo'])) {
        return $contester['profile_photo'];
    }

    return defaultAvatarUrl();
}
?>

<?php include("header.php"); ?>

<style>
    /* Compact hero */
    .contest-hero {
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

    body.dark-theme .contest-hero {
        background-color: #0f172a;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    .contest-hero-icon {
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

    .contest-hero-text {
        flex: 1;
        min-width: 200px;
    }

    .contest-hero-text h1 {
        font-size: 20px;
        margin-bottom: 4px;
        font-weight: 700;
    }

    .contest-hero-text p {
        font-size: 13px;
        opacity: 0.9;
        line-height: 1.5;
    }

    /* Container */
    .contest-container {
        max-width: 1400px;
        margin: 0 auto 40px;
        background-color: #ffffff;
        padding: 25px;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .contest-container {
        background-color: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    /* Election section */
    .election-section {
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .election-section:last-child { margin-bottom: 0; }

    body.dark-theme .election-section {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .election-section h2 {
        background-color: #2c7a7b;
        color: #ffffff;
        margin: 0;
        padding: 14px 20px;
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    body.dark-theme .election-section h2 {
        background-color: #0f172a;
    }

    .election-section h2 i {
        font-size: 14px;
        opacity: 0.85;
    }

    /* Post section */
    .post-section {
        margin: 16px;
        padding: 16px;
        background: #f9fafb;
        border-radius: 10px;
        border-left: 3px solid #2c7a7b;
        transition: background-color 0.3s ease;
    }

    .post-section:last-child { margin-bottom: 16px; }

    body.dark-theme .post-section {
        background: #2d2d3d;
    }

    .post-section h3 {
        color: #1f2937;
        margin-top: 0;
        margin-bottom: 14px;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    body.dark-theme .post-section h3 { color: #f3f4f6; }

    .post-section h3 i { color: #2c7a7b; font-size: 12px; }

    /* Table */
    .contester-table {
        width: 100%;
        border-collapse: collapse;
        background: #ffffff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    body.dark-theme .contester-table {
        background: #1e1e2e;
    }

    .contester-table th,
    .contester-table td {
        padding: 11px 14px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
    }

    body.dark-theme .contester-table th,
    body.dark-theme .contester-table td {
        border-bottom-color: #3d3d4d;
        color: #e5e7eb;
    }

    .contester-table th {
        background: #f3f4f6;
        color: #374151;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    body.dark-theme .contester-table th {
        background: #2d2d3d;
        color: #cbd5e1;
    }

    .contester-table tr:last-child td { border-bottom: none; }

    .contester-table tr:hover {
        background-color: #f9fafb;
    }

    body.dark-theme .contester-table tr:hover {
        background-color: #2d2d3d;
    }

    .contester-image {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #2c7a7b;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
        background: #f4f7f9;
    }

    .candidate-name {
        font-weight: 600;
        color: #1f2937;
        font-size: 14px;
    }

    body.dark-theme .candidate-name { color: #f3f4f6; }

    .candidate-bio {
        font-size: 13px;
        color: #6b7280;
        line-height: 1.5;
    }

    body.dark-theme .candidate-bio { color: #9ca3af; }

    .vote-count {
        display: inline-block;
        background: #e6f4f4;
        color: #2c7a7b;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    body.dark-theme .vote-count {
        background: rgba(44, 122, 123, 0.25);
        color: #a7f3d0;
    }

    /* Empty states */
    .no-data {
        text-align: center;
        padding: 50px 20px;
        color: #9ca3af;
        background: #f9fafb;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }

    body.dark-theme .no-data {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .no-data i {
        font-size: 42px;
        margin-bottom: 12px;
        display: block;
        color: #d1d5db;
    }

    body.dark-theme .no-data i { color: #4b5563; }

    .inline-info {
        text-align: center;
        color: #9ca3af;
        padding: 20px;
        font-size: 13px;
        font-style: italic;
    }

    body.dark-theme .inline-info { color: #6b7280; }

    .error-text {
        text-align: center;
        color: #dc2626;
        padding: 20px;
        font-size: 13px;
    }

    body.dark-theme .error-text { color: #fca5a5; }

    /* Mobile */
    @media (max-width: 768px) {
        .contest-hero { padding: 18px 20px; gap: 14px; }
        .contest-hero-icon { width: 42px; height: 42px; font-size: 22px; }
        .contest-hero-text h1 { font-size: 18px; }
        .contest-hero-text p { font-size: 12px; }
        .contest-container {
            padding: 16px;
            margin: 0 0 30px;
        }
        .post-section {
            margin: 12px;
            padding: 12px;
        }
        .contester-table th,
        .contester-table td {
            padding: 9px 10px;
            font-size: 12px;
        }
        .contester-image {
            width: 36px;
            height: 36px;
        }
        .election-section h2 {
            font-size: 15px;
            padding: 12px 16px;
        }
        .post-section h3 { font-size: 13px; }
        .candidate-name { font-size: 13px; }
        .candidate-bio { font-size: 12px; }
    }
</style>

<!-- Compact hero -->
<div class="contest-hero">
    <div class="contest-hero-icon">
        <i class="fas fa-users"></i>
    </div>
    <div class="contest-hero-text">
        <h1>Election Contestants</h1>
        <p>Browse all candidates running in every election and position</p>
    </div>
</div>

<div class="contest-container">
    <?php if (empty($elections)): ?>
        <div class="no-data">
            <i class="fas fa-vote-yea"></i>
            <p>No elections found.</p>
        </div>
    <?php else: ?>
        <?php foreach ($elections as $election): ?>
            <div class="election-section">
                <h2><i class="fas fa-poll"></i> <?php echo htmlspecialchars($election['title']); ?></h2>

                <?php
                try {
                    $postsStmt = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                    $postsStmt->execute([$election['id']]);
                    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($posts)) {
                        $postsStmt2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                        $postsStmt2->execute([$election['id']]);
                        $posts = $postsStmt2->fetchAll(PDO::FETCH_ASSOC);
                    }

                    if (empty($posts)):
                ?>
                        <p class="inline-info">No candidates have applied for this election yet.</p>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="post-section">
                                <h3>
                                    <i class="fas fa-user-tie"></i>
                                    <?php echo htmlspecialchars($post['postname']); ?>
                                </h3>

                                <table class="contester-table">
                                    <thead>
                                        <tr>
                                            <th width="70">Photo</th>
                                            <th>Candidate Name</th>
                                            <th>Bio / Manifesto</th>
                                            <th width="90">Votes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $contestersStmt = $conn->prepare("
                                            SELECT id, user_id, name, bio, profile_photo, votes
                                            FROM contesters
                                            WHERE election_id = ? AND postname = ?
                                            ORDER BY votes DESC
                                        ");
                                        $contestersStmt->execute([$election['id'], $post['postname']]);
                                        $contesters = $contestersStmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (empty($contesters)):
                                        ?>
                                            <tr>
                                                <td colspan="4" class="inline-info">
                                                    No contestants for this position
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($contesters as $contester): ?>
                                                <?php $imgSrc = getContesterImage($contester, $conn); ?>
                                                <tr>
                                                    <td style="text-align: center;">
                                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Contester" class="contester-image">
                                                    </td>
                                                    <td class="candidate-name"><?php echo htmlspecialchars($contester['name']); ?></td>
                                                    <td class="candidate-bio"><?php echo nl2br(htmlspecialchars($contester['bio'] ?? 'No bio provided')); ?></td>
                                                    <td><span class="vote-count"><?php echo (int) $contester['votes']; ?> votes</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php
                } catch (PDOException $e) {
                    error_log("Error fetching posts/contesters: " . $e->getMessage());
                    echo "<p class='error-text'>Error loading contestants for this election.</p>";
                }
                ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>
