<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$message = "";
$messageType = "";
$selectedElection = null;
$electionStats = null;

// Fetch all elections
$electionsStmt = $conn->prepare("SELECT id, title, status, start_date, end_date FROM elections ORDER BY id DESC");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Election Selection
if (isset($_GET['election_id'])) {
    $electionId = intval($_GET['election_id']);
    $selectedElection = array_filter($elections, function($e) use ($electionId) {
        return $e['id'] == $electionId;
    });
    $selectedElection = !empty($selectedElection) ? array_values($selectedElection)[0] : null;
    
    if ($selectedElection) {
        // Get vote statistics
        $voteStats = $conn->prepare("
            SELECT 
                COUNT(*) as total_votes,
                COUNT(DISTINCT username) as unique_voters,
                COUNT(DISTINCT postname) as total_positions
            FROM votes 
            WHERE election_id = ?
        ");
        $voteStats->execute([$electionId]);
        $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
        
        // Get contesters/candidates count
        $contesterStats = $conn->prepare("SELECT COUNT(*) as total_candidates FROM contesters WHERE election_id = ?");
        $contesterStats->execute([$electionId]);
        $contesterCount = $contesterStats->fetch(PDO::FETCH_ASSOC);
        $electionStats['total_candidates'] = $contesterCount['total_candidates'];
        
        // Get user registrations count
        $regStats = $conn->prepare("SELECT COUNT(*) as total_registered FROM user_elections WHERE election_id = ?");
        $regStats->execute([$electionId]);
        $regCount = $regStats->fetch(PDO::FETCH_ASSOC);
        $electionStats['total_registered'] = $regCount['total_registered'];
    }
}

// Option 1: Delete Only Votes (Keep candidates, posts, registrations)
if (isset($_POST['delete_only_votes'])) {
    $electionId = intval($_POST['election_id']);
    
    try {
        $conn->beginTransaction();
        
        // Get vote count before deletion
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM votes WHERE election_id = ?");
        $countStmt->execute([$electionId]);
        $voteCount = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete all votes for this election
        $deleteStmt = $conn->prepare("DELETE FROM votes WHERE election_id = ?");
        $deleteStmt->execute([$electionId]);
        
        // Reset vote counts in contesters table to 0
        $resetStmt = $conn->prepare("UPDATE contesters SET votes = 0 WHERE election_id = ?");
        $resetStmt->execute([$electionId]);
        
        // Log the action
        $logStmt = $conn->prepare("
            INSERT INTO event_log (username, event_type, event_description) 
            VALUES (?, 'Delete Only Votes', ?)
        ");
        $logStmt->execute([$_SESSION['username'], "Deleted only votes for election ID: $electionId. Deleted {$voteCount['count']} votes, kept candidates."]);
        
        $conn->commit();
        
        $message = "Successfully deleted {$voteCount['count']} votes. Candidates and registrations remain intact.";
        $messageType = "success";
        
        // Refresh stats
        $voteStats = $conn->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
        $voteStats->execute([$electionId]);
        $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
        $electionStats['total_candidates'] = $contesterCount['total_candidates'];
        $electionStats['total_registered'] = $regCount['total_registered'];
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "Error deleting votes: " . $e->getMessage();
        $messageType = "error";
    }
}

// Option 2: Delete Votes AND Candidates (Keep election structure)
if (isset($_POST['delete_votes_and_candidates'])) {
    $electionId = intval($_POST['election_id']);
    
    if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'yes') {
        $message = "Please confirm deletion by checking the confirmation box.";
        $messageType = "error";
    } else {
        try {
            $conn->beginTransaction();
            
            // Get counts before deletion
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM votes WHERE election_id = ?");
            $countStmt->execute([$electionId]);
            $voteCount = $countStmt->fetch(PDO::FETCH_ASSOC);
            
            $candidateStmt = $conn->prepare("SELECT COUNT(*) as count FROM contesters WHERE election_id = ?");
            $candidateStmt->execute([$electionId]);
            $candidateCount = $candidateStmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete votes
            $deleteVotes = $conn->prepare("DELETE FROM votes WHERE election_id = ?");
            $deleteVotes->execute([$electionId]);
            
            // Delete contesters/candidates
            $deleteCandidates = $conn->prepare("DELETE FROM contesters WHERE election_id = ?");
            $deleteCandidates->execute([$electionId]);
            
            // Log the action
            $logStmt = $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description) 
                VALUES (?, 'Delete Votes & Candidates', ?)
            ");
            $logStmt->execute([$_SESSION['username'], "Deleted {$voteCount['count']} votes and {$candidateCount['count']} candidates for election ID: $electionId"]);
            
            $conn->commit();
            
            $message = "Successfully deleted {$voteCount['count']} votes and {$candidateCount['count']} candidates.";
            $messageType = "success";
            
            // Refresh stats
            $voteStats = $conn->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
            $voteStats->execute([$electionId]);
            $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
            
            $contesterStats = $conn->prepare("SELECT COUNT(*) as total_candidates FROM contesters WHERE election_id = ?");
            $contesterStats->execute([$electionId]);
            $electionStats['total_candidates'] = $contesterStats->fetch(PDO::FETCH_ASSOC)['total_candidates'];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Option 3: Complete Reset (Delete everything - votes, candidates, registrations, posts)
if (isset($_POST['complete_reset'])) {
    $electionId = intval($_POST['election_id']);
    
    if (!isset($_POST['confirm_complete']) || $_POST['confirm_complete'] !== 'yes') {
        $message = "Please confirm complete reset by checking the confirmation box.";
        $messageType = "error";
    } else {
        try {
            $conn->beginTransaction();
            
            // Get counts before deletion
            $voteStmt = $conn->prepare("SELECT COUNT(*) as count FROM votes WHERE election_id = ?");
            $voteStmt->execute([$electionId]);
            $voteCount = $voteStmt->fetch(PDO::FETCH_ASSOC);
            
            $candidateStmt = $conn->prepare("SELECT COUNT(*) as count FROM contesters WHERE election_id = ?");
            $candidateStmt->execute([$electionId]);
            $candidateCount = $candidateStmt->fetch(PDO::FETCH_ASSOC);
            
            $regStmt = $conn->prepare("SELECT COUNT(*) as count FROM user_elections WHERE election_id = ?");
            $regStmt->execute([$electionId]);
            $regCount = $regStmt->fetch(PDO::FETCH_ASSOC);
            
            $postStmt = $conn->prepare("SELECT COUNT(*) as count FROM election_posts WHERE election_id = ?");
            $postStmt->execute([$electionId]);
            $postCount = $postStmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete all related data
            $conn->prepare("DELETE FROM votes WHERE election_id = ?")->execute([$electionId]);
            $conn->prepare("DELETE FROM contesters WHERE election_id = ?")->execute([$electionId]);
            $conn->prepare("DELETE FROM user_elections WHERE election_id = ?")->execute([$electionId]);
            $conn->prepare("DELETE FROM election_posts WHERE election_id = ?")->execute([$electionId]);
            
            // Optionally reset election status to 'upcoming'
            if (isset($_POST['reset_status'])) {
                $conn->prepare("UPDATE elections SET status = 'upcoming' WHERE id = ?")->execute([$electionId]);
            }
            
            // Log the action
            $logStmt = $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description) 
                VALUES (?, 'Complete Reset', ?)
            ");
            $logStmt->execute([$_SESSION['username'], "Complete reset for election ID: $electionId. Deleted {$voteCount['count']} votes, {$candidateCount['count']} candidates, {$regCount['count']} registrations, {$postCount['count']} posts."]);
            
            $conn->commit();
            
            $message = "Complete reset successful! Deleted votes, candidates, registrations, and posts.";
            $messageType = "success";
            
            // Refresh stats
            $voteStats = $conn->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
            $voteStats->execute([$electionId]);
            $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Delete Single Vote
if (isset($_POST['delete_single_vote']) && isset($_POST['vote_id'])) {
    $voteId = intval($_POST['vote_id']);
    $electionId = intval($_POST['election_id']);
    
    try {
        $getVoteStmt = $conn->prepare("SELECT * FROM votes WHERE id = ?");
        $getVoteStmt->execute([$voteId]);
        $vote = $getVoteStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vote) {
            $conn->beginTransaction();
            
            $conn->prepare("DELETE FROM votes WHERE id = ?")->execute([$voteId]);
            
            $updateContester = $conn->prepare("
                UPDATE contesters 
                SET votes = votes - 1 
                WHERE name = ? AND election_id = ? AND postname = ?
            ");
            $updateContester->execute([$vote['candidate_name'], $vote['election_id'], $vote['postname']]);
            
            $logStmt = $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description) 
                VALUES (?, 'Delete Single Vote', ?)
            ");
            $logStmt->execute([$_SESSION['username'], "Deleted vote ID: $voteId"]);
            
            $conn->commit();
            $message = "Vote deleted successfully!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Refresh / Reset Votes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7f9; }
        .refresh-container { max-width: 1400px; margin: 0 auto; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 28px; font-weight: 700; color: #1f2937; margin-bottom: 10px; }
        .page-header p { color: #6b7280; font-size: 14px; }
        
        .message { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .message.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .message.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .message.warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        
        .election-selector { background: white; border-radius: 16px; padding: 25px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
        .election-selector label { display: block; font-weight: 600; color: #374151; margin-bottom: 10px; font-size: 14px; }
        .election-selector select { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 16px; transition: all 0.3s; }
        .election-selector select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        
        .stats-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .stat-card { flex: 1; min-width: 180px; background: white; border-radius: 16px; padding: 20px; text-align: center; border: 1px solid #e5e7eb; }
        .stat-card.warning { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
        .stat-card.danger { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
        .stat-icon { font-size: 40px; margin-bottom: 10px; }
        .stat-value { font-size: 36px; font-weight: 800; color: #1f2937; }
        .stat-label { font-size: 14px; color: #6b7280; margin-top: 5px; }
        
        .action-buttons { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .action-group { background: white; border-radius: 16px; padding: 20px; border: 1px solid #e5e7eb; flex: 1; min-width: 250px; }
        .action-group h4 { margin-bottom: 15px; font-size: 16px; font-weight: 700; }
        .action-group p { font-size: 13px; color: #6b7280; margin-bottom: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-secondary { background: #6b7280; color: white; }
        
        .confirm-checkbox { margin-left: 10px; display: inline-flex; align-items: center; gap: 5px; }
        
        .votes-table-container { background: white; border-radius: 16px; overflow: hidden; border: 1px solid #e5e7eb; }
        .votes-table-container h3 { padding: 20px; font-size: 18px; font-weight: 600; }
        .votes-table { width: 100%; border-collapse: collapse; }
        .votes-table th { background: #f9fafb; padding: 12px 15px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .votes-table td { padding: 10px 15px; border-bottom: 1px solid #e5e7eb; }
        .delete-vote-btn { background: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .empty-state { text-align: center; padding: 60px; color: #9ca3af; }
        
        @media (max-width: 768px) {
            .stats-grid { flex-direction: column; }
            .action-buttons { flex-direction: column; }
            .btn { justify-content: center; }
            .votes-table { font-size: 12px; }
            .votes-table-container { overflow-x: auto; }
        }
    </style>
    <script>
        function updateElection() {
            const electionId = document.getElementById('electionSelect').value;
            if (electionId) {
                window.location.href = 'main.php?page=refreshdb&election_id=' + electionId;
            } else {
                window.location.href = 'main.php?page=refreshdb';
            }
        }
    </script>
</head>
<body>
    <div class="refresh-container">
        <div class="page-header">
            <h2><i class="fas fa-sync-alt"></i> Refresh / Reset Election Data</h2>
            <p>Select what data you want to delete for the election</p>
        </div>
        
        <!-- Election Selector -->
        <div class="election-selector">
            <label for="electionSelect"><i class="fas fa-calendar-alt"></i> Select Election:</label>
            <select id="electionSelect" onchange="updateElection();">
                <option value="">-- Select Election --</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo $election['id']; ?>" <?php echo ($selectedElection && $selectedElection['id'] == $election['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['title']); ?> (<?php echo ucfirst($election['status']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($selectedElection): ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['total_votes'] ?? 0); ?></div>
                    <div class="stat-label">Total Votes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['unique_voters'] ?? 0); ?></div>
                    <div class="stat-label">Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['total_candidates'] ?? 0); ?></div>
                    <div class="stat-label">Candidates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-registered"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['total_registered'] ?? 0); ?></div>
                    <div class="stat-label">Registered Voters</div>
                </div>
            </div>
            
            <!-- Action Options -->
            <div class="action-buttons">
                <!-- Option 1: Delete Only Votes -->
                <div class="action-group">
                    <h4><i class="fas fa-trash-alt"></i> Option 1: Delete Only Votes</h4>
                    <p>Keep all candidates, user registrations, and election posts. Only remove cast votes.</p>
                    <form method="post" onsubmit="return confirm('Delete ONLY votes? Candidates will remain.')">
                        <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                        <button type="submit" name="delete_only_votes" class="btn btn-warning">
                            <i class="fas fa-trash"></i> Delete Only Votes (<?php echo $electionStats['total_votes']; ?> votes)
                        </button>
                    </form>
                </div>
                
                <!-- Option 2: Delete Votes & Candidates -->
                <div class="action-group">
                    <h4><i class="fas fa-trash-alt"></i> Option 2: Delete Votes & Candidates</h4>
                    <p>Remove all votes and candidates. Keep election posts and user registrations.</p>
                    <form method="post" onsubmit="return confirm('Delete votes AND candidates? This cannot be undone!')">
                        <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                        <label class="confirm-checkbox">
                            <input type="checkbox" name="confirm_delete" value="yes" required>
                            <span>I confirm</span>
                        </label>
                        <button type="submit" name="delete_votes_and_candidates" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Delete Votes & Candidates
                        </button>
                    </form>
                </div>
                
                <!-- Option 3: Complete Reset -->
                <div class="action-group">
                    <h4><i class="fas fa-sync-alt"></i> Option 3: Complete Reset</h4>
                    <p>Delete EVERYTHING: votes, candidates, registrations, and election posts.</p>
                    <form method="post" onsubmit="return confirm('COMPLETE RESET: Delete all votes, candidates, registrations, and posts? This cannot be undone!')">
                        <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                        <label class="confirm-checkbox">
                            <input type="checkbox" name="confirm_complete" value="yes" required>
                            <span>I confirm</span>
                        </label>
                        <label class="confirm-checkbox">
                            <input type="checkbox" name="reset_status" value="yes" checked>
                            <span>Set status to 'Upcoming'</span>
                        </label>
                        <button type="submit" name="complete_reset" class="btn btn-danger">
                            <i class="fas fa-sync-alt"></i> Complete Reset
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Votes List -->
            <div class="votes-table-container">
                <h3><i class="fas fa-list"></i> All Votes Cast</h3>
                <?php
                $votesListStmt = $conn->prepare("
                    SELECT v.*, u.name as voter_name 
                    FROM votes v 
                    LEFT JOIN users u ON v.username = u.username 
                    WHERE v.election_id = ? 
                    ORDER BY v.voted_at DESC
                ");
                $votesListStmt->execute([$selectedElection['id']]);
                $votesList = $votesListStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($votesList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No votes have been cast for this election yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="votes-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Voter</th>
                                    <th>Position</th>
                                    <th>Candidate</th>
                                    <th>Voted At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($votesList as $vote): ?>
                                    <tr>
                                        <td><?php echo $vote['id']; ?></td>
                                        <td><?php echo htmlspecialchars($vote['voter_name'] ?? $vote['username']); ?></td>
                                        <td><?php echo htmlspecialchars($vote['postname']); ?></td>
                                        <td><?php echo htmlspecialchars($vote['candidate_name']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($vote['voted_at'])); ?></td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Delete this vote?')">
                                                <input type="hidden" name="vote_id" value="<?php echo $vote['id']; ?>">
                                                <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                                                <button type="submit" name="delete_single_vote" class="delete-vote-btn">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (empty($elections)): ?>
            <div class="message error">
                <i class="fas fa-info-circle"></i>
                <span>No elections found. Please create an election first.</span>
            </div>
        <?php else: ?>
            <div class="message warning">
                <i class="fas fa-info-circle"></i>
                <span>Please select an election from the dropdown above to manage its data.</span>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
