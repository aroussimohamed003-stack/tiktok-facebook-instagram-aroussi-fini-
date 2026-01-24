<?php
session_start();

// Use the unified configuration file for database connection
include "config.php";

// Map the config variable $con to the local variable $conn used in this file
$conn = $con;

// --- Auto-Migration for Notifications & Likes ---
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
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

// Check if video_id exists if table already existed
$check_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'video_id'");
if ($check_col->num_rows == 0) {
    try { $conn->query("ALTER TABLE notifications ADD COLUMN video_id INT DEFAULT NULL"); } catch(Exception $e){}
}

$conn->query("CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_post_like (post_id, user_id)
)");

// Add post_id to comments if needed
$check_col = $conn->query("SHOW COLUMNS FROM comments LIKE 'post_id'");
if ($check_col->num_rows == 0) {
    try { $conn->query("ALTER TABLE comments ADD COLUMN post_id INT DEFAULT NULL"); } catch(Exception $e){}
}

// Fix Foreign Key Constraint for Comments (Make video_id nullable so we can add comments to posts)
try {
    // 1. Drop existing FK if it's strictly enforcing NOT NULL interactions (MySQL sometimes needs explicit MODIFY)
    // 2. Modify video_id to allow NULL
    $conn->query("ALTER TABLE comments MODIFY COLUMN video_id INT NULL DEFAULT NULL");
} catch(Exception $e) {
    // Ignore if already nullable or other minor errors
}
// ---------------------------------------------

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle Ajax/Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];

    // Handle Like
    if (isset($_POST['like_post_id'])) {
        $post_id = intval($_POST['like_post_id']);
        
        // Check if liked
        $check = $conn->query("SELECT id FROM post_likes WHERE post_id = $post_id AND user_id = $user_id");
        if ($check->num_rows == 0) {
            // Like
            $conn->query("INSERT INTO post_likes (post_id, user_id) VALUES ($post_id, $user_id)");
            $action = 'liked';

            // Notify owner
            $p_query = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
            if ($p_query && $p_row = $p_query->fetch_assoc()) {
                $recipient = $p_row['user_id'];
                // Check if notification already exists to avoid duplication
                $chk_notif = $conn->query("SELECT id FROM notifications WHERE recipient_id = $recipient AND sender_id = $user_id AND type = 'like' AND post_id = $post_id");
                if ($chk_notif->num_rows == 0) {
                    $conn->query("INSERT INTO notifications (recipient_id, sender_id, type, post_id, created_at) VALUES ($recipient, $user_id, 'like', $post_id, NOW())");
                }
            }
        } else {
            // Unlike
            $conn->query("DELETE FROM post_likes WHERE post_id = $post_id AND user_id = $user_id");
            $action = 'unliked';

            // Remove Notification if exists
            $p_query = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
            if ($p_query && $p_row = $p_query->fetch_assoc()) {
                $recipient = $p_row['user_id'];
                $conn->query("DELETE FROM notifications WHERE recipient_id = $recipient AND sender_id = $user_id AND type = 'like' AND post_id = $post_id");
            }
        }
        
        // Return count
        $cnt = $conn->query("SELECT COUNT(*) as c FROM post_likes WHERE post_id = $post_id")->fetch_assoc()['c'];
        echo json_encode(['action' => $action, 'count' => $cnt]);
        exit();
    }

    // Handle Comment
    if (isset($_POST['comment_content']) && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $content = mysqli_real_escape_string($conn, $_POST['comment_content']);
        
        if (!empty($content)) {
            // Explicitly set video_id to NULL to avoid Foreign Key errors if default is 0
            $insert = $conn->query("INSERT INTO comments (post_id, video_id, user_id, comment, created_at) VALUES ($post_id, NULL, $user_id, '$content', NOW())");
            
            if ($insert) {
                // Notify owner
                $p_query = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
                if ($p_query && $p_row = $p_query->fetch_assoc()) {
                     $recipient = $p_row['user_id'];
                     $conn->query("INSERT INTO notifications (recipient_id, sender_id, type, post_id, created_at) VALUES ($recipient, $user_id, 'comment', $post_id, NOW())");
                }
                
                // Return new comment HTML (Simplified: just reload or return JSON)
                echo json_encode(['success' => true, 'username' => $_SESSION['username'], 'comment' => htmlspecialchars($content)]);
                exit();
            } else {
                echo json_encode(['success' => false, 'error' => 'DB Error: ' . $conn->error]);
                exit();
            }
        }
    }
}

// معالجة إضافة المنشور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['content'])) {
    $user_id = $_SESSION['user_id'];
    $content = $_POST['content'];
    $image_path = '';

    // تحميل الصورة إذا تم تحميلها
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/posts/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . uniqid() . '.' . $file_ext;

        // التحقق من أن الملف صورة
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_path = $target_file;
            } else {
                echo "<script>alert('حدث خطأ أثناء تحميل الصورة.');</script>";
            }
        } else {
            echo "<script>alert('الملف المرفوع ليس صورة.');</script>";
        }
    }

    // إدخال المنشور في قاعدة البيانات
    $sql = "INSERT INTO posts (user_id, content, image_path) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $content, $image_path);
        if ($stmt->execute()) {
            // Success
             header("Location: mo.php");
             exit();
        } else {
             echo "<script>alert('حدث خطأ أثناء نشر المنشور.');</script>";
        }
        $stmt->close();
    }
}

// معالجة حذف المنشور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_post'])) {
    $post_id = $_POST['delete_post'];
    $user_id = $_SESSION['user_id'];

    // التحقق من أن المستخدم هو صاحب المنشور قبل الحذف
    $sql = "DELETE FROM posts WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $post_id, $user_id);
        if ($stmt->execute()) {
             // Success
             header("Location: mo.php");
             exit();
        }
        $stmt->close();
    }
}

// معالجة حذف التعليق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_comment_id'])) {
    $comment_id = intval($_POST['delete_comment_id']);
    $user_id = $_SESSION['user_id'];

    // Check ownership
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
    if($stmt){
        $stmt->bind_param("ii", $comment_id, $user_id);
        if($stmt->execute()){
            echo json_encode(['success' => true]);
            exit();
        }
        $stmt->close();
    }
    echo json_encode(['success' => false, 'error' => 'Failed to delete']);
    exit();
}

// استرجاع المنشورات لعرضها مع معلومات المستخدمين
$my_id = $_SESSION['user_id'];
$sql = "SELECT posts.*, users.username, users.profile_picture,
        (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) as likes_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id AND post_likes.user_id = $my_id) as liked_by_me,
        (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count
        FROM posts
        JOIN users ON posts.user_id = users.id
        WHERE posts.user_id = $my_id 
           OR posts.user_id IN (SELECT sender_id FROM friends WHERE receiver_id = $my_id AND status = 'accepted')
           OR posts.user_id IN (SELECT receiver_id FROM friends WHERE sender_id = $my_id AND status = 'accepted')
        ORDER BY posts.created_at DESC";
$result = $conn->query($sql);
if (!$result) die("Error: " . $conn->error);

$current_user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet"> <!-- Essential for Navbar Styles -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            /* Light Theme Defaults */
            --primary-color: #00f2ea;
            --secondary-color: #ff0050;
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-color: #000000;
            --text-muted: #65676b;
            --border-color: #ddd;
            --input-bg: #f0f2f5;
        }

        [data-theme="dark"] {
            /* Dark Theme Overrides */
            --bg-color: #000000;
            --card-bg: #121212;
            --text-color: #ffffff;
            --text-muted: #aaaaaa;
            --border-color: #333;
            --input-bg: #2a2a2a;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding-top: 70px; /* Adjusted for navbar height */
            transition: background-color 0.3s, color 0.3s;
        }

        /* Navbar Correction */
        .navbar-custom {
            background-color: rgba(18, 18, 18, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .navbar-custom .navbar-brand, 
        .navbar-custom .nav-link {
            color: #ffffff !important;
        }
        .navbar-custom .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .navbar-toggler {
            filter: invert(1);
        }

        .container {
            max-width: 700px;
        }

        /* Create Post Card */
        .create-post-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .form-control {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 12px;
        }
        .form-control:focus {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--primary-color);
            box-shadow: none;
        }
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
            border: none;
            border-radius: 25px;
            font-weight: bold;
            padding: 10px 30px;
            color: white;
        }
        .btn-primary:hover {
            opacity: 0.9;
            transform: scale(1.02);
            color: white;
        }

        /* Post Card */
        .post-card {
            background: var(--card-bg);
            border-radius: 20px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .post-card:hover {
            border-color: var(--primary-color);
        }

        .post-header {
            padding: 15px;
            display: flex;
            align-items: center;
        }

        .profile-pic {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            margin-left: 15px; /* RTL */
        }

        .user-info h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }
        .user-info span {
            font-size: 12px;
            color: var(--text-muted);
        }

        .post-body {
            padding: 15px;
            padding-top: 0;
        }
        .post-text {
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 15px;
            white-space: pre-wrap;
            color: var(--text-color);
        }
        
        .post-image {
            width: 100%;
            border-radius: 12px;
            margin-top: 10px;
        }

        .post-footer {
            padding: 10px 15px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .post-footer .text-muted {
             color: var(--text-muted) !important;
        }

        .btn-delete {
            background: transparent;
            color: #ff4757;
            border: 1px solid #ff4757;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 12px;
            transition: all 0.3s;
        }
        .btn-delete:hover {
            background: #ff4757;
            color: white;
        }

        /* File Upload Styling */
        .custom-file-upload {
            display: inline-block;
            cursor: pointer;
            color: var(--primary-color);
            margin-top: 10px;
        }
        .custom-file-upload i {
            margin-left: 5px;
        }
        
        h1.page-title {
            text-align: center;
            font-weight: 800;
            margin-bottom: 30px;
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body>

<?php include("includes/navbar.php"); ?>

<div class="container-fluid py-4">
    <h1 class="page-title">Community Posts</h1>

    <div class="row">
        <!-- Sidebar Left (Desktop) -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="sticky-top" style="top: 80px;">
                <div class="p-3 rounded-4 mb-3" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <h5 class="mb-3">قائمة الأصدقاء</h5>
                    <div id="sidebar-friends-list">
                        <?php
                        $my_id = $_SESSION['user_id'];
                        $sidebar_friends = mysqli_query($con, "SELECT u.id, u.username, u.profile_picture FROM users u 
                            JOIN friends f ON (f.sender_id = u.id OR f.receiver_id = u.id) 
                            WHERE (f.sender_id = $my_id OR f.receiver_id = $my_id) AND f.status = 'accepted' AND u.id != $my_id LIMIT 10");
                        while($sf = mysqli_fetch_assoc($sidebar_friends)):
                            $sf_pic = !empty($sf['profile_picture']) ? $sf['profile_picture'] : 'uploads/profile.jpg';
                            // Fix potential wrong folder name in DB paths
                            $sf_pic = str_replace('profile_pictures', 'profiles', $sf_pic);
                        ?>
                            <a href="profile.php?user_id=<?= $sf['id'] ?>" class="d-flex align-items-center mb-2 text-decoration-none" style="color: var(--text-color);">
                                <img src="<?= $sf_pic ?>" class="rounded-circle me-2" style="width:35px;height:35px;object-fit:cover;" onerror="this.src='uploads/profile.jpg'">
                                <span><?= htmlspecialchars($sf['username']) ?></span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Content -->
        <div class="col-lg-6">
            <!-- Main Search Bar (Facebook style) -->
            <form action="search_users.php" method="GET" class="mb-4">
                <div class="input-group" style="box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 50px; overflow: hidden; border: 2px solid var(--primary-color);">
                    <span class="input-group-text bg-white border-0 ps-3">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="query" class="form-control border-0 py-2" placeholder="بحث عن أصدقاء..." required style="box-shadow: none;">
                    <button type="submit" class="btn btn-primary px-4 border-0" style="background: var(--primary-color);">بحث</button>
                </div>
            </form>

    <!-- Modal for Likes/Comments List -->
    <div class="modal fade" id="usersModal" tabindex="-1" aria-labelledby="usersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="background: var(--card-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 20px;">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title w-100 text-center" id="usersModalLabel">الأشخاص</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <ul id="usersList" class="p-0 m-0">
                        <!-- Content loaded via Ajax -->
                    </ul>
                </div>
            </div>
        </div>
    </div>


    <!-- Create Post Section -->
    <div class="create-post-card">
        <form action="" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <textarea class="form-control" name="content" rows="3" placeholder="بماذا تفكر؟" required></textarea>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <label for="image" class="custom-file-upload">
                    <i class="fas fa-image"></i> إضافة صورة
                </label>
                <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i> نشر
                </button>
            </div>
        </form>
    </div>

    <!-- Posts Feed -->
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php 
                $pp = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
            ?>
            <div class="post-card" id="post-<?php echo $row['id']; ?>">
                <div class="post-header">
                    <img src="<?php echo htmlspecialchars($pp); ?>" class="profile-pic" alt="User" onerror="this.src='uploads/profile.jpg'">
                    <div class="user-info">
                        <h5><?php echo htmlspecialchars($row['username']); ?></h5>
                        <span><i class="far fa-clock"></i> <?php echo date('Y-m-d h:i A', strtotime($row['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($row['user_id'] == $_SESSION['user_id']): ?>
                        <div class="ms-auto me-0" style="margin-right: auto !important; margin-left: 0 !important;"> <!-- RTL overrides -->
                            <form action="" method="post" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنشور؟');">
                                <input type="hidden" name="delete_post" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn-delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="post-body">
                    <div class="post-text"><?php echo htmlspecialchars($row['content']); ?></div>
                    <?php if (!empty($row['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" class="post-image" alt="Post Image">
                    <?php endif; ?>
                </div>
                
                <div class="post-footer">
                    <div class="">
                        <!-- Like Button -->
                        <span style="cursor: pointer; color: <?php echo $row['liked_by_me'] ? 'red' : 'inherit'; ?>;" 
                              onclick="toggleLike(this, <?php echo $row['id']; ?>)">
                            <i class="<?php echo $row['liked_by_me'] ? 'fas' : 'far'; ?> fa-heart"></i>
                        </span>
                        <span style="cursor: pointer;" onclick="showLikers(<?php echo $row['id']; ?>)">
                            <span class="like-count" id="like-count-<?php echo $row['id']; ?>"><?php echo $row['likes_count']; ?></span> إعجاب
                        </span>

                        <!-- Comment Button -->
                        <span class="ms-3" style="cursor: pointer;" onclick="document.getElementById('comment-box-<?php echo $row['id']; ?>').classList.toggle('d-none')">
                            <i class="far fa-comment"></i>
                        </span>
                        <span style="cursor: pointer;" onclick="showCommenters(<?php echo $row['id']; ?>)">
                             <?php echo $row['comments_count']; ?> تعليق
                        </span>



                    </div>
                </div>

                <!-- Comments Section -->
                <div id="comment-box-<?php echo $row['id']; ?>" class="p-3 border-top d-none" style="background-color: var(--input-bg);">
                    <!-- Comments List -->
                    <div class="comments-list mb-3" id="comments-list-<?php echo $row['id']; ?>">
                        <?php 
                        $pid = $row['id'];
                        $c_query = $conn->query("SELECT c.*, u.username, u.profile_picture FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = $pid ORDER BY c.created_at ASC");
                        while($cw = $c_query->fetch_assoc()){
                            $is_mine = ($cw['user_id'] == $_SESSION['user_id']);
                            $commenter_pp = !empty($cw['profile_picture']) ? $cw['profile_picture'] : 'uploads/profile.jpg';
                             echo "<div class='small mb-2 c-item-{$cw['id']} d-flex align-items-center gap-2'>
                                     <img src='{$commenter_pp}' style='width: 25px; height: 25px; border-radius: 50%; object-fit: cover;' onerror=\"this.src='uploads/profile.jpg'\">
                                     <div><strong>{$cw['username']}</strong>: {$cw['comment']}</div>";
                            
                            if($is_mine) {
                                echo " <a href='javascript:void(0)' onclick='deleteComment({$cw['id']})' class='text-danger ms-2' style='text-decoration:none;'><i class='fas fa-trash-alt'></i></a>";
                            }

                            echo "</div>";
                        }
                        ?>
                    </div>

                    <!-- Comment Form -->
                    <form onsubmit="submitComment(event, number_<?php echo $row['id']; ?>.value, <?php echo $row['id']; ?>)">
                        <div class="input-group">
                            <input type="text" id="number_<?php echo $row['id']; ?>" class="form-control form-control-sm" placeholder="اكتب تعليقاً...">
                            <button class="btn btn-sm btn-outline-primary" type="submit">إرسال</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
        
        <script>
        function toggleLike(btn, postId) {
            fetch('mo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'like_post_id=' + postId
            })
            .then(r => r.json())
            .then(data => {
                var icon = btn.querySelector('i');
                var count = document.getElementById('like-count-' + postId);
                if(count) count.textContent = data.count;
                
                if(data.action == 'liked') {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    btn.style.color = 'red';
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    btn.style.color = 'inherit';
                }
                // Update nav notifications immediately
                if(window.fetchNotifications) fetchNotifications();
            });
        }

        function deleteComment(commentId) {
            if(!confirm('Delete this comment?')) return;
            
            fetch('mo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'delete_comment_id=' + commentId
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    var el = document.querySelector('.c-item-' + commentId);
                    if(el) el.remove();
                } else {
                    alert('Error deleting');
                }
            });
        }

        function submitComment(e, content, postId) {
            e.preventDefault();
            if(!content.trim()) return;
            
            fetch('mo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'comment_content=' + encodeURIComponent(content) + '&post_id=' + postId
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    // Just reload page to get ID and correct rendering is easier, 
                    // but for smooth UI we append. Note: we won't have the ID for delete unless returned.
                    // The previous backend code for adding comment didn't return ID.
                    // Simplified: Reload page to show new comment with Delete button (since we need ID).
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(e => {
                console.error(e);
                alert('Request failed');
            });
        }

        function showLikers(postId) {
            document.getElementById('usersModalLabel').innerText = 'الأشخاص الذين أعجبوا';
            document.getElementById('usersList').innerHTML = '<li class="text-center p-3">جاري التحميل...</li>';
            var myModal = new bootstrap.Modal(document.getElementById('usersModal'));
            myModal.show();

            fetch('get_likers.php?post_id=' + postId)
                .then(r => r.text())
                .then(data => {
                    document.getElementById('usersList').innerHTML = data;
                });
        }

        function showCommenters(postId) {
            document.getElementById('usersModalLabel').innerText = 'الأشخاص الذين علقوا';
            document.getElementById('usersList').innerHTML = '<li class="text-center p-3">جاري التحميل...</li>';
            var myModal = new bootstrap.Modal(document.getElementById('usersModal'));
            myModal.show();

            fetch('get_commenters.php?post_id=' + postId)
                .then(r => r.text())
                .then(data => {
                    document.getElementById('usersList').innerHTML = data;
                });
        }
        </script>

    <?php else: ?>
         <div class="alert alert-info text-center" style="background: rgba(255,255,255,0.1); color: var(--text-color); border: 1px solid var(--border-color);">
            لا يوجد منشورات حتى الآن. كن أول من ينشر!
        </div>
    <?php endif; ?>

</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-dark rounded-4" style="background: white;">
            <div class="modal-header">
                <h5 class="modal-title">مشاركة مع الأصدقاء</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="friends-share-list" class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                    <div class="text-center p-3 text-muted">جاري التحميل...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentShareItemId = null;
let currentShareItemType = null;

function openShareModal(itemId, type) {
    currentShareItemId = itemId;
    currentShareItemType = type;
    const list = document.getElementById('friends-share-list');
    list.innerHTML = '<div class="text-center p-3 text-muted">جاري التحميل...</div>';
    
    const myModal = new bootstrap.Modal(document.getElementById('shareModal'));
    myModal.show();

    fetch('get_friends.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.friends.length === 0) {
                    list.innerHTML = '<div class="p-3 text-center">لا يوجد أصدقاء للمشاركة معهم</div>';
                    return;
                }
                list.innerHTML = '';
                data.friends.forEach(f => {
                    const btn = document.createElement('button');
                    btn.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
                    btn.innerHTML = `<img src="${f.profile_picture}" class="rounded-circle" style="width:30px;height:30px;object-fit:cover;"> <span>${f.username}</span>`;
                    btn.onclick = () => shareWithFriend(f.id, btn);
                    list.appendChild(btn);
                });
            } else {
                list.innerHTML = '<div class="p-3 text-danger">خطأ في تحميل الأصدقاء</div>';
            }
        });
}

function shareWithFriend(friendId, btn) {
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> جاري الإرسال...';

    const formData = new FormData();
    formData.append('action', 'share');
    formData.append('user_id', friendId);
    formData.append('item_id', currentShareItemId);
    formData.append('item_type', currentShareItemType);

    fetch('friend_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check text-success"></i> تم الإرسال';
            btn.classList.add('list-group-item-success');
        } else {
            btn.innerHTML = originalContent;
            btn.disabled = false;
            alert(data.error || 'فشل الإرسال');
        }
    });
}
</script>

<script>
    // Preview image name when selected
    document.getElementById('image').addEventListener('change', function() {
        if(this.files && this.files[0]) {
             document.querySelector('.custom-file-upload').innerHTML = '<i class="fas fa-check"></i> ' + this.files[0].name;
             document.querySelector('.custom-file-upload').style.color = '#00f2ea';
        }
    });
</script>

<?php include("includes/footer.php"); ?>