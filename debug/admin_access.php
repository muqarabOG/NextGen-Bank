<?php
/**
 * NextGenBank One-Click Admin Access
 * USE THIS ONLY FOR TESTING
 */
require_once 'includes/db_config.php';

// 1. Ensure the user exists with correct data
$username = 'ali_bhatti';
$password = '123';
$hash = password_hash($password, PASSWORD_BCRYPT);

$check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
if (mysqli_num_rows($check) == 0) {
    // Create the user if missing
    mysqli_query($conn, "INSERT INTO users (cnic, username, password_hash, full_name, date_of_birth, address, contact_number, email, gender, user_type, is_active) 
                        VALUES ('4210199999999', '$username', '$hash', 'Ali Bhatti', '1998-01-01', 'HQ', '03001234567', 'ali@nextgen.com', 'Male', 'Customer', 1)");
} else {
    // Update hash just in case
    mysqli_query($conn, "UPDATE users SET password_hash = '$hash', is_active = 1 WHERE username = '$username'");
}

// 2. Fetch the user data again
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
$user = mysqli_fetch_assoc($user_res);

// 3. Set Sessions manually (Bypass Login Form)
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['full_name'] = $user['full_name'];

// 4. Redirect to Dashboard
header("Location: dashboard.php");
exit;
?>