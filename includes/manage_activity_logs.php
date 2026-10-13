<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ============================================================
// Make sure event_log exists (safe no-op if it does)
// ============================================================
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS event_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50),
            event_type VARCHAR(50),
            event_description VARCHAR(500),
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_username (username),
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("event_log setup error: " . $e->getMessage());
}

// ============================================================
// CSV export — must run before any HTML output
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Apply the same filters as the on-screen list
    [$whereSql, $params] = buildActivityFilters($_GET);

    try {
        $stmt = $conn->prepare("
            SELECT id, username, event_type, event_description, date
            FROM event_log
            $whereSql
            ORDER BY date DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Username', 'Event Type', 'Description', 'Date (EAT)']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['username'],
                $r['event_type'],
                $r['event_description'],
                $r['date']
            ]);
        }
        fclose($out);
        exit;

    } catch (PDOException $e) {
        error_log("Activity log CSV error: " . $e->getMessage());
        exit;
    }
}

// ============================================================
// Handle "Clear old logs" action
// ============================================================
$actionMessage = "";
$actionMessageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_old') {
        $days = (int)($_POST['days'] ?? 30);
        if ($days < 1) $days = 30;

        try {
            $stmt = $conn->prepare("DELETE FROM event_log WHERE date < (NOW() - INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();

            // Log the cleanup itself
            $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description)
                VALUES (?, 'Maintenance', ?)
            ")->execute([
                $_SESSION['username'] ?? 'admin',
                "Cleared {$deleted} activity log entries older than {$days} days"
            ]);

            $actionMessage = "Cleared {$deleted} log entr" . ($deleted === 1 ? 'y' : 'ies') . " older than {$days} days.";
            $actionMessageType = 'success';
        } catch (PDOException $e) {
            error_log("Activity log clear error: " . $e->getMessage());
            $actionMessage = "Could not clear logs: " . $e->getMessage();
            $actionMessageType = 'error';
        }
    }
}

// ============================================================
// Helper: build WHERE clause from GET filters
// ============================================================
function buildActivityFilters(array $q): array {
    $where = [];
    $params = [];

    // Search across description, username, event type
    if (!empty($q['search'])) {
        $search = '%' . $q['search'] . '%';
        $where[] = "(event_description LIKE ? OR username LIKE ? OR event_type LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    // Event type filter
    if (!empty($q['event_type'])) {
        $where[] = "event_type = ?";
        $params[] = $q['event_type'];
    }

    // Username filter
    if (!empty($q['username'])) {
        $where[] = "username = ?";
        $params[] = $q['username'];
    }

    // Date range
    if (!empty($q['date_from'])) {
        $where[] = "date >= ?";
        $params[] = $q['date_from'] . ' 00:00:00';
    }
    if (!empty($q['date_to'])) {
        $where[] = "date <= ?";
        $params[] = $q['date_to'] . ' 23:59:59';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    return [$whereSql, $params];
}

// ============================================================
// Fetch filter inputs
// ============================================================
$filterSearch    = trim($_GET['search'] ?? '');
$filterType      = trim($_GET['event_type'] ?? '');
$filterUsername  = trim($_GET['username'] ?? '');
$filterDateFrom  = trim($_GET['date_from'] ?? '');
$filterDateTo    = trim($_GET['date_to'] ?? '');

// Per page
$allowedPerPage = [25, 50, 100, 200];
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 50;
}

// Current page
$page = max(1, (int)($_GET['p'] ?? 1));

// Build filter query
[$whereSql, $params] = buildActivityFilters($_GET);

// ============================================================
// Total count for pagination
// ============================================================
$totalRows = 0;
try {
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM event_log $whereSql");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Activity log count error: " . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// ============================================================
// Fetch the current page of logs
// ============================================================
$logs = [];
try {
    $stmt = $conn->prepare("
        SELECT id, username, event_type, event_description, date
        FROM event_log
        $whereSql
        ORDER BY date DESC, id DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Activity log fetch error: " . $e->getMessage());
}

// ============================================================
// Summary stats
// ============================================================
$stats = [
    'total'        => 0,
    'unique_users' => 0,
    'today'        => 0,
    'event_types'  => 0,
];
try {
    $stats['total'] = (int)$conn->query("SELECT COUNT(*) FROM event_log")->fetchColumn();
    $stats['unique_users'] = (int)$conn->query("SELECT COUNT(DISTINCT username) FROM event_log WHERE username IS NOT NULL")->fetchColumn();
    $stats['today'] = (int)$conn->query("SELECT COUNT(*) FROM event_log WHERE DATE(date) = CURDATE()")->fetchColumn();
    $stats['event_types'] = (int)$conn->query("SELECT COUNT(DISTINCT event_type) FROM event_log WHERE event_type IS NOT NULL")->fetchColumn();
} catch (PDOException $e) {
    error_log("Activity log stats error: " . $e->getMessage());
}

// ============================================================
// Distinct event types + usernames for the filter dropdowns
// ============================================================
$eventTypes = [];
$usernames = [];
try {
    $eventTypes = $conn->query("
        SELECT event_type, COUNT(*) AS cnt
        FROM event_log
        WHERE event_type IS NOT NULL AND event_type <> ''
        GROUP BY event_type
        ORDER BY event_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    $usernames = $conn->query("
        SELECT username, COUNT(*) AS cnt
        FROM event_log
        WHERE username IS NOT NULL AND username <> ''
        GROUP BY username
        ORDER BY username
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Activity log filters error: " . $e->getMessage());
}

// ============================================================
// Icon + color mapping by event type keyword
// ============================================================
function eventIcon(string $type): array {
    $t = strtolower($type);
    if (strpos($t, 'login') !== false || strpos($t, 'auth') !== false) {
        return ['fa-sign-in-alt', 'login'];
    }
    if (strpos($t, 'logout') !== false) {
        return ['fa-sign-out-alt', 'logout'];
    }
    if (strpos($t, 'approve') !== false) {
        return ['fa-check-circle', 'approve'];
    }
    if (strpos($t, 'reject') !== false || strpos($t, 'unapprove') !== false) {
        return ['fa-times-circle', 'reject'];
    }
    if (strpos($t, 'candidate') !== false) {
        return ['fa-user-tie', 'candidate'];
    }
    if (strpos($t, 'voter') !== false || strpos($t, 'registration') !== false) {
        return ['fa-user-check', 'voter'];
    }
    if (strpos($t, 'election') !== false) {
        return ['fa-poll', 'election'];
    }
    if (strpos($t, 'post') !== false) {
        return ['fa-clipboard-list', 'post'];
    }
    if (strpos($t, 'delete') !== false || strpos($t, 'remove') !== false) {
        return ['fa-trash', 'delete'];
    }
    if (strpos($t, 'reset') !== false) {
        return ['fa-sync-alt', 'reset'];
    }
    if (strpos($t, 'maintenance') !== false || strpos($t, 'migration') !== false) {
        return ['fa-wrench', 'maintenance'];
    }
    if (strpos($t, 'withdraw') !== false) {
        return ['fa-undo', 'withdraw'];
    }
    return ['fa-info-circle', 'default'];
}

// Helper: preserve current query string when changing filters
function logUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    // Remove empties
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) unset($q[$k]);
    }
    return '?' . http_build_query($q);
}
?>

<style>
/* ─────────────────────────────────────────────
   Page wrapper
   ───────────────────────────────────────────── */
.al-wrap { max-width: 1300px; margin: 0 auto; }

/* ─────────────────────────────────────────────
   Header
   ───────────────────────────────────────────── */
.al-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 20px;
}
.al-header-left h1 {
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 4px 0;
}
.al-header-left h1 i { color: #2c7a7b; }
.al-header-left p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}
.al-header-right {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.al-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
}
.al-btn-primary {
    background: #2c7a7b;
    color: white;
}
.al-btn-primary:hover { background: #236162; transform: translateY(-1px); }

.al-btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}
.al-btn-secondary:hover { background: #e5e7eb; }

.al-btn-danger {
    background: #dc2626;
    color: white;
}
.al-btn-danger:hover { background: #b91c1c; transform: translateY(-1px); }

/* ─────────────────────────────────────────────
   Stats strip
   ───────────────────────────────────────────── */
.al-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.al-stat {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.al-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #e6f4f4;
    color: #2c7a7b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.al-stat-info { flex: 1; min-width: 0; }
.al-stat-value {
    font-size: 22px;
    font-weight: 800;
    color: #1f2937;
    line-height: 1.2;
}
.al-stat-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 2px;
}

/* ─────────────────────────────────────────────
   Filters card
   ───────────────────────────────────────────── */
.al-filters {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.al-filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}
.al-field label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #4b5563;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 5px;
}
.al-field input,
.al-field select {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    background: #ffffff;
    color: #1f2937;
    -webkit-text-fill-color: #1f2937;
    box-sizing: border-box;
}
.al-field input:focus,
.al-field select:focus {
    outline: none;
    border-color: #2c7a7b;
    box-shadow: 0 0 0 3px rgba(44,122,123,0.12);
}

.al-filter-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
}
.al-active-filters {
    font-size: 12px;
    color: #6b7280;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.al-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #e6f4f4;
    color: #2c7a7b;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

/* ─────────────────────────────────────────────
   Log table
   ───────────────────────────────────────────── */
.al-table-wrap {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.al-table {
    width: 100%;
    border-collapse: collapse;
}
.al-table thead th {
    background: #f9fafb;
    color: #6b7280;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 11px 14px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}
.al-table tbody td {
    padding: 12px 14px;
    font-size: 13px;
    color: #374151;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: top;
}
.al-table tbody tr:last-child td { border-bottom: none; }
.al-table tbody tr:hover { background: #f9fafb; }

.al-typ {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-weight: 600;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
.al-typ i { font-size: 11px; }

.al-typ-login       { background: #dbeafe; color: #1e40af; }
.al-typ-logout      { background: #f3f4f6; color: #4b5563; }
.al-typ-approve     { background: #d1fae5; color: #065f46; }
.al-typ-reject      { background: #fee2e2; color: #991b1b; }
.al-typ-candidate   { background: #e6f4f4; color: #2c7a7b; }
.al-typ-voter       { background: #ede9fe; color: #5b21b6; }
.al-typ-election    { background: #cffafe; color: #0e7490; }
.al-typ-post        { background: #fef3c7; color: #92400e; }
.al-typ-delete      { background: #fee2e2; color: #991b1b; }
.al-typ-reset       { background: #fce7f3; color: #9d174d; }
.al-typ-maintenance { background: #f3f4f6; color: #6b7280; }
.al-typ-withdraw    { background: #fef3c7; color: #92400e; }
.al-typ-default     { background: #f3f4f6; color: #4b5563; }

.al-user {
    font-weight: 600;
    color: #1f2937;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.al-user i { color: #9ca3af; font-size: 11px; }

.al-desc {
    color: #4b5563;
    line-height: 1.5;
    max-width: 500px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    cursor: pointer;
}
.al-desc:hover { color: #1f2937; }

.al-date {
    font-size: 12px;
    color: #6b7280;
    white-space: nowrap;
}
.al-date strong {
    display: block;
    color: #374151;
    font-weight: 600;
}

.al-empty {
    padding: 60px 20px;
    text-align: center;
    color: #9ca3af;
}
.al-empty i {
    font-size: 48px;
    display: block;
    margin-bottom: 12px;
    color: #d1d5db;
}

/* ─────────────────────────────────────────────
   Pagination
   ───────────────────────────────────────────── */
.al-pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
    padding: 12px 0;
}
.al-pager-info {
    font-size: 13px;
    color: #6b7280;
}
.al-pager-controls {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.al-pager-btn {
    min-width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 12px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #4b5563;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.al-pager-btn:hover:not(.disabled):not(.active) {
    background: #f9fafb;
    border-color: #2c7a7b;
    color: #2c7a7b;
}
.al-pager-btn.active {
    background: #2c7a7b;
    color: white;
    border-color: #2c7a7b;
}
.al-pager-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}
.al-pager-ellipsis {
    padding: 0 4px;
    color: #9ca3af;
    font-size: 13px;
}

/* ─────────────────────────────────────────────
   Toast / alerts
   ───────────────────────────────────────────── */
.al-msg {
    padding: 12px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.al-msg.success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
.al-msg.error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #dc2626; }

/* ─────────────────────────────────────────────
   Modal
   ───────────────────────────────────────────── */
.al-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.6);
    z-index: 1000;
    padding: 20px;
    overflow-y: auto;
}
.al-modal.open { display: flex; align-items: flex-start; justify-content: center; }
.al-modal-content {
    background: #ffffff;
    border-radius: 14px;
    width: 100%;
    max-width: 600px;
    margin: 60px 0;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
    animation: alSlide 0.25s ease;
}
@keyframes alSlide {
    from { transform: translateY(-24px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}
.al-modal-header {
    background: #2c7a7b;
    color: white;
    padding: 16px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.al-modal-header h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.al-modal-close {
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.al-modal-close:hover { background: rgba(255,255,255,0.3); }
.al-modal-body { padding: 22px; }

.al-detail-row {
    display: flex;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
}
.al-detail-row:last-child { border-bottom: none; }
.al-detail-row strong {
    color: #6b7280;
    font-weight: 600;
    min-width: 100px;
    flex-shrink: 0;
}
.al-detail-row span { color: #1f2937; word-break: break-word; }
.al-detail-desc {
    background: #f9fafb;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #374151;
    line-height: 1.6;
    word-break: break-word;
}

/* ─────────────────────────────────────────────
   Cleanup modal
   ───────────────────────────────────────────── */
.al-warning {
    background: #fef2f2;
    color: #991b1b;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 18px;
    border-left: 3px solid #dc2626;
}
.al-input-row {
    margin-bottom: 14px;
}
.al-input-row label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.al-input-row input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    box-sizing: border-box;
}
.al-input-row input:focus {
    outline: none;
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220,38,38,0.15);
}

/* ─────────────────────────────────────────────
   Dark theme support
   ───────────────────────────────────────────── */
body.dark-theme .al-header-left h1 { color: #f3f4f6; }
body.dark-theme .al-header-left p { color: #9ca3af; }

body.dark-theme .al-btn-secondary { background: #2d2d3d; color: #cbd5e1; }
body.dark-theme .al-btn-secondary:hover { background: #3d3d4d; }

body.dark-theme .al-stat,
body.dark-theme .al-filters,
body.dark-theme .al-table-wrap {
    background: #1e1e2e;
    border-color: #3d3d4d;
}
body.dark-theme .al-stat-value { color: #f3f4f6; }
body.dark-theme .al-stat-label { color: #9ca3af; }
body.dark-theme .al-stat-icon { background: rgba(44,122,123,0.2); }

body.dark-theme .al-field label { color: #cbd5e1; }
body.dark-theme .al-field input,
body.dark-theme .al-field select {
    background: #2d2d3d;
    border-color: #3d3d4d;
    color: #f3f4f6;
    -webkit-text-fill-color: #f3f4f6;
}
body.dark-theme .al-field input:focus,
body.dark-theme .al-field select:focus {
    border-color: #4fd1c5;
}

body.dark-theme .al-table thead th {
    background: #2d2d3d;
    color: #cbd5e1;
    border-bottom-color: #3d3d4d;
}
body.dark-theme .al-table tbody td {
    color: #e5e7eb;
    border-bottom-color: #2d2d3d;
}
body.dark-theme .al-table tbody tr:hover { background: #25253a; }
body.dark-theme .al-user { color: #f3f4f6; }
body.dark-theme .al-desc { color: #cbd5e1; }
body.dark-theme .al-date strong { color: #e5e7eb; }

body.dark-theme .al-chip { background: rgba(44,122,123,0.25); color: #a7f3d0; }

body.dark-theme .al-pager-btn {
    background: #1e1e2e;
    border-color: #3d3d4d;
    color: #cbd5e1;
}
body.dark-theme .al-pager-btn:hover:not(.disabled):not(.active) {
    background: #2d2d3d;
    border-color: #4fd1c5;
    color: #4fd1c5;
}
body.dark-theme .al-pager-btn.active {
    background: #2c7a7b;
    color: white;
    border-color: #2c7a7b;
}
body.dark-theme .al-pager-info { color: #9ca3af; }

body.dark-theme .al-msg.success { background: #064e3b; color: #a7f3d0; }
body.dark-theme .al-msg.error   { background: #7f1d1d; color: #fecaca; }

body.dark-theme .al-empty { color: #9ca3af; }
body.dark-theme .al-empty i { color: #4b5563; }

body.dark-theme .al-modal-content { background: #1e1e2e; }
body.dark-theme .al-modal-body { color: #e5e7eb; }
body.dark-theme .al-detail-row { border-bottom-color: #3d3d4d; }
body.dark-theme .al-detail-row strong { color: #9ca3af; }
body.dark-theme .al-detail-row span { color: #f3f4f6; }
body.dark-theme .al-detail-desc {
    background: #2d2d3d;
    color: #cbd5e1;
}

/* ─────────────────────────────────────────────
   Responsive
   ───────────────────────────────────────────── */
@media (max-width: 1100px) {
    .al-filters-grid {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
@media (max-width: 900px) {
    .al-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .al-stats { grid-template-columns: 1fr; }
    .al-filters-grid { grid-template-columns: 1fr; }
    .al-header { flex-direction: column; align-items: stretch; }
    .al-header-right { justify-content: flex-start; }
    .al-table thead { display: none; }
    .al-table tbody tr {
        display: block;
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    body.dark-theme .al-table tbody tr { border-bottom-color: #3d3d4d; }
    .al-table tbody td {
        display: block;
        padding: 4px 0;
        border: none;
    }
    .al-table tbody td::before {
        content: attr(data-label);
        display: block;
        font-size: 10px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 2px;
    }
    .al-desc { -webkit-line-clamp: unset; }
}
</style>

<div class="al-wrap">

    <!-- Header -->
    <div class="al-header">
        <div class="al-header-left">
            <h1><i class="fas fa-history"></i> Activity Logs</h1>
            <p>Audit trail of every action performed by administrators and users</p>
        </div>
        <div class="al-header-right">
            <a class="al-btn al-btn-secondary" href="<?= htmlspecialchars(logUrl(['export' => 'csv'])) ?>">
                <i class="fas fa-download"></i> Export CSV
            </a>
            <button type="button" class="al-btn al-btn-danger" onclick="openCleanupModal()">
                <i class="fas fa-broom"></i> Clear Old Logs
            </button>
        </div>
    </div>

    <!-- Action message -->
    <?php if (!empty($actionMessage)): ?>
        <div class="al-msg <?= htmlspecialchars($actionMessageType) ?>">
            <i class="fas <?= $actionMessageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($actionMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="al-stats">
        <div class="al-stat">
            <div class="al-stat-icon"><i class="fas fa-list-ul"></i></div>
            <div class="al-stat-info">
                <div class="al-stat-value"><?= number_format($stats['total']) ?></div>
                <div class="al-stat-label">Total Events</div>
            </div>
        </div>
        <div class="al-stat">
            <div class="al-stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="al-stat-info">
                <div class="al-stat-value"><?= number_format($stats['today']) ?></div>
                <div class="al-stat-label">Today</div>
            </div>
        </div>
        <div class="al-stat">
            <div class="al-stat-icon"><i class="fas fa-users"></i></div>
            <div class="al-stat-info">
                <div class="al-stat-value"><?= number_format($stats['unique_users']) ?></div>
                <div class="al-stat-label">Unique Users</div>
            </div>
        </div>
        <div class="al-stat">
            <div class="al-stat-icon"><i class="fas fa-tags"></i></div>
            <div class="al-stat-info">
                <div class="al-stat-value"><?= number_format($stats['event_types']) ?></div>
                <div class="al-stat-label">Event Types</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form class="al-filters" method="get">
        <input type="hidden" name="page" value="activity_logs">
        <div class="al-filters-grid">
            <div class="al-field">
                <label for="search"><i class="fas fa-search"></i> Search</label>
                <input type="text" id="search" name="search" placeholder="Search description, user, type..."
                       value="<?= htmlspecialchars($filterSearch) ?>">
            </div>
            <div class="al-field">
                <label for="event_type">Event Type</label>
                <select id="event_type" name="event_type">
                    <option value="">All</option>
                    <?php foreach ($eventTypes as $et): ?>
                        <option value="<?= htmlspecialchars($et['event_type']) ?>"
                            <?= $filterType === $et['event_type'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($et['event_type']) ?> (<?= (int)$et['cnt'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="al-field">
                <label for="username">User</label>
                <select id="username" name="username">
                    <option value="">All</option>
                    <?php foreach ($usernames as $u): ?>
                        <option value="<?= htmlspecialchars($u['username']) ?>"
                            <?= $filterUsername === $u['username'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?> (<?= (int)$u['cnt'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="al-field">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="al-field">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="al-field">
                <label for="per_page">Per Page</label>
                <select id="per_page" name="per_page">
                    <?php foreach ($allowedPerPage as $pp): ?>
                        <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>>
                            <?= $pp ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="al-filter-actions">
            <div class="al-active-filters">
                <?php
                $hasFilters = $filterSearch || $filterType || $filterUsername || $filterDateFrom || $filterDateTo;
                if ($hasFilters):
                ?>
                    <span>Active:</span>
                    <?php if ($filterSearch): ?>
                        <span class="al-chip"><i class="fas fa-search"></i> <?= htmlspecialchars($filterSearch) ?></span>
                    <?php endif; ?>
                    <?php if ($filterType): ?>
                        <span class="al-chip"><i class="fas fa-tag"></i> <?= htmlspecialchars($filterType) ?></span>
                    <?php endif; ?>
                    <?php if ($filterUsername): ?>
                        <span class="al-chip"><i class="fas fa-user"></i> <?= htmlspecialchars($filterUsername) ?></span>
                    <?php endif; ?>
                    <?php if ($filterDateFrom): ?>
                        <span class="al-chip"><i class="fas fa-calendar"></i> from <?= htmlspecialchars($filterDateFrom) ?></span>
                    <?php endif; ?>
                    <?php if ($filterDateTo): ?>
                        <span class="al-chip"><i class="fas fa-calendar"></i> to <?= htmlspecialchars($filterDateTo) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div style="display:flex; gap:8px;">
                <?php if ($hasFilters): ?>
                    <a href="?page=activity_logs" class="al-btn al-btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
                <button type="submit" class="al-btn al-btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </div>
    </form>

    <!-- Table -->
    <?php if (empty($logs)): ?>
        <div class="al-table-wrap">
            <div class="al-empty">
                <i class="fas fa-inbox"></i>
                <p><strong>No activity logs found.</strong></p>
                <p style="font-size:12px; margin-top:6px;">
                    <?= $hasFilters ? 'Try adjusting or clearing your filters.' : 'Events will appear here once users start interacting with the system.' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="al-table-wrap">
            <table class="al-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th style="width:180px;">Event Type</th>
                        <th style="width:150px;">User</th>
                        <th>Description</th>
                        <th style="width:150px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        [$icon, $cls] = eventIcon($log['event_type'] ?? '');
                        $when = !empty($log['date']) ? strtotime($log['date']) : null;
                        ?>
                        <tr>
                            <td data-label="ID" style="color:#9ca3af; font-size:12px;">#<?= (int)$log['id'] ?></td>
                            <td data-label="Event Type">
                                <span class="al-typ al-typ-<?= htmlspecialchars($cls) ?>">
                                    <i class="fas <?= htmlspecialchars($icon) ?>"></i>
                                    <?= htmlspecialchars($log['event_type'] ?? 'Unknown') ?>
                                </span>
                            </td>
                            <td data-label="User">
                                <span class="al-user">
                                    <i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($log['username'] ?: 'system') ?>
                                </span>
                            </td>
                            <td data-label="Description">
                                <div class="al-desc"
                                     onclick='openDetail(<?= (int)$log['id'] ?>,
                                                          <?= json_encode($log['event_type'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                          <?= json_encode($log['username'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                          <?= json_encode($log['event_description'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                          <?= json_encode($log['date'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <?= htmlspecialchars($log['event_description'] ?? '—') ?>
                                </div>
                            </td>
                            <td data-label="Date">
                                <div class="al-date">
                                    <strong><?= $when ? date('M d, Y', $when) : '—' ?></strong>
                                    <?= $when ? date('H:i:s', $when) : '' ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="al-pager">
            <div class="al-pager-info">
                Showing <strong><?= number_format($offset + 1) ?></strong>–<strong><?= number_format(min($offset + $perPage, $totalRows)) ?></strong>
                of <strong><?= number_format($totalRows) ?></strong> entries
                <?php if ($hasFilters): ?> (filtered)<?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="al-pager-controls">
                    <?php
                    $prevDisabled = $page <= 1;
                    $nextDisabled = $page >= $totalPages;
                    $window = 2; // pages around current
                    ?>
                    <a class="al-pager-btn <?= $prevDisabled ? 'disabled' : '' ?>"
                       href="<?= $prevDisabled ? '#' : htmlspecialchars(logUrl(['p' => $page - 1])) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <?php
                    $shown = [];
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i === 1 || $i === $totalPages
                            || ($i >= $page - $window && $i <= $page + $window)) {
                            $shown[] = $i;
                        }
                    }
                    $prev = 0;
                    foreach ($shown as $i):
                        if ($prev && $i - $prev > 1):
                    ?>
                        <span class="al-pager-ellipsis">…</span>
                    <?php
                        endif;
                        $prev = $i;
                    ?>
                        <a class="al-pager-btn <?= $i === $page ? 'active' : '' ?>"
                           href="<?= htmlspecialchars(logUrl(['p' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    <?php endforeach; ?>

                    <a class="al-pager-btn <?= $nextDisabled ? 'disabled' : '' ?>"
                       href="<?= $nextDisabled ? '#' : htmlspecialchars(logUrl(['p' => $page + 1])) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Detail modal -->
<div class="al-modal" id="alDetailModal" onclick="if(event.target===this) closeDetail()">
    <div class="al-modal-content" onclick="event.stopPropagation()">
        <div class="al-modal-header">
            <h4><i class="fas fa-info-circle"></i> Event Details</h4>
            <button type="button" class="al-modal-close" onclick="closeDetail()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="al-modal-body">
            <div class="al-detail-row">
                <strong>ID</strong>
                <span id="alDetailId">—</span>
            </div>
            <div class="al-detail-row">
                <strong>Event Type</strong>
                <span id="alDetailType">—</span>
            </div>
            <div class="al-detail-row">
                <strong>User</strong>
                <span id="alDetailUser">—</span>
            </div>
            <div class="al-detail-row">
                <strong>Date</strong>
                <span id="alDetailDate">—</span>
            </div>
            <div class="al-detail-row" style="flex-direction: column; align-items: stretch;">
                <strong style="margin-bottom: 8px;">Description</strong>
                <div class="al-detail-desc" id="alDetailDesc">—</div>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup modal -->
<div class="al-modal" id="alCleanupModal" onclick="if(event.target===this) closeCleanupModal()">
    <div class="al-modal-content" onclick="event.stopPropagation()" style="max-width: 480px;">
        <div class="al-modal-header" style="background: #dc2626;">
            <h4><i class="fas fa-broom"></i> Clear Old Logs</h4>
            <button type="button" class="al-modal-close" onclick="closeCleanupModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="al-modal-body">
            <div class="al-warning">
                <strong>This action cannot be undone.</strong>
                <div style="margin-top: 4px;">
                    Log entries older than the specified number of days will be permanently deleted.
                </div>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="clear_old">
                <div class="al-input-row">
                    <label for="alCleanupDays">Delete logs older than (days):</label>
                    <input type="number" id="alCleanupDays" name="days" value="30" min="1" max="3650" required>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top: 6px;">
                    <button type="button" class="al-btn al-btn-secondary" onclick="closeCleanupModal()">Cancel</button>
                    <button type="submit" class="al-btn al-btn-danger">
                        <i class="fas fa-trash"></i> Clear Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDetail(id, type, user, desc, date) {
    document.getElementById('alDetailId').textContent = '#' + id;
    document.getElementById('alDetailType').textContent = type || '—';
    document.getElementById('alDetailUser').textContent = user || 'system';
    document.getElementById('alDetailDate').textContent = date || '—';
    document.getElementById('alDetailDesc').textContent = desc || '—';
    document.getElementById('alDetailModal').classList.add('open');
}

function closeDetail() {
    document.getElementById('alDetailModal').classList.remove('open');
}

function openCleanupModal() {
    document.getElementById('alCleanupModal').classList.add('open');
}

function closeCleanupModal() {
    document.getElementById('alCleanupModal').classList.remove('open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetail();
        closeCleanupModal();
    }
});
</script>
