<?php

include("conn.php");
// ---------- HANDLE ACTIONS ----------
// Optional: ?csv=1 → download as CSV
// Optional: ?json=1 → download as JSON
$asCsv = isset($_GET['csv']);
$asJson = isset($_GET['json']);

// ---------- FETCH SCHEMA ----------
$dbName = $conn->query("SELECT DATABASE()")->fetchColumn();

$tablesStmt = $conn->prepare("
    SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COLLATION
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = ?
    ORDER BY TABLE_NAME
");
$tablesStmt->execute([$dbName]);
$tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);

$schema = [];

foreach ($tables as $table) {
    $tname = $table['TABLE_NAME'];

    // Columns
    $colsStmt = $conn->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ");
    $colsStmt->execute([$dbName, $tname]);
    $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexes
    $idxStmt = $conn->prepare("
        SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ");
    $idxStmt->execute([$dbName, $tname]);
    $idxRows = $idxStmt->fetchAll(PDO::FETCH_ASSOC);

    $indexes = [];
    foreach ($idxRows as $row) {
        $indexes[$row['INDEX_NAME']]['unique'] = !$row['NON_UNIQUE'];
        $indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
    }

    // Foreign keys
    $fkStmt = $conn->prepare("
        SELECT
            kcu.CONSTRAINT_NAME,
            kcu.COLUMN_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME,
            rc.DELETE_RULE,
            rc.UPDATE_RULE
        FROM information_schema.KEY_COLUMN_USAGE kcu
        JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
            ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
        WHERE kcu.TABLE_SCHEMA = ?
          AND kcu.TABLE_NAME = ?
          AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY kcu.CONSTRAINT_NAME
    ");
    $fkStmt->execute([$dbName, $tname]);
    $foreignKeys = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

    // Actual row count (exact, not estimated)
    $count = null;
    try {
        $countStmt = $conn->query("SELECT COUNT(*) FROM `$tname`");
        $count = (int)$countStmt->fetchColumn();
    } catch (PDOException $e) {
        $count = null; // some views/permissions may not allow
    }

    $schema[] = [
        'name' => $tname,
        'engine' => $table['ENGINE'],
        'collation' => $table['TABLE_COLLATION'],
        'rows_estimated' => (int)$table['TABLE_ROWS'],
        'rows_actual' => $count,
        'data_size' => (int)$table['DATA_LENGTH'],
        'index_size' => (int)$table['INDEX_LENGTH'],
        'columns' => $columns,
        'indexes' => $indexes,
        'foreign_keys' => $foreignKeys,
    ];
}

// ---------- JSON OUTPUT ----------
if ($asJson) {
    header('Content-Type: application/json');
    echo json_encode([
        'database' => $dbName,
        'generated_at' => date('c'),
        'table_count' => count($schema),
        'tables' => $schema,
    ], JSON_PRETTY_PRINT);
    exit;
}

// ---------- CSV OUTPUT ----------
if ($asCsv) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="db_schema_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Table', 'Column', 'Type', 'Nullable', 'Key', 'Default', 'Extra']);
    foreach ($schema as $t) {
        foreach ($t['columns'] as $c) {
            fputcsv($out, [
                $t['name'],
                $c['COLUMN_NAME'],
                $c['COLUMN_TYPE'],
                $c['IS_NULLABLE'],
                $c['COLUMN_KEY'],
                $c['COLUMN_DEFAULT'],
                $c['EXTRA'],
            ]);
        }
    }
    fclose($out);
    exit;
}

// ---------- HTML OUTPUT ----------
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function fmt_bytes($b) {
    if ($b <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($b, 1024));
    return round($b / pow(1024, $i), 2) . ' ' . $units[$i];
}

$totalData = array_sum(array_column($schema, 'data_size'));
$totalIndex = array_sum(array_column($schema, 'index_size'));
$totalRows = array_sum(array_map(function($t) { return (int)$t['rows_actual']; }, $schema));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Inspector | <?= h($dbName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f9;
            color: #1f2937;
            padding: 30px 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background-color: #2c7a7b;
            color: white;
            border-radius: 16px;
            padding: 24px 30px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 15px rgba(44, 122, 123, 0.2);
        }

        .page-header h1 {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header .db-name {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 13px;
            opacity: 0.9;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .header-actions a {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .header-actions a:hover { background: rgba(255, 255, 255, 0.25); }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .summary-card i {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #e6f4f4;
            color: #2c7a7b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .summary-card .label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        .summary-card .value {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }

        .table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            margin-bottom: 18px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .table-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .table-header {
            background: #f9fafb;
            padding: 14px 20px;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-header:hover { background: #f3f4f6; }

        .table-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
        }

        .table-title i.fa-table { color: #2c7a7b; }

        .table-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            font-size: 12px;
            color: #6b7280;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #e6f4f4;
            color: #2c7a7b;
        }

        .badge.gray { background: #f3f4f6; color: #6b7280; }
        .badge.amber { background: #fef3c7; color: #92400e; }

        .toggle-icon {
            color: #9ca3af;
            transition: transform 0.3s;
            font-size: 14px;
        }

        .table-card.open .toggle-icon { transform: rotate(180deg); }

        .table-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .table-card.open .table-body {
            max-height: 5000px;
            overflow: visible;
        }

        .table-section {
            padding: 18px 20px;
            border-top: 1px solid #f3f4f6;
        }

        .table-section:first-child { border-top: none; }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i { color: #2c7a7b; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table.data th {
            text-align: left;
            padding: 8px 10px;
            background: #f9fafb;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #e5e7eb;
        }

        table.data td {
            padding: 8px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        table.data tr:last-child td { border-bottom: none; }

        table.data tr:hover td { background: #fafbfc; }

        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: #374151;
        }

        .null-value { color: #9ca3af; font-style: italic; }
        .pk-badge { color: #b45309; font-weight: 700; font-size: 11px; }
        .fk-badge { color: #7c3aed; font-weight: 700; font-size: 11px; }
        .uniq-badge { color: #0891b2; font-weight: 700; font-size: 11px; }

        .empty-note {
            color: #9ca3af;
            font-size: 13px;
            font-style: italic;
            padding: 8px 0;
        }

        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #78350f;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .warning i { color: #f59e0b; font-size: 16px; margin-top: 1px; }
        .warning strong { font-weight: 700; }

        @media (max-width: 640px) {
            body { padding: 16px 12px; }
            .page-header { padding: 18px 20px; }
            .page-header h1 { font-size: 18px; }
            .table-section { padding: 14px; }
            table.data { font-size: 12px; }
            table.data th, table.data td { padding: 6px 8px; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-database"></i> Database Inspector</h1>
            <div class="db-name"><?= h($dbName) ?> &middot; generated <?= date('M d, Y H:i') ?></div>
        </div>
        <div class="header-actions">
            <a href="?json=1<?= isset($_GET['key']) ? '&key=' . urlencode($_GET['key']) : '' ?>">
                <i class="fas fa-file-code"></i> JSON
            </a>
            <a href="?csv=1<?= isset($_GET['key']) ? '&key=' . urlencode($_GET['key']) : '' ?>">
                <i class="fas fa-file-csv"></i> CSV
            </a>
        </div>
    </div>

    <div class="warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Delete this file when you're done.</strong>
            It exposes your database schema (table names, columns, indexes, foreign keys) — even behind auth, it's not something to keep around. Once you've inspected the DB, remove <code>db_inspect.php</code> from your repo and redeploy.
        </div>
    </div>

    <!-- Summary -->
    <div class="summary">
        <div class="summary-card">
            <i class="fas fa-table"></i>
            <div>
                <div class="label">Tables</div>
                <div class="value"><?= count($schema) ?></div>
            </div>
        </div>
        <div class="summary-card">
            <i class="fas fa-list-ol"></i>
            <div>
                <div class="label">Total Rows</div>
                <div class="value"><?= number_format($totalRows) ?></div>
            </div>
        </div>
        <div class="summary-card">
            <i class="fas fa-hdd"></i>
            <div>
                <div class="label">Data Size</div>
                <div class="value"><?= fmt_bytes($totalData) ?></div>
            </div>
        </div>
        <div class="summary-card">
            <i class="fas fa-sitemap"></i>
            <div>
                <div class="label">Index Size</div>
                <div class="value"><?= fmt_bytes($totalIndex) ?></div>
            </div>
        </div>
    </div>

    <!-- Tables -->
    <?php foreach ($schema as $t): ?>
        <div class="table-card">
            <div class="table-header" onclick="this.parentElement.classList.toggle('open')">
                <div class="table-title">
                    <i class="fas fa-table"></i>
                    <?= h($t['name']) ?>
                    <span class="badge gray"><?= count($t['columns']) ?> cols</span>
                    <?php if (!empty($t['foreign_keys'])): ?>
                        <span class="badge amber"><?= count($t['foreign_keys']) ?> FKs</span>
                    <?php endif; ?>
                </div>
                <div class="table-meta">
                    <span><i class="fas fa-list-ol"></i> <?= number_format((int)$t['rows_actual']) ?> rows</span>
                    <span><i class="fas fa-hdd"></i> <?= fmt_bytes($t['data_size']) ?></span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>

            <div class="table-body">
                <!-- Columns -->
                <div class="table-section">
                    <div class="section-title"><i class="fas fa-columns"></i> Columns</div>
                    <table class="data">
                        <thead>
                            <tr>
                                <th style="width:20%">Name</th>
                                <th style="width:30%">Type</th>
                                <th style="width:10%">Null</th>
                                <th style="width:15%">Key</th>
                                <th style="width:15%">Default</th>
                                <th style="width:10%">Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($t['columns'] as $c): ?>
                                <tr>
                                    <td><code><?= h($c['COLUMN_NAME']) ?></code></td>
                                    <td><code><?= h($c['COLUMN_TYPE']) ?></code></td>
                                    <td><?= $c['IS_NULLABLE'] === 'YES' ? 'YES' : 'NO' ?></td>
                                    <td>
                                        <?php
                                            $k = $c['COLUMN_KEY'];
                                            if ($k === 'PRI') echo '<span class="pk-badge">PRIMARY</span>';
                                            elseif ($k === 'UNI') echo '<span class="uniq-badge">UNIQUE</span>';
                                            elseif ($k === 'MUL') echo '<span class="fk-badge">INDEX</span>';
                                            else echo '<span class="null-value">—</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($c['COLUMN_DEFAULT'] === null): ?>
                                            <span class="null-value">NULL</span>
                                        <?php else: ?>
                                            <code><?= h($c['COLUMN_DEFAULT']) ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $c['EXTRA'] ? '<code>' . h($c['EXTRA']) . '</code>' : '<span class="null-value">—</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Indexes -->
                <?php if (!empty($t['indexes'])): ?>
                    <div class="table-section">
                        <div class="section-title"><i class="fas fa-bolt"></i> Indexes</div>
                        <table class="data">
                            <thead>
                                <tr>
                                    <th style="width:30%">Name</th>
                                    <th style="width:15%">Type</th>
                                    <th>Columns</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($t['indexes'] as $idxName => $idx): ?>
                                    <tr>
                                        <td><code><?= h($idxName) ?></code></td>
                                        <td>
                                            <?php if ($idxName === 'PRIMARY'): ?>
                                                <span class="pk-badge">PRIMARY</span>
                                            <?php elseif ($idx['unique']): ?>
                                                <span class="uniq-badge">UNIQUE</span>
                                            <?php else: ?>
                                                <span class="fk-badge">INDEX</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php foreach ($idx['columns'] as $col): ?>
                                                <code><?= h($col) ?></code>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Foreign keys -->
                <?php if (!empty($t['foreign_keys'])): ?>
                    <div class="table-section">
                        <div class="section-title"><i class="fas fa-link"></i> Foreign Keys</div>
                        <table class="data">
                            <thead>
                                <tr>
                                    <th style="width:25%">Column</th>
                                    <th style="width:30%">References</th>
                                    <th style="width:15%">On Delete</th>
                                    <th style="width:15%">On Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($t['foreign_keys'] as $fk): ?>
                                    <tr>
                                        <td><code><?= h($fk['COLUMN_NAME']) ?></code></td>
                                        <td>
                                            <code><?= h($fk['REFERENCED_TABLE_NAME']) ?></code>.<code><?= h($fk['REFERENCED_COLUMN_NAME']) ?></code>
                                        </td>
                                        <td><code><?= h($fk['DELETE_RULE']) ?></code></td>
                                        <td><code><?= h($fk['UPDATE_RULE']) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-section">
                        <div class="section-title"><i class="fas fa-link"></i> Foreign Keys</div>
                        <div class="empty-note">No foreign keys on this table.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<script>
    // Auto-open the first table so the page doesn't look empty
    document.addEventListener('DOMContentLoaded', function() {
        const first = document.querySelector('.table-card');
        if (first) first.classList.add('open');
    });
</script>
</body>
</html>
