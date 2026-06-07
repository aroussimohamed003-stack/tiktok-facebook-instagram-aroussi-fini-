<?php
include("conn.php"); // Updated conn.php has the correct PDO connection, but wait, the other files use config.php with mysqli.
// Let's use config.php which has $con (mysqli)
include("config.php");

echo "<h1>Users</h1>";
$users = $con->query("SELECT * FROM users");
echo "<table border=1><tr><th>ID</th><th>Username</th></tr>";
while($u = $users->fetch_assoc()) {
    echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td></tr>";
}
echo "</table>";

echo "<h1>Videos</h1>";
$videos = $con->query("SELECT id, title, user_id FROM videos LIMIT 50");
echo "<table border=1><tr><th>ID</th><th>Title</th><th>User ID</th></tr>";
while($v = $videos->fetch_assoc()) {
    echo "<tr><td>{$v['id']}</td><td>{$v['title']}</td><td>{$v['user_id']}</td></tr>";
}
echo "</table>";
?>
