<?php
session_start();
include("config.php");
include("includes/remember_me.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $target_dir = "uploads/profiles/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if the file is an image
    $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
    if ($check !== false) {
        // Create a unique filename for the image
        $new_filename = "profile_" . $user_id . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;

        // Allow only specific image types
        if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif") {
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                // Update image path in database
                $update_query = mysqli_prepare($con, "UPDATE users SET profile_picture = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_query, "si", $target_file, $user_id);
                mysqli_stmt_execute($update_query);
            }
        }
    }
}

// Video deletion process
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_video_id'])) {
    $video_id = intval($_POST['delete_video_id']);

    // Check that the video belongs to the current user before deletion and get location
    $check_query = mysqli_prepare($con, "SELECT user_id, location FROM videos WHERE id = ?");
    mysqli_stmt_bind_param($check_query, "i", $video_id);
    mysqli_stmt_execute($check_query);
    $result = mysqli_stmt_get_result($check_query);
    $video = mysqli_fetch_assoc($result);

    if ($video && $video['user_id'] == $user_id) {
        $file_path = $video['location'];

        // 1. Delete all comments associated with this video
        $delete_comments = mysqli_prepare($con, "DELETE FROM comments WHERE video_id = ?");
        mysqli_stmt_bind_param($delete_comments, "i", $video_id);
        mysqli_stmt_execute($delete_comments);

        // 2. Delete all likes associated with this video
        $delete_likes = mysqli_prepare($con, "DELETE FROM video_likes WHERE video_id = ?");
        mysqli_stmt_bind_param($delete_likes, "i", $video_id);
        mysqli_stmt_execute($delete_likes);

        // 3. Delete all views associated with this video
        $delete_views = mysqli_prepare($con, "DELETE FROM video_views WHERE video_id = ?");
        mysqli_stmt_bind_param($delete_views, "i", $video_id);
        mysqli_stmt_execute($delete_views);

        // 4. Delete all notifications associated with this video
        $delete_notifications = mysqli_prepare($con, "DELETE FROM notifications WHERE video_id = ?");
        mysqli_stmt_bind_param($delete_notifications, "i", $video_id);
        mysqli_stmt_execute($delete_notifications);

        // 5. Delete the video record from the database
        $delete_query = mysqli_prepare($con, "DELETE FROM videos WHERE id = ?");
        mysqli_stmt_bind_param($delete_query, "i", $video_id);
        
        if (mysqli_stmt_execute($delete_query)) {
            // 6. Delete the actual file from storage
            if (!empty($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
            header("Location: ".$_SERVER['PHP_SELF']."?deleted=1");
            exit();
        } else {
            $error_msg = "فشل في حذف الفيديو من قاعدة البيانات.";
        }
    } else {
        $error_msg = "ليس لديك صلاحية لحذف هذا الفيديو.";
    }
}

// Handle profile update (username and password)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_username = mysqli_real_escape_string($con, $_POST['username']);
    $new_password = $_POST['password'];

    // Basic validation
    if (!empty($new_username)) {
        if (!empty($new_password)) {
             // Update both
             $update_query = mysqli_prepare($con, "UPDATE users SET username = ?, password = ? WHERE id = ?");
             mysqli_stmt_bind_param($update_query, "ssi", $new_username, $new_password, $user_id);
        } else {
             // Update username only
             $update_query = mysqli_prepare($con, "UPDATE users SET username = ? WHERE id = ?");
             mysqli_stmt_bind_param($update_query, "si", $new_username, $user_id);
        }

        if (mysqli_stmt_execute($update_query)) {
             $_SESSION['username'] = $new_username;
             $success_msg = "تم تحديث الملف الشخصي بنجاح!";
             // header("Location: ".$_SERVER['PHP_SELF']);
        } else {
             $error_msg = "خطأ في تحديث الملف الشخصي: " . mysqli_error($con);
        }
    } else {
        $error_msg = "لا يمكن أن يكون اسم المستخدم فارغاً.";
    }
}

// Check if viewing someone else
$is_my_profile = true;
$profile_id = $user_id;

if (isset($_GET['user_id']) && $_GET['user_id'] != $user_id) {
    $profile_id = intval($_GET['user_id']);
    $is_my_profile = false;
}

// Fetch user data
$user_query = mysqli_prepare($con, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($user_query, "i", $profile_id);
mysqli_stmt_execute($user_query);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($user_query));

if (!$user_data) {
    die("User not found.");
}

// Check friendship status if not my profile
$friend_status = 'none';
if (!$is_my_profile) {
    $check_friend = mysqli_query($con, "SELECT status, sender_id FROM friends WHERE (sender_id = $user_id AND receiver_id = $profile_id) OR (sender_id = $profile_id AND receiver_id = $user_id)");
    if ($f_row = mysqli_fetch_assoc($check_friend)) {
        if ($f_row['status'] == 'accepted') {
            $friend_status = 'friend';
        } elseif ($f_row['sender_id'] == $user_id) {
            $friend_status = 'sent';
        } else {
            $friend_status = 'received';
        }
    }
}

// Fetch user videos
if ($is_my_profile) {
    // Show all my own videos
    $fetchUserVideos = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $profile_id ORDER BY created_at DESC");
} elseif ($friend_status == 'friend') {
    // Friends see everything (as per previous app logic)
    $fetchUserVideos = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $profile_id ORDER BY created_at DESC");
} else {
    // Strangers see ONLY public videos
    $fetchUserVideos = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $profile_id AND privacy = 'public' ORDER BY created_at DESC");
}

// Set success message if redirected after deletion
if (isset($_GET['deleted'])) {
    $success_msg = "تم حذف الفيديو بنجاح!";
}
?>

<?php
// Set page title
$pageTitle = $is_my_profile ? "My Profile" : htmlspecialchars($user_data['username']) . " (@" . htmlspecialchars($user_data['username']) . ")";

// Include layout start
include("includes/layout_start.php");
?>

    <div class="max-w-4xl mx-auto pb-12" data-purpose="profile-page">
        <!-- Profile Header Area -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-8">
            <!-- Cover Placeholder / Header Background -->
            <div class="h-40 md:h-52 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 relative">
                <div class="absolute inset-0 opacity-20" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
            </div>
            
            <div class="px-6 pb-6 relative">
                <!-- Avatar & Actions Row -->
                <div class="flex flex-col md:flex-row items-center md:items-end justify-between -mt-16 md:-mt-20 mb-6 gap-6">
                    <div class="flex flex-col md:flex-row items-center md:items-end gap-6">
                        <div class="relative group">
                            <div class="w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-white shadow-xl overflow-hidden bg-slate-100 ring-4 ring-slate-50/50">
                                <?php 
                                    $p_img = !empty($user_data['profile_picture']) ? $user_data['profile_picture'] : 'uploads/profile.jpg';
                                ?>
                                <img src="<?= $p_img ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" id="profile-preview" onerror="this.src='uploads/profile.jpg'">
                                
                                <?php if ($is_my_profile): ?>
                                    <form id="profile-pic-form" method="post" enctype="multipart/form-data" class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                        <label for="profile-upload" class="cursor-pointer text-white flex flex-col items-center gap-2">
                                            <i class="fas fa-camera text-2xl"></i>
                                            <span class="text-[10px] font-bold uppercase tracking-widest">Update</span>
                                        </label>
                                        <input type="file" id="profile-upload" name="profile_picture" class="hidden" accept="image/*" onchange="document.getElementById('save-profile-btn').style.display='flex'; previewImage(event);">
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="text-center md:text-left pt-2 md:pb-2">
                            <h2 class="text-2xl md:text-3xl font-black text-slate-900 flex items-center justify-center md:justify-start gap-2">
                                <?= htmlspecialchars($user_data['username']) ?>
                                <svg class="w-5 h-5 text-blue-500 fill-current" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </h2>
                            <p class="text-slate-500 font-medium">@<?= htmlspecialchars($user_data['username']) ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php if ($is_my_profile): ?>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#editProfileModal" class="px-6 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all flex items-center gap-2">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                            <!-- Secondary Save Photo Button (Initially Hidden) -->
                            <button form="profile-pic-form" type="submit" id="save-profile-btn" style="display:none;" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-bold text-sm hover:bg-emerald-700 transition-all items-center gap-2 shadow-lg shadow-emerald-500/20">
                                <i class="fas fa-save"></i> Save Photo
                            </button>
                        <?php else: ?>
                            <div id="friend-btn-container" class="flex gap-2">
                                <?php if ($friend_status == 'none'): ?>
                                    <button onclick="friendRequest(<?= $profile_id ?>, 'add')" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold text-sm hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 flex items-center gap-2">
                                        <i class="fas fa-user-plus"></i> Follow
                                    </button>
                                <?php elseif ($friend_status == 'sent'): ?>
                                    <button class="px-6 py-2.5 bg-slate-100 text-slate-400 rounded-xl font-bold text-sm cursor-not-allowed flex items-center gap-2">
                                        <i class="fas fa-clock"></i> Requested
                                    </button>
                                <?php elseif ($friend_status == 'received'): ?>
                                    <button onclick="friendRequest(<?= $profile_id ?>, 'accept')" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-bold text-sm hover:bg-emerald-700 transition-all flex items-center gap-2">
                                        <i class="fas fa-check"></i> Accept Follow
                                    </button>
                                <?php elseif ($friend_status == 'friend'): ?>
                                    <button onclick="if(confirm('Unfriend user?')) friendRequest(<?= $profile_id ?>, 'unfriend')" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all flex items-center gap-2">
                                        <i class="fas fa-user-friends text-blue-500"></i> Following
                                    </button>
                                    <a href="message.php?user_id=<?= $profile_id ?>" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl font-bold text-sm hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 flex items-center gap-2">
                                        <i class="fas fa-comment"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Divider -->
                <div class="border-t border-slate-50 pt-6 flex items-center justify-around md:justify-start md:gap-12">
                    <div class="text-center md:text-left">
                        <span class="block text-xl font-black text-slate-900"><?= mysqli_num_rows($fetchUserVideos) ?></span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Videos</span>
                    </div>
                    <div class="text-center md:text-left">
                        <span class="block text-xl font-black text-slate-900"><?php 
                            $count_friends = mysqli_query($con, "SELECT COUNT(*) as total FROM friends WHERE (sender_id = $profile_id OR receiver_id = $profile_id) AND status = 'accepted'");
                            $friends_data = mysqli_fetch_assoc($count_friends);
                            echo $friends_data['total'];
                        ?></span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Friends</span>
                    </div>
                    <?php if (!$is_my_profile && $friend_status == 'friend'): ?>
                         <div class="hidden md:block">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 bg-blue-600 rounded-full animate-pulse"></span>
                                Online Recently
                            </span>
                         </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mb-6 flex items-center justify-between">
            <h3 class="text-lg font-black text-slate-900 flex items-center gap-2 font-brand">
                <i class="fas fa-play-circle text-blue-500"></i>
                <?= $is_my_profile ? "My Gallery" : "User Showcase" ?>
            </h3>
            <div class="flex gap-2">
                <button class="w-9 h-9 flex items-center justify-center rounded-xl bg-white text-blue-600 border border-slate-100 shadow-sm"><i class="fas fa-th-large"></i></button>
                <button class="w-9 h-9 flex items-center justify-center rounded-xl bg-white text-slate-400 border border-slate-100"><i class="fas fa-list"></i></button>
            </div>
        </div>

        <!-- Videos Grid -->
        <?php if (mysqli_num_rows($fetchUserVideos) > 0): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <?php while ($row = mysqli_fetch_assoc($fetchUserVideos)): ?>
                    <div class="relative group aspect-[3/4] rounded-2xl overflow-hidden bg-slate-900 border border-slate-100 shadow-sm">
                        <video src="<?= htmlspecialchars($row['location']) ?>" class="w-full h-full object-cover"></video>
                        
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex flex-col justify-end p-4">
                            <h5 class="text-white text-xs font-bold line-clamp-1 mb-2"><?= htmlspecialchars($row['title']) ?></h5>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-white/80"><i class="fas fa-eye mr-1"></i> <?= $row['views'] ?></span>
                                <div class="flex gap-2">
                                    <button class="w-7 h-7 flex items-center justify-center bg-white/20 backdrop-blur-md rounded-lg text-white hover:bg-white/40 transition-colors">
                                        <i class="fas fa-play text-[10px]"></i>
                                    </button>
                                    <?php if ($is_my_profile): ?>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this video?');" class="m-0">
                                            <input type="hidden" name="delete_video_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="w-7 h-7 flex items-center justify-center bg-rose-500/20 backdrop-blur-md rounded-lg text-rose-300 hover:bg-rose-500 hover:text-white transition-all">
                                                <i class="fas fa-trash text-[10px]"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Video Privacy Badge -->
                        <?php if (isset($row['privacy']) && $row['privacy'] == 'private'): ?>
                            <div class="absolute top-2 right-2 bg-slate-900/60 backdrop-blur-md text-white text-[9px] font-bold px-2 py-1 rounded-lg flex items-center gap-1">
                                <i class="fas fa-lock"></i> Private
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl border-2 border-dashed border-slate-200 p-12 text-center">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-video-slash text-3xl text-slate-300"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-900 mb-2">No Content Yet</h4>
                <p class="text-slate-500 text-sm mb-8">Share your first high-fidelity moment with the community.</p>
                <?php if ($is_my_profile): ?>
                    <a href="uplod-profile.php" class="inline-flex items-center gap-3 px-8 py-3 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20">
                        <i class="fas fa-upload"></i> Upload Video
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content overflow-hidden border-0 shadow-2xl rounded-3xl">
          <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-8">
            <h5 class="text-2xl font-black text-white text-center font-brand">Update Identity</h5>
            <p class="text-blue-100 text-xs text-center mt-1">Refine how the world sees you.</p>
          </div>
          <form method="POST" class="bg-white">
            <div class="p-8 space-y-6">
              <input type="hidden" name="update_profile" value="1">
              <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Public Name</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user_data['username']) ?>" required class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-medium">
              </div>
              <div class="space-y-2 text-right dir-rtl" dir="rtl">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">كلمة المرور الجديدة</label>
                <div class="relative">
                    <input type="password" id="modal-pass" name="password" placeholder="اتركه فارغاً للاحتفاظ بالحالية" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-sm text-right">
                    <button type="button" onclick="togglePass('modal-pass', this)" class="absolute inset-y-0 left-0 pl-4 text-slate-400">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
              </div>
            </div>
            <div class="bg-slate-50 p-6 flex gap-3">
              <button type="button" class="flex-1 py-3 bg-white border border-slate-200 text-slate-600 rounded-2xl font-bold text-sm" data-bs-dismiss="modal">Discard</button>
              <button type="submit" class="flex-[1.5] py-3 bg-blue-600 text-white rounded-2xl font-bold text-sm shadow-xl shadow-blue-500/20">Apply Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    function previewImage(event) {
        const reader = new FileReader();
        reader.onload = function() {
            const preview = document.getElementById('profile-preview');
            preview.src = reader.result;
        }
        reader.readAsDataURL(event.target.files[0]);
    }

    function togglePass(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function friendRequest(userId, action) {
        const container = document.getElementById('friend-btn-container');
        const originalHtml = container.innerHTML;
        container.innerHTML = '<div class="w-8 h-8 rounded-full border-4 border-blue-100 border-t-blue-600 animate-spin mx-auto"></div>';
        
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
                window.location.reload();
            } else {
                container.innerHTML = originalHtml;
                alert(data.error);
            }
        })
        .catch(err => {
            container.innerHTML = originalHtml;
            alert('Server error');
        });
    }
    </script>

<?php
// Include layout end
include("includes/layout_end.php");
?>