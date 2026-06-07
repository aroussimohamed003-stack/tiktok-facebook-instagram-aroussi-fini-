<?php
session_start();
include("config.php");

// التحقق من وجود معرف الفيديو أو المنشور
if (isset($_GET['video_id']) || isset($_GET['post_id'])) {
    if (isset($_GET['video_id'])) {
        $id = intval($_GET['video_id']);
        $table = "video_likes";
        $col = "video_id";
    } else {
        $id = intval($_GET['post_id']);
        $table = "post_likes";
        $col = "post_id";
    }
    
    // جلب المستخدمين الذين أعجبوا
    $likes_query = mysqli_query($con, "SELECT users.id as user_id, users.username, users.profile_picture 
                                     FROM $table 
                                     JOIN users ON $table.user_id = users.id 
                                     WHERE $table.$col = $id 
                                     ORDER BY $table.created_at DESC");
    
    if (mysqli_num_rows($likes_query) > 0) {
        while ($liker = mysqli_fetch_assoc($likes_query)) {
            $profile_pic = !empty($liker['profile_picture']) ? $liker['profile_picture'] : 'uploads/profile.jpg';
            echo '<li style="display: flex; align-items: center; gap: 10px; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); list-style: none;">';
            echo '<a href="profile.php?id=' . $liker['user_id'] . '" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; width: 100%;">';
            echo '<img src="' . htmlspecialchars($profile_pic) . '" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #00f2ea;" onerror="this.src=\'uploads/profile.jpg\'">';
            echo '<span style="font-weight: 600;">' . htmlspecialchars($liker['username']) . '</span>';
            echo '</a>';
            echo '</li>';
        }
    } else {
        echo '<li style="padding: 20px; text-align: center; color: #888; list-style: none;">لا يوجد إعجابات حتى الآن</li>';
    }
} else {
    echo '<li style="padding: 20px; text-align: center; color: #ff0050; list-style: none;">خطأ: المعرف غير موجود</li>';
}
?>

