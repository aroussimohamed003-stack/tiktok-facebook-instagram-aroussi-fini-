<?php
session_start();

include("config.php");
// جلب جميع الفيديوهات التي تم تعيينها كـ Sponsor
$videos = $con->query("SELECT * FROM videos WHERE is_sponsor = 1 ORDER BY RAND()");
?>

<?php
// Set page title
$pageTitle = "الفيديوهات المميزة";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
.video-item {
    margin-bottom: 20px;
    padding: 10px;
    border: 1px solid #ffc107;
    border-radius: 10px;
    background-color: var(--card-bg);
    box-shadow: 0 4px 8px var(--shadow-color);
}
.sponsor-badge {
    background-color: #ffc107;
    color: #000;
    padding: 5px 10px;
    border-radius: 5px;
    font-weight: bold;
}
.video-player {
    width: 100%;
    height: auto;
    border-radius: 10px;
}
.btn-primary {
    margin-top: 5px;
}
@media (max-width: 768px) {
    h1 { font-size: 24px; }
    .video-item { padding: 5px; }
    .btn-primary { width: 100%; margin-top: 5px; }
}
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>




    <div class="container mt-5">
      <br><br>  <h1 class="text-center mb-4">الفيديوهات المميزة (Sponsor)</h1>
        <div class="row">
            <?php while ($video = $videos->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="video-item">
                        <span class="sponsor-badge">Sponsor</span>
                        <video src="<?php echo $video['location']; ?>" class="video-player" controls></video>
                        <h3><?php echo htmlspecialchars($video['title']); ?></h3>
                        <p><?php echo htmlspecialchars($video['subject']); ?></p>
                        <p>المشاهدات: <?php echo $video['views']; ?></p>
                        <a href="?download=<?php echo $video['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-download"></i> تنزيل الفيديو
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

<?php
// Include footer
include("includes/footer.php");
?>
