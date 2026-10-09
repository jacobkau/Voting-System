<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

$username = $_SESSION['username'] ?? 'Guest';
$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
?>

<?php include("header.php"); ?>

<style>
    .help-container {
        max-width: 1000px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* Hero — compact and professional */
    .help-hero {
        background-color: #2c7a7b;
        border-radius: 16px;
        padding: 28px 30px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(44, 122, 123, 0.15);
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    body.dark-theme .help-hero {
        background-color: #0f172a;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    .help-hero-icon {
        font-size: 36px;
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .help-hero-text {
        flex: 1;
        min-width: 200px;
    }

    .help-hero-text h1 {
        font-size: 24px;
        margin-bottom: 6px;
        font-weight: 700;
    }

    .help-hero-text p {
        font-size: 14px;
        opacity: 0.9;
        line-height: 1.5;
    }

    .help-hero-badge {
        background: rgba(255, 255, 255, 0.15);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .help-hero-badge i {
        font-size: 12px;
        opacity: 0.85;
    }

    /* Guide sections */
    .guide-section {
        background: #ffffff;
        border-radius: 16px;
        margin-bottom: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    body.dark-theme .guide-section {
        background: #1e1e2e;
        border-color: #3d3d4d;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
    }

    .section-header {
        background: #f9fafb;
        padding: 18px 24px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
    }

    body.dark-theme .section-header { background: #2d2d3d; }

    .section-header:hover {
        background: #f3f4f6;
    }

    body.dark-theme .section-header:hover { background: #3d3d4d; }

    .section-title {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    body.dark-theme .section-title { color: #f3f4f6; }

    .section-title i {
        font-size: 18px;
        color: #2c7a7b;
    }

    .toggle-icon {
        font-size: 14px;
        color: #6b7280;
        transition: transform 0.3s;
    }

    body.dark-theme .toggle-icon { color: #9ca3af; }

    .section-content {
        padding: 0 24px;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .section-content.active {
        padding: 22px 24px 26px;
        max-height: 3000px;
    }

    /* Guide card */
    .guide-card {
        display: flex;
        gap: 18px;
        padding: 18px;
        margin-bottom: 16px;
        background: #f9fafb;
        border-radius: 12px;
        border-left: 3px solid #2c7a7b;
        transition: all 0.2s;
    }

    body.dark-theme .guide-card {
        background: #2d2d3d;
    }

    .guide-card:last-child { margin-bottom: 0; }

    .guide-card:hover {
        background: #f3f4f6;
    }

    body.dark-theme .guide-card:hover { background: #3d3d4d; }

    .guide-icon {
        font-size: 26px;
        min-width: 40px;
        text-align: center;
        color: #2c7a7b;
        padding-top: 2px;
    }

    .guide-content h3 {
        color: #1f2937;
        margin-bottom: 8px;
        font-size: 15px;
        font-weight: 700;
    }

    body.dark-theme .guide-content h3 { color: #f3f4f6; }

    .guide-content p {
        color: #6b7280;
        line-height: 1.6;
        font-size: 14px;
        margin-bottom: 8px;
    }

    body.dark-theme .guide-content p { color: #9ca3af; }

    .guide-content ul {
        list-style: none;
        padding: 0;
        margin: 8px 0 0;
    }

    .guide-content ul li {
        color: #6b7280;
        font-size: 14px;
        padding: 4px 0;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        line-height: 1.5;
    }

    body.dark-theme .guide-content ul li { color: #9ca3af; }

    .guide-content ul li i {
        color: #2c7a7b;
        margin-top: 4px;
        flex-shrink: 0;
        font-size: 12px;
    }

    .step-list {
        list-style: none;
        padding: 0;
    }

    .step-list li {
        padding: 9px 0;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.5;
    }

    body.dark-theme .step-list li {
        border-bottom-color: #3d3d4d;
        color: #9ca3af;
    }

    .step-list li:last-child { border-bottom: none; }

    .step-number {
        background-color: #2c7a7b;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 12px;
        flex-shrink: 0;
    }

    /* Tip / warning boxes */
    .tip-box {
        background: #fef3c7;
        border-left: 3px solid #f59e0b;
        padding: 13px 18px;
        border-radius: 10px;
        margin-top: 12px;
        color: #78350f;
        font-size: 13px;
        line-height: 1.6;
    }

    body.dark-theme .tip-box {
        background: #78350f;
        color: #fde68a;
    }

    .tip-box i {
        color: #f59e0b;
        margin-right: 8px;
    }

    body.dark-theme .tip-box i { color: #fde68a; }

    .warning-box {
        background: #fee2e2;
        border-left: 3px solid #dc2626;
        padding: 13px 18px;
        border-radius: 10px;
        margin-top: 12px;
        color: #7f1d1d;
        font-size: 13px;
        line-height: 1.6;
    }

    body.dark-theme .warning-box {
        background: #7f1d1d;
        color: #fecaca;
    }

    .warning-box i {
        color: #dc2626;
        margin-right: 8px;
    }

    body.dark-theme .warning-box i { color: #fecaca; }

    /* FAQ */
    .faq-item {
        padding: 14px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    body.dark-theme .faq-item { border-bottom-color: #3d3d4d; }

    .faq-item:last-child { border-bottom: none; }

    .faq-question {
        font-weight: 600;
        color: #1f2937;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        user-select: none;
        font-size: 14px;
    }

    body.dark-theme .faq-question { color: #f3f4f6; }

    .faq-question i.fa-chevron-right {
        transition: transform 0.3s;
        font-size: 12px;
        color: #6b7280;
        flex-shrink: 0;
    }

    body.dark-theme .faq-question i.fa-chevron-right { color: #9ca3af; }

    .faq-question .faq-icon {
        color: #2c7a7b;
        margin-right: 8px;
    }

    .faq-answer {
        padding-top: 10px;
        color: #6b7280;
        display: none;
        line-height: 1.6;
        font-size: 13px;
    }

    body.dark-theme .faq-answer { color: #9ca3af; }

    .faq-answer.show { display: block; }

    /* Contact info */
    .contact-list {
        list-style: none;
        padding: 0;
        margin: 10px 0 0;
    }

    .contact-list li {
        padding: 6px 0;
        color: #6b7280;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    body.dark-theme .contact-list li { color: #9ca3af; }

    .contact-list li i {
        color: #2c7a7b;
        width: 18px;
    }

    @media (max-width: 768px) {
        .help-container { padding: 0 15px; margin: 25px auto; }
        .help-hero { padding: 22px 20px; gap: 15px; }
        .help-hero-icon { width: 50px; height: 50px; font-size: 28px; }
        .help-hero-text h1 { font-size: 20px; }
        .help-hero-text p { font-size: 13px; }
        .section-header { padding: 15px 18px; }
        .section-title { font-size: 15px; }
        .section-title i { font-size: 16px; }
        .section-content { padding: 0 18px; }
        .section-content.active { padding: 18px 18px 22px; }
        .guide-card { flex-direction: column; text-align: left; gap: 10px; }
        .guide-icon { text-align: left; }
    }
</style>

<div class="help-container">
    <!-- Hero -->
    <div class="help-hero">
        <div class="help-hero-icon">
            <i class="fas fa-question-circle"></i>
        </div>
        <div class="help-hero-text">
            <h1>Voting System Guide</h1>
            <p>Your complete guide to participating in elections, voting, and managing your profile</p>
        </div>
        <div class="help-hero-badge">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
    </div>

    <!-- 1. Getting Started -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>Getting Started</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Create Your Account</h3>
                    <p>To participate in elections, you need to register an account first. Click on "Register" from the login page and fill in your details.</p>
                    <ul class="step-list" style="margin-top: 10px;">
                        <li><span class="step-number">1</span> Click on "Create New Account" on the login page</li>
                        <li><span class="step-number">2</span> Fill in your personal information (Username, Full Name, Email)</li>
                        <li><span class="step-number">3</span> Upload a profile photo (JPG, PNG, GIF, WEBP - max 2MB)</li>
                        <li><span class="step-number">4</span> Select the elections you want to register for</li>
                        <li><span class="step-number">5</span> Submit your registration and login</li>
                    </ul>
                </div>
            </div>
            <div class="tip-box">
                 <strong>Pro Tip:</strong> Use a clear profile photo so other voters can identify you easily, especially if you're running as a candidate.
            </div>
        </div>
    </div>

    <!-- 2. Voting Guide -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>How to Vote</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Cast Your Vote</h3>
                    <ul class="step-list">
                        <li><span class="step-number">1</span> Navigate to the <strong>Vote</strong> page from the main menu</li>
                        <li><span class="step-number">2</span> Select an active election from the dropdown</li>
                        <li><span class="step-number">3</span> Review all candidates for each position (photos and names are displayed)</li>
                        <li><span class="step-number">4</span> Click on your preferred candidate's card to select them</li>
                        <li><span class="step-number">5</span> Ensure you've voted for all positions</li>
                        <li><span class="step-number">6</span> Click "Submit Vote" to cast your ballot</li>
                    </ul>
                </div>
            </div>
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Important Voting Rules</h3>
                    <ul>
                        <li><i class="fas fa-check"></i> You can only vote once per election</li>
                        <li><i class="fas fa-check"></i> You must be registered for the election before voting</li>
                        <li><i class="fas fa-check"></i> Votes cannot be changed after submission</li>
                        <li><i class="fas fa-check"></i> You must vote for all positions in the election</li>
                    </ul>
                </div>
            </div>
            <div class="warning-box">
               <strong>Important:</strong> Once you submit your vote, it cannot be changed or undone. Make sure you've selected your preferred candidates before confirming.
            </div>
        </div>
    </div>

    <!-- 3. Becoming a Candidate -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>Applying as a Candidate</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-content">
                    <h3>How to Apply for Candidacy</h3>
                    <ul class="step-list">
                        <li><span class="step-number">1</span> Go to the <strong>Candidacy Application</strong> page</li>
                        <li><span class="step-number">2</span> Upload a professional profile photo</li>
                        <li><span class="step-number">3</span> Select the election you want to contest in</li>
                        <li><span class="step-number">4</span> Choose the position you're applying for</li>
                        <li><span class="step-number">5</span> Write a compelling bio and manifesto</li>
                        <li><span class="step-number">6</span> Submit your application for review</li>
                    </ul>
                </div>
            </div>
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Writing an Effective Manifesto</h3>
                    <ul>
                        <li><i class="fas fa-check"></i> State your qualifications and experience</li>
                        <li><i class="fas fa-check"></i> Outline your goals if elected</li>
                        <li><i class="fas fa-check"></i> Be clear, concise, and honest</li>
                        <li><i class="fas fa-check"></i> Highlight what makes you unique</li>
                        <li><i class="fas fa-check"></i> Keep it professional and respectful</li>
                    </ul>
                </div>
            </div>
            <div class="tip-box">
                 <strong>Pro Tip:</strong> You can only apply for ONE position per election. Choose the role that best fits your qualifications.
            </div>
        </div>
    </div>

    <!-- 4. Viewing Results -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>Viewing Election Results</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Accessing Results</h3>
                    <ul class="step-list">
                        <li><span class="step-number">1</span> Click on <strong>Results</strong> from the main menu</li>
                        <li><span class="step-number">2</span> Results are organized by election</li>
                        <li><span class="step-number">3</span> Each position shows all candidates and their vote counts</li>
                        <li><span class="step-number">4</span> Winners are highlighted with a special badge</li>
                        <li><span class="step-number">5</span> Results include vote percentages for easy comparison</li>
                    </ul>
                </div>
            </div>
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Understanding the Results Display</h3>
                    <ul>
                        <li><i class="fas fa-chart-simple"></i> <strong>Vote Count:</strong> Total number of votes received</li>
                        <li><i class="fas fa-percent"></i> <strong>Percentage:</strong> Share of total votes for that position</li>
                        <li><i class="fas fa-crown"></i> <strong>Winner Badge:</strong> Indicates the winning candidate</li>
                        <li><i class="fas fa-user"></i> <strong>Candidate Photos:</strong> Helps identify candidates easily</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Managing Your Profile -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>Managing Your Profile</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Profile Management Features</h3>
                    <ul class="step-list">
                        <li><span class="step-number">1</span> Go to <strong>My Profile</strong> from the menu</li>
                        <li><span class="step-number">2</span> Update your personal information (Name, Email)</li>
                        <li><span class="step-number">3</span> Change your profile photo</li>
                        <li><span class="step-number">4</span> Update your password for security</li>
                        <li><span class="step-number">5</span> View your voting history</li>
                        <li><span class="step-number">6</span> Check your candidacy applications</li>
                    </ul>
                </div>
            </div>
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Tracking Your Activity</h3>
                    <p>The <strong>My Applications</strong> page shows you:</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Which elections you're registered for</li>
                        <li><i class="fas fa-check"></i> Which positions you're contesting</li>
                        <li><i class="fas fa-check"></i> Your application status</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. FAQ -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>Frequently Asked Questions</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle faq-icon"></i> Can I change my vote after submitting?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    No, once you submit your vote, it is permanently recorded and cannot be changed. Make sure you've reviewed your choices before confirming.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle faq-icon"></i> Can I apply for multiple positions in the same election?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    No, you can only apply for ONE position per election. This ensures fair competition and prevents conflicts of interest.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle faq-icon"></i> How do I know if I'm registered for an election?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    Go to the <strong>My Applications</strong> page. It shows all elections you're registered for and any positions you're contesting.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle faq-icon"></i> What happens if I forget my password?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    Click on "Forgot Password" on the login page. A reset link will be sent to your registered email address.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle faq-icon"></i> Are my votes anonymous?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    Votes are recorded with your username for verification purposes, but only administrators can see who voted for whom. The public results only show vote counts per candidate.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle faq-icon"></i> What file formats are accepted for profile photos?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    We accept JPG, JPEG, PNG, GIF, and WEBP formats. Maximum file size is 2MB. For best results, use a clear, well-lit photo.
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Need More Help -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <span>Need Additional Help?</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-content">
                    <h3>Contact Support</h3>
                    <p>If you need further assistance, please contact the system administrator:</p>
                    <ul class="contact-list">
                        <li><i class="fas fa-envelope"></i> Email: <strong>wittyhighbrowtechnologies@gmail.com</strong></li>
                        <li><i class="fas fa-phone"></i> Phone: <strong>+254 768 374 497</strong></li>
                        <li><i class="fas fa-clock"></i> Response Time: Within 24 hours</li>
                    </ul>
                </div>
            </div>
            <div class="tip-box">
                <strong>Tip:</strong> Before contacting support, check this guide and the FAQ section for quick answers to common questions.
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSection(header) {
        const content = header.nextElementSibling;
        const icon = header.querySelector('.toggle-icon');

        content.classList.toggle('active');
        icon.style.transform = content.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
    }

    function toggleFAQ(element) {
        const answer = element.nextElementSibling;
        const icon = element.querySelector('.fa-chevron-right');

        answer.classList.toggle('show');
        icon.style.transform = answer.classList.contains('show') ? 'rotate(90deg)' : 'rotate(0)';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const firstSection = document.querySelector('.guide-section .section-content');
        const firstIcon = document.querySelector('.guide-section .toggle-icon');
        if (firstSection && firstIcon) {
            firstSection.classList.add('active');
            firstIcon.style.transform = 'rotate(180deg)';
        }
    });
</script>

<?php include("footer.php"); ?>
