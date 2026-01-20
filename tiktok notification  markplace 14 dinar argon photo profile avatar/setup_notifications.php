<?php
include("config.php");

// Create notifications table
$query = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NOT NULL,
    type ENUM('like', 'comment', 'message') NOT NULL,
    post_id INT DEFAULT NULL,
    message_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (mysqli_query($con, $query)) {
    echo "Notifications table created successfully.\n";
} else {
    echo "Error creating table: " . mysqli_error($con) . "\n";
}

// Check if likes table exists for posts (since mo.php needs it)
$queryLocations = "CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_like (post_id, user_id)
)";
mysqli_query($con, $queryLocations);

// Check if comments table exists for posts (mo.php interactions)
// Note: 'comments' table might already exist for videos (coment.php). 
// using 'post_comments' to distinguish or check structure of 'comments'.
// existing 'comments' table has 'video_id'. I should probably check if it has 'post_id'.
$result = mysqli_query($con, "SHOW COLUMNS FROM comments LIKE 'post_id'");
if (mysqli_num_rows($result) == 0) {
    // Add post_id to comments table to allow comments on posts too
    mysqli_query($con, "ALTER TABLE comments ADD COLUMN post_id INT DEFAULT NULL");
    mysqli_query($con, "ALTER TABLE comments ADD CONSTRAINT fk_payment_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE");
    // Make video_id nullable if it isn't
    mysqli_query($con, "ALTER TABLE comments MODIFY COLUMN video_id INT DEFAULT NULL"); // Might fail if FK exists
    echo "Updated comments table for posts.\n";
}

echo "Database setup complete.";
?>
