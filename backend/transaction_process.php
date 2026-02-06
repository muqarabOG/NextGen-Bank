<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;

// Get primary account
$acc_query = "SELECT account_id FROM accounts WHERE user_id = $user_id LIMIT 1";
$acc_res = mysqli_query($conn, $acc_query);
$acc_row = mysqli_fetch_assoc($acc_res);
$account_id = $acc_row ? $acc_row['account_id'] : 0;

$query = "SELECT t.*, tt.type_name, tt.category 
          FROM transactions t 
          JOIN transaction_types tt ON t.transaction_type_id = tt.type_id 
          WHERE (t.from_account_id = $account_id OR t.to_account_id = $account_id)";

if ($start_date && $end_date) {
    if ($start_date === $end_date) {
        $query .= " AND DATE(t.transaction_date) = '$start_date'";
    } else {
        $query .= " AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'";
    }
}

$query .= " ORDER BY t.transaction_date DESC";

$res = mysqli_query($conn, $query);
$transactions = [];

while ($row = mysqli_fetch_assoc($res)) {
    $transactions[] = $row;
}

echo json_encode(['success' => true, 'transactions' => $transactions, 'my_account_id' => $account_id]);
?>