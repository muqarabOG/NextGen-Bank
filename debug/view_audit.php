<?php
require_once 'includes/db_config.php';

echo "<h2>NextGenBank Login Audit Log</h2>";

$query = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 20";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width:100%;'>";
    echo "<tr style='background:#eee;'><th>Time</th><th>User ID</th><th>Action</th><th>Details</th><th>IP</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . ($row['user_id'] ?? 'N/A') . "</td>";
        echo "<td>" . $row['action_type'] . "</td>";
        echo "<td><pre>" . $row['new_values'] . "</pre></td>";
        echo "<td>" . $row['ip_address'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No audit logs found yet.</p>";
}

echo "<p><a href='login.html'>Back to Login</a></p>";
?>