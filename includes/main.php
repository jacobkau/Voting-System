<?php
include("conn.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Get admin name
$adminName = "";
try {
    $stmt = $conn->prepare("SELECT name FROM admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $adminName = $admin ? $admin['name'] : $_SESSION['username'] ?? 'Admin';
} catch (PDOException $e) {
    $adminName = $_SESSION['username'] ?? 'Admin';
}

// Determine which content to load
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Determine the current page for active link
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';
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

        /* Header Styles */
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            gap: 20px;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 30px;
        }

        .admin-info i {
            font-size: 18px;
        }

        .admin-info span {
            font-weight: 500;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
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
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        /* Hamburger Menu Button */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 5px;
        }

        /* Container */
        .container {
            display: flex;
            flex-grow: 1;
        }

        /* Sidebar Styles */
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        /* Main Content */
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

        /* Mobile Styles */
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
                border-top: 1px solid rgba(255,255,255,0.2);
                padding-top: 15px;
            }

            .admin-info, .logout-btn {
                width: 100%;
                justify-content: center;
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

        /* Small screens */
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
            <h1><i class="fas fa-vote-yea"></i>Witty Voting System Admin</h1>
            <p>Manage your elections and voters</p>
        </div>
        
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="header-right" id="headerRight">
            <div class="admin-info">
                <i class="fas fa-user-circle"></i>
                <span>Welcome, <?php echo htmlspecialchars($adminName); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <ul>
                <li><a href="main.php?page=home" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="main.php?page=users" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Manage Voters</a></li>
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
                // Include the appropriate content file
                switch ($page) {
                    case 'users':
                        include("manage_users.php");
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
        // Hamburger menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const headerRight = document.getElementById('headerRight');

        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                headerRight.classList.toggle('show');
            });
        }

        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isMobile = window.innerWidth <= 768;
            if (isMobile && sidebar && menuToggle) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                    headerRight.classList.remove('show');
                }
            }
        });

        // Handle window resize - reset open state when switching to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                headerRight.classList.remove('show');
            }
        });
    </script>
</body>
</html>
