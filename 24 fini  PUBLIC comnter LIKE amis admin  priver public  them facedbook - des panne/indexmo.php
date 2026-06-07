<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("config.php");
mysqli_set_charset($con, "utf8mb4");
include("includes/auto_delete.php");
include("includes/remember_me.php");

// Trigger auto-delete check for old reported videos
checkAndCleanReportedVideos($con);
// Trigger auto-delete check for old stories
checkAndCleanStories($con);

// معالجة تسجيل الخروج
if (isset($_GET['logout'])) {
    // Clear remember me cookie
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
    }

    // Clear DB token
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        mysqli_query($con, "UPDATE users SET remember_token = NULL WHERE id = $uid");
    }

    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// إنشاء الجداول إذا لم تكن موجودة
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS is_sponsor TINYINT(1) DEFAULT 0");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS reported_at TIMESTAMP NULL");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS views INT DEFAULT 0");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS status ENUM('active', 'signale') DEFAULT 'active'");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS privacy ENUM('public', 'private') DEFAULT 'public'");
mysqli_query($con, "ALTER TABLE posts ADD COLUMN IF NOT EXISTS privacy ENUM('public', 'private') DEFAULT 'public'");
// Add remember_token to users table
mysqli_query($con, "ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) NULL");

mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS video_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        user_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    )
");

// تصحيح علاقة المفتاح الأجنبي إذا كانت خطأ (تشير إلى videoss بدلاً من videos)
// هذا الكود سيحاول حذف القيد القديم وإضافة القيد الصحيح
try {
    $db_name = $dbname;
    $check_fk = mysqli_query($con, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'video_views' AND COLUMN_NAME = 'video_id' AND REFERENCED_TABLE_NAME = 'videoss' AND TABLE_SCHEMA = '$db_name'");
    if (mysqli_num_rows($check_fk) > 0) {
        $row = mysqli_fetch_assoc($check_fk);
        $fk_name = $row['CONSTRAINT_NAME'];
        mysqli_query($con, "ALTER TABLE video_views DROP FOREIGN KEY $fk_name");
        mysqli_query($con, "ALTER TABLE video_views ADD CONSTRAINT video_views_fk_videos FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE");
    }
} catch (Exception $e) {
    // Ignore errors if constraint doesn't exist
}

// Ensure video_views references videos table correctly if it was created without constraints or with wrong ones
try {
     // Check if we need to add the constraint (if it refers to nothing or videos) can be complex, 
     // but mainly we want to ensure it works for 'videos'. 
     // For simplicity in this environment, we'll assume the above block fixes the 'videoss' issue.
     // Also ensuring the column types match
     mysqli_query($con, "ALTER TABLE video_views MODIFY video_id INT(11) NOT NULL");
} catch (Exception $e) {}


// إنشاء جدول الإعجابات إذا لم يكن موجودًا
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS video_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id),
        UNIQUE KEY unique_like (video_id, user_id)
    )
");

// Create notification system tables
mysqli_query($con, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NOT NULL,
    type ENUM('like', 'comment', 'message', 'friend_request', 'friend_accepted') NOT NULL,
    post_id INT DEFAULT NULL,
    video_id INT DEFAULT NULL,
    message_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Update existing table if necessary
mysqli_query($con, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NOT NULL,
    type ENUM('like', 'comment', 'message', 'friend_request', 'friend_accepted') NOT NULL,
    post_id INT DEFAULT NULL,
    video_id INT DEFAULT NULL,
    message_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure columns exist (if table already existed)
mysqli_query($con, "ALTER TABLE notifications MODIFY COLUMN type ENUM('like', 'comment', 'message', 'friend_request', 'friend_accepted') NOT NULL");
if (!mysqli_num_rows(mysqli_query($con, "SHOW COLUMNS FROM notifications LIKE 'post_id'"))) {
    mysqli_query($con, "ALTER TABLE notifications ADD COLUMN post_id INT DEFAULT NULL AFTER type");
}
if (!mysqli_num_rows(mysqli_query($con, "SHOW COLUMNS FROM notifications LIKE 'video_id'"))) {
    mysqli_query($con, "ALTER TABLE notifications ADD COLUMN video_id INT DEFAULT NULL AFTER post_id");
}

// Check if video_id exists in notifications, if not add it
$check_col = mysqli_query($con, "SHOW COLUMNS FROM notifications LIKE 'video_id'");
if(mysqli_num_rows($check_col) == 0) {
    mysqli_query($con, "ALTER TABLE notifications ADD COLUMN video_id INT DEFAULT NULL");
}

mysqli_query($con, "CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT DEFAULT NULL,
    post_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create story_views table
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS story_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        user_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
    )
");

// Create story_comments table
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS story_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");
// Force conversion for existing table
mysqli_query($con, "ALTER TABLE story_comments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Add Music Columns to stories table
mysqli_query($con, "ALTER TABLE stories ADD COLUMN IF NOT EXISTS music_url VARCHAR(255) NULL");
mysqli_query($con, "ALTER TABLE stories ADD COLUMN IF NOT EXISTS music_title VARCHAR(255) NULL");
mysqli_query($con, "ALTER TABLE stories ADD COLUMN IF NOT EXISTS music_artist VARCHAR(255) NULL");
mysqli_query($con, "ALTER TABLE stories ADD COLUMN IF NOT EXISTS music_image VARCHAR(255) NULL");

// Create friends table
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS friends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_request (sender_id, receiver_id),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    endpoint TEXT NOT NULL,
    p256dh TEXT,
    auth TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(255))
)");

// Handle Add Story Comment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_story_comment') {
    if (isset($_SESSION['user_id'])) {
        $story_id = intval($_POST['story_id']);
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        if (!empty($comment)) {
            $stmt = $con->prepare("INSERT INTO story_comments (story_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $story_id, $user_id, $comment);
            if ($stmt->execute()) {
                 // Notify story owner
                 $s_query = mysqli_query($con, "SELECT user_id FROM stories WHERE id = $story_id");
                 if ($s_query && $s_row = mysqli_fetch_assoc($s_query)) {
                     $recipient = $s_row['user_id'];
                     if ($recipient != $user_id) {
                         mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type, post_id) VALUES ($recipient, $user_id, 'comment', $story_id)"); 
                     }
                 }
                 echo json_encode(['success' => true]);
            } else {
                 echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        }
    }
    exit();
}

// Handle Get Story Comments
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_story_comments') {
    $story_id = intval($_GET['story_id']);
    $query = "SELECT sc.id, sc.comment, sc.created_at, sc.user_id, u.username, u.profile_picture 
              FROM story_comments sc
              JOIN users u ON sc.user_id = u.id
              WHERE sc.story_id = $story_id
              ORDER BY sc.created_at ASC";
    $result = mysqli_query($con, $query);
    $comments = [];
    while($row = mysqli_fetch_assoc($result)){
        $comments[] = $row;
    }
    echo json_encode(['success' => true, 'comments' => $comments, 'current_user_id' => $_SESSION['user_id'] ?? 0]);
    exit();
}

// Handle Delete Story Comment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_story_comment') {
    if (isset($_SESSION['user_id'])) {
        $comment_id = intval($_POST['comment_id']);
        $user_id = $_SESSION['user_id'];
        
        // Allow deletion if user owns the comment OR owns the story
        $check = mysqli_query($con, "SELECT sc.id, sc.story_id, sc.user_id as comment_owner, s.user_id as story_owner 
                                     FROM story_comments sc 
                                     JOIN stories s ON sc.story_id = s.id 
                                     WHERE sc.id = $comment_id");
        $row = mysqli_fetch_assoc($check);
        
        if ($row) {
            if ($row['story_owner'] == $user_id || $row['comment_owner'] == $user_id) {
                 mysqli_query($con, "DELETE FROM story_comments WHERE id = $comment_id");
                 echo json_encode(['success' => true]);
            } else {
                 echo json_encode(['success' => false, 'error' => 'Permission denied']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Comment not found']);
        }
    }
    exit();
}

// ---------------------- VIDEO COMMENTS LOGIC (NEW) ----------------------

// Handle Add Video Comment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_video_comment') {
    if (isset($_SESSION['user_id'])) {
        $video_id = intval($_POST['video_id']);
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        if (!empty($comment)) {
            $stmt = $con->prepare("INSERT INTO comments (video_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
            // Check if column is post_id or video_id in comments table if schema varies, but standard is video_id based on previous reads
            $stmt->bind_param("iis", $video_id, $user_id, $comment);
            if ($stmt->execute()) {
                 // Notify video owner
                 $v_query = mysqli_query($con, "SELECT user_id FROM videos WHERE id = $video_id");
                 if ($v_query && $v_row = mysqli_fetch_assoc($v_query)) {
                     $recipient = $v_row['user_id'];
                     if ($recipient != $user_id) {
                         mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type, video_id) VALUES ($recipient, $user_id, 'comment', $video_id)"); 
                     }
                 }
                 echo json_encode(['success' => true]);
            } else {
                 echo json_encode(['success' => false, 'error' => 'Database error: ' . $con->error]);
            }
        }
    }
    exit();
}

// Handle Get Video Comments
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_video_comments') {
    $video_id = intval($_GET['video_id']);
    $query = "SELECT c.id, c.comment, c.created_at, c.user_id, u.username, u.profile_picture 
              FROM comments c
              JOIN users u ON c.user_id = u.id
              WHERE c.video_id = $video_id
              ORDER BY c.created_at DESC";
    $result = mysqli_query($con, $query);
    $comments = [];
    while($row = mysqli_fetch_assoc($result)){
        $comments[] = $row;
    }
    echo json_encode(['success' => true, 'comments' => $comments, 'current_user_id' => $_SESSION['user_id'] ?? 0]);
    exit();
}

// Handle Delete Video Comment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_video_comment') {
    if (isset($_SESSION['user_id'])) {
        $comment_id = intval($_POST['comment_id']);
        $user_id = $_SESSION['user_id'];
        
        // Verify ownership
        $check = mysqli_query($con, "SELECT user_id FROM comments WHERE id = $comment_id");
        $row = mysqli_fetch_assoc($check);
        
        if ($row && $row['user_id'] == $user_id) {
             mysqli_query($con, "DELETE FROM comments WHERE id = $comment_id");
             echo json_encode(['success' => true]);
        } else {
             echo json_encode(['success' => false, 'error' => 'Permission denied']);
        }
    }
    exit();
}


// Record Story View Logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_story_view'])) {
    if (isset($_SESSION['user_id'])) {
        $story_id = intval($_POST['story_id']);
        $user_id = $_SESSION['user_id'];
        
        // Prevent duplicate views check
        $check = mysqli_query($con, "SELECT id FROM story_views WHERE story_id = $story_id AND user_id = $user_id");
        if(mysqli_num_rows($check) == 0){
            mysqli_query($con, "INSERT INTO story_views (story_id, user_id) VALUES ($story_id, $user_id)");
        }
    }
    echo json_encode(['success' => true]);
    exit();
}

// Get Story Viewers Logic
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['get_story_viewers'])) {
    $story_id = intval($_GET['get_story_viewers']);
    $query = "SELECT u.username, u.profile_picture 
              FROM story_views sv
              JOIN users u ON sv.user_id = u.id
              WHERE sv.story_id = $story_id
              ORDER BY sv.viewed_at DESC";
    $result = mysqli_query($con, $query);
    $viewers = [];
    while($row = mysqli_fetch_assoc($result)){
        $viewers[] = $row;
    }
    echo json_encode(['success' => true, 'viewers' => $viewers]);
    exit();
}

// معالجة حذف الفيديو
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_video_id'])) {
    $video_id = intval($_POST['delete_video_id']);
    $user_id = $_SESSION['user_id'] ?? 0;

    // التحقق من أن المستخدم هو صاحب الفيديو قبل الحذف
    $query = mysqli_query($con, "SELECT location FROM videos WHERE id = $video_id AND user_id = $user_id");
    $row = mysqli_fetch_assoc($query);

    if ($row) {
        $file_path = $row['location'];

        // تنظيف الجداول المرتبطة
        mysqli_query($con, "DELETE FROM comments WHERE video_id = $video_id");
        mysqli_query($con, "DELETE FROM video_likes WHERE video_id = $video_id");
        mysqli_query($con, "DELETE FROM video_views WHERE video_id = $video_id");
        mysqli_query($con, "DELETE FROM notifications WHERE video_id = $video_id");

        // حذف الفيديو من قاعدة البيانات
        mysqli_query($con, "DELETE FROM videos WHERE id = $video_id");
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        header("Location: indexmo.php?deleted=1");
        exit();
    }
}

// معالجة تحديث المشاهدات
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_views_id'])) {
    $video_id = intval($_POST['update_views_id']);

    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $check_view = mysqli_query($con, "SELECT * FROM video_views WHERE video_id = $video_id AND user_id = $user_id LIMIT 1");

        if (mysqli_num_rows($check_view) == 0) {
            mysqli_query($con, "INSERT INTO video_views (video_id, user_id) VALUES ($video_id, $user_id)");
            mysqli_query($con, "UPDATE videos SET views = views + 1 WHERE id = $video_id");
        }
    }

    $result = mysqli_query($con, "SELECT views FROM videos WHERE id = $video_id");
    $row = mysqli_fetch_assoc($result);
    echo json_encode(['views' => $row['views']]);
    exit();
}

// Get Video Viewers Logic (NEW)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['get_video_viewers'])) {
    $video_id = intval($_GET['get_video_viewers']);
    $query = "SELECT u.username, u.profile_picture 
              FROM video_views vv
              JOIN users u ON vv.user_id = u.id
              WHERE vv.video_id = $video_id
              GROUP BY u.id
              ORDER BY MAX(vv.viewed_at) DESC";
    $result = mysqli_query($con, $query);
    $viewers = [];
    while($row = mysqli_fetch_assoc($result)){
        $viewers[] = $row;
    }
    echo json_encode(['success' => true, 'viewers' => $viewers]);
    exit();
}

// معالجة الإعجاب بالفيديو
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['like_video_id'])) {
    $video_id = intval($_POST['like_video_id']);
    $response = ['success' => false];

    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $check_like = mysqli_query($con, "SELECT * FROM video_likes WHERE video_id = $video_id AND user_id = $user_id LIMIT 1");

        if (mysqli_num_rows($check_like) == 0) {
            // إضافة إعجاب جديد
            mysqli_query($con, "INSERT INTO video_likes (video_id, user_id) VALUES ($video_id, $user_id)");
            mysqli_query($con, "UPDATE videos SET likes = likes + 1 WHERE id = $video_id");
            $response['action'] = 'added';
            
            // Notify video owner
            $v_query = mysqli_query($con, "SELECT user_id FROM videos WHERE id = $video_id");
            if ($v_query && $v_row = mysqli_fetch_assoc($v_query)) {
                $recipient = $v_row['user_id'];
                mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type, video_id) VALUES ($recipient, $user_id, 'like', $video_id)");
            }

        } else {
            // إزالة الإعجاب
            mysqli_query($con, "DELETE FROM video_likes WHERE video_id = $video_id AND user_id = $user_id");
            mysqli_query($con, "UPDATE videos SET likes = likes - 1 WHERE id = $video_id");
            $response['action'] = 'removed';
        }

        $result = mysqli_query($con, "SELECT likes FROM videos WHERE id = $video_id");
        $row = mysqli_fetch_assoc($result);
        $response['likes'] = $row['likes'];
        $response['success'] = true;
    }

    echo json_encode($response);
    exit();
}

// معالجة الإبلاغ عن الفيديو
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signal_video_id'])) {
    $video_id = intval($_POST['signal_video_id']);
    mysqli_query($con, "UPDATE videos SET status = 'signale', reported_at = NOW() WHERE id = $video_id");
    // Message is handled on client side before submission or we can return back with message param
    header("Location: indexmo.php?msg=reported");
    exit();
}

// Get Story View Count Logic
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['get_story_view_count'])) {
    $story_id = intval($_GET['get_story_view_count']);
    $query = "SELECT COUNT(*) as count FROM story_views WHERE story_id = $story_id";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($result);
    echo json_encode(['count' => $row['count']]);
    exit();
}

// معالجة حذف الستوري
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_story_id'])) {
    if (isset($_SESSION['user_id'])) {
        $story_id = intval($_POST['delete_story_id']);
        $user_id = $_SESSION['user_id'];
        
        // Verify ownership
        $query = mysqli_query($con, "SELECT file_path FROM stories WHERE id = $story_id AND user_id = $user_id");
        $row = mysqli_fetch_assoc($query);
        
        if ($row) {
            $file_path = $row['file_path'];
            mysqli_query($con, "DELETE FROM stories WHERE id = $story_id");
            mysqli_query($con, "DELETE FROM story_views WHERE story_id = $story_id"); // Cleanup views
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            header("Location: indexmo.php?story_deleted=1");
            exit();
        }
    }
}

// Upload Story Logic (Ported from story.php)
if (isset($_POST['upload_story']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get Music Data
    $music_url = isset($_POST['music_url']) ? $_POST['music_url'] : null;
    $music_title = isset($_POST['music_title']) ? $_POST['music_title'] : null;
    $music_artist = isset($_POST['music_artist']) ? $_POST['music_artist'] : null;
    $music_image = isset($_POST['music_image']) ? $_POST['music_image'] : null;

    // ... basic validation ...
    if (!isset($_FILES['story_file']) || $_FILES['story_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = "يرجى اختيار ملف صالح.";
    } else {
        $file_name = $_FILES['story_file']['name'];
        $file_tmp_name = $_FILES['story_file']['tmp_name'];
        $file_size = $_FILES['story_file']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_video_ext = ['mp4', 'mov', 'avi', 'webm', 'mkv'];

        $max_file_size = 50 * 1024 * 1024; // 50MB

        if ($file_size > $max_file_size) {
            $upload_error = "حجم الملف كبير جداً.";
        } elseif (in_array($ext, $allowed_image_ext)) {
            $uploadDir = "uploads/stories/images/";
            $story_type = "image";
        } elseif (in_array($ext, $allowed_video_ext)) {
            $uploadDir = "uploads/stories/videos/";
            $story_type = "video";
        } else {
            $upload_error = "نوع الملف غير مدعوم.";
        }

        if (!isset($upload_error)) {
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            if (!is_dir('uploads/stories')) mkdir('uploads/stories', 0755, true);
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $newFileName = uniqid() . "_" . time() . "." . $ext;
            $targetFile = $uploadDir . $newFileName;

            if (move_uploaded_file($file_tmp_name, $targetFile)) {
                $stmt = mysqli_prepare($con, "INSERT INTO stories (file_path, file_type, user_id, music_url, music_title, music_artist, music_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssissss", $targetFile, $story_type, $user_id, $music_url, $music_title, $music_artist, $music_image);
                if (mysqli_stmt_execute($stmt)) {
                    header("Location: indexmo.php?story_uploaded=1");
                    exit();
                } else {
                    $upload_error = "فشل في حفظ الستوري.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $upload_error = "فشل في رفع الملف.";
            }
        }
    }
}


// Ensure reported_at column exists
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS reported_at TIMESTAMP NULL");

// جلب الفيديوهات النشطة
$orderBy = "ORDER BY RAND()";
if (isset($_GET['video_id'])) {
    $vid = intval($_GET['video_id']);
    $orderBy = "ORDER BY (videos.id = $vid) DESC, RAND()";
}

$my_id = $_SESSION['user_id'] ?? 0;
// Make the feed public like TikTok: Show all active videos, but prioritize sessions or randomness
// Friend Logic for Videos
$friend_ids_list = "0";
if ($my_id > 0) {
    // Get confirmed friends
    $f_ids = [$my_id]; // Include self
    $f_query = mysqli_query($con, "SELECT sender_id, receiver_id FROM friends WHERE (sender_id = $my_id OR receiver_id = $my_id) AND status = 'accepted'");
    if ($f_query) {
        while($frow = mysqli_fetch_assoc($f_query)) {
            $f_ids[] = ($frow['sender_id'] == $my_id) ? $frow['receiver_id'] : $frow['sender_id'];
        }
    }
    $friend_ids_list = implode(',', $f_ids);
}

// Filter: Show video IF (is_sponsor = 1) OR (privacy = 'public') OR (user_id is in friend list)
// Also ensure status is active
$friend_condition = "AND videos.status = 'active' AND (videos.is_sponsor = 1 OR videos.privacy = 'public' OR videos.user_id IN ($friend_ids_list))";

$fetchAllVideos = mysqli_query($con, "SELECT videos.*, videos.user_id AS v_user_id, users.username, users.profile_picture,
                                     (SELECT COUNT(*) FROM comments WHERE video_id = videos.id) AS comments_count
                                   FROM videos
                                   JOIN users ON videos.user_id = users.id
                                   WHERE 1=1
                                   $friend_condition
                                   $orderBy");

if (!$fetchAllVideos) {
    die("Error fetching videos: " . mysqli_error($con));
}

// Active stories query using DB time - Only from friends
$my_id = $_SESSION['user_id'] ?? 0;
$story_friend_condition = "";
if ($my_id > 0) {
    $story_friend_condition = "AND (u.id = $my_id OR u.id IN (SELECT sender_id FROM friends WHERE receiver_id = $my_id AND status = 'accepted') OR u.id IN (SELECT receiver_id FROM friends WHERE sender_id = $my_id AND status = 'accepted'))";
} else {
    // If not logged in, show no stories or only public ones if we had a flag. 
    // For now, let's say stories are friend-only.
    $story_friend_condition = "AND 1=0"; 
}

$users_with_stories_query = "
    SELECT DISTINCT u.id, u.username, u.profile_picture
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at > NOW() - INTERVAL 48 HOUR
    $story_friend_condition
    ORDER BY s.created_at DESC
";
$stock_stories_result = mysqli_query($con, $users_with_stories_query);

if (!$stock_stories_result) {
    die("Error fetching stories: " . mysqli_error($con));
}

?>
<?php

// Set page title
$pageTitle = "عرض الفيديوهات";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
        body {
            padding-top: 20px !important; /* Fixed the massive gap */
            background-color: #f8fafc;
            color: #334155;
        }
        @media (min-width: 992px) {
            body {
                padding-top: 30px !important;
            }
        }

            max-width: 1400px;
            margin: 0 auto;
        }
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding-top: 10px;
        }
        @media (max-width: 991px) {
            .container-fluid {
                padding-top: 20px;
            }
        }
        .bg-dark {
            background-color: #242526 !important;
        }
        .text-white {
            color: #e4e6eb !important;
        }
        .border-secondary {
            border-color: #3e4042 !important;
        }

.video-scroller {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    scroll-snap-type: y mandatory;
    overflow-y: scroll;
    height: calc(100vh - 70px);
}
.video-item {
    scroll-snap-align: start;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px 0;
}
.video-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    border-radius: 15px;
    overflow: hidden;
}
.video-player {
    width: 100%;
    height: auto;
    border-radius: 15px;
}
.video-footer {
    position: absolute;
    bottom: 20px;
    left: 20px;
    color: white;
    background-color: rgba(0, 0, 0, 0.5);
    padding: 10px;
    border-radius: 5px;
    max-width: 90%;
    pointer-events: none;
    z-index: 1002;
}
.video-footer * {
    pointer-events: auto;
}
.profile-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    margin-left: 10px;
}
.action-buttons {
    position: absolute;
    right: 20px;
    bottom: 100px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    z-index: 1005;
    pointer-events: auto;
}
.stories-header {
    z-index: 5;
    position: relative;
}
.story-user-card {
    cursor: pointer;
    z-index: 6;
    position: relative;
    pointer-events: auto !important;
}
.action-btn {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    text-align: center;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.2s, filter 0.2s;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}
.action-btn:hover {
    transform: scale(1.1);
    filter: drop-shadow(0 0 5px rgba(254, 44, 85, 0.8));
}
.action-btn i {
    display: block;
    margin-bottom: 2px;
    font-size: 26px;
}
.action-btn span {
    font-size: 13px;
    font-weight: bold;
    color: #fff;
}
.action-btn.liked i {
    color: #FE2C55;
    animation: heartBeat 0.3s ease-in-out;
}
@keyframes heartBeat {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1); }
}
.comment-btn {
    position: relative;
}
.comment-count-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #FE2C55;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    font-weight: bold;
    border: 2px solid #000;
}
.action-btn i {
    filter: drop-shadow(0 0 2px rgba(0,0,0,0.8));
}
.upload-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    background-color: var(--btn-primary-bg);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 50%;
    font-size: 24px;
    width: 60px;
    height: 60px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    transition: transform 0.3s ease, background-color 0.3s ease;
}
.upload-btn:hover {
    transform: scale(1.1);
}
.viewers-popup, .likes-popup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: var(--card-bg);
    color: var(--text-color);
    padding: 20px;
    border-radius: 10px;
    max-height: 80vh;
    overflow-y: auto;
    z-index: 99999;
    width: 80%;
    max-width: 400px;
    box-shadow: 0 5px 15px var(--shadow-color);
    pointer-events: auto;
}
.viewers-popup ul, .likes-popup ul {
    list-style-type: none;
    padding: 0;
}
.viewers-popup li, .likes-popup li {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}
.close-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: var(--text-color);
    font-size: 20px;
    cursor: pointer;
}
.name-comont{
    text-decoration: none;
    color: white;
}


/* ستايلات الملف الشخصي - Premium Design */
.profile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.98);
    z-index: 20000; 
    overflow-y: auto;
    padding: 0;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.profile-content {
    background-color: transparent;
    color: #fff;
    max-width: 800px;
    margin: 0 auto;
    padding-top: 50px;
    padding-bottom: 50px;
    position: relative;
    min-height: 100%;
}

.profile-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-bottom: 40px;
    padding: 20px;
}

.profile-picture-container {
    position: relative;
    margin-bottom: 15px;
}

.profile-picture-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 0 20px rgba(254, 44, 85, 0.5);
    background: #222;
}

.profile-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 15px;
    font-size: 14px;
}

.profile-stat-item {
    display: flex;
    flex-direction: column;
}

.profile-stat-value {
    font-weight: bold;
    font-size: 18px;
    color: #FE2C55;
}

.profile-stat-label {
    color: #aaa;
    font-size: 12px;
}

.profile-bio {
    max-width: 400px;
    margin: 15px auto;
    color: #ccc;
    font-size: 14px;
    line-height: 1.5;
}

.profile-videos-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    padding: 0 15px;
    border-bottom: 1px solid #333;
    padding-bottom: 10px;
    color: #fff;
    text-align: right;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-videos-title i {
    color: #FE2C55;
}

.profile-videos {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    padding: 2px;
}

@media (min-width: 768px) {
    .profile-videos {
        grid-template-columns: repeat(4, 1fr);
        gap: 5px;
        padding: 10px;
    }
    .profile-picture-img {
        width: 150px;
        height: 150px;
    }
}

.profile-video {
    width: 100%;
    aspect-ratio: 9/16;
    background-color: #111;
    overflow: hidden;
    position: relative;
    cursor: pointer;
    transition: transform 0.2s;
}

.profile-video:hover {
    transform: scale(1.02);
    z-index: 1;
}

.profile-video video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-video-overlay {
    position: absolute;
    bottom: 5px;
    right: 5px;
    color: white;
    font-size: 12px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.8);
    display: flex;
    align-items: center;
    gap: 3px;
    background: rgba(0,0,0,0.3);
    padding: 2px 5px;
    border-radius: 4px;
}

.close-profile {
    position: fixed;
    top: 20px;
    left: 20px;
    font-size: 30px;
    color: #fff;
    cursor: pointer;
    z-index: 20002;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(5px);
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.close-profile:hover {
    background: rgba(255, 255, 255, 0.2);
}

.profile-link {
    cursor: pointer;
    font-weight: 600;
    transition: color 0.2s;
}

.profile-link:hover {
    color: #FE2C55 !important;
}

.liked {
    color: #FE2C55 !important;
}

.viewers-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.viewer-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--btn-primary-bg, #a777e3);
}
.no-data {
    text-align: center;
    color: #ccc;
    padding: 20px 0;
    justify-content: center;
}
.sponsor-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background-color: #ffc107;
    color: #000;
    padding: 5px 10px;
    border-radius: 5px;
    font-weight: bold;
    z-index: 10;
    font-size: 14px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
}

/* Stories Styles */
/* Stories Styles */
.stories-header {
    background: rgba(18, 18, 18, 0.95);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    /* Ensure it sits below the navbar */
    position: relative;
    padding: 10px 0;
    margin-top: 0;
}

@media (max-width: 768px) {
    .stories-header {
        /* Adjust for mobile navbar if needed, though simple flow layout usually handles it best if navbar is fixed */
        padding-top: 5px; 
    }
}


.stories-list {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding: 0 15px;
    scrollbar-width: none;
}
.stories-list::-webkit-scrollbar {
    display: none;
}

.story-user-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: transform 0.2s;
    min-width: 80px;
}
.story-user-card:hover {
    transform: scale(1.05);
}

.story-avatar-ring {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    padding: 3px;
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.story-add-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: transform 0.2s;
    min-width: 80px;
    position: relative;
}
.story-add-card:hover {
    transform: scale(1.05);
}

.story-add-icon {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background-color: #222;
    border: 2px dashed var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 24px;
}

/* Upload Modal Styles */
#uploadStoryModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 6000;
    justify-content: center;
    align-items: center;
}
.upload-story-card {
    background: #222;
    padding: 30px;
    border-radius: 20px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    border: 1px solid #333;
}


.story-user-avatar {
    width: 59px;
    height: 59px;
    border-radius: 50%;
    border: 3px solid #000;
    object-fit: cover;
}

.story-user-name {
    font-size: 11px;
    margin-top: 5px;
    color: #ccc;
    max-width: 70px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

/* Story Viewer Modal */
.story-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #000;
    z-index: 20005; /* Higher than navbar 10000 */
}

/* Viewers Button in Story */
.story-viewers-btn {
    position: absolute;
    bottom: 20px;
    left: 20px;
    color: white;
    background: rgba(0,0,0,0.5);
    padding: 8px 15px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.3);
    cursor: pointer;
    z-index: 5020;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Viewers List Modal (Inside Story) */
.story-viewers-modal {
    display: none;
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 50%;
    background: #1a1a1a;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    z-index: 5030;
    padding: 20px;
    overflow-y: auto;
    color: white;
    transition: transform 0.3s slide-up;
}

/* Loading Overlay Style */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.85);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: white;
}

/* Comments Modal (Inside Story) */
.story-comments-modal {
    display: none;
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 60%;
    background: #1a1a1a;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    z-index: 5040; /* Above viewers modal */
    padding: 0;
    color: white;
    flex-direction: column;
}
.story-comments-modal.active {
    display: flex !important;
}
.story-comments-header {
    padding: 15px;
    border-bottom: 1px solid #333;
    text-align: center;
    font-weight: bold;
    position: relative;
}
.story-comments-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}
.story-comment-item {
    display: flex;
    margin-bottom: 15px;
    animation: fadeIn 0.3s;
}
.story-comment-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}
.story-comment-content {
    background: #333;
    padding: 8px 12px;
    border-radius: 15px;
    max-width: 80%;
    font-size: 13px;
    position: relative;
}
.story-comment-user {
    font-weight: bold;
    font-size: 12px;
    color: #ccc;
    margin-bottom: 2px;
}
.story-comment-text {
    word-break: break-word;
}
.story-comment-delete {
    font-size: 10px;
    color: #ff4d4d;
    cursor: pointer;
    margin-left: 5px;
    padding: 2px;
}
.story-comments-footer {
    padding: 10px;
    border-top: 1px solid #333;
    display: flex;
    align-items: center;
    background: #1a1a1a;
}
.story-comment-input {
    flex: 1;
    background: #333;
    border: none;
    padding: 10px;
    border-radius: 20px;
    color: white;
    outline: none;
}
.story-comment-send {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 20px;
    margin-left: 10px;
    cursor: pointer;
}

.story-comments-btn {
    position: absolute;
    bottom: 20px;
    left: 140px; /* Right of viewers btn */
    color: white;
    background: rgba(0,0,0,0.5);
    padding: 8px 15px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.3);
    cursor: pointer;
    z-index: 5020;
    display: flex;
    align-items: center;
    gap: 5px;
}
.loading-spinner-large {
    width: 60px;
    height: 60px;
    border: 5px solid rgba(255,255,255,0.3);
    border-top: 5px solid var(--primary-color, #a777e3);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}


.story-content {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.story-media-full {
    max-width: 100%;
    width: 500px; /* Constrain width on desktop */
    max-height: 85vh; /* Do not fill entire height */
    object-fit: contain;
    border-radius: 12px;
    background: #000;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

.story-progress {
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    display: flex;
    gap: 5px;
    z-index: 5010;
}
.story-progress-bar {
    flex: 1;
    height: 2px;
    background: rgba(255,255,255,0.3);
    border-radius: 2px;
}
.story-progress-fill {
    height: 100%;
    background: #fff;
    width: 0;
}

.story-viewer-header {
    position: absolute;
    top: 25px;
    left: 0;
    width: 100%;
    padding: 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 5010;
    color: #fff;
}
        
.user-info-story {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-info-story img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
}
.user-info-story span {
    font-weight: 600;
    font-size: 14px;
}
.story-time {
    font-size: 12px;
    opacity: 0.7;
    margin-right: 8px;
}

.nav-area {
    position: absolute;
    top: 0;
    height: 100%;
    width: 30%;
    z-index: 5005;
}
.nav-prev { left: 0; }
.nav-next { right: 0; }

.music-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    cursor: pointer;
    transition: background 0.2s;
}
.music-item:hover {
    background: rgba(255,255,255,0.05);
}
.music-cover {
    width: 50px;
    height: 50px;
    border-radius: 5px;
    object-fit: cover;
}
.music-info {
    flex: 1;
}
.music-title {
    color: white;
    font-size: 14px;
    font-weight: 500;
}
.music-artist {
    color: #aaa;
    font-size: 12px;
}
.play-preview-btn {
    background: none;
    border: none;
    color: #a777e3;
    font-size: 24px;
    cursor: pointer;
    transition: transform 0.2s;
}
.play-preview-btn:hover {
    transform: scale(1.2);
}
'; ?>

<?php
// Include layout start
include("includes/layout_start.php");
?>

<!-- BEGIN: Stories Hook -->
<section class="mb-8 overflow-hidden" data-purpose="stories-container">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-900">Stories</h2>
        <button class="text-xs font-semibold text-blue-500 hover:underline">Watch All</button>
    </div>
    
    <div class="flex gap-4 overflow-x-auto pb-4 -mx-1 px-1 scrollbar-hide no-scrollbar">
        <!-- Add Story Button -->
        <button onclick="document.getElementById('uploadStoryModal').style.display = 'flex'" class="flex-shrink-0 flex flex-col items-center gap-2 group">
            <div class="w-16 h-16 rounded-full border-2 border-dashed border-slate-200 flex items-center justify-center group-hover:border-blue-500 group-hover:bg-blue-50 transition-all duration-300">
                <i class="fas fa-plus text-slate-400 group-hover:text-blue-500"></i>
            </div>
            <span class="text-[11px] font-medium text-slate-500 group-hover:text-slate-900">Add Story</span>
        </button>

        <?php if ($stock_stories_result && mysqli_num_rows($stock_stories_result) > 0): ?>
            <?php while ($user = mysqli_fetch_assoc($stock_stories_result)): ?>
                <?php 
                $story_count_query = "SELECT COUNT(*) as count FROM stories WHERE user_id = {$user['id']} AND created_at > NOW() - INTERVAL 48 HOUR";
                $count_result = mysqli_query($con, $story_count_query);
                $story_count = $count_result ? mysqli_fetch_assoc($count_result)['count'] : 0;
                ?>
                
                <?php if ($story_count > 0): ?>
                    <button onclick="viewUserStories(<?= $user['id'] ?>)" class="flex-shrink-0 flex flex-col items-center gap-2 group">
                        <div class="relative w-16 h-16 p-0.5 rounded-full bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 transition-transform group-hover:scale-105 active:scale-95">
                            <div class="w-full h-full rounded-full border-2 border-white overflow-hidden bg-slate-100">
                                <img src="<?= str_replace('profile_pictures', 'profiles', !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profile.jpg') ?>" 
                                     class="w-full h-full object-cover"
                                     onerror="this.src='uploads/profile.jpg'">
                            </div>
                        </div>
                        <span class="text-[11px] font-medium text-slate-500 group-hover:text-slate-900 truncate w-16 text-center">
                            <?= htmlspecialchars($user['username']) ?>
                        </span>
                    </button>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="flex items-center justify-center w-full py-4 text-slate-400 italic text-xs">No active stories</div>
        <?php endif; ?>
    </div>
</section>
<!-- END: Stories Hook -->

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner-large"></div>
        <h3 id="loadingText">جاري رفع القصة...</h3>
        <p id="loadingSubText">يرجى الانتظار بينما نقوم بمعالجة الوسائط الخاصة بك</p>
    </div>

    <!-- Upload Story Modal -->
    <div id="uploadStoryModal" onclick="if(event.target === this) this.style.display = 'none'">
        <div class="upload-story-card">
            <h3 style="color: white; margin-bottom: 10px;">إضافة قصة جديدة</h3>
            <p style="color: #bbb; font-size: 13px; margin-bottom: 20px;">
                أقصى مدة للفيديو: 60 ثانية | أقصى حجم: 50 ميجابايت
            </p>
            <?php if(isset($upload_error)): ?>
                <div class="alert alert-danger"><?= $upload_error ?></div>
            <?php endif; ?>
            <form id="uploadStoryForm" method="post" enctype="multipart/form-data">
                <input type="file" id="storyFileInput" name="story_file" class="form-control mb-3" accept="image/*,video/*" required>
                
                <button type="button" class="btn btn-info w-100 mb-3" onclick="toggleMusicSearch()">إضافة موسيقى 🎵</button>

                <div id="musicSearchContainer" style="display:none; margin-bottom:15px; background:#333; padding:10px; border-radius:10px;">
                    <div style="display:flex; gap:5px;">
                        <input type="text" id="musicSearchInput" class="form-control" placeholder="بحث عن أغنية أو فنان...">
                        <button type="button" class="btn btn-primary" onclick="searchMusic()">بحث</button>
                    </div>
                    <div id="musicSearchResults" style="margin-top:10px; max-height:150px; overflow-y:auto;"></div>
                </div>

                <div id="selectedMusicDisplay" style="display:none; color:#a777e3; margin-bottom:10px; font-size:14px; background: rgba(167, 119, 227, 0.1); padding: 10px; border-radius: 10px; text-align: left;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <span style="display:flex; align-items:center; gap:5px;">🎵 <span id="selectedMusicTitle" style="font-weight:bold;"></span></span>
                        <button type="button" class="btn btn-sm text-danger" onclick="removeMusic()" style="text-decoration:none; font-size:20px; padding:0; line-height:1;">&times;</button>
                    </div>
                    <audio id="selectedMusicPlayer" controls style="width: 100%; height: 35px; border-radius: 5px;"></audio>
                </div>

                <input type="hidden" name="music_url" id="music_url">
                <input type="hidden" name="music_title" id="music_title">
                <input type="hidden" name="music_artist" id="music_artist">
                <input type="hidden" name="music_image" id="music_image">

                <div id="aiWarning" class="alert alert-danger" style="display:none; font-size:12px;">
                    <strong>تم حظر المحتوى:</strong> تم اكتشاف محتوى غير لائق بواسطة الذكاء الاصطناعي.
                </div>
                <!-- Hidden input to ensure PHP detects the submission when submitted via JS .submit() -->
                <input type="hidden" name="upload_story" value="1">
                <button type="submit" class="btn btn-primary w-100">نشر الآن</button>
                <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="document.getElementById('uploadStoryModal').style.display = 'none'; resetUploadForm();">إلغاء</button>
            </form>
        </div>
    </div>

    <!-- Story Viewer Modal -->
    <div id="storyModal" class="story-modal">
        <div class="story-progress" id="storyProgress"></div>

        <div class="story-viewer-header">
            <div class="user-info-story">
                <img id="modalUserAvatar" src="uploads/profile.jpg" alt="" onerror="this.src='uploads/profile.jpg'">
                <div>
                    <span id="modalUsername">اسم المستخدم</span>
                    <span id="modalTime" class="story-time">2h</span>
                </div>
            </div>
            <button class="close-btn" onclick="closeStoryModal()" style="font-size: 30px; z-index: 5020;">&times;</button>
        </div>

        <div class="story-content">
            <div class="nav-area nav-prev" onclick="prevStory()"></div>
            <div class="nav-area nav-next" onclick="nextStory()"></div>
            <div id="storyMediaContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"></div>
            
            <!-- Viewers Button -->
            <div class="story-viewers-btn" id="storyViewersBtn" onclick="toggleStoryViewers()">
                <i class="fas fa-eye"></i> <span id="storyViewCount">0</span>
            </div>
        </div>

        <!-- Viewers List Sheet -->
        <div class="story-viewers-modal" id="storyViewersModal">
            <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
                <h4>تمت المشاهدة بواسطة</h4>
                <span onclick="toggleStoryViewers()" style="cursor:pointer; font-size:20px;">&times;</span>
            </div>
            <ul id="storyViewersList" style="list-style:none; padding:0;">
                <!-- Viewers injected here -->
            </ul>
        </div>

        <!-- Comments Modal -->
        <div class="story-comments-modal" id="storyCommentsModal">
            <div class="story-comments-header">
                التعليقات
                <span onclick="toggleStoryComments()" style="position:absolute; right:15px; top:15px; cursor:pointer;">&times;</span>
            </div>
            <div class="story-comments-list" id="storyCommentsList">
                <!-- Comments injected here -->
            </div>
            <div class="story-comments-footer">
                <input type="text" id="storyCommentInput" class="story-comment-input" placeholder="أضف تعليقاً...">
                <button class="story-comment-send" onclick="postStoryComment()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <!-- Video Comments Modal (Copied/Adapted from Story Comments) -->
    <div class="story-comments-modal" id="videoCommentsModal" style="z-index: 20006; position: fixed;">
        <div class="story-comments-header">
            التعليقات
            <span onclick="toggleVideoComments()" style="position:absolute; right:15px; top:15px; cursor:pointer;">&times;</span>
        </div>
        <div class="story-comments-list" id="videoCommentsList">
            <!-- Comments injected here -->
        </div>
        <div class="story-comments-footer">
            <input type="text" id="videoCommentInput" class="story-comment-input" placeholder="أضف تعليقاً...">
            <button class="story-comment-send" onclick="postVideoComment()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <!-- Video Viewers Modal (New) -->
    <div class="story-comments-modal" id="videoViewersModal" style="z-index: 20007; position: fixed;">
        <div class="story-comments-header">
            المشاهدات
            <span onclick="toggleVideoViewers()" style="position:absolute; right:15px; top:15px; cursor:pointer;">&times;</span>
        </div>
        <div class="story-comments-list" id="videoViewersList">
            <!-- Viewers injected here -->
        </div>
    </div>


    <!-- قسم الملف الشخصي (سيتم ملؤه بالجافاسكريبت) -->
    <div class="profile-overlay" id="profileOverlay">
        <span class="close-profile" onclick="closeProfile()">&times;</span>
        <div class="profile-content" id="profileContent">
            <!-- المحتوى سيتم إضافته هنا بالجافاسكريبت -->
        </div>
    </div>

    <!-- قسم الفيديوهات الرئيسي -->
    <!-- BEGIN: Main Feed -->
    <div class="space-y-6" id="mainContent" data-purpose="video-feed">
        <?php while ($row = mysqli_fetch_assoc($fetchAllVideos)): ?>
            <?php
            $video_id = $row['id'];
            $location = $row['location'];
            $subject = $row['subject'];
            $views = $row['views'];
            $title = $row['title'];

            $video_owner_id = $row['v_user_id'];
            $username = $row['username'];
            $profile_picture = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
            $display_profile_picture = str_replace('profile_pictures', 'profiles', $profile_picture);

            // Fetch viewers
            $viewers_query = mysqli_query($con, "SELECT users.username, users.profile_picture FROM video_views
                                               JOIN users ON video_views.user_id = users.id
                                               WHERE video_views.video_id = $video_id
                                               GROUP BY users.id
                                               ORDER BY MAX(video_views.viewed_at) DESC LIMIT 5");
            $viewers_data = [];
            while ($viewer = mysqli_fetch_assoc($viewers_query)) {
                $viewers_data[] = $viewer;
            }

            // Check if liked
            $liked = false;
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $check_like = mysqli_query($con, "SELECT * FROM video_likes WHERE video_id = $video_id AND user_id = $user_id LIMIT 1");
                $liked = mysqli_num_rows($check_like) > 0;
            }
            $likes_count = $row['likes'] ?? 0;
            $isYouTube = (strpos($location, 'youtube.com') !== false || strpos($location, 'youtu.be') !== false);
            ?>

            <article class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden group/post transition-all duration-300 hover:shadow-md" data-purpose="feed-post">
                <!-- Post Header -->
                <div class="p-4 flex items-center justify-between border-b border-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="relative group cursor-pointer" onclick="showProfile(<?= $video_owner_id ?>, '<?= addslashes($username) ?>', '<?= $display_profile_picture ?>')">
                            <img src="<?= $display_profile_picture ?>" class="w-10 h-10 rounded-full border border-slate-100 object-cover transition-transform group-hover:scale-105" onerror="this.src='uploads/profile.jpg'">
                            <?php if (isset($row['is_sponsor']) && $row['is_sponsor'] == 1): ?>
                                <div class="absolute -bottom-1 -right-1 bg-yellow-400 text-[8px] font-bold text-white px-1.5 py-0.5 rounded-full border-2 border-white">AD</div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-sm flex items-center gap-1">
                                <?= htmlspecialchars($username) ?>
                                <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">Recently posted • <i class="fas fa-globe-americas"></i></p>
                        </div>
                    </div>
                    <button class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-50 hover:text-slate-600 transition-all">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>

                <!-- Video Content Container -->
                <div class="relative bg-slate-900 flex items-center justify-center overflow-hidden aspect-[16/9] sm:aspect-video group/media">
                    <?php if ($isYouTube): 
                        $ytId = "";
                        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $location, $match)) { $ytId = $match[1]; }
                    ?>
                        <iframe src="https://www.youtube.com/embed/<?= $ytId ?>?autoplay=0&controls=1&rel=0" frameborder="0" allowfullscreen class="w-full h-full"></iframe>
                    <?php else: ?>
                        <video src="<?= $location ?>" class="w-full h-full object-contain cursor-pointer video-player" data-id="<?= $video_id ?>" loop muted playsinline></video>
                        
                        <!-- Sound Toggle Button -->
                        <button onclick="event.stopPropagation(); toggleVideoMute(this, '<?= $video_id ?>')" class="absolute top-4 right-4 z-20 w-10 h-10 bg-black/30 backdrop-blur-md rounded-full flex items-center justify-center text-white/80 hover:bg-black/50 transition-all focus:outline-none">
                            <i class="fas fa-volume-mute text-sm mute-icon-<?= $video_id ?>"></i>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($isYouTube): ?>
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/media:opacity-100 transition-opacity pointer-events-none">
                            <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center text-white text-2xl">
                                <i class="fas fa-play ml-1"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Interaction & Caption Area -->
                <div class="p-4 md:p-5">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-5">
                            <button class="flex items-center gap-1.5 text-slate-500 hover:text-rose-500 transition-colors <?= $liked ? 'text-rose-500' : '' ?> group/btn" data-video-id="<?= $video_id ?>" onclick="toggleLike(<?= $video_id ?>, this)">
                                <i class="fas fa-heart text-xl group-active/btn:scale-125 transition-transform"></i>
                                <span class="text-xs font-bold" id="likes-<?= $video_id ?>"><?= $likes_count ?></span>
                            </button>
                            <button class="flex items-center gap-1.5 text-slate-500 hover:text-blue-500 transition-colors group/btn" onclick="openVideoComments(<?= $video_id ?>)">
                                <i class="fas fa-comment text-xl group-active/btn:scale-125 transition-transform"></i>
                                <span class="text-xs font-bold" id="comments-count-<?= $video_id ?>"><?= $row['comments_count'] ?? 0 ?></span>
                            </button>
                            <button class="flex items-center gap-1.5 text-slate-500 hover:text-emerald-500 transition-colors group/btn" onclick="openShareModal(<?= $video_id ?>, 'video')">
                                <i class="fas fa-share-alt text-xl group-active/btn:scale-125 transition-transform"></i>
                            </button>
                        </div>
                        <button class="text-slate-400 hover:text-blue-500 transition-colors">
                            <i class="fas fa-bookmark text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div class="flex -space-x-2 overflow-hidden mb-2 cursor-pointer hover:opacity-80 transition-opacity" onclick="openVideoViewers(<?= $video_id ?>)">
                            <?php foreach ($viewers_data as $viewer): ?>
                                <img src="<?= !empty($viewer['profile_picture']) ? $viewer['profile_picture'] : 'uploads/profile.jpg' ?>" class="inline-block h-5 w-5 rounded-full ring-2 ring-white object-cover" onerror="this.src='uploads/profile.jpg'">
                            <?php endforeach; ?>
                            <?php if ($views > count($viewers_data)): ?>
                                <span class="flex items-center justify-center h-5 w-5 rounded-full ring-2 ring-white bg-slate-100 text-[8px] font-bold text-slate-500">+<?= $views - count($viewers_data) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-sm leading-relaxed">
                            <span class="font-bold text-slate-900 mr-1"><?= htmlspecialchars($username) ?></span> 
                            <span class="text-slate-600"><?= htmlspecialchars($subject) ?></span>
                        </div>
                        
                        <?php if (!empty($title)): ?>
                            <div class="px-3 py-2 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-[11px] text-slate-500 italic"><i class="fas fa-music mr-1"></i> <?= htmlspecialchars($title) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="pt-3 mt-3 border-t border-slate-50 flex items-center justify-between">
                            <a href="<?= $isYouTube ? 'javascript:void(0)' : $location ?>" <?= $isYouTube ? 'onclick="alert(\'YouTube videos cannot be downloaded directly.\')"' : 'download' ?> class="text-[11px] font-bold text-blue-500 hover:text-blue-700 uppercase tracking-wider">
                                <i class="fas fa-download mr-1"></i> Download
                            </a>
                            <form action="indexmo.php" method="POST" onsubmit="return confirm('Report this video?');">
                                <input type="hidden" name="signal_video_id" value="<?= $video_id ?>">
                                <button type="submit" class="text-[11px] font-bold text-slate-400 hover:text-red-500 transition-colors uppercase tracking-wider focus:outline-none">
                                    <i class="fas fa-flag mr-1"></i> Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
    <!-- END: Main Feed -->
    </div>

    <!-- BEGIN: Floating Action Button -->
    <a href="uplod-profile.php" class="fixed bottom-8 right-8 w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg shadow-blue-500/30 flex items-center justify-center hover:scale-110 active:scale-95 transition-all duration-300 z-40">
        <i class="fas fa-plus text-xl"></i>
    </a>

<script>
    // Pass PHP session user ID to JS
    window.currentUserId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;
</script>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-none rounded-[2rem] overflow-hidden shadow-2xl">
            <div class="modal-header border-none p-6 pb-0">
                <h5 class="modal-title font-bold text-slate-800">Share with</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="list-group list-group-flush" id="friends-share-list">
                    <!-- Loaded via AJAX -->
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <button class="w-full py-3 rounded-2xl bg-slate-50 text-slate-600 font-bold text-sm hover:bg-slate-100 transition-all flex items-center justify-center gap-2" onclick="navigator.share ? navigator.share({url: window.location.href}) : copyToClipboard(window.location.href)">
                        <i class="fas fa-link"></i> Copy Link
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NSFW JS Library -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/nsfwjs@2.4.0/dist/nsfwjs.min.js"></script>

<?php
// Add custom actions JS to be included in footer after jQuery
$additionalJs = ['js/custom_actions.js?v=' . time()];

// Include layout end
include("includes/layout_end.php");
?>