<?php
session_start();
include("config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit();
}

$my_id = $_SESSION['user_id'];
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$user_id || !$action) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

if ($action == 'add') {
    // Check if already exists
    $check = mysqli_query($con, "SELECT * FROM friends WHERE (sender_id = $my_id AND receiver_id = $user_id) OR (sender_id = $user_id AND receiver_id = $my_id)");
    if (mysqli_num_rows($check) == 0) {
        $stmt = $con->prepare("INSERT INTO friends (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $my_id, $user_id);
        if ($stmt->execute()) {
            // Add notification
            mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type) VALUES ($user_id, $my_id, 'friend_request')");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Request already exists or friendship established']);
    }
} elseif ($action == 'accept') {
    $stmt = $con->prepare("UPDATE friends SET status = 'accepted' WHERE sender_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $user_id, $my_id);
    if ($stmt->execute()) {
         // Notify the sender that the request was accepted
         mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type) VALUES ($user_id, $my_id, 'friend_accepted')");
         echo json_encode(['success' => true]);
    } else {
         echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} elseif ($action == 'share') {
    $item_id = intval($_POST['item_id']);
    $item_type = $_POST['item_type']; // 'video' or 'post'
    
    // Get absolute base URL if possible, or just relative
    $link = "";
    if ($item_type == 'video') {
        // Fetch video location to send actual video content
        $v_query = mysqli_query($con, "SELECT location FROM videos WHERE id = $item_id");
        if ($v_row = mysqli_fetch_assoc($v_query)) {
            $video_path = $v_row['location'];
            $share_message = "[[VIDEO]]" . $video_path;
        } else {
            // Fallback
             $link = "indexmo.php?video_id=" . $item_id;
             $share_message = "Check out this video: " . $link;
        }
    } else {
        $link = "mo.php#post-" . $item_id;
        $share_message = "Check out this post: " . $link;
    }
    
    $stmt = $con->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $my_id, $user_id, $share_message);
    if ($stmt->execute()) {
        $msg_id = $stmt->insert_id;
        mysqli_query($con, "INSERT INTO notifications (recipient_id, sender_id, type, message_id) VALUES ($user_id, $my_id, 'message', $msg_id)");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} elseif ($action == 'unfriend') {
    mysqli_query($con, "DELETE FROM friends WHERE (sender_id = $my_id AND receiver_id = $user_id) OR (sender_id = $user_id AND receiver_id = $my_id)");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
