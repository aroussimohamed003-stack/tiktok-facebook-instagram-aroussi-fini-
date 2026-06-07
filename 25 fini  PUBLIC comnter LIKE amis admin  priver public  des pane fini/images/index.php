<?php
session_start(); // بدء الجلسة

// تضمين الاتصال بقاعدة البيانات
include("config.php");

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
        
        header("Location: index.php");
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
    header("Location: index.php");
    exit();
}

// جلب الفيديوهات النشطة فقط
$fetchAllVideos = mysqli_query($con, "SELECT * FROM videos WHERE status = 'active' ORDER BY RAND()");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>عرض الفيديوهات</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    body { background-color: #000; color: #fff; margin: 0; padding: 0; }
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
        background-color: black; 
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

    .views-counter { 
        font-size: 16px; 
        font-weight: bold; 
        margin: 5px 0; 
    }

    .delete-btn, .signal-btn, .download-btn { 
        position: absolute; 
        z-index: 10; 
        padding: 8px 12px; 
        border-radius: 5px; 
        font-size: 14px; 
        transition: background-color 0.3s ease; 
    }

    .delete-btn { 
        top: 20px; 
        right: 20px; 
        background-color: #dc3545; 
        color: white; 
        border: none; 
    }

    .delete-btn:hover { background-color: #c82333; }

    .signal-btn { 
        top: 100px; 
        left: 20px; 
        background-color: #ffc107; 
        color: black; 
        border: none; 
    }

    .signal-btn:hover { background-color: #e0a800; }

    .download-btn { 
        top: 100px; 
        right: 20px; 
        background-color: #007bff; 
        color: white; 
        border: none; 
    }

    .download-btn:hover { background-color: #0056b3; }

    .video-footer { 
        position: absolute; 
        bottom: 20px; 
        left: 20px; 
        color: white; 
        background-color: rgba(0, 0, 0, 0.5); 
        padding: 10px; 
        border-radius: 5px; 
    }

    .upload-btn { 
        position: fixed; 
        bottom: 20px; 
        right: 20px; 
        z-index: 1000; 
        background-color: #28a745; 
        color: white; 
        border: none; 
        padding: 10px 20px; 
        border-radius: 5px; 
        font-size: 16px; 
    }

    .upload-btn:hover { 
        background-color: #218838; 
    }

    .logout-btn {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        font-size: 16px;
    }

    .logout-btn:hover {
        background-color: #c82333;
    }

    /* عند الشاشات الكبيرة 960px */
    @media (min-width: 960px) {
        .video-scroller { 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
        }
        .video-footer { 
            bottom: 10px; 
            left: 10px; 
        }
        .delete-btn, .signal-btn, .download-btn { 
            font-size: 12px; 
            padding: 6px 10px; 
        }
    }
  </style>
</head>
<body>

<!-- زر تسجيل الخروج -->
<a href="logout.php" class="btn btn-danger logout-btn">
    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
</a>

<div class="video-scroller">
  <?php
  while ($row = mysqli_fetch_assoc($fetchAllVideos)) {
      $id = $row['id'];
      $location = $row['location'];
      $subject = $row['subject'];
      $views = $row['views'];
      $title = $row['title'];

      // جلب قائمة المشاهدين
      $viewers_query = mysqli_query($con, "SELECT users.username FROM video_views 
                                           JOIN users ON video_views.user_id = users.id 
                                           WHERE video_views.video_id = $id 
                                           ORDER BY video_views.viewed_at DESC");
      $viewers = [];
      while ($viewer = mysqli_fetch_assoc($viewers_query)) {
          $viewers[] = $viewer['username'];
      }

      echo '<div class="video-item">';
      echo '  <div class="video-container">';
      echo '    <video src="'.$location.'" class="video-player" data-id="'.$id.'"></video>';
      echo '    <form action="index.php" method="POST">';
      echo '      <input type="hidden" name="signal_video_id" value="'.$id.'">';
      echo '      <button type="submit" class="btn btn-warning signal-btn">🚩 Signalé</button>';
      echo '    </form>';
      echo '    <a href="'.$location.'" download class="btn btn-primary download-btn"><i class="fas fa-download"></i></a>';
      echo '    <div class="video-footer">';
      echo '      <h3 class="description">'.$subject.'</h3>';
      echo '      <p class="description">'.$title.'</p>';
      echo '      <p class="views-counter"><strong>المشاهدات:</strong> <span id="views-'.$id.'">'.$views.'</span></p>';
      echo '      <p class="viewers-list"><strong>المشاهدون:</strong> ' . implode(", ", $viewers) . '</p>';
      echo '    </div>';
      echo '  </div>';
      echo '</div>';
  }
  ?>
</div>

<a href="upload.php" class="btn btn-primary upload-btn">
    <i class="bi bi-cloud-arrow-down-fill"></i> رفع الفيديو
</a>

<script>
document.addEventListener("DOMContentLoaded", function () {
    let videos = document.querySelectorAll("video");

    let observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.target.dataset.manual) {
                if (entry.isIntersecting) {
                    entry.target.play();
                } else {
                    entry.target.pause();
                }
            }
        });
    }, { threshold: 0.7 });

    videos.forEach(video => {
        observer.observe(video);

        video.addEventListener("play", function () {
            if (!video.dataset.viewed) {
                let videoId = video.dataset.id;
                
                setTimeout(() => {
                    fetch("index.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "update_views_id=" + videoId
                    })
                    .then(response => response.json())
                    .then(data => {
                        let viewsCounter = document.querySelector("#views-" + videoId);
                        if (viewsCounter) viewsCounter.textContent = data.views + " مشاهدات";
                    });
                    video.dataset.viewed = true;
                }, 10000);
            }
        });

        video.addEventListener("click", function () {
            video.dataset.manual = true;
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>
</html>