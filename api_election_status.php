<?php
session_start();
include("conn.php");

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

$stmt = $conn->prepare("
    SELECT
        e.id,
        e.status            AS election_status,
        r.status            AS request_status,
        r.rejection_reason,
        ue.id               AS approved_registration_id,
        (SELECT COUNT(*) FROM votes v WHERE v.user_id = ? AND v.election_id = e.id) AS votes_cast
    FROM elections e
    LEFT JOIN election_registration_requests r
        ON r.election_id = e.id AND r.user_id = ?
    LEFT JOIN user_elections ue
        ON ue.election_id = e.id AND ue.user_id = ?
    WHERE e.status IN ('active','upcoming')
    ORDER BY e.start_date ASC
");
$stmt->execute([$userId, $userId, $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize the effective registration status the same way elections.php does
$out = [];
foreach ($rows as $row) {
    if (!empty($row['approved_registration_id'])) {
        $regStatus = 'approved';
    } elseif (!empty($row['request_status'])) {
        $regStatus = $row['request_status'];
    } else {
        $regStatus = 'none';
    }

    $out[] = [
        'election_id'     => (int)$row['id'],
        'election_status' => $row['election_status'],
        'reg_status'      => $regStatus,
        'rejection_reason'=> $row['rejection_reason'],
        'votes_cast'      => (int)$row['votes_cast'],
    ];
}

// Also compute a lightweight fingerprint so the front end knows when
// the election list itself changed (new election, closed election, etc.)
echo json_encode([
    'ok'        => true,
    'timestamp' => time(),
    'elections' => $out,
]);
