<?php
// header.php - Common header for all pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cloudinary helpers
require_once __DIR__ . '/cloudinary.php';

// Get user info for display
$userName = $_SESSION['username'] ?? 'Guest';
$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Resolve the user's avatar (Cloudinary URL or system default)
$headerAvatarUrl = defaultAvatarUrl();
if ($isLoggedIn && !empty($_SESSION['profile_photo'])) {
    $sessionPhoto = $_SESSION['profile_photo'];
    if (preg_match('#^(https?://|data:image/)#i', $sessionPhoto)) {
        $headerAvatarUrl = $sessionPhoto;
    }
}

// Determine current page filename for active link detection
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper to output the active class when the link matches the current page
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

        /* ---------- Base ---------- */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: 80px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* ---------- Light theme ---------- */
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

        body.light-theme .navbar .title h1 { color: #1f2937; }
        body.light-theme .navbar .title h1 i { color: #2c7a7b; }

        body.light-theme .navbar a {
            color: #4b5563;
        }

        body.light-theme .navbar a:hover {
            background-color: #f3f4f6;
            color: #2c7a7b;
        }

        body.light-theme .navbar a.active {
            background-color: #e6f4f4;
            color: #2c7a7b;
            font-weight: 600;
        }

        body.light-theme .nav-avatar {
            border-color: #2c7a7b;
        }

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

        body.light-theme .logout-link:hover {
            background: #fee2e2;
        }

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

        body.light-theme .user-info:hover {
            background: #d1ebeb;
        }

        /* ---------- Dark theme ---------- */
        body.dark-theme {
            background-color: #1e293b;
            color: #f3f4f6;
        }

        body.dark-theme .navbar {
            background-color: #0f172a;
            color: #f3f4f6;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }

        body.dark-theme .navbar .title h1 { color: #f3f4f6; }
        body.dark-theme .navbar .title h1 i { color: #2c7a7b; }

        body.dark-theme .navbar a {
            color: #cbd5e1;
        }

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

        body.dark-theme .help-link:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        body.dark-theme .logout-link {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fecaca;
        }

        body.dark-theme .logout-link:hover {
            background: rgba(220, 38, 38, 0.35);
        }

        body.dark-theme .theme-toggle {
            background: rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
        }

        body.dark-theme .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        body.dark-theme .user-info {
            background: rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
        }

        body.dark-theme .user-info:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        /* ---------- Navbar layout ---------- */
        .navbar {
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar .title h1 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .navbar .title h1 i { font-size: 1.5rem; }

        .navbar .links {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }

        .navbar a {
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .nav-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid;
            display: inline-block;
            vertical-align: middle;
        }

        .theme-toggle {
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-family: inherit;
            white-space: nowrap;
        }

        .user-info {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 1024px) {
            .navbar .title h1 { font-size: 1.1rem; }
            .navbar a, .theme-toggle { padding: 6px 10px; font-size: 13px; }
        }

        @media (max-width: 768px) {
            body { padding-top: 130px; }
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
                padding: 12px 20px;
            }
            .navbar .links {
                justify-content: center;
                width: 100%;
            }
            .navbar a, .theme-toggle { padding: 8px 12px; font-size: 12px; }
            .user-info { margin-left: 0; }
        }

        @media (max-width: 600px) {
            body { padding-top: 145px; }
            .navbar .links { gap: 6px; }
            .navbar a span:not(.user-info span),
            .theme-toggle span { display: none; }
            .navbar a i, .theme-toggle i { margin: 0; font-size: 16px; }
            .user-info span { display: inline; font-size: 12px; }
        }

        @media (max-width: 480px) {
            body { padding-top: 160px; }
            .navbar { padding: 10px 15px; }
            .navbar .title h1 { font-size: 1rem; }
            .navbar .links { gap: 5px; }
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
            <div class="title">
                <h1>
                    <i class="fas fa-vote-yea"></i>
                    <span>Witty Voting System</span>
                </h1>
            </div>

            <?php if ($isLoggedIn): ?>
                <div class="links">
                    <a href="profile.php" class="user-info<?php echo navActive('profile.php', $currentPage); ?>">
                        <img src="<?php echo htmlspecialchars($headerAvatarUrl); ?>" alt="Avatar" class="nav-avatar">
                        <span><?php echo htmlspecialchars($userName); ?></span>
                    </a>
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

                    <button id="themeToggle" class="theme-toggle" type="button">
                        <i class="fas fa-moon"></i>
                        <span>Dark</span>
                    </button>

                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="links">
                    <a href="index.php" class="<?php echo trim(navActive('index.php', $currentPage)); ?>">
                        <i class="fas fa-chart-bar"></i> <span>Results</span>
                    </a>
                    <a href="help.php" class="help-link<?php echo navActive('help.php', $currentPage); ?>">
                        <i class="fas fa-question-circle"></i> <span>Help</span>
                    </a>

                    <button id="themeToggle" class="theme-toggle" type="button">
                        <i class="fas fa-moon"></i>
                        <span>Dark</span>
                    </button>

                    <a href="login.php" class="<?php echo trim(navActive('login.php', $currentPage)); ?>" style="background-color: #2c7a7b; color: white;">
                        <i class="fas fa-sign-in-alt"></i> <span>Login</span>
                    </a>
                </div>
            <?php endif; ?>
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
