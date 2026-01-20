<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link href="style.css" rel="stylesheet">
</head>
<body>
  <div class="app-vedio">
    <?php
      include("config.php"); 
      // تأكد من أن الاتصال بقاعدة البيانات موجود في المتغير $con
      $fetchAllVidos = mysqli_query($con, "SELECT * FROM videos ORDER BY id DESC");
      while($row = mysqli_fetch_assoc($fetchAllVidos)){
          $location = $row['location'];
          $subject = $row['subject'];
          $title   = $row['title'];
          echo '<div class="video">';
          echo '  <video src="'.$location.'" class="vedio-player"></video>';
          echo '  <div class="footer">';
          echo '    <div class="footer-text">';
          echo '      <h3>Mohamed Aroussi</h3>';
          echo '      <p class="description">'.$subject.'</p>';
          echo '      <div class="img-marq">';
          echo '        <a href="upload.php"><img src="images/pngwing.com.png" alt="Upload"></a>';
          echo '        <marquee>'.$title.'</marquee>';
          echo '      </div>';
          echo '    </div>';
          echo '  </div>';
          echo '</div>';
      }
    ?>
  </div>
  <script>
    const vedios = document.querySelectorAll('video');
    for(const video of vedios){
      video.addEventListener('click', function(){
        if(video.paused){
          video.play();
        } else {
          video.pause();
        }
      });
    }
  </script>
</body>
</html>
