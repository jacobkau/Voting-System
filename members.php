<?php
session_start();

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('display_startup_errors', $isLocal ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// ────────────────────────────────────────────────────────────
// Fetch users
// ────────────────────────────────────────────────────────────
$sql = "SELECT id, username, name AS full_name, email, profile_photo, date AS created_at
        FROM users
        ORDER BY id";
$stmt = $conn->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ────────────────────────────────────────────────────────────
// Fetch ALL user-election registrations in one query (avoids N+1)
// Include the election date/override columns so we can derive status
// ────────────────────────────────────────────────────────────
$userElections = [];
try {
    $ueStmt = $conn->prepare("
        SELECT ue.user_id,
               e.id    AS election_id,
               e.title,
               e.status,
               e.start_date,
               e.end_date,
               e.manual_status,
               e.manual_until
        FROM user_elections ue
        JOIN elections e ON e.id = ue.election_id
        ORDER BY e.start_date DESC
    ");
    $ueStmt->execute();
    foreach ($ueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['live_status'] = effectiveElectionStatus($row);
        $userElections[$row['user_id']][] = $row;
    }
} catch (PDOException $e) {
    error_log("Error fetching user elections: " . $e->getMessage());
    $userElections = [];
}

function getUserAvatar($user) {
    if (!empty($user['profile_photo']) && preg_match('#^https?://#i', $user['profile_photo'])) {
        return $user['profile_photo'];
    }
    return defaultAvatarUrl();
}
?>

<?php include("header.php"); ?>

<style>
    .members-container {
        width: 90%;
        max-width: 1200px;
        margin: 40px auto;
        background-color: #ffffff;
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        overflow-x: auto;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .members-container {
        background-color: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .members-container h1 {
        color: #1f2937;
        margin-bottom: 20px;
        text-align: center;
        font-size: 28px;
        transition: color 0.3s ease;
    }

    body.dark-theme .members-container h1 { color: #f3f4f6; }

    .members-container h1 i {
        color: #2c7a7b;
        margin-right: 10px;
    }

    .members-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .members-table th,
    .members-table td {
        border: 1px solid #e5e7eb;
        padding: 12px;
        text-align: left;
        font-size: 14px;
        transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
    }

    body.dark-theme .members-table th,
    body.dark-theme .members-table td {
        border-color: #3d3d4d;
        color: #e5e7eb;
    }

    .members-table th {
        background-color: #2c7a7b;
        color: #ffffff;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    body.dark-theme .members-table th {
        background-color: #0f172a;
    }

    .members-table tr:hover {
        background-color: #f9fafb;
    }

    body.dark-theme .members-table tr:hover {
        background-color: #2d2d3d;
    }

    .members-table img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #2c7a7b;
        background: #f4f7f9;
    }

    .election-list {
        margin: 0;
        padding-left: 20px;
        list-style: none;
    }

    .election-list li {
        margin: 5px 0;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    body.dark-theme .election-list li { color: #cbd5e1; }

    .mini-status {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 7px;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .mini-status.status-active    { background: #d1fae5; color: #065f46; }
    .mini-status.status-upcoming  { background: #fef3c7; color: #92400e; }
    .mini-status.status-completed { background: #e5e7eb; color: #374151; }

    body.dark-theme .mini-status.status-active    { background: #064e3b; color: #a7f3d0; }
    body.dark-theme .mini-status.status-upcoming  { background: #78350f; color: #fde68a; }
    body.dark-theme .mini-status.status-completed { background: #374151; color: #e5e7eb; }

    .no-data {
        text-align: center;
        color: #9ca3af;
        padding: 40px;
        font-style: italic;
    }

    .no-elections {
        color: #9ca3af;
        font-style: italic;
    }

    @media (max-width: 768px) {
        .members-container {
            width: 95%;
            padding: 15px;
        }
        .members-table th,
        .members-table td {
            padding: 8px;
            font-size: 13px;
        }
        .members-table img {
            width: 40px;
            height: 40px;
        }
    }
</style>

<div class="members-container">
    <h1><i class="fas fa-users"></i> Registered Voters</h1>

    <?php if (empty($users)): ?>
        <div class="no-data">
            <p>No registered users yet.</p>
        </div>
    <?php else: ?>
        <table class="members-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Profile Photo</th>
                    <th>Date Registered</th>
                    <th>Registered Elections</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $avatarUrl = getUserAvatar($user); ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                        <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td data-label="Full Name"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                        <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td data-label="Profile Photo">
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile Photo">
                        </td>
                        <td data-label="Date Registered">
                            <?php
                            if (!empty($user['created_at'])) {
                                echo date('Y-m-d H:i', strtotime($user['created_at']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td data-label="Registered Elections">
                            <?php
                            $userElectionRows = $userElections[$user['id']] ?? [];
                            if (count($userElectionRows) > 0) {
                                echo "<ul class='election-list'>";
                                foreach ($userElectionRows as $row) {
                                    $ls = $row['live_status'];
                                    $label = ucfirst($ls);
                                    echo "<li>"
                                       . "<span>" . htmlspecialchars($row['title']) . "</span>"
                                       . "<span class='mini-status status-{$ls}'>{$label}</span>"
                                       . "</li>";
                                }
                                echo "</ul>";
                            } else {
                                echo "<span class='no-elections'>No elections registered</span>";
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>
