<?php
// Detect environment
$is_local = false;
if (stripos(__FILE__, 'xampp') !== false || 
    stripos(__FILE__, 'Users') !== false ||
    stripos(__FILE__, 'aroussi') !== false ||
    $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || 
    $_SERVER['REMOTE_ADDR'] == '::1' || 
    stripos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    $is_local = true;
}

if ($is_local) {
    $username = "root";
    $password = "";
    $host = "127.0.0.1";
    $dbname = "tutoreal";
} else {
    $username = "if0_40097384";
    $password = "1ThXLmVD9G9ZLGH";
    $host = "sql308.infinityfree.com";
    $dbname = "if0_40097384_tik";
}

try {
    $database = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set the PDO error mode to exception
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    // Use a flag or die gracefully instead of crashing
    $database = null;
}
?>