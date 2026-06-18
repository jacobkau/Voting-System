<?php
// footer.php - Common footer for all pages
?>
    </div> <!-- Close main-content div -->
    
    <footer style="background: rgba(0,0,0,0.2); padding: 20px 0; text-align: center; color: white; margin-top: 40px; backdrop-filter: blur(10px);">
        <div>
            <ul style="display: flex; flex-wrap: wrap; justify-content: center; list-style: none; padding: 0; margin: 0 0 10px 0;">
                <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                    <li style="margin: 0 15px;"><a href="index.php" style="text-decoration: none; color: white;"><i class="fas fa-chart-bar"></i> Results</a></li>
                    <li style="margin: 0 15px;"><a href="vote.php" style="text-decoration: none; color: white;"><i class="fas fa-check-circle"></i> Vote</a></li>
                    <li style="margin: 0 15px;"><a href="apply.php" style="text-decoration: none; color: white;"><i class="fas fa-user-plus"></i> Apply</a></li>
                    <li style="margin: 0 15px;"><a href="profile.php" style="text-decoration: none; color: white;"><i class="fas fa-user"></i> Profile</a></li>
                <?php else: ?>
                    <li style="margin: 0 15px;"><a href="index.php" style="text-decoration: none; color: white;"><i class="fas fa-chart-bar"></i> Results</a></li>
                    <li style="margin: 0 15px;"><a href="login.php" style="text-decoration: none; color: white;"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li style="margin: 0 15px;"><a href="register.php" style="text-decoration: none; color: white;"><i class="fas fa-user-plus"></i> Register</a></li>
                <?php endif; ?>
                <li style="margin: 0 15px;"><a href="help.php" style="text-decoration: none; color: white;"><i class="fas fa-question-circle"></i> Help</a></li>
            </ul>
        </div>
        <div>&copy; <?php echo date("Y"); ?> Witty Voting Management System. All rights reserved.</div>
        <div style="font-size: 12px; margin-top: 10px; opacity: 0.7;">
            <i class="fas fa-shield-alt"></i> Secure Voting Platform
        </div>
    </footer>
    
    <script>
        // Apply theme to all dynamic content
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('voting_theme');
            if (savedTheme && !document.body.classList.contains(savedTheme + '-theme')) {
                document.body.classList.add(savedTheme + '-theme');
            }
        });
    </script>
</body>
</html>
