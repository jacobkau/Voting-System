<?php
ob_start();
require_once __DIR__ . '/conn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Cloudinary helpers (defaultAvatarUrl)
require_once __DIR__ . '/../cloudinary.php';

// Get admin name + profile photo
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

// Resolve displayable URL — Cloudinary only, fall back to system default
$adminPhotoDisplay = (!empty($adminPhoto) && preg_match('#^https?://#i', $adminPhoto))
    ? $adminPhoto
    : defaultAvatarUrl();

// Determine which content to load
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="../logo.jpg" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f7f9;
            color: #333;
        }

        header {
            background-color: #2c7a7b;
            color: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .header-left p {
            margin: 5px 0 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

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

        .admin-info i {
            font-size: 22px;
        }

        .admin-info span {
            font-weight: 500;
            color: white;
        }

        .admin-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.6);
            display: inline-block;
            vertical-align: middle;
            background: #f4f7f9;
            transition: border-color 0.25s ease;
        }

        .admin-info:hover .admin-avatar {
            border-color: rgba(255, 255, 255, 0.95);
        }

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

        .container {
            display: flex;
            flex-grow: 1;
        }

        .sidebar {
            background-color: #1e293b;
            width: 280px;
            padding: 20px 0;
            box-sizing: border-box;
            transition: all 0.3s ease;
            color: #fff;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar li {
            margin-bottom: 5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            text-decoration: none;
            color: #cbd5e1;
            border-radius: 0;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .sidebar a i {
            width: 20px;
            font-size: 16px;
        }

        .sidebar a:hover {
            background-color: #334155;
            color: white;
        }

        .sidebar a.active {
            background-color: #2c7a7b;
            color: white;
            border-left: 3px solid #fff;
        }

        .sidebar .refresh-link {
            margin-top: 20px;
            border-top: 1px solid #334155;
            padding-top: 15px;
        }

        .sidebar .refresh-link a {
            color: #f87171;
        }

        .sidebar .refresh-link a:hover {
            background-color: rgba(248, 113, 113, 0.1);
        }

        main {
            flex-grow: 1;
            padding: 25px;
            box-sizing: border-box;
            background-color: #f4f7f9;
        }

        .main-content {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            min-height: calc(100vh - 150px);
        }

        /* ---------- Notification bell ---------- */
        .notif-wrap {
            position: relative;
            display: inline-flex;
        }

        .notif-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
            width: 42px;
            height: 42px;
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

        .notif-btn.has-new {
            animation: notifShake 0.6s ease;
        }

        @keyframes notifShake {
            0%, 100% { transform: rotate(0deg); }
            20%      { transform: rotate(-12deg); }
            40%      { transform: rotate(12deg); }
            60%      { transform: rotate(-8deg); }
            80%      { transform: rotate(8deg); }
        }

        .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
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

        .notif-badge.show {
            display: inline-flex;
        }

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

        .notif-panel-body {
            max-height: 380px;
            overflow-y: auto;
        }

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
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .notif-item-icon.registration {
            background: #fef3c7;
            color: #92400e;
        }

        .notif-item-icon.candidacy {
            background: #e6f4f4;
            color: #2c7a7b;
        }

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

        /* Mobile */
        @media (max-width: 768px) {
            header {
                padding: 12px 20px;
            }

            .header-left h1 {
                font-size: 1.2rem;
            }

            .menu-toggle {
                display: block;
            }

            .header-right {
                display: none;
            }

            .header-right.show {
                display: flex;
                flex-direction: column;
                width: 100%;
                margin-top: 15px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
                padding-top: 15px;
                align-items: stretch;
            }

            .admin-info, .logout-btn {
                width: 100%;
                justify-content: center;
            }

            .notif-wrap {
                width: 100%;
                justify-content: center;
                position: relative;
            }

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

            .notif-panel.open {
                display: block;
            }

            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                max-height: 0;
                overflow: hidden;
                padding: 0;
                transition: max-height 0.3s ease-out;
            }

            .sidebar.open {
                max-height: 500px;
                padding: 20px 0;
            }

            main {
                padding: 15px;
            }

            .main-content {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .sidebar a {
                padding: 10px 15px;
                font-size: 13px;
            }
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
            <ul>
                <li><a href="main.php?page=home" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="main.php?page=voters" class="<?php echo $currentPage === 'voters' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Manage Voters</a></li>
                <li><a href="main.php?page=users" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Manage All Users</a></li>
                <li><a href="main.php?page=votes" class="<?php echo $currentPage === 'votes' ? 'active' : ''; ?>"><i class="fas fa-vote-yea"></i> Manage Votes</a></li>
                <li><a href="main.php?page=candidates" class="<?php echo $currentPage === 'candidates' ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> Manage Candidates</a></li>
                <li><a href="main.php?page=elections" class="<?php echo $currentPage === 'elections' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Manage Elections</a></li>
                <li><a href="main.php?page=posts" class="<?php echo $currentPage === 'posts' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Manage Posts</a></li>
                <li><a href="main.php?page=settings" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>"><i class="fas fa-cogs"></i> Voting Settings</a></li>
                <li><a href="main.php?page=results" class="<?php echo $currentPage === 'results' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> View Results</a></li>
                <li><a href="main.php?page=profile" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            </ul>
            <ul class="refresh-link">
                <li><a href="main.php?page=refreshdb" class="<?php echo $currentPage === 'refreshdb' ? 'active' : ''; ?>"><i class="fas fa-sync-alt"></i> Refresh Vote</a></li>
            </ul>
        </aside>

        <main>
            <div class="main-content">
                <?php
                switch ($page) {
                    case 'users':
                        include("manage_users.php");
                        break;
                    case 'voters':
                        include("manage_election_voters.php");
                        break;
                    case 'votes':
                        include("manage_votes.php");
                        break;
                    case 'candidates':
                        include("manage_candidates.php");
                        break;
                    case 'elections':
                        include("manage_elections.php");
                        break;
                    case 'posts':
                        include("admin_manage_posts.php");
                        break;
                    case 'settings':
                        include("voting_settings.php");
                        break;
                    case 'results':
                        include("view_results.php");
                        break;
                    case 'profile':
                        include("admin_settings.php");
                        break;
                    case 'refreshdb':
                        include("refreshdb.php");
                        break;
                    case 'home':
                    default:
                        include("admin.php");
                        break;
                }
                ?>
            </div>
        </main>
    </div>

    <script>
        // ────────────────────────────────────────────────────────────
        // Hamburger menu toggle
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

            // Track state so we can animate on new activity
            let lastTotal = -1;
            let lastSeenTotal = 0;

            // Restore "read" state from previous session
            try {
                lastSeenTotal = parseInt(localStorage.getItem('notif_seen_total') || '0', 10) || 0;
            } catch (e) { lastSeenTotal = 0; }

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

                    // Shake the bell when the total goes UP
                    if (lastTotal >= 0 && data.total > lastTotal) {
                        btn.classList.add('has-new');
                        setTimeout(() => btn.classList.remove('has-new'), 700);
                    }
                    lastTotal = data.total;

                } catch (err) {
                    // Silent — next tick will retry
                }
            }

            // Toggle panel on bell click
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const willOpen = !panel.classList.contains('open');
                panel.classList.toggle('open', willOpen);

                if (willOpen) {
                    // Refresh on open so counts are always up to date
                    pollOnce();
                }
            });

            // Mark all read
            document.getElementById('notifMarkRead')?.addEventListener('click', function(e) {
                e.stopPropagation();
                lastSeenTotal = lastTotal >= 0 ? lastTotal : 0;
                try { localStorage.setItem('notif_seen_total', String(lastSeenTotal)); } catch (err) {}
                renderBadge(lastSeenTotal);
                panel.classList.remove('open');
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!panel.classList.contains('open')) return;
                if (!panel.contains(e.target) && !btn.contains(e.target)) {
                    panel.classList.remove('open');
                }
            });

            // Close on Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') panel.classList.remove('open');
            });

            // Start polling
            pollOnce();
            setInterval(pollOnce, POLL_INTERVAL_MS);

            // Catch up when the user returns to the tab
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') pollOnce();
            });
        })();
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>
