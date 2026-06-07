<?php
// setup_morchedsat.php
include("config.php");

// Turn on error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "<h1>Morchedsat Setup</h1>";

try {
    // 1. Create or Get Morchedsat User
    $new_username = 'morchedsat';
    $new_password = '100200300'; 

    echo "<p>Checking for user: <strong>$new_username</strong>...</p>";

    // Check if user exists
    $check_user = mysqli_query($con, "SELECT id FROM users WHERE username = '$new_username'");

    if (mysqli_num_rows($check_user) > 0) {
        $row = mysqli_fetch_assoc($check_user);
        $morchedsat_id = $row['id'];
        echo "<div style='color: green;'>Found existing user. ID: " . $morchedsat_id . ". Updating password...</div>";
        mysqli_query($con, "UPDATE users SET password = '$new_password' WHERE id = $morchedsat_id");
    } else {
        // Create user if not exists
        $insert_user = "INSERT INTO users (username, password) VALUES ('$new_username', '$new_password')";
        if (mysqli_query($con, $insert_user)) {
            $morchedsat_id = mysqli_insert_id($con);
            echo "<div style='color: green;'>Created NEW user. ID: " . $morchedsat_id . "</div>";
        }
    }

    // 2. Make Morchedsat friend with ALL existing users
    echo "<h3>Checking Friendships...</h3>";
    
    // Retrieve all users except morchedsat
    $all_users_query = mysqli_query($con, "SELECT id, username FROM users WHERE id != $morchedsat_id");
    
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
                                                 WHERE (sender_id = $morchedsat_id AND receiver_id = $user_id) 
                                                    OR (sender_id = $user_id AND receiver_id = $morchedsat_id)");
                                                   
        if (mysqli_num_rows($check_friendship) == 0) {
            // Create friendship (accepted)
            $insert_friendship = "INSERT INTO friends (sender_id, receiver_id, status) VALUES ($morchedsat_id, $user_id, 'accepted')";
            if (mysqli_query($con, $insert_friendship)) {
                $count_added++;
                echo "<tr><td>$user_id</td><td>$user_name</td><td style='color:green'>Added Friend</td></tr>";
            } else {
                echo "<tr><td>$user_id</td><td>$user_name</td><td style='color:red'>Error Adding</td></tr>";
            }
        } else {
            $count_already++;
        }
    }
    echo "</table>";

    echo "<h3>Summary</h3>";
    echo "<ul>";
    echo "<li>Total other users found: $total_users</li>";
    echo "<li>Already friends with: $count_already</li>";
    echo "<li>Newly added friends: <strong>$count_added</strong></li>";
    echo "</ul>";
    
    echo "<p style='font-size: 1.2em; color: blue;'>Done! 'morchedsat' is now friends with everyone and password is set to $new_password.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

