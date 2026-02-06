<?php
// Database configuration for NextGenBank
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'nextgenbank1'; // Standardized database name

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 for full emoji/special character support
mysqli_set_charset($conn, "utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
