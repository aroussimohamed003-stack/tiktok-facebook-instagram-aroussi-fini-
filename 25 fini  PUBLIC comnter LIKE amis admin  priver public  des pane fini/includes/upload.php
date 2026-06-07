<?php
// Database connection settings
// Database connection settings
$servername = "sql308.infinityfree.com";
$username = "if0_40097384";
$password = "1ThXLmVD9G9ZLGH";
$dbname = "if0_40097384_tik";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Processing chunk uploads (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['chunk'])) {
    $uploadDir = "videos/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $tempDir = "temp_uploads/";
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $fileId = $_POST['fileId'] ?? '';
    $chunkIndex = intval($_POST['chunkIndex'] ?? 0);
    $totalChunks = intval($_POST['totalChunks'] ?? 0);
    $fileName = $_POST['fileName'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $title = $_POST['title'] ?? '';
    
    $chunkFile = $tempDir . $fileId . "_chunk_" . $chunkIndex . ".tmp";
    if (move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
        $uploadedChunks = glob($tempDir . $fileId . "_chunk_*.tmp");
        if (count($uploadedChunks) == $totalChunks) {
            $finalPath = "videos/" . basename($fileName);
            if ($out = fopen($_SERVER['DOCUMENT_ROOT'] . "/" . $finalPath, "wb")) {
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkFileName = $tempDir . $fileId . "_chunk_" . $i . ".tmp";
                    if (!file_exists($chunkFileName)) {
                        fclose($out);
                        http_response_code(500);
                        echo "Error assembling file.";
                        exit;
                    }
                    if ($in = fopen($chunkFileName, "rb")) {
                        while ($buff = fread($in, 4096)) {
                            fwrite($out, $buff);
                        }
                        fclose($in);
                        unlink($chunkFileName);
                    }
                }
                fclose($out);
                $stmt = $conn->prepare("INSERT INTO videos (location, subject, title) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $finalPath, $subject, $title);
                $stmt->execute();
                $stmt->close();
                echo "File uploaded successfully.";
            } else {
                http_response_code(500);
                echo "Could not open final file.";
            }
        } else {
            echo "تم رفع الجزء " . $chunkIndex;
        }
    } else {
        http_response_code(500);
        echo "فشل رفع الجزء.";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>رفع فيديو</title>
  <style>
  
a {
            display: inline-block;
            text-decoration: none;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
  
    body { font-family: Arial, sans-serif; direction: rtl; text-align: center; background-color: #f4f4f4; padding: 20px; }
    .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
    input, button { width: 100%; margin: 10px 0; padding: 10px; border-radius: 5px; border: 1px solid #ccc; }
    button { background-color: #28a745; color: white; cursor: pointer; }
    button:hover { background-color: #218838; }
    .progress { width: 100%; background: #ddd; border-radius: 5px; overflow: hidden; margin-top: 10px; }
    .progress-bar { height: 20px; background: #28a745; width: 0%; transition: width 0.3s; }
    #message { margin-top: 15px; }
  </style>
</head>
<body>
    
<a href="index.php"> رجوع لصفحة رئسية </a>
  
    
  <div class="container">
    <h2>رفع فيديو (رفع متقطع)</h2>
    <input type="file" id="fileInput" accept="video/*">
    <input type="text" id="subject" placeholder="عنوان الفيديو" required>
    <input type="text" id="title" placeholder="وصف الفيديو" required>
    <button id="uploadBtn">رفع الفيديو</button>
    <div class="progress">
      <div class="progress-bar" id="progressBar"></div>
    </div>
    <div id="message"></div>
  </div>
  <script>
    const uploadBtn = document.getElementById("uploadBtn");
    const fileInput = document.getElementById("fileInput");
    const progressBar = document.getElementById("progressBar");
    const messageDiv = document.getElementById("message");
    const chunkSize = 1024 * 1024;
    uploadBtn.addEventListener("click", () => {
      const file = fileInput.files[0];
      const subject = document.getElementById("subject").value;
      const title = document.getElementById("title").value;
      if (!file) {
        alert("الرجاء اختيار ملف");
        return;
      }
      const fileId = Date.now() + "_" + file.name;
      let start = 0, chunkIndex = 0;
      function sendNextChunk() {
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);
        const formData = new FormData();
        formData.append("fileId", fileId);
        formData.append("chunkIndex", chunkIndex);
        formData.append("totalChunks", Math.ceil(file.size / chunkSize));
        formData.append("fileName", file.name);
        formData.append("subject", subject);
        formData.append("title", title);
        formData.append("chunk", chunk);
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "", true);
        xhr.onload = function() {
          if (xhr.status === 200) {
            progressBar.style.width = Math.min(100, Math.floor((end / file.size) * 100)) + "%";
            if (end < file.size) {
              start = end;
              chunkIndex++;
              sendNextChunk();
            } else {
              messageDiv.innerHTML = "<p style='color:green;'>تم رفع الملف بنجاح!</p>";
            }
          } else {
            messageDiv.innerHTML = "<p style='color:red;'>حدث خطأ أثناء الرفع.</p>";
          }
        };
        xhr.onerror = function() {
          messageDiv.innerHTML = "<p style='color:red;'>فشل الاتصال بالخادم.</p>";
        };
        xhr.send(formData);
      }
      sendNextChunk();
    });
  </script>
</body>
</html>
<script>
fileInput.addEventListener('change', function() {
  const file = fileInput.files[0];
  if (file) {
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.onloadedmetadata = function() {
      window.URL.revokeObjectURL(video.src);
      if (video.videoWidth > video.videoHeight) {
        alert('الرجاء رفع فيديو عمودي (رأسي) بدلاً من أفقي.');
        fileInput.value = ''; // مسح الملف المختار
      }
    };
    video.src = URL.createObjectURL(file);
  }
});
</script>
