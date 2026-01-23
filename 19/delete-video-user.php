<?php
session_start();
include("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_video_id'])) {
    $video_id = intval($_POST['delete_video_id']);
    $user_id = $_SESSION['user_id'];

    // جلب مسار الفيديو من قاعدة البيانات
    $query = mysqli_query($con, "SELECT location FROM videos WHERE id = $video_id AND user_id = $user_id");
    $row = mysqli_fetch_assoc($query);

    if ($row) {
        $file_path = $row['location'];

        // حذف الفيديو من قاعدة البيانات
        mysqli_query($con, "DELETE FROM videos WHERE id = $video_id AND user_id = $user_id");

        // حذف الملف من المجلد
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        header("Location: index.php");
        exit();
    }
}
?>