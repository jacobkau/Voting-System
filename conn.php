<?php
if (defined('CONN_LOADED')) {
    return;
}
define('CONN_LOADED', true);

// =====================================================================
// 1. PHP TIMEZONE
// =====================================================================
date_default_timezone_set('Africa/Nairobi');

// =====================================================================
// 2. PROXY DETECTION
// =====================================================================
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
    && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// =====================================================================
// 3. SESSION SAVE PATH
//    Must run BEFORE session_start(). If a session is already active
//    (e.g. a page called session_start() before including conn.php),
//    we silently skip — PHP wouldn't allow the change anyway.
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
    // Only set the save path if no session has been opened yet
    if (session_status() === PHP_SESSION_NONE) {
        session_save_path($chosenSessionPath);
    }
} else {
    error_log("SESSION ERROR: no writable session path found.");
}

// =====================================================================
// 4. SESSION COOKIE PARAMS + START
// =====================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// =====================================================================
// 5. HTTPS ENFORCEMENT
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
            && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);

    if (!$isHttps) {
        $host       = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!empty($host)) {
            header('Location: https://' . $host . $requestUri, true, 301);
            exit;
        }
    }

    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// =====================================================================
// 6. DATABASE CONNECTION
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

    try {
        $conn->exec("SET time_zone = '+03:00'");
    } catch (PDOException $e) {
        error_log("Could not set MySQL session timezone: " . $e->getMessage());
    }

} catch (Exception $e) {
    conn_fail("DB connection failed: " . $e->getMessage());
}

// =====================================================================
// 7. ELECTION STATUS HELPER
// =====================================================================
if (!function_exists('effectiveElectionStatus')) {

    function effectiveElectionStatus(array $e): string {
        // 1. Manual override in force?
        if (!empty($e['manual_status'])) {
            if (empty($e['manual_until'])) {
                return $e['manual_status'];
            }
            if (strtotime($e['manual_until']) > time()) {
                return $e['manual_status'];
            }
        }

        // 2. Derive from dates
        $now   = time();
        $start = !empty($e['start_date']) ? strtotime($e['start_date']) : null;
        $end   = !empty($e['end_date'])   ? strtotime($e['end_date'])   : null;

        if ($start !== null && $now < $start) {
            return 'upcoming';
        }
        if ($end !== null && $now > $end) {
            return 'completed';
        }
        if ($start !== null && $end !== null && $now >= $start && $now <= $end) {
            return 'active';
        }

        return $e['status'] ?? 'upcoming';
    }
}
