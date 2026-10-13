<?php
require_once __DIR__ . '/../conn.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $electionsStmt = $conn->query("
        SELECT id, title, status, start_date, end_date, manual_status, manual_until
        FROM elections
        ORDER BY id DESC
    ");
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Overwrite `status` with the derived value so the frontend sees the effective status
    foreach ($elections as &$e) {
        $e['status'] = effectiveElectionStatus($e);
    }
    unset($e);

    $out = [];

    foreach ($elections as $election) {
        $eId = (int)$election['id'];

        // Registered voters count
        $rStmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) FROM user_elections WHERE election_id = ?");
        $rStmt->execute([$eId]);
        $registered = (int)$rStmt->fetchColumn();

        // Turnout — distinct users who actually cast at least one vote
        $vStmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) FROM votes WHERE election_id = ? AND user_id IS NOT NULL");
        $vStmt->execute([$eId]);
        $voted = (int)$vStmt->fetchColumn();

        // Total votes cast (individual rows — one per position per user)
        $tStmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?");
        $tStmt->execute([$eId]);
        $totalVotesCast = (int)$tStmt->fetchColumn();

        // Positions and their results
        $posts = [];
        $postStmt = $conn->prepare("
            SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname
        ");
        $postStmt->execute([$eId]);
        $postRows = $postStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($postRows)) {
            // Fall back to contesters
            $postStmt2 = $conn->prepare("
                SELECT DISTINCT postname FROM contesters
                WHERE election_id = ? AND status = 'approved'
                ORDER BY postname
            ");
            $postStmt2->execute([$eId]);
            $postRows = $postStmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($postRows as $post) {
            $postName = $post['postname'];

            $cStmt = $conn->prepare("
                SELECT
                    c.id,
                    c.name,
                    c.votes AS stored_votes,
                    (SELECT COUNT(*) FROM votes v
                     WHERE v.election_id = c.election_id
                       AND v.postname = c.postname
                       AND v.candidate_name = c.name) AS actual_votes
                FROM contesters c
                WHERE c.election_id = ? AND c.postname = ? AND c.status = 'approved'
                ORDER BY actual_votes DESC, c.name ASC
            ");
            $cStmt->execute([$eId, $postName]);
            $candidatesRaw = $cStmt->fetchAll(PDO::FETCH_ASSOC);

            $candidates = [];
            $postTotal = 0;
            foreach ($candidatesRaw as $c) {
                $votes = (int)$c['actual_votes'];
                $postTotal += $votes;
                $candidates[] = [
                    'id'     => (int)$c['id'],
                    'name'   => $c['name'],
                    'votes'  => $votes,
                    'stored' => (int)$c['stored_votes'],
                ];
            }

            // Percentages
            foreach ($candidates as &$cand) {
                $cand['percentage'] = $postTotal > 0 ? round(($cand['votes'] / $postTotal) * 100, 1) : 0.0;
            }
            unset($cand);

            $posts[] = [
                'postname'    => $postName,
                'total_votes' => $postTotal,
                'candidates'  => $candidates,
            ];
        }

        $turnoutPct = $registered > 0 ? round(($voted / $registered) * 100, 1) : 0.0;

        $out[] = [
            'id'             => $eId,
            'title'          => $election['title'],
            'status'         => $election['status'],
            'start_date'     => $election['start_date'],
            'end_date'       => $election['end_date'],
            'registered'     => $registered,
            'voted'          => $voted,
            'turnout_pct'    => $turnoutPct,
            'total_votes_cast' => $totalVotesCast,
            'positions'      => $posts,
        ];
    }

    echo json_encode([
        'ok'         => true,
        'timestamp'  => time(),
        'elections'  => $out,
    ]);

} catch (PDOException $e) {
    error_log("Admin results API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
