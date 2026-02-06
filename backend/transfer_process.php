<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    $response['error'] = 'Unauthorized session';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_account_num = mysqli_real_escape_string($conn, $_POST['to_account']);
    $amount = floatval($_POST['amount']);
    $transfer_type = $_POST['transfer_type'] ?? 'internal';
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');

    if ($amount <= 0) {
        $response['error'] = 'Invalid amount';
        echo json_encode($response);
        exit;
    }

    // 1. Get sender account
    $sender_query = "SELECT * FROM accounts WHERE user_id = $user_id LIMIT 1";
    $sender_res = mysqli_query($conn, $sender_query);
    $sender_acc = mysqli_fetch_assoc($sender_res);

    if (!$sender_acc) {
        $response['error'] = 'Sender account not found';
        echo json_encode($response);
        exit;
    }

    if ($sender_acc['available_balance'] < $amount) {
        $response['error'] = 'Insufficient balance';
        echo json_encode($response);
        exit;
    }

    // 1.5 Check Transfer Limits
    $limit_query = "SELECT * FROM transfer_limits WHERE account_id = " . $sender_acc['account_id'];
    $limit_res = mysqli_query($conn, $limit_query);
    if ($limit = mysqli_fetch_assoc($limit_res)) {
        $today_q = "SELECT SUM(amount) as total FROM transactions 
                    WHERE from_account_id = " . $sender_acc['account_id'] . " 
                    AND DATE(transaction_date) = CURDATE() AND status = 'Completed'";
        $today_res = mysqli_query($conn, $today_q);
        $today_total = mysqli_fetch_assoc($today_res)['total'] ?? 0;

        if (($today_total + $amount) > $limit['daily_limit']) {
            $response['error'] = 'Daily transfer limit exceeded. Remaining: Rs. ' . ($limit['daily_limit'] - $today_total);
            echo json_encode($response);
            exit;
        }
    }

    mysqli_begin_transaction($conn);

    try {
        $ref = "TXN" . time() . rand(100, 999);
        $recipient_acc_id = 'NULL';

        if ($transfer_type === 'internal') {
            // 2. Get recipient account
            $recipient_query = "SELECT * FROM accounts WHERE account_number = '$to_account_num'";
            $recipient_res = mysqli_query($conn, $recipient_query);
            $recipient_acc = mysqli_fetch_assoc($recipient_res);

            if (!$recipient_acc) {
                throw new Exception("Recipient account not found in NextGenBank");
            }

            if ($recipient_acc['account_id'] === $sender_acc['account_id']) {
                throw new Exception("Cannot transfer to the same account");
            }

            $recipient_acc_id = $recipient_acc['account_id'];

            // Add to recipient
            $add_query = "UPDATE accounts SET current_balance = current_balance + $amount, available_balance = available_balance + $amount WHERE account_id = $recipient_acc_id";
            mysqli_query($conn, $add_query);
        }

        // Deduct from sender
        $deduct_query = "UPDATE accounts SET current_balance = current_balance - $amount, available_balance = available_balance - $amount WHERE account_id = " . $sender_acc['account_id'];
        mysqli_query($conn, $deduct_query);

        // Record transaction
        $is_external = ($transfer_type === 'external') ? 1 : 0;
        $trans_query = "INSERT INTO transactions (transaction_reference, from_account_id, to_account_id, transaction_type_id, amount, status, is_external_transfer, external_bank_name, external_account_number, initiated_by_user_id) 
                        VALUES ('$ref', " . $sender_acc['account_id'] . ", $recipient_acc_id, 3, $amount, 'Completed', $is_external, '$bank_name', '$to_account_num', $user_id)";
        mysqli_query($conn, $trans_query);

        // Audit Log
        $log_data = mysqli_real_escape_string($conn, json_encode(['from' => $sender_acc['account_number'], 'to' => $to_account_num, 'type' => $transfer_type, 'bank' => $bank_name, 'amount' => $amount]));
        mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, table_name, record_id, new_values, ip_address) 
                            VALUES ($user_id, 'TRANSFER', 'transactions', LAST_INSERT_ID(), '$log_data', '" . $_SERVER['REMOTE_ADDR'] . "')");

        mysqli_commit($conn);
        $response['success'] = true;
        $response['ref'] = $ref;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['error'] = 'Transaction failed: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid request';
}

echo json_encode($response);
?>