<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Connection Test</h1>";

// Test MySQLi (config.php style)
echo "<h2>Testing MySQLi...</h2>";
$servername = "sql308.infinityfree.com";
$username = "if0_40097384";
$password = "1ThXLmVD9G9ZLGH";
$dbname = "if0_40097384_tik";

$con = new mysqli($servername, $username, $password, $dbname);

if ($con->connect_error) {
    echo "<p style='color:red'>MySQLi Connection failed: " . $con->connect_error . "</p>";
} else {
    echo "<p style='color:green'>MySQLi Connected successfully</p>";
    $con->close();
}

// Test PDO (conn.php style)
echo "<h2>Testing PDO...</h2>";
try {
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>PDO Connected successfully</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>PDO Connection failed: " . $e->getMessage() . "</p>";
}
?>
