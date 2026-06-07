<?php
session_start();
include("config.php");

// Hardcoded Admin Credentials
$admin_email = "mohamed@gmail.com";
$admin_pass = "aroussi123";

// Handle Login
if (isset($_POST['login_admin'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    if ($email === $admin_email && $pass === $admin_pass) {
        $_SESSION['is_admin'] = true;
        header("Location: admin-cpanl.php");
        exit();
    } else {
        $error = "بيانات الدخول غير صحيحة";
    }
}

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header("Location: admin-cpanl.php");
    exit();
}

// Show Login Form if not authorized
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
?>
<!DOCTYPE html>
<html class="dark" lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول المسؤول</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .login-gradient { background: linear-gradient(135deg, #0b0e14 0%, #161b26 100%); }
    </style>
</head>
<body class="login-gradient min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-[#161b26] border border-white/5 rounded-2xl p-8 shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-white text-2xl font-bold">لوحة التحكم</h1>
            <p class="text-slate-400 mt-2">يرجى تسجيل الدخول للمتابعة</p>
        </div>
        <?php if(isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-3 rounded-xl text-center mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-2">البريد الإلكتروني</label>
                <input type="email" name="email" class="w-full bg-slate-900/50 border border-white/5 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-primary outline-none transition-all" required>
            </div>
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-2">كلمة المرور</label>
                <input type="password" name="password" class="w-full bg-slate-900/50 border border-white/5 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-primary outline-none transition-all" required>
            </div>
            <button type="submit" name="login_admin" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-600/20">دخول</button>
        </form>
    </div>
</body>
</html>
<?php exit(); }

// --- ADMIN PANEL LOGIC ---
// Ensure email exists
$check_email = mysqli_query($con, "SHOW COLUMNS FROM users LIKE 'email'");
if (mysqli_num_rows($check_email) == 0) { mysqli_query($con, "ALTER TABLE users ADD email VARCHAR(255) NULL"); }

// Handle Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_user_id'])) {
        $uid = intval($_POST['delete_user_id']);
        mysqli_query($con, "DELETE FROM video_views WHERE user_id = $uid");
        mysqli_query($con, "DELETE FROM story_views WHERE user_id = $uid");
        mysqli_query($con, "DELETE FROM stories WHERE user_id = $uid");
        mysqli_query($con, "DELETE FROM videos WHERE user_id = $uid");
        mysqli_query($con, "DELETE FROM users WHERE id = $uid");
        header("Location: admin-cpanl.php"); exit();
    }
    if (isset($_POST['edit_user_id'])) {
        $uid = intval($_POST['edit_user_id']);
        $u = mysqli_real_escape_string($con, $_POST['username']);
        $e = mysqli_real_escape_string($con, $_POST['email']);
        $p = mysqli_real_escape_string($con, $_POST['password']);
        mysqli_query($con, "UPDATE users SET username='$u', email='$e', password='$p' WHERE id=$uid");
        header("Location: admin-cpanl.php"); exit();
    }
    if (isset($_POST['delete_video_id'])) {
        $vid = intval($_POST['delete_video_id']);
        mysqli_query($con, "DELETE FROM videos WHERE id = $vid");
        header("Location: admin-cpanl.php"); exit();
    }
    if (isset($_POST['restore_video_id'])) {
        $vid = intval($_POST['restore_video_id']);
        mysqli_query($con, "UPDATE videos SET status = 'active' WHERE id = $vid");
        header("Location: admin-cpanl.php"); exit();
    }
}

// Fetch Stats
$total_users = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM users"))['c'];
$reported_count = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM videos WHERE status = 'signale'"))['c'];
$total_videos = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM videos"))['c'];
$total_stories = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM stories"))['c'];

// 3. Handle AJAX Data Fetching for User Details
if (isset($_GET['fetch_details']) && isset($_GET['user_id'])) {
    $uid = intval($_GET['user_id']);
    
    // Fetch Statistics
    $v_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM videos WHERE user_id = $uid"))['c'];
    $s_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM stories WHERE user_id = $uid"))['c'];
    $p_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM posts WHERE user_id = $uid"))['c'];
    $c_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM comments WHERE user_id = $uid"))['c'];
    $m_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM messages WHERE sender_id = $uid OR receiver_id = $uid"))['c'];
    $f_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM friends WHERE (sender_id = $uid OR receiver_id = $uid) AND status = 'accepted'"))['c'];
    
    ?>
    <div class="space-y-6">
        <!-- Stats Widgets -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                <p class="text-slate-400 text-xs mb-1">الفيديوهات</p>
                <h4 class="text-xl font-bold text-primary"><?= $v_cnt ?></h4>
            </div>
            <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                <p class="text-slate-400 text-xs mb-1">القصص</p>
                <h4 class="text-xl font-bold text-secondary"><?= $s_cnt ?></h4>
            </div>
            <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                <p class="text-slate-400 text-xs mb-1">المنشورات</p>
                <h4 class="text-xl font-bold text-green-500"><?= $p_cnt ?></h4>
            </div>
            <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                <p class="text-slate-400 text-xs mb-1">الأصدقاء</p>
                <h4 class="text-xl font-bold text-orange-500"><?= $f_cnt ?></h4>
            </div>
        </div>

        <!-- Detail Sections -->
        <div class="space-y-8">
            <!-- Videos Section -->
            <section>
                <h5 class="text-lg font-bold mb-4 border-b border-white/5 pb-2">🎥 الفيديوهات</h5>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php
                    $vids = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $uid ORDER BY id DESC");
                    while($v = mysqli_fetch_assoc($vids)): ?>
                        <div class="bg-black/50 rounded-xl overflow-hidden border border-white/5 group">
                            <video class="w-full h-32 object-cover" src="<?= $v['location'] ?>"></video>
                            <div class="p-2">
                                <p class="text-[10px] text-slate-300 truncate font-semibold"><?= htmlspecialchars($v['title']) ?></p>
                                <p class="text-[8px] text-slate-500 mt-1"><?= $v['status'] ?></p>
                            </div>
                        </div>
                    <?php endwhile; if(mysqli_num_rows($vids)==0) echo "<p class='text-slate-500 text-xs'>لا توجد فيديوهات</p>"; ?>
                </div>
            </section>

            <!-- Posts Section -->
            <section>
                <h5 class="text-lg font-bold mb-4 border-b border-white/5 pb-2">📝 المنشورات</h5>
                <div class="space-y-4">
                    <?php
                    $psts = mysqli_query($con, "SELECT * FROM posts WHERE user_id = $uid ORDER BY created_at DESC");
                    while($p = mysqli_fetch_assoc($psts)): ?>
                        <div class="bg-white/5 p-4 rounded-xl border border-white/5 flex gap-4">
                            <?php if(!empty($p['image_path'])): ?>
                                <img src="<?= $p['image_path'] ?>" class="size-16 rounded-lg object-cover border border-white/10 shrink-0">
                            <?php endif; ?>
                            <div class="flex-1">
                                <p class="text-xs text-slate-200"><?= htmlspecialchars($p['content']) ?></p>
                                <p class="text-[10px] text-slate-500 mt-2"><?= $p['created_at'] ?></p>
                            </div>
                        </div>
                    <?php endwhile; if(mysqli_num_rows($psts)==0) echo "<p class='text-slate-500 text-xs'>لا توجد منشورات</p>"; ?>
                </div>
            </section>

            <!-- Stories Section -->
            <section>
                <h5 class="text-lg font-bold mb-4 border-b border-white/5 pb-2">📸 القصص</h5>
                <div class="grid grid-cols-3 md:grid-cols-4 gap-3">
                    <?php
                    $strs = mysqli_query($con, "SELECT * FROM stories WHERE user_id = $uid ORDER BY created_at DESC");
                    while($s = mysqli_fetch_assoc($strs)): ?>
                        <div class="relative group aspect-[9/16] bg-black rounded-lg overflow-hidden border border-white/5">
                            <?php if($s['file_type'] == 'image'): ?>
                                <img src="<?= $s['file_path'] ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-all">
                            <?php else: ?>
                                <video src="<?= $s['file_path'] ?>" class="w-full h-full object-cover opacity-80"></video>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex flex-col justify-end p-2">
                                <p class="text-[8px] text-slate-400 capitalize"><?= $s['file_type'] ?></p>
                            </div>
                        </div>
                    <?php endwhile; if(mysqli_num_rows($strs)==0) echo "<div class='col-span-full py-4 text-center text-slate-500 text-xs'>لا توجد قصص</div>"; ?>
                </div>
            </section>

            <!-- Messages Summary Table -->
            <section>
                <h5 class="text-lg font-bold mb-4 border-b border-white/5 pb-2">✉️ سجل الرسائل (آخر 20)</h5>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-500 border-b border-white/5">
                                <th class="pb-2">من/إلى</th>
                                <th class="pb-2">الرسالة</th>
                                <th class="pb-2">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php
                            $msgs = mysqli_query($con, "SELECT m.*, u1.username as sender, u2.username as receiver 
                                                      FROM messages m 
                                                      JOIN users u1 ON m.sender_id = u1.id 
                                                      JOIN users u2 ON m.receiver_id = u2.id 
                                                      WHERE sender_id = $uid OR receiver_id = $uid 
                                                      ORDER BY created_at DESC LIMIT 20");
                            while($m = mysqli_fetch_assoc($msgs)): 
                                $txt = $m['message'];
                                if(strpos($txt, '[[IMAGE]]') !== false) $txt = "📷 صورة";
                                elseif(strpos($txt, '[[VIDEO]]') !== false) $txt = "🎥 فيديو";
                                elseif(strpos($txt, '[[AUDIO]]') !== false) $txt = "🎤 صوتية";
                            ?>
                                <tr>
                                    <td class="py-2"><span class="<?= $m['sender_id'] == $uid ? 'text-blue-400' : 'text-slate-400' ?>"><?= $m['sender'] ?> ➔ <?= $m['receiver'] ?></span></td>
                                    <td class="py-2 text-slate-300 max-w-[200px] truncate"><?= htmlspecialchars($txt) ?></td>
                                    <td class="py-2 text-slate-500"><?= date('m/d H:i', strtotime($m['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; if(mysqli_num_rows($msgs)==0) echo "<tr><td colspan='3' class='text-center py-4 text-slate-500'>لا توجد رسائل</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
    <?php
    exit();
}

$fetchReportedVideos = mysqli_query($con, "SELECT * FROM videos WHERE status = 'signale'");
$fetchUsers = mysqli_query($con, "SELECT * FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html class="dark" lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Modern Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "secondary": "#a855f7",
                        "background-dark": "#0b0e14",
                        "surface-dark": "#161b26",
                    },
                },
            },
        }
    </script>
    <style>
        .glass-sidebar { background: rgba(22, 27, 38, 0.7); backdrop-filter: blur(12px); border-left: 1px solid rgba(255, 255, 255, 0.05); }
        .stat-card-gradient { background: linear-gradient(135deg, rgba(22, 27, 38, 1) 0%, rgba(30, 36, 50, 1) 100%); }
        .neon-glow { box-shadow: 0 0 15px rgba(19, 91, 236, 0.3); }
    </style>
</head>
<body class="bg-background-dark font-display text-slate-100 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="glass-sidebar w-64 hidden lg:flex flex-col fixed inset-y-0 right-0 z-50">
            <div class="p-6 flex items-center gap-3">
                <div class="size-10 bg-gradient-to-br from-primary to-secondary rounded-xl flex items-center justify-center text-white neon-glow">
                    <span class="material-symbols-outlined font-bold">rocket_launch</span>
                </div>
                <div>
                    <h1 class="text-white text-lg font-bold">Aroussi Admin</h1>
                    <p class="text-slate-500 text-xs">لوحة التحكم</p>
                </div>
            </div>
            <nav class="flex-1 px-4 space-y-2 mt-4">
                <a href="#overview" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 text-primary border border-primary/20">
                    <span class="material-symbols-outlined">dashboard</span>
                    <p class="text-sm font-semibold">نظرة عامة</p>
                </a>
                <a href="#users" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-white/5 hover:text-white transition-all">
                    <span class="material-symbols-outlined">group</span>
                    <p class="text-sm font-medium">المستخدمين</p>
                </a>
                <a href="#reported" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-white/5 hover:text-white transition-all">
                    <span class="material-symbols-outlined">flag</span>
                    <p class="text-sm font-medium">البلاغات</p>
                </a>
                <a href="indexmo.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-white/5 hover:text-white transition-all">
                    <span class="material-symbols-outlined">home</span>
                    <p class="text-sm font-medium">العودة للموقع</p>
                </a>
            </nav>
            <div class="p-4 border-t border-white/5">
                <a href="?logout=true" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-400/10 transition-all">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">تسجيل الخروج</p>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:mr-64 flex flex-col min-h-screen">
            <!-- Header -->
            <header class="h-20 flex items-center justify-between px-8 border-b border-white/5 bg-background-dark/50 backdrop-blur-md sticky top-0 z-40">
                <div class="relative w-full max-w-md">
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-500">search</span>
                    <input class="w-full bg-surface-dark/50 border border-white/5 rounded-xl pr-10 pl-4 py-2 text-sm text-slate-200 focus:ring-2 focus:ring-primary outline-none" placeholder="بحث..." type="text"/>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-left hidden sm:block">
                        <p class="text-sm font-bold text-white leading-none">مدير النظام</p>
                        <p class="text-[10px] text-primary font-bold tracking-wider uppercase mt-1">Super Admin</p>
                    </div>
                </div>
            </header>

            <div class="p-8 space-y-8">
                <!-- Stats -->
                <div id="overview" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="stat-card-gradient p-6 rounded-2xl border border-white/5 flex flex-col gap-4 group hover:border-primary/30 transition-all">
                        <div class="flex items-center justify-between">
                            <div class="size-12 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-500"><span class="material-symbols-outlined">group</span></div>
                            <div class="text-xs font-bold text-blue-500">مستخدم</div>
                        </div>
                        <div><p class="text-slate-400 text-sm">إجمالي المستخدمين</p><h3 class="text-2xl font-bold mt-1"><?= $total_users ?></h3></div>
                    </div>
                    <div class="stat-card-gradient p-6 rounded-2xl border border-white/5 flex flex-col gap-4 group hover:border-red-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <div class="size-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500"><span class="material-symbols-outlined">report</span></div>
                            <div class="text-xs font-bold text-red-500">نشط</div>
                        </div>
                        <div><p class="text-slate-400 text-sm">فيديوهات مبلغ عنها</p><h3 class="text-2xl font-bold mt-1"><?= $reported_count ?></h3></div>
                    </div>
                    <div class="stat-card-gradient p-6 rounded-2xl border border-white/5 flex flex-col gap-4 group hover:border-purple-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <div class="size-12 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-500"><span class="material-symbols-outlined">movie</span></div>
                        </div>
                        <div><p class="text-slate-400 text-sm">إجمالي الفيديوهات</p><h3 class="text-2xl font-bold mt-1"><?= $total_videos ?></h3></div>
                    </div>
                    <div class="stat-card-gradient p-6 rounded-2xl border border-white/5 flex flex-col gap-4 group hover:border-cyan-500/30 transition-all">
                        <div class="flex items-center justify-between">
                            <div class="size-12 rounded-xl bg-cyan-500/10 flex items-center justify-center text-cyan-500"><span class="material-symbols-outlined">camera</span></div>
                        </div>
                        <div><p class="text-slate-400 text-sm">إجمالي القصص</p><h3 class="text-2xl font-bold mt-1"><?= $total_stories ?></h3></div>
                    </div>
                </div>

                <!-- Reported Videos -->
                <div id="reported" class="bg-surface-dark border border-white/5 rounded-2xl p-6">
                    <h3 class="text-xl font-bold mb-6 flex items-center gap-2"><span class="material-symbols-outlined text-warning">flag</span> الفيديوهات المبلغ عنها</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right">
                            <thead>
                                <tr class="text-slate-500 text-xs font-bold uppercase border-b border-white/5">
                                    <th class="pb-4">الفيديو</th>
                                    <th class="pb-4">العنوان</th>
                                    <th class="pb-4">التاريخ</th>
                                    <th class="pb-4 text-left">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php while ($row = mysqli_fetch_assoc($fetchReportedVideos)): ?>
                                <tr class="group hover:bg-white/5 transition-all">
                                    <td class="py-4">
                                        <video class="h-16 w-24 rounded-lg bg-black object-cover border border-white/10" src="<?= $row['location']; ?>"></video>
                                    </td>
                                    <td class="py-4"><p class="text-sm font-bold"><?= $row['title']; ?></p></td>
                                    <td class="py-4 text-xs text-slate-500"><?= $row['reported_at']; ?></td>
                                    <td class="py-4 text-left">
                                        <div class="flex gap-2">
                                            <form method="POST" onsubmit="return confirm('حذف نهائي؟')">
                                                <input type="hidden" name="delete_video_id" value="<?= $row['id']; ?>">
                                                <button class="bg-red-500/10 text-red-500 px-3 py-1 rounded-lg text-xs font-bold hover:bg-red-500 hover:text-white transition-all">حذف</button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="restore_video_id" value="<?= $row['id']; ?>">
                                                <button class="bg-green-500/10 text-green-500 px-3 py-1 rounded-lg text-xs font-bold hover:bg-green-500 hover:text-white transition-all">استعادة</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Users Table -->
                <div id="users" class="bg-surface-dark border border-white/5 rounded-2xl p-6">
                    <h3 class="text-xl font-bold mb-6 flex items-center gap-2"><span class="material-symbols-outlined text-primary">group</span> إدارة المستخدمين</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right font-inter">
                            <thead>
                                <tr class="text-slate-500 text-xs font-bold uppercase border-b border-white/5">
                                    <th class="pb-4">المستخدم</th>
                                    <th class="pb-4">البريد</th>
                                    <th class="pb-4">كلمة المرور</th>
                                    <th class="pb-4 text-left">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php while ($user = mysqli_fetch_assoc($fetchUsers)): ?>
                                <tr class="group hover:bg-white/5 transition-all">
                                    <td class="py-4">
                                        <div class="flex items-center gap-3">
                                            <img class="size-10 rounded-full border border-white/10 object-cover" src="<?= !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profile.jpg' ?>" onerror="this.src='uploads/profile.jpg'">
                                            <div>
                                                <p class="text-sm font-bold"><?= htmlspecialchars($user['username']); ?></p>
                                                <p class="text-[10px] text-slate-500">ID: <?= $user['id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 text-sm text-slate-400"><?= $user['email'] ? $user['email'] : 'N/A' ?></td>
                                    <td class="py-4 font-mono text-xs text-slate-500"><?= htmlspecialchars($user['password']); ?></td>
                                    <td class="py-4 text-left">
                                        <div class="flex gap-2">
                                            <button onclick="viewUserDetails(<?= $user['id']; ?>, '<?= $user['username'] ?>')" class="p-2 hover:text-blue-500 transition-all"><span class="material-symbols-outlined text-lg">visibility</span></button>
                                            <button onclick="openEditModal(<?= $user['id']; ?>, '<?= $user['username'] ?>', '<?= $user['email'] ?>', '<?= $user['password'] ?>')" class="p-2 hover:text-green-500 transition-all"><span class="material-symbols-outlined text-lg">edit</span></button>
                                            <form method="POST" class="inline" onsubmit="return confirm('حذف المستخدم نهائياً؟')">
                                                <input type="hidden" name="delete_user_id" value="<?= $user['id']; ?>">
                                                <button class="p-2 hover:text-red-500 transition-all"><span class="material-symbols-outlined text-lg">delete</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-[#161b26] w-full max-w-4xl rounded-2xl border border-white/5 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-white/5 flex justify-between items-center sticky top-0 bg-[#161b26]">
                <h4 id="detTitle" class="text-xl font-bold">تفاصيل المستخدم</h4>
                <button onclick="closeDetails()" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div id="detBody" class="p-8"></div>
        </div>
    </div>

    <!-- Edit Modal (Simple Version) -->
    <div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-[#161b26] w-full max-w-md rounded-2xl border border-white/5 p-8">
            <h4 class="text-xl font-bold mb-6">تعديل المستخدم</h4>
            <form method="POST" class="space-y-4">
                <input type="hidden" id="editUserId" name="edit_user_id">
                <div><label class="text-xs text-slate-400 mb-1 block">الاسم</label><input type="text" id="editName" name="username" class="w-full bg-slate-900 border border-white/5 rounded-xl p-3"></div>
                <div><label class="text-xs text-slate-400 mb-1 block">البريد</label><input type="email" id="editEmail" name="email" class="w-full bg-slate-900 border border-white/5 rounded-xl p-3"></div>
                <div><label class="text-xs text-slate-400 mb-1 block">كلمة المرور</label><input type="text" id="editPass" name="password" class="w-full bg-slate-900 border border-white/5 rounded-xl p-3"></div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 bg-primary py-3 rounded-xl font-bold">حفظ</button>
                    <button type="button" onclick="closeEdit()" class="flex-1 bg-white/5 py-3 rounded-xl">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewUserDetails(uid, name) {
            document.getElementById('detTitle').innerText = 'نشاط المستخدم: ' + name;
            document.getElementById('detBody').innerHTML = '<div class="flex justify-center p-20"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div></div>';
            document.getElementById('detailsModal').style.display = 'flex';
            fetch('admin-cpanl.php?fetch_details=1&user_id=' + uid)
                .then(r => r.text())
                .then(h => { document.getElementById('detBody').innerHTML = h; });
        }
        function closeDetails() { document.getElementById('detailsModal').style.display = 'none'; }
        function openEditModal(uid, name, email, pass) {
            document.getElementById('editUserId').value = uid;
            document.getElementById('editName').value = name;
            document.getElementById('editEmail').value = email;
            document.getElementById('editPass').value = pass;
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEdit() { document.getElementById('editModal').style.display = 'none'; }
    </script>
</body>
</html>