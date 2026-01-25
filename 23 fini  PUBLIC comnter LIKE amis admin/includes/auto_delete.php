<?php
function checkAndCleanReportedVideos($con) {
    // Select videos that were reported more than 48 hours ago
    // We use DATE_SUB or INTERVAL syntax compatible with MySQL
    $query = mysqli_query($con, "SELECT id, location FROM videos WHERE status = 'signale' AND reported_at < NOW() - INTERVAL 48 HOUR");

    if (!$query) {
        // Query failed (e.g. table/column doesn't exist yet)
        // Log error or ignore, but don't crash
        return; 
    }

    if (mysqli_num_rows($query) > 0) {
        while ($row = mysqli_fetch_assoc($query)) {
            $video_id = $row['id'];
            $file_path = $row['location'];

            // Delete video from database
            mysqli_query($con, "DELETE FROM videos WHERE id = $video_id");

            // Delete video from video_views (cascade should handle this, but good to be safe if not)
            // Note: DB FK usually handles this, but we'll stick to logic.
            // If FK is ON DELETE CASCADE, we don't need to manually delete from child tables, 
            // but in the analysis we saw attempts to fix FKs. We will rely on the main delete.
            
            // Delete video file from server
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
}

function checkAndCleanStories($con) {
    // Select stories created more than 48 hours ago
    // Use PHP time to ensure we are using the application server's clock, which corresponds to the "51h" seen by the user.
    // Select stories created more than 48 hours ago using DB time for consistency
    $query = mysqli_query($con, "SELECT id, file_path FROM stories WHERE created_at < NOW() - INTERVAL 48 HOUR");

    if (!$query) {
        return;
    }

    if (mysqli_num_rows($query) > 0) {
        while ($row = mysqli_fetch_assoc($query)) {
            $story_id = $row['id'];
            $file_path = $row['file_path'];

            // Manually delete dependent records to be safe (in case CASCADE is missing)
            mysqli_query($con, "DELETE FROM story_views WHERE story_id = $story_id");
            mysqli_query($con, "DELETE FROM story_comments WHERE story_id = $story_id");
            
            // Delete notifications related to this story
            mysqli_query($con, "DELETE FROM notifications WHERE (type = 'comment' AND post_id = $story_id) OR (type = 'story' AND post_id = $story_id)");

            // Delete story from database
            mysqli_query($con, "DELETE FROM stories WHERE id = $story_id");

            // Delete story file from server
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
}
?>
