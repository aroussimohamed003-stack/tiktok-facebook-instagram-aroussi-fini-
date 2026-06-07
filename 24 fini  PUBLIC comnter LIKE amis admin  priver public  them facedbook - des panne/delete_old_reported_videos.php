<?php
include("config.php");

// Select videos that were reported more than 48 hours ago
$query = mysqli_query($con, "SELECT id, location FROM videos WHERE status = 'signale' AND reported_at < NOW() - INTERVAL 48 HOUR");

if (mysqli_num_rows($query) > 0) {
    while ($row = mysqli_fetch_assoc($query)) {
        $video_id = $row['id'];
        $file_path = $row['location'];

        // Delete video from database
        mysqli_query($con, "DELETE FROM videos WHERE id = $video_id");

        // Delete video file from server
        if (file_exists($file_path)) {
            unlink($file_path);
            echo "Deleted file: " . $file_path . "\n";
        } else {
            echo "File not found, but deleted from DB: " . $file_path . "\n";
        }
        echo "Deleted video with ID: " . $video_id . "\n";
    }
} else {
    echo "No old reported videos to delete.\n";
}

mysqli_close($con);
?>