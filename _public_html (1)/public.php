<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("config.php");
mysqli_set_charset($con, "utf8mb4");

// Standard includes for session/cleanup like indexmo.php
include("includes/auto_delete.php");
include("includes/remember_me.php");

// Trigger auto-deletes if needed
if (function_exists('checkAndCleanReportedVideos')) checkAndCleanReportedVideos($con);
if (function_exists('checkAndCleanStories')) checkAndCleanStories($con);

// Fetch videos that have at least 50 likes AND 20 comments
$query = "
    SELECT v.*, u.username, u.profile_picture,
           (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id) as comment_count
    FROM videos v
    JOIN users u ON v.user_id = u.id
    WHERE v.status = 'active'
      AND v.likes >= 50 
      AND (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id) >= 20
    ORDER BY v.id DESC
";

$fetchAllVideos = mysqli_query($con, $query);

if (!$fetchAllVideos) {
    die("Error fetching videos: " . mysqli_error($con));
}

$pageTitle = "الفيديوهات العامة (Public)";

// Inline styles specific to this page (Sync with indexmo.php)
$inlineStyles = '
        body {
            padding-top: 120px !important;
            background-color: #18191a;
            color: #e4e6eb;
        }
        @media (min-width: 992px) {
            body {
                padding-top: 140px !important;
            }
        }
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding-top: 10px;
        }
        .video-scroller {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            scroll-snap-type: y mandatory;
            overflow-y: scroll;
            height: calc(100vh - 70px);
        }
        .video-item {
            scroll-snap-align: start;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px 0;
            position: relative;
        }
        .video-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
        }
        .video-player {
            width: 100%;
            height: auto;
            border-radius: 15px;
        }
        .video-footer {
            position: absolute;
            bottom: 20px;
            left: 20px;
            color: white;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 5px;
            max-width: 90%;
            pointer-events: none;
            z-index: 1002;
        }
        .video-footer * {
            pointer-events: auto;
        }
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        .action-buttons {
            position: absolute;
            right: 20px;
            bottom: 100px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 1005;
            pointer-events: auto;
        }
        .action-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            text-align: center;
            cursor: pointer;
        }
        .action-btn i {
            display: block;
            margin-bottom: 5px;
        }
        .action-btn span {
            font-size: 12px;
        }
        .liked {
            color: #FE2C55 !important;
        }
        .viewers-popup, .likes-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #242526;
            color: #e4e6eb;
            padding: 20px;
            border-radius: 10px;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 20005;
            width: 80%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .profile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.92);
            z-index: 20002;
            overflow-y: auto;
            padding: 10px;
            padding-top: 80px;
            backdrop-filter: blur(10px);
        }
        .profile-content {
            background-color: #1a1a1a;
            color: #fff;
            border-radius: 15px;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            border: 1px solid #333;
        }
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 25px;
        }
        .profile-picture-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #FE2C55;
            margin-bottom: 10px;
        }
        .profile-videos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px;
        }
        @media (max-width: 480px) {
            .profile-videos {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .profile-video {
            position: relative;
            width: 100%;
            aspect-ratio: 9/16;
            background-color: #000;
            overflow: hidden;
            border-radius: 4px;
        }
        .profile-video video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .close-profile {
            position: fixed;
            top: 20px;
            left: 20px;
            font-size: 30px;
            color: #fff;
            cursor: pointer;
            z-index: 20005;
            background: rgba(0,0,0,0.5);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .story-comments-modal {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 70%;
            background: #1a1a1a;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            z-index: 10005;
            padding: 0;
            color: white;
            flex-direction: column;
            box-shadow: 0 -5px 25px rgba(0,0,0,0.5);
        }
        .story-comments-modal.active {
            display: flex !important;
        }
        .story-comments-header {
            padding: 15px;
            border-bottom: 1px solid #333;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            position: relative;
            background: #1a1a1a;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        .story-comments-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: #1a1a1a;
        }
        .story-comments-footer {
            padding: 15px;
            border-top: 1px solid #333;
            display: flex;
            gap: 10px;
            background: #1a1a1a;
            padding-bottom: 30px; /* Space for mobile keyboards/gestures */
        }
        .story-comment-input {
            flex: 1;
            background: #333;
            border: none;
            border-radius: 20px;
            padding: 10px 15px;
            color: white;
            outline: none;
        }
        .story-comment-send {
            background: #FE2C55;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        /* Comment Item Styles */
        .story-comment-item {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        .story-comment-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #444;
        }
        .story-comment-content {
            flex: 1;
        }
        .story-comment-user {
            font-weight: bold;
            font-size: 13px;
            color: #fff;
            margin-bottom: 2px;
        }
        .story-comment-text {
            font-size: 14px;
            color: #ccc;
            line-height: 1.4;
            position: relative;
            padding-right: 25px; /* Space for delete icon */
        }
        .story-comment-delete {
            position: absolute;
            right: 0;
            top: 2px;
            color: #666;
            cursor: pointer;
            font-size: 12px;
        }
        .story-comment-delete:hover {
            color: #FE2C55;
        }
';

include("includes/header.php");
include("includes/navbar.php");
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-12">
            <div class="video-scroller" id="mainContent">
                <?php if (mysqli_num_rows($fetchAllVideos) == 0): ?>
                    <div class="text-center w-100 p-5">
                        <h3>
                             لا توجد فيديوهات عامة حالياً تستوفي الشروط </h3>
بحيث الفديوات trend لا تظهر الفيديوهات إلا إذا وصلت إلى 50 إعجاب وما فوق و 20 تعليق وما فوق في نفس الوقت.                            </div>

                <?php endif; ?>

                <?php while ($row = mysqli_fetch_assoc($fetchAllVideos)): ?>
                    <?php
                    $video_id = $row['id'];
                    $location = $row['location'];
                    $subject = $row['subject'] ?? '';
                    $views = $row['views'] ?? 0;
                    $title = $row['title'] ?? '';
                    $video_owner_id = $row['user_id'];
                    $username = $row['username'];
                    $profile_picture = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
                    $likes_count = $row['likes'] ?? 0;
                    $comment_count = $row['comment_count'];
                    ?>

                    <div class="video-item">
                        <div class="video-container">
                            <?php 
                            $isYouTube = (strpos($location, 'youtube.com') !== false || strpos($location, 'youtu.be') !== false);
                            if ($isYouTube): 
                                $ytId = "";
                                if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $location, $match)) {
                                    $ytId = $match[1];
                                }
                            ?>
                                <div class="video-player youtube-container" style="aspect-ratio: 9/16; background: #000;">
                                    <iframe src="https://www.youtube.com/embed/<?php echo $ytId; ?>?autoplay=0&controls=1&rel=0" frameborder="0" allowfullscreen style="width:100%; height:100%;"></iframe>
                                </div>
                            <?php else: ?>
                                <video src="<?php echo $location; ?>" class="video-player" data-id="<?php echo $video_id; ?>" loop muted playsinline webkit-playsinline></video>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <button class="action-btn viewers-btn" data-video-id="<?php echo $video_id; ?>">
                                    <i class="fas fa-eye"></i>
                                    <span id="views-<?php echo $video_id; ?>"><?php echo $views; ?></span>
                                </button>
                                
                                <?php
                                $liked = false;
                                if (isset($_SESSION['user_id'])) {
                                    $uid = $_SESSION['user_id'];
                                    $check_like = mysqli_query($con, "SELECT * FROM video_likes WHERE video_id = $video_id AND user_id = $uid LIMIT 1");
                                    $liked = mysqli_num_rows($check_like) > 0;
                                }
                                ?>
                                <div class="action-btn" style="cursor: default; display: flex; flex-direction: column; align-items: center;">
                                    <i class="fas fa-heart like-trigger <?php echo $liked ? 'liked' : ''; ?>" data-video-id="<?php echo $video_id; ?>" style="cursor: pointer;"></i>
                                    <span class="likes-count-trigger" data-video-id="<?php echo $video_id; ?>" id="likes-<?php echo $video_id; ?>" style="cursor: pointer; font-size: 12px; margin-top: 5px;"><?php echo $likes_count; ?></span>
                                </div>

                                <button class="action-btn" onclick="openVideoComments(<?php echo $video_id; ?>)">
                                    <i class="fas fa-comment"></i>
                                    <span id="comments-count-<?php echo $video_id; ?>"><?php echo $comment_count; ?></span>
                                </button>

                                <button class="action-btn" onclick="openShareModal(<?php echo $video_id; ?>, 'video')">
                                    <i class="fas fa-share"></i>
                                </button>
                            </div>

                            <div class="video-footer">
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?php echo $profile_picture; ?>" class="profile-img" onerror="this.src='uploads/profile.jpg'">
                                    <span style="font-weight: bold; cursor: pointer;" onclick="showProfile(<?php echo $video_owner_id; ?>, '<?php echo addslashes($username); ?>', '<?php echo addslashes($profile_picture); ?>')"><?php echo htmlspecialchars($username); ?></span>
                                </div>
                                <p style="margin-bottom: 5px; font-weight: 500; font-size: 16px;"><?php echo htmlspecialchars($title); ?></p>
                                <p style="font-size: 14px; opacity: 0.9; margin:0;"><?php echo htmlspecialchars($subject); ?></p>
                            </div>
                        </div>

                        <!-- Popups needed for custom_actions.js -->
                        <div class="viewers-popup" id="viewers-popup-<?php echo $video_id; ?>">
                            <button class="close-btn">&times;</button>
                            <h4>المشاهدون</h4>
                            <ul class="viewers-list" id="viewers-list-<?php echo $video_id; ?>">
                                <li class="text-center p-3">يمكنك رؤية المشاهدين الحقيقيين في الصفحة لمتابعيهم</li>
                            </ul>
                        </div>

                        <div class="likes-popup" id="likes-popup-<?php echo $video_id; ?>">
                            <button class="close-btn">&times;</button>
                            <h4>الإعجابات</h4>
                            <ul id="likes-list-<?php echo $video_id; ?>">
                            <?php
                            $likes_query = mysqli_query($con, "SELECT users.username, users.profile_picture FROM video_likes
                                                           JOIN users ON video_likes.user_id = users.id
                                                           WHERE video_likes.video_id = $video_id
                                                           ORDER BY video_likes.created_at DESC");
                            while ($liker = mysqli_fetch_assoc($likes_query)) {
                                $l_pic = !empty($liker['profile_picture']) ? $liker['profile_picture'] : 'uploads/profile.jpg';
                                echo '<li style="display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">';
                                echo '<img src="' . htmlspecialchars($l_pic) . '" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #FE2C55;" onerror="this.src=\'uploads/profile.jpg\'">';
                                echo '<span>' . htmlspecialchars($liker['username']) . '</span>';
                                echo '</li>';
                            }
                            if (mysqli_num_rows($likes_query) == 0) {
                                echo '<li>لا يوجد إعجابات حتى الآن</li>';
                            }
                            ?>
                        </ul>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Video Comments Modal -->
<div class="story-comments-modal" id="videoCommentsModal">
    <div class="story-comments-header">
        التعليقات
        <span onclick="toggleVideoComments()" style="position:absolute; right:15px; top:15px; cursor:pointer; font-size: 24px;">&times;</span>
    </div>
    <div class="story-comments-list" id="videoCommentsList"></div>
    <div class="story-comments-footer">
        <input type="text" id="videoCommentInput" class="story-comment-input" placeholder="أضف تعليقاً...">
        <button class="story-comment-send" onclick="postVideoComment()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Profile Overlay -->
<div class="profile-overlay" id="profileOverlay">
    <span class="close-profile" onclick="closeProfile()">&times;</span>
    <div class="profile-content" id="profileContent"></div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true" style="z-index: 20002;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-dark rounded-4" style="background: white;">
            <div class="modal-header">
                <h5 class="modal-title">مشاركة مع الأصدقاء</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="friends-share-list" class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                    <div class="text-center p-3 text-muted">جاري التحميل...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
</script>

<?php 
$additionalJs = ['js/custom_actions.js?v=' . time()];
include("includes/footer.php"); 
?>
