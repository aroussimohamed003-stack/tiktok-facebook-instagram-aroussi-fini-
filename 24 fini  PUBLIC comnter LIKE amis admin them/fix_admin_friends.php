<?php
// fix_admin_friends.php
// Visit this file in your browser: http://localhost/.../fix_admin_friends.php

include("config.php");

// Turn on error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "<h1>Admin Sponsor Setup</h1>";

try {
    // 1. Create or Get Admin User
    $admin_username = 'admin sponsor';
    $admin_password = 'mohamed123'; 

    echo "<p>Checking for user: <strong>$admin_username</strong>...</p>";

    // Check if user exists
    $check_admin = mysqli_query($con, "SELECT id FROM users WHERE username = '$admin_username'");

    if (mysqli_num_rows($check_admin) > 0) {
        $row = mysqli_fetch_assoc($check_admin);
        $admin_id = $row['id'];
        echo "<div style='color: green;'>Found existing Admin user. ID: " . $admin_id . "</div>";
    } else {
        // Create user if not exists
        $insert_admin = "INSERT INTO users (username, password) VALUES ('$admin_username', '$admin_password')";
        if (mysqli_query($con, $insert_admin)) {
            $admin_id = mysqli_insert_id($con);
            echo "<div style='color: green;'>Created NEW Admin user. ID: " . $admin_id . "</div>";
        }
    }

    // 2. Make Admin friend with ALL existing users
    echo "<h3>Checking Friendships...</h3>";
    
    // Retrieve all users except admin
    $all_users_query = mysqli_query($con, "SELECT id, username FROM users WHERE id != $admin_id");
    
    if (!$all_users_query) {
        throw new Exception("Error fetching users: " . mysqli_error($con));
    }

    $count_added = 0;
    $count_already = 0;
    $total_users = 0;

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Status</th></tr>";

    while ($user = mysqli_fetch_assoc($all_users_query)) {
        $user_id = $user['id'];
        $user_name = htmlspecialchars($user['username']);
        $total_users++;
        
        // Check if friendship already exists
        $check_friendship = mysqli_query($con, "SELECT * FROM friends 
                                                WHERE (sender_id = $admin_id AND receiver_id = $user_id) 
                                                   OR (sender_id = $user_id AND receiver_id = $admin_id)");
                                                   
        if (mysqli_num_rows($check_friendship) == 0) {
            // Create friendship (accepted)
            // We'll make admin the sender
            $insert_friendship = "INSERT INTO friends (sender_id, receiver_id, status) VALUES ($admin_id, $user_id, 'accepted')";
            if (mysqli_query($con, $insert_friendship)) {
                $count_added++;
                echo "<tr><td>$user_id</td><td>$user_name</td><td style='color:green'>Added Friend</td></tr>";
            } else {
                echo "<tr><td>$user_id</td><td>$user_name</td><td style='color:red'>Error Adding</td></tr>";
            }
        } else {
            $count_already++;
            // Uncomment to see all users
            // echo "<tr><td>$user_id</td><td>$user_name</td><td style='color:gray'>Already Friend</td></tr>";
        }
    }
    echo "</table>";

    echo "<h3>Summary</h3>";
    echo "<ul>";
    echo "<li>Total other users found: $total_users</li>";
    echo "<li>Already friends with: $count_already</li>";
    echo "<li>Newly added friends: <strong>$count_added</strong></li>";
    echo "</ul>";
    
    echo "<p style='font-size: 1.2em; color: blue;'>Done! 'admin sponsor' is now friends with everyone.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
