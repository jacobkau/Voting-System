<?php
// includes/csrf.php
// CSRF protection helpers. Include this AFTER session_start().

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get (or create) the CSRF token for this session.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field with the CSRF token.
 * Usage in a form:  <?= csrfField() ?>
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
         . '">';
}

/**
 * Verify the submitted CSRF token. Dies with 403 if invalid.
 * Call at the top of every POST handler.
 */
function verifyCsrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';

    // Also accept the token from a header (useful for AJAX/fetch)
    if ($submitted === '' && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        // If it's an AJAX request, respond with JSON
        if (!empty($_POST['ajax']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.']);
            exit();
        }
        die('Invalid or expired CSRF token. Please refresh the page and try again.');
    }
}

/**
 * Rotate the CSRF token (call after login/logout).
 */
function rotateCsrf(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
