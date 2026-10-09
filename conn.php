<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';

// ============================================================
//  ENFORCE HTTPS - Redirect all HTTP traffic to HTTPS
// ============================================================
// Skip enforcement for CLI (cron jobs, scripts) and local development
if (php_sapi_name() !== 'cli') {

    // Detect HTTPS - works behind Render's proxy (X-Forwarded-Proto)
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
        (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
    );

    if (!$isHttps) {
        // Build the HTTPS URL preserving host, path, and query string
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Safety: only redirect if we have a valid host
        if (!empty($host)) {
            $redirectUrl = 'https://' . $host . $requestUri;

            // 301 = permanent redirect (better for SEO and caching)
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
    }

    // ============================================================
    // 0b. SECURITY HEADERS (recommended alongside HTTPS)
    // ============================================================
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ============================================================
// 1. Fetch the secret link from Render's environment settings
// ============================================================
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
