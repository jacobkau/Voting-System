<?php
// fix_database.php - Run this to fix database issues
include("conn.php");

// Try to disable primary key requirement
try {
    $conn->exec("SET SESSION sql_require_primary_key = 0");
    echo "<p style='color:green'>✓ Disabled sql_require_primary_key for this session</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠ Could not disable sql_require_primary_key: " . $e->getMessage() . "</p>";
}

// SQL to create votes table
$sql = "
DROP TABLE IF EXISTS votes;

CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    election_id INT NOT NULL,
    postname VARCHAR(100) NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (username, election_id, postname)
);
";

try {
    $conn->exec($sql);
    echo "<p style='color:green'>✓ Votes table created successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}

// Check if tables exist
$tables = ['users', 'elections', 'election_posts', 'user_elections', 'contesters', 'votes'];
echo "<h3>Table Status:</h3>";
foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->rowCount() > 0) {
        echo "<p style='color:green'>✓ $table exists</p>";
    } else {
        echo "<p style='color:red'>✗ $table missing</p>";
    }
}
?>
