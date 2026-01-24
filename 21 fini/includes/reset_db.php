<?php
session_start();
include("config.php");

$message = "";
$authenticated = isset($_SESSION['db_reset_auth']) && $_SESSION['db_reset_auth'] === true;

// Handle Login
if (isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if ($user === "mohamed-sat59@" && $pass === "mohamed-sat59@") {
        $_SESSION['db_reset_auth'] = true;
        $authenticated = true;
    } else {
        $message = "<div class='alert alert-danger'>بيانات الدخول غير صحيحة! Incorrect credentials.</div>";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['db_reset_auth']);
    header("Location: reset_db.php");
    exit();
}

// Handle Reset Logic
if ($authenticated && isset($_POST['confirm_reset'])) {
    // Disable foreign key checks to allow truncation
    mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");

    // List of tables to truncate
    $tables = [
        'users',
        'videos',
        'comments',
        'likes',
        'video_views',
        'story_views',
        'stories',
        'video_likes',
        'messages'
    ];

    $success = true;
    foreach ($tables as $table) {
        if (!mysqli_query($con, "TRUNCATE TABLE $table")) {
            $success = false;
            $message .= "Error emptying table $table: " . mysqli_error($con) . "<br>";
        }
    }

    // Re-enable foreign key checks
    mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");

    if ($success) {
        $message = "<div class='alert alert-success'>تم حذف جميع البيانات بنجاح. Database cleared.</div>";
    } else {
        $message = "<div class='alert alert-danger'>حدث خطأ أثناء الحذف:<br>$message</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Database - Secured</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #000;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            padding: 20px;
        }
        .warning-card {
            background-color: #1a1a1a;
            padding: 40px;
            border-radius: 20px;
            border: 2px solid #ff0050;
            max-width: 500px;
            width: 100%;
        }
        .btn-primary {
            background-color: #00f2ea;
            border: none;
            color: #000;
        }
        .btn-danger {
            background-color: #ff0050;
            border: none;
            padding: 15px 30px;
            font-size: 20px;
            margin-top: 20px;
        }
        .btn-danger:hover {
            background-color: #d60045;
        }
        .form-control {
            background-color: #333;
            border: 1px solid #444;
            color: #fff;
            margin-bottom: 15px;
        }
        .form-control:focus {
            background-color: #444;
            color: #fff;
            border-color: #ff0050;
            box-shadow: none;
        }
    </style>
</head>
<body>

<div class="container warning-card">
    <?php if (!$authenticated): ?>
        <h2 class="mb-4">تسجيل الدخول للإدارة</h2>
        <h5 class="mb-4 text-muted">Admin Login Required</h5>
        
        <?php echo $message; ?>

        <form method="post">
            <input type="text" name="username" class="form-control" placeholder="اسم المستخدم (Username)" required>
            <input type="password" name="password" class="form-control" placeholder="كلمة السر (Password)" required>
            <button type="submit" name="login" class="btn btn-primary w-100 py-2">دخول (Login)</button>
        </form>
    <?php else: ?>
        <h1 class="text-danger mb-4">تحذير! Warning!</h1>
        <p class="lead">أنت على وشك حذف جميع البيانات من قاعدة البيانات.</p>
        <p>You are about to delete ALL data from the database.</p>
        <ul class="text-start" style="list-style-position: inside;">
            <li>Users (المستخدمين)</li>
            <li>Videos (الفيديوهات)</li>
            <li>Stories (القصص)</li>
            <li>Messages (الرسائل)</li>
        </ul>
        
        <?php echo $message; ?>

        <form method="post">
            <button type="submit" name="confirm_reset" class="btn btn-danger w-100" onclick="return confirm('Are you 100% sure? This cannot be undone.');">
                تأكيد الحذف (Delete All)
            </button>
        </form>
        
        <div class="mt-4 d-flex justify-content-between">
            <a href="indexmo.php" class="text-white text-decoration-none">عودة للرئيسية</a>
            <a href="?logout=1" class="text-danger text-decoration-none">تسجيل الخروج</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
