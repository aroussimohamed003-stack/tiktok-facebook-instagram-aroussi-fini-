<?php
session_start(); // بدء الجلسة

// معالجة تسجيل الخروج
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// تضمين الاتصال بقاعدة البيانات
include("config.php");

// إصلاح خطأ المفتاح الأجنبي في جدول التعليقات (يشير إلى videoss بدلاً من videos)
try {
    $db_res = mysqli_query($con, "SELECT DATABASE()");
    $db_row = mysqli_fetch_row($db_res);
    $db_name = $db_row[0];

    $check_fk = mysqli_query($con, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'comments' AND COLUMN_NAME = 'video_id' AND REFERENCED_TABLE_NAME = 'videoss' AND TABLE_SCHEMA = '$db_name'");
    
    if ($check_fk && mysqli_num_rows($check_fk) > 0) {
        $row = mysqli_fetch_assoc($check_fk);
        $fk_name = $row['CONSTRAINT_NAME'];
        // حذف القيد الخاطئ
        mysqli_query($con, "ALTER TABLE comments DROP FOREIGN KEY `$fk_name`");
        // إضافة القيد الصحيح
        mysqli_query($con, "ALTER TABLE comments ADD CONSTRAINT `comments_fk_videos_fixed` FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE");
    }
} catch (Exception $e) {
    // تجاهل الأخطاء لتجنب توقف الصفحة
}

// التأكد من وجود عمود المشاهدات وحالة الفيديو
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS views INT DEFAULT 0");
mysqli_query($con, "ALTER TABLE videos ADD COLUMN IF NOT EXISTS status ENUM('active', 'signale') DEFAULT 'active'");

// إنشاء جدول المشاهدين إذا لم يكن موجودًا
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS video_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        user_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id)
    )
");

// معالجة حذف الفيديو
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_video_id'])) {
    $video_id = intval($_POST['delete_video_id']);

    // جلب مسار الفيديو من قاعدة البيانات
    $query = mysqli_query($con, "SELECT location FROM videos WHERE id = $video_id");
    $row = mysqli_fetch_assoc($query);

    if ($row) {
        $file_path = $row['location'];

        // حذف الفيديو من قاعدة البيانات
        mysqli_query($con, "DELETE FROM videos WHERE id = $video_id");

        // حذف الملف من المجلد
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        header("Location: coment.php");
        exit();
    }
}

// معالجة تحديث عدد المشاهدات عبر AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_views_id'])) {
    $video_id = intval($_POST['update_views_id']);
    $user_id = $_SESSION['user_id']; // الحصول على user_id من الجلسة

    // تسجيل المشاهدة
    mysqli_query($con, "INSERT INTO video_views (video_id, user_id) VALUES ($video_id, $user_id)");

    // تحديث عدد المشاهدات
    mysqli_query($con, "UPDATE videos SET views = views + 1 WHERE id = $video_id");

    // جلب عدد المشاهدات الجديد
    $result = mysqli_query($con, "SELECT views FROM videos WHERE id = $video_id");
    $row = mysqli_fetch_assoc($result);
    echo json_encode(['views' => $row['views']]);
    exit();
}

// معالجة الإبلاغ عن الفيديو
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signal_video_id'])) {
    $video_id = intval($_POST['signal_video_id']);
    mysqli_query($con, "UPDATE videos SET status = 'signale' WHERE id = $video_id");
    header("Location: coment.php");
    exit();
}

// معالجة إضافة التعليق
// معالجة إضافة التعليق
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment'])) {
    $comment = mysqli_real_escape_string($con, $_POST['comment']);
    $video_id = intval($_POST['video_id']);
    $user_id = $_SESSION['user_id']; // الحصول على user_id من الجلسة

    // إدخال التعليق في قاعدة البيانات
    $query = "INSERT INTO comments (video_id, user_id, comment, created_at) VALUES ($video_id, $user_id, '$comment', NOW())";
    if (mysqli_query($con, $query)) {
        // Notify video owner
        $v_query = mysqli_query($con, "SELECT user_id FROM videos WHERE id = $video_id");
        if ($v_query && $v_row = mysqli_fetch_assoc($v_query)) {
             $recipient = $v_row['user_id'];
             mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type, video_id) VALUES ($recipient, $user_id, 'comment', $video_id)");
        }
    }
}

// معالجة حذف التعليق
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_comment_id'])) {
    $comment_id = intval($_POST['delete_comment_id']);

    // التأكد من أن المستخدم هو صاحب التعليق أو لديه الصلاحيات
    $query = mysqli_query($con, "SELECT user_id FROM comments WHERE id = $comment_id");
    $row = mysqli_fetch_assoc($query);

    if ($row && $_SESSION['user_id'] == $row['user_id']) {
        mysqli_query($con, "DELETE FROM comments WHERE id = $comment_id");
    }

    header("Location: coment.php");
    exit();
}

// جلب الفيديوهات النشطة فقط مع معلومات المستخدم
$fetchAllVideos = mysqli_query($con, "SELECT videos.*, users.username
                                       FROM videos
                                       JOIN users ON videos.user_id = users.id
                                       WHERE videos.status = 'active'
                                       ORDER BY RAND()");

?>

<?php
// Set page title
$pageTitle = "عرض الفيديوهات والتعليقات";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
.video-scroller {
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow-y: scroll;
    height: 100vh;
}
.video-item {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px;
}
.video-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    border-radius: 15px;
    overflow: hidden;
    background-color: var(--card-bg);
    box-shadow: 0 4px 12px var(--shadow-color);
}
.video-player {
    width: 100%;
    height: auto;
    border-radius: 15px;
}
.views-counter {
    font-size: 16px;
    font-weight: bold;
    margin: 5px 0;
    color: var(--text-color);
}
.comment {
    background-color: var(--card-bg);
    padding: 10px;
    margin-top: 10px;
    border-radius: 5px;
    border: 1px solid var(--border-color);
    color: var(--text-color);
}
.comment strong {
    color: var(--primary-color);
}
.name-profile {
    text-decoration: underline;
    color: var(--primary-color);
    font-size: 20px;
}
.comments-section {
    background-color: var(--card-bg);
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    border: 1px solid var(--border-color);
}
.video-footer {
    padding: 10px;
    background-color: var(--card-bg);
    border-top: 1px solid var(--border-color);
}
.form-control {
    background-color: var(--bg-color);
    border-color: var(--border-color);
    color: var(--text-color);
}
.form-control:focus {
    background-color: var(--bg-color);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: 0 0 0 0.25rem rgba(103, 61, 230, 0.25);
}
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>

<div class="container-fluid video-scroller">
    <?php
    while ($row = mysqli_fetch_assoc($fetchAllVideos)) {
        $id = $row['id'];
        $location = $row['location'];
        $views = $row['views'];
        $title = $row['title'];
        $user_id = $row['user_id']; // معرف المستخدم الذي رفع الفيديو
        $username = $row['username']; // اسم المستخدم الذي رفع الفيديو

        // جلب التعليقات المرتبطة بالفيديو
        $comments_query = mysqli_query($con, "SELECT comments.*, users.username, users.profile_picture FROM comments
                                              JOIN users ON comments.user_id = users.id
                                              WHERE comments.video_id = $id
                                              ORDER BY comments.created_at DESC");

        echo '<div class="row justify-content-center video-item">';
        echo '  <div class="col-md-6 col-lg-4">';
        echo '    <div class="video-container">';
        echo '      <video controls class="video-player" data-id="'.$id.'" src="'.$location.'"></video>';
        echo '      <div class="video-footer">';

            // جلب بيانات المستخدم بما في ذلك الصورة الشخصية
$user_query = mysqli_query($con, "SELECT username, profile_picture FROM users WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($user_query);

// عرض الصورة الشخصية بجانب اسم المستخدم
echo '     🙍 <a class="name-profile" href="indexmo.php?profile='.$user_id.'">';
if ($user_data['profile_picture']) {
    echo '<img src="'.$user_data['profile_picture'].'" alt="Profile Picture" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;" onerror="this.src=\'uploads/profile.jpg\'">';
} else {
    echo '<img src="uploads/profile.jpg" alt="Default Profile Picture" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;" onerror="this.src=\'uploads/profile.jpg\'">';
}
echo htmlspecialchars($username).'</a>';


        echo '        <p class="views-counter"><strong>المشاهدات:</strong> <span id="views-'.$id.'">'.$views.'</span></p>';
        echo '      </div>';
        echo '    </div>';

        // نموذج إضافة تعليق
        echo '    <form action="" method="POST">';
        echo '      <input type="text" name="comment" class="form-control" placeholder="أضف تعليقك هنا" required>';
        echo '      <input type="hidden" name="video_id" value="'.$id.'">';
        echo '      <button type="submit" class="btn btn-primary mt-2">إضافة تعليق</button>';
        echo '    </form>';

        // عرض التعليقات
        echo '    <div class="comments-section">';
        while ($comment_row = mysqli_fetch_assoc($comments_query)) {
            $comment_pp = !empty($comment_row['profile_picture']) ? $comment_row['profile_picture'] : 'uploads/profile.jpg';
            echo '<div class="comment" style="display: flex; align-items: start; gap: 10px; margin-bottom: 15px;">';
            echo '<img src="'.$comment_pp.'" alt="Avatar" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;" onerror="this.src=\'uploads/profile.jpg\'">';
            echo '<div>';
            echo '<strong>' . htmlspecialchars($comment_row['username']) . '</strong>: ';
            echo htmlspecialchars($comment_row['comment']);

            // زر حذف التعليق (يظهر فقط إذا كان المستخدم هو صاحب التعليق)
            if ($_SESSION['user_id'] == $comment_row['user_id']) {
                echo '<form action="coment.php" method="POST" style="display:inline;">';
                echo '  <input type="hidden" name="delete_comment_id" value="' . $comment_row['id'] . '">';
                echo '  <button type="submit" class="btn btn-danger btn-sm">حذف</button>';
                echo '</form>';
            }

            echo '</div></div>';
        }
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }
    ?>
</div>

<?php
// Set inline JavaScript
$inlineJs = "
$(document).ready(function() {
    // تشغيل الفيديو عند التمرير إليه
    $('.video-player').each(function() {
        var video = $(this)[0];
        var observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting) {
                video.play();
            } else {
                video.pause();
            }
        }, { threshold: 0.5 });

        observer.observe(video);
    });

    // تحديث عدد المشاهدات عند تشغيل الفيديو
    $('.video-player').on('play', function() {
        var video_id = $(this).data('id');
        $.ajax({
            url: 'coment.php',
            method: 'POST',
            data: { update_views_id: video_id },
            success: function(response) {
                var data = JSON.parse(response);
                $('#views-' + video_id).text(data.views);
            }
        });
    });
});
";

// Include footer
include("includes/footer.php");
?>