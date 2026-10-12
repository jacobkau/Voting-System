<?php
session_start();
include __DIR__ . '/../conn.php';
require_once __DIR__ . '/../cloudinary.php';

header('Content-Type: application/json');

if (empty($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$electionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
if ($electionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid election_id']);
    exit;
}

function resolveContesterImage($contester, $conn) {
    if (!empty($contester['user_id'])) {
        try {
            $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $stmt->execute([$contester['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow && !empty($userRow['profile_photo']) && preg_match('#^https?://#i', $userRow['profile_photo'])) {
                return $userRow['profile_photo'];
            }
        } catch (PDOException $e) { /* swallow */ }
    }

    if (!empty($contester['profile_photo']) && preg_match('#^https?://#i', $contester['profile_photo'])) {
        return $contester['profile_photo'];
    }

    return defaultAvatarUrl();
}

try {
    // Confirm election exists
    $eStmt = $conn->prepare("SELECT id, title FROM elections WHERE id = ?");
    $eStmt->execute([$electionId]);
    $election = $eStmt->fetch(PDO::FETCH_ASSOC);

    if (!$election) {
        http_response_code(404);
        echo json_encode(['error' => 'Election not found']);
        exit;
    }

    // Get positions — first from election_posts, then fall back to approved contesters
    $postStmt = $conn->prepare("
        SELECT DISTINCT postname FROM election_posts
        WHERE election_id = ? ORDER BY postname
    ");
    $postStmt->execute([$electionId]);
    $posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($posts)) {
        $postStmt2 = $conn->prepare("
            SELECT DISTINCT postname FROM contesters
            WHERE election_id = ? AND status = 'approved'
            ORDER BY postname
        ");
        $postStmt2->execute([$electionId]);
        $posts = $postStmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    $outPositions = [];
    foreach ($posts as $post) {
        $postName = $post['postname'];

        $cStmt = $conn->prepare("
            SELECT id, user_id, name, bio, votes, profile_photo
            FROM contesters
            WHERE election_id = ? AND postname = ? AND status = 'approved'
            ORDER BY votes DESC, name ASC
        ");
        $cStmt->execute([$electionId, $postName]);
        $rows = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        $candidates = [];
        foreach ($rows as $r) {
            $candidates[] = [
                'id'    => (int)$r['id'],
                'name'  => $r['name'],
                'bio'   => $r['bio'] ?? '',
                'votes' => (int)$r['votes'],
                'photo' => resolveContesterImage($r, $conn),
            ];
        }

        $outPositions[] = [
            'postname'   => $postName,
            'candidates' => $candidates,
        ];
    }

    echo json_encode([
        'ok'        => true,
        'election'  => [
            'id'    => (int)$election['id'],
            'title' => $election['title'],
        ],
        'positions' => $outPositions,
    ]);

} catch (PDOException $e) {
    error_log("Contestants API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
