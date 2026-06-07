<?php
session_start();
include("config.php");
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
        }
    }
}

// معالجة Sponsor
if (isset($_GET['unsponsor'])) {
    $video_id = intval($_GET['unsponsor']);
    $con->query("UPDATE videos SET is_sponsor = 0 WHERE id = $video_id AND user_id = " . $_SESSION['user_id']);
    header("Location: sponsor.php");
    exit;
}
if (isset($_GET['sponsor'])) {
    $video_id = intval($_GET['sponsor']);
    $con->query("UPDATE videos SET is_sponsor = 1 WHERE id = $video_id AND user_id = " . $_SESSION['user_id']);
    header("Location: sponsor.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$videos = $con->query("SELECT * FROM videos WHERE user_id = $user_id ORDER BY is_sponsor DESC, id DESC");

include("includes/layout_start.php");
?>

<div class="mb-10 text-center">
    <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">My Content Portfolio</h1>
    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Manage your videos and sponsored status</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-20">
    <?php while ($video = $videos->fetch_assoc()): ?>
        <article class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden transition-all hover:shadow-xl hover:shadow-slate-100 group">
            <div class="relative aspect-[9/16] md:aspect-video bg-black overflow-hidden">
                <video src="<?php echo $video['location']; ?>" class="w-full h-full object-cover" controls preload="metadata"></video>
                <?php if ($video['is_sponsor']): ?>
                    <div class="absolute top-4 right-4 bg-amber-400 text-amber-950 px-3 py-1.5 rounded-xl font-black text-[10px] uppercase tracking-widest flex items-center gap-1.5 shadow-lg shadow-amber-200/50 animate-pulse">
                        <i class="fas fa-star"></i> Sponsored
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 mb-1 leading-tight"><?php echo htmlspecialchars($video['title']); ?></h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($video['subject']); ?></p>
                    </div>
                    <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl">
                        <i class="fas fa-eye text-blue-500 text-[10px]"></i>
                        <span class="text-[11px] font-black text-slate-700"><?php echo number_format($video['views']); ?></span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-50">
                    <a href="?download=<?php echo $video['id']; ?>" class="flex-1 min-w-[120px] bg-slate-900 text-white px-4 py-3 rounded-2xl font-bold text-[10px] uppercase tracking-widest text-center hover:bg-slate-800 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-download"></i> Download
                    </a>
                    
                    <?php if ($video['is_sponsor']): ?>
                        <a href="?unsponsor=<?php echo $video['id']; ?>" class="flex-1 min-w-[120px] bg-red-50 text-red-600 px-4 py-3 rounded-2xl font-bold text-[10px] uppercase tracking-widest text-center hover:bg-red-100 transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i class="fas fa-times-circle"></i> Remove
                        </a>
                    <?php else: ?>
                        <a href="?sponsor=<?php echo $video['id']; ?>" class="flex-1 min-w-[120px] bg-amber-400 text-amber-950 px-4 py-3 rounded-2xl font-bold text-[10px] uppercase tracking-widest text-center hover:bg-amber-300 transition-all active:scale-95 flex items-center justify-center gap-2 shadow-lg shadow-amber-100">
                            <i class="fas fa-star"></i> Sponsor
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
    
    <?php if ($videos->num_rows == 0): ?>
        <div class="col-span-full py-20 bg-white rounded-[3rem] text-center border-2 border-dashed border-slate-100">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-video-slash text-slate-200 text-3xl"></i>
            </div>
            <h3 class="text-slate-900 font-bold text-xl mb-2">No videos yet</h3>
            <p class="text-slate-400 text-sm font-medium">Start uploading to showcase your content here!</p>
            <a href="uplod-profile.php" class="inline-block mt-8 bg-blue-600 text-white px-8 py-3 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-blue-200">Upload Content</a>
        </div>
    <?php endif; ?>
</div>

<?php include("includes/layout_end.php"); ?>