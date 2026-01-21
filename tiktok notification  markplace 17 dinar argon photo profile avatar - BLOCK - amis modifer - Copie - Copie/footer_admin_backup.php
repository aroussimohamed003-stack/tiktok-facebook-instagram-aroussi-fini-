<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إدارة المستخدمين</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <h1 class="text-center mb-4">إدارة المستخدمين</h1>

    <?php
    // اتصال بقاعدة البيانات
 include("conn.php");
    // حذف المستخدم عند النقر على زر الحذف
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $stmt = $database->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('تم حذف المستخدم بنجاح'); window.location='admin-cpanl.php';</script>";
    }

    // جلب جميع المستخدمين من قاعدة البيانات
    $sql = $database->prepare("SELECT * FROM users");
    $sql->execute();
    $users = $sql->fetchAll(PDO::FETCH_ASSOC);

    // عرض المستخدمين
    foreach ($users as $user) {
        echo '
        <div class="card text-white bg-danger mb-3 float-left m-3" style="max-width: 18rem;">
          <div class="card-header">ID: ' . $user["id"] . '</div>
          <div class="card-body">
            <h5 class="card-title">' . $user["username"] . '</h5>
            <p class="card-text">كلمة المرور: ' . $user["password"] . '</p>
            <p class="card-text">تاريخ الإنشاء: ' . $user["created_at"] . '</p>
            <a href="edit.php?id=' . $user["id"] . '" class="btn btn-warning">تعديل</a>
            <a href="admin-cpanl.php?delete=' . $user["id"] . '" class="btn btn-danger" onclick="return confirm(\'هل أنت متأكد من الحذف؟\');">حذف</a>
          </div>
        </div>';
    }
    ?>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>