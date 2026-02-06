<?php
require_once 'includes/db_config.php';

echo "<h2>NextGenBank: Full User Table Dump</h2>";

$query = "SELECT user_id, username, full_name, user_type, is_active, password_hash FROM users";
$result = mysqli_query($conn, $query);

if ($result) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width:100%;'>";
    echo "<tr style='background:#eee;'><th>ID</th><th>Username</th><th>Full Name</th><th>Type</th><th>Active</th><th>Hash (First 15 chars)</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['full_name'] . "</td>";
        echo "<td>" . $row['user_type'] . "</td>";
        echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td><code>" . substr($row['password_hash'], 0, 15) . "...</code></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Query Error: " . mysqli_error($conn);
}
?>