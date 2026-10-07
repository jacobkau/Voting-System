<?php
include("conn.php");

// Safety: only run when explicitly enabled via query string
if (!isset($_GET['go']) || $_GET['go'] !== 'yes') {
    die("Add ?go=yes to the URL to run.");
}

header('Content-Type: text/plain; charset=utf-8');

$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "=== {$table} ===\n";
    $cols = $conn->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        printf(
            "  %-25s %-25s %-8s %-6s %-10s %s\n",
            $c['Field'],
            $c['Type'],
            $c['Null'],
            $c['Key'],
            $c['Default'] ?? 'NULL',
            $c['Extra']
        );
    }
    echo "\n";
}
