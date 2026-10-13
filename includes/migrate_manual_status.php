<?php
// migrate_manual_status.php — ONE-TIME MIGRATION. DELETE AFTER USE.
const MIGRATION_TOKEN = 'change-me-to-something-random-4f8b1c';

if (!isset($_GET['token']) || !hash_equals(MIGRATION_TOKEN, $_GET['token'])) {
    http_response_code(403);
    die('Forbidden.');
}

header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/includes/conn.php';

echo "=== Elections manual_status migration ===\n\n";

try {
    // Add manual_status column if missing
    $hasManualStatus = $conn->query("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'elections'
          AND column_name  = 'manual_status'
    ")->fetchColumn();

    if ($hasManualStatus) {
        echo "ℹ️  manual_status already exists.\n";
    } else {
        $conn->exec("ALTER TABLE elections ADD COLUMN manual_status VARCHAR(20) DEFAULT NULL");
        echo "✅ Added column 'manual_status'.\n";
    }

    // Add manual_until column if missing
    $hasManualUntil = $conn->query("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'elections'
          AND column_name  = 'manual_until'
    ")->fetchColumn();

    if ($hasManualUntil) {
        echo "ℹ️  manual_until already exists.\n";
    } else {
        $conn->exec("ALTER TABLE elections ADD COLUMN manual_until DATETIME DEFAULT NULL");
        echo "✅ Added column 'manual_until'.\n";
    }

    // Show final structure
    echo "\n=== Current elections columns ===\n";
    $cols = $conn->query("SHOW COLUMNS FROM elections")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        printf("  %-20s %-30s %s\n", $c['Field'], $c['Type'], $c['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }

    echo "\n✅ Migration complete. DELETE THIS FILE.\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
}
