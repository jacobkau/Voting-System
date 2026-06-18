<?php
session_start();
include("conn.php");

echo "<h2>Voting System Test</h2>";

// Test 1: Check if there are active elections
$elections = $conn->query("SELECT id, title, status FROM elections WHERE status = 'active'");
echo "<h3>Active Elections:</h3>";
if ($elections->rowCount() > 0) {
    while ($election = $elections->fetch()) {
        echo "✓ ID: {$election['id']} - {$election['title']} (Status: {$election['status']})<br>";
    }
} else {
    echo "<span style='color:orange'>⚠ No active elections. Please activate an election.</span><br>";
}

// Test 2: Check if there are election posts
$posts = $conn->query("SELECT ep.id, ep.postname, e.title as election_title FROM election_posts ep JOIN elections e ON ep.election_id = e.id");
echo "<h3>Election Posts:</h3>";
if ($posts->rowCount() > 0) {
    while ($post = $posts->fetch()) {
        echo "✓ {$post['election_title']} - Post: {$post['postname']}<br>";
    }
} else {
    echo "<span style='color:orange'>⚠ No posts found. Use 'Manage Posts' to add positions.</span><br>";
}

// Test 3: Check if there are candidates
$candidates = $conn->query("SELECT c.name, c.postname, e.title as election_title FROM contesters c JOIN elections e ON c.election_id = e.id");
echo "<h3>Candidates:</h3>";
if ($candidates->rowCount() > 0) {
    while ($candidate = $candidates->fetch()) {
        echo "✓ {$candidate['election_title']} - {$candidate['postname']}: {$candidate['name']}<br>";
    }
} else {
    echo "<span style='color:orange'>⚠ No candidates found. Users need to apply for positions.</span><br>";
}

// Test 4: Check votes table structure
echo "<h3>Votes Table Structure:</h3>";
$columns = $conn->query("DESCRIBE votes");
while ($col = $columns->fetch()) {
    echo "{$col['Field']} - {$col['Type']}<br>";
}

// Test 5: If user is logged in, show their voting status
if (isset($_SESSION['username'])) {
    echo "<h3>Your Voting Status:</h3>";
    $username = $_SESSION['username'];
    
    // Get user ID
    $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $userStmt->execute([$username]);
    $user = $userStmt->fetch();
    
    if ($user) {
        // Check registrations
        $regStmt = $conn->prepare("SELECT e.title FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
        $regStmt->execute([$user['id']]);
        $registrations = $regStmt->fetchAll();
        
        if (count($registrations) > 0) {
            echo "You are registered for:<br>";
            foreach ($registrations as $reg) {
                echo " - {$reg['title']}<br>";
            }
        } else {
            echo "<span style='color:orange'>⚠ You are not registered for any elections.</span><br>";
        }
        
        // Check votes cast
        $voteStmt = $conn->prepare("SELECT election_id, postname, candidate_name, voted_at FROM votes WHERE username = ?");
        $voteStmt->execute([$username]);
        $votesCast = $voteStmt->fetchAll();
        
        if (count($votesCast) > 0) {
            echo "<br>You have voted in:<br>";
            foreach ($votesCast as $vote) {
                echo " - Election ID {$vote['election_id']}: {$vote['postname']} -> {$vote['candidate_name']}<br>";
            }
        } else {
            echo "<br><span style='color:orange'>⚠ You haven't cast any votes yet.</span><br>";
        }
    }
}

// Instructions
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Make sure there is at least one <strong>active election</strong></li>";
echo "<li>Add <strong>posts/positions</strong> to the election using 'Manage Posts' button</li>";
echo "<li>Users need to <strong>apply for candidacy</strong> to become candidates</li>";
echo "<li>Users need to be <strong>registered for the election</strong> to vote</li>";
echo "<li>Then users can <strong>vote</strong> through vote.php</li>";
echo "</ol>";
?>
