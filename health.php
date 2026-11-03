<?php
require_once __DIR__ . '/conn.php';

try {
    // Simple query to test the connection
    $stmt = $conn->query("SELECT 1");
    if ($stmt) {
        echo json_encode(["status" => "ok", "database" => "connected"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
}
