<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("config.php");

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    $sql = "SELECT s.*, u.username, u.profile_picture,
                   COUNT(sv.id) AS view_count
            FROM stories s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN story_views sv ON s.id = sv.story_id
            WHERE s.user_id = ? 
            AND s.created_at > NOW() - INTERVAL 48 HOUR
            GROUP BY s.id
            ORDER BY s.created_at ASC";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stories = [];
    while ($row = $result->fetch_assoc()) {
        $stories[] = [
            'id' => $row['id'],
            'file_path' => $row['file_path'],
            'file_type' => $row['file_type'],
            'created_at' => $row['created_at'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'profile_picture' => str_replace('profile_pictures', 'profiles', !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg'),
            'view_count' => $row['view_count'],
            'music_url' => $row['music_url'] ?? null,
            'music_title' => $row['music_title'] ?? null,
            'music_artist' => $row['music_artist'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stories' => $stories
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
}
?>