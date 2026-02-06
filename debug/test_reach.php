<?php
echo "<h1>Backend is Reachable</h1>";
echo "<p>Current Time: " . date("Y-m-d H:i:s") . "</p>";
require_once 'includes/db_config.php';
if ($conn) {
    echo "<p style='color:green'>✅ Database Connection OK</p>";
} else {
    echo "<p style='color:red'>❌ Database Connection Failed</p>";
}
?>