</div>

<footer class="site-footer">
    <div class="footer-grid">
        <!-- LEFT: brand -->
        <div class="footer-brand">
            <span class="footer-brand-name">Witty Voting System</span>
            <span class="footer-brand-tag"><i class="fas fa-shield-alt"></i> Secure Voting Platform</span>
        </div>

        <!-- CENTER: quick links -->
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

        <!-- RIGHT: copyright -->
        <div class="footer-copy">
            &copy; <?php echo date("Y"); ?> Witty Voting Management System
        </div>
    </div>
</footer>

<style>
    .site-footer {
        padding: 18px 24px;
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
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    /* 3-column single-row grid */
    .footer-grid {
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 24px;
    }

    /* LEFT: brand block */
    .footer-brand {
        display: flex;
        flex-direction: column;
        gap: 2px;
        text-align: left;
    }

    .footer-brand-name {
        font-size: 14px;
        font-weight: 700;
    }

    body.light-theme .footer-brand-name { color: #1f2937; }
    body.dark-theme .footer-brand-name { color: #f3f4f6; }

    .footer-brand-tag {
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        opacity: 0.75;
    }

    .footer-brand-tag i {
        color: #2c7a7b;
        font-size: 11px;
    }

    /* CENTER: nav links */
    .footer-links {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 4px 18px;
    }

    .footer-links li { margin: 0; }

    .footer-links a {
        text-decoration: none;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: color 0.2s ease;
        white-space: nowrap;
    }

    body.light-theme .footer-links a { color: #4b5563; }
    body.light-theme .footer-links a:hover { color: #2c7a7b; }

    body.dark-theme .footer-links a { color: #cbd5e1; }
    body.dark-theme .footer-links a:hover { color: #ffffff; }

    body.light-theme .footer-links a i { color: #2c7a7b; }
    body.dark-theme .footer-links a i { color: #2c7a7b; }

    /* RIGHT: copyright */
    .footer-copy {
        font-size: 12px;
        text-align: right;
        opacity: 0.75;
        white-space: nowrap;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 14px;
            text-align: center;
        }
        .footer-brand {
            align-items: center;
            text-align: center;
        }
        .footer-copy { text-align: center; }
    }

    @media (max-width: 600px) {
        .site-footer { padding: 16px 18px; }
        .footer-links { gap: 4px 14px; }
        .footer-links a { font-size: 12px; }
        .footer-brand-name { font-size: 13px; }
        .footer-brand-tag { font-size: 11px; }
        .footer-copy { font-size: 11px; white-space: normal; }
    }
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
