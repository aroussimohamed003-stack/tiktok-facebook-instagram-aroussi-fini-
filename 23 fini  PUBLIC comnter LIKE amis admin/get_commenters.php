<?php
session_start();
include("config.php");

// التحقق من وجود معرف الفيديو أو المنشور
if (isset($_GET['video_id']) || isset($_GET['post_id'])) {
    if (isset($_GET['video_id'])) {
        $id = intval($_GET['video_id']);
        $col = "video_id";
    } else {
        $id = intval($_GET['post_id']);
        $col = "post_id";
    }
    
    // جلب المستخدمين الذين علقوا (Unique users)
    $comments_query = mysqli_query($con, "SELECT DISTINCT users.id as user_id, users.username, users.profile_picture 
                                        FROM comments 
                                        JOIN users ON comments.user_id = users.id 
                                        WHERE comments.$col = $id 
                                        ORDER BY comments.created_at DESC");
    
    if (mysqli_num_rows($comments_query) > 0) {
        while ($commenter = mysqli_fetch_assoc($comments_query)) {
            $profile_pic = !empty($commenter['profile_picture']) ? $commenter['profile_picture'] : 'uploads/profile.jpg';
            echo '<li style="display: flex; align-items: center; gap: 10px; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); list-style: none;">';
            echo '<a href="profile.php?id=' . $commenter['user_id'] . '" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; width: 100%;">';
            echo '<img src="' . htmlspecialchars($profile_pic) . '" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #00f2ea;" onerror="this.src=\'uploads/profile.jpg\'">';
            echo '<span style="font-weight: 600;">' . htmlspecialchars($commenter['username']) . '</span>';
            echo '</a>';
            echo '</li>';
        }
    } else {
        echo '<li style="padding: 20px; text-align: center; color: #888; list-style: none;">لا يوجد تعليقات حتى الآن</li>';
    }
} else {
    echo '<li style="padding: 20px; text-align: center; color: #ff0050; list-style: none;">خطأ: المعرف غير موجود</li>';
}
?>
