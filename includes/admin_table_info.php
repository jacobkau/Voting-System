<?php
// includes/admin_table_info.php
// TEMPORARY DIAGNOSTIC — DELETE AFTER USE.

const ADMIN_INFO_TOKEN = 'change-me-to-random-7c4f9b';

if (!isset($_GET['token']) || !hash_equals(ADMIN_INFO_TOKEN, $_GET['token'])) {
    http_response_code(403);
    die('Forbidden.');
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/conn.php';

echo "=== `admin` table structure ===\n\n";

$cols = $conn->query("
    SELECT
        COLUMN_NAME     AS col,
        COLUMN_TYPE     AS type,
        IS_NULLABLE     AS nullable,
        COLUMN_DEFAULT  AS def,
        EXTRA           AS extra,
        COLUMN_KEY      AS colkey
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name   = 'admin'
    ORDER BY ORDINAL_POSITION
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($cols)) {
    echo "❌ Table `admin` does not exist in database `" . $conn->query('SELECT DATABASE()')->fetchColumn() . "`.\n";
    exit;
}

printf("%-20s %-30s %-8s %-12s %-10s %-8s\n", 'COLUMN', 'TYPE', 'NULL?', 'DEFAULT', 'EXTRA', 'KEY');
echo str_repeat('-', 95) . "\n";

foreach ($cols as $c) {
    printf(
        "%-20s %-30s %-8s %-12s %-10s %-8s\n",
        $c['col'],
        $c['type'],
        $c['nullable'],
        $c['def'] ?? 'NULL',
        $c['extra'] ?? '',
        $c['colkey'] ?? ''
    );
}

echo "\n=== Indexes on `admin` ===\n\n";

$idx = $conn->query("
    SELECT
        INDEX_NAME   AS name,
        NON_UNIQUE   AS non_unique,
        SEQ_IN_INDEX AS seq,
        COLUMN_NAME  AS col
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'admin'
    ORDER BY INDEX_NAME, SEQ_IN_INDEX
")->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($idx as $r) {
    $grouped[$r['name']]['non_unique'] = (int)$r['non_unique'];
    $grouped[$r['name']]['cols'][] = $r['col'];
}

foreach ($grouped as $name => $info) {
    printf(
        "%-25s %-8s (%s)\n",
        $name,
        $info['non_unique'] === 0 ? 'UNIQUE' : 'INDEX',
        implode(', ', $info['cols'])
    );
}

echo "\n=== Sample rows (first 5, passwords masked) ===\n\n";

$rows = $conn->query("
    SELECT * FROM admin ORDER BY id LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "(no rows)\n";
} else {
    foreach ($rows as $row) {
        foreach ($row as $k => $v) {
            if ($k === 'password') {
                $v = substr($v, 0, 12) . '... (masked)';
            }
            echo str_pad($k, 20) . ": " . $v . "\n";
        }
        echo str_repeat('-', 40) . "\n";
    }
}

echo "\nDone. DELETE THIS FILE.\n";
