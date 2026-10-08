 </div> 

    <footer class="site-footer">
        <div>
            <ul class="footer-links">
                <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                    <li><a href="index.php"><i class="fas fa-chart-bar"></i> Results</a></li>
                    <li><a href="vote.php"><i class="fas fa-check-circle"></i> Vote</a></li>
                    <li><a href="apply.php"><i class="fas fa-user-plus"></i> Apply</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <?php else: ?>
                    <li><a href="index.php"><i class="fas fa-chart-bar"></i> Results</a></li>
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                <?php endif; ?>
                <li><a href="help.php"><i class="fas fa-question-circle"></i> Help</a></li>
            </ul>
        </div>
        <div class="footer-copy">&copy; <?php echo date("Y"); ?> Witty Voting Management System. All rights reserved.</div>
        <div class="footer-tagline">
            <i class="fas fa-shield-alt"></i> Secure Voting Platform
        </div>
    </footer>

    <style>
        .site-footer {
            padding: 25px 20px;
            text-align: center;
            margin-top: 40px;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body.light-theme .site-footer {
            background-color: #ffffff;
            color: #4b5563;
            border-top: 1px solid #e5e7eb;
        }

        body.dark-theme .site-footer {
            background-color: #0f172a;
            color: #cbd5e1;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            list-style: none;
            padding: 0;
            margin: 0 0 12px 0;
            gap: 8px 20px;
        }

        .footer-links li { margin: 0; }

        .footer-links a {
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s ease;
        }

        body.light-theme .footer-links a { color: #4b5563; }
        body.light-theme .footer-links a:hover { color: #2c7a7b; }

        body.dark-theme .footer-links a { color: #cbd5e1; }
        body.dark-theme .footer-links a:hover { color: #ffffff; }

        .footer-copy {
            font-size: 13px;
            margin-bottom: 8px;
        }

        .footer-tagline {
            font-size: 12px;
            opacity: 0.7;
        }

        body.light-theme .footer-links a i { color: #2c7a7b; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('voting_theme') || 'light';
            if (!document.body.classList.contains(savedTheme + '-theme')) {
                document.body.classList.add(savedTheme + '-theme');
            }
        });
    </script>
</body>
</html>
