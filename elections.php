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
$message = "";
$messageType = "";

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? (int)$user['id'] : 0;

// Handle "Request registration" submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_election_id'])) {
    $requestElectionId = (int)$_POST['request_election_id'];

    // Validate the election exists and is open for registration
    $checkElection = $conn->prepare("
        SELECT id, status FROM elections
        WHERE id = ? AND status IN ('active','upcoming')
    ");
    $checkElection->execute([$requestElectionId]);
    $targetElection = $checkElection->fetch(PDO::FETCH_ASSOC);

    if (!$targetElection) {
        $message = "That election is not open for registration.";
        $messageType = "error";
    } else {
        // Check for existing request or approved registration
        $checkExisting = $conn->prepare("
            SELECT status FROM election_registration_requests
            WHERE user_id = ? AND election_id = ?
        ");
        $checkExisting->execute([$userId, $requestElectionId]);
        $existing = $checkExisting->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['status'] === 'pending') {
                $message = "Your registration request is already pending admin approval.";
                $messageType = "info";
            } elseif ($existing['status'] === 'approved') {
                $message = "You are already approved for this election.";
                $messageType = "info";
            } else {
                // Previously rejected — allow re-request by resetting to pending
                $conn->prepare("
                    UPDATE election_registration_requests
                    SET status = 'pending', requested_at = NOW(),
                        reviewed_at = NULL, reviewed_by = NULL, rejection_reason = NULL
                    WHERE user_id = ? AND election_id = ?
                ")->execute([$userId, $requestElectionId]);

                $conn->prepare("
                    INSERT INTO event_log (username, event_type, event_description)
                    VALUES (?, 'Election Registration Request', ?)
                ")->execute([$username, "Resubmitted registration for election ID $requestElectionId"]);

                $message = "Your registration request has been resubmitted for review.";
                $messageType = "success";
            }
        } else {
            // New request
            try {
                $conn->prepare("
                    INSERT INTO election_registration_requests
                    (user_id, election_id, status)
                    VALUES (?, ?, 'pending')
                ")->execute([$userId, $requestElectionId]);

                $conn->prepare("
                    INSERT INTO event_log (username, event_type, event_description)
                    VALUES (?, 'Election Registration Request', ?)
                ")->execute([$username, "Requested registration for election ID $requestElectionId"]);

                $message = "Your registration request has been submitted. Please wait for admin approval.";
                $messageType = "success";
            } catch (PDOException $e) {
                error_log("Registration request error: " . $e->getMessage());
                $message = "Could not submit your request. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// Fetch all open elections with this user's request status
$electionsStmt = $conn->prepare("
    SELECT
        e.id, e.title, e.description, e.start_date, e.end_date, e.status,
        r.status AS request_status,
        r.rejection_reason,
        ue.id AS approved_registration_id
    FROM elections e
    LEFT JOIN election_registration_requests r
        ON r.election_id = e.id AND r.user_id = ?
    LEFT JOIN user_elections ue
        ON ue.election_id = e.id AND ue.user_id = ?
    WHERE e.status IN ('active','upcoming')
    ORDER BY e.start_date ASC
");
$electionsStmt->execute([$userId, $userId]);
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Render the action button for a single election card.
 * Used both by the initial server-render and as the canonical markup
 * that the polling JS mirrors when a status changes.
 */
function renderActionButton(array $election, string $regStatus): string {
    $electionId = (int)$election['id'];

    if ($regStatus === 'approved') {
        return '<button class="btn-request approved" disabled>'
             . '<i class="fas fa-check-circle"></i> You Are Registered'
             . '</button>';
    }

    if ($regStatus === 'pending') {
        return '<button class="btn-request pending" disabled>'
             . '<i class="fas fa-hourglass-half"></i> Awaiting Admin Approval'
             . '</button>';
    }

    if ($regStatus === 'rejected') {
        $html = '<form method="post">'
              . '<button type="submit" name="request_election_id" value="' . $electionId . '"'
              . ' class="btn-request rejected"'
              . ' onclick="return confirm(\'Resubmit your registration request for this election?\');">'
              . '<i class="fas fa-redo"></i> Request Again'
              . '</button>'
              . '</form>';

        if (!empty($election['rejection_reason'])) {
            $html .= '<div class="rejection-note">'
                   . '<strong>Reason:</strong> '
                   . htmlspecialchars($election['rejection_reason'])
                   . '</div>';
        }

        return $html;
    }

    // 'none' — no request yet
    return '<form method="post">'
         . '<button type="submit" name="request_election_id" value="' . $electionId . '"'
         . ' class="btn-request"'
         . ' onclick="return confirm(\'Request to register as a voter for this election?\');">'
         . '<i class="fas fa-user-plus"></i> Request Registration'
         . '</button>'
         . '</form>';
}

/**
 * Resolve the effective registration status for a single election row.
 */
function resolveRegStatus(array $election): string {
    if (!empty($election['approved_registration_id'])) {
        return 'approved';
    }
    if (!empty($election['request_status'])) {
        return $election['request_status'];
    }
    return 'none';
}
?>

<?php include("header.php"); ?>

<style>
    .elections-container {
        max-width: 1000px;
        margin: 0 auto 40px;
        padding: 0 20px;
    }

    .elections-hero {
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

    body.dark-theme .elections-hero {
        background-color: #0f172a;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    .elections-hero-icon {
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

    .elections-hero-text { flex: 1; min-width: 200px; }
    .elections-hero-text h1 { font-size: 20px; margin-bottom: 4px; font-weight: 700; }
    .elections-hero-text p { font-size: 13px; opacity: 0.9; line-height: 1.5; }

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

    .message {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
    }

    .message.success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
    .message.error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #dc2626; }
    .message.info    { background: #eff6ff; color: #1e40af; border-left: 3px solid #3b82f6; }

    body.dark-theme .message.success { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .message.error   { background: #7f1d1d; color: #fecaca; }
    body.dark-theme .message.info    { background: #1e3a8a; color: #dbeafe; }

    .election-grid {
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
        transition: all 0.2s ease;
    }

    body.dark-theme .election-card {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .election-card:hover {
        border-color: #2c7a7b;
        box-shadow: 0 4px 15px rgba(44, 122, 123, 0.1);
    }

    .election-card.flash-update {
        animation: cardFlash 1.2s ease;
    }

    @keyframes cardFlash {
        0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
        50%  { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.25); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
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
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    body.dark-theme .election-card p { color: #9ca3af; }

    .election-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 12px;
        color: #6b7280;
    }

    body.dark-theme .election-meta { color: #9ca3af; }

    .election-meta span { display: inline-flex; align-items: center; gap: 5px; }
    .election-meta i { color: #2c7a7b; font-size: 11px; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .status-active    { background: #d1fae5; color: #065f46; }
    .status-upcoming  { background: #fef3c7; color: #92400e; }
    .status-pending   { background: #fef3c7; color: #92400e; }
    .status-approved  { background: #d1fae5; color: #065f46; }
    .status-rejected  { background: #fee2e2; color: #991b1b; }

    body.dark-theme .status-active   { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .status-upcoming { background: #78350f; color: #fde68a; }
    body.dark-theme .status-pending  { background: #78350f; color: #fde68a; }
    body.dark-theme .status-approved { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .status-rejected { background: #7f1d1d; color: #fecaca; }

    .election-card-actions {
        margin-top: auto;
        padding-top: 6px;
    }

    .btn-request {
        width: 100%;
        padding: 10px 16px;
        background-color: #2c7a7b;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-request:hover:not(:disabled) {
        background-color: #236162;
        transform: translateY(-1px);
    }

    .btn-request:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-request.pending {
        background-color: #f59e0b;
    }

    .btn-request.approved {
        background-color: #10b981;
    }

    .btn-request.rejected {
        background-color: #dc2626;
    }

    .btn-request.rejected:hover:not(:disabled) {
        background-color: #b91c1c;
    }

    .rejection-note {
        font-size: 12px;
        color: #991b1b;
        margin-top: 6px;
        padding: 6px 10px;
        background: #fee2e2;
        border-radius: 8px;
    }

    body.dark-theme .rejection-note {
        background: #7f1d1d;
        color: #fecaca;
    }

    .empty-state {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 60px 20px;
        text-align: center;
        color: #9ca3af;
    }

    body.dark-theme .empty-state {
        background: #1e1e2e;
        border-color: #3d3d4d;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        display: block;
        color: #d1d5db;
    }

    body.dark-theme .empty-state i { color: #4b5563; }

    @media (max-width: 600px) {
        .elections-container { padding: 0 15px; }
        .elections-hero { padding: 18px 20px; gap: 14px; }
        .elections-hero-icon { width: 42px; height: 42px; font-size: 22px; }
        .elections-hero-text h1 { font-size: 18px; }
        .election-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="elections-container">
    <div class="elections-hero">
        <div class="elections-hero-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="elections-hero-text">
            <h1>Open Elections</h1>
            <p>Browse open elections and request voter registration. Your request will be reviewed by an administrator.</p>
        </div>
        <span class="live-indicator" title="This page updates automatically">
            <span class="live-dot"></span> Live
        </span>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($elections)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <p>There are no open elections at this time.</p>
            <p>Please check back later.</p>
        </div>
    <?php else: ?>
        <div class="election-grid">
            <?php foreach ($elections as $election): ?>
                <?php $regStatus = resolveRegStatus($election); ?>
                <div class="election-card" data-election-id="<?php echo (int)$election['id']; ?>">
                    <div class="election-card-header">
                        <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                        <span class="status-pill status-<?php echo htmlspecialchars($election['status']); ?>">
                            <i class="fas <?php echo $election['status'] === 'active' ? 'fa-play' : 'fa-clock'; ?>"></i>
                            <?php echo ucfirst(htmlspecialchars($election['status'])); ?>
                        </span>
                    </div>

                    <?php if (!empty($election['description'])): ?>
                        <p><?php echo htmlspecialchars($election['description']); ?></p>
                    <?php endif; ?>

                    <div class="election-meta">
                        <?php if (!empty($election['start_date'])): ?>
                            <span><i class="far fa-calendar-alt"></i> Starts <?php echo date('M d, Y', strtotime($election['start_date'])); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($election['end_date'])): ?>
                            <span><i class="far fa-calendar-check"></i> Ends <?php echo date('M d, Y', strtotime($election['end_date'])); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="election-card-actions"
                         data-role="actions"
                         data-status="<?php echo htmlspecialchars($regStatus); ?>">
                        <?php echo renderActionButton($election, $regStatus); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const POLL_INTERVAL_MS = 15000; // 15 seconds
    const API_URL = 'api_election_status.php';

    // Track last-known statuses so we only touch the DOM when something changed
    const lastKnownStatus = {};

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }

    function buildButtonHtml(electionId, status, rejectionReason) {
        if (status === 'approved') {
            return '<button class="btn-request approved" disabled>'
                 + '<i class="fas fa-check-circle"></i> You Are Registered'
                 + '</button>';
        }
        if (status === 'pending') {
            return '<button class="btn-request pending" disabled>'
                 + '<i class="fas fa-hourglass-half"></i> Awaiting Admin Approval'
                 + '</button>';
        }
        if (status === 'rejected') {
            let html = '<form method="post">'
                     + '<button type="submit" name="request_election_id" value="' + electionId + '"'
                     + ' class="btn-request rejected"'
                     + ' onclick="return confirm(\'Resubmit your registration request for this election?\');">'
                     + '<i class="fas fa-redo"></i> Request Again'
                     + '</button>'
                     + '</form>';
            if (rejectionReason) {
                html += '<div class="rejection-note">'
                     + '<strong>Reason:</strong> ' + escapeHtml(rejectionReason)
                     + '</div>';
            }
            return html;
        }
        return '<form method="post">'
             + '<button type="submit" name="request_election_id" value="' + electionId + '"'
             + ' class="btn-request"'
             + ' onclick="return confirm(\'Request to register as a voter for this election?\');">'
             + '<i class="fas fa-user-plus"></i> Request Registration'
             + '</button>'
             + '</form>';
    }

    async function pollOnce() {
        // Don't poll while the tab is hidden — saves requests and battery
        if (document.visibilityState === 'hidden') return;

        try {
            const res = await fetch(API_URL, {
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!res.ok) return;

            const data = await res.json();
            if (!data.ok || !Array.isArray(data.elections)) return;

            // 1. If the set of open elections changed, do a soft page reload
            const serverIds = data.elections.map(e => String(e.election_id)).sort().join(',');
            const renderedIds = Array.from(document.querySelectorAll('.election-card'))
                                     .map(el => el.dataset.electionId).sort().join(',');

            if (serverIds !== renderedIds) {
                location.reload();
                return;
            }

            // 2. Update any buttons whose status changed since last poll
            data.elections.forEach(e => {
                const id = e.election_id;
                const container = document.querySelector(
                    '.election-card[data-election-id="' + id + '"] [data-role="actions"]'
                );
                if (!container) return;

                const prev = lastKnownStatus[id];
                if (prev === e.reg_status) return; 

                if (prev !== undefined) {
                    // Only redraw if this isn't the very first poll
                    container.innerHTML = buildButtonHtml(id, e.reg_status, e.rejection_reason);
                    container.dataset.status = e.reg_status;

                    // Brief green flash on the card to signal "something just updated"
                    const card = container.closest('.election-card');
                    if (card) {
                        card.classList.add('flash-update');
                        setTimeout(() => card.classList.remove('flash-update'), 1200);
                    }
                }

                lastKnownStatus[id] = e.reg_status;
            });

        } catch (err) {
            // Silent — network hiccup; next tick will retry
        }
    }

    // Prime the cache with the current server-rendered statuses
    document.querySelectorAll('.election-card').forEach(card => {
        const id = parseInt(card.dataset.electionId, 10);
        const actions = card.querySelector('[data-role="actions"]');
        if (actions && id) {
            lastKnownStatus[id] = actions.dataset.status || 'none';
        }
    });

    // Regular polling
    setInterval(pollOnce, POLL_INTERVAL_MS);

    // Catch up immediately when the user returns to the tab
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            pollOnce();
        }
    });
})();
</script>

<?php include("footer.php"); ?>
