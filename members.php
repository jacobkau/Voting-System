<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

// Cloudinary helpers (defaultAvatarUrl)
require_once __DIR__ . '/cloudinary.php';

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch users — Cloudinary profile_photo only
$sql = "SELECT id, username, name as full_name, email, profile_photo, date as created_at FROM users ORDER BY id";
$stmt = $conn->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Resolve a displayable avatar URL for a user.
 * Prefers Cloudinary URL in users.profile_photo, otherwise system default.
 */
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
    }

    .election-list li {
        margin: 5px 0;
        color: #374151;
    }

    body.dark-theme .election-list li { color: #cbd5e1; }

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
                            try {
                                $electionsStmt = $conn->prepare("SELECT e.title FROM elections e JOIN user_elections ue ON e.id = ue.election_id WHERE ue.user_id = ?");
                                $electionsStmt->execute([$user['id']]);
                                $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($elections) > 0) {
                                    echo "<ul class='election-list'>";
                                    foreach ($elections as $election) {
                                        echo "<li>" . htmlspecialchars($election['title']) . "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "<span class='no-elections'>No elections registered</span>";
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching user elections: " . $e->getMessage());
                                echo "<span style='color: #dc2626;'>Error loading elections</span>";
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
