<?php
session_start();
include("config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit();
}

$my_id = $_SESSION['user_id'];

$sql = "SELECT DISTINCT u.id, u.username, u.profile_picture 
        FROM users u
        JOIN friends f ON (f.sender_id = u.id OR f.receiver_id = u.id)
        WHERE (f.sender_id = $my_id OR f.receiver_id = $my_id) 
          AND f.status = 'accepted' 
          AND u.id != $my_id";

$result = mysqli_query($con, $sql);
$friends = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['profile_picture'] = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
        $row['profile_picture'] = str_replace('profile_pictures', 'profiles', $row['profile_picture']);
        $friends[] = $row;
    }
}

echo json_encode(['success' => true, 'friends' => $friends]);
