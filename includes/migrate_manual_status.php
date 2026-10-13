<?php
// migrate_manual_status.php — ONE-TIME MIGRATION. DELETE AFTER USE.
const MIGRATION_TOKEN = 'change-me-to-something-random-4f8b1c';

if (!isset($_GET['token']) || !hash_equals(MIGRATION_TOKEN, $_GET['token'])) {
    http_response_code(403);
    die('Forbidden.');
}

header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/conn.php';

echo "=== Elections migration + timezone check ===\n\n";

try {
    // -----------------------------------------------------------------
    // 1. Verify MySQL session timezone
    // -----------------------------------------------------------------
    $tz = $conn->query("SELECT @@session.time_zone AS tz")->fetch(PDO::FETCH_ASSOC);
    echo "MySQL session timezone: " . $tz['tz'] . "\n";
    echo "MySQL NOW()           : " . $conn->query("SELECT NOW()")->fetchColumn() . "\n";
    echo "PHP   date()          : " . date('Y-m-d H:i:s') . "\n";
    echo "PHP   timezone        : " . date_default_timezone_get() . "\n\n";

    // Force EAT on this connection (matches what conn.php does)
    $conn->exec("SET time_zone = '+03:00'");
    $tzAfter = $conn->query("SELECT @@session.time_zone AS tz")->fetch(PDO::FETCH_ASSOC);
    echo "After SET time_zone: " . $tzAfter['tz'] . "\n\n";

    // -----------------------------------------------------------------
    // 2. Add manual_status column if missing
    // -----------------------------------------------------------------
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

    // -----------------------------------------------------------------
    // 3. Add manual_until column if missing
    // -----------------------------------------------------------------
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

    // -----------------------------------------------------------------
    // 4. Shift existing start_date / end_date from UTC to EAT
    // -----------------------------------------------------------------
    $alreadyShifted = false;
    try {
        $markerStmt = $conn->prepare("
            SELECT COUNT(*) FROM event_log
            WHERE event_type = 'Migration'
              AND event_description LIKE 'UTC to EAT date shift%'
        ");
        $markerStmt->execute();
        $alreadyShifted = (int)$markerStmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        $alreadyShifted = false;
    }

    if ($alreadyShifted) {
        echo "ℹ️  start_date / end_date already shifted (found migration marker).\n";
    } else {
        echo "\n⏳ Shifting start_date / end_date from UTC to EAT (+3 hours)...\n";

        $beforeRows = $conn->query("
            SELECT id, title, start_date, end_date
            FROM elections
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $shifted = $conn->exec("
            UPDATE elections
            SET start_date = DATE_ADD(start_date, INTERVAL 3 HOUR),
                end_date   = DATE_ADD(end_date,   INTERVAL 3 HOUR)
            WHERE start_date IS NOT NULL
               OR end_date   IS NOT NULL
        ");

        echo "✅ Shifted {$shifted} election row(s).\n";

        $afterRows = $conn->query("
            SELECT id, title, start_date, end_date
            FROM elections
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $afterById = [];
        foreach ($afterRows as $r) {
            $afterById[$r['id']] = $r;
        }

        echo "\n=== Before → After ===\n";
        foreach ($beforeRows as $b) {
            $a = $afterById[$b['id']] ?? null;
            printf(
                "  [%d] %s\n     start: %s → %s\n     end  : %s → %s\n",
                (int)$b['id'],
                $b['title'],
                $b['start_date'],
                $a['start_date'] ?? '—',
                $b['end_date'],
                $a['end_date'] ?? '—'
            );
        }

        try {
            $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description)
                VALUES (?, 'Migration', ?)
            ")->execute([
                'system',
                'UTC to EAT date shift — ran ' . date('Y-m-d H:i:s') . ' EAT'
            ]);
            echo "\n✅ Wrote migration marker to event_log.\n";
        } catch (PDOException $e) {
            echo "\n⚠️  Could not write migration marker: " . $e->getMessage() . "\n";
            echo "    If you re-run this migration, dates will shift again.\n";
        }
    }

    // =================================================================
    // 5. FULL DATABASE SCHEMA DUMP
    // =================================================================
    echo "\n\n";
    echo "=============================================================\n";
    echo "                    FULL DATABASE SCHEMA\n";
    echo "=============================================================\n";

    // 5a. All tables in the current database
    $tablesStmt = $conn->query("
        SELECT TABLE_NAME, TABLE_ROWS, ENGINE, TABLE_COLLATION
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nDatabase: " . $conn->query("SELECT DATABASE()")->fetchColumn() . "\n";
    echo "Table count: " . count($tables) . "\n\n";

    echo "=== Overview ===\n";
    printf("  %-30s %-12s %-10s %s\n", 'TABLE', 'ROWS', 'ENGINE', 'COLLATION');
    printf("  %-30s %-12s %-10s %s\n", str_repeat('-', 30), str_repeat('-', 12), str_repeat('-', 10), str_repeat('-', 20));
    foreach ($tables as $t) {
        printf(
            "  %-30s %-12s %-10s %s\n",
            $t['TABLE_NAME'],
            $t['TABLE_ROWS'] === null ? '—' : number_format((int)$t['TABLE_ROWS']),
            $t['ENGINE'] ?? '—',
            $t['TABLE_COLLATION'] ?? '—'
        );
    }

    // 5b. Columns for every table
    $allColumns = $conn->query("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_KEY,
            EXTRA
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Group by table
    $columnsByTable = [];
    foreach ($allColumns as $c) {
        $columnsByTable[$c['TABLE_NAME']][] = $c;
    }

    // 5c. Indexes for every table
    $allIndexes = $conn->query("
        SELECT
            TABLE_NAME,
            INDEX_NAME,
            NON_UNIQUE,
            SEQ_IN_INDEX,
            COLUMN_NAME
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
        ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
    ")->fetchAll(PDO::FETCH_ASSOC);

    $indexesByTable = [];
    foreach ($allIndexes as $ix) {
        $indexesByTable[$ix['TABLE_NAME']][$ix['INDEX_NAME']]['non_unique'] = (int)$ix['NON_UNIQUE'];
        $indexesByTable[$ix['TABLE_NAME']][$ix['INDEX_NAME']]['columns'][] = $ix['COLUMN_NAME'];
    }

    // 5d. Foreign keys
    $allFks = $conn->query("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.key_column_usage
        WHERE table_schema = DATABASE()
          AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME, CONSTRAINT_NAME
    ")->fetchAll(PDO::FETCH_ASSOC);

    $fksByTable = [];
    foreach ($allFks as $fk) {
        $fksByTable[$fk['TABLE_NAME']][] = $fk;
    }

    // 5e. Render
    foreach ($columnsByTable as $tableName => $cols) {
        echo "\n-------------------------------------------------------------\n";
        echo "TABLE: {$tableName}\n";
        echo "-------------------------------------------------------------\n";

        printf("  %-25s %-30s %-8s %-15s %-10s %s\n",
            'COLUMN', 'TYPE', 'NULL?', 'DEFAULT', 'KEY', 'EXTRA');
        printf("  %-25s %-30s %-8s %-15s %-10s %s\n",
            str_repeat('-', 25), str_repeat('-', 30), str_repeat('-', 8),
            str_repeat('-', 15), str_repeat('-', 10), str_repeat('-', 20));

        foreach ($cols as $c) {
            $default = $c['COLUMN_DEFAULT'];
            if ($default === null) {
                $default = 'NULL';
            } elseif ($default === '') {
                $default = "''";
            }

            printf("  %-25s %-30s %-8s %-15s %-10s %s\n",
                $c['COLUMN_NAME'],
                $c['COLUMN_TYPE'],
                $c['IS_NULLABLE'] === 'YES' ? 'YES' : 'NO',
                substr($default, 0, 15),
                $c['COLUMN_KEY'] ?: '',
                $c['EXTRA'] ?: ''
            );
        }

        // Indexes
        if (!empty($indexesByTable[$tableName])) {
            echo "\n  Indexes:\n";
            foreach ($indexesByTable[$tableName] as $idxName => $info) {
                $kind = $info['non_unique'] === 0 ? 'UNIQUE' : 'INDEX ';
                printf("    %-8s %-30s (%s)\n",
                    $kind,
                    $idxName,
                    implode(', ', $info['columns'])
                );
            }
        }

        // Foreign keys
        if (!empty($fksByTable[$tableName])) {
            echo "\n  Foreign keys:\n";
            foreach ($fksByTable[$tableName] as $fk) {
                printf("    %-30s %s → %s.%s\n",
                    $fk['CONSTRAINT_NAME'],
                    $fk['COLUMN_NAME'],
                    $fk['REFERENCED_TABLE_NAME'],
                    $fk['REFERENCED_COLUMN_NAME']
                );
            }
        }
    }

    echo "\n=============================================================\n";
    echo "              END OF DATABASE SCHEMA DUMP\n";
    echo "=============================================================\n";

    echo "\n✅ Migration complete. DELETE THIS FILE.\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
}
