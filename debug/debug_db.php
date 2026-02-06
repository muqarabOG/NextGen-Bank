<?php
require_once 'includes/db_config.php';

echo "<h2>NextGenBank Diagnostic Tool</h2>";

// 1. Check Connection
if ($conn) {
    echo "<p style='color:green'>✅ Database Connected Successfully: " . $db_name . "</p>";
} else {
    echo "<p style='color:red'>❌ Connection Failed</p>";
    exit;
}

// 2. Check User ali_bhatti
$username = 'ali_bhatti';
$query = "SELECT * FROM users WHERE username = '$username'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "<h3>User Audit: $username</h3>";
    echo "<ul>";
    echo "<li><b>Full Name:</b> " . $user['full_name'] . "</li>";
    echo "<li><b>Status:</b> " . ($user['is_active'] ? 'Active' : 'Inactive') . "</li>";
    echo "<li><b>Password Hash:</b> <pre>" . $user['password_hash'] . "</pre></li>";
    echo "</ul>";

    // 3. Test Verify
    $test_pass = '123';
    if (password_verify($test_pass, $user['password_hash'])) {
        echo "<p style='color:green'>✅ Password '123' matches stored hash.</p>";
    } else {
        echo "<p style='color:red'>❌ Password '123' DOES NOT match stored hash.</p>";

        // Let's generate a fresh hash for them to see
        echo "<p>Try running this specific SQL to fix it precisely:</p>";
        echo "<pre>UPDATE users SET password_hash = '" . password_hash($test_pass, PASSWORD_BCRYPT) . "' WHERE username = '$username';</pre>";
    }
} else {
    echo "<p style='color:red'>❌ User '$username' not found in database.</p>";
}
?>