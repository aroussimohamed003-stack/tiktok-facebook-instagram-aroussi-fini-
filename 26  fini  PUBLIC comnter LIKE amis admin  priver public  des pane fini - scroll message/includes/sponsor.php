<?php
session_start(); // بدء الجلسة

include("config.php");
// إنشاء جدول الفيديوهات إذا لم يكن موجودًا
$con->query("
    CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        location VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        subject VARCHAR(255),
        views INT DEFAULT 0,
        is_sponsor TINYINT(1) DEFAULT 0,
        user_id INT
    )
");

// معالجة تنزيل الفيديو
if (isset($_GET['download'])) {
    $video_id = intval($_GET['download']);
    $query = $con->query("SELECT location FROM videos WHERE id = $video_id");
    if ($query->num_rows > 0) {
        $row = $query->fetch_assoc();
        $file_path = $row['location'];
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            echo "الملف غير موجود.";
        }
    } else {
        echo "الفيديو غير موجود.";
    }
}

// معالجة إزالة الفيديو من Sponsor
if (isset($_GET['unsponsor'])) {
    $video_id = intval($_GET['unsponsor']);
    $con->query("UPDATE videos SET is_sponsor = 0 WHERE id = $video_id");
    header("Location: " . $_SERVER['PHP_SELF']); // إعادة تحميل الصفحة
    exit;
}

// معالجة تعيين الفيديو كـ Sponsor
if (isset($_GET['sponsor'])) {
    $video_id = intval($_GET['sponsor']);
    $con->query("UPDATE videos SET is_sponsor = 1 WHERE id = $video_id");
    header("Location: " . $_SERVER['PHP_SELF']); // إعادة تحميل الصفحة
    exit;
}

// جلب الفيديوهات الخاصة بالمستخدم الحالي
$user_id = $_SESSION['user_id']; // افترض أن معرف المستخدم مخزن في الجلسة
$videos = $con->query("SELECT * FROM videos WHERE user_id = $user_id ORDER BY is_sponsor DESC, RAND()");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض الفيديوهات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #000; color: #fff; }
        .video-item { margin-bottom: 20px; padding: 10px; border: 1px solid #444; border-radius: 10px; }
        .video-item.sponsor { border-color: #ffc107; background-color: #222; }
        .sponsor-badge { background-color: #ffc107; color: #000; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .video-player { width: 100%; height: auto; border-radius: 10px; }
        .btn-sponsor { background-color: #28a745; color: #fff; border: none; padding: 5px 10px; border-radius: 5px; }
        .btn-sponsor:hover { background-color: #218838; }
        .btn-danger { margin-top: 5px; }
        @media (max-width: 768px) {
            h1 { font-size: 24px; }
            .video-item { padding: 5px; }
            .btn-sponsor, .btn-danger { width: 100%; margin-top: 5px; }
        }
    </style>
</head>
<body>
    
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">😎 Mohamed Aroussi😎</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php?logout=true" class="btn btn-danger logout-btn">
                    تسجيل الخروج <i class="fas fa-sign-out-alt"></i>
                </a>
            <?php endif; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">مرحبًا</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                  
                  
                           <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="indexmo.php">home</a>
                        </li>
                        
                          
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="sponsor-add.php">sponsoriser</a>
                        </li>
                        
                        
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="story.php">story</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="message.php">message</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">profile</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    
    
    <div class="container mt-5">
        <h1 class="text-center mb-4">الفيديوهات الخاصة بي</h1>
        <div class="row">
            <?php while ($video = $videos->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="video-item <?php echo $video['is_sponsor'] ? 'sponsor' : ''; ?>">
                        <?php if ($video['is_sponsor']): ?>
                            <span class="sponsor-badge">Sponsor</span>
                        <?php endif; ?>
                        <video src="<?php echo $video['location']; ?>" class="video-player" controls></video>
                        <h3><?php echo htmlspecialchars($video['title']); ?></h3>
                        <p><?php echo htmlspecialchars($video['subject']); ?></p>
                        <p>المشاهدات: <?php echo $video['views']; ?></p>
                        <a href="?download=<?php echo $video['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-download"></i> تنزيل الفيديو
                        </a>
                        <a href="?sponsor=<?php echo $video['id']; ?>" class="btn btn-sponsor">
                            تعيين كـ Sponsor
                        </a>
                        <?php if ($video['is_sponsor']): ?>
                            <a href="?unsponsor=<?php echo $video['id']; ?>" class="btn btn-danger">
                                إزالة Sponsor
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>