<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Cloudinary helpers (uploadToCloudinary, extractPublicIdFromUrl, Configuration)
require_once __DIR__ . '/../cloudinary.php';

// Validate user ID
$userId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$userId) {
    echo json_encode(["error" => "Invalid user ID"]);
    exit;
}

// Helper: build a displayable image URL from either a Cloudinary URL or a legacy filename
function resolveImageUrl($value, $folder = 'faces', $default = 'faces/default.png') {
    if (empty($value)) {
        return $default;
    }
    // Already a full URL (Cloudinary or otherwise)
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    // Legacy: local filename inside the given folder
    return $folder . '/' . $value;
}

try {
    // Fetch user details
    $userStmt = $conn->prepare("SELECT name AS full_name, username, email, profile_photo FROM users WHERE id = ?");
    if (!$userStmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    $user = $userResult->fetch_assoc();
    $userStmt->close();

    // Fetch election registrations
    $registrationsStmt = $conn->prepare("SELECT e.title FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
    $registrationsStmt->bind_param("i", $userId);
    $registrationsStmt->execute();
    $registrationsResult = $registrationsStmt->get_result();
    $registrations = array_column($registrationsResult->fetch_all(MYSQLI_ASSOC), 'title');
    $registrationsStmt->close();

    // Fetch voted elections
    $votesStmt = $conn->prepare("SELECT e.title, v.vote_timestamp AS date FROM votes v JOIN elections e ON v.election_id = e.id WHERE v.username = (SELECT username FROM users WHERE id = ?)");
    $votesStmt->bind_param("i", $userId);
    $votesStmt->execute();
    $votesResult = $votesStmt->get_result();
    $votes = $votesResult->fetch_all(MYSQLI_ASSOC);
    $votesStmt->close();

    // Fetch contested posts (profile_photo is now a Cloudinary URL)
    $contestsStmt = $conn->prepare("SELECT e.title AS election, c.postname, c.profile_photo AS contester_photo FROM contesters c JOIN elections e ON c.election_id = e.id WHERE c.name = (SELECT username FROM users WHERE id = ?)");
    $contestsStmt->bind_param("i", $userId);
    $contestsStmt->execute();
    $contestsResult = $contestsStmt->get_result();
    $contests = $contestsResult->fetch_all(MYSQLI_ASSOC);
    $contestsStmt->close();

    // Resolve profile photo: prefer contester photo (Cloudinary), fall back to user's photo
    $profilePhotoRaw = $user['profile_photo'];
    if (!empty($contests) && !empty($contests[0]['contester_photo'])) {
        $profilePhotoRaw = $contests[0]['contester_photo'];
    }

    // Normalize contester photos in the contests array too
    foreach ($contests as &$c) {
        $c['contester_photo'] = resolveImageUrl($c['contester_photo'], 'faces', 'faces/default.png');
    }
    unset($c);

    // Build final display URL
    $profilePhotoUrl = resolveImageUrl($profilePhotoRaw, 'faces', 'faces/default.png');

    // Prepare JSON response
    $userData = [
        "username" => $user['username'],
        "fullname" => $user['full_name'],
        "email" => $user['email'],
        "registrations" => $registrations,
        "votes" => $votes,
        "contests" => $contests,
        "profile_photo" => $profilePhotoUrl,
    ];

    echo json_encode($userData);

} catch (Exception $e) {
    error_log("Error fetching user info: " . $e->getMessage());
    echo json_encode(["error" => "Error fetching user details"]);
}
?>
