<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    $response['error'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $type = $_POST['type'];
    $provider = $_POST['provider'];
    $ref_id = $_POST['ref'];

    // 1. Check Balance
    $acc_q = mysqli_query($conn, "SELECT account_id, available_balance FROM accounts WHERE user_id = $user_id LIMIT 1");
    $acc = mysqli_fetch_assoc($acc_q);

    if (!$acc || $acc['available_balance'] < $amount) {
        $response['error'] = 'Insufficient balance for this payment.';
        echo json_encode($response);
        exit;
    }

    $account_id = $acc['account_id'];

    mysqli_begin_transaction($conn);
    try {
        // Deduct
        mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance - $amount, available_balance = available_balance - $amount WHERE account_id = $account_id");

        // Record
        $ref = "BILL" . time() . rand(10, 99);
        $desc = "Bill Payment: $provider ($type) Ref: $ref_id";

        $ins = "INSERT INTO transactions (transaction_reference, from_account_id, to_account_id, transaction_type_id, amount, status, initiated_by_user_id) 
                VALUES ('$ref', $account_id, NULL, 5, $amount, 'Completed', $user_id)";
        mysqli_query($conn, $ins);

        mysqli_commit($conn);
        $response['success'] = true;
        $response['ref'] = $ref;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['error'] = 'System failure: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>