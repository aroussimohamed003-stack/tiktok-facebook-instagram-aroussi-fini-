<?php
session_start();
include("config.php");
include("includes/remember_me.php");

// If already logged in via cookie, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: indexmo.php");
    exit();
}


// معالجة تسجيل الدخول
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $con->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if ($password === $user['password']) {
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
            } catch (Exception $e) {}

            header("Location: indexmo.php");
            exit();
        } else {
            $login_error = "كلمة المرور غير صحيحة";
        }
    } else {
        $login_error = "اسم المستخدم غير موجود";
    }
    $stmt->close();
}

// معالجة التسجيل
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "اسم المستخدم مطلوب";
    } elseif (strlen($username) < 4) {
        $errors[] = "اسم المستخدم يجب أن يكون على الأقل 4 أحرف";
    }
    
    if (empty($password)) {
        $errors[] = "كلمة المرور مطلوبة";
    } elseif (strlen($password) < 8) {
        $errors[] = "كلمة المرور يجب أن تكون على الأقل 8 أحرف";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "كلمة المرور غير متطابقة";
    }
    
    if (empty($errors)) {
        $stmt = $con->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "اسم المستخدم موجود مسبقاً";
        } else {
            $stmt = $con->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "تم التسجيل بنجاح! يمكنك تسجيل الدخول الآن";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $errors[] = "حدث خطأ أثناء التسجيل. يرجى المحاولة لاحقاً.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام تسجيل الدخول والتسجيل</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Tajawal', sans-serif;
    }
    
    body {
        background: linear-gradient(135deg, #6e8efb, #a777e3);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        overflow-x: hidden;
    }
    
    .container {
        position: relative;
        width: 100%;
        max-width: 800px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        padding: 30px;
        z-index: 10;
        animation: fadeIn 0.8s ease;
        margin: 80px 0 30px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(50px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-container {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
    }
    
    .form-section {
        flex: 1;
        min-width: 300px;
        padding: 15px;
    }
    
    .divider {
        width: 2px;
        min-height: 2px;
        background: rgba(0,0,0,0.1);
        margin: 10px 0;
    }
    
    h2 {
        color: #333;
        text-align: center;
        margin-bottom: 25px;
        font-weight: 700;
        position: relative;
        font-size: 1.5rem;
    }
    
    h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background: linear-gradient(to right, #6e8efb, #a777e3);
        border-radius: 2px;
    }
    
    .input-group {
        position: relative;
        margin-bottom: 20px;
    }
    
    .input-group input {
        width: 100%;
        padding: 12px 18px;
        border: 2px solid #ddd;
        border-radius: 50px;
        font-size: 15px;
        outline: none;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
    }
    
    .input-group input:focus {
        border-color: #a777e3;
        box-shadow: 0 0 10px rgba(167, 119, 227, 0.3);
    }
    
    .input-group label {
        position: absolute;
        top: 12px;
        right: 18px;
        color: #777;
        transition: all 0.3s ease;
        pointer-events: none;
        font-size: 15px;
    }
    
    .input-group input:focus + label,
    .input-group input:valid + label {
        top: -8px;
        right: 12px;
        font-size: 11px;
        background: white;
        padding: 0 8px;
        color: #a777e3;
    }
    
    .btn {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 50px;
        background: linear-gradient(to right, #6e8efb, #a777e3);
        color: white;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(110, 142, 251, 0.4);
    }
    
    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(110, 142, 251, 0.6);
    }
    
    .btn-register {
        background: linear-gradient(to right, #4CAF50, #2E7D32);
        box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
    }
    
    .btn-register:hover {
        box-shadow: 0 8px 20px rgba(76, 175, 80, 0.6);
    }
    
    .btn-video {
        margin-top: 12px;
        background: linear-gradient(to right, #ff5e62, #ff9966);
        box-shadow: 0 5px 15px rgba(255, 94, 98, 0.4);
    }
    
    .btn-video:hover {
        box-shadow: 0 8px 20px rgba(255, 94, 98, 0.6);
    }
    
    .alert {
        padding: 12px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
        font-size: 14px;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .toggle-password {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #777;
        font-size: 14px;
    }
    
    .bg-bubbles {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
        overflow: hidden;
    }
    
    .bg-bubbles li {
        position: absolute;
        list-style: none;
        display: block;
        width: 30px;
        height: 30px;
        background: rgba(255, 255, 255, 0.15);
        bottom: -160px;
        animation: square 25s infinite;
        transition-timing-function: linear;
        border-radius: 10px;
    }
    
    @keyframes square {
        0% { transform: translateY(0) rotate(0deg); opacity: 1; }
        100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
    }
    
    /* Modal for Local Video */
    .modal {
        display: none;
        position: fixed;
        z-index: 100;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        overflow: auto;
    }

    .modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        max-width: 800px;
    }

    .close {
        position: absolute;
        top: 15px;
        right: 15px;
        color: #fff;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        z-index: 101;
    }

    .video-container {
        width: 100%;
        height: 0;
        padding-bottom: 56.25%;
        position: relative;
        background: #000;
    }

    .video-container video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }

    /* تحسينات للشاشات الصغيرة */
    @media (max-width: 768px) {
        .container {
            padding: 20px;
            border-radius: 15px;
        }
        
        .form-container {
            flex-direction: column;
            gap: 15px;
        }
        
        .form-section {
            padding: 10px;
            min-width: 100%;
        }
        
        .divider {
            width: 100%;
            height: 1px;
            margin: 5px 0;
        }
        
        h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
        }
        
        .input-group input {
            padding: 10px 15px;
            font-size: 14px;
        }
        
        .input-group label {
            font-size: 14px;
            top: 10px;
            right: 15px;
        }
        
        .btn {
            padding: 10px;
            font-size: 14px;
        }
        
        .alert {
            padding: 10px;
            font-size: 13px;
        }
        
        .bg-bubbles li {
            width: 20px;
            height: 20px;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 15px;
            border-radius: 10px;
        }
        
        h2 {
            font-size: 1.2rem;
        }
        
        .input-group input {
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .input-group label {
            font-size: 13px;
        }
        
        .btn {
            font-size: 13px;
        }
        
        .toggle-password {
            font-size: 12px;
            left: 12px;
        }
    }
</style>
</head>
<body>
     <br><br>
     <nav class="navbar bg-body-tertiary fixed-top">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">مرحباً</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
     <!--   <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
          <li class="nav-item">
            <a class="nav-link" href="meet.php">Chat Live</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              حول التطبيق
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="register.php">حول التطبيق</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
            </ul>
                    </ul>-->
            
                      <li class="nav-item">
                <a href="Aroussi.apk" download>
        <img src="Play_Store.png" alt="تحميل التطبيق" style="width: 200px; cursor: pointer;">
    </a>
          </li>
          </li>
        </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<br><br><br><br><br>
    <div class="bg-bubbles">
        <li style="left: 10%;"></li>
        <li style="left: 20%; width: 80px; height: 80px; animation-delay: 2s; animation-duration: 17s;"></li>
        <li style="left: 25%; animation-delay: 4s;"></li>
        <li style="left: 40%; width: 60px; height: 60px; animation-duration: 22s; background: rgba(255, 255, 255, 0.25);"></li>
        <li style="left: 70%;"></li>
        <li style="left: 80%; width: 120px; height: 120px; animation-delay: 3s; background: rgba(255, 255, 255, 0.2);"></li>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <!-- تسجيل الدخول -->
            <div class="form-section">
                <h2>تسجيل الدخول</h2>
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="input-group">
                        <input type="text" name="username" id="username" required>
                        <label for="username">اسم المستخدم</label>
                    </div>
                    
                    <div class="input-group">
                        <input type="password" name="password" id="password" required>
                        <label for="password">كلمة المرور</label>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                    
                    <button type="submit" name="login" class="btn">دخول</button>
                    <button type="button" class="btn btn-video" id="videoBtn">مشاهدة فيديو تعريفي</button>
                </form>
            </div>
            
            <div class="divider"></div>
            
            <!-- التسجيل -->
            <div class="form-section">
                <h2>إنشاء حساب جديد</h2>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="input-group">
                        <input type="text" name="reg_username" id="reg_username" required>
                        <label for="reg_username">اسم المستخدم</label>
                    </div>
                    
                    <div class="input-group">
                        <input type="password" name="reg_password" id="reg_password" required>
                        <label for="reg_password">كلمة المرور</label>
                        <i class="fas fa-eye toggle-password" id="toggleRegPassword"></i>
                    </div>
                    
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <label for="confirm_password">تأكيد كلمة المرور</label>
                        <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-register">تسجيل</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Local Video Modal -->
    <div id="videoModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="video-container">
                <video id="localVideo" controls>
                    <source src="vid/fini clip sitte tiktok instagram.mp4" type="video/mp4">
                    المتصفح لا يدعم تشغيل الفيديو.
                </video>
            </div>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        function setupPasswordToggle(passwordId, toggleId) {
            const toggle = document.getElementById(toggleId);
            const password = document.getElementById(passwordId);
            
            if (toggle && password) {
                toggle.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.classList.toggle('fa-eye-slash');
                });
            }
        }
        
        // Setup all password toggles
        setupPasswordToggle('password', 'togglePassword');
        setupPasswordToggle('reg_password', 'toggleRegPassword');
        setupPasswordToggle('confirm_password', 'toggleConfirmPassword');
        
        // Floating bubbles animation
        document.querySelectorAll('.bg-bubbles li').forEach((bubble, index) => {
            bubble.style.animationDuration = `${15 + Math.random() * 20}s`;
            bubble.style.animationDelay = `${Math.random() * 5}s`;
            bubble.style.left = `${Math.random() * 100}%`;
            bubble.style.width = `${20 + Math.random() * 100}px`;
            bubble.style.height = bubble.style.width;
        });

        // Local Video Modal
        const modal = document.getElementById('videoModal');
        const videoBtn = document.getElementById('videoBtn');
        const closeBtn = document.getElementsByClassName('close')[0];
        const localVideo = document.getElementById('localVideo');

        videoBtn.addEventListener('click', function() {
            localVideo.currentTime = 0; // Start from beginning
            localVideo.play(); // Play the video
            modal.style.display = "block";
        });

        closeBtn.addEventListener('click', function() {
            localVideo.pause(); // Pause the video
            modal.style.display = "none";
        });

        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                localVideo.pause(); // Pause when clicking outside
                modal.style.display = "none";
            }
        });
    </script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>