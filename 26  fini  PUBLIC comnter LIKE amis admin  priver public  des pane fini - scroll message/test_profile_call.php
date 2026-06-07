<?php
// Simulate GET request
$_GET['user_id'] = 1;
ob_start();
include 'get_profile.php';
$output1 = ob_get_clean();

$_GET['user_id'] = 5;
ob_start();
include 'get_profile.php';
$output5 = ob_get_clean();

echo "--- User 1 Response ---\n";
echo $output1 . "\n";
echo "--- User 5 Response ---\n";
echo $output5 . "\n";
?>
