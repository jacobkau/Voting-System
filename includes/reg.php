<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Map $conn to $db if your conn.php file sets up the connection variable as $db
if (!isset($conn) && isset($db)) {
    $conn = $db;
}

// Load the invite code from environment (Render dashboard)
$ADMIN_INVITE_CODE = getenv('ADMIN_INVITE_CODE');

// Safety: if the env var isn't set, refuse to accept any registration
if (empty($ADMIN_INVITE_CODE)) {
    error_log("ADMIN_INVITE_CODE is not set — admin registration is disabled.");
    $error = "Registration is temporarily disabled. Please contact the site administrator.";
    $registrationDisabled = true;
} else {
    $registrationDisabled = false;
}

$error = $error ?? "";
$success = "";

if (!$registrationDisabled && $_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrf();
    $invite_code = trim($_POST["invite_code"] ?? "");
    $username    = trim($_POST["username"]);
    $name        = trim($_POST["name"]);
    $email       = trim($_POST["email"]);
    $password    = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (empty($invite_code)) {
        $error = "Invite code is required.";
    } elseif (!hash_equals($ADMIN_INVITE_CODE, $invite_code)) {
        // hash_equals prevents timing attacks
        $error = "Invalid invite code.";
    } elseif (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = "Username must be 3–30 characters and contain only letters, numbers, and underscores.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            // Check for duplicates in the admin table
            $check_query = "SELECT COUNT(*) FROM admin WHERE username = :username OR email = :email";
            $stmt = $conn->prepare($check_query);
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email
            ]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $error = "Username or email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert_query = "INSERT INTO admin (username, name, email, password) VALUES (:username, :name, :email, :password)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->execute([
                    ':username' => $username,
                    ':name'     => $name,
                    ':email'    => $email,
                    ':password' => $hashed_password
                ]);

                $success = "Registration successful! Redirecting to login…";
                header("refresh:2;url=admin_login.php");
            }
        } catch (PDOException $e) {
            error_log("Admin registration DB error: " . $e->getMessage());
            $error = "An error occurred during registration. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="../logo.jpg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            background-color: #2c7a7b;
        }

        .registration-container {
            background-color: white;
            padding: 35px 30px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 500px;
            max-width: 100%;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .registration-container h2 {
            text-align: center;
            margin: 0 0 8px 0;
            color: #2c7a7b;
            font-size: 26px;
            font-weight: 700;
        }

        .registration-container .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 25px;
        }

        .registration-container label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }

        .registration-container input[type="text"],
        .registration-container input[type="email"],
        .registration-container input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            margin-bottom: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .registration-container input:focus {
            outline: none;
            border-color: #2c7a7b;
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.15);
        }

        /* Invite code field gets a subtle highlight */
        .invite-group {
            padding: 15px;
            background: #f0fdfa;
            border: 1px dashed #2c7a7b;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .invite-group label {
            color: #2c7a7b;
        }

        .invite-group label i {
            margin-right: 6px;
        }

        .invite-group input {
            background: white;
        }

        .invite-note {
            font-size: 12px;
            color: #6b7280;
            margin-top: -8px;
            margin-bottom: 0;
        }

        .registration-container input[type="submit"] {
            background-color: #2c7a7b;
            color: white;
            padding: 14px 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            margin-top: 5px;
        }

        .registration-container input[type="submit"]:hover:not(:disabled) {
            background-color: #236162;
            transform: translateY(-1px);
            box-shadow: 0 5px 20px rgba(44, 122, 123, 0.3);
        }

        .registration-container input[type="submit"]:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc2626;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #28a745;
        }

        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-links a {
            color: #2c7a7b;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: #236162;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .registration-container { padding: 25px 20px; }
            .registration-container h2 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <h2>Admin Registration</h2>
        <p class="subtitle">Registration requires an invite code</p>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" action="reg.php">
             <?= csrfField() ?>
            <div class="invite-group">
                <label for="invite_code"><i class="fas fa-key"></i> Invite Code</label>
                <input type="text" name="invite_code" id="invite_code" required
                       autocomplete="off" placeholder="Enter the admin invite code"
                       <?php echo $registrationDisabled ? 'disabled' : ''; ?>>
                <p class="invite-note">Provided by an existing administrator.</p>
            </div>

            <label for="username">Username</label>
            <input type="text" name="username" id="username" required
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   <?php echo $registrationDisabled ? 'disabled' : ''; ?>>

            <label for="name">Name</label>
            <input type="text" name="name" id="name" required
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                   <?php echo $registrationDisabled ? 'disabled' : ''; ?>>

            <label for="email">Email</label>
            <input type="email" name="email" id="email" required
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   <?php echo $registrationDisabled ? 'disabled' : ''; ?>>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required minlength="8"
                   <?php echo $registrationDisabled ? 'disabled' : ''; ?>>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                   <?php echo $registrationDisabled ? 'disabled' : ''; ?>>

            <input type="submit" value="Register"
                   <?php echo $registrationDisabled ? 'disabled' : ''; ?>>
        </form>

        <div class="footer-links">
            <a href="admin_login.php">Already have an account? Login</a>
        </div>
    </div>
</body>
</html>
