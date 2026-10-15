<?php
// Buffer everything from line 1 so any stray warning is swallowed.
ob_start();

// Start the session explicitly BEFORE including conn.php, so we know the
// session is active when we check $_SESSION['admin_id'] later.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conn.php';

// Discard whatever was buffered (warnings, whitespace, BOMs) so the JSON
// output starts on a clean response.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Never 401 the notification poller — just return an empty payload so the
// bell shows 0 and the JS keeps running. A 401 here floods the console and
// (on Render) sometimes kills the connection mid-response.
if (empty($_SESSION['admin_id'])) {
    echo json_encode([
        'ok'                          => true,
        'timestamp'                   => time(),
        'pending_voter_registrations' => 0,
        'pending_candidates'          => 0,
        'total'                       => 0,
        'authenticated'               => false,
    ]);
    exit;
}

$out = [
    'ok'                          => true,
    'timestamp'                   => time(),
    'pending_voter_registrations' => 0,
    'pending_candidates'          => 0,
    'total'                       => 0,
    'authenticated'               => true,
];

try {
    // Pending voter registration requests
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM election_registration_requests
        WHERE status = 'pending'
    ");
    $out['pending_voter_registrations'] = (int)$stmt->fetchColumn();

    // Pending candidate applications
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM contesters
        WHERE status = 'pending'
    ");
    $out['pending_candidates'] = (int)$stmt->fetchColumn();

    $out['total'] = $out['pending_voter_registrations'] + $out['pending_candidates'];

} catch (PDOException $e) {
    error_log("Notification fetch error: " . $e->getMessage());

    // Return valid JSON on failure so the JS doesn't choke on HTML
    echo json_encode([
        'ok'                          => false,
        'timestamp'                   => time(),
        'pending_voter_registrations' => 0,
        'pending_candidates'          => 0,
        'total'                       => 0,
        'authenticated'               => true,
        'error'                       => 'Database error',
    ]);
    exit;
}

echo json_encode($out);
