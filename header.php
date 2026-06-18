<?php
// header.php - Common header for all pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info for display
$userName = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh;
            padding-top: 70px;
            transition: all 0.3s ease;
        }
        
        /* Light Theme */
        body.light-theme {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        body.light-theme .navbar {
            background: rgba(255,255,255,0.95);
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        body.light-theme .navbar a {
            color: #333;
        }
        
        body.light-theme .navbar a:hover {
            background-color: rgba(0,0,0,0.1);
        }
        
        /* Dark Theme */
        body.dark-theme {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        body.dark-theme .navbar {
            background: rgba(0,0,0,0.9);
            color: white;
        }
        
        /* Fixed Navbar */
        .navbar { 
            background: rgba(0,0,0,0.2); 
            color: white; 
            padding: 12px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar .title h1 { 
            margin: 0; 
            font-size: 1.3rem; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar .title h1 i {
            font-size: 1.5rem;
        }
        
        .navbar .links { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 5px; 
            align-items: center; 
        }
        
        .navbar a { 
            color: white; 
            text-decoration: none; 
            padding: 8px 14px; 
            border-radius: 8px; 
            transition: all 0.3s; 
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar a:hover, .navbar a.active { 
            background-color: rgba(255,255,255,0.2); 
            transform: translateY(-2px);
        }
        
        /* Help button special styling */
        .help-link {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .help-link:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        /* Logout button */
        .logout-link {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid rgba(220, 38, 38, 0.3);
        }
        
        .logout-link:hover {
            background: rgba(220, 38, 38, 0.4);
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .theme-toggle:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        body.light-theme .theme-toggle {
            background: rgba(0,0,0,0.1);
            color: #333;
        }
        
        body.light-theme .theme-toggle:hover {
            background: rgba(0,0,0,0.2);
        }
        
        /* User info badge - only shown when logged in */
        .user-info {
            background: rgba(255,255,255,0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 5px;
        }
        
        body.light-theme .user-info {
            background: rgba(0,0,0,0.1);
        }
        
        /* Mobile responsive - Desktop First */
        @media (max-width: 1024px) {
            .navbar .title h1 {
                font-size: 1.1rem;
            }
            .navbar a, .theme-toggle {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 120px;
            }
            
            .navbar { 
                flex-direction: column; 
                text-align: center; 
                gap: 12px; 
                padding: 12px 20px;
            }
            
            .navbar .links {
                justify-content: center;
                width: 100%;
            }
            
            .navbar a, .theme-toggle {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .user-info {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 600px) {
            body {
                padding-top: 140px;
            }
            
            .navbar .links {
                gap: 8px;
            }
            
            .navbar a span, .theme-toggle span {
                display: none;
            }
            
            .navbar a i, .theme-toggle i {
                margin: 0;
                font-size: 16px;
            }
            
            .user-info span {
                display: inline;
                font-size: 12px;
            }
            
            .user-info i {
                display: inline-block;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding-top: 160px;
            }
            
            .navbar {
                padding: 10px 15px;
            }
            
            .navbar .title h1 {
                font-size: 1rem;
            }
            
            .navbar .links {
                gap: 5px;
            }
        }
        
        /* Scroll effect for navbar */
        .navbar.scrolled {
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(5px);
            padding: 8px 30px;
        }
        
        body.light-theme .navbar.scrolled {
            background: rgba(255,255,255,0.98);
        }
        
        @media (max-width: 768px) {
            .navbar.scrolled {
                padding: 8px 20px;
            }
        }
        
        /* Main content container */
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
                <!-- Logged In User - Show all links -->
                <div class="links">
                    <a href="profile.php"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
                    <a href="vote.php"><i class="fas fa-check-circle"></i> <span>Vote</span></a>
                    <a href="apply.php"><i class="fas fa-user-plus"></i> <span>Candidacy</span></a>
                    <a href="contest.php"><i class="fas fa-users"></i> <span>Contesters</span></a>
                    <a href="my_applications.php"><i class="fas fa-file-alt"></i> <span>My Apps</span></a>
                    <a href="index.php"><i class="fas fa-chart-bar"></i> <span>Results</span></a>
                    <a href="help.php" class="help-link"><i class="fas fa-question-circle"></i> <span>Help</span></a>
                    
                    <!-- Theme Toggle Button -->
                    <button id="themeToggle" class="theme-toggle">
                        <i class="fas fa-sun"></i>
                        <span>Light</span>
                    </button>
                    
                    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
                </div>
                
            <?php else: ?>
                <!-- Not Logged In - Show only public links -->
                <div class="links">
                    <a href="index.php"><i class="fas fa-chart-bar"></i> <span>Results</span></a>
                    <a href="help.php" class="help-link"><i class="fas fa-question-circle"></i> <span>Help</span></a>
                    
                    <!-- Theme Toggle Button -->
                    <button id="themeToggle" class="theme-toggle">
                        <i class="fas fa-sun"></i>
                        <span>Light</span>
                    </button>
                    
                    <a href="login.php" style="background: rgba(99, 102, 241, 0.8);"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <div class="main-content">
        <script>
        // Theme management
        (function() {
            // Get saved theme or default to 'dark'
            const savedTheme = localStorage.getItem('voting_theme') || 'dark';
            document.body.classList.add(savedTheme + '-theme');
            
            // Update theme toggle button icon and text
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
            
            // Toggle theme function
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
            
            // Add event listener when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                updateThemeButton();
                const themeBtn = document.getElementById('themeToggle');
                if (themeBtn) {
                    themeBtn.addEventListener('click', toggleTheme);
                }
                
                // Navbar scroll effect
                const navbar = document.getElementById('navbar');
                if (navbar) {
                    window.addEventListener('scroll', function() {
                        if (window.scrollY > 50) {
                            navbar.classList.add('scrolled');
                        } else {
                            navbar.classList.remove('scrolled');
                        }
                    });
                }
            });
        })();
        </script>
