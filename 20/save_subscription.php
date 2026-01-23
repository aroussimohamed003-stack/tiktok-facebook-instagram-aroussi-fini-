<?php
include 'config.php';
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['endpoint'])) {
    http_response_code(400);
    exit('Invalid data');
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$endpoint = mysqli_real_escape_string($con, $data['endpoint']);
$p256dh = mysqli_real_escape_string($con, $data['keys']['p256dh'] ?? '');
$auth = mysqli_real_escape_string($con, $data['keys']['auth'] ?? '');

// Create table if not exists
mysqli_query($con, "CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    endpoint TEXT NOT NULL,
    p256dh TEXT,
    auth TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(255))
)");

// Insert or update subscription
$sql = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) 
        VALUES ($user_id, '$endpoint', '$p256dh', '$auth') 
        ON DUPLICATE KEY UPDATE user_id = $user_id, p256dh = '$p256dh', auth = '$auth'";

if (mysqli_query($con, $sql)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => mysqli_error($con)]);
}
?>
