<?php
if (!ob_get_level()) {
    ob_start();
}
require_once __DIR__ . '/conn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once __DIR__ . '/../cloudinary.php';

// ─────────────────────────────────────────────────────────────
// Load admin info
// ─────────────────────────────────────────────────────────────
$adminName = "";
$adminPhoto = "";
try {
    $stmt = $conn->prepare("SELECT name, profile_photo FROM admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $adminName = $admin['name'] ?? ($_SESSION['username'] ?? 'Admin');
        $adminPhoto = $admin['profile_photo'] ?? '';
    } else {
        $adminName = $_SESSION['username'] ?? 'Admin';
    }
} catch (PDOException $e) {
    $adminName = $_SESSION['username'] ?? 'Admin';
}

$adminPhotoDisplay = (!empty($adminPhoto) && preg_match('#^https?://#i', $adminPhoto))
    ? $adminPhoto
    : defaultAvatarUrl();

// ─────────────────────────────────────────────────────────────
// Determine current page
// ─────────────────────────────────────────────────────────────
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
if ($page === '') {
    $inferred = [
        'election_id'  => 'voters',
        'status'       => 'voters',
        'user_id'      => 'users',
        'q'            => 'users',
    ];
    foreach ($inferred as $param => $target) {
        if (isset($_GET[$param])) {
            $page = $target;
            break;
        }
    }
}
$currentPage = $page;

// ─────────────────────────────────────────────────────────────
// Sub-page map
// ─────────────────────────────────────────────────────────────
$subpageMap = [
    'users'         => 'manage_users.php',
    'voters'        => 'manage_election_voters.php',
    'candidates'    => 'manage_candidates.php',
    'elections'     => 'manage_elections.php',
    'posts'         => 'manage_posts.php',
    'results'       => 'manage_results.php',
    'admins'        => 'manage_admins.php',
    'profile'       => 'manage_profile.php',
    'activity_logs' => 'manage_activity_logs.php',
    'home'          => 'manage_dashboard.php',
];

$subpageFile = $subpageMap[$currentPage] ?? $subpageMap['home'];

// ─────────────────────────────────────────────────────────────
// Render sub-page into a buffer and extract its body content
// ─────────────────────────────────────────────────────────────
$subpageStyles    = '';
$subpageBodyHtml  = '';
$subpageError     = '';
$subpageDebug     = [];

$subpageFullPath = __DIR__ . '/' . $subpageFile;

if (file_exists($subpageFullPath)) {
    $nestedLevel = ob_get_level();
    ob_start();

    try {
        include($subpageFullPath);
    } catch (Throwable $e) {
        error_log("Sub-page include error ({$subpageFile}): " . $e->getMessage());
        echo '<div style="padding:40px;text-align:center;color:#991b1b;">'
           . 'Could not load this page. Please try again.'
           . '</div>';
    }

    // Pull up the buffer to the level we started at
    $captured = '';
    while (ob_get_level() > $nestedLevel) {
        $captured = ob_get_clean() . $captured;
    }

    $rawHtml = $captured;
    $subpageDebug['bytes_captured'] = strlen($rawHtml);
    $subpageDebug['has_doctype']    = (stripos($rawHtml, '<!DOCTYPE') !== false);
    $subpageDebug['has_html_tag']   = (stripos($rawHtml, '<html') !== false);
    $subpageDebug['has_head_tag']   = (stripos($rawHtml, '<head') !== false);
    $subpageDebug['has_body_tag']   = (stripos($rawHtml, '<body') !== false);

    // Hoist <style> blocks
    if (preg_match_all('#<style\b[^>]*>.*?</style>#is', $rawHtml, $styleMatches)) {
        foreach ($styleMatches[0] as $styleTag) {
            $subpageStyles .= $styleTag . "\n";
        }
        $rawHtml = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $rawHtml);
    }

    // Extract body content
    if (preg_match('#<body\b[^>]*>(.*?)</body>#is', $rawHtml, $bodyMatch)) {
        $subpageBodyHtml = $bodyMatch[1];
        $subpageDebug['extraction_mode'] = 'body';
    } else {
        // No body tag — strip any leftover document-level tags
        $cleaned = $rawHtml;
        $cleaned = preg_replace('#<!DOCTYPE[^>]*>#is', '', $cleaned);
        $cleaned = preg_replace('#</?html\b[^>]*>#is', '', $cleaned);
        $cleaned = preg_replace('#</?head\b[^>]*>#is', '', $cleaned);
        $cleaned = preg_replace('#</?body\b[^>]*>#is', '', $cleaned);
        $cleaned = preg_replace('#<meta\b[^>]*>#is', '', $cleaned);
        $cleaned = preg_replace('#<title\b[^>]*>.*?</title>#is', '', $cleaned);
        $cleaned = preg_replace('#<link\b[^>]*rel=["\']stylesheet["\'][^>]*>#is', '', $cleaned);

        $subpageBodyHtml = $cleaned;
        $subpageDebug['extraction_mode'] = 'fallback_clean';
    }
} else {
    $subpageError = "Page file not found: {$subpageFile}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="../logo.jpg" type="image/x-icon">

    <?php if (!empty($subpageStyles)): ?>
        <!-- Styles lifted from the included sub-page (kept inline so main.php wins any tie) -->
        <?php echo $subpageStyles; ?>
    <?php endif; ?>

    <style>
        /* ═════════════════════════════════════════════════════════
           BASE RESET
           ═════════════════════════════════════════════════════════ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f9;
            color: #333;
        }

        /* ═════════════════════════════════════════════════════════
           HEADER
           ═════════════════════════════════════════════════════════ */
        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 70px;
            z-index: 1000;
            background-color: #2c7a7b;
            color: white;
            padding: 12px 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h1 { margin: 0; font-size: 1.5rem; }
        .header-left p  { margin: 5px 0 0; font-size: 0.8rem; opacity: 0.9; }

        .header-right { display: flex; align-items: center; gap: 14px; }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 15px 6px 6px;
            border-radius: 30px;
            text-decoration: none;
            color: inherit;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .admin-info:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        .admin-info i { font-size: 22px; }
        .admin-info span { font-weight: 500; color: white; }

        .admin-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.6);
            display: inline-block;
            vertical-align: middle;
            background: #f4f7f9;
            transition: border-color 0.25s ease;
        }
        .admin-info:hover .admin-avatar { border-color: rgba(255, 255, 255, 0.95); }

        .logout-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 30px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 5px;
        }

        /* ═════════════════════════════════════════════════════════
           LAYOUT
           ═════════════════════════════════════════════════════════ */
        .container {
            display: flex;
            position: fixed;
            top: 70px; left: 0; right: 0; bottom: 0;
        }

        .sidebar {
            background-color: #1e293b;
            width: 250px;
            padding: 16px 12px;
            box-sizing: border-box;
            color: #fff;
            flex-shrink: 0;
            overflow-y: auto;
            overflow-x: hidden;
            height: 100%;
            transition: width 0.25s ease, padding 0.25s ease;
        }

        .sidebar.collapsed {
            width: 72px;
            padding: 16px 8px;
        }

        .sidebar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 4px 12px;
            margin-bottom: 8px;
            border-bottom: 1px solid #334155;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #f1f5f9;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.2s ease;
        }
        .sidebar-brand i { color: #2c7a7b; font-size: 18px; flex-shrink: 0; }
        .sidebar-brand span { transition: opacity 0.2s ease, width 0.2s ease; }

        .sidebar.collapsed .sidebar-brand span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-collapse-btn {
            background: rgba(255, 255, 255, 0.08);
            border: none;
            color: #94a3b8;
            width: 28px; height: 28px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .sidebar-collapse-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        .sidebar-collapse-btn i { transition: transform 0.25s ease; }
        .sidebar.collapsed .sidebar-collapse-btn i { transform: rotate(180deg); }

        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar li { margin-bottom: 4px; }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: #cbd5e1;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 13.5px;
            white-space: nowrap;
            overflow: hidden;
        }
        .sidebar a i {
            width: 20px;
            font-size: 15px;
            flex-shrink: 0;
            text-align: center;
        }
        .sidebar a span { transition: opacity 0.2s ease; }

        .sidebar.collapsed a {
            justify-content: center;
            padding: 10px 0;
        }
        .sidebar.collapsed a span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar a:hover { background-color: #334155; color: white; }
        .sidebar a.active {
            background-color: #2c7a7b;
            color: white;
            border-left: 3px solid #fff;
        }
        .sidebar.collapsed a.active { border-left: none; }

        main {
            flex-grow: 1;
            padding: 25px;
            box-sizing: border-box;
            background-color: #f4f7f9;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .main-content {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            min-height: 100%;
        }

        /* ═════════════════════════════════════════════════════════
           NOTIFICATION BELL
           ═════════════════════════════════════════════════════════ */
        .notif-wrap { position: relative; display: inline-flex; }

        .notif-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
            width: 42px; height: 42px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.25s ease;
            position: relative;
            font-family: inherit;
        }
        .notif-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        .notif-btn.has-new { animation: notifShake 0.6s ease; }

        @keyframes notifShake {
            0%, 100% { transform: rotate(0deg); }
            20%      { transform: rotate(-12deg); }
            40%      { transform: rotate(12deg); }
            60%      { transform: rotate(-8deg); }
            80%      { transform: rotate(8deg); }
        }

        .notif-badge {
            position: absolute;
            top: -4px; right: -4px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            background: #dc2626;
            color: white;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            display: none;
            align-items: center;
            justify-content: center;
            border: 2px solid #2c7a7b;
            line-height: 1;
        }
        .notif-badge.show { display: inline-flex; }

        .notif-panel {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 340px;
            max-width: calc(100vw - 40px);
            background: white;
            color: #1f2937;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            z-index: 1100;
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .notif-panel.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .notif-panel-header {
            background: #2c7a7b;
            color: white;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-panel-header .mark-read {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.85);
            font-size: 11px;
            cursor: pointer;
            font-family: inherit;
            padding: 3px 8px;
            border-radius: 4px;
            transition: background 0.15s ease;
        }
        .notif-panel-header .mark-read:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        .notif-panel-body { max-height: 380px; overflow-y: auto; }

        .notif-item {
            display: flex;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #f3f4f6;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s ease;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f9fafb; }

        .notif-item-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .notif-item-icon.registration { background: #fef3c7; color: #92400e; }
        .notif-item-icon.candidacy    { background: #e6f4f4; color: #2c7a7b; }

        .notif-item-body { flex: 1; min-width: 0; }

        .notif-item-title {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
        }
        .notif-item-desc {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
        }

        .notif-empty {
            padding: 40px 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }
        .notif-empty i {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
            color: #d1d5db;
        }

        /* ═════════════════════════════════════════════════════════
           DEBUG PANEL (only shown with ?debug=1)
           ═════════════════════════════════════════════════════════ */
        .debug-panel {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #78350f;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            line-height: 1.6;
        }
        .debug-panel strong { color: #92400e; }

        /* ═════════════════════════════════════════════════════════
           MOBILE
           ═════════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            header { padding: 12px 20px; height: auto; }
            .header-left h1 { font-size: 1.2rem; }
            .menu-toggle { display: block; }
            .header-right { display: none; }
            .header-right.show {
                display: flex;
                flex-direction: column;
                width: 100%;
                margin-top: 15px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
                padding-top: 15px;
                align-items: stretch;
            }
            .admin-info, .logout-btn { width: 100%; justify-content: center; }
            .notif-wrap { width: 100%; justify-content: center; position: relative; }
            .notif-btn {
                width: 100%;
                height: auto;
                border-radius: 30px;
                padding: 10px;
                justify-content: center;
                gap: 10px;
            }
            .notif-btn::after {
                content: 'Notifications';
                font-size: 14px;
                font-weight: 500;
            }
            .notif-panel {
                position: static;
                transform: none;
                opacity: 1;
                pointer-events: auto;
                box-shadow: none;
                margin-top: 10px;
                width: 100%;
                max-width: 100%;
                display: none;
            }
            .notif-panel.open { display: block; }
            .container {
                top: 0;
                position: relative;
                display: flex;
                flex-direction: column;
                height: auto;
                overflow: visible;
            }
            .sidebar,
            .sidebar.collapsed {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px !important;
                height: 100%;
                z-index: 1200;
                padding: 20px 12px !important;
                overflow-y: auto;
                transition: left 0.3s ease-out;
            }
            .sidebar.open { left: 0; }
            .sidebar.open::after {
                content: '';
                position: fixed;
                top: 0; left: 280px; right: 0; bottom: 0;
                background: rgba(0, 0, 0, 0.4);
                z-index: -1;
            }
            .sidebar.collapsed .sidebar-brand span,
            .sidebar.collapsed a span {
                opacity: 1;
                width: auto;
                overflow: visible;
            }
            .sidebar.collapsed a {
                justify-content: flex-start;
                padding: 10px 12px;
            }
            .sidebar-collapse-btn { display: none; }
            main { padding: 15px; height: auto; overflow: visible; }
            .main-content { padding: 15px; min-height: auto; }
        }

        @media (max-width: 480px) {
            .sidebar a { padding: 10px 12px; font-size: 13px; }
        }

        /* ═════════════════════════════════════════════════════════
           DEFENSIVE LAYOUT LOCKS — placed LAST so they win any tie.
           Sub-pages can redefine .container, .header, .sidebar, etc.
           in their own <style> blocks. These rules make sure the
           admin shell keeps working regardless.
           ═════════════════════════════════════════════════════════ */
        body > .container {
            display: flex !important;
            position: fixed !important;
            top: 70px !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: transparent !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: visible !important;
            margin: 0 !important;
        }

        body > .container > .sidebar {
            display: flex !important;
            flex-direction: column !important;
            width: 250px !important;
            flex-shrink: 0 !important;
            background-color: #1e293b !important;
            padding: 16px 12px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            position: relative !important;
            left: auto !important;
            top: auto !important;
            z-index: 1 !important;
            margin: 0 !important;
            height: 100% !important;
        }

        body > .container > .sidebar.collapsed {
            width: 72px !important;
            padding: 16px 8px !important;
        }

        body > .container > main {
            flex-grow: 1 !important;
            padding: 25px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            height: 100% !important;
            box-sizing: border-box !important;
            background-color: #f4f7f9 !important;
            margin: 0 !important;
        }

        body > .container > main > .main-content {
            background-color: #ffffff !important;
            padding: 25px !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
            min-height: 100% !important;
            overflow: visible !important;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1><i class="fas fa-vote-yea"></i>&nbsp; Witty Voting System Admin Panel</h1>
            <p>Manage your elections and voters</p>
        </div>

        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="header-right" id="headerRight">
            <div class="notif-wrap" id="notifWrap">
                <button type="button" class="notif-btn" id="notifBtn" title="Notifications" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="notifBadge">0</span>
                </button>

                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-header">
                        <span>Notifications</span>
                        <button type="button" class="mark-read" id="notifMarkRead">Mark all read</button>
                    </div>
                    <div class="notif-panel-body" id="notifBody">
                        <div class="notif-empty">
                            <i class="fas fa-bell-slash"></i>
                            Loading...
                        </div>
                    </div>
                </div>
            </div>

            <a href="main.php?page=profile" class="admin-info" title="Profile Settings">
                <img src="<?php echo htmlspecialchars($adminPhotoDisplay); ?>"
                     alt="Admin"
                     class="admin-avatar">
                <span>Welcome, <?php echo htmlspecialchars($adminName); ?></span>
            </a>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-top">
                <a href="main.php?page=home" class="sidebar-brand">
                    <i class="fas fa-vote-yea"></i>
                    <span>Admin Panel</span>
                </a>
                <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Collapse sidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>

            <ul>
                <li><a href="main.php?page=home" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="main.php?page=elections" class="<?php echo $currentPage === 'elections' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> <span>Manage Elections</span></a></li>
                <li><a href="main.php?page=posts" class="<?php echo $currentPage === 'posts' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> <span>Manage Posts</span></a></li>
                <li><a href="main.php?page=candidates" class="<?php echo $currentPage === 'candidates' ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> <span>Manage Candidates</span></a></li>
                <li><a href="main.php?page=voters" class="<?php echo $currentPage === 'voters' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> <span>Manage Voters</span></a></li>
                <li><a href="main.php?page=users" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span>Manage All Users</span></a></li>
                <li><a href="main.php?page=results" class="<?php echo $currentPage === 'results' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> <span>View Results</span></a></li>
                <li><a href="main.php?page=admins" class="<?php echo $currentPage === 'admins' ? 'active' : ''; ?>"><i class="fas fa-user-shield"></i> <span>Manage Admins</span></a></li>
                <li><a href="main.php?page=profile" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user-cog"></i> <span>Profile Settings</span></a></li>
                <li><a href="main.php?page=activity_logs" class="<?php echo $currentPage === 'activity_logs' ? 'active' : ''; ?>"><i class="fas fa-history"></i> <span>Activity Logs</span></a></li>
            </ul>
        </aside>

        <main>
            <div class="main-content">
                <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                    <div class="debug-panel">
                        <strong>DEBUG — main.php sub-page loader</strong><br>
                        Sub-page: <?php echo htmlspecialchars($subpageFile); ?><br>
                        Bytes captured: <?php echo (int)$subpageDebug['bytes_captured']; ?><br>
                        Has &lt;!DOCTYPE&gt;: <?php echo !empty($subpageDebug['has_doctype']) ? 'YES' : 'no'; ?><br>
                        Has &lt;html&gt;: <?php echo !empty($subpageDebug['has_html_tag']) ? 'YES' : 'no'; ?><br>
                        Has &lt;head&gt;: <?php echo !empty($subpageDebug['has_head_tag']) ? 'YES' : 'no'; ?><br>
                        Has &lt;body&gt;: <?php echo !empty($subpageDebug['has_body_tag']) ? 'YES' : 'no'; ?><br>
                        Extraction mode: <?php echo htmlspecialchars($subpageDebug['extraction_mode'] ?? 'none'); ?><br>
                        Body content length: <?php echo strlen($subpageBodyHtml); ?> bytes
                    </div>
                <?php endif; ?>

                <?php
                if (!empty($subpageError)) {
                    echo '<div style="padding:40px;text-align:center;color:#991b1b;">'
                       . htmlspecialchars($subpageError)
                       . '</div>';
                } else {
                    echo $subpageBodyHtml;
                }
                ?>
            </div>
        </main>
    </div>

    <script>
        // ────────────────────────────────────────────────────────────
        // Hamburger menu toggle (mobile)
        // ────────────────────────────────────────────────────────────
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const headerRight = document.getElementById('headerRight');

        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                headerRight.classList.toggle('show');
            });
        }

        document.addEventListener('click', function(event) {
            const isMobile = window.innerWidth <= 768;
            if (isMobile && sidebar && menuToggle) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                    headerRight.classList.remove('show');
                }
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                headerRight.classList.remove('show');
            }
        });

        // ────────────────────────────────────────────────────────────
        // Sidebar collapse toggle (desktop)
        // ────────────────────────────────────────────────────────────
        (function() {
            const collapseBtn = document.getElementById('sidebarCollapseBtn');
            if (!collapseBtn || !sidebar) return;

            const STORAGE_KEY = 'admin_sidebar_collapsed';

            try {
                if (localStorage.getItem(STORAGE_KEY) === '1') {
                    sidebar.classList.add('collapsed');
                }
            } catch (e) { /* localStorage unavailable */ }

            collapseBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                try {
                    localStorage.setItem(STORAGE_KEY, isCollapsed ? '1' : '0');
                } catch (e) { /* ignore */ }
            });
        })();

        // ────────────────────────────────────────────────────────────
        // Live notification bell
        // ────────────────────────────────────────────────────────────
        (function() {
            const POLL_INTERVAL_MS = 15000;
            const API_URL = 'api/api_admin_notifications.php';

            const btn = document.getElementById('notifBtn');
            const badge = document.getElementById('notifBadge');
            const panel = document.getElementById('notifPanel');
            const body = document.getElementById('notifBody');

            if (!btn || !badge || !panel || !body) return;

            let lastTotal = -1;
            let lastSeenTotal = 0;

            try {
                lastSeenTotal = parseInt(localStorage.getItem('notif_seen_total') || '0', 10) || 0;
            } catch (e) {
                lastSeenTotal = 0;
            }

            function renderBadge(total) {
                const unread = Math.max(0, total - lastSeenTotal);
                if (unread > 0) {
                    badge.textContent = unread > 99 ? '99+' : String(unread);
                    badge.classList.add('show');
                } else {
                    badge.textContent = '0';
                    badge.classList.remove('show');
                }
            }

            function renderPanel(data) {
                const items = [];

                if (data.pending_voter_registrations > 0) {
                    const n = data.pending_voter_registrations;
                    items.push(`
                        <a href="main.php?page=voters&status=pending" class="notif-item">
                            <div class="notif-item-icon registration">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="notif-item-body">
                                <div class="notif-item-title">Voter Registration Requests</div>
                                <div class="notif-item-desc">
                                    ${n} request${n === 1 ? '' : 's'} awaiting your review.
                                </div>
                            </div>
                        </a>
                    `);
                }

                if (data.pending_candidates > 0) {
                    const n = data.pending_candidates;
                    items.push(`
                        <a href="main.php?page=candidates" class="notif-item">
                            <div class="notif-item-icon candidacy">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="notif-item-body">
                                <div class="notif-item-title">Candidate Applications</div>
                                <div class="notif-item-desc">
                                    ${n} new candidate application${n === 1 ? '' : 's'} to review.
                                </div>
                            </div>
                        </a>
                    `);
                }

                if (items.length === 0) {
                    body.innerHTML = `
                        <div class="notif-empty">
                            <i class="fas fa-check-circle"></i>
                            You're all caught up.
                        </div>
                    `;
                } else {
                    body.innerHTML = items.join('');
                }
            }

            async function pollOnce() {
                try {
                    const res = await fetch(API_URL, {
                        credentials: 'same-origin',
                        cache: 'no-store'
                    });
                    if (!res.ok) return;

                    const data = await res.json();
                    if (!data.ok) return;

                    renderBadge(data.total);
                    renderPanel(data);

                    if (lastTotal >= 0 && data.total > lastTotal) {
                        btn.classList.add('has-new');
                        setTimeout(() => btn.classList.remove('has-new'), 700);
                    }
                    lastTotal = data.total;

                } catch (err) { /* silent */ }
            }

            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const willOpen = !panel.classList.contains('open');
                panel.classList.toggle('open', willOpen);

                if (willOpen) {
                    pollOnce();
                }
            });

            document.getElementById('notifMarkRead')?.addEventListener('click', function(e) {
                e.stopPropagation();
                lastSeenTotal = lastTotal >= 0 ? lastTotal : 0;
                try { localStorage.setItem('notif_seen_total', String(lastSeenTotal)); } catch (err) {}
                renderBadge(lastSeenTotal);
                panel.classList.remove('open');
            });

            document.addEventListener('click', function(e) {
                if (!panel.classList.contains('open')) return;
                if (!panel.contains(e.target) && !btn.contains(e.target)) {
                    panel.classList.remove('open');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') panel.classList.remove('open');
            });

            pollOnce();
            setInterval(pollOnce, POLL_INTERVAL_MS);

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') pollOnce();
            });
        })();
    </script>
    <?php if (ob_get_level()) { ob_end_flush(); } ?>
</body>
</html>
