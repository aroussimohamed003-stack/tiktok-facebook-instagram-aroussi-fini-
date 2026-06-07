<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection settings
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutoreal";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// التحقق من وجود الجداول وإنشاؤها فقط إذا لم تكن موجودة
$check_users = $conn->query("SHOW TABLES LIKE 'users'");
$check_stories = $conn->query("SHOW TABLES LIKE 'stories'");
$check_views = $conn->query("SHOW TABLES LIKE 'story_views'");

// إذا لم تكن الجداول موجودة، أنشئها
if ($check_users->num_rows == 0) {
    $conn->query("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        profile_picture VARCHAR(255) DEFAULT 'assets/default_profile.jpg',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

if ($check_stories->num_rows == 0) {
    $conn->query("CREATE TABLE stories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_path VARCHAR(255) NOT NULL,
        file_type ENUM('image', 'video') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

if ($check_views->num_rows == 0) {
    $conn->query("CREATE TABLE story_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT,
        user_id INT,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_view (story_id, user_id)
    )");
}

// إضافة مستخدمين تجريبيين إذا لم يكونوا موجودين
$check_test_user = $conn->query("SELECT id FROM users WHERE username = 'test'");
if ($check_test_user->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password) VALUES ('test', '" . password_hash('password', PASSWORD_DEFAULT) . "')");
}

$check_user1 = $conn->query("SELECT id FROM users WHERE username = 'user1'");
if ($check_user1->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password) VALUES ('user1', '" . password_hash('123456', PASSWORD_DEFAULT) . "')");
}

$check_user2 = $conn->query("SELECT id FROM users WHERE username = 'user2'");
if ($check_user2->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password) VALUES ('user2', '" . password_hash('123456', PASSWORD_DEFAULT) . "')");
}

// Check for remember me cookie if not logged in
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $conn->real_escape_string($_COOKIE['remember_me']);
    $query = "SELECT * FROM users WHERE remember_token = '$token' LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['profile_picture'] = $user['profile_picture'] ?: 'assets/default_profile.jpg';
    }
}

// Login logic starts here
if (isset($_POST['login'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['profile_picture'] = $user['profile_picture'] ?: 'assets/default_profile.jpg';
            header("Location: story.php");
            
            // Persistent Login (Remember Me)
            try {
                $token = bin2hex(random_bytes(32));
                // Update user with token
                $uid = $user['id'];
                $conn->query("UPDATE users SET remember_token = '$token' WHERE id = $uid");
                // Set cookie for 30 days
                setcookie('remember_me', $token, time() + (86400 * 30), "/");
            } catch (Exception $e) {}

            exit();
        } else {
            $error = "كلمة المرور غير صحيحة";
        }
    } else {
        $error = "اسم المستخدم غير موجود";
    }
}

// Logout
if (isset($_GET['logout'])) {
    // Clear remember me
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
    }
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $conn->query("UPDATE users SET remember_token = NULL WHERE id = $uid");
    }

    session_unset();
    session_destroy();
    header("Location: story.php");
    exit();
}

// Upload story - تحقق من وجود المستخدم في الجلسة أولاً
if (isset($_POST['upload_story']) && isset($_SESSION['user_id'])) {
    // تحقق من أن المستخدم موجود في قاعدة البيانات
    $user_id = $_SESSION['user_id'];
    $check_user = $conn->query("SELECT id FROM users WHERE id = $user_id");
    
    if ($check_user && $check_user->num_rows > 0) {
        if (!isset($_FILES['story_file']) || $_FILES['story_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "يرجى اختيار ملف صالح.";
        } else {
            $file_name = $_FILES['story_file']['name'];
            $file_tmp_name = $_FILES['story_file']['tmp_name'];
            $file_size = $_FILES['story_file']['size'];
            $file_error = $_FILES['story_file']['error'];

            // التحقق من الملف
            $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_video_ext = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $max_file_size = 50 * 1024 * 1024; // 50MB
            
            if ($file_size > $max_file_size) {
                $error = "حجم الملف كبير جداً. الحد الأقصى هو 50MB.";
            } elseif (in_array($ext, $allowed_image_ext)) {
                $uploadDir = "uploads/stories/images/";
                $story_type = "image";
            } elseif (in_array($ext, $allowed_video_ext)) {
                $uploadDir = "uploads/stories/videos/";
                $story_type = "video";
            } else {
                $error = "نوع الملف غير مدعوم. فقط الصور (JPG, PNG, GIF, WEBP) والفيديوهات (MP4, MOV, AVI, WEBM, MKV) مسموحة.";
            }

            if (!isset($error)) {
                // إنشاء المجلدات إذا لم تكن موجودة
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                if (!is_dir('uploads/stories')) mkdir('uploads/stories', 0755, true);
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $newFileName = uniqid() . "_" . time() . "." . $ext;
                $targetFile = $uploadDir . $newFileName;

                if (move_uploaded_file($file_tmp_name, $targetFile)) {
                    $stmt = $conn->prepare("INSERT INTO stories (file_path, file_type, user_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $targetFile, $story_type, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = "تم رفع الستوري بنجاح!";
                        // إعادة توجيه لتجنب إعادة الإرسال
                        header("Location: story.php?success=" . urlencode($success));
                        exit();
                    } else {
                        $error = "فشل في حفظ الستوري في قاعدة البيانات: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "فشل في رفع الملف. يرجى التحقق من صلاحيات المجلد.";
                }
            }
        }
    } else {
        $error = "المستخدم غير موجود. يرجى تسجيل الدخول مرة أخرى.";
        session_unset();
        session_destroy();
        header("Location: story.php");
        exit();
    }
}

// Delete story
if (isset($_GET['delete_id']) && isset($_SESSION['user_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $user_id = $_SESSION['user_id'];

    $result = $conn->query("SELECT file_path FROM stories WHERE id = $delete_id AND user_id = $user_id");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $file_path = $row['file_path'];
        
        // حذف من story_views أولاً
        $conn->query("DELETE FROM story_views WHERE story_id = $delete_id");
        
        // حذف من stories
        $conn->query("DELETE FROM stories WHERE id = $delete_id AND user_id = $user_id");
        
        // حذف الملف من السيرفر
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $success = "تم حذف الستوري بنجاح!";
        header("Location: story.php?success=" . urlencode($success));
        exit();
    }
}

// Record story view
if (isset($_GET['view_story']) && isset($_SESSION['user_id'])) {
    $story_id = intval($_GET['view_story']);
    $user_id = $_SESSION['user_id'];

    // التحقق إذا كان قد شوهد من قبل
    $check = $conn->query("SELECT id FROM story_views WHERE story_id = $story_id AND user_id = $user_id");
    
    if (!$check || $check->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO story_views (story_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $story_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit();
}

// Get viewers
if (isset($_GET['get_viewers'])) {
    $story_id = intval($_GET['get_viewers']);

    $sql = "SELECT u.id, u.username, u.profile_picture, sv.viewed_at
            FROM story_views sv
            JOIN users u ON sv.user_id = u.id
            WHERE sv.story_id = ?
            ORDER BY sv.viewed_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $viewers = [];
    while ($row = $result->fetch_assoc()) {
        $viewers[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'profile_picture' => !empty($row['profile_picture']) ? $row['profile_picture'] : 'assets/default_profile.jpg',
            'viewed_at' => $row['viewed_at']
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($viewers);
    exit();
}

// الحصول على الستوريات النشطة (آخر 24 ساعة) مع تجميع حسب المستخدم
$users_with_stories_query = "
    SELECT DISTINCT u.id, u.username, u.profile_picture
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at > NOW() - INTERVAL 48 HOUR
    ORDER BY s.created_at DESC
";

$users_result = $conn->query($users_with_stories_query);

// الحصول على آخر الستوريات للشبكة
$recent_stories_query = "
    SELECT s.*, u.username, u.profile_picture,
           COUNT(sv.id) AS view_count
    FROM stories s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN story_views sv ON s.id = sv.story_id
    WHERE s.created_at > NOW() - INTERVAL 48 HOUR
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 12
";

$recent_stories_result = $conn->query($recent_stories_query);

// عرض رسالة النجاح إذا وجدت في URL
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

?>






<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ستوري آب - رفع وعرض الستوريات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff0050;
            --secondary-color: #00f2ea;
            --bg-color: #000000;
            --card-bg: #121212;
            --text-color: #ffffff;
        }

        /* Navbar Custom Styles */
        .navbar-custom {
            background-color: rgba(18, 18, 18, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link {
            color: #fff;
        }
        .navbar-custom .nav-link:hover {
            color: var(--primary-color);
        }
        .mobile-bottom-nav {
            background-color: #000;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .mobile-bottom-nav-icon {
            color: #fff;
        }
        .mobile-bottom-nav-icon.active {
            color: var(--primary-color);
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            color: var(--text-color);
        }

        /* Modern Stories Header */
        .stories-header {
            padding: 20px;
            background: rgba(18, 18, 18, 0.8);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        /* Stories Circle List */
        .stories-list {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 15px;
            scrollbar-width: none; /* Firefox */
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
        }
        .story-user-card:hover {
            transform: scale(1.05);
        }

        .story-avatar-ring {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .story-user-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid #000;
            object-fit: cover;
        }

        .story-user-name {
            font-size: 12px;
            margin-top: 5px;
            color: #ccc;
            max-width: 70px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Full Screen Story Modal */
        .story-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            z-index: 2000;
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

        /* Progress Bar */
        .story-progress {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            z-index: 2010;
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

        /* Header in Modal */
        .story-viewer-header {
            position: absolute;
            top: 25px;
            left: 0;
            width: 100%;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 2010;
            color: #fff;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-info img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        .user-info span {
            font-weight: 600;
            font-size: 14px;
        }
        .story-time {
            font-size: 12px;
            opacity: 0.7;
            margin-right: 8px;
        }

        /* Viewers Button */
        .viewers-trigger {
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 2020;
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 14px;
            cursor: pointer;
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .viewers-trigger:hover {
            background: rgba(0,0,0,0.7);
        }

        /* Viewers Modal */
        .viewers-modal {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 50vh;
            background: #1a1a1a;
            border-radius: 20px 20px 0 0;
            z-index: 3000;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
            padding: 20px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
        }
        .viewers-modal.active {
            transform: translateY(0);
            display: block;
        }
        .viewers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        .viewers-list-container {
            height: calc(100% - 60px);
            overflow-y: auto;
        }
        .viewer-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        .viewer-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Navigation Areas */
        .nav-area {
            position: absolute;
            top: 0;
            height: 100%;
            width: 30%;
            z-index: 2005;
        }
        .nav-prev { left: 0; }
        .nav-next { right: 0; }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        /* Upload Btn */
        .floating-upload {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff0050, #cc2366);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(255, 0, 80, 0.4);
            cursor: pointer;
            z-index: 100;
            transition: transform 0.2s;
        }
        .floating-upload:hover {
            transform: scale(1.1);
        }

        /* Upload Modal */
        #uploadModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 4000;
            justify-content: center;
            align-items: center;
        }
        .upload-card {
            background: #222;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .floating-upload {
    position: fixed;
    bottom: 111px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(45deg, #ff0050, #cc2366);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 15px rgba(255, 0, 80, 0.4);
    cursor: pointer;
    z-index: 100;
    transition: transform 0.2s;
    }


    </style>
</head>
<body>
    <?php include("includes/navbar.php"); ?>
    <?php if (isset($_SESSION['user_id'])): ?>
        
        <!-- Header & Stories -->
        <div class="stories-header" style="top: 70px;">
             <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0" style="font-weight: 700;">Stories</h4>
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= $_SESSION['profile_picture'] ?? 'assets/default_profile.jpg' ?>" 
                         style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                </div>
            </div>

            <!-- Active Stories List -->
            <div class="stories-list">
                <?php if ($users_result && $users_result->num_rows > 0): ?>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <?php 
                        $story_count_query = "SELECT COUNT(*) as count FROM stories WHERE user_id = {$user['id']} AND created_at > NOW() - INTERVAL 48 HOUR";
                        $count_result = $conn->query($story_count_query);
                        $story_count = $count_result ? $count_result->fetch_assoc()['count'] : 0;
                        ?>
                        
                        <?php if ($story_count > 0): ?>
                            <div class="story-user-card" onclick="viewUserStories(<?= $user['id'] ?>)">
                                <div class="story-avatar-ring">
                                    <img src="<?= !empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/default_profile.jpg' ?>" 
                                         class="story-user-avatar"
                                         onerror="this.src='assets/default_profile.jpg'">
                                </div>
                                <div class="story-user-name">
                                    <?= htmlspecialchars($user['username']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted w-100 text-center">No active stories</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Grid (Optional, kept for history) -->
        <div class="container mt-4">
             <h5 class="mb-3 text-muted">Recent Updates</h5>
             <div class="row g-3">
                <?php if ($recent_stories_result && $recent_stories_result->num_rows > 0): ?>
                    <?php while ($story = $recent_stories_result->fetch_assoc()): ?>
                        <div class="col-4 col-md-3">
                            <div style="aspect-ratio: 9/16; border-radius: 10px; overflow: hidden; position: relative; cursor: pointer;"
                                 onclick="openStoryModal('<?= $story['id'] ?>')">
                                <?php if ($story['file_type'] == 'image'): ?>
                                    <img src="<?= $story['file_path'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <video src="<?= $story['file_path'] ?>" style="width: 100%; height: 100%; object-fit: cover;"></video>
                                    <i class="fas fa-play" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white;"></i>
                                <?php endif; ?>
                                <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 8px;">
                                    <div style="font-size: 10px; color: white;">
                                        <i class="fas fa-eye me-1"></i><?= $story['view_count'] ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
             </div>
        </div>

        <!-- Floating Upload Button -->
        <div class="floating-upload" onclick="document.getElementById('uploadModal').style.display = 'flex'">
            <i class="fas fa-plus"></i>
        </div>

        <!-- Upload Modal -->
        <div id="uploadModal" onclick="if(event.target === this) this.style.display = 'none'">
            <div class="upload-card">
                <h3>Add to Story</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="story_file" class="form-control mb-3" accept="image/*,video/*" required>
                    <button type="submit" name="upload_story" class="btn btn-primary w-100">Post Now</button>
                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="document.getElementById('uploadModal').style.display = 'none'">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Story Viewer Modal -->
        <div id="storyModal" class="story-modal">
            <div class="story-progress" id="storyProgress"></div>

            <div class="story-viewer-header">
                <div class="user-info">
                    <img id="modalUserAvatar" src="" alt="">
                    <div>
                        <span id="modalUsername">Username</span>
                        <span id="modalTime" class="story-time">2h</span>
                    </div>
                </div>
                <button class="close-btn" onclick="closeStoryModal()">&times;</button>
            </div>

            <div class="story-content">
                <div class="nav-area nav-prev" onclick="prevStory()"></div>
                <div class="nav-area nav-next" onclick="nextStory()"></div>
                <div id="storyMediaContainer" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"></div>
            </div>

            <!-- Viewers Trigger Button (Only visible to owner) -->
            <div id="viewersTrigger" class="viewers-trigger" style="display: none;" onclick="showStoryViewers()">
                <i class="fas fa-eye"></i> 
                Seen by <span id="viewersCount">0</span>
            </div>
        </div>

        <!-- Viewers Bottom Sheet -->
        <div id="viewersModal" class="viewers-modal">
            <div class="viewers-header">
                 <h5>Seen by <span id="viewersTotal">0</span></h5>
                 <button class="close-btn" onclick="document.getElementById('viewersModal').classList.remove('active')">&times;</button>
            </div>
            <div class="viewers-list-container" id="viewersList">
                <!-- Viewers injected here -->
            </div>
        </div>

    <?php else: ?>
        <!-- Login Form (Simplistic for now) -->
        <div style="height: 100vh; display: flex; align-items: center; justify-content: center; background: url('assets/bg.jpg') cover;">
            <div class="login-container" style="background: rgba(0,0,0,0.8); backdrop-filter: blur(10px);">
                <h2 class="text-center mb-4">Story Login</h2>
                <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                <form method="post">
                    <input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
                    <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
                    <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        let currentStories = [];
        let currentStoryIndex = 0;
        let progressInterval;
        let storyDuration = 5000;
        let currentVideo = null;
        let currentUserId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;

        function viewUserStories(userId) {
            fetch('get_user_stories.php?user_id=' + userId)
                .then(r => r.json())
                .then(data => {
                    if(data.success && data.stories.length > 0){
                        currentStories = data.stories;
                        currentStoryIndex = 0;
                        openStoryModalUI();
                    }
                });
        }

        function openStoryModal(storyId) {
             fetch('get_single_story.php?story_id=' + storyId)
                .then(r => r.json())
                .then(data => {
                    if(data.success){
                        currentStories = [data.story];
                        currentStoryIndex = 0;
                        openStoryModalUI();
                    }
                });
        }

        function openStoryModalUI() {
            document.getElementById('storyModal').style.display = 'block';
            loadCurrentStory();
        }

        function closeStoryModal() {
            document.getElementById('storyModal').style.display = 'none';
            document.getElementById('viewersModal').classList.remove('active');
            stopProgress();
            if(currentVideo) currentVideo.pause();
        }

        function loadCurrentStory() {
            const story = currentStories[currentStoryIndex];
            
            // Media
            const container = document.getElementById('storyMediaContainer');
            container.innerHTML = '';
            
            if(story.file_type === 'image') {
                const img = document.createElement('img');
                img.src = story.file_path;
                img.className = 'story-media-full';
                container.appendChild(img);
                storyDuration = 5000;
                startProgress();
            } else {
                const video = document.createElement('video');
                video.src = story.file_path;
                video.className = 'story-media-full';
                video.autoplay = true;
                video.playsInline = true;
                video.onloadedmetadata = () => {
                    storyDuration = video.duration * 1000;
                    startProgress();
                };
                video.onended = nextStory;
                container.appendChild(video);
                currentVideo = video;
            }
            
            // Show Viewers Button (For validation: Showing for everyone)
            const trigger = document.getElementById('viewersTrigger');
            trigger.style.display = 'flex';
            document.getElementById('viewersCount').textContent = story.view_count || 0;
            
            // Check usage for debugging
            console.log('Current User:', currentUserId, 'Story Owner:', story.user_id);

            updateProgressBars();
            recordView(story.id);
        }

        function showStoryViewers() {
            const story = currentStories[currentStoryIndex];
            const modal = document.getElementById('viewersModal');
            const list = document.getElementById('viewersList');
            const total = document.getElementById('viewersTotal');
            
            list.innerHTML = '<div class="text-center text-muted mt-3">Loading...</div>';
            modal.classList.add('active');

            fetch('story.php?get_viewers=' + story.id)
                .then(r => r.json())
                .then(users => {
                    total.innerText = users.length;
                    if(users.length === 0) {
                        list.innerHTML = '<div class="text-center text-muted mt-5">No views yet</div>';
                        return;
                    }
                    
                    let html = '';
                    users.forEach(u => {
                        html += `
                        <div class="viewer-item">
                            <img src="${u.profile_picture}" onerror="this.src='assets/default_profile.jpg'">
                            <div>
                                <div style="font-weight:600">${u.username}</div>
                                <div style="font-size:12px; color:#777">${formatTime(u.viewed_at)}</div>
                            </div>
                        </div>
                        `;
                    });
                    list.innerHTML = html;
                });
        }

        function updateProgressBars() {
            const container = document.getElementById('storyProgress');
            container.innerHTML = '';
            currentStories.forEach((_, i) => {
                const bar = document.createElement('div');
                bar.className = 'story-progress-bar';
                const fill = document.createElement('div');
                fill.className = 'story-progress-fill';
                fill.id = 'progress-' + i;
                if(i < currentStoryIndex) fill.style.width = '100%';
                else if(i === currentStoryIndex) fill.style.width = '0%';
                
                bar.appendChild(fill);
                container.appendChild(bar);
            });
        }

        function startProgress() {
            stopProgress();
            const start = Date.now();
            progressInterval = setInterval(() => {
                const elapsed = Date.now() - start;
                const pct = Math.min(100, (elapsed / storyDuration) * 100);
                const fill = document.getElementById('progress-' + currentStoryIndex);
                if(fill) fill.style.width = pct + '%';
                
                if(elapsed >= storyDuration) nextStory();
            }, 50);
        }

        function stopProgress() {
            if(progressInterval) clearInterval(progressInterval);
        }

        function nextStory() {
            if(currentStoryIndex < currentStories.length - 1) {
                currentStoryIndex++;
                loadCurrentStory();
            } else {
                closeStoryModal();
            }
        }

        function prevStory() {
            if(currentStoryIndex > 0) {
                currentStoryIndex--;
                loadCurrentStory();
            }
        }
        
        function recordView(storyId) {
            // Only record if not owner? usually we do record
            fetch('story.php?view_story=' + storyId);
        }

        function formatTime(ts) {
            // Simple formatter
            return new Date(ts).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Init bootstrap tooltips/popovers if needed
    </script>
</body>
</html>