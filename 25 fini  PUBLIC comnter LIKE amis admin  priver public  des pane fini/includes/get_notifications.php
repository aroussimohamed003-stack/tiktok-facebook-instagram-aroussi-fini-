<?php
// Suppress errors to prevent breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
ob_start(); // Start buffering

// Tell config.php not to die on error, so we can return JSON
$suppress_db_die = true;
include "config.php";
ob_clean(); // Clean anything config.php might have outputted

header('Content-Type: application/json');

// Check for DB connection error explicitly
if ($con->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $con->connect_error]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = $con;

// Mark as read
if (isset($_POST['mark_read'])) {
    $conn->query("UPDATE notifications SET is_read = TRUE WHERE recipient_id = $user_id AND is_read = FALSE");
    echo json_encode(['success' => true]);
    exit();
}

// Delete specific notification
if (isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $conn->query("DELETE FROM notifications WHERE id = $del_id AND recipient_id = $user_id");
    echo json_encode(['success' => true]);
    exit();
}

// Fetch notifications
// Limit to last 10
$sql = "SELECT n.*, u.username, u.profile_picture 
        FROM notifications n
        JOIN users u ON n.sender_id = u.id
        WHERE n.recipient_id = $user_id
        ORDER BY n.created_at DESC LIMIT 10";

$result = $conn->query($sql);

$notifications = [];
$unseen_count = 0;

// Count unseen
$count_res = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = $user_id AND is_read = FALSE");
if ($count_res) {
    $unseen_count = $count_res->fetch_assoc()['count'];
}

while ($row = $result->fetch_assoc()) {
    $row['profile_picture'] = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
    
    // Format message
    $msg = "";
    $is_self = ($row['sender_id'] == $row['recipient_id']);
    
    if ($row['type'] == 'like') {
        if(!empty($row['video_id'])) {
             $msg = $is_self ? "liked your own video." : "liked your video.";
        } else {
             $msg = $is_self ? "liked your own post." : "liked your post.";
        }
    } elseif ($row['type'] == 'comment') {
        if(!empty($row['video_id'])) {
             $msg = $is_self ? "commented on your own video." : "commented on your video.";
        } else {
             $msg = $is_self ? "commented on your own post." : "commented on your post.";
        }
    } elseif ($row['type'] == 'message') {
        $msg = "sent you a message.";
    }
    
    // If self, username is 'You' (optional, but let's keep username for clarity or change it)
    // Actually keep username, e.g. "Ahmed liked your own post" -> "Ahmed liked his own post"? 
    // Wait, if sender=recipient, it means "Ahmed liked Ahmed's post".
    // So "Ahmed liked your own video". That sounds readable if "your" refers to the logged in user (recipient).
    // Yes.
    
    $row['message'] = $msg;
    $row['time_ago'] = time_elapsed_string($row['created_at']);
    $notifications[] = $row;
}

echo json_encode([
    'unseen_count' => $unseen_count,
    'notifications' => $notifications
]);

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);
    
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    // Create a custom array or object to hold the values to avoid modifying $diff
    $values = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    foreach ($string as $k => &$v) {
        if ($values[$k]) {
            $v = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
