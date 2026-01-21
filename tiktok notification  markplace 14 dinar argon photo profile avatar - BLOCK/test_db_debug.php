<?php
include 'config.php';

echo "Script Path: " . __FILE__ . "\n";
echo "Detected Local: " . ($is_local ? 'YES' : 'NO') . "\n";
echo "Servername: " . $servername . "\n";
echo "DB Name: " . $dbname . "\n";

if ($con) {
    echo "Connection Successful!\n";
} else {
    echo "Connection Failed (Variable \$con not set)\n";
}
?>
