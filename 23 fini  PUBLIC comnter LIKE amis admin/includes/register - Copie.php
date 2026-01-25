<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Roboto:900,400,300|Dancing+Script" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:900,400,300|Dancing+Script" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="register/main.css">
  <title>Register</title>
  <style>
    .video-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      padding: 12px 20px;
      background: linear-gradient(135deg, #6e8efb, #a777e3);
      color: white;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      font-family: 'Roboto', sans-serif;
      font-weight: 500;
      font-size: 16px;
      z-index: 1000;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      transition: all 0.3s ease;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .video-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
      background: linear-gradient(135deg, #a777e3, #6e8efb);
    }
    
    .video-btn:active {
      transform: translateY(1px);
    }
    
    .video-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: 0.5s;
    }
    
    .video-btn:hover::before {
      left: 100%;
    }
    
    .video-btn i {
      margin-right: 8px;
      font-size: 18px;
    }
    
    .pulse {
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(167, 119, 227, 0.7);
      }
      70% {
        box-shadow: 0 0 0 10px rgba(167, 119, 227, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(167, 119, 227, 0);
      }
    }
    
    .video-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.9);
      z-index: 1001;
      justify-content: center;
      align-items: center;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .video-modal.active {
      opacity: 1;
    }
    
    .video-container {
      width: 80%;
      max-width: 800px;
      position: relative;
      transform: scale(0.8);
      transition: transform 0.3s ease;
    }
    
    .video-modal.active .video-container {
      transform: scale(1);
    }
    
    .close-btn {
      position: absolute;
      top: -50px;
      right: 0;
      color: white;
      font-size: 36px;
      cursor: pointer;
      transition: transform 0.2s;
    }
    
    .close-btn:hover {
      transform: rotate(90deg);
    }
    
    .video-container iframe {
      width: 100%;
      height: 450px;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
  </style>
</head>
<style>
    .t{
          text-decoration: none;
    font-size: 22px;
    margin-left: 128px;
    }
</style>
<body>
 <br><br>
     <nav class="navbar bg-body-tertiary fixed-top">
  <div class="container-fluid">
<button class="video-btn pulse" id="videoBtn">
    <i class="fas fa-play-circle"></i> Watch Tutorial
  </button>
  
  <div class="video-modal" id="videoModal">
    <div class="video-container">
      <span class="close-btn" id="closeBtn">&times;</span>
      <iframe id="youtubeVideo" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">welacme</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              about
            </a>
            <ul class="dropdown-menu">
               
              <li><a class="dropdown-item" href="register.php">about</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
             <!-- <li><a class="dropdown-item" href="#">Something else here</a></li>-->
         
            </ul>
            
                      <li class="nav-item">
                <!--<a href="Aroussi.apk" download>
        <img src="Play_Store.png" alt="Download App" style="width: 200px; cursor: pointer;">--->
    </a>
          </li>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>





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
    <h1>Register</h1>
    <div class="form-group">
      <input required="required" class="form-control" name="username"/>
      <label class="form-label">Username</label>
    </div>
    <div class="form-group">
      <input id="password" type="password" required="required" class="form-control" name="password"/>
      <label class="form-label">Password</label>
      <p class="alert">Invalid Credentials..!!</p>
      <button type="submit" class="btn">Register</button><br><br>
          <a class="t" href="login.php">Login</a>
    </div>

  </form>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="register/js.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>



  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="register/js.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const videoBtn = document.getElementById('videoBtn');
        const modal = document.getElementById('videoModal');
        const closeBtn = document.getElementById('closeBtn');
        const iframe = document.getElementById('youtubeVideo');
        
        videoBtn.addEventListener('click', function() {
            // Use the correct YouTube embed link
            iframe.src = "https://www.youtube.com/embed/BS9M7jxXTIg?autoplay=1&rel=0";
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('active');
            }, 10);
        });
        
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                iframe.src = "";
            }, 300);
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                    iframe.src = "";
                }, 300);
            }
        });
        
        // Add vibration effect to button every 10 seconds to attract attention
        setInterval(() => {
            videoBtn.classList.add('pulse');
            setTimeout(() => {
                videoBtn.classList.remove('pulse');
            }, 2000);
        }, 10000);
    });
</script>





</body>
</html>

<?php
include("config.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $password = $_POST['password'];  // Remove encryption

    $query = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
    if (mysqli_query($con, $query)) {
        $new_user_id = mysqli_insert_id($con);
        
        // AUTOMATICALLY FRIEND WITH ADMIN
        $admin_res = mysqli_query($con, "SELECT id FROM users WHERE username = 'admin sponsor'");
        if(mysqli_num_rows($admin_res) > 0){
             $admin_row = mysqli_fetch_assoc($admin_res);
             $admin_id = $admin_row['id'];
             // Insert friendship (Admin as sender, New User as receiver, Accepted)
             mysqli_query($con, "INSERT IGNORE INTO friends (sender_id, receiver_id, status) VALUES ($admin_id, $new_user_id, 'accepted')");
        }
        
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($con);
    }
}
?>