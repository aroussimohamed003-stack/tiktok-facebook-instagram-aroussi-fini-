<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("config.php");

if (!isset($_GET['user_id'])) {
    die(json_encode(['error' => 'User ID not provided']));
}

$user_id = intval($_GET['user_id']);

// جلب بيانات المستخدم
$user_query = mysqli_query($con, "SELECT * FROM users WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($user_query);

if (!$user_data) {
    die(json_encode(['error' => 'User not found']));
}

// جلب فيديوهات المستخدم (نشطة أو مبلغ عنها حديثاً)
$videos_query = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $user_id AND (status = 'active' OR (status = 'signale' AND reported_at > NOW() - INTERVAL 48 HOUR)) ORDER BY id DESC");
$videos = [];
while ($video = mysqli_fetch_assoc($videos_query)) {
    $videos[] = $video;
}

// إعداد البيانات للإرجاع
$response = [
    'username' => $user_data['username'],
    'bio' => $user_data['bio'] ?? '',
    'videos' => $videos
];

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo json_encode($response);
?>