<?php
require_once 'includes/db_config.php';

echo "<h2>NextGenBank: Mock Login Test</h2>";

$test_user = 'ali_bhatti';
$test_pass = '123';

echo "Attempting to login with:<br>";
echo "Username: <b>$test_user</b><br>";
echo "Password: <b>$test_pass</b><br><hr>";

// 1. Database Check
if (!$conn) {
    echo "<p style='color:red'>❌ Error: Database connection failed.</p>";
    exit;
}
echo "<p style='color:green'>✅ Database connection OK.</p>";

// 2. Fetch User
$username_esc = mysqli_real_escape_string($conn, $test_user);
$query = "SELECT * FROM users WHERE username = '$username_esc'";
echo "Running Query: <code>$query</code><br>";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "<p style='color:green'>✅ User found in database.</p>";
    echo "Full Name: " . $user['full_name'] . "<br>";
    echo "Hashed PW in DB: <code>" . $user['password_hash'] . "</code><br>";
    echo "Is Active: " . $user['is_active'] . "<br>";

    // 3. Password Verification
    echo "<br>Testing <code>password_verify('$test_pass', 'HashFromDB')</code>...<br>";
    if (password_verify($test_pass, $user['password_hash'])) {
        echo "<h3 style='color:green'>✅ SUCCESS: Password is correct!</h3>";
        echo "If this works here but NOT on the login page, then the problem is in your <b>login.html</b> fetch call or the <b>backend/login_process.php</b> pathing.";
    } else {
        echo "<h3 style='color:red'>❌ FAILURE: Password does NOT match the hash.</h3>";
        echo "This means the hash in the database was created with a different password, or there are hidden characters.";

        // Let's generate what the hash SHOULD be
        $new_hash = password_hash($test_pass, PASSWORD_BCRYPT);
        echo "<p>Your server generates this hash for '123':<br><code>$new_hash</code></p>";
        echo "<p>Run this SQL to fix it:<br><code>UPDATE users SET password_hash = '$new_hash' WHERE username = '$test_user';</code></p>";
    }
} else {
    echo "<p style='color:red'>❌ User '$test_user' NOT FOUND in database.</p>";
}
?>