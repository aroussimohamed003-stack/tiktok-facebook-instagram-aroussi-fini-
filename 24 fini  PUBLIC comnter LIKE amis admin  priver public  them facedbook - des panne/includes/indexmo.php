<?php
session_start();
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
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS views INT DEFAULT 0");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS status ENUM('active', 'signale') DEFAULT 'active'");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS likes INT DEFAULT 0");
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
    type ENUM('like', 'comment', 'message') NOT NULL,
    post_id INT DEFAULT NULL,
    video_id INT DEFAULT NULL,
    message_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

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

// جلب الفيديوهات النشطة
$orderBy = "ORDER BY RAND()";
if (isset($_GET['video_id'])) {
    $vid = intval($_GET['video_id']);
    $orderBy = "ORDER BY (videos.id = $vid) DESC, RAND()";
}

$fetchAllVideos = mysqli_query($con, "SELECT videos.*, videos.user_id AS v_user_id, users.username, users.profile_picture
                                   FROM videos
                                   JOIN users ON videos.user_id = users.id
                                   WHERE videos.status = 'active' OR (videos.status = 'signale' AND videos.reported_at > NOW() - INTERVAL 48 HOUR)
                                   $orderBy");

if (!$fetchAllVideos) {
    die("Error fetching videos: " . mysqli_error($con));
}

// Active stories query using DB time
$users_with_stories_query = "
    SELECT DISTINCT u.id, u.username, u.profile_picture
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at > NOW() - INTERVAL 48 HOUR
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
            padding-top: 70px;
        }

.video-scroller {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    scroll-snap-type: y mandatory;
    overflow-y: scroll;
    height: 100vh;
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
}
.action-btn {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    text-align: center;
    cursor: pointer;
}
.action-btn i {
    display: block;
    margin-bottom: 5px;
}
.action-btn span {
    font-size: 12px;
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
    z-index: 1000;
    width: 80%;
    max-width: 400px;
    box-shadow: 0 5px 15px var(--shadow-color);
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

/* ستايلات الملف الشخصي */
.profile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
    z-index: 2000;
    overflow-y: auto;
    padding: 20px;
}
.profile-content {
    background-color: var(--card-bg);
    color: var(--text-color);
    border-radius: 10px;
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}
.profile-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}
.profile-picture {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin-left: 20px;
}
.profile-info {
    flex: 1;
}
.profile-videos {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
.profile-video {
    width: 100%;
    aspect-ratio: 9/16;
    background-color: #222;
    border-radius: 5px;
    overflow: hidden;
}
.profile-video video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.close-profile {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 30px;
    color: var(--text-color);
    cursor: pointer;
    z-index: 2001;
}
.profile-link {
    cursor: pointer;
}
.liked {
    color: #ff4d4d !important;
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
    z-index: 5000;
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
    max-height: 100vh;
    object-fit: contain;
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
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>

<!-- Import TensorFlow.js and NSFWJS -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.11.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/nsfwjs@2.4.1/dist/nsfwjs.min.js"></script>

    <!-- Stories Section -->
    <div class="stories-header">
        <div class="stories-list">
            
            <!-- Add Story Button (First Item) -->
            <div class="story-add-card" onclick="document.getElementById('uploadStoryModal').style.display = 'flex'">
                <div class="story-add-icon">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="story-user-name">
                    Add Story
                </div>
            </div>

            <?php if ($stock_stories_result && mysqli_num_rows($stock_stories_result) > 0): ?>
                <?php while ($user = mysqli_fetch_assoc($stock_stories_result)): ?>
                    <?php 
                    $story_count_query = "SELECT COUNT(*) as count FROM stories WHERE user_id = {$user['id']} AND created_at > NOW() - INTERVAL 48 HOUR";
                    $count_result = mysqli_query($con, $story_count_query);
                    $story_count = $count_result ? mysqli_fetch_assoc($count_result)['count'] : 0;
                    ?>
                    
                    <?php if ($story_count > 0): ?>
                        <div class="story-user-card" onclick="viewUserStories(<?= $user['id'] ?>)">
                            <div class="story-avatar-ring">
                                <img src="<?= !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profile.jpg' ?>" 
                                        class="story-user-avatar"
                                        onerror="this.src='uploads/profile.jpg'">
                            </div>
                            <div class="story-user-name">
                                <?= htmlspecialchars($user['username']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted w-100 text-center" style="font-size: 14px; padding: 10px;">لا توجد قصص نشطة</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner-large"></div>
        <h3 id="loadingText">Uploading Story...</h3>
        <p id="loadingSubText">Please wait while we process your media</p>
    </div>

    <!-- Upload Story Modal -->
    <div id="uploadStoryModal" onclick="if(event.target === this) this.style.display = 'none'">
        <div class="upload-story-card">
            <h3 style="color: white; margin-bottom: 10px;">Add to Story</h3>
            <p style="color: #bbb; font-size: 13px; margin-bottom: 20px;">
                Max video duration: 60s | Max size: 50MB
            </p>
            <?php if(isset($upload_error)): ?>
                <div class="alert alert-danger"><?= $upload_error ?></div>
            <?php endif; ?>
            <form id="uploadStoryForm" method="post" enctype="multipart/form-data">
                <input type="file" id="storyFileInput" name="story_file" class="form-control mb-3" accept="image/*,video/*" required>
                
                <button type="button" class="btn btn-info w-100 mb-3" onclick="toggleMusicSearch()">Add Music 🎵</button>

                <div id="musicSearchContainer" style="display:none; margin-bottom:15px; background:#333; padding:10px; border-radius:10px;">
                    <div style="display:flex; gap:5px;">
                        <input type="text" id="musicSearchInput" class="form-control" placeholder="Search song or artist...">
                        <button type="button" class="btn btn-primary" onclick="searchMusic()">Search</button>
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
                    <strong>Content blocked:</strong> Inappropriate content detected by AI.
                </div>
                <!-- Hidden input to ensure PHP detects the submission when submitted via JS .submit() -->
                <input type="hidden" name="upload_story" value="1">
                <button type="submit" class="btn btn-primary w-100">Post Now</button>
                <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="document.getElementById('uploadStoryModal').style.display = 'none'; resetUploadForm();">Cancel</button>
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
                    <span id="modalUsername">Username</span>
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
                <h4>Viewed by</h4>
                <span onclick="toggleStoryViewers()" style="cursor:pointer; font-size:20px;">&times;</span>
            </div>
            <ul id="storyViewersList" style="list-style:none; padding:0;">
                <!-- Viewers injected here -->
            </ul>
        </div>

        <!-- Comments Modal -->
        <div class="story-comments-modal" id="storyCommentsModal">
            <div class="story-comments-header">
                Comments
                <span onclick="toggleStoryComments()" style="position:absolute; right:15px; top:15px; cursor:pointer;">&times;</span>
            </div>
            <div class="story-comments-list" id="storyCommentsList">
                <!-- Comments injected here -->
            </div>
            <div class="story-comments-footer">
                <input type="text" id="storyCommentInput" class="story-comment-input" placeholder="Add a comment...">
                <button class="story-comment-send" onclick="postStoryComment()"><i class="fas fa-paper-plane"></i></button>
            </div>
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
    <div class="video-scroller" id="mainContent">
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

                        // جلب المشاهدين مع الصور الشخصية
$viewers_query = mysqli_query($con, "SELECT users.username, users.profile_picture FROM video_views
                                                           JOIN users ON video_views.user_id = users.id
                                                           WHERE video_views.video_id = $video_id
                                                           GROUP BY users.id
                                                           ORDER BY MAX(video_views.viewed_at) DESC");
                        $viewers_data = [];
                        while ($viewer = mysqli_fetch_assoc($viewers_query)) {
                            $viewers_data[] = $viewer;
                        }
                        ?>

                        <div class="video-item">
                            <div class="video-container">
                                <?php if (isset($row['is_sponsor']) && $row['is_sponsor'] == 1): ?>
                                    <div class="sponsor-badge">Sponsor</div>
                                <?php endif; ?>
                                <video src="<?= $location ?>" class="video-player" data-id="<?= $video_id ?>" loop muted></video>

                                <div class="action-buttons">
                                    <button class="action-btn viewers-btn" data-video-id="<?= $video_id ?>">
                                        <i class="fas fa-eye"></i>
                                        <span id="views-<?= $video_id ?>"><?= $views ?></span>
                                    </button>
                                    <?php
                                    // التحقق مما إذا كان المستخدم قد أعجب بالفيديو
                                    $liked = false;
                                    if (isset($_SESSION['user_id'])) {
                                        $user_id = $_SESSION['user_id'];
                                        $check_like = mysqli_query($con, "SELECT * FROM video_likes WHERE video_id = $video_id AND user_id = $user_id LIMIT 1");
                                        $liked = mysqli_num_rows($check_like) > 0;
                                    }

                                    // جلب عدد الإعجابات
                                    $likes_count = $row['likes'] ?? 0;
                                    ?>
                                    <div class="action-btn" style="cursor: default; display: flex; flex-direction: column; align-items: center;">
                                        <i class="fas fa-heart like-trigger <?= $liked ? 'liked' : '' ?>" data-video-id="<?= $video_id ?>" style="cursor: pointer; font-size: 24px;"></i>
                                        <span class="likes-count-trigger" data-video-id="<?= $video_id ?>" id="likes-<?= $video_id ?>" style="cursor: pointer; font-size: 12px; margin-top: 5px;"><?= $likes_count ?></span>
                                    </div>
                                    <a href="<?= $location ?>" download class="action-btn">
                                        <i class="fas fa-download"></i>
                                        <span>تحميل</span>
                                    </a>
                                    <a href="coment.php?video_id=<?= $video_id ?>" class="action-btn">
                                        <i class="fas fa-comment"></i>
                                        <span>تعليق</span>
                                    </a>
                                    <form action="indexmo.php" method="POST" class="action-btn" onsubmit="return confirm('هل أنت متأكد أنك تريد الإبلاغ عن هذا الفيديو؟ سيتم حذفه تلقائيًا بعد 48 ساعة.');">
                                        <input type="hidden" name="signal_video_id" value="<?= $video_id ?>">
                                        <button type="submit" style="background: none; border: none; color: white;">
                                            <i class="fas fa-flag"></i>
                                            <span>إبلاغ</span>
                                        </button>
                                    </form>
                                </div>

                                <div class="video-footer">
                                    <div class="d-flex align-items-center mb-2">
                                        <img src="<?= $profile_picture ?>" class="profile-img" onerror="this.src='uploads/profile.jpg'">
                                        <span class="profile-link" onclick="showProfile(<?= $video_owner_id ?>, '<?= addslashes($username) ?>', '<?= $profile_picture ?>')">
                                            <?= htmlspecialchars($username) ?>
                                        </span>
                                    </div>
                                    <p><?= htmlspecialchars($subject) ?></p>
                                    <p><?= htmlspecialchars($title) ?></p>
                                </div>
                            </div>

                            <div class="viewers-popup" id="viewers-popup-<?= $video_id ?>">
                                <button class="close-btn">&times;</button>
                                <h4>المشاهدون (<?= count($viewers_data) ?>)</h4>
                                <ul class="viewers-list">
                                    <?php foreach ($viewers_data as $viewer): ?>
                                        <?php 
                                            $viewer_pic = !empty($viewer['profile_picture']) ? $viewer['profile_picture'] : 'uploads/profile.jpg';
                                        ?>
                                        <li>
                                            <img src="<?= $viewer_pic ?>" alt="Profile" class="viewer-img" onerror="this.src='uploads/profile.jpg'">
                                            <span><?= htmlspecialchars($viewer['username']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($viewers_data)): ?>
                                        <li class="no-data">لا يوجد مشاهدون حتى الآن</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                <!-- نافذة الإعجابات -->
                <div class="likes-popup" id="likes-popup-<?= $video_id ?>">
                    <button class="close-btn">&times;</button>
                    <h4>الإعجابات</h4>
                    <ul id="likes-list-<?= $video_id ?>">
                        <?php
                        // جلب المستخدمين الذين أعجبوا بالفيديو
                        $likes_query = mysqli_query($con, "SELECT users.username, users.profile_picture FROM video_likes
                                                       JOIN users ON video_likes.user_id = users.id
                                                       WHERE video_likes.video_id = $video_id
                                                       ORDER BY video_likes.created_at DESC");
                        $likers = [];
                        while ($liker = mysqli_fetch_assoc($likes_query)) {
                            $profile_pic = !empty($liker['profile_picture']) ? $liker['profile_picture'] : 'uploads/profile.jpg';
                            echo '<li style="display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">';
                            echo '<img src="' . htmlspecialchars($profile_pic) . '" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #00f2ea;" onerror="this.src=\'uploads/profile.jpg\'">';
                            echo '<span>' . htmlspecialchars($liker['username']) . '</span>';
                            echo '</li>';
                        }

                        if (mysqli_num_rows($likes_query) == 0) {
                            echo '<li>لا يوجد إعجابات حتى الآن</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <a href="uplod-profile.php" class="upload-btn">
        <i class="fas fa-plus"></i>
    </a>

<script>
    // Pass PHP session user ID to JS
    window.currentUserId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;
</script>
<?php
// Set inline JavaScript
$inlineJs = "
$(document).ready(function() {
    // معالجة المشاهدات
    $('video').each(function() {
        const video = this;
        const videoId = $(this).data('id');

        // عند تشغيل الفيديو
        $(video).on('play', function() {
            if (!$(this).data('viewed')) {
                $.ajax({
                    url: 'indexmo.php',
                    method: 'POST',
                    data: { update_views_id: videoId },
                    dataType: 'json',
                    success: function(data) {
                        $('#views-' + videoId).text(data.views);
                    }
                });

                $(video).data('viewed', true);
            }
        });

        // تشغيل/إيقاف عند النقر
        $(video).on('click', function() {
            if (video.paused) {
                video.play();
                video.muted = false;
            } else {
                video.pause();
            }
        });
    });

    // معالجة عرض المشاهدين
    $('.viewers-btn').click(function(e) {
        e.stopPropagation();
        const videoId = $(this).data('video-id');
        $('.likes-popup').hide();
        $('.viewers-popup').hide();
        $('#viewers-popup-' + videoId).show();
    });

    // معالجة الإعجابات (النقر على القلب)
    $(document).on('click', '.like-trigger', function(e) {
        e.stopPropagation();
        const videoId = $(this).data('video-id');
        const likeBtn = $(this);

        $.ajax({
            url: 'indexmo.php',
            method: 'POST',
            data: { like_video_id: videoId },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#likes-' + videoId).text(data.likes);

                    if (data.action === 'added') {
                        likeBtn.addClass('liked');
                    } else {
                        likeBtn.removeClass('liked');
                    }

                    // تحديث قائمة المعجبين
                    $.ajax({
                        url: 'get_likers.php',
                        method: 'GET',
                        data: { video_id: videoId },
                        success: function(response) {
                            $('#likes-list-' + videoId).html(response);
                        }
                    });
                }
            }
        });
    });

    // عرض قائمة المعجبين (النقر على الرقم)
    $(document).on('click', '.likes-count-trigger', function(e) {
        e.stopPropagation();
        const videoId = $(this).data('video-id');
        $('.likes-popup').hide();
        $('.viewers-popup').hide();
        $('#likes-popup-' + videoId).show();
    });

    // إغلاق النوافذ المنبثقة
    $('.close-btn').click(function() {
        $(this).closest('.viewers-popup, .likes-popup').hide();
    });

    $(document).click(function() {
        $('.viewers-popup, .likes-popup').hide();
    });

    // منع إغلاق النافذة عند النقر عليها
    $('.viewers-popup, .likes-popup').click(function(e) {
        e.stopPropagation();
    });

    // تشغيل الفيديو عند ظهوره في الشاشة
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target;
            if (entry.isIntersecting) {
                video.play();
            } else {
                video.pause();
            }
        });
    }, { threshold: 0.7 });

    $('video').each(function() {
        observer.observe(this);
    });
});

// عرض الملف الشخصي
function showProfile(userId, username, profilePicture) {
    // إظهار النافذة المنبثقة
    $('#profileOverlay').show();

    // إضافة محتوى تحميل مؤقت
    $('#profileContent').html('<div class=\"loading-spinner\"></div>');

    // جلب بيانات المستخدم والفيديوهات عبر AJAX
    $.ajax({
        url: 'get_profile.php',
        method: 'GET',
        cache: false,
        data: { user_id: userId },
        success: function(response) {
            // بناء HTML للملف الشخصي
            let profileHtml = '<div class=\"profile-header\">' +
                '<img src=\"' + profilePicture + '\" class=\"profile-picture\">' +
                '<div class=\"profile-info\">' +
                '<h3>' + username + '</h3>';

            if (response.bio) {
                profileHtml += '<p>' + response.bio + '</p>';
            }

            profileHtml += '</div></div>' +
                '<h4>فيديوهات المستخدم</h4>' +
                '<div class=\"profile-videos\">';

            // إضافة الفيديوهات
            if (response.videos && response.videos.length > 0) {
                for (let i = 0; i < response.videos.length; i++) {
                    profileHtml += '<div class=\"profile-video\">' +
                        '<video src=\"' + response.videos[i].location + '?t=' + new Date().getTime() + '\" loop muted playsinline webkit-playsinline></video>' +
                        '</div>';
                }
            } else {
                profileHtml += '<p>لا توجد فيديوهات</p>';
            }

            profileHtml += '</div>';

            // عرض المحتوى
            $('#profileContent').html(profileHtml);

            // إضافة حدث النقر لفيديوهات الملف الشخصي
            $('.profile-video video').click(function() {
                if (this.paused) {
                    this.play();
                    this.muted = false;
                } else {
                    this.pause();
                }
            });
        },
        error: function() {
            $('#profileContent').html('<p>حدث خطأ أثناء تحميل الملف الشخصي</p>');
        }
    });
}

// إغلاق الملف الشخصي
function closeProfile() {
    $('#profileOverlay').hide();
    // إيقاف تشغيل أي فيديو في الملف الشخصي
    $('#profileContent video').each(function() {
        this.pause();
    });
}

// Story Logic
let currentStories = [];
let currentStoryIndex = 0;
let progressInterval;
let storyDuration = 60000;

function viewUserStories(userId) {
    // Show loading or open modal
    $('#storyModal').show();
    $('#storyMediaContainer').html('<div class=\"spinner-border text-light\"></div>');
    
    fetch('get_user_stories.php?user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            if(data.success && data.stories.length > 0){
                currentStories = data.stories;
                currentStoryIndex = 0;
                openStoryModalUI();
            } else {
                alert('No stories found');
                $('#storyModal').hide();
            }
        })
        .catch(err => {
            console.error(err);
            $('#storyModal').hide();
        });
}

function openStoryModalUI() {
    $('#storyModal').show();
    renderStory();
}

function closeStoryModal() {
    $('#storyModal').hide();
    clearInterval(progressInterval);
    const video = document.querySelector('#storyMediaContainer video');
    if (video) video.pause();
    const audio = document.getElementById('storyBackgroundAudio');
    if (audio) { audio.pause(); audio.remove(); }
    currentStories = [];
}

function renderStory() {
    if (currentStoryIndex >= currentStories.length) {
        closeStoryModal();
        return;
    }
    
    const story = currentStories[currentStoryIndex];
    const container = document.getElementById('storyMediaContainer');
    const avatar = document.getElementById('modalUserAvatar');
    const username = document.getElementById('modalUsername');
    const timeLabel = document.getElementById('modalTime');
    const progressBarContainer = document.getElementById('storyProgress');
    
    // Update User Info
    avatar.src = story.profile_picture;
    username.innerText = story.username;
    
    // Calculate time ago
    const storyTime = new Date(story.created_at);
    const now = new Date();
    const diff = Math.floor((now - storyTime) / 1000 / 60); // minutes
    let timeText = diff + 'm';
    if (diff > 60) timeText = Math.floor(diff/60) + 'h';
    timeLabel.innerText = timeText;
    
    // Verify ownership and add delete button
    // Remove existing delete btn if any
    $('.story-delete-btn').remove();
    
    if (window.currentUserId && story.user_id == window.currentUserId) {
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'story-delete-btn';
        deleteBtn.innerHTML = '<i class=\"fas fa-trash\"></i>';
        deleteBtn.style.cssText = 'background:none; border:none; color:white; font-size:20px; cursor:pointer; margin-right:15px;';
        deleteBtn.onclick = function() { deleteStory(story.id); };
        
        // Append to header info
        document.querySelector('.user-info-story').appendChild(deleteBtn);
    }
    
    // Render Bars
    progressBarContainer.innerHTML = '';
    currentStories.forEach((s, index) => {
        const bar = document.createElement('div');
        bar.className = 'story-progress-bar';
        const fill = document.createElement('div');
        fill.className = 'story-progress-fill';
        fill.style.width = index < currentStoryIndex ? '100%' : '0%';
        if (index === currentStoryIndex) fill.id = 'currentProgressFill';
        bar.appendChild(fill);
        progressBarContainer.appendChild(bar);
    });
    
    // Stop any existing audio
    const oldAudio = document.getElementById('storyBackgroundAudio');
    if(oldAudio) { oldAudio.pause(); oldAudio.remove(); }

    // Render Media
    container.innerHTML = '';
    
    // Play Music if available
    if (story.music_url) {
        const audio = document.createElement('audio');
        audio.id = 'storyBackgroundAudio';
        audio.src = story.music_url;
        audio.loop = true;
        audio.volume = 0.5;
        audio.style.display = 'none';
        container.appendChild(audio);
        audio.play().catch(e => console.log('Audio autoplay blocked:', e));
        
        // Display Song Name (Clean, minimal)
        const musicLabel = document.createElement('div');
        musicLabel.className = 'story-music-label';
        musicLabel.innerHTML = '<i class=\"fas fa-music\"></i> <span>' + story.music_title + '</span>';
        musicLabel.style.cssText = 'position:absolute; top:75px; left:15px; color:white; background:rgba(0,0,0,0.3); padding:5px 10px; border-radius:15px; font-size:12px; z-index:5010; display:flex; align-items:center; gap:5px;';
        container.appendChild(musicLabel);
    }

    if (story.file_type === 'image') {
        const img = document.createElement('img');
        img.src = story.file_path;
        img.className = 'story-media-full';
        container.appendChild(img);
        startProgress(60000); // 60 seconds
    } else {
        const video = document.createElement('video');
        video.className = 'story-media-full';
        video.playsInline = true;
        video.autoplay = true;
        video.muted = true; // Start muted for guaranteed autoplay
        video.onended = nextStory;
        
        video.onloadedmetadata = () => {
            startProgress(video.duration * 1000);
            video.play().then(() => {
                // If no background music, try to unmute video
                if (!story.music_url) {
                    video.muted = false;
                }
            }).catch(e => {
                console.log('Autoplay failed, keeping muted:', e);
                video.muted = true;
                video.play();
            });
        };
        
        video.onerror = () => {
            console.error('Error loading story video');
            nextStory();
        };

        video.src = story.file_path;
        container.appendChild(video);
        
        // Manual fall-through if metadata is already available
        if (video.readyState >= 1) {
            startProgress(video.duration * 1000);
            video.play().catch(() => {});
        }
    }

    
    // Record view
    recordStoryView(story.id);
    updateViewersCount(story.id);

    // Setup Viewers List
    setupViewersList(story.id);

    // Add Comments Button dynamically
    $('.story-comments-btn').remove(); // remove old
    const commentsBtn = document.createElement('div');
    commentsBtn.className = 'story-comments-btn';
    commentsBtn.onclick = function() { toggleStoryComments(story.id); };
    commentsBtn.innerHTML = '<i class=\"fas fa-comment\"></i> <span id=\"storyCommentCount\">...</span>';
    container.parentElement.appendChild(commentsBtn);
    
    // Reset Comments Modal
    $('#storyCommentsList').html('');
    document.getElementById('storyCommentsModal').style.display = 'none';
    
    // Preload comment count
    updateCommentsCount(story.id);
}

function updateCommentsCount(storyId) {
    $.get('indexmo.php', {action: 'get_story_comments', story_id: storyId}, function(data){
        try {
            const res = JSON.parse(data);
             if(res.success) {
                 $('#storyCommentCount').text(res.comments.length);
             }
        } catch(e){}
    });
}

function toggleStoryComments(storyId) {
    const modal = document.getElementById('storyCommentsModal');
    // Save curretn story ID for posting
    if(storyId) modal.dataset.storyId = storyId;
    else storyId = modal.dataset.storyId; // Retrieve if not passed (closing)
    
    if (modal.style.display === 'flex') {
        modal.style.display = 'none';
        // Resume progress if needed
        const video = document.querySelector('#storyMediaContainer video');
        if (video) video.play();
    } else {
        modal.style.display = 'flex';
        // Pause story
        clearInterval(progressInterval);
        const video = document.querySelector('#storyMediaContainer video');
        if (video) video.pause();
        
        loadStoryComments(storyId);
    }
}

function loadStoryComments(storyId) {
    // Show loading
    $('#storyCommentsList').html('<div style=\'text-align:center; padding:20px; color:#999;\'>Loading...</div>');
    
    $.get('indexmo.php', {action: 'get_story_comments', story_id: storyId}, function(data){
        try {
            const res = JSON.parse(data);
            if(res.success) {
                renderCommentsList(res.comments, res.current_user_id);
                $('#storyCommentCount').text(res.comments.length);
            }
        } catch(e){
            console.error(e);
        }
    });
}

function renderCommentsList(comments, currentUserId) {
    if(comments.length === 0) {
        $('#storyCommentsList').html('<div style=\'text-align:center; padding:20px; color:#999;\'>No comments yet. Be the first!</div>');
        return;
    }
    
    let html = '';
    comments.forEach(c => {
        let pic = c.profile_picture ? c.profile_picture : 'uploads/profile.jpg';
        let deleteHtml = '';
        
        // Check story owner
        let storyOwnerId = currentStories[currentStoryIndex].user_id; // Global var usage
        
        if (c.user_id == currentUserId || currentUserId == storyOwnerId) {
            deleteHtml = `<i class='fas fa-trash story-comment-delete' onclick='deleteStoryComment(\${c.id})'></i>`;
        }
        
        html += `
            <div class='story-comment-item'>
                <img src='\${pic}' class='story-comment-avatar' onerror='this.src=\"uploads/profile.jpg\"'>
                <div class='story-comment-content'>
                    <div class='story-comment-user'>\${c.username}</div>
                    <div class='story-comment-text'>
                        \${c.comment}
                        \${deleteHtml}
                    </div>
                </div>
            </div>
        `;
    });
    $('#storyCommentsList').html(html);
    // Scroll to bottom
    const list = document.getElementById('storyCommentsList');
    list.scrollTop = list.scrollHeight;
}

function postStoryComment() {
    const modal = document.getElementById('storyCommentsModal');
    const storyId = modal.dataset.storyId;
    const input = document.getElementById('storyCommentInput');
    const text = input.value.trim();
    
    if(!text) return;
    
    // Optimistic UI? No, let's wait.
    
    $.post('indexmo.php', {
        action: 'add_story_comment',
        story_id: storyId,
        comment: text
    }, function(data){
        try{
            const res = JSON.parse(data);
            if(res.success) {
                input.value = '';
                loadStoryComments(storyId);
            }
        }catch(e){}
    });
}

function deleteStoryComment(commentId) {
    if(confirm('Delete this comment?')) {
        const modal = document.getElementById('storyCommentsModal');
        const storyId = modal.dataset.storyId; // to refresh
        
        $.post('indexmo.php', {
            action: 'delete_story_comment',
            comment_id: commentId
        }, function(data){
             try {
                 const res = JSON.parse(data);
                 if(res.success) {
                     loadStoryComments(storyId);
                 } else {
                     alert(res.error);
                 }
             } catch(e){}
        });
    }
}

function recordStoryView(storyId) {
    $.post('indexmo.php', {record_story_view: 1, story_id: storyId});
}

function updateViewersCount(storyId) {
    $.get('indexmo.php', {get_story_view_count: storyId}, function(data){
        try {
            const res = JSON.parse(data);
            $('#storyViewCount').text(res.count);
        } catch(e){}
    });
}


function postStoryComment() {
    const modal = document.getElementById('storyCommentsModal');
    const storyId = modal.dataset.storyId;
    const input = document.getElementById('storyCommentInput');
    const text = input.value.trim();
    
    if(!text) return;
    
    // Optimistic UI? No, let's wait.
    
    $.post('indexmo.php', {
        action: 'add_story_comment',
        story_id: storyId,
        comment: text
    }, function(data){
        try{
            const res = JSON.parse(data);
            if(res.success) {
                input.value = '';
                loadStoryComments(storyId);
            }
        }catch(e){}
    });
}

function deleteStoryComment(commentId) {
    if(confirm('Delete this comment?')) {
        const modal = document.getElementById('storyCommentsModal');
        const storyId = modal.dataset.storyId; // to refresh
        
        $.post('indexmo.php', {
            action: 'delete_story_comment',
            comment_id: commentId
        }, function(data){
             try {
                 const res = JSON.parse(data);
                 if(res.success) {
                     loadStoryComments(storyId);
                 } else {
                     alert(res.error);
                 }
             } catch(e){}
        });
    }
}

function recordStoryView(storyId) {
    $.post('indexmo.php', {record_story_view: 1, story_id: storyId});
}

function deleteStory(storyId) {
    if(confirm('Are you sure you want to delete this story?')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'indexmo.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_story_id';
        input.value = storyId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function updateViewersCount(storyId) {
    $.get('indexmo.php', {get_story_view_count: storyId}, function(data){
        try {
            const res = JSON.parse(data);
            $('#storyViewCount').text(res.count);
        } catch(e){}
    });
}

function setupViewersList(storyId) {
    $('#storyViewersList').html('<li style=\'padding:10px;text-align:center;color:#999;\'>Loading...</li>');
    
    $.get('indexmo.php', {get_story_viewers: storyId}, function(data){
        try {
            const res = JSON.parse(data);
            if(res.success && res.viewers.length > 0) {
                let html = '';
                res.viewers.forEach(v => {
                    let pic = v.profile_picture ? v.profile_picture : 'uploads/profile.jpg';
                    html += `
                        <li style='display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.1);'>
                            <img src='\${pic}' style='width:40px; height:40px; border-radius:50%; object-fit:cover;' onerror='this.src=\"uploads/profile.jpg\"'>
                            <span>\${v.username}</span>
                        </li>
                    `;
                });
                $('#storyViewersList').html(html);
            } else {
                $('#storyViewersList').html('<li style=\'padding:10px;text-align:center;color:#999;\'>No views yet</li>');
            }
        } catch(e) {
            $('#storyViewersList').html('<li style=\'padding:10px;text-align:center;color:#999;\'>Error loading viewers</li>');
        }
    });
}

function toggleStoryViewers() {
    const modal = document.getElementById('storyViewersModal');
    if (modal.style.display === 'block') {
        modal.style.display = 'none';
    } else {
        modal.style.display = 'block';
    }
}

function startProgress(duration) {
    clearInterval(progressInterval);
    const fill = document.getElementById('currentProgressFill');
    let startTime = Date.now();
    
    progressInterval = setInterval(() => {
        let elapsed = Date.now() - startTime;
        let pct = (elapsed / duration) * 100;
        if (pct >= 100) {
            pct = 100;
            clearInterval(progressInterval);
            nextStory();
        }
        if (fill) fill.style.width = pct + '%';
    }, 50);
}

function nextStory() {
    if (currentStoryIndex < currentStories.length - 1) {
        currentStoryIndex++;
        renderStory();
    } else {
        closeStoryModal();
    }
}

function prevStory() {
    if (currentStoryIndex > 0) {
        currentStoryIndex--;
        renderStory();
    }
}

// Ensure clicking close button actually closes
document.querySelector('#storyModal .close-btn').addEventListener('click', closeStoryModal);

function showLoadingOverlay(text = \"Uploading Story...\", subText = \"Please wait while we process your media\") {
    document.getElementById('loadingText').innerText = text;
    document.getElementById('loadingSubText').innerText = subText;
    document.getElementById('loadingOverlay').style.display = 'flex';
    document.getElementById('uploadStoryModal').style.display = 'none';
}

function hideLoadingOverlay() {
    document.getElementById('loadingOverlay').style.display = 'none';
    document.getElementById('uploadStoryModal').style.display = 'flex';
}

function resetUploadForm() {
    document.getElementById('uploadStoryForm').reset();
    document.getElementById('aiWarning').style.display = 'none';
    removeMusic();
}

// Music Search Logic
let searchTimeout;

function toggleMusicSearch() {
    var container = document.getElementById('musicSearchContainer');
    if (container.style.display === 'none') {
        container.style.display = 'block';
        // Auto-focus input
        const input = document.getElementById('musicSearchInput');
        input.focus();
        
        // Auto-load popular suggestions if empty
        if (!input.value && document.getElementById('musicSearchResults').innerHTML === '') {
            searchMusic('Top 50');
        }
    } else {
        container.style.display = 'none';
    }
}

// Add event listener to input for live search
document.getElementById('musicSearchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value;
    if (query.length > 2) {
        searchTimeout = setTimeout(() => searchMusic(query), 500); // 500ms debounce
    }
});

function searchMusic(query) {
    if (!query) query = document.getElementById('musicSearchInput').value;
    if (!query) return;

    var resultsDiv = document.getElementById('musicSearchResults');
    resultsDiv.innerHTML = '<div class=\"text-white\" style=\"padding:10px; text-align:center;\"><div class=\"spinner-border spinner-border-sm text-light\"></div> Searching...</div>';

    fetch('search_music.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.tracks && data.tracks.length > 0) {
                var html = '';
                data.tracks.forEach(track => {
                    var name = track.name.replace(/'/g, \"&apos;\");
                    var artist = track.artist.replace(/'/g, \"&apos;\");
                    var url = track.preview_url;
                    var image = track.image;

                    html += '<div class=\"music-item\" style=\"display:flex; align-items:center; justify-content:space-between;\">';
                    html += '<div style=\"display:flex; align-items:center; gap:10px; flex:1; cursor:pointer;\" onclick=\"selectMusic(\\'' + url + '\\', \\'' + name + '\\', \\'' + artist + '\\', \\'' + image + '\\')\">';
                    html += '<img src=\"' + image + '\" class=\"music-cover\">';
                    html += '<div class=\"music-info\"><div class=\"music-title\">' + track.name + '</div><div class=\"music-artist\">' + track.artist + '</div></div>';
                    html += '</div>';
                    html += '<button type=\"button\" class=\"play-preview-btn\" onclick=\"togglePreview(\\'' + url + '\\', this)\"><i class=\"fas fa-play-circle\"></i></button>';
                    html += '</div>';
                });
                resultsDiv.innerHTML = html;
            } else {
                 resultsDiv.innerHTML = '<div class=\"text-white\" style=\"padding:10px; text-align:center;\">No results found</div>';
            }
        })
        .catch(err => {
            resultsDiv.innerHTML = '<div class=\"text-danger\" style=\"padding:10px;\">Error searching</div>';
        });
}

let currentPreviewAudio = null;
let currentPreviewBtn = null;

function togglePreview(url, btn) {
    if (currentPreviewAudio && currentPreviewAudio.src === url) {
        if (currentPreviewAudio.paused) {
            currentPreviewAudio.play();
            btn.innerHTML = '<i class=\"fas fa-pause-circle\"></i>';
        } else {
            currentPreviewAudio.pause();
            btn.innerHTML = '<i class=\"fas fa-play-circle\"></i>';
        }
    } else {
        if (currentPreviewAudio) {
            currentPreviewAudio.pause();
            if (currentPreviewBtn) currentPreviewBtn.innerHTML = '<i class=\"fas fa-play-circle\"></i>';
        }
        currentPreviewAudio = new Audio(url);
        currentPreviewAudio.play();
        currentPreviewBtn = btn;
        btn.innerHTML = '<i class=\"fas fa-pause-circle\"></i>';
        
        currentPreviewAudio.onended = function() {
            btn.innerHTML = '<i class=\"fas fa-play-circle\"></i>';
        };
    }
}

function selectMusic(url, title, artist, image) {
    // Stop any preview playing in search
    if (currentPreviewAudio) {
        currentPreviewAudio.pause();
        if (currentPreviewBtn) currentPreviewBtn.innerHTML = '<i class=\"fas fa-play-circle\"></i>';
    }

    document.getElementById('music_url').value = url;
    document.getElementById('music_title').value = title;
    document.getElementById('music_artist').value = artist;
    document.getElementById('music_image').value = image;

    document.getElementById('selectedMusicTitle').innerText = title + ' - ' + artist;
    document.getElementById('selectedMusicDisplay').style.display = 'block';
    document.getElementById('musicSearchContainer').style.display = 'none';
    
    // Set and play in the selected area player
    const player = document.getElementById('selectedMusicPlayer');
    player.src = url;
    player.play().catch(e => console.log('Autoplay prevented:', e));
}

function removeMusic() {
    document.getElementById('music_url').value = '';
    document.getElementById('music_title').value = '';
    document.getElementById('music_artist').value = '';
    document.getElementById('music_image').value = '';
    document.getElementById('selectedMusicDisplay').style.display = 'none';
    
    const player = document.getElementById('selectedMusicPlayer');
    player.pause();
    player.src = '';
}

// AI Content Moderation Logic
let nsfwModel;

async function loadModel() {
    if (!nsfwModel) {
        nsfwModel = await nsfwjs.load();
    }
}

// Pre-load model on page load
loadModel();

document.getElementById('uploadStoryForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('storyFileInput');
    const file = fileInput.files[0];
    
    if (!file) return;

    // Reset warning
    document.getElementById('aiWarning').style.display = 'none';

    // Check Video Duration
    if (file.type.startsWith('video/')) {
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.src = URL.createObjectURL(file);
        
        try {
            await new Promise((resolve, reject) => {
                video.onloadedmetadata = () => resolve();
                video.onerror = () => reject('Invalid video file');
            });

            // Duration check (Allow up to 61s for buffer)
            if (video.duration > 61) {
                alert('Video too long! Maximum duration is 60 seconds.');
                return; 
            }
        } catch (error) {
            console.error('Video metadata error:', error);
        }
    }
    
    showLoadingOverlay(\"Checking content...\", \"AI is analyzing your media for safety\");

    try {
        if (!nsfwModel) await loadModel();
        
        const isSafe = await checkContentSafety(file);
        
        if (isSafe) {
            // Update Text for upload phase
            document.getElementById('loadingText').innerText = \"Uploading Story...\";
            document.getElementById('loadingSubText').innerText = \"Finalizing your upload\";
            
            // Submit the form programmatically
            this.submit(); 
        } else {
            hideLoadingOverlay();
            document.getElementById('aiWarning').style.display = 'block';
            document.getElementById('aiWarning').innerHTML = \"<strong>Content Blocked:</strong> Explicit or inappropriate content detected.\";
        }
    } catch (err) {
        console.error(\"AI Check Error:\", err);
        // Fallback: allow upload if AI fails (or block depending on policy)
        // For now, we proceed to avoid blocking users on error
        this.submit();
    }
});

async function checkContentSafety(file) {
    return new Promise((resolve, reject) => {
        const fileType = file.type.split('/')[0];
        const url = URL.createObjectURL(file);

        if (fileType === 'image') {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.src = url;
            img.onload = async () => {
                const predictions = await nsfwModel.classify(img);
                URL.revokeObjectURL(url);
                resolve(isSafe(predictions));
            };
            img.onerror = reject;
        } else if (fileType === 'video') {
            const video = document.createElement('video');
            video.src = url;
            video.crossOrigin = 'anonymous';
            video.muted = true;
            
            video.onloadeddata = async () => {
                const duration = video.duration;
                // Check frames at 10%, 50%, 90%
                const timestamps = [duration * 0.1, duration * 0.5, duration * 0.9];
                
                let isVideoSafe = true;
                
                for (let time of timestamps) {
                    video.currentTime = time;
                    await new Promise(r => { video.onseeked = r; });
                    const predictions = await nsfwModel.classify(video);
                    if (!isSafe(predictions)) {
                        isVideoSafe = false;
                        break;
                    }
                }
                
                URL.revokeObjectURL(url);
                resolve(isVideoSafe);
            };
            video.onerror = reject;
        } else {
            resolve(true); // Unknown type
        }
    });
}

function isSafe(predictions) {
    // Check top prediction
    const top = predictions[0];
    // Block if Porn or Hentai is #1 with high confidence (> 60%)
    const unsafeClasses = ['Porn', 'Hentai', 'Sexy'];
    
    // Check if any unsafe class has > 0.6 probability
    const unsafe = predictions.some(p => unsafeClasses.includes(p.className) && p.probability > 0.6);
    
    return !unsafe;
}

";

// Include footer with our JavaScript
include("includes/footer.php");
?>