<?php

include("conn.php");


header('Content-Type: text/plain; charset=utf-8');

function tableExists(PDO $conn, string $table): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnInfo(PDO $conn, string $table, string $column): ?array {
    $stmt = $conn->prepare("
        SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function foreignKeyExists(PDO $conn, string $table, string $constraintName): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $stmt->execute([$table, $constraintName]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $conn, string $table, string $indexName): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $indexName]);
    return (int)$stmt->fetchColumn() > 0;
}

function runStep(PDO $conn, string $label, callable $fn): void {
    echo "→ {$label}\n";
    try {
        $fn();
        echo "  OK\n\n";
    } catch (PDOException $e) {
        echo "  FAILED: " . $e->getMessage() . "\n\n";
    }
}

// ============================================================
// START
// ============================================================
echo "==========================================\n";
echo "Witty Voting System — Registration Migration\n";
echo "==========================================\n\n";

// ------------------------------------------------------------
// 1. Create election_registration_requests
// ------------------------------------------------------------
if (!tableExists($conn, 'election_registration_requests')) {
    runStep($conn, "Create election_registration_requests table", function() use ($conn) {
        $conn->exec("
            CREATE TABLE election_registration_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                election_id INT NOT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TIMESTAMP NULL DEFAULT NULL,
                reviewed_by INT NULL DEFAULT NULL,
                rejection_reason VARCHAR(255) DEFAULT NULL,
                UNIQUE KEY uniq_request (user_id, election_id),
                INDEX idx_status (status),
                INDEX idx_election (election_id),
                CONSTRAINT fk_req_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_req_election
                    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
                CONSTRAINT fk_req_reviewer
                    FOREIGN KEY (reviewed_by) REFERENCES admin(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    });
} else {
    echo "→ election_registration_requests already exists — skipping\n\n";
}

// ------------------------------------------------------------
// 2. Widen event_log.event_description to VARCHAR(500)
// ------------------------------------------------------------
$descInfo = columnInfo($conn, 'event_log', 'event_description');
if ($descInfo && stripos($descInfo['COLUMN_TYPE'], 'varchar(500)') === false) {
    runStep($conn, "Widen event_log.event_description to VARCHAR(500)", function() use ($conn) {
        $conn->exec("ALTER TABLE event_log MODIFY event_description VARCHAR(500)");
    });
} else {
    echo "→ event_log.event_description already widened — skipping\n\n";
}

// ------------------------------------------------------------
// 3. Add index on votes.election_id
// ------------------------------------------------------------
if (!indexExists($conn, 'votes', 'idx_votes_election')) {
    runStep($conn, "Add index idx_votes_election on votes.election_id", function() use ($conn) {
        $conn->exec("ALTER TABLE votes ADD INDEX idx_votes_election (election_id)");
    });
} else {
    echo "→ idx_votes_election already exists — skipping\n\n";
}

// ------------------------------------------------------------
// 4. Add FK on votes.user_id → users.id
// ------------------------------------------------------------
if (!foreignKeyExists($conn, 'votes', 'fk_votes_user')) {
    runStep($conn, "Add FK fk_votes_user on votes.user_id", function() use ($conn) {
        // Ensure any orphan user_ids are nulled first (defensive)
        $conn->exec("
            UPDATE votes v
            LEFT JOIN users u ON v.user_id = u.id
            SET v.user_id = NULL
            WHERE v.user_id IS NOT NULL AND u.id IS NULL
        ");
        $conn->exec("
            ALTER TABLE votes
            ADD CONSTRAINT fk_votes_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ");
    });
} else {
    echo "→ fk_votes_user already exists — skipping\n\n";
}

// ------------------------------------------------------------
// 5. Add FK on votes.election_id → elections.id
// ------------------------------------------------------------
if (!foreignKeyExists($conn, 'votes', 'fk_votes_election')) {
    runStep($conn, "Add FK fk_votes_election on votes.election_id", function() use ($conn) {
        // Clean any orphan election_ids first
        $conn->exec("
            DELETE v FROM votes v
            LEFT JOIN elections e ON v.election_id = e.id
            WHERE e.id IS NULL
        ");
        $conn->exec("
            ALTER TABLE votes
            ADD CONSTRAINT fk_votes_election
            FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
        ");
    });
} else {
    echo "→ fk_votes_election already exists — skipping\n\n";
}

// ============================================================
// FINAL REPORT
// ============================================================
echo "==========================================\n";
echo "Snapshot after migration\n";
echo "==========================================\n\n";

echo "=== election_registration_requests ===\n";
foreach ($conn->query("DESCRIBE election_registration_requests")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    printf(
        "  %-25s %-30s %-5s %-5s %s\n",
        $c['Field'], $c['Type'], $c['Null'], $c['Key'] ?: '-', $c['Default'] ?? 'NULL'
    );
}
echo "\n";

echo "=== votes ===\n";
foreach ($conn->query("DESCRIBE votes")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    printf(
        "  %-25s %-30s %-5s %-5s %s\n",
        $c['Field'], $c['Type'], $c['Null'], $c['Key'] ?: '-', $c['Default'] ?? 'NULL'
    );
}
echo "\n";

echo "Foreign keys on votes:\n";
$fks = $conn->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'votes'
      AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($fks as $fk) {
    printf(
        "  %s : %s → %s.%s\n",
        $fk['CONSTRAINT_NAME'],
        $fk['COLUMN_NAME'],
        $fk['REFERENCED_TABLE_NAME'],
        $fk['REFERENCED_COLUMN_NAME']
    );
}
echo "\n";

echo "Done. DELETE this file now.\n";
