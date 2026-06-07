<?php
include("conn.php");

// جلب بيانات المستخدم
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $database->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
}

// تحديث بيانات المستخدم
if (isset($_POST['update'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $database->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
    $stmt->execute([$username, $password, $id]);

    echo "<script>alert('تم التعديل بنجاح'); window.location='admin-cpanl.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تعديل المستخدم</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
</head>
<body>

<div class="container mt-5">
  <h2>تعديل بيانات المستخدم</h2>
  <form method="POST">
    <div class="form-group">
      <label>اسم المستخدم</label>
      <input type="text" name="username" class="form-control" value="<?= $user['username']; ?>" required>
    </div>
    <div class="form-group">
      <label>كلمة المرور</label>
      <input type="text" name="password" class="form-control" value="<?= $user['password']; ?>" required>
    </div>
    <button type="submit" name="update" class="btn btn-success">حفظ التعديلات</button>
    <a href="index.php" class="btn btn-secondary">إلغاء</a>
  </form>
</div>

</body>
</html>
