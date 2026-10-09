<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    include __DIR__ . '/conn.php';
}

// Cloudinary helpers
require_once __DIR__ . '/cloudinary.php';

// Get user info for display
$userName = $_SESSION['username'] ?? 'Guest';
$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Resolve the user's avatar
$headerAvatarUrl = defaultAvatarUrl();

if ($isLoggedIn) {
    $resolvedAvatar = null;

    // 1. DB lookup 
    if (!empty($_SESSION['user_id']) && isset($conn)) {
        try {
            $avatarStmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $avatarStmt->execute([$_SESSION['user_id']]);
            $avatarRow = $avatarStmt->fetch(PDO::FETCH_ASSOC);

            if ($avatarRow && !empty($avatarRow['profile_photo']) && preg_match('#^https?://#i', $avatarRow['profile_photo'])) {
                $resolvedAvatar = $avatarRow['profile_photo'];
                $_SESSION['profile_photo'] = $resolvedAvatar;
            }
        } catch (PDOException $e) {
            error_log("Header avatar fetch error: " . $e->getMessage());
        }
    }

    // 2. Session fallback
    if (empty($resolvedAvatar) && !empty($_SESSION['profile_photo'])) {
        $sessionPhoto = $_SESSION['profile_photo'];
        if (preg_match('#^https?://#i', $sessionPhoto)) {
            $resolvedAvatar = $sessionPhoto;
        }
    }

    if (!empty($resolvedAvatar)) {
        $headerAvatarUrl = $resolvedAvatar;
    }
}

// logo URL
$logoUrl = getenv('SYSTEM_LOGO_URL');
if (empty($logoUrl) || !preg_match('#^https?://#i', $logoUrl)) {
    $logoUrl = '';
}

// Determine current page filename for active link detection
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper to output the active class
function navActive($file, $currentPage) {
    return $file === $currentPage ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: 76px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.light-theme {
            background-color: #f4f7f9;
            color: #1f2937;
        }

        body.light-theme .navbar {
            background-color: #ffffff;
            color: #1f2937;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            border-bottom: 1px solid #e5e7eb;
        }

        body.light-theme .navbar .brand-name { color: #1f2937; }
        body.light-theme .navbar .brand-icon { color: #2c7a7b; }

        body.light-theme .navbar a { color: #4b5563; }

        body.light-theme .navbar a:hover {
            background-color: #f3f4f6;
            color: #2c7a7b;
        }

        body.light-theme .navbar a.active {
            background-color: #e6f4f4;
            color: #2c7a7b;
            font-weight: 600;
        }

        body.light-theme .nav-avatar { border-color: #2c7a7b; }

        body.light-theme .help-link {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            color: #4b5563;
        }

        body.light-theme .help-link:hover {
            background: #f3f4f6;
            color: #2c7a7b;
        }

        body.light-theme .logout-link {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        body.light-theme .logout-link:hover { background: #fee2e2; }

        body.light-theme .theme-toggle {
            background: #f3f4f6;
            color: #4b5563;
        }

        body.light-theme .theme-toggle:hover {
            background: #e5e7eb;
            color: #2c7a7b;
        }

        body.light-theme .user-info {
            background: #e6f4f4;
            color: #2c7a7b;
        }

        body.light-theme .user-info:hover { background: #d1ebeb; }

        body.light-theme .nav-divider {
            background: #e5e7eb;
        }

        body.dark-theme {
            background-color: #1e293b;
            color: #f3f4f6;
        }

        body.dark-theme .navbar {
            background-color: #0f172a;
            color: #f3f4f6;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }

        body.dark-theme .navbar .brand-name { color: #f3f4f6; }
        body.dark-theme .navbar .brand-icon { color: #2c7a7b; }

        body.dark-theme .navbar a { color: #cbd5e1; }

        body.dark-theme .navbar a:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        body.dark-theme .navbar a.active {
            background-color: rgba(44, 122, 123, 0.3);
            color: #ffffff;
            font-weight: 600;
        }

        body.dark-theme .nav-avatar {
            border-color: rgba(255, 255, 255, 0.6);
        }

        body.dark-theme .help-link {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        body.dark-theme .help-link:hover { background: rgba(255, 255, 255, 0.15); }

        body.dark-theme .logout-link {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fecaca;
        }

        body.dark-theme .logout-link:hover { background: rgba(220, 38, 38, 0.35); }

        body.dark-theme .theme-toggle {
            background: rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
        }

        body.dark-theme .theme-toggle:hover { background: rgba(255, 255, 255, 0.2); }

        body.dark-theme .user-info {
            background: rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
        }

        body.dark-theme .user-info:hover { background: rgba(255, 255, 255, 0.18); }

        body.dark-theme .nav-divider {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar {
            padding: 12px 30px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .navbar .brand-logo {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            object-fit: cover;
            background: #ffffff;
            display: block;
        }

        .navbar .brand-icon {
            font-size: 1.6rem;
            display: none; 
        }

        .navbar .brand-name {
            font-size: 1.05rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .navbar .nav-center {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .navbar .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .navbar a,
        .navbar .theme-toggle,
        .navbar .user-info {
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
            font-family: inherit;
            border: none;
            cursor: pointer;
        }

        .nav-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid;
            display: inline-block;
            vertical-align: middle;
            background: #f4f7f9;
        }

        .user-info {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .nav-divider {
            width: 1px;
            height: 24px;
            margin: 0 2px;
            flex-shrink: 0;
        }

        @media (max-width: 1100px) {
            .navbar { padding: 10px 20px; gap: 12px; }
            .navbar .brand-name { font-size: 0.95rem; }
            .navbar a,
            .navbar .theme-toggle,
            .navbar .user-info { padding: 7px 10px; font-size: 12px; }
        }

        @media (max-width: 900px) {
            .navbar .brand-name { display: none; }
            .navbar .nav-center a span { display: none; }
            .navbar .nav-center a { padding: 8px 10px; }
            .navbar .nav-center a i { margin: 0; font-size: 15px; }
        }

        @media (max-width: 768px) {
            body { padding-top: 130px; }

            .navbar {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
                gap: 10px;
                padding: 12px 16px;
            }

            .navbar .brand-name { display: inline; font-size: 1rem; }
            .navbar .brand { justify-content: center; }

            .navbar .nav-center {
                width: 100%;
                justify-content: center;
                gap: 4px;
            }

            .navbar .nav-center a span { display: inline; }

            .navbar .nav-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
            }

            .nav-divider { display: none; }
        }

        @media (max-width: 600px) {
            body { padding-top: 150px; }

            .navbar .nav-center a span { display: none; }
            .navbar .nav-center a i { margin: 0; font-size: 15px; }
            .navbar .theme-toggle span { display: none; }
            .navbar .logout-link span { display: none; }
            .navbar .user-info span { display: inline; font-size: 12px; }
        }

        @media (max-width: 480px) {
            body { padding-top: 165px; }
            .navbar { padding: 10px 12px; }
            .navbar .brand-logo { width: 30px; height: 30px; }
            .navbar .brand-name { font-size: 0.9rem; }
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar" id="navbar">
            <a href="index.php" class="brand">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="brand-logo">
                <?php else: ?>
                    <i class="fas fa-vote-yea brand-icon" style="display: inline-block;"></i>
                <?php endif; ?>
                <span class="brand-name">Witty Voting System</span>
            </a>

            <div class="nav-center">
                <?php if ($isLoggedIn): ?>
                    <a href="vote.php" class="<?php echo trim(navActive('vote.php', $currentPage)); ?>">
                        <i class="fas fa-check-circle"></i> <span>Vote</span>
                    </a>
                    <a href="apply.php" class="<?php echo trim(navActive('apply.php', $currentPage)); ?>">
                        <i class="fas fa-user-plus"></i> <span>Candidacy</span>
                    </a>
                    <a href="contest.php" class="<?php echo trim(navActive('contest.php', $currentPage)); ?>">
                        <i class="fas fa-users"></i> <span>Contesters</span>
                    </a>
                    <a href="my_applications.php" class="<?php echo trim(navActive('my_applications.php', $currentPage)); ?>">
                        <i class="fas fa-file-alt"></i> <span>My Apps</span>
                    </a>
                    <a href="index.php" class="<?php echo trim(navActive('index.php', $currentPage)); ?>">
                        <i class="fas fa-chart-bar"></i> <span>Results</span>
                    </a>
                    <a href="help.php" class="help-link<?php echo navActive('help.php', $currentPage); ?>">
                        <i class="fas fa-question-circle"></i> <span>Help</span>
                    </a>
                <?php else: ?>
                    <a href="index.php" class="<?php echo trim(navActive('index.php', $currentPage)); ?>">
                        <i class="fas fa-chart-bar"></i> <span>Results</span>
                    </a>
                    <a href="help.php" class="help-link<?php echo navActive('help.php', $currentPage); ?>">
                        <i class="fas fa-question-circle"></i> <span>Help</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="nav-right">
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="user-info<?php echo navActive('profile.php', $currentPage); ?>">
                        <img src="<?php echo htmlspecialchars($headerAvatarUrl); ?>" alt="Avatar" class="nav-avatar">
                        <span><?php echo htmlspecialchars($userName); ?></span>
                    </a>

                    <span class="nav-divider"></span>

                    <button id="themeToggle" class="theme-toggle" type="button">
                        <i class="fas fa-moon"></i>
                        <span>Dark</span>
                    </button>

                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                <?php else: ?>
                    <button id="themeToggle" class="theme-toggle" type="button">
                        <i class="fas fa-moon"></i>
                        <span>Dark</span>
                    </button>

                    <a href="login.php" class="<?php echo trim(navActive('login.php', $currentPage)); ?>" style="background-color: #2c7a7b; color: white;">
                        <i class="fas fa-sign-in-alt"></i> <span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="main-content">
        <script>
        (function() {
            const savedTheme = localStorage.getItem('voting_theme') || 'light';
            document.body.classList.add(savedTheme + '-theme');

            function updateThemeButton() {
                const isLight = document.body.classList.contains('light-theme');
                const themeBtn = document.getElementById('themeToggle');
                if (themeBtn) {
                    if (isLight) {
                        themeBtn.innerHTML = '<i class="fas fa-moon"></i> <span>Dark</span>';
                    } else {
                        themeBtn.innerHTML = '<i class="fas fa-sun"></i> <span>Light</span>';
                    }
                }
            }

            window.toggleTheme = function() {
                if (document.body.classList.contains('light-theme')) {
                    document.body.classList.remove('light-theme');
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('voting_theme', 'dark');
                } else {
                    document.body.classList.remove('dark-theme');
                    document.body.classList.add('light-theme');
                    localStorage.setItem('voting_theme', 'light');
                }
                updateThemeButton();
            };

            document.addEventListener('DOMContentLoaded', function() {
                updateThemeButton();
                const themeBtn = document.getElementById('themeToggle');
                if (themeBtn) {
                    themeBtn.addEventListener('click', toggleTheme);
                }
            });
        })();
        </script>
