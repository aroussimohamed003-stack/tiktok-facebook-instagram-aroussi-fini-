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

// Fetch user data including profile picture
$user_query = mysqli_query($con, "SELECT * FROM users WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($user_query);

// Fetch user videos
$fetchUserVideos = mysqli_query($con, "SELECT * FROM videos WHERE user_id = $user_id ORDER BY created_at DESC");

// Set success message if redirected after deletion
if (isset($_GET['deleted'])) {
    $success_msg = "تم حذف الفيديو بنجاح!";
}
?>

<?php
// Set page title
$pageTitle = "My Profile";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
.video-container {
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
    padding: 15px;
    border-radius: 8px;
    background-color: var(--card-bg);
}
.upload-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}
.profile-pic {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary-color);
}
.profile-header {
    text-align: center;
    margin-bottom: 30px;
}
.file-upload {
    display: none;
}
.upload-label {
    cursor: pointer;
}
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>
<div class="container mt-5">
  <!-- Profile Picture Section -->
  <div class="profile-header">
    <form method="post" enctype="multipart/form-data">
      <label for="profile-upload" class="upload-label">
        <?php if (!empty($user_data['profile_picture']) && file_exists($user_data['profile_picture'])): ?>
          <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>" class="profile-pic mb-3" id="profile-preview">
        <?php else: ?>
          <img src="uploads/profile.jpg" class="profile-pic mb-3" id="profile-preview">
        <?php endif; ?>
        <div class="text-center">
          <span class="badge bg-primary"><i class="fas fa-camera"></i> Change Photo</span>
        </div>
      </label>
      <input type="file" id="profile-upload" name="profile_picture" class="file-upload" accept="image/*">
      <button type="submit" class="btn btn-success mt-2" id="save-profile-btn" style="display:none;">
        <i class="fas fa-save"></i> Save Photo
      </button>
    </form>
    <h3 class="mt-3"><?php echo htmlspecialchars($user_data['username'] ?? 'User'); ?></h3>
    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
      <i class="fas fa-edit"></i> تعديل الملف الشخصي
    </button>
    
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success mt-3"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger mt-3"><?php echo $error_msg; ?></div>
    <?php endif; ?>
  </div>

  <h2 class="text-center mb-4">My Videos</h2>

  <?php if (mysqli_num_rows($fetchUserVideos) > 0): ?>
    <div class="row">
      <?php while ($row = mysqli_fetch_assoc($fetchUserVideos)): ?>
        <div class="col-md-4 mb-4">
          <div class="video-container">
            <video src="<?php echo htmlspecialchars($row['location']); ?>" class="w-100" controls></video>
            <div class="mt-3">
              <h5><?php echo htmlspecialchars($row['title']); ?></h5>
              <p><i class="fas fa-eye"></i> <?php echo $row['views']; ?> views</p>
              <form method="POST" onsubmit="return confirm('Are you sure you want to delete this video?');">
                <input type="hidden" name="delete_video_id" value="<?php echo $row['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-info text-center">
      <i class="fas fa-video-slash fa-2x mb-3"></i>
      <h4>No videos to display</h4>
      <a href="uplod-profile.php" class="btn btn-primary mt-2">
        <i class="fas fa-upload"></i> Upload New Video
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0" style="border-radius: 20px; background: var(--card-bg); color: var(--text-color);">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-center w-100" id="editProfileModalLabel">تعديل الملف الشخصي</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="update_profile" value="1">
          <div class="mb-4">
            <label for="username" class="form-label fw-bold">اسم المستخدم</label>
            <div class="input-group">
              <span class="input-group-text bg-transparent border-0 border-bottom rounded-0"><i class="fas fa-user text-primary"></i></span>
              <input type="text" class="form-control bg-transparent border-0 border-bottom rounded-0 shadow-none text-light" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
            </div>
          </div>
          <div class="mb-4">
            <label for="password" class="form-label fw-bold">كلمة المرور الجديدة</label>
            <div class="input-group">
              <span class="input-group-text bg-transparent border-0 border-bottom rounded-0"><i class="fas fa-lock text-primary"></i></span>
              <input type="password" class="form-control bg-transparent border-0 border-bottom rounded-0 shadow-none text-light" id="password" name="password" placeholder="اتركه فارغاً للاحتفاظ بالحالية">
              <button class="btn btn-outline-secondary border-0 border-bottom rounded-0" type="button" id="togglePassword">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-dark px-4" style="border-radius: 10px;" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary px-4" style="border-radius: 10px; background: linear-gradient(45deg, #00d2ff 0%, #3a7bd5 100%); border: none;">حفظ التغييرات</button>
        </div>
      </form>
    </div>
  </div>
</div>

<a href="uplod-profile.php" class="btn btn-primary upload-btn">
  <i class="fas fa-plus"></i> Upload New Video
</a>

<?php
// Set inline JavaScript
$inlineJs = "
// Display image preview before upload
document.getElementById('profile-upload').addEventListener('change', function(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('profile-preview').src = e.target.result;
      document.getElementById('save-profile-btn').style.display = 'inline-block';
    }
    reader.readAsDataURL(file);
  }
});

// Toggle password visibility
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

if (togglePassword) {
  togglePassword.addEventListener('click', function (e) {
      // toggle the type attribute
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      // toggle the eye slash icon
      this.querySelector('i').classList.toggle('fa-eye-slash');
  });
}

";

// Include footer
include("includes/footer.php");
?>