<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
// Database connection
$servername = "sql308.infinityfree.com";
$username = "if0_40097384";
$password = "1ThXLmVD9G9ZLGH";
$dbname = "if0_40097384_tik";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed']));
}

if (isset($_GET['story_id'])) {
    $story_id = intval($_GET['story_id']);
    
    $sql = "SELECT s.*, u.username, u.profile_picture,
                   COUNT(sv.id) AS view_count
            FROM stories s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN story_views sv ON s.id = sv.story_id
            WHERE s.id = ?
            GROUP BY s.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $story = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'story' => [
                'id' => $story['id'],
                'file_path' => $story['file_path'],
                'file_type' => $story['file_type'],
                'created_at' => $story['created_at'],
                'user_id' => $story['user_id'],
                'username' => $story['username'],
                'profile_picture' => !empty($story['profile_picture']) ? $story['profile_picture'] : 'uploads/profile.jpg',
                'view_count' => $story['view_count']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Story not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Story ID required']);
}
?>