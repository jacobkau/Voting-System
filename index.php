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

$username = $_SESSION["username"];

// ────────────────────────────────────────────────────────────
// Fetch all elections (with aggregated counts for the cards)
// ────────────────────────────────────────────────────────────
try {
    $electionsStmt = $conn->prepare("
        SELECT e.id, e.title, e.description, e.start_date, e.end_date, e.status,
               (SELECT COUNT(*) FROM election_posts WHERE election_id = e.id) AS positions_count,
               (SELECT COUNT(DISTINCT postname) FROM contesters WHERE election_id = e.id AND status = 'approved') AS approved_posts,
               (SELECT COUNT(*) FROM contesters WHERE election_id = e.id AND status = 'approved') AS candidates_count,
               (SELECT COUNT(*) FROM votes WHERE election_id = e.id) AS votes_count
        FROM elections e
        ORDER BY 
            CASE e.status WHEN 'active' THEN 1 WHEN 'upcoming' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
            e.start_date DESC
    ");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

// ────────────────────────────────────────────────────────────
// Live avatar helper (unchanged from previous version)
// ────────────────────────────────────────────────────────────
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

// ────────────────────────────────────────────────────────────
// User's quick stats
// ────────────────────────────────────────────────────────────
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
.candidate-row-link {
    cursor: pointer;
    transition: background 0.15s ease;
}

.candidate-row-link:hover {
    background: #f0fdfa; 
}

body.dark-theme .candidate-row-link:hover {
    background: #1a3a3a;
}

.candidate-bio-line {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
    line-height: 1.5;
}

body.dark-theme .candidate-bio-line {
    color: #9ca3af;
}

.candidate-row-link td:first-child::after {
    content: '';
}
    .results-hero {
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

    body.dark-theme .results-hero {
        background-color: #0f172a;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    .results-hero-icon {
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

    .results-hero-text { flex: 1; min-width: 200px; }
    .results-hero-text h1 { font-size: 20px; margin-bottom: 4px; font-weight: 700; }
    .results-hero-text p { font-size: 13px; opacity: 0.9; line-height: 1.5; }

    .live-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        background: rgba(255, 255, 255, 0.15);
        padding: 4px 10px;
        border-radius: 20px;
        opacity: 0.9;
    }

    .live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: livePulse 2s infinite;
    }

    @keyframes livePulse {
        0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70%  { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }

    .stat-box {
        background: #ffffff;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e7eb;
        transition: transform 0.2s ease, background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .stat-box {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .stat-box:hover {
        transform: translateY(-2px);
        border-color: #2c7a7b;
    }

    .stat-box i {
        font-size: 22px;
        color: #2c7a7b;
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #e6f4f4;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.dark-theme .stat-box i {
        background: rgba(44, 122, 123, 0.2);
    }

    .stat-box .stat-info { flex: 1; min-width: 0; }

    .stat-box .number {
        font-size: 20px;
        font-weight: 800;
        color: #1f2937;
        line-height: 1.2;
    }

    body.dark-theme .stat-box .number { color: #f3f4f6; }

    .stat-box .label {
        color: #6b7280;
        font-size: 12px;
        margin-top: 2px;
    }

    body.dark-theme .stat-box .label { color: #9ca3af; }

    /* ────────────────────────────────────────────────
       Election cards grid
       ──────────────────────────────────────────────── */
    .elections-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }

    .election-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.25s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    body.dark-theme .election-card {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .election-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background-color: #2c7a7b;
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }

    .election-card:hover {
        border-color: #2c7a7b;
        box-shadow: 0 8px 25px rgba(44, 122, 123, 0.12);
        transform: translateY(-3px);
    }

    .election-card:hover::before {
        transform: scaleX(1);
    }

    .election-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .election-card h3 {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        line-height: 1.3;
    }

    body.dark-theme .election-card h3 { color: #f3f4f6; }

    .election-card p {
        color: #6b7280;
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    body.dark-theme .election-card p { color: #9ca3af; }

    .election-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 12px;
        color: #6b7280;
    }

    body.dark-theme .election-card-meta { color: #9ca3af; }

    .election-card-meta span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .election-card-meta i {
        color: #2c7a7b;
        font-size: 11px;
    }

    /* Mini live stats inside each card */
    .card-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        padding: 12px 0 0;
        border-top: 1px dashed #e5e7eb;
        margin-top: auto;
    }

    body.dark-theme .card-stats { border-top-color: #3d3d4d; }

    .card-stat {
        text-align: center;
    }

    .card-stat .cs-num {
        font-size: 16px;
        font-weight: 800;
        color: #2c7a7b;
        line-height: 1.2;
        transition: color 0.3s ease;
    }
    

    body.dark-theme .card-stat .cs-num { color: #4fd1c5; }

    .card-stat .cs-label {
        font-size: 10px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-top: 2px;
    }

    body.dark-theme .card-stat .cs-label { color: #9ca3af; }

    .card-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #2c7a7b;
        margin-top: 4px;
        transition: gap 0.2s ease;
    }

    .election-card:hover .card-cta {
        gap: 10px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .status-active    { background: #d1fae5; color: #065f46; }
    .status-upcoming  { background: #fef3c7; color: #92400e; }
    .status-completed { background: #e5e7eb; color: #374151; }

    body.dark-theme .status-active   { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .status-upcoming { background: #78350f; color: #fde68a; }
    body.dark-theme .status-completed{ background: #374151; color: #e5e7eb; }

    .no-data {
        text-align: center;
        padding: 50px 20px;
        background: #f9fafb;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        grid-column: 1 / -1;
    }

    body.dark-theme .no-data {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .no-data i {
        font-size: 42px;
        color: #d1d5db;
        margin-bottom: 12px;
        display: block;
    }

    body.dark-theme .no-data i { color: #4b5563; }

    .no-data p { color: #9ca3af; font-size: 14px; }

    /* ────────────────────────────────────────────────
       Results modal
       ──────────────────────────────────────────────── */
    .results-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 1000;
        padding: 20px;
        overflow-y: auto;
        animation: fadeIn 0.2s ease;
    }

    .results-modal.open {
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    .results-modal-content {
        background: #ffffff;
        border-radius: 16px;
        width: 100%;
        max-width: 900px;
        margin: 40px 0;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: slideDown 0.25s ease;
    }

    body.dark-theme .results-modal-content {
        background: #1e1e2e;
    }

    @keyframes slideDown {
        from { transform: translateY(-30px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }

    .results-modal-header {
        background-color: #2c7a7b;
        color: white;
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    body.dark-theme .results-modal-header { background-color: #0f172a; }

    .results-modal-header h2 {
        font-size: 18px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
    }

    .results-modal-header h2 i {
        font-size: 16px;
        opacity: 0.9;
    }

    .results-modal-header .modal-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .results-modal-header .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .results-modal-body {
        padding: 24px;
        min-height: 200px;
    }

    .results-loading {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }

    .results-loading .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e5e7eb;
        border-top-color: #2c7a7b;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 16px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    body.dark-theme .results-loading { color: #9ca3af; }
    body.dark-theme .results-loading .spinner { border-color: #3d3d4d; border-top-color: #4fd1c5; }

    /* Results table inside modal */
    .results-modal-body h3 {
        color: #374151;
        margin-top: 22px;
        margin-bottom: 12px;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    body.dark-theme .results-modal-body h3 { color: #e5e7eb; }

    .results-modal-body h3:first-child { margin-top: 0; }

    .results-modal-body h3 i { color: #2c7a7b; font-size: 13px; }

    .votes-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border-radius: 10px;
        overflow: hidden;
    }

    .votes-table th,
    .votes-table td {
        border: 1px solid #e5e7eb;
        padding: 11px 14px;
        text-align: left;
        font-size: 13px;
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
        font-size: 10px;
        letter-spacing: 0.5px;
    }

    body.dark-theme .votes-table th { background-color: #0f172a; }

    .votes-table tr:hover { background-color: #f9fafb; }
    body.dark-theme .votes-table tr:hover { background-color: #2d2d3d; }

    .votes-table tr.winner-row { background-color: #d1fae5; }
    body.dark-theme .votes-table tr.winner-row { background-color: #064e3b; }
    .votes-table tr.winner-row:hover { background-color: #a7f3d0; }
    body.dark-theme .votes-table tr.winner-row:hover { background-color: #065f46; }

    .candidate-image {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #2c7a7b;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        background: #f4f7f9;
    }

    .winner-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background-color: #f59e0b;
        color: #ffffff;
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: 6px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .vote-count {
        font-size: 14px;
        font-weight: 700;
        color: #2c7a7b;
    }

    body.dark-theme .vote-count { color: #4fd1c5; }

    .percentage-bar {
        background: #e5e7eb;
        border-radius: 10px;
        height: 6px;
        width: 100%;
        overflow: hidden;
    }

    body.dark-theme .percentage-bar { background: #3d3d4d; }

    .percentage-fill {
        background-color: #2c7a7b;
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .percentage-text {
        font-size: 12px;
        color: #6b7280;
        min-width: 42px;
    }

    body.dark-theme .percentage-text { color: #9ca3af; }

    .inline-info {
        color: #6b7280;
        font-style: italic;
        text-align: center;
        padding: 16px;
        font-size: 13px;
    }

    body.dark-theme .inline-info { color: #9ca3af; }

    /* Live pulse animation when a vote count changes */
    @keyframes countPulse {
        0%   { color: #10b981; }
        100% { color: inherit; }
    }

    .count-updated {
        animation: countPulse 1.2s ease;
        font-weight: 800;
    }

    /* Mobile */
    @media (max-width: 900px) {
        .stats-row { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .results-hero { padding: 18px 20px; gap: 14px; }
        .results-hero-icon { width: 42px; height: 42px; font-size: 22px; }
        .results-hero-text h1 { font-size: 18px; }
        .results-hero-text p { font-size: 12px; }
        .elections-grid { grid-template-columns: 1fr; }
        .results-modal { padding: 10px; }
        .results-modal-content { margin: 10px 0; }
        .results-modal-body { padding: 16px; }
        .results-modal-header { padding: 14px 18px; }
        .results-modal-header h2 { font-size: 15px; }
        .votes-table th, .votes-table td { padding: 8px 10px; font-size: 12px; }
        .candidate-image { width: 34px; height: 34px; }
        .vote-count { font-size: 12px; }
    }
</style>

<div class="results-hero">
    <div class="results-hero-icon">
        <i class="fas fa-chart-bar"></i>
    </div>
    <div class="results-hero-text">
        <h1>Election Results</h1>
        <p>Click any election card to view detailed results</p>
    </div>
    <span class="live-indicator" title="Results update automatically">
        <span class="live-dot"></span> Live
    </span>
</div>

<div class="stats-row">
    <div class="stat-box">
        <i class="fas fa-vote-yea"></i>
        <div class="stat-info">
            <div class="number"><?php echo number_format($totalVotesByUser); ?></div>
            <div class="label">Your Votes Cast</div>
        </div>
    </div>
    <div class="stat-box">
        <i class="fas fa-user-tie"></i>
        <div class="stat-info">
            <div class="number"><?php echo number_format($totalApplicationsByUser); ?></div>
            <div class="label">Your Candidacy Applications</div>
        </div>
    </div>
    <div class="stat-box">
        <i class="fas fa-calendar-check"></i>
        <div class="stat-info">
            <div class="number"><?php echo number_format($totalRegistrationsByUser); ?></div>
            <div class="label">Your Election Registrations</div>
        </div>
    </div>
</div>

<!-- Elections as clickable cards -->
<div class="elections-grid">
    <?php if (empty($elections)): ?>
        <div class="no-data">
            <i class="fas fa-vote-yea"></i>
            <p>No elections available at this time.</p>
            <p>Please check back later for upcoming elections.</p>
        </div>
    <?php else: ?>
        <?php foreach ($elections as $election): ?>
            <div class="election-card"
                 data-election-id="<?php echo (int)$election['id']; ?>"
                 onclick="openResults(<?php echo (int)$election['id']; ?>, '<?php echo htmlspecialchars(addslashes($election['title'])); ?>')">
                <div class="election-card-header">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <span class="status-pill status-<?php echo htmlspecialchars($election['status']); ?>">
                        <i class="fas <?php echo $election['status'] === 'active' ? 'fa-play' : ($election['status'] === 'completed' ? 'fa-flag-checkered' : 'fa-clock'); ?>"></i>
                        <?php echo ucfirst(htmlspecialchars($election['status'])); ?>
                    </span>
                </div>

                <?php if (!empty($election['description'])): ?>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                <?php endif; ?>

                <div class="election-card-meta">
                    <?php if (!empty($election['start_date'])): ?>
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($election['start_date'])); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($election['end_date'])): ?>
                        <span><i class="far fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($election['end_date'])); ?></span>
                    <?php endif; ?>
                </div>

                <div class="card-stats">
                    <div class="card-stat">
                        <div class="cs-num" data-role="votes-count"><?php echo number_format($election['votes_count']); ?></div>
                        <div class="cs-label">Votes</div>
                    </div>
                    <div class="card-stat">
                        <div class="cs-num"><?php echo number_format($election['candidates_count']); ?></div>
                        <div class="cs-label">Candidates</div>
                    </div>
                    <div class="card-stat">
                        <div class="cs-num"><?php echo number_format($election['positions_count']); ?></div>
                        <div class="cs-label">Positions</div>
                    </div>
                </div>

                <div class="card-cta">
                    View Results <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Results modal -->
<div class="results-modal" id="resultsModal" onclick="if(event.target === this) closeResults()">
    <div class="results-modal-content" onclick="event.stopPropagation()">
        <div class="results-modal-header">
            <h2><i class="fas fa-poll"></i> <span id="resultsModalTitle">Election Results</span></h2>
            <button type="button" class="modal-close" onclick="closeResults()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="results-modal-body" id="resultsModalBody">
            <div class="results-loading">
                <div class="spinner"></div>
                Loading results...
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    const POLL_INTERVAL_MS = 15000;
    const RESULTS_API = 'api/election_results.php';
    const SUMMARY_API = 'api/election_summary.php';

    let currentElectionId = null;
    let resultsPollTimer = null;

    // ────────────────────────────────────────────────────
    // Modal open / close
    // ────────────────────────────────────────────────────
    window.openResults = function(electionId, title) {
        currentElectionId = electionId;
        document.getElementById('resultsModalTitle').textContent = title;

        const modal = document.getElementById('resultsModal');
        const body = document.getElementById('resultsModalBody');

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        body.innerHTML = '<div class="results-loading"><div class="spinner"></div>Loading results...</div>';

        fetchResults();

        if (resultsPollTimer) clearInterval(resultsPollTimer);
        resultsPollTimer = setInterval(fetchResults, POLL_INTERVAL_MS);
    };

    window.closeResults = function() {
        currentElectionId = null;
        document.getElementById('resultsModal').classList.remove('open');
        document.body.style.overflow = '';
        if (resultsPollTimer) {
            clearInterval(resultsPollTimer);
            resultsPollTimer = null;
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentElectionId !== null) {
            closeResults();
        }
    });

    // ────────────────────────────────────────────────────
    // Fetch + render results
    // ────────────────────────────────────────────────────
    async function fetchResults() {
        if (!currentElectionId) return;

        try {
            const res = await fetch(RESULTS_API + '?election_id=' + currentElectionId, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Failed to load');

            renderResults(data);

        } catch (err) {
            document.getElementById('resultsModalBody').innerHTML =
                '<p class="inline-info">Could not load results. Retrying…</p>';
        }
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }

    function shortBio(bio, max) {
        if (!bio) return 'No manifesto provided.';
        const clean = String(bio).trim();
        if (clean.length <= max) return clean;
        return clean.substring(0, max) + '…';
    }

    function renderResults(data) {
        const body = document.getElementById('resultsModalBody');

        if (!data.posts || data.posts.length === 0) {
            body.innerHTML = '<p class="inline-info">No positions available for this election.</p>';
            return;
        }

        const html = data.posts.map(function(post) {
            if (!post.candidates || post.candidates.length === 0) {
                return '<h3><i class="fas fa-user-tie"></i> ' + escapeHtml(post.postname) + '</h3>' +
                       '<p class="inline-info">No candidates for this position</p>';
            }

            const rows = post.candidates.map(function(c, idx) {
                const isWinner = idx === 0 && post.total_votes > 0;
                const pct = post.total_votes > 0 ? (c.votes / post.total_votes) * 100 : 0;
                const hasUserId = c.user_id && c.user_id > 0;
                const profileUrl = hasUserId ? ('candidate.php?user_id=' + c.user_id) : '';

                // The whole row is a link if we have a user_id
                const rowTag = hasUserId
                    ? '<tr class="' + (isWinner ? 'winner-row' : '') + ' candidate-row-link" onclick="window.location.href=\'' + profileUrl + '\'">'
                    : '<tr class="' + (isWinner ? 'winner-row' : '') + '">';

                const bio = shortBio(c.bio, 140);

                return rowTag +
                    '<td style="text-align:center; width:60px;">' +
                        '<img src="' + escapeHtml(c.photo) + '" alt="" class="candidate-image">' +
                    '</td>' +
                    '<td>' +
                        '<strong>' + escapeHtml(c.name) + '</strong>' +
                        (isWinner ? ' <span class="winner-badge"><i class="fas fa-crown"></i> Winner</span>' : '') +
                        '<div class="candidate-bio-line">' + escapeHtml(bio) + '</div>' +
                    '</td>' +
                    '<td style="width:100px;">' +
                        '<span class="vote-count" data-candidate-id="' + c.id + '">' + c.votes.toLocaleString() + '</span> votes' +
                    '</td>' +
                    '<td style="width:170px;">' +
                        '<div style="display:flex; align-items:center; gap:10px;">' +
                            '<div class="percentage-bar" style="flex:1;">' +
                                '<div class="percentage-fill" style="width:' + pct.toFixed(1) + '%;"></div>' +
                            '</div>' +
                            '<span class="percentage-text">' + pct.toFixed(1) + '%</span>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
            }).join('');

            return '<h3><i class="fas fa-user-tie"></i> ' + escapeHtml(post.postname) + '</h3>' +
                   '<table class="votes-table">' +
                       '<thead><tr>' +
                           '<th style="width:60px;">Photo</th>' +
                           '<th>Candidate &amp; Manifesto</th>' +
                           '<th style="width:100px;">Votes</th>' +
                           '<th style="width:170px;">Percentage</th>' +
                       '</tr></thead>' +
                       '<tbody>' + rows + '</tbody>' +
                   '</table>';
        }).join('');

        body.innerHTML = html;
    }

    // ────────────────────────────────────────────────────
    // Poll the summary counts on the cards
    // ────────────────────────────────────────────────────
    async function pollSummary() {
        if (document.visibilityState === 'hidden') return;

        try {
            const res = await fetch(SUMMARY_API, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) return;

            const data = await res.json();
            if (!data.ok || !Array.isArray(data.elections)) return;

            data.elections.forEach(function(e) {
                const card = document.querySelector('.election-card[data-election-id="' + e.election_id + '"]');
                if (!card) return;

                const countEl = card.querySelector('[data-role="votes-count"]');
                if (!countEl) return;

                const previous = parseInt(countEl.textContent.replace(/[^\d]/g, ''), 10) || 0;
                if (previous !== e.votes_count) {
                    countEl.textContent = e.votes_count.toLocaleString();
                    countEl.classList.add('count-updated');
                    setTimeout(function() { countEl.classList.remove('count-updated'); }, 1200);
                }
            });

        } catch (err) {
            // Silent — retry next cycle
        }
    }

    setInterval(function() {
        if (currentElectionId === null) pollSummary();
    }, POLL_INTERVAL_MS);

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            if (currentElectionId === null) {
                pollSummary();
            } else {
                fetchResults();
            }
        }
    });

    pollSummary();
})();
</script>

<?php include("footer.php"); ?>
