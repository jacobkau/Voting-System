<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) { // Check for admin_id, more secure
    header("Location: admin_login.php");
    exit();
}


// Delete Contester
if (isset($_GET['delete_contester'])) {
    $contester_id = $_GET['delete_contester'];
    $sql_delete = "DELETE FROM contesters WHERE id = $contester_id";
    if ($conn->query($sql_delete) === TRUE) {
        echo "<p style='color:green;'>Contester deleted successfully.</p>";
    } else {
        echo "<p style='color:red;'>Error deleting contester: " . $conn->error . "</p>";
    }
}

$sql = "SELECT * FROM contesters";
$result = $conn->query($sql);

// ... (HTML table to display contesters) ...
?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Post</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['name']; ?></td>
                <td><?php echo $row['postname']; ?></td>
                <td><a href="?delete_contester=<?php echo $row['id']; ?>">Delete</a></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php $conn->close(); ?>