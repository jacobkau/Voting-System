<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch all elections
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections ORDER BY id DESC");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

function getProfileImage($profilePhotoBlob, $profilePhotoType) {
    if (!empty($profilePhotoBlob)) {
        return 'data:image/' . $profilePhotoType . ';base64,' . base64_encode($profilePhotoBlob);
    }
    return 'default-avatar.png';
}
?>

<?php include("header.php"); ?>

<style>
    .contest-container { 
        max-width: 1400px; 
        margin: 40px auto; 
        background-color: white; 
        padding: 35px; 
        border-radius: 20px; 
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); 
    }
    
    .contest-container h1 {
        color: #333;
        margin-bottom: 30px;
        text-align: center;
        font-size: 32px;
    }
    
    .election-section { 
        margin-bottom: 40px; 
        border: 1px solid #e5e7eb; 
        border-radius: 16px;
        overflow: hidden;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .election-section h2 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        margin: 0;
        padding: 18px 25px;
        font-size: 22px;
    }
    
    .post-section { 
        margin: 25px;
        padding: 20px;
        background: #f9fafb;
        border-radius: 12px;
        border-left: 4px solid #667eea;
    }
    
    .post-section h3 {
        color: #374151;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .contester-table { 
        width: 100%; 
        border-collapse: collapse; 
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .contester-table th, 
    .contester-table td { 
        padding: 14px 16px; 
        text-align: left; 
        border-bottom: 1px solid #e5e7eb;
    }
    
    .contester-table th { 
        background: #f3f4f6;
        color: #374151;
        font-weight: 600;
        font-size: 14px;
    }
    
    .contester-table tr:last-child td {
        border-bottom: none;
    }
    
    .contester-table tr:hover {
        background-color: #f9fafb;
    }
    
    .contester-image {
        width: 55px;
        height: 55px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #667eea;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .candidate-name {
        font-weight: 700;
        color: #1f2937;
    }
    
    .vote-count {
        display: inline-block;
        background: #e0e7ff;
        color: #4338ca;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .no-data {
        text-align: center;
        padding: 60px;
        color: #9ca3af;
        background: white;
        border-radius: 16px;
    }
    
    .no-data i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
    }
    
    @media (max-width: 768px) {
        .contest-container { 
            margin: 20px;
            padding: 15px;
        }
        .contest-container h1 {
            font-size: 24px;
        }
        .post-section {
            margin: 15px;
            padding: 15px;
        }
        .contester-table th, 
        .contester-table td { 
            padding: 10px;
            font-size: 13px;
        }
        .contester-image {
            width: 40px;
            height: 40px;
        }
        .election-section h2 {
            font-size: 18px;
            padding: 15px 20px;
        }
    }
</style>

<div class="contest-container">
    <h1>Election Contestants</h1>

    <?php if (empty($elections)): ?>
        <div class="no-data">
            <i class="fas fa-vote-yea"></i>
            <p>No elections found.</p>
        </div>
    <?php else: ?>
        <?php foreach ($elections as $election): ?>
            <div class="election-section">
                <h2><i class="fas fa-poll"></i> <?php echo htmlspecialchars($election['title']); ?></h2>

                <?php
                // Fetch posts for the election
                try {
                    // First try to get from election_posts
                    $postsStmt = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                    $postsStmt->execute([$election['id']]);
                    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // If no posts in election_posts, get from contesters
                    if (empty($posts)) {
                        $postsStmt2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                        $postsStmt2->execute([$election['id']]);
                        $posts = $postsStmt2->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    if (empty($posts)):
                ?>
                        <p style="text-align: center; color: #9ca3af; padding: 30px;">No candidates have applied for this election yet.</p>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="post-section">
                                <h3>
                                    <i class="fas fa-user-tie"></i> 
                                    <?php echo htmlspecialchars($post['postname']); ?>
                                </h3>

                                <table class="contester-table">
                                    <thead>
                                        <tr>
                                            <th width="80">Photo</th>
                                            <th>Candidate Name</th>
                                            <th>Bio / Manifesto</th>
                                            <th width="100">Votes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Fetch contesters for the post
                                        $contestersStmt = $conn->prepare("
                                            SELECT name, bio, profile_photo_blob, profile_photo_type, votes 
                                            FROM contesters 
                                            WHERE election_id = ? AND postname = ? 
                                            ORDER BY votes DESC
                                        ");
                                        $contestersStmt->execute([$election['id'], $post['postname']]);
                                        $contesters = $contestersStmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($contesters)):
                                        ?>
                                            <tr>
                                                <td colspan="4" style="text-align: center; color: #9ca3af;">
                                                    No contestants for this position
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($contesters as $contester): ?>
                                                <tr>
                                                    <td style="text-align: center;">
                                                        <?php if (!empty($contester['profile_photo_blob'])): ?>
                                                            <img src="data:image/<?php echo $contester['profile_photo_type']; ?>;base64,<?php echo base64_encode($contester['profile_photo_blob']); ?>" alt="Contester" class="contester-image">
                                                        <?php else: ?>
                                                            <img src="default-avatar.png" alt="Default" class="contester-image">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="candidate-name"><?php echo htmlspecialchars($contester['name']); ?></td>
                                                    <td><?php echo nl2br(htmlspecialchars($contester['bio'] ?? 'No bio provided')); ?></td>
                                                    <td><span class="vote-count"><?php echo $contester['votes']; ?> votes</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php 
                } catch (PDOException $e) {
                    error_log("Error fetching posts/contesters: " . $e->getMessage());
                    echo "<p style='color: #dc2626; text-align: center; padding: 20px;'>Error loading contestants for this election.</p>";
                }
                ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>
