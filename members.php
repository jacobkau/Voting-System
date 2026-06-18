<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fix column names to match your actual table structure
$sql = "SELECT id, username, name as full_name, email, profile_photo, date as created_at FROM users ORDER BY id";
$stmt = $conn->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Registered Voters</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f4f4f4; 
        }
        
        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar .title h1 { 
            margin: 0; 
            font-size: 1.5rem; 
        }
        
        .navbar .links { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px;
        }
        
        .navbar a { 
            color: white; 
            text-decoration: none; 
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .navbar a:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .members-container { 
            width: 90%;
            max-width: 1200px; 
            margin: 40px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            overflow-x: auto;
        }
        
        .members-container h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .members-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        
        .members-table th, 
        .members-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        
        .members-table th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        
        .members-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .members-table img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }
        
        .election-list {
            margin: 0;
            padding-left: 20px;
        }
        
        .election-list li {
            margin: 5px 0;
        }
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
        }
        
        footer { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            text-align: center; 
            padding: 20px; 
            margin-top: 40px; 
        }
        
        footer ul { 
            list-style: none; 
            padding: 0; 
            margin: 0 0 10px 0;
        }
        
        footer li { 
            display: inline; 
            margin: 0 15px; 
        }
        
        footer a { 
            color: white; 
            text-decoration: none; 
        }
        
        footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) { 
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .members-container { 
                width: 95%;
                padding: 15px;
            }
            .members-table th, 
            .members-table td { 
                padding: 8px;
                font-size: 14px;
            }
            .members-table img {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="title">
                <h1>Online Voting Management System</h1>
            </div>
            <div class="links">
                <a href="index.php">All Votes</a>
                <a href="vote.php">Vote</a>
                <a href="apply.php">Contest</a>
                <a href="contest.php">Contesters</a>
                <a href="members.php" class="active">Reg. Voters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>
    
    <div class="members-container">
        <h1>📋 Registered Voters</h1>
        
        <?php if (empty($users)): ?>
            <div class="no-data">
                <p>No registered users yet.</p>
            </div>
        <?php else: ?>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Profile Photo</th>
                        <th>Date Registered</th>
                        <th>Registered Elections</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                            <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td data-label="Full Name"><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Profile Photo">
                                <?php 
                                // Handle profile photo (check BLOB first, then file)
                                if (!empty($user['profile_photo']) && file_exists('uploads/' . $user['profile_photo'])): 
                                ?>
                                    <img src="uploads/<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo">
                                <?php elseif (!empty($user['profile_photo_blob'])): ?>
                                    <img src="data:image/<?php echo $user['profile_photo_type']; ?>;base64,<?php echo base64_encode($user['profile_photo_blob']); ?>" alt="Profile Photo">
                                <?php else: ?>
                                    <img src="default-avatar.png" alt="Default Avatar">
                                <?php endif; ?>
                            </td>
                            <td data-label="Date Registered">
                                <?php 
                                if (!empty($user['created_at'])) {
                                    echo date('Y-m-d H:i', strtotime($user['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td data-label="Registered Elections">
                                <?php
                                // Fetch registered elections for the user using PDO
                                try {
                                    $electionsStmt = $conn->prepare("SELECT e.title FROM elections e JOIN user_elections ue ON e.id = ue.election_id WHERE ue.user_id = ?");
                                    $electionsStmt->execute([$user['id']]);
                                    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($elections) > 0) {
                                        echo "<ul class='election-list'>";
                                        foreach ($elections as $election) {
                                            echo "<li>" . htmlspecialchars($election['title']) . "</li>";
                                        }
                                        echo "</ul>";
                                    } else {
                                        echo "<span style='color: #999;'>No elections registered</span>";
                                    }
                                } catch (PDOException $e) {
                                    error_log("Error fetching user elections: " . $e->getMessage());
                                    echo "<span style='color: red;'>Error loading elections</span>";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer>
        <div>
            <h3>Faster links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="vote.php">Vote</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Log out</a></li>
            </ul>
        </div>
        <div>
            &copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

<?php
$stmt = null;
?>
