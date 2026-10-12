<?php
session_start();
include("conn.php");

header('Content-Type: application/json');

if (empty($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $stmt = $conn->query("
        SELECT e.id AS election_id,
               (SELECT COUNT(*) FROM votes WHERE election_id = e.id) AS votes_count
        FROM elections e
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'election_id' => (int)$r['election_id'],
            'votes_count' => (int)$r['votes_count'],
        ];
    }

    echo json_encode([
        'ok'        => true,
        'timestamp' => time(),
        'elections' => $out,
    ]);
} catch (PDOException $e) {
    error_log("Summary API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
