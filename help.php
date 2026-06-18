<?php
// help.php - User Guide and Help Page
session_start();
include("conn.php");

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
?>

<?php include("header.php"); ?>

<style>
    .help-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    /* Hero Section */
    .help-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 50px;
        text-align: center;
        color: white;
        margin-bottom: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .help-hero h1 {
        font-size: 48px;
        margin-bottom: 15px;
    }
    
    .help-hero p {
        font-size: 18px;
        opacity: 0.95;
    }
    
    .help-hero .welcome-badge {
        background: rgba(255,255,255,0.2);
        display: inline-block;
        padding: 8px 20px;
        border-radius: 30px;
        margin-top: 20px;
        font-size: 14px;
    }
    
    /* Quick Stats */
    .stats-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .stat-box {
        flex: 1;
        min-width: 180px;
        background: white;
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .stat-box:hover {
        transform: translateY(-5px);
    }
    
    .stat-box i {
        font-size: 45px;
        color: #667eea;
        margin-bottom: 10px;
    }
    
    .stat-box .number {
        font-size: 32px;
        font-weight: 800;
        color: #333;
    }
    
    .stat-box .label {
        color: #666;
        font-size: 14px;
        margin-top: 5px;
    }
    
    /* Guide Sections */
    .guide-section {
        background: white;
        border-radius: 20px;
        margin-bottom: 30px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .section-header {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        padding: 20px 30px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .section-header:hover {
        background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    }
    
    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .section-title i {
        font-size: 28px;
        color: #667eea;
    }
    
    .toggle-icon {
        font-size: 20px;
        color: #6b7280;
        transition: transform 0.3s;
    }
    
    .section-content {
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .section-content.active {
        padding: 30px;
        max-height: 2000px;
    }
    
    .guide-card {
        display: flex;
        gap: 20px;
        padding: 20px;
        margin-bottom: 20px;
        background: #f9fafb;
        border-radius: 12px;
        border-left: 4px solid #667eea;
        transition: all 0.3s;
    }
    
    .guide-card:hover {
        background: #f3f4f6;
        transform: translateX(5px);
    }
    
    .guide-icon {
        font-size: 40px;
        min-width: 60px;
        text-align: center;
    }
    
    .guide-content h3 {
        color: #374151;
        margin-bottom: 8px;
        font-size: 18px;
    }
    
    .guide-content p {
        color: #6b7280;
        line-height: 1.6;
    }
    
    .step-list {
        list-style: none;
        padding: 0;
    }
    
    .step-list li {
        padding: 12px 0;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .step-number {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }
    
    .tip-box {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 15px 20px;
        border-radius: 10px;
        margin-top: 15px;
    }
    
    .tip-box i {
        color: #f59e0b;
        margin-right: 10px;
    }
    
    .warning-box {
        background: #fee2e2;
        border-left: 4px solid #dc2626;
        padding: 15px 20px;
        border-radius: 10px;
        margin-top: 15px;
    }
    
    .warning-box i {
        color: #dc2626;
        margin-right: 10px;
    }
    
    .faq-item {
        padding: 15px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .faq-question {
        font-weight: 700;
        color: #1f2937;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .faq-answer {
        padding-top: 10px;
        color: #6b7280;
        display: none;
    }
    
    .faq-answer.show {
        display: block;
    }
    
    @media (max-width: 768px) {
        .help-hero { padding: 30px; }
        .help-hero h1 { font-size: 32px; }
        .section-title { font-size: 18px; }
        .guide-card { flex-direction: column; text-align: center; }
        .guide-icon { text-align: center; }
        .stats-row { flex-direction: column; }
    }
</style>

<div class="help-container">
    <!-- Hero Section -->
    <div class="help-hero">
        <i class="fas fa-question-circle" style="font-size: 64px; margin-bottom: 20px;"></i>
        <h1>Welcome to Voting System Guide</h1>
        <p>Your complete guide to participating in elections, voting, and managing your profile</p>
        <div class="welcome-badge">
            <i class="fas fa-user"></i> Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <i class="fas fa-vote-yea"></i>
            <div class="number">
                <?php
                $voteCount = $conn->query("SELECT COUNT(*) as count FROM votes WHERE username = '" . $username . "'")->fetch(PDO::FETCH_ASSOC);
                echo $voteCount['count'];
                ?>
            </div>
            <div class="label">Votes Cast</div>
        </div>
        <div class="stat-box">
            <i class="fas fa-user-tie"></i>
            <div class="number">
                <?php
                $applyCount = $conn->query("SELECT COUNT(*) as count FROM contesters WHERE name = '" . $username . "'")->fetch(PDO::FETCH_ASSOC);
                echo $applyCount['count'];
                ?>
            </div>
            <div class="label">Candidacy Applications</div>
        </div>
        <div class="stat-box">
            <i class="fas fa-calendar-check"></i>
            <div class="number">
                <?php
                $regCount = $conn->query("SELECT COUNT(*) as count FROM user_elections ue JOIN users u ON ue.user_id = u.id WHERE u.username = '" . $username . "'")->fetch(PDO::FETCH_ASSOC);
                echo $regCount['count'];
                ?>
            </div>
            <div class="label">Elections Registered</div>
        </div>
    </div>
    
    <!-- Main Guide Sections -->
    
    <!-- 1. Getting Started -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-rocket"></i>
                <span>Getting Started</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-user-plus"></i></div>
                <div class="guide-content">
                    <h3>Create Your Account</h3>
                    <p>To participate in elections, you need to register an account first. Click on "Register" from the login page and fill in your details.</p>
                    <ul class="step-list" style="margin-top: 10px;">
                        <li><span class="step-number">1</span> Click on "Create New Account" on the login page</li>
                        <li><span class="step-number">2</span> Fill in your personal information (Username, Full Name, Email)</li>
                        <li><span class="step-number">3</span> Upload a profile photo (JPG, PNG, GIF - max 2MB)</li>
                        <li><span class="step-number">4</span> Select the elections you want to register for</li>
                        <li><span class="step-number">5</span> Submit your registration and login</li>
                    </ul>
                </div>
            </div>
            
            <div class="tip-box">
                <i class="fas fa-lightbulb"></i> <strong>Pro Tip:</strong> Use a clear profile photo so other voters can identify you easily, especially if you're running as a candidate.
            </div>
        </div>
    </div>
    
    <!-- 2. Voting Guide -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-check-circle"></i>
                <span>How to Vote</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-hand-pointer"></i></div>
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
                <div class="guide-icon"><i class="fas fa-info-circle"></i></div>
                <div class="guide-content">
                    <h3>Important Voting Rules</h3>
                    <ul>
                        <li>✓ You can only vote once per election</li>
                        <li>✓ You must be registered for the election before voting</li>
                        <li>✓ Votes cannot be changed after submission</li>
                        <li>✓ You must vote for all positions in the election</li>
                    </ul>
                </div>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> Once you submit your vote, it cannot be changed or undone. Make sure you've selected your preferred candidates before confirming.
            </div>
        </div>
    </div>
    
    <!-- 3. Becoming a Candidate -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-user-tie"></i>
                <span>Applying as a Candidate</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-file-signature"></i></div>
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
                <div class="guide-icon"><i class="fas fa-pen-fancy"></i></div>
                <div class="guide-content">
                    <h3>Writing an Effective Manifesto</h3>
                    <ul>
                        <li>✓ State your qualifications and experience</li>
                        <li>✓ Outline your goals if elected</li>
                        <li>✓ Be clear, concise, and honest</li>
                        <li>✓ Highlight what makes you unique</li>
                        <li>✓ Keep it professional and respectful</li>
                    </ul>
                </div>
            </div>
            
            <div class="tip-box">
                <i class="fas fa-lightbulb"></i> <strong>Pro Tip:</strong> You can only apply for ONE position per election. Choose the role that best fits your qualifications.
            </div>
        </div>
    </div>
    
    <!-- 4. Viewing Results -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                <span>Viewing Election Results</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-poll"></i></div>
                <div class="guide-content">
                    <h3>Accessing Results</h3>
                    <ul class="step-list">
                        <li><span class="step-number">1</span> Click on <strong>All Votes</strong> from the main menu</li>
                        <li><span class="step-number">2</span> Results are organized by election</li>
                        <li><span class="step-number">3</span> Each position shows all candidates and their vote counts</li>
                        <li><span class="step-number">4</span> Winners are highlighted with a special badge</li>
                        <li><span class="step-number">5</span> Results include vote percentages for easy comparison</li>
                    </ul>
                </div>
            </div>
            
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="guide-content">
                    <h3>Understanding the Results Display</h3>
                    <ul>
                        <li>📊 <strong>Vote Count:</strong> Total number of votes received</li>
                        <li>📈 <strong>Percentage:</strong> Share of total votes for that position</li>
                        <li>🏆 <strong>Winner Badge:</strong> Indicates the winning candidate</li>
                        <li>👤 <strong>Candidate Photos:</strong> Helps identify candidates easily</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 5. Managing Your Profile -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-user-cog"></i>
                <span>Managing Your Profile</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-edit"></i></div>
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
                <div class="guide-icon"><i class="fas fa-history"></i></div>
                <div class="guide-content">
                    <h3>Tracking Your Activity</h3>
                    <p>The <strong>My Applications</strong> page shows you:</p>
                    <ul>
                        <li>✓ Which elections you're registered for</li>
                        <li>✓ Which positions you're contesting</li>
                        <li>✓ Your application status</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 6. Frequently Asked Questions -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-question-circle"></i>
                <span>Frequently Asked Questions (FAQ)</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle" style="color: #667eea;"></i> Can I change my vote after submitting?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    No, once you submit your vote, it is permanently recorded and cannot be changed. Make sure you've reviewed your choices before confirming.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle" style="color: #667eea;"></i> Can I apply for multiple positions in the same election?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    No, you can only apply for ONE position per election. This ensures fair competition and prevents conflicts of interest.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle" style="color: #667eea;"></i> How do I know if I'm registered for an election?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    Go to <strong>My Applications</strong> page. It shows all elections you're registered for and any positions you're contesting.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle" style="color: #667eea;"></i> What happens if I forget my password?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    Click on "Forgot Password" on the login page. You'll need to contact the administrator to reset your password.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle" style="color: #667eea;"></i> Are my votes anonymous?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    Votes are recorded with your username for verification purposes, but only administrators can see who voted for whom. The public results only show vote counts per candidate.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span><i class="fas fa-question-circle" style="color: #667eea;"></i> What file formats are accepted for profile photos?</span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-answer">
                    We accept JPG, JPEG, PNG, and GIF formats. Maximum file size is 2MB. For best results, use a clear, well-lit photo.
                </div>
            </div>
        </div>
    </div>
    
    <!-- 7. Need More Help? -->
    <div class="guide-section">
        <div class="section-header" onclick="toggleSection(this)">
            <div class="section-title">
                <i class="fas fa-headset"></i>
                <span>Need Additional Help?</span>
            </div>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <div class="guide-card">
                <div class="guide-icon"><i class="fas fa-envelope"></i></div>
                <div class="guide-content">
                    <h3>Contact Support</h3>
                    <p>If you need further assistance, please contact the system administrator:</p>
                    <ul style="margin-top: 10px;">
                        <li><i class="fas fa-envelope"></i> Email: <strong>jacobwitty@example.com</strong></li>
                        <li><i class="fas fa-phone"></i> Phone: <strong>+254 700 000 000</strong></li>
                        <li><i class="fas fa-clock"></i> Response Time: Within 24 hours</li>
                    </ul>
                </div>
            </div>
            
            <div class="tip-box">
                <i class="fas fa-info-circle"></i> <strong>Tip:</strong> Before contacting support, make sure to check this guide and the FAQ section for quick answers to common questions.
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
    
    // Auto-open first section for better UX
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
