<?php
include("conn.php");

if (isset($_GET['election_id']) && is_numeric($_GET['election_id'])) {
    $election_id = $_GET['election_id'];
    $sql_posts = "SELECT postname FROM election_posts WHERE election_id = ?";
    $stmt_posts = $conn->prepare($sql_posts);
    $stmt_posts->bind_param("i", $election_id);
    $stmt_posts->execute();
    $result_posts = $stmt_posts->get_result();
    $posts = [];
    if ($result_posts->num_rows > 0) {
        while ($row_post = $result_posts->fetch_assoc()) {
            $posts[] = $row_post['postname'];
        }
    }
    $stmt_posts->close();
    echo json_encode($posts);
}
?>