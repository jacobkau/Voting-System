<?php
// =====================================================================
// conn.php — database, session, HTTPS enforcement
// =====================================================================

if (defined('CONN_LOADED')) {
    return;
}
define('CONN_LOADED', true);

// =====================================================================
// PROXY DETECTION 
// =====================================================================
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
    && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// =====================================================================
// SESSION SAVE PATH 
// =====================================================================
$sessCandidates = [
    session_save_path(),
    sys_get_temp_dir(),                
    '/tmp',
    __DIR__ . '/../tmp_sessions',       
];

$chosenSessionPath = null;
foreach ($sessCandidates as $p) {
    if ($p && is_dir($p) && is_writable($p)) {
        $chosenSessionPath = $p;
        break;
    }
}

if ($chosenSessionPath === null) {
    $custom = __DIR__ . '/../tmp_sessions';
    if (!is_dir($custom)) {
        @mkdir($custom, 0700, true);
    }
    if (is_dir($custom) && is_writable($custom)) {
        $chosenSessionPath = $custom;
    }
}

if ($chosenSessionPath !== null) {
    session_save_path($chosenSessionPath);
} else {
    error_log("SESSION ERROR: no writable session path found.");
}

// =====================================================================
// SESSION COOKIE PARAMS + START
// =====================================================================
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================================
// HTTPS ENFORCEMENT
// =====================================================================
if (php_sapi_name() !== 'cli'
    && !headers_sent()
    && empty($GLOBALS['__https_enforced'])) {

    $GLOBALS['__https_enforced'] = true;

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

    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// =====================================================================
// DATABASE CONNECTION
// =====================================================================

if (!function_exists('conn_fail')) {
    function conn_fail(string $logMessage): void {
        error_log($logMessage);

        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $logMessage . "\n");
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Database temporarily unavailable. Please try again shortly.";
        exit;
    }
}

$uri = getenv('AIVEN_DATABASE_URL');

if (!$uri) {
    conn_fail("AIVEN_DATABASE_URL is missing from the environment.");
}

$fields = parse_url($uri);

if (!$fields || !isset($fields["host"])) {
    conn_fail("AIVEN_DATABASE_URL is malformed: " . substr($uri, 0, 30) . "...");
}

$dsn  = "mysql:host=" . $fields["host"];
$dsn .= ";port=" . ($fields["port"] ?? '27643');
$dsn .= ";dbname=defaultdb;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE                      => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_CA                 => __DIR__ . '/ca.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
];

try {
    $user = $fields["user"] ?? 'avnadmin';
    $pass = $fields["pass"] ?? '';

    $db   = new PDO($dsn, $user, $pass, $options);
    $conn = $db;

} catch (Exception $e) {
    conn_fail("DB connection failed: " . $e->getMessage());
}

// ✅ No closing ?>
