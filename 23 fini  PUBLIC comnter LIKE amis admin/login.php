<?php
include("config.php");
session_start();
include("includes/remember_me.php");

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: indexmo.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $password = $_POST['password'];  // Remove encryption

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($con, $query);
    $user = mysqli_fetch_assoc($result);

    if ($user && $password === $user['password']) {  // Compare password directly
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        // Persistent Login (Remember Me)
        try {
            $token = bin2hex(random_bytes(32));
            // Update user with token
            $update_query = "UPDATE users SET remember_token = '$token' WHERE id = " . $user['id'];
            mysqli_query($con, $update_query);
            
            // Set cookie for 30 days
            setcookie('remember_me', $token, time() + (86400 * 30), "/");
        } catch (Exception $e) {
            // Ignore token errors, proceed with login
        }

        header("Location: indexmo.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Roboto:900,400,300|Dancing+Script" rel="stylesheet">
  <link rel="stylesheet" href="register/main.css">
  <title>Login </title>
</head>
<body>

  <div class="panda">
    <div class="ear"></div>
    <div class="face">
      <div class="eye-shade"></div>
      <div class="eye-white">
        <div class="eye-ball"></div>
      </div>
      <div class="eye-shade rgt"></div>
      <div class="eye-white rgt">
        <div class="eye-ball"></div>
      </div>
      <div class="nose"></div>
      <div class="mouth"></div>
    </div>
    <div class="body"> </div>
    <div class="foot">
      <div class="finger"></div>
    </div>
    <div class="foot rgt">
      <div class="finger"></div>
    </div>
  </div>
  <form method="POST" action="">
    <div class="hand"></div>
    <div class="hand rgt"></div>
    <h1> Login</h1>
    <?php
    if (isset($error)) {
        echo '<p class="alert">' . $error . '</p>';
    }
    ?>
    <div class="form-group">
      <input name="username" required="required" class="form-control"/>
      <label class="form-label">Username</label>
    </div>
    <div class="form-group">
      <input id="password" name="password" type="password" required="required" class="form-control"/>
      <label class="form-label">Password</label>
      <button type="submit" class="btn">Login</button>
    </div>
  </form>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="register/js.js"></script>

</body>
</html>

