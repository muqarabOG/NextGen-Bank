<?php
// Database configuration for NextGenBank
// Database configuration for NextGenBank
// Checks for Environment Variables first (Railway/Cloud) -> Fallback to Localhost (XAMPP)
$db_host = getenv('MYSQLHOST') ?: 'localhost';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: 'nextgenbank';
$db_port = getenv('MYSQLPORT') ?: 3306;

// Force port into connection if not localhost
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

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