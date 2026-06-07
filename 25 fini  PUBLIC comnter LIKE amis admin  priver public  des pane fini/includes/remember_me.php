<?php
// includes/remember_me.php

// Ensure database connection is available
if (isset($con) && !isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    
    // Clean token
    $clean_token = mysqli_real_escape_string($con, $token);
    
    // Find user with this token
    $query = "SELECT * FROM users WHERE remember_token = '$clean_token' LIMIT 1";
    $result = mysqli_query($con, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Log the user in
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
    }
}
?>
