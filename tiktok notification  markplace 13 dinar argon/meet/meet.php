<?php
session_start();
// Include connection if needed for other things, but session is key
require_once '../conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$room = isset($_GET['room']) ? htmlspecialchars($_GET['room']) : 'LiveChat';
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatLive - <?php echo $room; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- PeerJS CDN -->
    <script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>
</head>
<body>

<div class="meet-container">
    <div id="join-screen" class="join-screen">
        <button onclick="startMeeting()" class="join-btn">Join Meeting / انضمام للاجتماع</button>
    </div>
    <div id="error-log" style="position:fixed; top:0; left:0; background:rgba(255,0,0,0.8); color:white; padding:10px; z-index:9999; max-width: 100%; display:none;"></div>
    <div class="video-grid" id="video-grid">
        <!-- Videos will be added here dynamically -->
    </div>

    <div class="controls-bar">
        <button class="control-btn" id="audio-btn" onclick="toggleAudio()" title="كتم الصوت">
            <i class="fas fa-microphone"></i>
        </button>
        <button class="control-btn" id="video-btn" onclick="toggleVideo()" title="إيقاف الفيديو">
            <i class="fas fa-video"></i>
        </button>
        <button class="control-btn" id="screen-btn" onclick="startScreenShare()" title="مشاركة الشاشة">
            <i class="fas fa-desktop"></i>
        </button>
        <button class="control-btn" id="record-btn" onclick="toggleRecord()" title="تسجيل">
            <i class="fas fa-record-vinyl"></i>
        </button>
        <button class="control-btn danger" onclick="window.location.href='../indexmo.php'" title="خروج">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
