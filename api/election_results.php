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

function resolveCandidateImage($candidate, $conn) {
    if (!empty($candidate['user_id'])) {
        try {
            $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $stmt->execute([$candidate['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow && !empty($userRow['profile_photo']) && preg_match('#^https?://#i', $userRow['profile_photo'])) {
                return $userRow['profile_photo'];
            }
        } catch (PDOException $e) { /* swallow */ }
    }

    if (!empty($candidate['profile_photo']) && preg_match('#^https?://#i', $candidate['profile_photo'])) {
        return $candidate['profile_photo'];
    }

    return defaultAvatarUrl();
}

try {
    $eStmt = $conn->prepare("SELECT id, title FROM elections WHERE id = ?");
    $eStmt->execute([$electionId]);
    $election = $eStmt->fetch(PDO::FETCH_ASSOC);

    if (!$election) {
        http_response_code(404);
        echo json_encode(['error' => 'Election not found']);
        exit;
    }

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

    $outPosts = [];
    foreach ($posts as $post) {
        $postName = $post['postname'];

        $cStmt = $conn->prepare("
            SELECT id, user_id, name, votes, profile_photo
            FROM contesters
            WHERE postname = ? AND election_id = ? AND status = 'approved'
            ORDER BY votes DESC, name ASC
        ");
        $cStmt->execute([$postName, $electionId]);
        $candidatesRaw = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        $candidates = [];
        $totalVotes = 0;
        foreach ($candidatesRaw as $c) {
            $totalVotes += (int)$c['votes'];
            $candidates[] = [
                'id'    => (int)$c['id'],
                'name'  => $c['name'],
                'votes' => (int)$c['votes'],
                'photo' => resolveCandidateImage($c, $conn),
            ];
        }

        $outPosts[] = [
            'postname'    => $postName,
            'total_votes' => $totalVotes,
            'candidates'  => $candidates,
        ];
    }

    echo json_encode([
        'ok'       => true,
        'election' => [
            'id'    => (int)$election['id'],
            'title' => $election['title'],
        ],
        'posts' => $outPosts,
    ]);

} catch (PDOException $e) {
    error_log("Results API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
