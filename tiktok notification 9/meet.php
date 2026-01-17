<?php
session_start();
require_once 'conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
// Default room for 'chatlive'
$room = "LiveChat";

// specific room can be passed via GET
if (isset($_GET['room']) && !empty($_GET['room'])) {
    $room = htmlspecialchars($_GET['room']);
}

// Redirect to the meet application
header("Location: meet/meet.php?room=" . urlencode($room) . "&username=" . urlencode($username));
exit();
?>
