<?php
/**
 * One-shot database migration script.
 * Aligns the schema with what the current application code expects.
 *
 * RUN ONCE, then DELETE this file immediately.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Map $conn to $db if your conn.php sets up the connection as $db
if (!isset($conn) && isset($db)) {
    $conn = $db;
}

// ============================================================
// SAFETY GUARDS
// ============================================================
// Require either an active admin session OR an explicit ?go=yes with ?token=
// Set a token below. Replace 'change-me-before-running' with something random.
$MIGRATION_TOKEN = 'change-me-before-running';

$authorized = false;
if (isset($_SESSION['admin_id'])) {
    $authorized = true;
} elseif (isset($_GET['go'], $_GET['token']) && $_GET['go'] === 'yes' && hash_equals($MIGRATION_TOKEN, $_GET['token'])) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    exit("Forbidden. Log in as admin, or pass ?go=yes&token=YOUR_TOKEN");
}

header('Content-Type: text/plain; charset=utf-8');

// ============================================================
// HELPERS
// ============================================================
function columnExists(PDO $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(PDO $conn, string $table): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function runSql(PDO $conn, string $label, string $sql): void {
    echo "→ {$label}\n";
    try {
        $conn->exec($sql);
        echo "  OK\n\n";
    } catch (PDOException $e) {
        echo "  FAILED: " . $e->getMessage() . "\n\n";
    }
}

echo "==========================================\n";
echo "Voting System — Schema Migration\n";
echo "==========================================\n\n";

// ============================================================
// 1. ADMIN: rename photo -> profile_photo, widen to VARCHAR(500)
// ============================================================
if (columnExists($conn, 'admin', 'photo') && !columnExists($conn, 'admin', 'profile_photo')) {
    runSql(
        $conn,
        "Rename admin.photo -> admin.profile_photo and widen to VARCHAR(500)",
        "ALTER TABLE admin CHANGE COLUMN photo profile_photo VARCHAR(500) DEFAULT NULL"
    );
} elseif (columnExists($conn, 'admin', 'profile_photo')) {
    echo "→ admin.profile_photo already exists — skipping rename\n\n";
} else {
    echo "→ admin has neither 'photo' nor 'profile_photo' — adding it\n\n";
    runSql($conn, "Add admin.profile_photo", "ALTER TABLE admin ADD COLUMN profile_photo VARCHAR(500) DEFAULT NULL");
}

// ============================================================
// 2. VOTES: add user_id column + backfill from users.username
//    (keeps existing votes table compatible with new code)
// ============================================================
if (!columnExists($conn, 'votes', 'user_id')) {
    runSql(
        $conn,
        "Add votes.user_id column",
        "ALTER TABLE votes ADD COLUMN user_id INT NULL AFTER id"
    );

    if (columnExists($conn, 'votes', 'username')) {
        runSql(
            $conn,
            "Backfill votes.user_id from users.username",
            "UPDATE votes v
             JOIN users u ON v.username = u.username
             SET v.user_id = u.id
             WHERE v.user_id IS NULL"
        );

        runSql(
            $conn,
            "Add index on votes.user_id",
            "ALTER TABLE votes ADD INDEX idx_votes_user_id (user_id)"
        );
    }
} else {
    echo "→ votes.user_id already exists — skipping\n\n";
}

// ============================================================
// 3. CONTESTERS: add user_id index if missing
//    (already has user_id, but ensure FK-style index exists)
// ============================================================
if (tableExists($conn, 'contesters') && columnExists($conn, 'contesters', 'user_id')) {
    $hasIndex = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'contesters'
          AND COLUMN_NAME = 'user_id'
          AND INDEX_NAME <> 'PRIMARY'
    ");
    $hasIndex->execute();
    if ((int)$hasIndex->fetchColumn() === 0) {
        runSql(
            $conn,
            "Add index on contesters.user_id",
            "ALTER TABLE contesters ADD INDEX idx_contesters_user_id (user_id)"
        );
    } else {
        echo "→ contesters.user_id index already exists — skipping\n\n";
    }
}

// ============================================================
// 4. Ensure event_log has the columns the code expects
// ============================================================
if (!tableExists($conn, 'event_log')) {
    runSql(
        $conn,
        "Create event_log table",
        "CREATE TABLE event_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50),
            event_type VARCHAR(50),
            event_description VARCHAR(255),
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
} else {
    echo "→ event_log exists — skipping\n\n";
}

// ============================================================
// 5. Ensure election_posts exists (used by manage_candidates.php)
// ============================================================
if (!tableExists($conn, 'election_posts')) {
    runSql(
        $conn,
        "Create election_posts table",
        "CREATE TABLE election_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            election_id INT NOT NULL,
            postname VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_election_posts_election (election_id)
        )"
    );
} else {
    echo "→ election_posts exists — skipping\n\n";
}

// ============================================================
// 6. Ensure user_elections exists
// ============================================================
if (!tableExists($conn, 'user_elections')) {
    runSql(
        $conn,
        "Create user_elections table",
        "CREATE TABLE user_elections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            election_id INT NOT NULL,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_election (user_id, election_id),
            INDEX idx_ue_election (election_id)
        )"
    );
} else {
    echo "→ user_elections exists — skipping\n\n";
}

// ============================================================
// FINAL REPORT
// ============================================================
echo "==========================================\n";
echo "Current schema snapshot\n";
echo "==========================================\n\n";

$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "=== {$t} ===\n";
    foreach ($conn->query("DESCRIBE `{$t}`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        printf(
            "  %-25s %-25s %-5s %-5s %s\n",
            $c['Field'],
            $c['Type'],
            $c['Null'],
            $c['Key'] ?: '-',
            $c['Default'] ?? 'NULL'
        );
    }
    echo "\n";
}

echo "Done. DELETE this file now.\n";
