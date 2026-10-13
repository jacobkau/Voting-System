<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/../cloudinary.php';

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$adminId = (int)$_SESSION['admin_id'];
$message = "";
$messageType = "";

// Read message from PRG redirect
if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['msg_type'] ?? 'success';
}

// ────────────────────────────────────────────────────────────
// Email helper
// ────────────────────────────────────────────────────────────
function sendCandidateDecisionEmail(string $email, string $userName, string $electionTitle, string $position, string $decision, string $reason = ''): array {
    $serviceId  = getenv('EMAILJS_SERVICE_ID2');
    $templateId = getenv('EMAILJS_TEMPLATE_ID2');
    $publicKey  = getenv('EMAILJS_PUBLIC_KEY2');
    $privateKey = getenv('EMAILJS_PRIVATE_KEY2');

    if (!$serviceId || !$templateId || !$publicKey) {
        return ['sent' => false, 'error' => 'EmailJS not configured.'];
    }

    if ($decision === 'approved') {
        $subjectLine = 'Your candidacy has been approved';
        $messageBody = 'Good news — your application to run as a candidate for the position of "' . $position . '" in "' . $electionTitle . '" has been approved. Your profile will now be visible to voters.';
    } elseif ($decision === 'unapproved') {
        $subjectLine = 'Your candidacy approval has been revoked';
        $messageBody = 'Your previously approved candidacy for "' . $position . '" in "' . $electionTitle . '" has been revoked by an administrator. You have been returned to the pending queue.';
        if ($reason !== '') {
            $messageBody .= "\n\nReason: " . $reason;
        }
    } else {
        $subjectLine = 'Your candidacy was not approved';
        $messageBody = 'We regret to inform you that your application to run as a candidate for "' . $position . '" in "' . $electionTitle . '" was not approved.';
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
            'position'       => $position,
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
    error_log("EmailJS candidate decision error: " . $err);
    return ['sent' => false, 'error' => $err];
}

// ────────────────────────────────────────────────────────────
// Live avatar lookup
// ────────────────────────────────────────────────────────────
function getCandidateAvatar($candidate, $conn) {
    if (!empty($candidate['user_id'])) {
        try {
            $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $stmt->execute([$candidate['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow && !empty($userRow['profile_photo']) && preg_match('#^https?://#i', $userRow['profile_photo'])) {
                return $userRow['profile_photo'];
            }
        } catch (PDOException $e) {
            error_log("Candidate live-avatar lookup error: " . $e->getMessage());
        }
    }

    if (!empty($candidate['profile_photo']) && preg_match('#^https?://#i', $candidate['profile_photo'])) {
        return $candidate['profile_photo'];
    }

    return defaultAvatarUrl();
}

function fetchUsersForElection($conn, $electionId) {
    if (empty($electionId)) return [];
    $users = [];
    try {
        $stmt = $conn->prepare("SELECT u.id, u.username FROM users u JOIN user_elections er ON u.id = er.user_id WHERE er.election_id = ?");
        $stmt->execute([$electionId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Fetch users for election error: " . $e->getMessage());
    }
    return $users;
}

function fetchPositionsForElection($conn, $electionId) {
    if (empty($electionId)) return [];
    $positions = [];
    try {
        $stmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Fetch positions for election error: " . $e->getMessage());
    }
    return $positions;
}

// ────────────────────────────────────────────────────────────
// Handle approve / reject / unapprove
// ────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['candidate_id'], $_POST['decision'])) {
    $candidateId = (int)$_POST['candidate_id'];
    $decision    = $_POST['decision'];
    $reason      = trim($_POST['reason'] ?? '');

    $returnStatus     = $_POST['return_status'] ?? 'pending';
    $returnElectionId = isset($_POST['return_election_id']) ? (int)$_POST['return_election_id'] : 0;

    if (!in_array($decision, ['approve', 'reject', 'unapprove'], true)) {
        $message = "Invalid decision.";
        $messageType = "error";
    } else {
        try {
            $conn->beginTransaction();

            $cStmt = $conn->prepare("
                SELECT c.id, c.user_id, c.election_id, c.postname, c.votes,
                       u.username, u.name AS full_name, u.email,
                       e.title AS election_title
                FROM contesters c
                JOIN users u ON c.user_id = u.id
                JOIN elections e ON c.election_id = e.id
                WHERE c.id = ?
            ");
            $cStmt->execute([$candidateId]);
            $candidate = $cStmt->fetch(PDO::FETCH_ASSOC);

            if (!$candidate) {
                throw new Exception("Candidate not found.");
            }

            if ($decision === 'approve') {
                $conn->prepare("
                    UPDATE contesters
                    SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?, rejection_reason = NULL
                    WHERE id = ?
                ")->execute([$adminId, $candidateId]);

                $logDesc = "Approved candidacy: {$candidate['username']} for '{$candidate['postname']}' in '{$candidate['election_title']}'";
                $message = "Approved candidacy for {$candidate['username']}.";

            } elseif ($decision === 'reject') {
                $conn->prepare("
                    UPDATE contesters
                    SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ?
                    WHERE id = ?
                ")->execute([$adminId, $reason ?: null, $candidateId]);

                $logDesc = "Rejected candidacy: {$candidate['username']} for '{$candidate['postname']}' in '{$candidate['election_title']}'";
                $message = "Rejected candidacy for {$candidate['username']}.";

            } else { // unapprove
                $voteCount = (int)$candidate['votes'];
                if ($voteCount > 0) {
                    throw new Exception("Cannot unapprove — this candidate already has {$voteCount} vote(s). Remove the votes first if you truly need to.");
                }

                $conn->prepare("
                    UPDATE contesters
                    SET status = 'pending', reviewed_at = NULL, reviewed_by = NULL, rejection_reason = NULL
                    WHERE id = ?
                ")->execute([$candidateId]);

                $logDesc = "Unapproved candidacy: {$candidate['username']} for '{$candidate['postname']}' in '{$candidate['election_title']}'";
                $message = "Unapproved candidacy for {$candidate['username']}. The application is back in the pending queue.";
            }

            $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description)
                VALUES (?, 'Candidate Review', ?)
            ")->execute([$_SESSION['username'] ?? 'admin', $logDesc]);

            $conn->commit();
            $messageType = "success";

            $recipientName = !empty($candidate['full_name']) ? $candidate['full_name'] : $candidate['username'];

            if ($decision === 'approve') {
                $emailResult = sendCandidateDecisionEmail(
                    $candidate['email'], $recipientName,
                    $candidate['election_title'], $candidate['postname'],
                    'approved', ''
                );
            } elseif ($decision === 'reject') {
                $emailResult = sendCandidateDecisionEmail(
                    $candidate['email'], $recipientName,
                    $candidate['election_title'], $candidate['postname'],
                    'rejected', $reason
                );
            } else {
                $emailResult = sendCandidateDecisionEmail(
                    $candidate['email'], $recipientName,
                    $candidate['election_title'], $candidate['postname'],
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
            error_log("Candidate decision error: " . $e->getMessage());
            $message = $e->getMessage() ?: "Could not process the request. Please try again.";
            $messageType = "error";
        }
    }

    $redirect = 'main.php?page=candidates'
              . '&status=' . urlencode($returnStatus)
              . ($returnElectionId ? '&election_id=' . $returnElectionId : '')
              . '&msg=' . urlencode($message)
              . '&msg_type=' . urlencode($messageType);
    header('Location: ' . $redirect);
    exit;
}

// ────────────────────────────────────────────────────────────
// Fetch Elections — with derived status
// ────────────────────────────────────────────────────────────
try {
    $electionsStmt = $conn->prepare("
        SELECT id, title, status, start_date, end_date, manual_status, manual_until
        FROM elections
        ORDER BY id DESC
    ");
    $electionsStmt->execute();
    $allElections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach derived status
    foreach ($allElections as &$e) {
        $e['live_status'] = effectiveElectionStatus($e);
    }
    unset($e);

    // Sort: active first, then upcoming, then completed
    usort($allElections, function ($a, $b) {
        $order = ['active' => 1, 'upcoming' => 2, 'completed' => 3];
        $oa = $order[$a['live_status']] ?? 4;
        $ob = $order[$b['live_status']] ?? 4;
        if ($oa !== $ob) return $oa - $ob;
        return $b['id'] - $a['id'];
    });

    $elections = $allElections;

} catch (Exception $e) {
    error_log("Election fetch error: " . $e->getMessage());
    echo "Error fetching election data. Please try again.";
    exit();
}

$selectedElectionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_POST['election_id']) ? intval($_POST['election_id']) : null);

$usersForSelectedElection = [];
$positionsForSelectedElection = [];
if ($selectedElectionId) {
    $usersForSelectedElection = fetchUsersForElection($conn, $selectedElectionId);
    $positionsForSelectedElection = fetchPositionsForElection($conn, $selectedElectionId);
}

// ────────────────────────────────────────────────────────────
// Handle Add Candidate
// ────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "add_candidate") {
    $userId = intval($_POST['name']);
    $electionId = intval($_POST['election_id']);
    $position = trim($_POST['position']);
    $bio = trim($_POST['bio']);
    $profilePhotoUrl = "";

    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $profilePhotoUrl = uploadToCloudinary($_FILES['profile_photo']['tmp_name'], 'candidates');
        if (!$profilePhotoUrl) {
            echo "<p style='color:red;'>Error uploading profile photo to Cloudinary.</p>";
        }
    }

    try {
        $checkRegStmt = $conn->prepare("SELECT 1 FROM user_elections WHERE user_id = ? AND election_id = ?");
        $checkRegStmt->execute([$userId, $electionId]);
        if ($checkRegStmt->rowCount() === 0) {
            throw new Exception("User is not registered for the selected election.");
        }

        $checkPostStmt = $conn->prepare("SELECT 1 FROM election_posts WHERE postname = ? AND election_id = ?");
        $checkPostStmt->execute([$position, $electionId]);
        if ($checkPostStmt->rowCount() === 0) {
            throw new Exception("Position not found for the selected election.");
        }

        $checkCandidateStmt = $conn->prepare("SELECT 1 FROM contesters WHERE user_id = ? AND election_id = ? AND postname = ?");
        $checkCandidateStmt->execute([$userId, $electionId, $position]);
        if ($checkCandidateStmt->rowCount() > 0) {
            throw new Exception("User is already a candidate for this position in this election.");
        }

        $stmt = $conn->prepare("INSERT INTO contesters (user_id, election_id, postname, bio, profile_photo, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$userId, $electionId, $position, $bio, $profilePhotoUrl])) {
            echo "<p style='color:green;'>Candidate added successfully (pending approval).</p>";
            echo "<script>setTimeout(function(){ window.location.href = window.location.pathname + '?election_id=' + $electionId; }, 1000);</script>";
        } else {
            throw new Exception("Failed to insert candidate.");
        }
    } catch (Exception $e) {
        error_log("Candidate insert error: " . $e->getMessage());
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// ────────────────────────────────────────────────────────────
// Handle Delete
// ────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === "delete_candidate") {
    header('Content-Type: application/json');

    $candidateId = intval($_POST['delete_id']);
    if ($candidateId <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit();
    }

    try {
        $voteStmt = $conn->prepare("SELECT votes FROM contesters WHERE id = ?");
        $voteStmt->execute([$candidateId]);
        $row = $voteStmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int)$row['votes'] > 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Cannot delete — this candidate already has " . (int)$row['votes'] . " vote(s)."
            ]);
            exit();
        }

        $stmt_select = $conn->prepare("SELECT profile_photo FROM contesters WHERE id = ?");
        $stmt_select->execute([$candidateId]);
        $candidate = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if ($candidate && !empty($candidate['profile_photo'])) {
            $publicId = extractPublicIdFromUrl($candidate['profile_photo']);
            if ($publicId) {
                try {
                    $upload = new \Cloudinary\Api\Upload\UploadApi();
                    $upload->destroy($publicId);
                } catch (Exception $ce) {
                    error_log("Cloudinary delete error: " . $ce->getMessage());
                }
            }
        }

        $stmt_delete = $conn->prepare("DELETE FROM contesters WHERE id = ?");
        $stmt_delete->execute([$candidateId]);

        echo json_encode(["status" => "success"]);

    } catch (Exception $e) {
        error_log("Candidate delete error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }
    exit();
}

// ────────────────────────────────────────────────────────────
// Fetch candidates with optional status + election filter
// ────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, ['pending', 'approved', 'rejected', 'all'], true)) {
    $filterStatus = 'pending';
}

$filterElectionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : null;

$where = [];
$params = [];

if ($filterElectionId) {
    $where[] = "c.election_id = ?";
    $params[] = $filterElectionId;
}
if ($filterStatus !== 'all') {
    $where[] = "c.status = ?";
    $params[] = $filterStatus;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $candidatesStmt = $conn->prepare("
        SELECT c.*, e.title AS election_title, u.username, u.name AS full_name, u.email
        FROM contesters c
        JOIN elections e ON c.election_id = e.id
        JOIN users u ON c.user_id = u.id
        $whereSql
        ORDER BY c.id DESC
    ");
    $candidatesStmt->execute($params);
    $candidates = $candidatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Candidate fetch error: " . $e->getMessage());
    echo "Error fetching candidate data. Please try again.";
    $candidates = [];
}

// Counts for badges
$countsStmt = $conn->query("
    SELECT status, COUNT(*) AS c
    FROM contesters
    GROUP BY status
");
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $countsStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = (int)$row['c'];
}

// Helper for tab URLs
function candidateTabUrl(string $status, ?int $electionId): string {
    $url = '?page=candidates&status=' . urlencode($status);
    if ($electionId) {
        $url .= '&election_id=' . (int)$electionId;
    }
    return $url;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Manage Candidates</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 100%; max-width: 1200px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }

        .page-title { font-size: 20px; font-weight: 700; color: #1f2937; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .page-title i { color: #2c7a7b; }

        .filter-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-tab {
            padding: 7px 14px; border-radius: 20px; font-size: 13px;
            text-decoration: none; background: #f3f4f6; color: #4b5563;
            font-weight: 500; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .filter-tab:hover { background: #e5e7eb; }
        .filter-tab.active { background: #2c7a7b; color: white; }
        .filter-tab .count {
            background: rgba(0, 0, 0, 0.08); padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700;
        }
        .filter-tab.active .count { background: rgba(255, 255, 255, 0.25); }

        .message { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; font-size: 13px; display: flex; align-items: center; gap: 10px; }
        .message.success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .message.error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #dc2626; }

        .form-container { margin-bottom: 24px; padding: 18px; background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; }
        .form-container h3 { margin: 0 0 14px 0; font-size: 16px; color: #1f2937; }
        .form-container input, .form-container select, .form-container textarea {
            width: 100%; padding: 10px 12px; margin: 5px 0;
            border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; font-size: 13px;
            box-sizing: border-box;
        }
        .form-container textarea { resize: vertical; min-height: 90px; }
        .form-container button {
            background: #2c7a7b; color: white; padding: 10px 18px;
            border: none; cursor: pointer; border-radius: 8px;
            font-weight: 600; font-family: inherit; font-size: 13px;
        }
        .form-container button:hover { background: #236162; }

        .filter-bar { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .filter-bar label { font-size: 13px; color: #4b5563; }
        .filter-bar select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; font-size: 13px; }

        .candidate-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        .candidate-table th, .candidate-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .candidate-table th { background: #f3f4f6; color: #374151; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .candidate-table tr:last-child td { border-bottom: none; }
        .candidate-table tr:hover { background: #f9fafb; }

        .profile-img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #2c7a7b; background: #f4f7f9; }

        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-pending  { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        .election-live-status {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-left: 6px;
        }
        .election-live-status.status-active    { background: #d1fae5; color: #065f46; }
        .election-live-status.status-upcoming  { background: #fef3c7; color: #92400e; }
        .election-live-status.status-completed { background: #e5e7eb; color: #374151; }

        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-buttons button, .action-buttons span {
            padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
            border: none; cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 5px;
            transition: all 0.2s;
        }
        .btn-approve { background: #10b981; color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #dc2626; color: white; }
        .btn-reject:hover { background: #b91c1c; }
        .btn-unapprove { background: #f59e0b; color: white; }
        .btn-unapprove:hover { background: #d97706; }
        .btn-unapprove:disabled { background: #cbd5e1; cursor: not-allowed; opacity: 0.7; }
        .delete-btn { background: #fee2e2; color: #991b1b; }
        .delete-btn:hover { background: #fecaca; }

        .reject-note { font-size: 11px; color: #991b1b; margin-top: 4px; font-style: italic; }
        .votes-warning { font-size: 11px; color: #b45309; margin-top: 4px; display: flex; align-items: center; gap: 5px; }

        .empty-state { padding: 60px 20px; text-align: center; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; color: #9ca3af; }
        .empty-state i { font-size: 42px; margin-bottom: 12px; display: block; color: #d1d5db; }

        @media (max-width: 768px) {
            .candidate-table th, .candidate-table td { padding: 10px 8px; font-size: 12px; }
            .profile-img { width: 36px; height: 36px; }
            .action-buttons { flex-direction: column; }
            .action-buttons button, .action-buttons span { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="page-title">
            <i class="fas fa-user-tie"></i>
            Manage Candidates
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="<?= candidateTabUrl('pending', $filterElectionId) ?>"
               class="filter-tab <?= $filterStatus === 'pending' ? 'active' : '' ?>">
                <i class="fas fa-hourglass-half"></i> Pending
                <span class="count"><?= $counts['pending'] ?></span>
            </a>
            <a href="<?= candidateTabUrl('approved', $filterElectionId) ?>"
               class="filter-tab <?= $filterStatus === 'approved' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Approved
                <span class="count"><?= $counts['approved'] ?></span>
            </a>
            <a href="<?= candidateTabUrl('rejected', $filterElectionId) ?>"
               class="filter-tab <?= $filterStatus === 'rejected' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="count"><?= $counts['rejected'] ?></span>
            </a>
            <a href="<?= candidateTabUrl('all', $filterElectionId) ?>"
               class="filter-tab <?= $filterStatus === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
        </div>

        <div class="form-container">
            <h3>Add Candidate</h3>
            <form id="add-candidate-form" enctype="multipart/form-data" method="POST">
                <select name="election_id" id="election-select" required onchange="this.form.submit();">
                    <option value="">Select Election</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>" <?php if ($selectedElectionId == $election['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($election['title']); ?> (<?php echo ucfirst($election['live_status']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="name" id="candidate-select" required>
                    <option value="">Select Candidate</option>
                    <?php foreach ($usersForSelectedElection as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="position" id="position-select" required>
                    <option value="">Select Position</option>
                    <?php foreach ($positionsForSelectedElection as $position): ?>
                        <option value="<?php echo htmlspecialchars($position['postname']); ?>">
                            <?php echo htmlspecialchars($position['postname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <textarea name="bio" placeholder="Candidate Bio" rows="4" required></textarea>
                <input type="file" name="profile_photo" accept="image/*" required>
                <div style="font-size:12px; color:#666; margin-top:-3px;">
                    Image will be uploaded to Cloudinary (max 500x500, face-centered crop). Added candidates start as <strong>pending</strong> and need approval.
                </div>
                <br>
                <button type="submit" name="action" value="add_candidate">Add Candidate</button>
            </form>
        </div>

        <div class="filter-bar">
            <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="page" value="candidates">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <label>Filter by election:</label>
                <select name="election_id" onchange="this.form.submit()">
                    <option value="">All elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filterElectionId == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['title']) ?> (<?= ucfirst($e['live_status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($candidates)): ?>
            <div class="empty-state">
                <i class="fas fa-user-tie"></i>
                <p>No <?= htmlspecialchars($filterStatus === 'all' ? '' : $filterStatus) ?> candidates found.</p>
            </div>
        <?php else: ?>
            <table class="candidate-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Election</th>
                        <th>Position</th>
                        <th>Votes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="candidate-list">
                    <?php foreach ($candidates as $candidate): ?>
                        <?php
                            $imgSrc = getCandidateAvatar($candidate, $conn);
                            $candidateStatus = $candidate['status'] ?? 'approved';
                            $hasVotes = (int)$candidate['votes'] > 0;

                            // Look up derived status of this candidate's election
                            $candidateElectionStatus = 'upcoming';
                            foreach ($elections as $el) {
                                if ($el['id'] == $candidate['election_id']) {
                                    $candidateElectionStatus = $el['live_status'];
                                    break;
                                }
                            }
                        ?>
                        <tr id="candidate-<?php echo $candidate['id']; ?>">
                            <td><?php echo $candidate['id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Profile Photo" class="profile-img">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($candidate['full_name'] ?: $candidate['username']); ?></strong>
                                <div style="font-size:11px; color:#6b7280;">@<?php echo htmlspecialchars($candidate['username']); ?></div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($candidate['election_title']); ?>
                                <span class="election-live-status status-<?php echo htmlspecialchars($candidateElectionStatus); ?>">
                                    <?php echo ucfirst(htmlspecialchars($candidateElectionStatus)); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($candidate['postname']); ?></td>
                            <td><strong><?php echo (int)$candidate['votes']; ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($candidateStatus); ?>">
                                    <?php
                                        $icon = $candidateStatus === 'approved' ? 'fa-check' :
                                                ($candidateStatus === 'rejected' ? 'fa-times' : 'fa-hourglass-half');
                                    ?>
                                    <i class="fas <?= $icon ?>"></i>
                                    <?php echo ucfirst(htmlspecialchars($candidateStatus)); ?>
                                </span>
                                <?php if ($candidateStatus === 'rejected' && !empty($candidate['rejection_reason'])): ?>
                                    <div class="reject-note">"<?= htmlspecialchars($candidate['rejection_reason']) ?>"</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($candidateStatus === 'pending'): ?>
                                    <div class="action-buttons">
                                        <form method="post" style="display:inline;"
                                              onsubmit="return confirm('Approve candidacy for <?= htmlspecialchars(addslashes($candidate['username'])) ?>? An email will be sent to them.');">
                                            <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <input type="hidden" name="return_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                            <input type="hidden" name="return_election_id" value="<?= (int)$filterElectionId ?>">
                                            <button type="submit" class="btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;"
                                              onsubmit="return handleCandidateReject(this);">
                                            <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <input type="hidden" name="reason" value="">
                                            <input type="hidden" name="return_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                            <input type="hidden" name="return_election_id" value="<?= (int)$filterElectionId ?>">
                                            <button type="submit" class="btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>

                                <?php elseif ($candidateStatus === 'approved'): ?>
                                    <div class="action-buttons">
                                        <?php if ($hasVotes): ?>
                                            <button type="button" class="btn-unapprove" disabled
                                                    title="Cannot unapprove — this candidate has already received votes.">
                                                <i class="fas fa-lock"></i> Unapprove
                                            </button>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;"
                                                  onsubmit="return confirmCandidateUnapprove(this, '<?= htmlspecialchars(addslashes($candidate['username'])) ?>');">
                                                <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                                <input type="hidden" name="decision" value="unapprove">
                                                <input type="hidden" name="reason" value="">
                                                <input type="hidden" name="return_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                                <input type="hidden" name="return_election_id" value="<?= (int)$filterElectionId ?>">
                                                <button type="submit" class="btn-unapprove">
                                                    <i class="fas fa-undo"></i> Unapprove
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" style="display:inline;"
                                              onsubmit="return confirm('Permanently delete this candidate? This cannot be undone.');">
                                            <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                            <input type="hidden" name="decision" value="delete">
                                            <button type="submit" class="delete-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                    <?php if ($hasVotes): ?>
                                        <div class="votes-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?= (int)$candidate['votes'] ?> vote(s) received — cannot unapprove or delete.
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:12px;">
                                        <?= !empty($candidate['reviewed_at']) ? 'Reviewed ' . date('M d, Y', strtotime($candidate['reviewed_at'])) : '—' ?>
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
    function handleCandidateReject(form) {
        const reason = prompt("Reason for rejection (optional, will be shown to the user and included in the email):");
        if (reason === null) return false;
        form.querySelector('input[name="reason"]').value = reason;
        return true;
    }

    function confirmCandidateUnapprove(form, username) {
        const msg = 'Unapprove candidacy for ' + username + '?\n\n' +
                    'This will:\n' +
                    '  - Return them to the pending queue\n' +
                    '  - Send them an email notification\n\n' +
                    'You can optionally provide a reason:';
        const reason = prompt(msg);
        if (reason === null) return false;
        form.querySelector('input[name="reason"]').value = reason;
        return true;
    }

    $(document).ready(function() {
        $(document).on("click", ".ajax-delete-btn", function() {
            if (!confirm("Are you sure you want to delete this candidate?")) return;
            let candidateId = $(this).data("id");
            $.post("manage_candidates.php", { action: "delete_candidate", delete_id: candidateId }, function(response) {
                if (response.status === "success") {
                    $("#candidate-" + candidateId).fadeOut(500, function() {
                        $(this).remove();
                        if ($("#candidate-list tr").length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert("Error: " + (response.message || "Unknown error"));
                }
            }, "json").fail(function(xhr) {
                alert("Request failed: " + xhr.responseText);
            });
        });
    });
    </script>
</body>
</html>
