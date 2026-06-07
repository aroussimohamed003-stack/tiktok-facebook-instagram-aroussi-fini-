<?php
session_start();
include("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data including profile picture
$fetchUserData = mysqli_query($con, "SELECT * FROM users WHERE id = $user_id");
$userData = mysqli_fetch_assoc($fetchUserData);

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    if ($_FILES['profile_picture']['error'] == 0) {
        $target_dir = "uploads/profiles/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
        }
        $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if file is an image
        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        if ($check !== false) {
            // Check file size (5MB max)
            if ($_FILES["profile_picture"]["size"] <= 5000000) {
                // Allow certain file formats
                if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif") {
                    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                        // Delete old profile picture if exists
                        if ($userData['profile_picture'] && file_exists($userData['profile_picture'])) {
                            unlink($userData['profile_picture']);
                        }
                        // Update profile picture in database
                        mysqli_query($con, "UPDATE users SET profile_picture = '$target_file' WHERE id = $user_id");
                        header("Location: profile.php"); // Refresh the page
                        exit();
                    } else {
                        $error = "حدث خطأ أثناء تحميل الصورة.";
                    }
                } else {
                    $error = "فقط ملفات JPG, JPEG, PNG & GIF مسموح بها.";
                }
            } else {
                $error = "حجم الصورة كبير جدًا. الحد الأقصى هو 5MB.";
            }
        } else {
            $error = "الملف المرفوع ليس صورة.";
        }
    } else {
        $error = "حدث خطأ أثناء تحميل الصورة.";
    }
}

// Handle profile picture deletion
if (isset($_GET['delete_picture'])) {
    if ($userData['profile_picture'] && file_exists($userData['profile_picture'])) {
        unlink($userData['profile_picture']);
    }
    mysqli_query($con, "UPDATE users SET profile_picture = NULL WHERE id = $user_id");
    header("Location: profile.php"); // Refresh the page
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ملفي الشخصي</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #000; color: #fff; }
    .profile-picture { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; }
    .upload-form { max-width: 400px; margin: 20px auto; }
    .error { color: red; }
  </style>
</head>
<body>
<br><br><br>
<div class="container mt-4">
  <h2 class="text-center"> profile</h2>

  <!-- Display Profile Picture -->
  <div class="text-center">
    <?php if ($userData['profile_picture']): ?>
      <img src="<?php echo $userData['profile_picture']; ?>" class="profile-picture" alt="Profile Picture">
    <?php else: ?>
      <img src="uploads/profile.jpg" class="profile-picture" alt="Default Profile Picture">
    <?php endif; ?>
    <br><br>
    <a href="?delete_picture=true" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف الصورة الشخصية؟');">حذف الصورة الشخصية</a>
  </div>

  <!-- Upload Profile Picture Form -->
  <div class="upload-form">
  <h4 class="text-center">Name : <?php echo $userData['username']; ?></h4>
    <?php if (isset($error)): ?>
      <p class="error text-center"><?php echo $error; ?></p>
    <?php endif; ?>
    <form action="profile.php" method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label for="profile_picture">اختر صورة شخصية:</label>
        <input type="file" class="form-control" id="profile_picture" name="profile_picture" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">تحميل</button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>