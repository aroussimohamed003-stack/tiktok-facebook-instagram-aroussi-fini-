<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
$profilePic = 'uploads/profile.jpg'; // Default fallback

if ($isLoggedIn) {
    $uid = $_SESSION['user_id'];
    $u_res = mysqli_query($con, "SELECT profile_picture FROM users WHERE id = $uid");
    if ($u_res && $u_row = mysqli_fetch_assoc($u_res)) {
        if (!empty($u_row['profile_picture'])) {
            $profilePic = $u_row['profile_picture'];
        }
    }
}
?>

<!-- BEGIN: MainHeader -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur-xl border-b border-slate-200/60 px-4 md:px-8 py-2.5 flex items-center justify-between">
    <!-- Logo & Desktop Nav -->
    <div class="flex items-center gap-8">
        <a href="indexmo.php" class="flex items-center gap-2 group">
            <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-blue-100 transition-transform group-hover:scale-105 active:scale-95">A</div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 hidden sm:block font-outfit">Aoussi</h1>
        </a>

        <!-- Desktop Navigation Links -->
        <nav class="hidden lg:flex items-center gap-1">
            <a href="indexmo.php" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'indexmo.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'; ?>">
                Home
            </a>
            <a href="mo.php" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'mo.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'; ?>">
                Feeds
            </a>
            <a href="public.php" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'public.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'; ?>">
                Explore
            </a>
            <a href="marketplace.php" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'; ?>">
                Market
            </a>
            <a href="message.php" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'message.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'; ?>">
                Messages
            </a>
        </nav>
    </div>

    <!-- Search Bar -->
    <div class="flex-1 max-w-md mx-4 hidden md:block">
        <div class="relative group">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
                </svg>
            </span>
            <form action="search_users.php" method="GET" class="w-full">
                <input name="query" class="w-full bg-slate-100/50 border-none ring-1 ring-slate-200/60 rounded-2xl py-2 pl-11 pr-4 text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all outline-none placeholder:text-slate-400 font-medium font-outfit" placeholder="Search for anything..." type="text"/>
            </form>
        </div>
    </div>

    <!-- Right Icons -->
    <div class="flex items-center gap-1 md:gap-3">
        <!-- New Search Button for Mobile (Hidden on Desktop) -->
        <button onclick="toggleMobileSearch()" class="md:hidden p-2.5 rounded-2xl hover:bg-slate-100 text-slate-600 transition-all active:scale-95 group" title="Search Friends">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
            </svg>
        </button>

        <!-- New Message Button for Mobile -->
        <a href="message.php" class="md:hidden p-2.5 rounded-2xl hover:bg-slate-100 text-slate-600 transition-all active:scale-95 group" title="Messages">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
            </svg>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="uplod-profile.php" class="p-2.5 rounded-2xl hover:bg-slate-100 text-slate-600 transition-all active:scale-95 group" title="Create Video">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4v16m8-8H4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
                </svg>
            </a>
            
            <div class="relative">
                <button id="notif-trigger" onclick="toggleNotifications(event)" class="p-2.5 rounded-2xl hover:bg-slate-100 text-slate-600 transition-colors relative" title="Notifications">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                    </svg>
                    <span id="notif-badge" class="absolute top-2 right-2 w-4 h-4 bg-red-500 border-2 border-white rounded-full text-[8px] flex items-center justify-center text-white font-bold hidden">0</span>
                </button>
                
                <!-- Notification Dropdown -->
                <div id="notification-list" class="absolute right-0 mt-3 w-80 bg-white rounded-3xl shadow-2xl border border-slate-100 hidden overflow-hidden z-[60]">
                    <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                        <h3 class="font-bold text-slate-900 text-sm">Notifications</h3>
                        <button onclick="markAllRead()" class="text-[11px] font-bold text-blue-600 hover:text-blue-700">Mark all read</button>
                    </div>
                    <div id="notif-items" class="max-h-[400px] overflow-y-auto no-scrollbar">
                        <div class="p-8 text-center">
                            <i class="fas fa-circle-notch fa-spin text-slate-300 text-xl mb-2"></i>
                            <p class="text-xs text-slate-400 font-medium">Loading...</p>
                        </div>
                    </div>
                    <a href="notification.php" class="block p-4 text-center text-xs font-bold text-slate-500 border-t border-slate-50 hover:bg-slate-50 transition-colors">See all activity</a>
                </div>
            </div>

            <div class="relative">
                <button onclick="toggleProfileDropdown(event)" class="flex items-center gap-2.5 p-1 pr-3 rounded-2xl hover:bg-slate-100 transition-all border border-transparent hover:border-slate-200 group">
                    <div class="w-9 h-9 rounded-full overflow-hidden border-2 border-white shadow-sm ring-1 ring-slate-100">
                        <img alt="User Avatar" class="w-full h-full object-cover" src="<?php echo $profilePic; ?>" onerror="this.src='uploads/profile.jpg'"/>
                    </div>
                    <span class="text-sm font-bold text-slate-700 hidden lg:block"><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-chevron-down text-[10px] text-slate-400 group-hover:text-slate-600 transition-colors"></i>
                </button>
                
                <!-- Profile Dropdown -->
                <div id="profile-dropdown" class="absolute right-0 mt-3 w-48 bg-white rounded-2xl shadow-2xl border border-slate-100 hidden overflow-hidden z-[60]">
                    <div class="p-2">
                        <a href="profile.php" class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-xl transition-colors">
                            <i class="fas fa-user text-blue-500 w-5"></i>
                            الملف الشخصي
                        </a>
                        <a href="?logout=true" class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-500 hover:bg-red-50 rounded-xl transition-colors" onclick="return confirm('هل تريد تسجيل الخروج؟')">
                            <i class="fas fa-sign-out-alt w-5"></i>
                            تسجيل الخروج
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2">
                <a href="login.php" class="text-sm font-bold text-slate-500 hover:text-slate-900 px-5 py-2.5 transition-colors">Login</a>
                <a href="register.php" class="text-sm font-bold bg-blue-600 text-white px-6 py-2.5 rounded-2xl hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all active:scale-95">Join</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- New Mobile Search Overlay (Hidden by Default) -->
<div id="mobile-search-overlay" class="fixed inset-0 bg-white z-[100] hidden flex flex-col p-6 overflow-y-auto animate-fadeInUp">
    <div class="flex items-center gap-4 mb-8">
        <button onclick="toggleMobileSearch()" class="w-10 h-10 flex items-center justify-center bg-slate-100 rounded-xl text-slate-600 hover:bg-slate-200 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">بحث عن أصدقاء</h2>
    </div>
    
    <form action="search_users.php" method="GET" class="relative group">
        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
            <i class="fas fa-search"></i>
        </span>
        <input name="query" autofocus class="w-full bg-slate-50 border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 rounded-2xl py-4 pl-12 pr-4 text-sm font-medium outline-none transition-all placeholder:text-slate-400" placeholder="ابحث بالإسم أو اسم المستخدم..." type="text"/>
    </form>

    <div class="mt-8">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">اقتراحات البحث</h3>
        <div class="flex flex-wrap gap-2">
            <a href="search_users.php?query=a" class="px-4 py-2 bg-slate-100 rounded-full text-xs font-bold text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-colors transition-transform active:scale-95">#الأكثر_رواجاً</a>
            <a href="search_users.php?query=m" class="px-4 py-2 bg-slate-100 rounded-full text-xs font-bold text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-colors transition-transform active:scale-95">#أصدقاء_جدد</a>
            <a href="search_users.php?query=s" class="px-4 py-2 bg-slate-100 rounded-full text-xs font-bold text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-colors transition-transform active:scale-95">#مشاهير</a>
        </div>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="fixed bottom-0 left-0 right-0 bg-white/80 backdrop-blur-xl border-t border-slate-200 p-1.5 flex items-center justify-around lg:hidden z-50">
    <a href="indexmo.php" class="flex flex-col items-center gap-0.5 p-1.5 <?php echo basename($_SERVER['PHP_SELF']) == 'indexmo.php' ? 'text-blue-600' : 'text-slate-400'; ?>">
        <i class="fas fa-home text-lg"></i>
        <span class="text-[9px] font-bold">Home</span>
    </a>
    <a href="mo.php" class="flex flex-col items-center gap-0.5 p-1.5 <?php echo basename($_SERVER['PHP_SELF']) == 'mo.php' ? 'text-blue-600' : 'text-slate-400'; ?>">
        <i class="fas fa-newspaper text-lg"></i>
        <span class="text-[9px] font-bold">Feeds</span>
    </a>
    <a href="uplod-profile.php" class="flex flex-col items-center justify-center -mt-8 w-12 h-12 bg-blue-600 rounded-full text-white shadow-lg shadow-blue-200">
        <i class="fas fa-plus text-lg"></i>
    </a>
    <a href="public.php" class="flex flex-col items-center gap-0.5 p-1.5 <?php echo basename($_SERVER['PHP_SELF']) == 'public.php' ? 'text-blue-600' : 'text-slate-400'; ?>">
        <i class="fas fa-search text-lg"></i>
        <span class="text-[9px] font-bold">Explore</span>
    </a>
    <a href="marketplace.php" class="flex flex-col items-center gap-0.5 p-1.5 <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace.php' ? 'text-blue-600' : 'text-slate-400'; ?>">
        <i class="fas fa-store text-lg"></i>
        <span class="text-[9px] font-bold">Market</span>
    </a>
    <a href="message.php" class="flex flex-col items-center gap-0.5 p-1.5 <?php echo basename($_SERVER['PHP_SELF']) == 'message.php' ? 'text-blue-600' : 'text-slate-400'; ?>">
        <i class="fas fa-comment text-lg"></i>
        <span class="text-[9px] font-bold">Messages</span>
    </a>
    <a href="profile.php" class="flex flex-col items-center gap-0.5 p-1.5 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-blue-600' : 'text-slate-400'; ?>">
        <i class="fas fa-user text-lg"></i>
        <span class="text-[9px] font-bold">Profile</span>
    </a>
</nav>
<!-- END: MainHeader -->

<style>
    .notif-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #f8fafc;
        transition: all 0.2s;
        text-decoration: none;
        position: relative;
    }
    .notif-item:hover {
        background-color: #f1f5f9;
    }
    .notif-item.unread {
        background-color: #f0f7ff;
    }
    .notif-item.unread::after {
        content: '';
        position: absolute;
        left: 4px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 4px;
        background-color: #3b82f6;
        border-radius: 50%;
    }
    .notif-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .notif-text {
        font-size: 13px;
        color: #334155;
        line-height: 1.4;
    }
    .notif-text strong {
        color: #0f172a;
    }
    .notif-time {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px;
    }
    .notif-delete {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #64748b;
        opacity: 0;
        transition: all 0.2s;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        z-index: 20;
    }
    .group\/nitem:hover .notif-delete {
        opacity: 1;
    }
    .notif-delete:hover {
        background: #fee2e2;
        color: #ef4444;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInUp {
        animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    @media (max-width: 640px) {
        #notification-list {
            position: fixed !important;
            left: 12px !important;
            right: 12px !important;
            width: auto !important;
            top: 70px !important;
            z-index: 100 !important;
            max-height: 80vh !important;
            border-radius: 24px !important;
        }
        #profile-dropdown {
            position: fixed !important;
            left: 12px !important;
            right: 12px !important;
            width: auto !important;
            top: 70px !important;
            z-index: 100 !important;
            border-radius: 20px !important;
        }
        .notif-text {
            font-size: 12px;
        }
    }
</style>

<script>
let lastUnseenCount = -1;
let audioUnlocked = false;

function unlockAudio() {
    if (audioUnlocked) return;
    const sound = document.getElementById('notification-sound');
    if (sound) {
        sound.play().then(() => {
            sound.pause();
            sound.currentTime = 0;
            audioUnlocked = true;
            document.removeEventListener('click', unlockAudio);
            document.removeEventListener('touchstart', unlockAudio);
        }).catch(e => console.log("Audio unlock failed:", e));
    }
}
document.addEventListener('click', unlockAudio);
document.addEventListener('touchstart', unlockAudio);

function toggleMobileSearch() {
    const overlay = document.getElementById('mobile-search-overlay');
    overlay.classList.toggle('hidden');
    if (!overlay.classList.contains('hidden')) {
        overlay.querySelector('input').focus();
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function toggleProfileDropdown(e) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    const dropdown = document.getElementById('profile-dropdown');
    dropdown.classList.toggle('hidden');
    // Close notifications if open
    document.getElementById('notification-list').classList.add('hidden');
}

function toggleNotifications(e) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    const dropdown = document.getElementById('notification-list');
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
        fetchNotifications();
        // Close profile dropdown if open
        document.getElementById('profile-dropdown').classList.add('hidden');
    }
}

document.addEventListener('click', function(e) {
    const pDropdown = document.getElementById('profile-dropdown');
    const nDropdown = document.getElementById('notification-list');
    
    if (pDropdown && !pDropdown.classList.contains('hidden') && !pDropdown.contains(e.target) && !e.target.closest('button[onclick*="toggleProfileDropdown"]')) {
        pDropdown.classList.add('hidden');
    }
    
    if (nDropdown && !nDropdown.classList.contains('hidden')) {
         if (!nDropdown.contains(e.target) && !e.target.closest('#notif-trigger')) {
             nDropdown.classList.add('hidden');
         }
    }
});

function markAllRead() {
    const fd = new FormData();
    fd.append('mark_read', 'true');
    fetch('get_notifications.php', { method: 'POST', body: fd }).then(() => {
        document.getElementById('notif-badge').classList.add('hidden');
        document.querySelectorAll('.notif-item').forEach(i => i.classList.remove('unread'));
    });
}

function fetchNotifications() {
    fetch('get_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (data.error) return;
            const badge = document.getElementById('notif-badge');
            if (badge) {
                if (data.unseen_count > 0) {
                    if (lastUnseenCount !== -1 && data.unseen_count > lastUnseenCount) {
                        playNotificationSound();
                        if (data.notifications && data.notifications.length > 0) {
                            const latest = data.notifications[0];
                            showBrowserNotification(latest.username, latest.message, latest.profile_picture);
                        }
                    }
                    badge.textContent = data.unseen_count > 9 ? '9+' : data.unseen_count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
                lastUnseenCount = data.unseen_count;
            }
            
            let html = '';
            if (!data.notifications || data.notifications.length === 0) {
                html = '<div class="p-12 text-center text-slate-400 text-xs">No notifications yet</div>';
            } else {
                data.notifications.forEach(n => {
                    const unreadClass = n.is_read == 0 ? 'unread' : '';
                    let link = '#';
                    if(n.type == 'message') link = 'message.php';
                    else if(n.type == 'like' || n.type == 'comment') {
                        if(n.post_id) link = 'mo.php#post-' + n.post_id;
                        else if(n.video_id) link = 'indexmo.php?video_id=' + n.video_id;
                    } else if (n.type == 'friend_request' || n.type == 'friend_accepted') {
                        link = 'profile.php?user_id=' + n.sender_id;
                    }

                    html += `
                    <div class="relative group/nitem">
                        <a href="${link}" class="notif-item ${unreadClass}">
                            <img src="${n.profile_picture || 'uploads/profile.jpg'}" class="notif-avatar" onerror="this.src='uploads/profile.jpg'">
                            <div style="padding-right: 40px; flex: 1;">
                                <div class="notif-text"><strong>${n.username}</strong> ${n.message}</div>
                                <div class="notif-time">${n.time_ago}</div>
                            </div>
                        </a>
                        <button onclick="deleteNotification(${n.id}, event)" class="notif-delete" title="Delete notification">
                            <i class="fas fa-trash-alt text-[10px]"></i>
                        </button>
                    </div>`;
                });
            }
            document.getElementById('notif-items').innerHTML = html;
        });
}

function deleteNotification(id, e) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    const fd = new FormData();
    fd.append('delete_id', id);
    fetch('get_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) fetchNotifications();
        });
}

function showBrowserNotification(title, body, icon) {
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    new Notification(title, { body: body, icon: icon || 'uploads/profile.jpg' });
}

function playNotificationSound() {
    const sound = document.getElementById('notification-sound');
    if (sound) sound.play().catch(e => console.log("Sound play blocked"));
}

// Polling for UI
setInterval(fetchNotifications, 10000);

// Initial call
if (document.getElementById('notif-badge')) fetchNotifications();
</script>

<?php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$baseDir = dirname($scriptName);
if ($baseDir === '\\' || $baseDir === '/') $baseDir = '';
$baseUrl = $protocol . "://" . $host . $baseDir;
?>
<audio id="notification-sound" src="<?php echo $baseUrl; ?>/notification.mp3" preload="auto"></audio>
