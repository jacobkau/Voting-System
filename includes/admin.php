<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Summary Data
try {
    // Total Elections
    $electionsStmt = $conn->prepare("SELECT COUNT(*) as count FROM elections");
    $electionsStmt->execute();
    $totalElections = $electionsStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total Users
    $usersStmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $usersStmt->execute();
    $totalUsers = $usersStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total Votes
    $votesStmt = $conn->prepare("SELECT COUNT(*) as count FROM votes");
    $votesStmt->execute();
    $totalVotes = $votesStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total Contesters
    $contestersStmt = $conn->prepare("SELECT COUNT(*) as count FROM contesters");
    $contestersStmt->execute();
    $totalContesters = $contestersStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $totalElections = 0;
    $totalUsers = 0;
    $totalVotes = 0;
    $totalContesters = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 90%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        h2 { color: #333; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .dashboard-item { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px; 
            border-radius: 10px; 
            text-align: center;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .dashboard-item:hover {
            transform: translateY(-5px);
        }
        .dashboard-item h3 { 
            margin: 0 0 10px 0; 
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        .dashboard-item p { 
            margin: 0; 
            font-size: 36px; 
            font-weight: bold;
        }
        .dashboard-item:nth-child(1) { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .dashboard-item:nth-child(2) { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .dashboard-item:nth-child(3) { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .dashboard-item:nth-child(4) { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        
        @media (max-width: 768px) { 
            .admin-container { width: 95%; }
            .dashboard-item p { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Admin Dashboard</h2>
        <div class="dashboard-grid">
            <div class="dashboard-item">
                <h3>Total Elections</h3>
                <p><?php echo number_format($totalElections); ?></p>
            </div>
            <div class="dashboard-item">
                <h3>Total Registered Users</h3>
                <p><?php echo number_format($totalUsers); ?></p>
            </div>
            <div class="dashboard-item">
                <h3>Total Votes Cast</h3>
                <p><?php echo number_format($totalVotes); ?></p>
            </div>
            <div class="dashboard-item">
                <h3>Total Candidates</h3>
                <p><?php echo number_format($totalContesters); ?></p>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Close PDO connection by setting to null
$conn = null;
?>
