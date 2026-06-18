<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch all elections using PDO
try {
    $electionQuery = "SELECT id, title FROM elections ORDER BY id DESC";
    $electionResult = $conn->query($electionQuery);
    $elections = $electionResult->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

// Helper function to get candidate image
function getCandidateImage($candidate) {
    if (!empty($candidate['profile_photo_blob'])) {
        return 'data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']);
    } elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
        return 'faces/' . htmlspecialchars($candidate['profile_photo']);
    }
    return 'faces/default.jpg';
}
?>

<?php include("header.php"); ?>

<style>
    .votes-container { 
        max-width: 1400px; 
        margin: 40px auto; 
        background-color: white; 
        padding: 35px; 
        border-radius: 20px; 
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); 
    }
    
    .votes-container h1 {
        color: #333;
        margin-bottom: 30px;
        text-align: center;
        font-size: 32px;
    }
    
    .votes-container h2 {
        color: #667eea;
        margin-top: 30px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e0e0e0;
        font-size: 24px;
    }
    
    .votes-container h3 {
        color: #764ba2;
        margin-top: 25px;
        margin-bottom: 15px;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .votes-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 20px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .votes-table th, 
    .votes-table td { 
        border: 1px solid #e5e7eb; 
        padding: 14px; 
        text-align: left; 
    }
    
    .votes-table th { 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
    }
    
    .votes-table tr:hover {
        background-color: #f9fafb;
    }
    
    .candidate-image {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #667eea;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .winner-badge {
        display: inline-block;
        background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
        color: white;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 20px;
        margin-left: 10px;
    }
    
    .vote-count {
        font-size: 18px;
        font-weight: 700;
        color: #667eea;
    }
    
    .percentage-bar {
        background: #e5e7eb;
        border-radius: 10px;
        height: 8px;
        width: 100%;
        overflow: hidden;
        margin-top: 5px;
    }
    
    .percentage-fill {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        height: 100%;
        border-radius: 10px;
        transition: width 0.5s ease;
    }
    
    .no-data {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 16px;
    }
    
    .no-data i {
        font-size: 48px;
        color: #cbd5e1;
        margin-bottom: 15px;
        display: block;
    }
    
    .no-data p {
        color: #9ca3af;
    }
    
    .crown-icon {
        color: #f59e0b;
        margin-right: 5px;
    }
    
    .loading-results {
        text-align: center;
        padding: 60px;
    }
    
    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 3px solid #e5e7eb;
        border-top-color: #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .votes-container { 
            margin: 20px;
            padding: 15px;
        }
        .votes-container h1 { font-size: 24px; }
        .votes-container h2 { font-size: 18px; }
        .votes-container h3 { font-size: 16px; }
        .votes-table th, 
        .votes-table td { 
            padding: 8px;
            font-size: 12px;
        }
        .candidate-image {
            width: 40px;
            height: 40px;
        }
    }
</style>

<div class="votes-container">
    <h1><i class="fas fa-chart-bar"></i> Election Results</h1>

    <?php if (empty($elections)): ?>
        <div class="no-data">
            <i class="fas fa-vote-yea"></i>
            <p>No elections available at this time.</p>
            <p>Please check back later for upcoming elections.</p>
        </div>
    <?php else: ?>
        <?php foreach ($elections as $election): 
            $electionId = $election['id']; 
        ?>
            <h2><i class="fas fa-poll"></i> <?php echo htmlspecialchars($election['title']); ?></h2>

            <?php
            try {
                $postQuery = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postQuery->execute([$electionId]);
                $posts = $postQuery->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($posts)) {
                    $postQuery2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                    $postQuery2->execute([$electionId]);
                    $posts = $postQuery2->fetchAll(PDO::FETCH_ASSOC);
                }
                
                if (empty($posts)): 
            ?>
                    <p style="color: #9ca3af; font-style: italic; text-align: center; padding: 20px;">No positions available for this election.</p>
                <?php else: ?>
                    <?php foreach ($posts as $post): 
                        $postName = $post['postname']; 
                    ?>
                        <h3><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($postName); ?></h3>
                        <table class="votes-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Photo</th>
                                    <th>Candidate Name</th>
                                    <th style="width: 150px;">Votes</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $candidateQuery = $conn->prepare("
                                    SELECT id, name, votes, profile_photo, profile_photo_blob, profile_photo_type 
                                    FROM contesters 
                                    WHERE postname = ? AND election_id = ? 
                                    ORDER BY votes DESC
                                ");
                                $candidateQuery->execute([$postName, $electionId]);
                                $candidates = $candidateQuery->fetchAll(PDO::FETCH_ASSOC);
                                $totalVotes = array_sum(array_column($candidates, 'votes'));
                                
                                if (empty($candidates)):
                                ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #9ca3af;">No candidates for this position</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $index => $candidate): 
                                        $isWinner = ($index === 0 && $totalVotes > 0);
                                        $percentage = ($totalVotes > 0) ? ($candidate['votes'] / $totalVotes) * 100 : 0;
                                    ?>
                                        <tr style="<?php echo $isWinner ? 'background: linear-gradient(90deg, #d1fae5 0%, #a7f3d0 100%);' : ''; ?>">
                                            <td style="text-align: center;">
                                                <?php if (!empty($candidate['profile_photo_blob'])): ?>
                                                    <img src="data:image/<?php echo $candidate['profile_photo_type']; ?>;base64,<?php echo base64_encode($candidate['profile_photo_blob']); ?>" alt="Candidate" class="candidate-image">
                                                <?php elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])): ?>
                                                    <img src="faces/<?php echo htmlspecialchars($candidate['profile_photo']); ?>" alt="Candidate" class="candidate-image">
                                                <?php else: ?>
                                                    <img src="faces/default.jpg" alt="Default" class="candidate-image">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($candidate['name']); ?></strong>
                                                <?php if ($isWinner): ?>
                                                    <span class="winner-badge"><i class="fas fa-crown"></i> Winner</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="vote-count"><?php echo number_format($candidate['votes']); ?></span> votes</td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="percentage-bar" style="flex: 1;">
                                                        <div class="percentage-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                    </div>
                                                    <span style="font-size: 14px; color: #666; min-width: 45px;"><?php echo number_format($percentage, 1); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php } catch (PDOException $e) {
                error_log("Error fetching posts/candidates: " . $e->getMessage());
                echo "<p style='color: #dc2626; text-align: center; padding: 20px;'>Error loading results for this election.</p>";
            } ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>
