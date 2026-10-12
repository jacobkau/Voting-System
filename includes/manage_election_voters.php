<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/conn.php';
require_once __DIR__ . '/../cloudinary.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}


error_log(sprintf(
    "EmailJS DEBUG: SERVICE=[%s] TEMPLATE=[%s] PUBLIC=[%s] PRIVATE=[%s]",
    getenv('EMAILJS_SERVICE_ID') ?: 'MISSING',
    getenv('EMAILJS_DECISION_TEMPLATE_ID') ?: 'MISSING',
    getenv('EMAILJS_PUBLIC_KEY') ? 'SET' : 'MISSING',
    getenv('EMAILJS_PRIVATE_KEY') ? 'SET' : 'MISSING'
));


$adminId = (int)$_SESSION['admin_id'];
$message = "";
$messageType = "";

// Read message from PRG redirect
if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['msg_type'] ?? 'success';
}

function sendDecisionEmail(string $email, string $userName, string $electionTitle, string $decision, string $reason = ''): array {
    $serviceId  = getenv('EMAILJS_SERVICE_ID');
    $templateId = getenv('EMAILJS_DECISION_TEMPLATE_ID');
    $publicKey  = getenv('EMAILJS_PUBLIC_KEY');
    $privateKey = getenv('EMAILJS_PRIVATE_KEY');

    if (!$serviceId || !$templateId || !$publicKey) {
        return ['sent' => false, 'error' => 'EmailJS not configured.'];
    }

    if ($decision === 'approved') {
        $subjectLine = 'Your registration has been approved';
        $messageBody = 'Great news — your request to register as a voter for "' . $electionTitle . '" has been approved. You can now cast your vote when the election opens.';
    } elseif ($decision === 'unapproved') {
        $subjectLine = 'Your registration has been revoked';
        $messageBody = 'Your previously approved registration for "' . $electionTitle . '" has been revoked by an administrator. You have been returned to the pending queue. If you believe this is in error, please contact support.';
        if ($reason !== '') {
            $messageBody .= "\n\nReason: " . $reason;
        }
    } else {
        $subjectLine = 'Your registration was not approved';
        $messageBody = 'We regret to inform you that your request to register as a voter for "' . $electionTitle . '" was not approved.';
        if ($reason !== '') {
            $messageBody .= "\n\nReason: " . $reason;
        }
    }

    $payload = [
        'service_id'      => $serviceId,
        'template_id'     => $templateId,
        'user_id'         => $publicKey,
        'template_params' => [
            'user_name'      => $userName,
            'user_email'     => $email,
            'election_title' => $electionTitle,
            'decision'       => $decision,
            'subject_line'   => $subjectLine,
            'message_body'   => $messageBody,
            'reason'         => $reason,
        ],
    ];

    if ($privateKey) {
        $payload['accessToken'] = $privateKey;
    }

    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && !$curlError) {
        return ['sent' => true, 'error' => null];
    }

    $err = "HTTP {$httpCode} — " . ($response ?: $curlError);
    error_log("EmailJS decision email error: " . $err);
    return ['sent' => false, 'error' => $err];
}

/**
 * Check whether a user has cast any votes in the given election.
 * Used as a safety guard before unapproving.
 */
function userHasVotesInElection(PDO $conn, int $userId, int $electionId): int {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE user_id = ? AND election_id = ?");
        $stmt->execute([$userId, $electionId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Vote check error: " . $e->getMessage());
        return 0;
    }
}

// Handle approve/reject/unapprove action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_id'], $_POST['decision'])) {
    $requestId = (int)$_POST['request_id'];
    $decision = $_POST['decision'];
    $reason = trim($_POST['reason'] ?? '');

    // Capture context for redirect
    $returnStatus = $_POST['return_status'] ?? 'pending';
    $returnElectionId = isset($_POST['return_election_id']) ? (int)$_POST['return_election_id'] : 0;

    if (!in_array($decision, ['approve', 'reject', 'unapprove'], true)) {
        $message = "Invalid decision.";
        $messageType = "error";
    } else {
        try {
            $conn->beginTransaction();

            // Fetch the request + user details in one shot
            $reqStmt = $conn->prepare("
                SELECT r.*,
                       u.username, u.name AS full_name, u.email,
                       e.title AS election_title
                FROM election_registration_requests r
                JOIN users u ON r.user_id = u.id
                JOIN elections e ON r.election_id = e.id
                WHERE r.id = ?
            ");
            $reqStmt->execute([$requestId]);
            $request = $reqStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception("Request not found.");
            }

            if ($decision === 'approve') {
                $conn->prepare("
                    INSERT IGNORE INTO user_elections (user_id, election_id)
                    VALUES (?, ?)
                ")->execute([$request['user_id'], $request['election_id']]);

                $conn->prepare("
                    UPDATE election_registration_requests
                    SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?, rejection_reason = NULL
                    WHERE id = ?
                ")->execute([$adminId, $requestId]);

                $logDesc = "Approved voter registration: {$request['username']} for '{$request['election_title']}'";
                $message = "Approved registration for {$request['username']}.";

            } elseif ($decision === 'reject') {
                $conn->prepare("
                    UPDATE election_registration_requests
                    SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ?
                    WHERE id = ?
                ")->execute([$adminId, $reason ?: null, $requestId]);

                $logDesc = "Rejected voter registration: {$request['username']} for '{$request['election_title']}'";
                $message = "Rejected registration for {$request['username']}.";

            } else { // unapprove
                // Safety check — block unapproval if the user has already cast votes
                $voteCount = userHasVotesInElection($conn, (int)$request['user_id'], (int)$request['election_id']);
                if ($voteCount > 0) {
                    throw new Exception("Cannot unapprove — this voter has already cast {$voteCount} vote(s) in this election.");
                }

                // Remove from user_elections
                $conn->prepare("
                    DELETE FROM user_elections
                    WHERE user_id = ? AND election_id = ?
                ")->execute([$request['user_id'], $request['election_id']]);

                // Reset the request back to pending so it can be re-reviewed
                $conn->prepare("
                    UPDATE election_registration_requests
                    SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL, rejection_reason = NULL
                    WHERE id = ?
                ")->execute([$requestId]);

                $logDesc = "Unapproved voter registration: {$request['username']} for '{$request['election_title']}'";
                $message = "Unapproved registration for {$request['username']}. The request is back in the pending queue.";
            }

            $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description)
                VALUES (?, 'Voter Registration Review', ?)
            ")->execute([$_SESSION['username'] ?? 'admin', $logDesc]);

            $conn->commit();
            $messageType = "success";

            // Send email AFTER commit — email failure doesn't roll back the decision
            $recipientName = !empty($request['full_name']) ? $request['full_name'] : $request['username'];

            if ($decision === 'approve') {
                $emailResult = sendDecisionEmail(
                    $request['email'], $recipientName, $request['election_title'],
                    'approved', ''
                );
            } elseif ($decision === 'reject') {
                $emailResult = sendDecisionEmail(
                    $request['email'], $recipientName, $request['election_title'],
                    'rejected', $reason
                );
            } else {
                $emailResult = sendDecisionEmail(
                    $request['email'], $recipientName, $request['election_title'],
                    'unapproved', $reason
                );
            }

            if ($emailResult['sent']) {
                $message .= " Email notification sent.";
            } else {
                $message .= " (Email could not be sent — please notify manually.)";
            }

        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("Approve/reject/unapprove error: " . $e->getMessage());
            $message = $e->getMessage() ?: "Could not process the request. Please try again.";
            $messageType = "error";
        }
    }

    $redirect = 'main.php?page=voters'
              . '&status=' . urlencode($returnStatus)
              . ($returnElectionId ? '&election_id=' . $returnElectionId : '')
              . '&msg=' . urlencode($message)
              . '&msg_type=' . urlencode($messageType);
    header('Location: ' . $redirect);
    exit;
}

// Optional filter
$filterElectionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : null;
$filterStatus = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, ['pending', 'approved', 'rejected', 'all'], true)) {
    $filterStatus = 'pending';
}

// Fetch all elections for the dropdown
$elections = $conn->query("SELECT id, title, status FROM elections ORDER BY id DESC")
    ->fetchAll(PDO::FETCH_ASSOC);

// Build the requests query
$where = [];
$params = [];

if ($filterElectionId) {
    $where[] = "r.election_id = ?";
    $params[] = $filterElectionId;
}
if ($filterStatus !== 'all') {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $conn->prepare("
    SELECT
        r.id, r.status, r.requested_at, r.reviewed_at, r.rejection_reason,
        u.id AS user_id, u.username, u.name AS full_name, u.email, u.profile_photo,
        e.id AS election_id, e.title AS election_title, e.status AS election_status,
        (SELECT COUNT(*) FROM votes v WHERE v.user_id = r.user_id AND v.election_id = r.election_id) AS votes_cast
    FROM election_registration_requests r
    JOIN users u ON r.user_id = u.id
    JOIN elections e ON r.election_id = e.id
    $whereSql
    ORDER BY r.requested_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for badges
$countsStmt = $conn->query("
    SELECT status, COUNT(*) AS c
    FROM election_registration_requests
    GROUP BY status
");
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $countsStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = (int)$row['c'];
}

// Helper to build a tab URL
function tabUrl(string $status, ?int $electionId): string {
    $url = '?page=voters&status=' . urlencode($status);
    if ($electionId) {
        $url .= '&election_id=' . (int)$electionId;
    }
    return $url;
}
?>

<style>
    .manage-voters-wrap { padding: 4px; }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }

    .page-header h1 {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-header h1 i { color: #2c7a7b; }

    .filters {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 18px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .filters form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .filters select,
    .filters input {
        padding: 8px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        background: white;
        color: #1f2937;
    }

    .filters button {
        padding: 8px 16px;
        background-color: #2c7a7b;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
    }

    .filters button:hover { background-color: #236162; }

    .filter-tabs {
        display: flex;
        gap: 6px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: 7px 14px;
        border-radius: 20px;
        font-size: 13px;
        text-decoration: none;
        background: #f3f4f6;
        color: #4b5563;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .filter-tab:hover { background: #e5e7eb; }

    .filter-tab.active {
        background: #2c7a7b;
        color: white;
    }

    .filter-tab .count {
        background: rgba(0, 0, 0, 0.08);
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 700;
    }

    .filter-tab.active .count {
        background: rgba(255, 255, 255, 0.25);
    }

    .requests-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .requests-table th,
    .requests-table td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        font-size: 13px;
    }

    .requests-table th {
        background: #f3f4f6;
        color: #374151;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .requests-table tr:last-child td { border-bottom: none; }

    .requests-table tr:hover { background: #f9fafb; }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #2c7a7b;
        background: #f4f7f9;
        flex-shrink: 0;
    }

    .user-detail { min-width: 0; }
    .user-name { font-weight: 600; color: #1f2937; }
    .user-email { color: #6b7280; font-size: 12px; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .status-pending  { background: #fef3c7; color: #92400e; }
    .status-approved { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-active   { background: #d1fae5; color: #065f46; }
    .status-upcoming { background: #fef3c7; color: #92400e; }
    .status-completed{ background: #e5e7eb; color: #374151; }

    .action-buttons {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .action-buttons button {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }

    .btn-approve {
        background: #10b981;
        color: white;
    }
    .btn-approve:hover { background: #059669; }

    .btn-reject {
        background: #dc2626;
        color: white;
    }
    .btn-reject:hover { background: #b91c1c; }

    .btn-unapprove {
        background: #f59e0b;
        color: white;
    }
    .btn-unapprove:hover { background: #d97706; }

    .btn-unapprove:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .reject-note {
        font-size: 11px;
        color: #991b1b;
        margin-top: 4px;
        font-style: italic;
    }

    .votes-warning {
        font-size: 11px;
        color: #b45309;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    body.dark-theme .votes-warning { color: #fbbf24; }

    .message {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .message.success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
    .message.error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #dc2626; }

    .empty-state {
        padding: 60px 20px;
        text-align: center;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 42px;
        margin-bottom: 12px;
        display: block;
        color: #d1d5db;
    }

    /* Dark theme */
    body.dark-theme .page-header h1 { color: #f3f4f6; }
    body.dark-theme .filters {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }
    body.dark-theme .filters select,
    body.dark-theme .filters input {
        background: #1e1e2e;
        border-color: #3d3d4d;
        color: #f3f4f6;
    }
    body.dark-theme .filter-tab {
        background: #2d2d3d;
        color: #cbd5e1;
    }
    body.dark-theme .filter-tab:hover { background: #3d3d4d; }
    body.dark-theme .filter-tab.active {
        background: #2c7a7b;
        color: white;
    }
    body.dark-theme .filter-tab .count {
        background: rgba(255, 255, 255, 0.12);
    }
    body.dark-theme .filter-tab.active .count {
        background: rgba(255, 255, 255, 0.25);
    }
    body.dark-theme .requests-table {
        background: #1e1e2e;
    }
    body.dark-theme .requests-table th {
        background: #2d2d3d;
        color: #cbd5e1;
    }
    body.dark-theme .requests-table td {
        border-bottom-color: #3d3d4d;
        color: #e5e7eb;
    }
    body.dark-theme .requests-table tr:hover { background: #2d2d3d; }
    body.dark-theme .user-name { color: #f3f4f6; }
    body.dark-theme .user-email { color: #9ca3af; }
    body.dark-theme .status-pending  { background: #78350f; color: #fde68a; }
    body.dark-theme .status-approved { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .status-rejected { background: #7f1d1d; color: #fecaca; }
    body.dark-theme .status-active   { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .status-upcoming { background: #78350f; color: #fde68a; }
    body.dark-theme .status-completed{ background: #374151; color: #e5e7eb; }
    body.dark-theme .empty-state {
        background: #2d2d3d;
        border-color: #3d3d4d;
    }

    @media (max-width: 768px) {
        .requests-table th,
        .requests-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
        .user-avatar { width: 30px; height: 30px; }
        .action-buttons { flex-direction: column; }
        .action-buttons button { width: 100%; justify-content: center; }
    }
</style>

<div class="manage-voters-wrap">
    <div class="page-header">
        <h1><i class="fas fa-user-check"></i> Election Voter Registrations</h1>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="filter-tabs">
        <a href="<?= tabUrl('pending', $filterElectionId) ?>"
           class="filter-tab <?= $filterStatus === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-hourglass-half"></i> Pending
            <span class="count"><?= $counts['pending'] ?></span>
        </a>
        <a href="<?= tabUrl('approved', $filterElectionId) ?>"
           class="filter-tab <?= $filterStatus === 'approved' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Approved
            <span class="count"><?= $counts['approved'] ?></span>
        </a>
        <a href="<?= tabUrl('rejected', $filterElectionId) ?>"
           class="filter-tab <?= $filterStatus === 'rejected' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Rejected
            <span class="count"><?= $counts['rejected'] ?></span>
        </a>
        <a href="<?= tabUrl('all', $filterElectionId) ?>"
           class="filter-tab <?= $filterStatus === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
        </a>
    </div>

    <div class="filters">
        <form method="get">
            <input type="hidden" name="page" value="voters">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
            <label style="font-size:13px;color:#4b5563;">Filter by election:</label>
            <select name="election_id" onchange="this.form.submit()">
                <option value="">All elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filterElectionId == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['title']) ?> (<?= $e['status'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No <?= htmlspecialchars($filterStatus === 'all' ? '' : $filterStatus) ?> registration requests found.</p>
        </div>
    <?php else: ?>
        <table class="requests-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Election</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <?php
                        $avatar = defaultAvatarUrl();
                        if (!empty($r['profile_photo']) && preg_match('#^https?://#i', $r['profile_photo'])) {
                            $avatar = $r['profile_photo'];
                        }
                        $hasVoted = (int)$r['votes_cast'] > 0;
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="user-avatar">
                                <div class="user-detail">
                                    <div class="user-name"><?= htmlspecialchars($r['full_name'] ?: $r['username']) ?></div>
                                    <div class="user-email"><?= htmlspecialchars($r['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['election_title']) ?></strong>
                            <span class="status-badge status-<?= htmlspecialchars($r['election_status']) ?>"
                                  style="font-size:10px; padding:2px 8px; margin-left:6px;">
                                <?= htmlspecialchars($r['election_status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($r['requested_at'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($r['status']) ?>">
                                <?php
                                    $icon = $r['status'] === 'approved' ? 'fa-check' :
                                            ($r['status'] === 'rejected' ? 'fa-times' : 'fa-hourglass-half');
                                ?>
                                <i class="fas <?= $icon ?>"></i>
                                <?= ucfirst(htmlspecialchars($r['status'])) ?>
                            </span>
                            <?php if ($r['status'] === 'rejected' && !empty($r['rejection_reason'])): ?>
                                <div class="reject-note">"<?= htmlspecialchars($r['rejection_reason']) ?>"</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <div class="action-buttons">
                                    <form method="post" style="display:inline;"
                                          onsubmit="return confirm('Approve <?= htmlspecialchars(addslashes($r['username'])) ?> as a voter? An email will be sent to them.');">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <input type="hidden" name="return_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                        <input type="hidden" name="return_election_id" value="<?= (int)$filterElectionId ?>">
                                        <button type="submit" class="btn-approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline;"
                                          onsubmit="return handleReject(this);">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="hidden" name="reason" value="">
                                        <input type="hidden" name="return_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                        <input type="hidden" name="return_election_id" value="<?= (int)$filterElectionId ?>">
                                        <button type="submit" class="btn-reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($r['status'] === 'approved'): ?>
                                <?php if ($hasVoted): ?>
                                    <button type="button" class="btn-unapprove" disabled
                                            title="Cannot unapprove — this voter has already cast votes in this election.">
                                        <i class="fas fa-lock"></i> Unapprove
                                    </button>
                                    <div class="votes-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Voter has cast <?= (int)$r['votes_cast'] ?> vote(s) — cannot unapprove.
                                    </div>
                                <?php else: ?>
                                    <form method="post" style="display:inline;"
                                          onsubmit="return confirmUnapprove(this, '<?= htmlspecialchars(addslashes($r['username'])) ?>');">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="decision" value="unapprove">
                                        <input type="hidden" name="reason" value="">
                                        <input type="hidden" name="return_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                        <input type="hidden" name="return_election_id" value="<?= (int)$filterElectionId ?>">
                                        <button type="submit" class="btn-unapprove">
                                            <i class="fas fa-undo"></i> Unapprove
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#9ca3af;font-size:12px;">
                                    <?= !empty($r['reviewed_at']) ? 'Reviewed ' . date('M d', strtotime($r['reviewed_at'])) : '—' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function handleReject(form) {
    const reason = prompt("Reason for rejection (optional, will be shown to the user and included in the email):");
    if (reason === null) return false;
    form.querySelector('input[name="reason"]').value = reason;
    return true;
}

function confirmUnapprove(form, username) {
    const msg = 'Unapprove ' + username + ' for this election?\n\n' +
                'This will:\n' +
                '  - Remove them from the registered voters list\n' +
                '  - Return their request to the pending queue\n' +
                '  - Send them an email notification\n\n' +
                'You can optionally provide a reason:';

    const reason = prompt(msg);
    if (reason === null) return false;
    form.querySelector('input[name="reason"]').value = reason;
    return true;
}
</script>
