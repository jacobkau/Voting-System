<?php

include __DIR__ . '/conn.php';

header('Content-Type: text/plain; charset=utf-8');

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

echo "==========================================\n";
echo "Candidate Approval Migration\n";
echo "==========================================\n\n";

// 1. Add status column
if (!columnExists($conn, 'contesters', 'status')) {
    try {
        $conn->exec("
            ALTER TABLE contesters
            ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL,
            ADD COLUMN reviewed_by INT NULL DEFAULT NULL,
            ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL
        ");
        echo "→ Added status, reviewed_at, reviewed_by, rejection_reason columns\n  OK\n\n";

        // Backfill existing candidates as approved so nothing disappears
        $updated = $conn->exec("UPDATE contesters SET status = 'approved' WHERE status = 'pending'");
        echo "→ Marked {$updated} existing candidates as approved\n  OK\n\n";
    } catch (PDOException $e) {
        echo "  FAILED: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "→ contesters.status already exists — skipping\n\n";
}

// 2. Add index for filtering by status
$idxCheck = $conn->query("
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contesters'
      AND INDEX_NAME = 'idx_contesters_status'
");
if ((int)$idxCheck->fetchColumn() === 0) {
    try {
        $conn->exec("ALTER TABLE contesters ADD INDEX idx_contesters_status (status)");
        echo "→ Added index on contesters.status\n  OK\n\n";
    } catch (PDOException $e) {
        echo "  FAILED: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "→ idx_contesters_status already exists — skipping\n\n";
}

// 3. Final snapshot
echo "==========================================\n";
echo "Snapshot: contesters\n";
echo "==========================================\n\n";
foreach ($conn->query("DESCRIBE contesters")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    printf("  %-25s %-40s %-5s %-5s %s\n",
        $c['Field'], $c['Type'], $c['Null'], $c['Key'] ?: '-', $c['Default'] ?? 'NULL');
}

echo "\nDone. DELETE this file now.\n";
