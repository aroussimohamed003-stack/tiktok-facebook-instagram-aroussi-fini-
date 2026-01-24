<?php
include("config.php");
$query = mysqli_query($con, "SELECT id, username, profile_picture FROM users LIMIT 10");
while($row = mysqli_fetch_assoc($query)) {
    echo "ID: " . $row['id'] . " | Name: " . $row['username'] . " | Pic: [" . $row['profile_picture'] . "]\n";
}
?>
