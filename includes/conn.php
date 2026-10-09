<?php

// =====================================================================
// HTTPS ENFORCEMENT
// =====================================================================
// Skip enforcement for CLI (e.g., cron jobs, artisan, migrations)
if (php_sapi_name() !== 'cli') {

    // 1. Detect whether the current request is already HTTPS
    $isHttps =
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (!empty($_SERVER['HTTP_CF_VISITOR'])
            && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false);

    // 2. If not HTTPS, redirect permanently (301) to the HTTPS version
    if (!$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $redirect = 'https://' . $host . $requestUri;

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        exit;
    }

    // 3. Send HSTS header (browsers remember to always use HTTPS)
    // Only send if we're on HTTPS. Start with a short max-age; raise it after testing.
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// =====================================================================
// DATABASE CONNECTION
// =====================================================================

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
