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
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول المسؤول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h3 class="text-center mb-4">لوحة التحكم</h3>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login_admin" class="btn btn-primary w-100">دخول</button>
        </form>
    </div>
</body>
</html>
<?php
    exit(); // Stop execution here if not logged in
}

// --- ADMIN PANEL CONTENT (Only accessible if logged in) ---

// 1. Ensure 'email' column exists in users table
$check_email = mysqli_query($con, "SHOW COLUMNS FROM users LIKE 'email'");
if (mysqli_num_rows($check_email) == 0) {
    mysqli_query($con, "ALTER TABLE users ADD email VARCHAR(255) NULL");
}

// 2. Handle User Actions (Delete / Edit)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Delete User
    if (isset($_POST['delete_user_id'])) {
        $user_id = intval($_POST['delete_user_id']);
        // Delete related data first (optional but recommended)
        mysqli_query($con, "DELETE FROM video_views WHERE user_id = $user_id");
        mysqli_query($con, "DELETE FROM story_views WHERE user_id = $user_id");
        mysqli_query($con, "DELETE FROM stories WHERE user_id = $user_id");
        mysqli_query($con, "DELETE FROM videos WHERE user_id = $user_id");
        // Delete user
        mysqli_query($con, "DELETE FROM users WHERE id = $user_id");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }

    // Edit User
    if (isset($_POST['edit_user_id'])) {
        $user_id = intval($_POST['edit_user_id']);
        $username = mysqli_real_escape_string($con, $_POST['username']);
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $password = mysqli_real_escape_string($con, $_POST['password']); // Store as plain text for now as requested
        
        $sql = "UPDATE users SET username='$username', email='$email', password='$password' WHERE id=$user_id";
        mysqli_query($con, $sql);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }

    // Delete Video
    if (isset($_POST['delete_video_id'])) {
        $video_id = intval($_POST['delete_video_id']);
        mysqli_query($con, "DELETE FROM video_views WHERE video_id = $video_id");
        mysqli_query($con, "DELETE FROM videos WHERE id = $video_id");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }

    // Restore Video
    if (isset($_POST['restore_video_id'])) {
        $video_id = intval($_POST['restore_video_id']);
        mysqli_query($con, "UPDATE videos SET status = 'active' WHERE id = $video_id");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

// 3. Handle AJAX Data Fetching for User Details
if (isset($_GET['fetch_details']) && isset($_GET['user_id'])) {
    $uid = intval($_GET['user_id']);
    
    // Fetch Statistics
    $v_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM videos WHERE user_id = $uid"))['c'];
    $s_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM stories WHERE user_id = $uid"))['c'];
    $p_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM posts WHERE user_id = $uid"))['c'];
    $c_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM comments WHERE user_id = $uid"))['c'];
    $m_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM messages WHERE sender_id = $uid OR receiver_id = $uid"))['c'];
    
    ?>
    <div class="row g-3">
        <!-- Stats Widgets -->
        <div class="col-md-4">
            <div class="card bg-light border-0 text-center p-3">
                <h5><i class="fas fa-video text-primary"></i> الفيديوهات</h5>
                <h3 class="mb-0"><?= $v_cnt ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light border-0 text-center p-3">
                <h5><i class="fas fa-camera text-info"></i> القصص</h5>
                <h3 class="mb-0"><?= $s_cnt ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light border-0 text-center p-3">
                <h5><i class="fas fa-file-alt text-success"></i> المنشورات</h5>
                <h3 class="mb-0"><?= $p_cnt ?></h3>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mt-4" id="userTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-videos">فيديوهات</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-posts">منشورات</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-stories">قصص</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-comments">تعليقات</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-messages">رسائل</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-likes">إعجابات</button></li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white rounded-bottom" id="userTabsContent">
        <div class="tab-pane fade show active" id="tab-videos">
            <div class="row row-cols-1 row-cols-md-3 g-2">
                <?php
                $vids = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $uid ORDER BY id DESC");
                while($v = mysqli_fetch_assoc($vids)): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <video class="card-img-top" src="<?= $v['location'] ?>" style="height: 150px; background: #000;"></video>
                            <div class="card-body p-2">
                                <small class="d-block text-truncate fw-bold"><?= htmlspecialchars($v['title']) ?></small>
                                <small class="text-muted"><?= $v['status'] ?></small>
                            </div>
                        </div>
                    </div>
                <?php endwhile; if(mysqli_num_rows($vids)==0) echo "<p class='text-muted text-center'>لا توجد فيديوهات</p>"; ?>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-posts">
            <?php
            $psts = mysqli_query($con, "SELECT * FROM posts WHERE user_id = $uid ORDER BY created_at DESC");
            while($p = mysqli_fetch_assoc($psts)): ?>
                <div class="border-bottom mb-3 pb-2">
                    <div class="d-flex align-items-start">
                        <?php if(!empty($p['image_path'])): ?>
                            <img src="<?= $p['image_path'] ?>" style="width: 80px; height: 80px; object-fit: cover; margin-left: 15px;" class="rounded border">
                        <?php endif; ?>
                        <div>
                            <p class="mb-1 fw-bold"><?= htmlspecialchars($p['content']) ?></p>
                            <small class="text-muted"><i class="far fa-clock"></i> <?= $p['created_at'] ?></small>
                            <div class="mt-1">
                                <?php 
                                    $pid = $p['id'];
                                    $l_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM post_likes WHERE post_id = $pid"))['c'];
                                    $c_cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM comments WHERE post_id = $pid"))['c'];
                                ?>
                                <span class="badge bg-light text-dark border"><i class="fas fa-heart text-danger"></i> <?= $l_cnt ?></span>
                                <span class="badge bg-light text-dark border ms-1"><i class="fas fa-comment text-primary"></i> <?= $c_cnt ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; if(mysqli_num_rows($psts)==0) echo "<p class='text-muted text-center py-3'>لا توجد منشورات</p>"; ?>
        </div>
        <div class="tab-pane fade" id="tab-stories">
            <div class="row row-cols-2 row-cols-md-4 g-2">
                <?php
                $strs = mysqli_query($con, "SELECT * FROM stories WHERE user_id = $uid ORDER BY created_at DESC");
                while($s = mysqli_fetch_assoc($strs)): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0">
                            <?php if($s['file_type'] == 'image'): ?>
                                <img src="<?= $s['file_path'] ?>" class="card-img-top rounded" style="height: 120px; object-fit: cover;">
                            <?php else: ?>
                                <video src="<?= $s['file_path'] ?>" class="card-img-top rounded" style="height: 120px; background: #000;"></video>
                            <?php endif; ?>
                            <div class="card-body p-1 text-center font-small">
                                <?php 
                                    $sid = $s['id'];
                                    $v_res = mysqli_query($con, "SELECT COUNT(*) as c FROM story_views WHERE story_id = $sid");
                                    $v_count = mysqli_fetch_assoc($v_res)['c'];
                                ?>
                                <small class="text-muted d-block" style="font-size: 10px;"><?= date('m/d H:i', strtotime($s['created_at'])) ?></small>
                                <span class="badge bg-info text-white view-story-btn" 
                                      style="font-size: 10px; cursor: pointer;" 
                                      data-sid="<?= $sid ?>" 
                                      onclick="showStoryViewers(<?= $sid ?>, event)">
                                    <?= $v_count ?> مشاهدة
                                </span>
                                <div id="viewers-list-<?= $sid ?>" class="d-none mt-1 p-1 border rounded bg-light text-start" style="font-size: 9px; max-height: 100px; overflow-y: auto;">
                                    <?php
                                    $vrs = mysqli_query($con, "SELECT u.username FROM story_views sv JOIN users u ON sv.user_id = u.id WHERE sv.story_id = $sid");
                                    while($vr = mysqli_fetch_assoc($vrs)) echo "• ".htmlspecialchars($vr['username'])."<br>";
                                    if(mysqli_num_rows($vrs)==0) echo "لا توجد مشاهدات";
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; if(mysqli_num_rows($strs)==0) echo "<div class='col-12 text-center text-muted'>لا توجد قصص</div>"; ?>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-comments">
            <?php
            $cmts = mysqli_query($con, "SELECT * FROM comments WHERE user_id = $uid ORDER BY created_at DESC");
            while($c = mysqli_fetch_assoc($cmts)): ?>
                <div class="border-bottom mb-2 pb-2">
                    <p class="mb-1 italic"><?= htmlspecialchars($c['comment']) ?></p>
                    <small class="text-muted">على: <?= $c['post_id'] ? "منشور #".$c['post_id'] : ($c['video_id'] ? "فيديو #".$c['video_id'] : "غير معروف") ?> | <?= $c['created_at'] ?></small>
                </div>
            <?php endwhile; if(mysqli_num_rows($cmts)==0) echo "<p class='text-muted text-center'>لا توجد تعليقات</p>"; ?>
        </div>
        <div class="tab-pane fade" id="tab-messages">
             <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>من/إلى</th><th>الرسالة</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        <?php
                        $msgs = mysqli_query($con, "SELECT m.*, u1.username as sender, u2.username as receiver 
                                                  FROM messages m 
                                                  JOIN users u1 ON m.sender_id = u1.id 
                                                  JOIN users u2 ON m.receiver_id = u2.id 
                                                  WHERE sender_id = $uid OR receiver_id = $uid 
                                                  ORDER BY created_at DESC");
                        while($m = mysqli_fetch_assoc($msgs)): 
                            $msg_text = $m['message'];
                            if(strpos($msg_text, '[[VIDEO_CALL]]') !== false) {
                                $parts = explode('|', $msg_text);
                                if(count($parts) >= 3) {
                                    $msg_text = '<span class="text-primary"><i class="fas fa-video"></i> مكالمة فيديو</span>';
                                }
                            } elseif (strpos($msg_text, '[[IMAGE]]') !== false) {
                                $path = str_replace('[[IMAGE]]', '', $msg_text);
                                $msg_text = '<img src="'.$path.'" style="width:100px; height:60px; object-fit:cover;" class="rounded border" onclick="window.open(this.src)">';
                            } elseif (strpos($msg_text, '[[VIDEO]]') !== false) {
                                $path = str_replace('[[VIDEO]]', '', $msg_text);
                                $msg_text = '<video src="'.$path.'" style="width:100px; height:60px; background:#000;" class="rounded border" controls></video>';
                            } elseif (strpos($msg_text, '[[AUDIO]]') !== false) {
                                $path = str_replace('[[AUDIO]]', '', $msg_text);
                                $msg_text = '<audio src="'.$path.'" style="width:150px; height:30px;" controls></audio>';
                            }
                        ?>
                            <tr class="<?= $m['sender_id'] == $uid ? 'table-light' : '' ?>">
                                <td><small class="fw-bold"><?= $m['sender'] ?> ➔ <?= $m['receiver'] ?></small></td>
                                <td><small><?= $msg_text ?></small></td>
                                <td><small class="text-muted"><?= date('m/d H:i', strtotime($m['created_at'])) ?></small></td>
                            </tr>
                        <?php endwhile; if(mysqli_num_rows($msgs)==0) echo "<tr><td colspan='3' class='text-center'>لا توجد رسائل</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        <div class="tab-pane fade" id="tab-likes">
            <h6>إعجابات المنشورات:</h6>
            <?php
            $lks = mysqli_query($con, "SELECT pl.*, p.content FROM post_likes pl JOIN posts p ON pl.post_id = p.id WHERE pl.user_id = $uid");
            while($l = mysqli_fetch_assoc($lks)): ?>
                 <small class='d-block border-bottom mb-1 pb-1'>أعجب بمنشور: "<?= mb_substr($l['content'],0,30) ?>..."</small>
            <?php endwhile; if(mysqli_num_rows($lks)==0) echo "<small class='text-muted d-block'>لا توجد إعجابات منشورات</small>"; ?>

            <h6 class="mt-3">إعجابات الفيديوهات:</h6>
            <?php
            $vlks = mysqli_query($con, "SELECT vl.*, v.title FROM video_likes vl JOIN videos v ON vl.video_id = v.id WHERE vl.user_id = $uid");
            while($l = mysqli_fetch_assoc($vlks)): ?>
                 <small class='d-block border-bottom mb-1 pb-1'>أعجب بفيديو: "<?= htmlspecialchars($l['title']) ?>"</small>
            <?php endwhile; if(mysqli_num_rows($vlks)==0) echo "<small class='text-muted'>لا توجد إعجابات فيديوهات</small>"; ?>
        </div>
    </div>
    <?php
    exit();
}

// Fetch Data
$fetchReportedVideos = mysqli_query($con, "SELECT * FROM videos WHERE status = 'signale'");
$fetchUsers = mysqli_query($con, "SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المسؤول - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">Admin Panel</span>
            <div class="d-flex align-items-center">
                 <span class="text-white me-3 ms-3">Welcome Admin</span>
                 <a href="?logout=true" class="btn btn-danger btn-sm">تسجيل خروج</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        
        <!-- Reported Videos Section -->
        <div class="card mb-5 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="fas fa-flag"></i> الفيديوهات المبلغ عنها</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>الفيديو</th>
                                <th>العنوان</th>
                                <th>الموضوع</th>
                                <th>تاريخ الإبلاغ</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($fetchReportedVideos) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($fetchReportedVideos)): ?>
                                    <tr>
                                        <td>
                                            <video width="120" height="80" controls class="rounded border">
                                                <source src="<?= $row['location']; ?>" type="video/mp4">
                                            </video>
                                        </td>
                                        <td><?= $row['title']; ?></td>
                                        <td><?= $row['subject']; ?></td>
                                        <td><?= $row['reported_at']; ?></td>
                                        <td>
                                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا الفيديو؟');">
                                                <input type="hidden" name="delete_video_id" value="<?= $row['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> حذف</button>
                                            </form>
                                            <form action="" method="POST" style="display:inline;">
                                                <input type="hidden" name="restore_video_id" value="<?= $row['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> استعادة</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">لا توجد فيديوهات مبلغ عنها</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Users Management Section -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-users"></i> إدارة المستخدمين</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>اسم المستخدم</th>
                                <th>البريد الإلكتروني</th>
                                <th>كلمة المرور</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($fetchUsers)): ?>
                                <tr>
                                    <td><?= $user['id']; ?></td>
                                    <td><?= htmlspecialchars($user['username']); ?></td>
                                    <td><?= isset($user['email']) ? htmlspecialchars($user['email']) : 'N/A'; ?></td>
                                    <td class="text-muted" style="font-family: monospace;"><?= htmlspecialchars($user['password']); ?></td>
                                    <td>
                                        <!-- View Details Button -->
                                        <button class="btn btn-info btn-sm text-white" onclick="viewUserDetails(<?= $user['id']; ?>, '<?= htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-eye"></i> تفاصيل
                                        </button>

                                        <!-- Edit Button -->
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id']; ?>">
                                            <i class="fas fa-edit"></i> تعديل
                                        </button>
                                        
                                        <!-- Delete Button -->
                                        <form action="" method="POST" style="display:inline;" onsubmit="return confirm('⚠️ تحذير: سيتم حذف المستخدم وجميع بياناته (فيديوهات، قصص، إلخ). هل أنت متأكد؟');">
                                            <input type="hidden" name="delete_user_id" value="<?= $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> حذف</button>
                                        </form>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editUserModal<?= $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">تعديل المستخدم: <?= htmlspecialchars($user['username']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_user_id" value="<?= $user['id']; ?>">
                                                            <div class="mb-3">
                                                                 <label class="form-label">اسم المستخدم</label>
                                                                 <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                 <label class="form-label">البريد الإلكتروني</label>
                                                                 <input type="email" name="email" class="form-control" value="<?= isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                 <label class="form-label">كلمة المرور</label>
                                                                 <input type="text" name="password" class="form-control" value="<?= htmlspecialchars($user['password']); ?>" required>
                                                                 <small class="text-muted">يمكنك تغيير كلمة المرور هنا</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- User Details Modal -->
        <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="userDetailsTitle">تفاصيل المستخدم</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="userDetailsBody" dir="rtl">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewUserDetails(userId, username) {
            document.getElementById('userDetailsTitle').innerText = 'تفاصيل المستخدم: ' + username;
            document.getElementById('userDetailsBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
            
            var userModalEl = document.getElementById('userDetailsModal');
            var myModal = new bootstrap.Modal(userModalEl);
            myModal.show();

            fetch('admin-cpanl.php?fetch_details=1&user_id=' + userId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('userDetailsBody').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('userDetailsBody').innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء تحميل البيانات</div>';
                });
        }

        function showStoryViewers(sid, event) {
            event.stopPropagation();
            let list = document.getElementById('viewers-list-' + sid);
            if (list.classList.contains('d-none')) {
                list.classList.remove('d-none');
            } else {
                list.classList.add('d-none');
            }
        }
    </script>
</body>
</html>