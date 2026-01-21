<?php
session_start();
include("config.php");
include("includes/auto_delete.php");

$isLoggedIn = isset($_SESSION['user_id']);
$my_id = $isLoggedIn ? $_SESSION['user_id'] : 0;

$query = isset($_GET['query']) ? mysqli_real_escape_string($con, $_GET['query']) : '';

$pageTitle = "بحث عن أصدقاء";
include("includes/header.php");
include("includes/navbar.php");
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden" style="background: var(--card-bg, #fff); color: var(--text-color, #333);">
                <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #FE2C55, #a777e3); color: white;">
                    <h4 class="mb-0"><i class="fas fa-search me-2"></i> نتائج البحث عن: "<?php echo htmlspecialchars($query); ?>"</h4>
                </div>
                <div class="card-body p-4">
                    <?php
                    if (!empty($query)) {
                        $sql = "SELECT id, username, profile_picture FROM users WHERE (username LIKE '%$query%') AND id != $my_id LIMIT 50";
                        $result = mysqli_query($con, $sql);

                        if (mysqli_num_rows($result) > 0) {
                            while ($user = mysqli_fetch_assoc($result)) {
                                $uid = $user['id'];
                                $pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profile.jpg';
                                
                                // Check friendship status
                                $status = 'none';
                                $friendship_id = 0;
                                if ($isLoggedIn) {
                                    $check = mysqli_query($con, "SELECT id, status, sender_id FROM friends WHERE (sender_id = $my_id AND receiver_id = $uid) OR (sender_id = $uid AND receiver_id = $my_id)");
                                    if ($row = mysqli_fetch_assoc($check)) {
                                        $friendship_id = $row['id'];
                                        if ($row['status'] == 'accepted') {
                                            $status = 'friend';
                                        } elseif ($row['sender_id'] == $my_id) {
                                            $status = 'sent';
                                        } else {
                                            $status = 'received';
                                        }
                                    }
                                }
                                ?>
                                <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo $pic; ?>" class="rounded-circle border" style="width: 60px; height: 60px; object-fit: cover;" onerror="this.src='uploads/profile.jpg'">
                                        <div>
                                            <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['username']); ?></h5>
                                            <a href="profile.php?user_id=<?php echo $uid; ?>" class="text-decoration-none small text-primary">عرض الملف الشخصي</a>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($isLoggedIn): ?>
                                            <div id="status-btn-<?php echo $uid; ?>">
                                                <?php if ($status == 'none'): ?>
                                                    <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="friendAction(<?php echo $uid; ?>, 'add')">
                                                        <i class="fas fa-user-plus me-1"></i> إضافة صديق
                                                    </button>
                                                <?php elseif ($status == 'sent'): ?>
                                                    <button class="btn btn-secondary btn-sm rounded-pill px-3" disabled title="طلب الصداقة قيد الانتظار">
                                                        <i class="fas fa-clock me-1"></i> طلب مرسل
                                                    </button>
                                                <?php elseif ($status == 'received'): ?>
                                                    <button class="btn btn-success btn-sm rounded-pill px-3" onclick="friendAction(<?php echo $uid; ?>, 'accept')">
                                                        <i class="fas fa-check me-1"></i> قبول الصداقة
                                                    </button>
                                                <?php elseif ($status == 'friend'): ?>
                                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3" disabled>
                                                        <i class="fas fa-user-friends me-1"></i> أنتم أصدقاء
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="text-center py-5 text-muted"><i class="fas fa-user-slash fa-3x mb-3"></i><p>لا يوجد مستخدمين بهذا الاسم</p></div>';
                        }
                    } else {
                        echo '<div class="text-center py-5 text-muted"><p>يرجى إدخال اسم للبحث</p></div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function friendAction(userId, action) {
    const btnContainer = document.getElementById('status-btn-' + userId);
    const originalHtml = btnContainer.innerHTML;
    btnContainer.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div>';
    
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('action', action);
    
    fetch('friend_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (action === 'add') {
                btnContainer.innerHTML = '<button class="btn btn-secondary btn-sm rounded-pill px-3" disabled><i class="fas fa-clock me-1"></i> طلب مرسل</button>';
            } else if (action === 'accept') {
                btnContainer.innerHTML = '<button class="btn btn-outline-primary btn-sm rounded-pill px-3" disabled><i class="fas fa-user-friends me-1"></i> أنتم أصدقاء</button>';
            }
        } else {
            btnContainer.innerHTML = originalHtml;
            alert(data.error || 'حدث خطأ ما');
        }
    })
    .catch(err => {
        btnContainer.innerHTML = originalHtml;
        alert('فشل في الاتصال بالخادم');
    });
}
</script>

<?php include("includes/footer.php"); ?>
