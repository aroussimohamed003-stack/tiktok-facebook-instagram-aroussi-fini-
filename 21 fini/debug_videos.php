<?php
include("config.php");
header('Content-Type: text/plain');

echo "--- USERS ---\n";
$users = $con->query("SELECT id, username FROM users");
while($u = $users->fetch_assoc()) {
    echo "ID: {$u['id']}, User: {$u['username']}\n";
}

echo "\n--- VIDEOS ---\n";
$videos = $con->query("SELECT id, user_id, title, location FROM videos");
while($v = $videos->fetch_assoc()) {
    echo "ID: {$v['id']}, UserID: {$v['user_id']}, Title: {$v['title']}, Location: {$v['location']}\n";
}

echo "\n--- TEST get_profile logic for User 5 ---\n";
$user_id = 5;
$videos_query = mysqli_query($con, "SELECT id, location FROM videos WHERE user_id = $user_id ORDER BY id DESC");
while ($video = mysqli_fetch_assoc($videos_query)) {
    echo "User 5 has video ID: " . $video['id'] . " Loc: " . $video['location'] . "\n";
}
?>
