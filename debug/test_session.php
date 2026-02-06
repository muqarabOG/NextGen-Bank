<?php
session_start();
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;

echo "<h2>Session Persistence Test</h2>";
echo "<p>Counter: <b>" . $_SESSION['test_counter'] . "</b></p>";
echo "<p>Instruction: Refresh this page. If the number doesn't go up, your XAMPP session configuration is broken.</p>";
echo "<hr>";
echo "<p>Session ID: " . session_id() . "</p>";
?>