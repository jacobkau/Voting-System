<?php
session_start();
require_once __DIR__ . '/../conn.php';

header('Content-Type: application/json');

if (empty($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$username = $_SESSION["username"];

$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$userId = (int)$user['id'];

// ────────────────────────────────────────────────────────────
// Fetch all elections with registration + vote info, plus date columns
// ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        e.id,
        e.title,
        e.status            AS election_status,
        e.start_date,
        e.end_date,
        e.manual_status,
        e.manual_until,
        r.status            AS request_status,
        r.rejection_reason,
        ue.id               AS approved_registration_id,
        (SELECT COUNT(*) FROM votes v WHERE v.user_id = ? AND v.election_id = e.id) AS votes_cast
    FROM elections e
    LEFT JOIN election_registration_requests r
        ON r.election_id = e.id AND r.user_id = ?
    LEFT JOIN user_elections ue
        ON ue.election_id = e.id AND ue.user_id = ?
    ORDER BY e.start_date ASC
");
$stmt->execute([$userId, $userId, $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by derived status — active OR upcoming only
$out = [];
foreach ($rows as $row) {
    $derived = effectiveElectionStatus($row);

    // The elections.php page only shows active/upcoming elections
    if (!in_array($derived, ['active', 'upcoming'], true)) {
        continue;
    }

    if (!empty($row['approved_registration_id'])) {
        $regStatus = 'approved';
    } elseif (!empty($row['request_status'])) {
        $regStatus = $row['request_status'];
    } else {
        $regStatus = 'none';
    }

    $out[] = [
        'election_id'      => (int)$row['id'],
        'election_status'  => $derived,
        'reg_status'       => $regStatus,
        'rejection_reason' => $row['rejection_reason'],
        'votes_cast'       => (int)$row['votes_cast'],
    ];
}

echo json_encode([
    'ok'        => true,
    'timestamp' => time(),
    'elections' => $out,
]);
