<?php
// migrate_unique_vote.php
// ONE-TIME MIGRATION: Adds UNIQUE constraint to prevent duplicate votes.
// ⚠️  DELETE THIS FILE IMMEDIATELY AFTER RUNNING IT.

// ---- Change this to any random string, then visit: migrate_unique_vote.php?token=YOUR_SECRET ----
const MIGRATION_TOKEN = 'change-me-to-something-random-9f3a2b';

if (!isset($_GET['token']) || !hash_equals(MIGRATION_TOKEN, $_GET['token'])) {
    http_response_code(403);
    die('Forbidden. Provide the correct ?token= value.');
}

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/conn.php';

echo "=== Duplicate Vote Migration ===\n\n";

try {
    // --- Step 1: Detect duplicate rows that would block the UNIQUE key ---
    $dupes = $conn->query("
        SELECT username, election_id, COUNT(*) AS cnt
        FROM votes
        GROUP BY username, election_id
        HAVING cnt > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($dupes)) {
        echo "Found " . count($dupes) . " user(s) with duplicate votes. Cleaning up...\n\n";

        foreach ($dupes as $d) {
            echo "  - {$d['username']} in election {$d['election_id']} ({$d['cnt']} rows)\n";
        }

        // Delete all but the lowest id per (username, election_id)
        $deleted = $conn->exec("
            DELETE v1 FROM votes v1
            INNER JOIN votes v2
                ON v1.username   = v2.username
               AND v1.election_id = v2.election_id
               AND v1.id > v2.id
        ");

        echo "\nDeleted {$deleted} duplicate row(s).\n\n";
    } else {
        echo "No duplicates found. Safe to proceed.\n\n";
    }

    // --- Step 2: Add the UNIQUE key if it doesn't already exist ---
    $indexExists = $conn->query("
        SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = 'votes'
          AND index_name   = 'uniq_user_election'
    ")->fetchColumn();

    if ($indexExists) {
        echo "UNIQUE KEY 'uniq_user_election' already exists. Nothing to do.\n";
    } else {
        $conn->exec("
            ALTER TABLE votes
            ADD UNIQUE KEY uniq_user_election (username, election_id)
        ");
        echo "✅ UNIQUE KEY 'uniq_user_election' added to votes(username, election_id).\n";
    }

    // --- Step 3: Verify ---
    echo "\n=== Verification ===\n";
    $verify = $conn->query("
        SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = 'votes'
          AND non_unique   = 0
        GROUP BY index_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($verify as $row) {
        echo "UNIQUE index: {$row['index_name']} on ({$row['cols']})\n";
    }

    echo "\n✅ Migration complete. DELETE THIS FILE NOW.\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "If the error mentions duplicate entries, run the dedupe query manually and retry.\n";
}
