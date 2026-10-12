<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conn.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$out = [
    'ok'         => true,
    'timestamp'  => time(),
    'pending_voter_registrations' => 0,
    'pending_candidates'          => 0,
    'total'                       => 0,
];

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM election_registration_requests WHERE status = 'pending'");
    $out['pending_voter_registrations'] = (int)$stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM contesters WHERE status = 'pending'");
    $out['pending_candidates'] = (int)$stmt->fetchColumn();

    $out['total'] = $out['pending_voter_registrations'] + $out['pending_candidates'];

} catch (PDOException $e) {
    error_log("Notification fetch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

echo json_encode($out);
