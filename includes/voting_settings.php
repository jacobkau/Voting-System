<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Function to get election type data
function getElectionTypes($conn) {
    try {
        $sql = "SELECT * FROM election_types";
        $result = $conn->query($sql);
        $electionTypes = [];
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $electionTypes[] = $row;
            }
        }
        return $electionTypes;
    } catch (PDOException $e) {
        error_log("Error fetching election types: " . $e->getMessage());
        return [];
    }
}

// Function to get election data
function getElections($conn) {
    try {
        $sql = "SELECT * FROM elections ORDER BY id DESC";
        $result = $conn->query($sql);
        $elections = [];
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $elections[] = $row;
            }
        }
        return $elections;
    } catch (PDOException $e) {
        error_log("Error fetching elections: " . $e->getMessage());
        return [];
    }
}

// Function to get registered member count
function getRegisteredMemberCount($conn) {
    try {
        $sql = "SELECT COUNT(*) as count FROM users";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['count'] : 0;
        }
        return 0;
    } catch (PDOException $e) {
        error_log("Error counting members: " . $e->getMessage());
        return 0;
    }
}

// Function to get candidate count
function getCandidateCount($conn) {
    try {
        $sql = "SELECT COUNT(*) as count FROM contesters";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['count'] : 0;
        }
        return 0;
    } catch (PDOException $e) {
        error_log("Error counting candidates: " . $e->getMessage());
        return 0;
    }
}

// Get Data
$electionTypes = getElectionTypes($conn);
$elections = getElections($conn);
$memberCount = getRegisteredMemberCount($conn);
$candidateCount = getCandidateCount($conn);
?>

<h2>Voting System Overview</h2>

<?php if (empty($electionTypes)): ?>
    <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; color: #856404;">
        No election types found. Please add election types to the database.
    </div>
<?php else: ?>
    <h3>Election Types</h3>
    <table>
        <thead>
            <tr>
                <th>Type Name</th>
                <th>Voting Enabled</th>
                <th>Registration Enabled</th>
                <th>Results Visible</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($electionTypes as $type) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($type['type_name'] ?? 'N/A'); ?></td>
                    <td><?php echo isset($type['voting_enabled']) ? ($type['voting_enabled'] ? 'Yes' : 'No') : 'N/A'; ?></td>
                    <td><?php echo isset($type['registration_enabled']) ? ($type['registration_enabled'] ? 'Yes' : 'No') : 'N/A'; ?></td>
                    <td><?php echo isset($type['results_visible']) ? ($type['results_visible'] ? 'Yes' : 'No') : 'N/A'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (empty($elections)): ?>
    <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; color: #856404;">
        No elections found. Please create an election.
    </div>
<?php else: ?>
    <h3>Elections</h3>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Election Type ID</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($elections as $election) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($election['title'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($election['start_date'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($election['end_date'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($election['election_type_id'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h3>System Statistics</h3>
<p><strong>Registered Members:</strong> <?php echo $memberCount; ?></p>
<p><strong>Registered Candidates:</strong> <?php echo $candidateCount; ?></p>

<style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }
    
    h2, h3 {
        color: #333;
    }
</style>
