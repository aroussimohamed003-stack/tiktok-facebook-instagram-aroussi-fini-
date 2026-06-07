<?php
session_start();
include("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
            if ($out = fopen($finalPath, "wb")) {
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
                $stmt = $conn->prepare("INSERT INTO videos (location, subject, title, user_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $finalPath, $subject, $title, $user_id);
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
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Videos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="css/theme.css" rel="stylesheet"> <!-- Essential for Navbar Styles -->
  <style>
    :root {
        --primary-color: #00f2ea;
        --secondary-color: #ff0050;
        --bg-color: #000000;
        --card-bg: #121212;
        --text-color: #ffffff;
        --input-bg: #2a2a2a;
        --border-color: #333;
    }

    body { 
        background-color: var(--bg-color); 
        color: var(--text-color);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .navbar-custom {
        background-color: rgba(18, 18, 18, 0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .navbar-custom .navbar-brand, 
    .navbar-custom .nav-link {
        color: #ffffff !important;
    }

    .navbar-custom .nav-link:hover {
        color: var(--primary-color) !important;
    }
    
    .navbar-toggler {
        filter: invert(1);
    }

    .main-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 90vh; /* Adjust for navbar */
        padding-top: 80px; /* Space for fixed navbar */
        padding-bottom: 40px;
    }

    .upload-card { 
        background: var(--card-bg); 
        padding: 30px; 
        border-radius: 20px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
        width: 100%;
        max-width: 500px;
        border: 1px solid var(--border-color);
    }

    .upload-card h2 {
        font-weight: 700;
        background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 30px;
    }

    .form-label {
        color: #ccc;
        font-weight: 500;
    }

    .form-control {
        background-color: var(--input-bg);
        border: 1px solid var(--border-color);
        color: white;
        border-radius: 10px;
        padding: 12px;
    }

    .form-control:focus {
        background-color: var(--input-bg);
        color: white;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(0, 242, 234, 0.25);
    }
    
    .form-control::placeholder {
        color: #777;
    }

    .upload-icon-wrapper {
        text-align: center;
        margin-bottom: 20px;
        border: 2px dashed var(--border-color);
        border-radius: 15px;
        padding: 30px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .upload-icon-wrapper:hover {
        border-color: var(--primary-color);
        background-color: rgba(255,255,255,0.05);
    }

    .upload-icon {
        font-size: 50px;
        color: var(--primary-color);
        margin-bottom: 10px;
    }

    .btn-upload {
        background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        border: none;
        padding: 12px;
        border-radius: 30px;
        font-weight: bold;
        color: white;
        font-size: 18px;
        transition: transform 0.2s;
    }

    .btn-upload:hover {
        transform: scale(1.02);
        color: white;
    }

    .btn-upload:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .progress { 
        height: 10px; 
        margin-top: 15px; 
        background-color: #333;
        border-radius: 5px;
    }
    
    .progress-bar {
        background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
    }

    #loading { display: none; }
    
    .alert {
        border-radius: 10px;
        background-color: rgba(255,255,255,0.1);
        color: white;
        border: none;
    }
    .alert-success { border-right: 4px solid var(--primary-color); }
    .alert-danger { border-right: 4px solid var(--secondary-color); }

    /* Custom File Input Styling */
    input[type="file"] {
        display: none;
    }
  </style>
</head>
<body>

<?php include("includes/navbar.php"); ?>

<div class="main-container">
  <div class="upload-card">
    <h2 class="text-center">رفع فيديو جديد</h2>
    
    <form id="uploadForm">
      
      <!-- Custom File Upload Area -->
      <div class="mb-4">
          <label for="fileInput" class="upload-icon-wrapper d-block">
              <i class="fas fa-cloud-upload-alt upload-icon"></i>
              <p class="mb-0 text-muted">اضغط لاختيار ملف (فيديو عمودي)</p>
              <div id="fileNameDisplay" class="mt-2 text-primary" style="font-size: 14px;"></div>
          </label>
          <input type="file" id="fileInput" accept="video/*" required>
      </div>

      <div class="mb-3">
        <label for="subject" class="form-label">العنوان</label>
        <input type="text" class="form-control" id="subject" placeholder="أدخل عنواناً جذاباً..." required>
      </div>
      
      <div class="mb-4">
        <label for="title" class="form-label">الوصف</label>
        <input type="text" class="form-control" id="title" placeholder="أدخل وصفاً قصيراً..." required>
      </div>
      
      <button type="button" id="uploadBtn" class="btn btn-primary w-100 btn-upload">
         <i class="fas fa-arrow-up me-2"></i> رفع الفيديو
      </button>

      <div id="loading" class="text-center mt-3">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">جاري التحميل...</span>
        </div>
        <p class="mt-2 text-muted" style="font-size: 14px;">يتم رفع الفيديو، يرجى الانتظار...</p>
      </div>

      <div class="progress mt-3">
        <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
      </div>
      
      <div id="message" class="alert mt-3" style="display: none;"></div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const uploadBtn = document.getElementById("uploadBtn");
  const fileInput = document.getElementById("fileInput");
  const fileNameDisplay = document.getElementById("fileNameDisplay");
  const progressBar = document.getElementById("progressBar");
  const messageDiv = document.getElementById("message");
  const loadingDiv = document.getElementById("loading");
  const chunkSize = 1 * 1024 * 1024; // 1MB chunks
  
  // التحقق من اتجاه الفيديو وتحديث اسم الملف
  fileInput.addEventListener('change', function() {
    const file = fileInput.files[0];
    if (!file) {
        fileNameDisplay.textContent = "";
        return;
    }

    fileNameDisplay.textContent = file.name;
    
    const video = document.createElement('video');
    video.preload = 'metadata';
    
    video.onloadedmetadata = function() {
      window.URL.revokeObjectURL(video.src);
      // Enforce Vertical Video (Block Horizontal)
      if (video.videoWidth > video.videoHeight) {
        showMessage('Cannot upload video to horizontal', 'danger');
        fileInput.value = ''; 
        fileNameDisplay.textContent = "";
      } else {
          messageDiv.style.display = 'none'; // Clear warnings
      }
    };
    
    video.onerror = function() {
      showMessage('تعذر قراءة ملف الفيديو.', 'danger');
      fileInput.value = '';
      fileNameDisplay.textContent = "";
    };
    
    video.src = URL.createObjectURL(file);
  });
  
  uploadBtn.addEventListener("click", function() {
    const file = fileInput.files[0];
    const subject = document.getElementById("subject").value;
    const title = document.getElementById("title").value;
    
    if (!file) {
      showMessage('الرجاء اختيار ملف فيديو', 'danger');
      return;
    }
    
    if (!subject || !title) {
      showMessage('الرجاء إدخال عنوان ووصف الفيديو', 'danger');
      return;
    }
    
    loadingDiv.style.display = "block";
    uploadBtn.disabled = true;
    messageDiv.style.display = "none";
    
    const fileId = Date.now() + "_" + file.name.replace(/\s+/g, '_');
    let chunkIndex = 0;
    const totalChunks = Math.ceil(file.size / chunkSize);
    
    function uploadChunk(start) {
      const end = Math.min(start + chunkSize, file.size);
      const chunk = file.slice(start, end);
      
      const formData = new FormData();
      formData.append("fileId", fileId);
      formData.append("chunkIndex", chunkIndex);
      formData.append("totalChunks", totalChunks);
      formData.append("fileName", file.name);
      formData.append("subject", subject);
      formData.append("title", title);
      formData.append("chunk", chunk);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(text => {
        progressBar.style.width = Math.min(100, Math.floor((end / file.size) * 100)) + "%";
        
        if (end < file.size) {
          chunkIndex++;
          uploadChunk(end);
        } else {
          showMessage('تم رفع الفيديو بنجاح!', 'success');
          loadingDiv.style.display = "none";
          uploadBtn.disabled = false;
          progressBar.style.width = "0%";
          setTimeout(() => window.location.href = 'indexmo.php', 2000); // Redirect to home
        }
      })
      .catch(error => {
        showMessage('حدث خطأ أثناء الرفع: ' + error.message, 'danger');
        loadingDiv.style.display = "none";
        uploadBtn.disabled = false;
      });
    }
    
    uploadChunk(0);
  });
  
  function showMessage(text, type) {
    messageDiv.textContent = text;
    messageDiv.className = `alert alert-${type}`;
    messageDiv.style.display = "block";
  }
});
</script>

</body>
</html>