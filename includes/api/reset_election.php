<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conn.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$electionId = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
$confirmText = trim($_POST['confirm_text'] ?? '');
$reason      = trim($_POST['reason'] ?? '');

if ($electionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid election ID']);
    exit;
}

if (strlen($reason) < 5) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason (at least 5 characters)']);
    exit;
}

try {
    // Fetch the election and verify the confirm string
    $eStmt = $conn->prepare("SELECT id, title FROM elections WHERE id = ?");
    $eStmt->execute([$electionId]);
    $election = $eStmt->fetch(PDO::FETCH_ASSOC);

    if (!$election) {
        echo json_encode(['success' => false, 'message' => 'Election not found']);
        exit;
    }

    if ($confirmText !== $election['title']) {
        echo json_encode(['success' => false, 'message' => 'Confirmation text did not match the election title']);
        exit;
    }

    $conn->beginTransaction();

    // Count votes before deleting (for the audit log)
    $cStmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?");
    $cStmt->execute([$electionId]);
    $votesRemoved = (int)$cStmt->fetchColumn();

    // Delete votes
    $conn->prepare("DELETE FROM votes WHERE election_id = ?")->execute([$electionId]);

    // Reset contester counters
    $conn->prepare("UPDATE contesters SET votes = 0 WHERE election_id = ?")->execute([$electionId]);

    // Audit log
    $conn->prepare("
        INSERT INTO event_log (username, event_type, event_description)
        VALUES (?, 'Election Reset', ?)
    ")->execute([
        $_SESSION['username'] ?? 'admin',
        "Reset election '{$election['title']}' (ID {$electionId}), removed {$votesRemoved} vote(s). Reason: {$reason}"
    ]);

    $conn->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Election reset successfully. {$votesRemoved} vote(s) removed.",
        'votes_removed' => $votesRemoved,
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Election reset error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
