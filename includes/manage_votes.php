<?php
include("conn.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Delete Vote
if (isset($_GET['delete_vote']) && is_numeric($_GET['delete_vote'])) {
    $vote_id = intval($_GET['delete_vote']);
    try {
        $stmt = $conn->prepare("DELETE FROM votes WHERE id = ?");
        $stmt->execute([$vote_id]);
        echo "<p style='color:green;'>Vote deleted successfully.</p>";
    } catch (Exception $e) {
        error_log("Error deleting vote: " . $e->getMessage());
        echo "<p style='color:red;'>Error deleting vote: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Fetch Elections
try {
    $stmt = $conn->prepare("SELECT id, title FROM elections");
    $stmt->execute();
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
    echo "<p style='color:red;'>Error fetching elections. Please check logs.</p>";
}

// Function to fetch vote counts for a specific election and post
function getVoteCounts($conn, $electionId, $postName) {
    try {
        $stmt = $conn->prepare("SELECT candidate_name, COUNT(*) AS vote_count FROM votes WHERE election_id = ? AND postname = ? GROUP BY candidate_name");
        $stmt->execute([$electionId, $postName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching vote counts: " . $e->getMessage());
        return [];
    }
}

// Function to fetch posts for a specific election
function getElectionPosts($conn, $electionId) {
    try {
        $stmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
        $stmt->execute([$electionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching election posts: " . $e->getMessage());
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Votes</title>
    <style>
        table {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            border: 1px solid #ddd;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px 15px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #e0f7fa;
        }

        td button {
            background-color: #d9534f;
            border: none;
            color: white;
            padding: 8px 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
        }

        td button:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>
    <h2>Manage Votes</h2>
    <?php if (empty($elections)): ?>
        <p style="color: red;">No elections found.</p>
    <?php else: ?>
        <?php foreach ($elections as $election): ?>
            <h3><?php echo htmlspecialchars($election['title']); ?></h3>
            <?php
            $electionId = $election['id'];
            $posts = getElectionPosts($conn, $electionId);
            if (!empty($posts)):
            ?>
             <table>
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Candidate</th>
                        <th>Vote Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $voteCounts = getVoteCounts($conn, $electionId, $post['postname']);
                        foreach ($voteCounts as $voteCount):
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($post['postname']); ?></td>
                                <td><?php echo htmlspecialchars($voteCount['candidate_name']); ?></td>
                                <td><?php echo $voteCount['vote_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No posts found for this election.</p>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

<?php 
// Close PDO connection by setting to null
$conn = null;
?>
