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
    position: relative; /* For absolute positioning of delete button */
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
.notif-item:hover .delete-notif {
    display: block;
}
.notif-item.unread {
    background: rgba(0, 242, 234, 0.1);
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
    padding-right: 20px; /* Space for delete button */
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
    display: block; /* Always visible */
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
</style>';
?>

<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="indexmo.php" style="gap: 10px;">
      <img src="images/kk-01.png" alt="Logo" class="img-fluid" style="max-height: 50px; width: auto;">
      <span style="font-weight: bold; font-size: 1.25rem;">Aroussi</span>
    </a>

    <div class="d-flex align-items-center ms-auto order-lg-last">
        <?php if ($isLoggedIn): ?>
            <!-- Single Notification Icon for ALL screens -->
            <a href="javascript:void(0)" class="nav-link position-relative me-3 text-reset" onclick="toggleNotifications(event)">
                <i class="fas fa-bell fa-lg"></i>
                <span class="badge-count" id="notif-badge">0</span>
            </a>

            <!-- User Profile (Desktop Only Text, Icon for Mobile is in collapse or bottom nav) -->
            <a href="profile.php" class="btn btn-sm btn-outline-primary me-2 d-none d-lg-inline-flex align-items-center">
              <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
            </a>
        <?php else: ?>
             <div class="d-none d-lg-flex">
                <a href="login.php" class="btn btn-sm btn-outline-primary me-2">تسجيل الدخول</a>
                <a href="register.php" class="btn btn-sm btn-primary">إنشاء حساب</a>
             </div>
        <?php endif; ?>

        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
          <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Notification Dropdown -->
    <div id="notification-list" class="notification-dropdown">
        <div style="padding: 10px; font-weight: bold; border-bottom: 1px solid var(--border-color); background: var(--card-bg);">
            Notifications <small style="float: right; cursor: pointer; color: var(--primary-color);" onclick="markAllRead()">Mark all read</small>
        </div>
        <div id="notif-items">
            <div class="text-center p-3 text-muted">Loading...</div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">القائمة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
          <li class="nav-item">
            <a class="nav-link" href="indexmo.php"><i class="fas fa-home"></i> page accel</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="meet.php?room=LiveChat"><i class="fas fa-video"></i> ChatLive</a>
          </li>
          <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link" href="mo.php"><i class="fas fa-cloud-upload-alt"></i> postes </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="profile.php"><i class="fas fa-user-circle"></i>  profile</a>
          </li>
          <!---<li class="nav-item">
            <a class="nav-link" href="coment.php"><i class="fas fa-comments"></i> comnter</a>
          </li>--->
          <li class="nav-item">
            <a class="nav-link" href="message.php"><i class="fas fa-envelope"></i> message</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="marketplace.php" style="color: #FE2C55; font-weight:bold;"><i class="fas fa-store"></i> Marketplace</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="about.php"><i class="fas fa-info-circle"></i> عن الموقع</a>
          </li>
            <?php endif; ?>
          <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link text-danger" href="indexmo.php?logout=true"><i class="fas fa-sign-out-alt"></i> loug out </a>
          </li>
          <?php endif; ?>
          
        </ul>

        <!-- Theme toggle switch -->
        <div class="theme-switch-wrapper mt-3">
          <span class="slider-icon"><i class="fas fa-sun"></i></span>
          <label class="theme-switch">
            <input type="checkbox">
            <span class="slider round"></span>
          </label>
          <span class="slider-icon ms-2"><i class="fas fa-moon"></i></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Spacer -->
<div style="height: 70px;"></div>

<!-- Bottom Mobile Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
  <div class="mobile-bottom-nav-icons">
    <a href="indexmo.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'indexmo.php' ? 'active' : ''; ?>">
      <i class="fas fa-home"></i>
      <span>الرئيسية</span>
    </a>

    <a href="meet.php?room=LiveChat" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'meet.php' ? 'active' : ''; ?>">
      <i class="fas fa-video"></i>
      <span>ChatLive</span>
    </a>
    <?php if ($isLoggedIn): ?>    <a href="uplod-profile.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'uplod-profile.php' ? 'active' : ''; ?>">

      <i class="fas fa-plus-circle"></i>
      <span>رفع</span>
    </a>
    <a href="marketplace.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace.php' ? 'active' : ''; ?>">
      <i class="fas fa-store"></i>
      <span>متجر</span>
    </a>
    <?php else: ?>
    <a href="login.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">
      <i class="fas fa-sign-in-alt"></i>
      <span>دخول</span>
    </a>
    <?php endif; ?>
    <!---<a href="coment.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'coment.php' ? 'active' : ''; ?>">
      <i class="fas fa-comments"></i>
      <span>تعليقات</span>
    </a>--->
    <?php if ($isLoggedIn): ?>
    <a href="profile.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
      <i class="fas fa-user"></i>
      <span>حسابي</span>
    </a>
    <?php else: ?>
    <a href="register.php" class="mobile-bottom-nav-icon <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">
      <i class="fas fa-user-plus"></i>
      <span>تسجيل</span>
    </a>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleNotifications(e) {
    if(e) {
        e.stopPropagation();
        e.preventDefault();
    }
    var dropdown = document.getElementById('notification-list');
    var isActive = dropdown.classList.contains('active');
    
    // Close others
    document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('show'));

    if (isActive) {
        dropdown.classList.remove('active');
    } else {
        dropdown.classList.add('active');
        fetchNotifications();
    }
}

// Close when clicking outside
document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('notification-list');
    var bell = document.querySelector('.fa-bell');
    var bellLink = bell ? bell.closest('a') : null;

    // If click is not inside dropdown and not on the bell
    if (dropdown && dropdown.classList.contains('active')) {
         if (!dropdown.contains(e.target) && (!bellLink || !bellLink.contains(e.target))) {
             dropdown.classList.remove('active');
         }
    }
});

function deleteNotification(e, id) {
    if(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    $.post('get_notifications.php', { delete_id: id }, function(res) {
        fetchNotifications();
    });
}

function markAllRead() {
    $.post('get_notifications.php', { mark_read: true }, function() {
        var badges = document.querySelectorAll('.badge-count');
        badges.forEach(b => { b.style.display = 'none'; });
        // Optionally reload list or mark items visibly read
        document.querySelectorAll('.notif-item').forEach(i => i.classList.remove('unread'));
    });
}

function fetchNotifications() {
    $.getJSON('get_notifications.php')
        .done(function(data) {
            if (data.error) {
                 console.error('Notification error:', data.error);
                 return; 
            }
            
            var count = data.unseen_count;
            var badge = document.getElementById('notif-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }

            var html = '';
            if (data.notifications.length === 0) {
                html = '<div class="text-center p-3 text-muted">No notifications</div>';
            } else {
                data.notifications.forEach(n => {
                    var cls = n.is_read == 0 ? 'unread' : '';
                    
                    // Determine Link
                    var link = '#';
                    if(n.type == 'message') link = 'message.php';
                    else if(n.type == 'like' || n.type == 'comment') {
                        if(n.post_id) link = 'mo.php#post-' + n.post_id;
                        else if(n.video_id) link = 'indexmo.php?video_id=' + n.video_id;
                    }

                    html += `
                    <div class="notif-item-wrapper" style="position:relative;">
                        <a href="${link}" class="notif-item ${cls}">
                            <img src="${n.profile_picture || 'uploads/profile.jpg'}" class="notif-avatar" onerror="this.src='uploads/profile.jpg'">
                            <div class="notif-content">
                                <strong>${n.username}</strong> ${n.message}
                                <div class="notif-time">${n.time_ago}</div>
                            </div>
                        </a>
                        <span class="delete-notif" onclick="deleteNotification(event, ${n.id})"><i class="fas fa-times"></i></span>
                    </div>`;
                });
            }
            document.getElementById('notif-items').innerHTML = html;
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Fetch failed:', textStatus, errorThrown, jqXHR.responseText);
            var errorMsg = 'Failed to load';
            if(jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            } else if (jqXHR.responseText) {
                 // Try to show a bit of the response text if it's short
                 errorMsg += ': ' + jqXHR.responseText.substring(0, 50);
            }
            document.getElementById('notif-items').innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i> ' + errorMsg + '</div>';
        });
}

// Initial fetch
setInterval(fetchNotifications, 10000);
document.addEventListener('DOMContentLoaded', fetchNotifications);
</script>
