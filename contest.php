<?php
session_start();

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('display_startup_errors', $isLocal ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// ────────────────────────────────────────────────────────────
// Fetch elections — filter by derived status in PHP
// ────────────────────────────────────────────────────────────
try {
    $electionsStmt = $conn->prepare("
        SELECT e.id, e.title, e.description, e.status,
               e.start_date, e.end_date, e.manual_status, e.manual_until,
               (SELECT COUNT(DISTINCT postname) FROM contesters
                 WHERE election_id = e.id AND status = 'approved') AS positions_count,
               (SELECT COUNT(*) FROM contesters
                 WHERE election_id = e.id AND status = 'approved') AS candidates_count
        FROM elections e
        ORDER BY e.start_date DESC
    ");
    $electionsStmt->execute();
    $allElections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allElections as &$e) {
        $e['live_status'] = effectiveElectionStatus($e);
    }
    unset($e);

    usort($allElections, function ($a, $b) {
        $order = ['active' => 1, 'upcoming' => 2, 'completed' => 3];
        $oa = $order[$a['live_status']] ?? 4;
        $ob = $order[$b['live_status']] ?? 4;
        if ($oa !== $ob) return $oa - $ob;
        return strtotime($b['start_date']) - strtotime($a['start_date']);
    });

    $elections = $allElections;

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

    .contest-hero-text { flex: 1; min-width: 200px; }
    .contest-hero-text h1 { font-size: 20px; margin-bottom: 4px; font-weight: 700; }
    .contest-hero-text p { font-size: 13px; opacity: 0.9; line-height: 1.5; }

    .elections-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        margin-bottom: 40px;
    }

    .election-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
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

    .candidate-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 4px;
    }

    .candidate-chip {
        font-size: 11px;
        background: #e6f4f4;
        color: #2c7a7b;
        padding: 3px 9px;
        border-radius: 20px;
        font-weight: 500;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    body.dark-theme .candidate-chip {
        background: rgba(44, 122, 123, 0.25);
        color: #a7f3d0;
    }

    .candidate-chip.more {
        background: #f3f4f6;
        color: #6b7280;
    }

    body.dark-theme .candidate-chip.more {
        background: #3d3d4d;
        color: #9ca3af;
    }

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

    .card-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding-top: 10px;
        border-top: 1px dashed #e5e7eb;
        margin-top: auto;
    }

    body.dark-theme .card-stats { border-top-color: #3d3d4d; }

    .card-stat { text-align: center; }

    .card-stat .cs-num {
        font-size: 18px;
        font-weight: 800;
        color: #2c7a7b;
        line-height: 1.2;
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

    .election-card:hover .card-cta { gap: 10px; }

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
        grid-column: 1 / -1;
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

    .contest-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 1000;
        padding: 20px;
        overflow-y: auto;
        animation: fadeIn 0.2s ease;
    }

    .contest-modal.open {
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .contest-modal-content {
        background: #ffffff;
        border-radius: 16px;
        width: 100%;
        max-width: 950px;
        margin: 40px 0;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: slideDown 0.25s ease;
    }

    body.dark-theme .contest-modal-content { background: #1e1e2e; }

    @keyframes slideDown {
        from { transform: translateY(-30px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }

    .contest-modal-header {
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

    body.dark-theme .contest-modal-header { background-color: #0f172a; }

    .contest-modal-header h2 {
        font-size: 18px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .contest-modal-header h2 i { font-size: 16px; opacity: 0.9; flex-shrink: 0; }

    .modal-close {
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

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .contest-modal-body {
        padding: 24px;
        min-height: 200px;
    }

    .modal-loading {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }

    .modal-loading .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e5e7eb;
        border-top-color: #2c7a7b;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 16px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    body.dark-theme .modal-loading { color: #9ca3af; }
    body.dark-theme .modal-loading .spinner { border-color: #3d3d4d; border-top-color: #4fd1c5; }

    .modal-position {
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    body.dark-theme .modal-position {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .modal-position:last-child { margin-bottom: 0; }

    .modal-position-header {
        background: #f9fafb;
        padding: 14px 18px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    body.dark-theme .modal-position-header {
        background: #2d2d3d;
        border-bottom-color: #3d3d4d;
    }

    .modal-position-title {
        font-size: 15px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    body.dark-theme .modal-position-title { color: #f3f4f6; }

    .modal-position-title i { color: #2c7a7b; font-size: 14px; }

    .modal-position-count {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    body.dark-theme .modal-position-count { color: #9ca3af; }

    .modal-candidates {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 12px;
        padding: 16px;
    }

    .modal-candidate {
        display: flex;
        gap: 12px;
        padding: 14px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    body.dark-theme .modal-candidate {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    .modal-candidate:hover {
        border-color: #2c7a7b;
        box-shadow: 0 4px 12px rgba(44, 122, 123, 0.08);
    }

    .modal-candidate-image {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #2c7a7b;
        flex-shrink: 0;
        background: #f4f7f9;
    }

    .modal-candidate-info {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .modal-candidate-name {
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
        line-height: 1.3;
    }

    body.dark-theme .modal-candidate-name { color: #f3f4f6; }

    .modal-candidate-bio {
        font-size: 12px;
        color: #6b7280;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    body.dark-theme .modal-candidate-bio { color: #9ca3af; }

    .modal-candidate-bio.expanded {
        -webkit-line-clamp: unset;
        display: block;
    }

    .bio-toggle {
        background: none;
        border: none;
        color: #2c7a7b;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        padding: 0;
        font-family: inherit;
        text-align: left;
        margin-top: 2px;
    }

    .bio-toggle:hover { text-decoration: underline; }

    .modal-candidate-votes {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 600;
        color: #2c7a7b;
        background: #e6f4f4;
        padding: 3px 9px;
        border-radius: 20px;
        align-self: flex-start;
    }

    body.dark-theme .modal-candidate-votes {
        background: rgba(44, 122, 123, 0.25);
        color: #a7f3d0;
    }

    .modal-empty {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    .modal-empty i {
        font-size: 42px;
        display: block;
        margin-bottom: 12px;
        color: #d1d5db;
    }

    body.dark-theme .modal-empty i { color: #4b5563; }

    .modal-empty p { font-size: 13px; }

    @media (max-width: 768px) {
        .contest-hero { padding: 18px 20px; gap: 14px; }
        .contest-hero-icon { width: 42px; height: 42px; font-size: 22px; }
        .contest-hero-text h1 { font-size: 18px; }
        .contest-hero-text p { font-size: 12px; }
        .elections-grid { grid-template-columns: 1fr; }
        .contest-modal { padding: 10px; }
        .contest-modal-content { margin: 10px 0; }
        .contest-modal-header { padding: 14px 18px; }
        .contest-modal-header h2 { font-size: 15px; }
        .contest-modal-body { padding: 16px; }
        .modal-candidates { padding: 12px; gap: 10px; grid-template-columns: 1fr; }
    }
</style>

<div class="contest-hero">
    <div class="contest-hero-icon">
        <i class="fas fa-users"></i>
    </div>
    <div class="contest-hero-text">
        <h1>Election Contestants</h1>
        <p>Click any election to see the full list of candidates, bios, and vote counts</p>
    </div>
</div>

<div class="elections-grid">
    <?php if (empty($elections)): ?>
        <div class="no-data">
            <i class="fas fa-vote-yea"></i>
            <p>No elections found.</p>
        </div>
    <?php else: ?>
        <?php foreach ($elections as $election): ?>
            <?php
                $liveStatus = $election['live_status'];
                $preview = [];
                try {
                    $pv = $conn->prepare("
                        SELECT name FROM contesters
                        WHERE election_id = ? AND status = 'approved'
                        ORDER BY votes DESC, name ASC
                        LIMIT 4
                    ");
                    $pv->execute([$election['id']]);
                    $preview = $pv->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) { $preview = []; }
            ?>
            <div class="election-card"
                 data-election-id="<?php echo (int)$election['id']; ?>"
                 onclick="openContest(<?php echo (int)$election['id']; ?>, '<?php echo htmlspecialchars(addslashes($election['title'])); ?>')">

                <div class="election-card-header">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <span class="status-pill status-<?php echo htmlspecialchars($liveStatus); ?>">
                        <i class="fas <?php echo $liveStatus === 'active' ? 'fa-play' : ($liveStatus === 'completed' ? 'fa-flag-checkered' : 'fa-clock'); ?>"></i>
                        <?php echo ucfirst(htmlspecialchars($liveStatus)); ?>
                    </span>
                </div>

                <?php if (!empty($election['description'])): ?>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                <?php endif; ?>

                <?php if (!empty($preview)): ?>
                    <div class="candidate-preview">
                        <?php foreach ($preview as $name): ?>
                            <span class="candidate-chip"><?php echo htmlspecialchars($name); ?></span>
                        <?php endforeach; ?>
                        <?php if ((int)$election['candidates_count'] > count($preview)): ?>
                            <span class="candidate-chip more">
                                +<?php echo (int)$election['candidates_count'] - count($preview); ?> more
                            </span>
                        <?php endif; ?>
                    </div>
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
                        <div class="cs-num"><?php echo number_format($election['candidates_count']); ?></div>
                        <div class="cs-label">Candidates</div>
                    </div>
                    <div class="card-stat">
                        <div class="cs-num"><?php echo number_format($election['positions_count']); ?></div>
                        <div class="cs-label">Positions</div>
                    </div>
                </div>

                <div class="card-cta">
                    View Contestants <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="contest-modal" id="contestModal" onclick="if(event.target === this) closeContest()">
    <div class="contest-modal-content" onclick="event.stopPropagation()">
        <div class="contest-modal-header">
            <h2><i class="fas fa-users"></i> <span id="contestModalTitle">Contestants</span></h2>
            <button type="button" class="modal-close" onclick="closeContest()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="contest-modal-body" id="contestModalBody">
            <div class="modal-loading">
                <div class="spinner"></div>
                Loading contestants...
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const CONTEST_API = 'api/election_contestants.php';

    let currentElectionId = null;

    window.openContest = async function(electionId, title) {
        currentElectionId = electionId;
        document.getElementById('contestModalTitle').textContent = title;

        const modal = document.getElementById('contestModal');
        const body = document.getElementById('contestModalBody');

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        body.innerHTML = '<div class="modal-loading"><div class="spinner"></div>Loading contestants...</div>';

        try {
            const res = await fetch(CONTEST_API + '?election_id=' + electionId, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Failed to load');

            renderContestants(data);

        } catch (err) {
            body.innerHTML =
                '<div class="modal-empty">' +
                    '<i class="fas fa-exclamation-triangle"></i>' +
                    '<p>Could not load contestants. Please try again.</p>' +
                '</div>';
        }
    };

    window.closeContest = function() {
        currentElectionId = null;
        document.getElementById('contestModal').classList.remove('open');
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentElectionId !== null) closeContest();
    });

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }

    function renderContestants(data) {
        const body = document.getElementById('contestModalBody');

        if (!data.positions || data.positions.length === 0) {
            body.innerHTML =
                '<div class="modal-empty">' +
                    '<i class="fas fa-user-slash"></i>' +
                    '<p>No candidates have applied for this election yet.</p>' +
                '</div>';
            return;
        }

        const html = data.positions.map(function(pos, posIndex) {
            if (!pos.candidates || pos.candidates.length === 0) {
                return '<div class="modal-position">' +
                    '<div class="modal-position-header">' +
                        '<div class="modal-position-title"><i class="fas fa-user-tie"></i> ' + escapeHtml(pos.postname) + '</div>' +
                        '<span class="modal-position-count">No candidates</span>' +
                    '</div>' +
                    '<div class="modal-empty"><i class="fas fa-user-slash"></i><p>No contestants for this position</p></div>' +
                '</div>';
            }

            const cards = pos.candidates.map(function(c, idx) {
                const bioId = 'bio-' + posIndex + '-' + idx;
                const hasLongBio = c.bio && c.bio.length > 180;
                const shortBio = hasLongBio ? c.bio.substring(0, 180) + '…' : (c.bio || 'No bio provided');

                return '<div class="modal-candidate">' +
                    '<img src="' + escapeHtml(c.photo) + '" alt="" class="modal-candidate-image">' +
                    '<div class="modal-candidate-info">' +
                        '<div class="modal-candidate-name">' + escapeHtml(c.name) + '</div>' +
                        '<div class="modal-candidate-bio" id="' + bioId + '">' + escapeHtml(shortBio) + '</div>' +
                        (hasLongBio ? '<button type="button" class="bio-toggle" onclick="toggleBio(\'' + bioId + '\', \'' + escapeHtml(c.bio.replace(/'/g, "\\'")).replace(/\n/g, '\\n') + '\')">Read more</button>' : '') +
                        '<span class="modal-candidate-votes">' +
                            '<i class="fas fa-vote-yea"></i> ' + c.votes.toLocaleString() + ' votes' +
                        '</span>' +
                    '</div>' +
                '</div>';
            }).join('');

            return '<div class="modal-position">' +
                '<div class="modal-position-header">' +
                    '<div class="modal-position-title"><i class="fas fa-user-tie"></i> ' + escapeHtml(pos.postname) + '</div>' +
                    '<span class="modal-position-count">' + pos.candidates.length + ' candidate' + (pos.candidates.length === 1 ? '' : 's') + '</span>' +
                '</div>' +
                '<div class="modal-candidates">' + cards + '</div>' +
            '</div>';
        }).join('');

        body.innerHTML = html;
    }

    window.toggleBio = function(bioId, fullText) {
        const el = document.getElementById(bioId);
        const btn = el.nextElementSibling;
        if (!el || !btn) return;

        if (el.classList.contains('expanded')) {
            el.classList.remove('expanded');
            el.textContent = fullText.substring(0, 180) + '…';
            btn.textContent = 'Read more';
        } else {
            el.classList.add('expanded');
            el.innerHTML = escapeHtml(fullText).replace(/\n/g, '<br>');
            btn.textContent = 'Show less';
        }
    };
})();
</script>

<?php include("footer.php"); ?>
