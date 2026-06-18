<?php
include("conn.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Tables Viewer</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; }
        .table-card { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-name { font-size: 24px; color: #667eea; margin: 0 0 10px 0; }
        .table-stats { color: #666; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .primary-key { background: #d4edda; color: #155724; }
        .nullable { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Database Tables Overview</h1>
        <p>Database: <strong>defaultdb</strong></p>";

try {
    // Get all tables
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Get table row count
        $count = $conn->query("SELECT COUNT(*) as count FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='table-card'>";
        echo "<h2 class='table-name'>📊 $table</h2>";
        echo "<div class='table-stats'>Total Records: " . $count['count'] . " | <a href='?view=$table' style='color:#667eea'>View Structure</a></div>";
        
        // Show table structure
        $columns = $conn->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>";
        echo "<tbody>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td><strong>" . $column['Field'] . "</strong></td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . ($column['Null'] == 'YES' ? '<span class="badge nullable">YES</span>' : 'NO') . "</td>";
            echo "<td>" . ($column['Key'] ? '<span class="badge primary-key">' . $column['Key'] . '</span>' : '') . "</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
        
        // Show sample data if requested
        if (isset($_GET['view']) && $_GET['view'] == $table) {
            $sampleData = $conn->query("SELECT * FROM `$table` LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($sampleData)) {
                echo "<div class='table-card'>";
                echo "<h3>Sample Data (first 5 rows)</h3>";
                echo "<table>";
                echo "<thead><tr>";
                foreach (array_keys($sampleData[0]) as $col) {
                    echo "<th>$col</th>";
                }
                echo "</tr></thead><tbody>";
                foreach ($sampleData as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars(substr($value ?? '', 0, 50)) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            }
        }
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
