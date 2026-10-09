<?php
// =====================================================================
// conn.php — database, session, CSRF, HTTPS enforcement
// Safe to include multiple times per request (guarded below).
// =====================================================================

// ---- Load-once guard: makes this file idempotent ----
if (defined('CONN_LOADED')) {
    return;
}
define('CONN_LOADED', true);

// =====================================================================
// SESSION
// =====================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================================
// CSRF HELPERS
// =====================================================================
require_once __DIR__ . '/csrf.php';

// =====================================================================
// HTTPS ENFORCEMENT
// ---------------------------------------------------------------------
// Runs only once per request, and only if PHP can still send headers.
// Guards prevent:
//   - "Cannot modify header information" when a sub-page re-includes
//     this file after main.php has already printed HTML
//   - Double redirects / double HSTS headers
//   - Any interference with CLI scripts (cron, migrations, etc.)
// =====================================================================
if (php_sapi_name() !== 'cli'
    && !headers_sent()
    && empty($GLOBALS['__https_enforced'])) {

    $GLOBALS['__https_enforced'] = true;

    // Detect HTTPS, honoring proxies (Render, Cloudflare, etc.)
    $isHttps =
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (!empty($_SERVER['HTTP_CF_VISITOR'])
            && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false);

    if (!$isHttps) {
        $host       = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: https://' . $host . $requestUri);
        exit;
    }

    // HSTS — tell browsers to always use HTTPS for this domain
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// =====================================================================
// DATABASE CONNECTION
// =====================================================================

// ---------------------------------------------------------------------
// Helper: emit a clean 500 and exit — never prints before headers are set
// ---------------------------------------------------------------------
if (!function_exists('conn_fail')) {
    function conn_fail(string $logMessage): void {
        error_log($logMessage);

        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $logMessage . "\n");
            exit(1);
        }

        // Only try to send headers if none have been sent yet
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Database temporarily unavailable. Please try again shortly.";
        exit;
    }
}

// 1. Fetch the secret link from Render's environment settings
$uri = getenv('AIVEN_DATABASE_URL');

if (!$uri) {
    conn_fail("AIVEN_DATABASE_URL is missing from the environment.");
}

// 2. Safely parse the database URL details
$fields = parse_url($uri);

if (!$fields || !isset($fields["host"])) {
    conn_fail("AIVEN_DATABASE_URL is malformed: " . substr($uri, 0, 30) . "...");
}

// 3. Cleanly build the basic MySQL DSN (no SSL text inside the string)
$dsn  = "mysql:host=" . $fields["host"];
$dsn .= ";port=" . ($fields["port"] ?? '27643');
$dsn .= ";dbname=defaultdb;charset=utf8mb4";

// 4. Pass the SSL certificate correctly using PHP PDO Array options
$options = [
    PDO::ATTR_ERRMODE                      => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_CA                 => __DIR__ . '/ca.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true, // Forces certificate verification
];

try {
    $user = $fields["user"] ?? 'avnadmin';
    $pass = $fields["pass"] ?? '';

    // 5. Connect securely using the options array
    $db   = new PDO($dsn, $user, $pass, $options);
    $conn = $db;

} catch (Exception $e) {
    conn_fail("DB connection failed: " . $e->getMessage());
}

// ✅ No closing ?>
