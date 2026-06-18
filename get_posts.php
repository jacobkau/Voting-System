<?php
session_start();
include("conn.php");

header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['election_id']) || empty($_GET['election_id'])) {
    echo '<option value="">Invalid Election ID</option>';
    exit();
}

$electionId = intval($_GET['election_id']);

try {
    // First, try to fetch posts from election_posts table
    $stmt = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
    $stmt->execute([$electionId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($posts) > 0) {
        echo '<option value="">-- Select Position --</option>';
        foreach ($posts as $post) {
            echo '<option value="' . htmlspecialchars($post['postname']) . '">' . htmlspecialchars($post['postname']) . '</option>';
        }
    } else {
        // If no posts in election_posts, check if there are any contesters
        $stmt2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
        $stmt2->execute([$electionId]);
        $contesterPosts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($contesterPosts) > 0) {
            echo '<option value="">-- Select Position --</option>';
            foreach ($contesterPosts as $post) {
                echo '<option value="' . htmlspecialchars($post['postname']) . '">' . htmlspecialchars($post['postname']) . '</option>';
            }
        } else {
            echo '<option value="">No positions available for this election</option>';
        }
    }
} catch (PDOException $e) {
    error_log("Error in get_posts.php: " . $e->getMessage());
    echo '<option value="">Error loading positions</option>';
}
?>
