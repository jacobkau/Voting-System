<?php
// conn.php - Safe, hidden credentials with proper PHP MySQL SSL attributes

// 1. Fetch the secret link from Render's environment settings
$uri = getenv('AIVEN_DATABASE_URL');

if (!$uri) {
    die("Database Connection Error: Secure configuration string is missing.");
}

// 2. Safely parse the database URL details
$fields = parse_url($uri);

if (!$fields || !isset($fields["host"])) {
    die("Database Connection Error: Secure configuration string is corrupted.");
}

// 3. Cleanly build the basic MySQL DSN (No SSL text inside the string)
$dsn = "mysql:host=" . $fields["host"];
$dsn .= ";port=" . ($fields["port"] ?? '27643');
$dsn .= ";dbname=defaultdb;charset=utf8mb4";

// 4. Pass the SSL certificate correctly using PHP PDO Array options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/ca.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true    // Forces certificate verification
];

try {
    $user = $fields["user"] ?? 'avnadmin';
    $pass = $fields["pass"] ?? '';
    
    // 5. Connect securely using the options array
    $db = new PDO($dsn, $user, $pass, $options);
    $conn = $db;

} catch (Exception $e) {
    // If it still fails, let's see the error temporarily so we can fix it!
    die("Database Connection Error: " . $e->getMessage());
}
?>
