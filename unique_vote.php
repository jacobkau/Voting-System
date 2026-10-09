<?php
// includes/csrf_debug.php
// TEMPORARY DIAGNOSTIC — DELETE AFTER USE.

require_once __DIR__ . '/conn.php';

// If this is the AJAX test submission, respond with JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'session_id'         => session_id(),
        'cookie_php_sessid'  => $_COOKIE[session_name()] ?? null,
        'session_csrf'       => $_SESSION['csrf_token'] ?? null,
        'post_csrf'          => $_POST['csrf_token'] ?? null,
        'header_csrf'        => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
        'all_headers'        => array_filter($_SERVER, fn($k) => str_starts_with($k, 'HTTP_'), ARRAY_FILTER_USE_KEY),
        'is_https_detected'  => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'session_save_path'  => session_save_path(),
        'session_writable'   => is_writable(session_save_path()) ? 'yes' : 'no',
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CSRF Debug</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <style>
        body { font-family: monospace; padding: 20px; max-width: 900px; margin: auto; }
        pre { background: #f4f4f4; padding: 12px; border-radius: 6px; overflow-x: auto; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>
    <h2>CSRF Debug</h2>

    <p><strong>Token rendered in meta tag:</strong></p>
    <pre id="metaToken"><?= htmlspecialchars(csrfToken()) ?></pre>

    <p><strong>Session id (server side):</strong> <?= htmlspecialchars(session_id()) ?></p>
    <p><strong>Session cookie name:</strong> <?= htmlspecialchars(session_name()) ?></p>
    <p><strong>PHP session cookie value (browser sent):</strong>
        <?= htmlspecialchars($_COOKIE[session_name()] ?? '(none)') ?>
    </p>

    <p><strong>Session save path:</strong> <?= htmlspecialchars(session_save_path()) ?></p>
    <p><strong>Session save path writable:</strong>
        <?= is_writable(session_save_path()) ? '✅ yes' : '❌ no' ?>
    </p>

    <button id="testBtn">Send test AJAX</button>
    <pre id="result">Click the button above…</pre>

    <script>
        document.getElementById('testBtn').addEventListener('click', async () => {
            const out = document.getElementById('result');
            out.textContent = 'Sending…';

            const token = document.querySelector('meta[name="csrf-token"]').content;

            const formData = new URLSearchParams();
            formData.append('ajax_action', 'debug');
            formData.append('csrf_token', token);   // send in body

            try {
                const res = await fetch('csrf_debug.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': token,          // AND in header
                    },
                    body: formData.toString()
                });
                const data = await res.json();
                out.textContent = JSON.stringify(data, null, 2);
            } catch (err) {
                out.textContent = 'Error: ' + err;
            }
        });
    </script>
</body>
</html>
