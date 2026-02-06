<?php
require_once 'includes/db_config.php';

echo "<h2>NextGenBank Emergency Access Tool</h2>";

// 1. Handle Logout/Direct Login Action
if (isset($_GET['action']) && $_GET['action'] === 'bypass') {
    $username = 'ali_bhatti';
    $res = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' LIMIT 1");
    if ($user = mysqli_fetch_assoc($res)) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];
        header("Location: dashboard.php");
        exit;
    } else {
        echo "<p style='color:red'>❌ Error: Could not find user '$username' to bypass.</p>";
    }
}

// 2. Normal Diagnostic and Fix
function report($msg, $success = true)
{
    $color = $success ? "green" : "red";
    echo "<p style='color:$color'>" . ($success ? "✅ " : "❌ ") . $msg . "</p>";
}

echo "<h3>1. Table Integrity Check</h3>";
$tables_needed = ['users', 'accounts', 'audit_logs', 'user_sessions', 'transactions'];
foreach ($tables_needed as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($check) > 0) {
        report("Table '$table' is present.");
    } else {
        report("CRITICAL: Table '$table' is missing!", false);
    }
}

echo "<h3>2. Account Repair (User: ali_bhatti)</h3>";
$username = 'ali_bhatti';
$password = '123';
$hash = '123'; // Plain text for simplicity

// Clean delete to ensure a fresh, active user
mysqli_query($conn, "DELETE FROM users WHERE username = '$username'");

$sql = "INSERT INTO users (cnic, username, password_hash, full_name, father_name, date_of_birth, address, contact_number, email, gender, user_type, is_active) 
        VALUES ('4210199999999', '$username', '$hash', 'Ali Bhatti', 'N/A', '1998-01-01', 'HQ', '03001234567', 'ali@test.com', 'Male', 'Customer', 1)";

if (mysqli_query($conn, $sql)) {
    $user_id = mysqli_insert_id($conn);
    report("User '$username' has been reset. New Password: <b>$password</b>");

    // Check for Account
    mysqli_query($conn, "DELETE FROM accounts WHERE user_id = $user_id");
    mysqli_query($conn, "INSERT INTO accounts (account_number, user_id, type_id, current_balance, available_balance, opening_date, status) 
                         VALUES ('NGB0000001', $user_id, 1, 50000.00, 50000.00, CURDATE(), 'Active')");
    report("Account NGB0000001 created/linked with $50,000 balance.");
} else {
    report("Failed to repair user: " . mysqli_error($conn), false);
}

echo "<hr>";
echo "<h3>3. Access Strategy</h3>";
echo "<ul>";
echo "<li><b>Strategy A:</b> Try logging in at <a href='login.html'>login.html</a> with <b>$username</b> / <b>$password</b></li>";
echo "<li><b>Strategy B (Bypass):</b> If Strategy A still fails, click here: <a href='fix_login.php?action=bypass' style='padding:10px 20px; background: #FFD700; color: black; border-radius: 5px; font-weight: bold; text-decoration: none;'>🚀 FORCE LOGIN AS ALI BHATTI</a></li>";
echo "</ul>";

echo "<p style='margin-top:20px; font-size: 11px; color: #666;'>Note: Use Strategy B only if you want to test the dashboard immediately. Strategy A is the real test for the login system.</p>";
?>