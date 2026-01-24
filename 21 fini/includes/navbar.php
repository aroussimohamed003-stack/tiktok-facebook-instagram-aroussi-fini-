<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Notification Styles
echo '<style>
.notification-dropdown {
    position: fixed;
    top: 60px;
    right: 10px; /* Default for mobile */
    width: 300px;
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #ccc);
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    display: none;
    z-index: 10000;
    max-height: 400px;
    overflow-y: auto;
}
@media (min-width: 992px) {
    .notification-dropdown {
        right: 80px; /* Adjust for desktop */
    }
}
.notification-dropdown.active {
    display: block;
}
.notif-item {
    position: relative;
    padding: 10px;
    border-bottom: 1px solid var(--border-color, #eee);
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-color, #333);
    text-decoration: none;
    transition: background 0.2s;
}
.notif-item:hover {
    background: rgba(0,0,0,0.05);
    color: var(--text-color, #333);
}
.notif-item.unread {
    background: rgba(103, 61, 230, 0.1);
}
.notif-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.notif-content {
    font-size: 13px;
    flex: 1;
    padding-right: 20px;
}
.notif-time {
    font-size: 11px;
    color: #888;
    margin-top: 2px;
}
.delete-notif {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    color: #999;
    cursor: pointer;
    font-size: 14px;
    padding: 5px;
    z-index: 10;
}
.delete-notif:hover {
    color: #ff0050;
}
.badge-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff0050;
    color: white;
    border-radius: 50%;
    font-size: 10px;
    padding: 2px 5px;
    display: none;
}
.search-container {
    position: relative;
    flex: 1;
    max-width: 400px;
    margin: 0 15px;
}
@media (max-width: 991px) {
    .search-container {
        display: none;
    }
}
.search-input {
    width: 100%;
    padding: 8px 15px 8px 40px;
    border-radius: 50px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.1);
    color: white;
    outline: none;
    transition: all 0.3s;
}
.search-input:focus {
    background: rgba(255,255,255,0.2);
    border-color: #FE2C55;
}
.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #bbb;
}
</style>';
?>

<nav class="navbar navbar-expand-lg fixed-top" style="z-index: 10000; background-color: var(--nav-bg, #fff);">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="indexmo.php" style="gap: 10px;">
      <img src="images/kk-01.png" alt="Logo" class="img-fluid" style="max-height: 50px; width: auto;">
      <span style="font-weight: bold; font-size: 1.25rem;">Aroussi</span>
    </a>

    <!-- Desktop Search -->
   <!--- <div class="search-container d-none d-lg-block">
        <form action="search_users.php" method="GET">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="query" class="search-input" placeholder="Search friends...">
        </form>
    </div>
--->


    <div class="d-flex align-items-center ms-auto order-lg-last">
        <?php if ($isLoggedIn): ?>
            <!-- Single Notification Icon -->
            <a href="javascript:void(0)" class="nav-link position-relative me-3 text-reset" onclick="toggleNotifications(event)" style="z-index: 10001;">
                <i class="fas fa-bell fa-lg"></i>
                <span class="badge-count" id="notif-badge">0</span>
            </a>
            <!-- Desktop Profile -->
            <a href="profile.php" class="btn btn-sm btn-outline-primary me-2 d-none d-lg-inline-flex align-items-center">
              <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
            </a>
        <?php else: ?>
        <!--     <div class="d-none d-lg-flex">
                <a href="login.php" class="btn btn-sm btn-outline-primary me-2">تسجيل الدخول</a>
                <a href="register.php" class="btn btn-sm btn-primary">إنشاء حساب</a>
             </div>--->
        <?php endif; ?>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation" style="color: #000 !important; background: transparent; font-size: 28px; padding: 0;">
          <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Notification Dropdown -->
    <div id="notification-list" class="notification-dropdown">
        <div style="padding: 10px; font-weight: bold; border-bottom: 1px solid var(--border-color); background: var(--card-bg); sticky: top; top: 0; z-index: 2;">
            Notifications <small style="float: right; cursor: pointer; color: var(--primary-color);" onclick="markAllRead()">Mark all read</small>
        </div>
        <div id="notif-items">
            <div class="text-center p-3 text-muted">Loading...</div>
        </div>
    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel" style="z-index: 10010;">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">القائمة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
          <li class="nav-item">
            <a class="nav-link" href="indexmo.php"><i class="fas fa-home"></i> الصفحة الرئيسية</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="meet.php?room=LiveChat"><i class="fas fa-video"></i> بث مباشر</a>
          </li>
          <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link" href="mo.php"><i class="fas fa-cloud-upload-alt"></i> المنشورات</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="profile.php"><i class="fas fa-user-circle"></i> الملف الشخصي</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="message.php"><i class="fas fa-envelope"></i> الرسائل</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="marketplace.php" style="color: #FE2C55; font-weight:bold;"><i class="fas fa-store"></i> المتجر</a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="about.php"><i class="fas fa-info-circle"></i> عن الموقع</a>
          </li>
          <?php if ($isLoggedIn): ?>
          <li class="nav-item border-top mt-2 pt-2">
            <a class="nav-link text-danger" href="indexmo.php?logout=true"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</nav>



<!-- Bottom Mobile Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
  <div class="mobile-bottom-nav-icons">
    <a href="indexmo.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'indexmo.php' ? 'active' : ''; ?>">
      <i class="fas fa-home"></i>
      <span>الرئيسية</span>
    </a>
    <a href="meet.php?room=LiveChat" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'meet.php' ? 'active' : ''; ?>">
      <i class="fas fa-video"></i>
      <span>بث مباشر</span>
    </a>
    <?php if ($isLoggedIn): ?>
    <a href="uplod-profile.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'uplod-profile.php' ? 'active' : ''; ?>">
      <i class="fas fa-plus-circle" style="color: #FE2C55; font-size: 30px;"></i>
      <span>رفع</span>
    </a>
    <a href="marketplace.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace.php' ? 'active' : ''; ?>">
      <i class="fas fa-store"></i>
      <span>متجر</span>
    </a>
    <a href="profile.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
      <i class="fas fa-user"></i>
      <span>حسابي</span>
    </a>
    <?php else: ?>
    <a href="login.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">
      <i class="fas fa-sign-in-alt"></i>
      <span>دخول</span>
    </a>
    <a href="register.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">
      <i class="fas fa-user-plus"></i>
      <span>تسجيل</span>
    </a>
    <?php endif; ?>
  </div>
</div>

<?php
// Get the base URL for the site to ensure assets load correctly from any subfolder
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$baseDir = dirname($scriptName);
if ($baseDir === '\\' || $baseDir === '/') $baseDir = '';
$baseUrl = $protocol . "://" . $host . $baseDir;
?>
<audio id="notification-sound" src="<?php echo $baseUrl; ?>/notification.mp3" preload="auto"></audio>


<script>
let lastUnseenCount = 0;

function showBrowserNotification(title, body, icon, url) {
    if (!("Notification" in window)) return;
    
    if (Notification.permission === "granted") {
        const notification = new Notification(title, {
            body: body,
            icon: icon || 'images/kk-01.png'
        });
        notification.onclick = function() {
            window.focus();
            if (url) window.location.href = url;
            this.close();
        };
    } else if (Notification.permission !== "denied") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                showBrowserNotification(title, body, icon, url);
            }
        });
    }
}

function playNotificationSound() {
    const sound = document.getElementById('notification-sound');
    if (sound) {
        // Force reload to ensure it's ready
        sound.load(); 
        const playPromise = sound.play();
        if (playPromise !== undefined) {
            playPromise.then(_ => {
                console.log("Sound played successfully");
            }).catch(e => {
                console.warn("Auto-play blocked. Will play on next click.");
                const playOnInteract = () => {
                    sound.play().catch(err => console.error("Delayed play failed:", err));
                    document.removeEventListener('click', playOnInteract);
                    document.removeEventListener('touchstart', playOnInteract);
                };
                document.addEventListener('click', playOnInteract);
                document.addEventListener('touchstart', playOnInteract);
            });
        }
    }
}

// Push Subscription Logic
async function subscribeToPush() {
    if ('serviceWorker' in navigator) {
        const registration = await navigator.serviceWorker.ready;
        try {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: 'BPaY8Xq8GqW6vJz7i7X-N_9Gq_Kk8P4z8_n_G_z_l_H_P_l_I_G_M_A_R_O_U_S_S_I' // Placeholder VAPID key
            });
            console.log('Push Subscribed:', subscription);
            
            // Save subscription to server
            await fetch('save_subscription.php', {
                method: 'POST',
                body: JSON.stringify(subscription),
                headers: { 'Content-Type': 'application/json' }
            });
        } catch (error) {
            console.error('Push Registration Failed:', error);
        }
    }
}

// Request notification permissions and subscribe
if ("Notification" in window) {
    if (Notification.permission === "default") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") subscribeToPush();
        });
    } else if (Notification.permission === "granted") {
        subscribeToPush();
    }
}

function toggleNotifications(e) {
    if(e) { e.stopPropagation(); e.preventDefault(); }
    var dropdown = document.getElementById('notification-list');
    dropdown.classList.toggle('active');
    if (dropdown.classList.contains('active')) fetchNotifications();
}

document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('notification-list');
    if (dropdown && dropdown.classList.contains('active')) {
         if (!dropdown.contains(e.target) && !e.target.closest('.fa-bell')) {
             dropdown.classList.remove('active');
         }
    }
});

function deleteNotification(e, id) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    const fd = new FormData();
    fd.append('delete_id', id);
    fetch('get_notifications.php', { method: 'POST', body: fd }).then(() => fetchNotifications());
}

function markAllRead() {
    const fd = new FormData();
    fd.append('mark_read', 'true');
    fetch('get_notifications.php', { method: 'POST', body: fd }).then(() => {
        document.querySelectorAll('.badge-count').forEach(b => b.style.display = 'none');
        document.querySelectorAll('.notif-item').forEach(i => i.classList.remove('unread'));
    });
}

function fetchNotifications() {
    fetch('get_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (data.error) return;
            var badge = document.getElementById('notif-badge');
            if (badge) {
                if (data.unseen_count > 0) {
                    // Check if we have new notifications
                    if (data.unseen_count > lastUnseenCount) {
                        playNotificationSound();
                        
                        // Show browser notification for the latest one
                        if (data.notifications && data.notifications.length > 0) {
                            const latest = data.notifications[0];
                            if (latest.is_read == 0) {
                                let link = 'indexmo.php';
                                if(latest.type == 'message') link = 'message.php';
                                else if(latest.type == 'like' || latest.type == 'comment') {
                                    if(latest.post_id) link = 'mo.php#post-' + latest.post_id;
                                    else if(latest.video_id) link = 'indexmo.php?video_id=' + latest.video_id;
                                }
                                showBrowserNotification(latest.username, latest.message, latest.profile_picture, link);
                            }
                        }
                    }
                    lastUnseenCount = data.unseen_count;
                    badge.textContent = data.unseen_count;
                    badge.style.display = 'block';
                } else {
                    lastUnseenCount = 0;
                    badge.style.display = 'none';
                }
            }
            var html = '';
            if (!data.notifications || data.notifications.length === 0) {
                html = '<div class="text-center p-3 text-muted">لا توجد إشعارات</div>';
            } else {
                data.notifications.forEach(n => {
                    var cls = n.is_read == 0 ? 'unread' : '';
                    var link = '#';
                    if(n.type == 'message') link = 'message.php';
                    else if(n.type == 'like' || n.type == 'comment') {
                        if(n.post_id) link = 'mo.php#post-' + n.post_id;
                        else if(n.video_id) link = 'indexmo.php?video_id=' + n.video_id;
                    } else if (n.type == 'friend_request' || n.type == 'friend_accepted') {
                        link = 'profile.php?user_id=' + n.sender_id;
                    }
                    var actionBtn = (n.type == 'friend_request' && n.is_read == 0) ? 
                        `<button class="btn btn-sm btn-primary mt-1 py-0 px-3 rounded-pill" onclick="acceptFriendAction(event, ${n.sender_id}, this)" style="font-size: 11px;">قبول</button>` : '';

                    html += `
                    <div style="position:relative;">
                        <a href="${link}" class="notif-item ${cls}">
                            <img src="${n.profile_picture || 'uploads/profile.jpg'}" class="notif-avatar" onerror="this.src='uploads/profile.jpg'">
                            <div class="notif-content">
                                <strong>${n.username}</strong> ${n.message}
                                ${actionBtn}
                                <div class="notif-time">${n.time_ago}</div>
                            </div>
                        </a>
                        <span class="delete-notif" onclick="deleteNotification(event, ${n.id})"><i class="fas fa-times"></i></span>
                    </div>`;
                });
            }
            document.getElementById('notif-items').innerHTML = html;
        }).catch(err => console.error(err));
}

function acceptFriendAction(e, userId, button) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    button.disabled = true; button.innerHTML = '...';
    const fd = new FormData();
    fd.append('user_id', userId);
    fd.append('action', 'accept');
    fetch('friend_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                button.className = "btn btn-sm btn-success mt-1 py-0 px-3 rounded-pill";
                button.innerHTML = "تم القبول";
            } else {
                button.disabled = false; button.innerHTML = "خطأ";
                alert(data.error || 'حدث خطأ');
            }
        });
}

document.addEventListener('DOMContentLoaded', fetchNotifications);
setInterval(fetchNotifications, 3000); // Check every 3 seconds instead of 10
</script>
