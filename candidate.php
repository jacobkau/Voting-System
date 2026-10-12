<?php
session_start();

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('display_startup_errors', $isLocal ? '1' : '0');
error_reporting(E_ALL);

include("conn.php");
require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$candidateUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($candidateUserId <= 0) {
    header("Location: index.php");
    exit();
}

// ────────────────────────────────────────────────────────────
// Fetch the candidate's user record
// ────────────────────────────────────────────────────────────
try {
    $uStmt = $conn->prepare("
        SELECT id, username, name AS full_name, email, profile_photo, date AS registered_at
        FROM users WHERE id = ?
    ");
    $uStmt->execute([$candidateUserId]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Candidate fetch error: " . $e->getMessage());
    header("Location: index.php");
    exit();
}

// ────────────────────────────────────────────────────────────
// Fetch all this candidate's approved applications
// ────────────────────────────────────────────────────────────
try {
    $cStmt = $conn->prepare("
        SELECT c.id, c.postname, c.bio, c.votes, c.profile_photo, c.date AS applied_at,
               e.id AS election_id, e.title AS election_title, e.status AS election_status
        FROM contesters c
        JOIN elections e ON c.election_id = e.id
        WHERE c.user_id = ? AND c.status = 'approved'
        ORDER BY c.date DESC
    ");
    $cStmt->execute([$candidateUserId]);
    $applications = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Candidate applications fetch error: " . $e->getMessage());
    $applications = [];
}

// ────────────────────────────────────────────────────────────
// Resolve displayable avatar
// ────────────────────────────────────────────────────────────
$avatar = defaultAvatarUrl();
if (!empty($user['profile_photo']) && preg_match('#^https?://#i', $user['profile_photo'])) {
    $avatar = $user['profile_photo'];
}

// Fallback to a contester snapshot if the user has no photo
if ($avatar === defaultAvatarUrl() && !empty($applications)) {
    $snap = $applications[0]['profile_photo'] ?? '';
    if (!empty($snap) && preg_match('#^https?://#i', $snap)) {
        $avatar = $snap;
    }
}

$displayName = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

// Total votes across all positions
$totalVotes = 0;
foreach ($applications as $a) {
    $totalVotes += (int)$a['votes'];
}
?>

<?php include("header.php"); ?>

<style>
    .candidate-wrap {
        max-width: 900px;
        margin: 0 auto 40px;
        padding: 0 20px;
    }

    .candidate-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #2c7a7b;
        text-decoration: none;
        margin-bottom: 16px;
        font-weight: 600;
        transition: gap 0.2s ease;
    }

    .candidate-back:hover { gap: 10px; }

    body.dark-theme .candidate-back { color: #4fd1c5; }

    /* Profile card */
    .candidate-profile {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        display: flex;
        gap: 24px;
        align-items: flex-start;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    body.dark-theme .candidate-profile {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .candidate-avatar-lg {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #2c7a7b;
        background: #f4f7f9;
        flex-shrink: 0;
    }

    .candidate-profile-info {
        flex: 1;
        min-width: 220px;
    }

    .candidate-profile-info h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 4px 0;
        line-height: 1.2;
    }

    body.dark-theme .candidate-profile-info h1 { color: #f3f4f6; }

    .candidate-username {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 12px;
    }

    body.dark-theme .candidate-username { color: #9ca3af; }

    .candidate-stats-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 8px;
    }

    .candidate-stat-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #e6f4f4;
        color: #2c7a7b;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    body.dark-theme .candidate-stat-pill {
        background: rgba(44, 122, 123, 0.25);
        color: #a7f3d0;
    }

    /* Applications list */
    .candidate-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        margin: 24px 0 12px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    body.dark-theme .candidate-section-title { color: #f3f4f6; }

    .candidate-section-title i { color: #2c7a7b; }

    .application-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    body.dark-theme .application-card {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .application-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .application-role {
        font-size: 15px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    body.dark-theme .application-role { color: #f3f4f6; }

    .application-role i { color: #2c7a7b; font-size: 14px; }

    .application-election {
        font-size: 12px;
        color: #6b7280;
        margin-left: 22px;
    }

    body.dark-theme .application-election { color: #9ca3af; }

    .application-votes {
        background: #2c7a7b;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .application-bio {
        font-size: 14px;
        color: #4b5563;
        line-height: 1.65;
        margin-top: 8px;
        padding-top: 12px;
        border-top: 1px dashed #e5e7eb;
        white-space: pre-wrap;
    }

    body.dark-theme .application-bio {
        color: #cbd5e1;
        border-top-color: #3d3d4d;
    }

    .application-bio.empty {
        color: #9ca3af;
        font-style: italic;
    }

    .application-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 11px;
        color: #9ca3af;
        margin-top: 12px;
    }

    .application-meta span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        color: #9ca3af;
    }

    body.dark-theme .empty-state {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .empty-state i {
        font-size: 42px;
        display: block;
        margin-bottom: 12px;
        color: #d1d5db;
    }

    body.dark-theme .empty-state i { color: #4b5563; }

    @media (max-width: 600px) {
        .candidate-wrap { padding: 0 15px; }
        .candidate-profile { padding: 20px; gap: 16px; }
        .candidate-avatar-lg { width: 84px; height: 84px; }
        .candidate-profile-info h1 { font-size: 20px; }
        .application-card { padding: 16px; }
    }
</style>

<div class="candidate-wrap">
    <a href="javascript:history.back()" class="candidate-back">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <!-- Profile card -->
    <div class="candidate-profile">
        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="candidate-avatar-lg">

        <div class="candidate-profile-info">
            <h1><?php echo htmlspecialchars($displayName); ?></h1>
            <div class="candidate-username">@<?php echo htmlspecialchars($user['username']); ?></div>

            <div class="candidate-stats-inline">
                <span class="candidate-stat-pill">
                    <i class="fas fa-vote-yea"></i> <?php echo number_format($totalVotes); ?> total votes
                </span>
                <span class="candidate-stat-pill">
                    <i class="fas fa-user-tie"></i> <?php echo count($applications); ?> position<?php echo count($applications) === 1 ? '' : 's'; ?>
                </span>
                <?php if (!empty($user['registered_at'])): ?>
                    <span class="candidate-stat-pill">
                        <i class="far fa-calendar-alt"></i> Joined <?php echo date('M Y', strtotime($user['registered_at'])); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Manifestos per position -->
    <div class="candidate-section-title">
        <i class="fas fa-file-alt"></i> Manifestos
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <i class="fas fa-file-signature"></i>
            <p>This candidate has no approved manifestos yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="application-card">
                <div class="application-header">
                    <div>
                        <div class="application-role">
                            <i class="fas fa-user-tie"></i>
                            <?php echo htmlspecialchars($app['postname']); ?>
                        </div>
                        <div class="application-election">
                            <i class="fas fa-poll"></i> <?php echo htmlspecialchars($app['election_title']); ?>
                            · <?php echo ucfirst(htmlspecialchars($app['election_status'])); ?>
                        </div>
                    </div>
                    <div class="application-votes">
                        <i class="fas fa-vote-yea"></i> <?php echo number_format($app['votes']); ?>
                    </div>
                </div>

                <?php if (!empty(trim($app['bio'] ?? ''))): ?>
                    <div class="application-bio"><?php echo nl2br(htmlspecialchars($app['bio'])); ?></div>
                <?php else: ?>
                    <div class="application-bio empty">No manifesto provided.</div>
                <?php endif; ?>

                <div class="application-meta">
                    <span><i class="far fa-clock"></i> Applied <?php echo date('M d, Y', strtotime($app['applied_at'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>
