<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'toggle_freeze') {
    $status = $_POST['status']; // 'Active' or 'Inactive'
    $query = "UPDATE cards SET status = '$status' WHERE user_id = $user_id";
    if (mysqli_query($conn, $query))
        $response['success'] = true;
} elseif ($action === 'update_limit') {
    $limit = intval($_POST['limit']);
    $query = "UPDATE cards SET daily_spending_limit = $limit WHERE user_id = $user_id";
    if (mysqli_query($conn, $query))
        $response['success'] = true;
} elseif ($action === 'permanent_block') {
    $query = "UPDATE cards SET status = 'Blocked' WHERE user_id = $user_id";
    if (mysqli_query($conn, $query))
        $response['success'] = true;
} elseif ($action === 'request_card') {
    // Check if user already has a pending card request
    $pending_check = mysqli_query($conn, "SELECT request_id FROM card_requests WHERE user_id = $user_id AND status = 'Pending'");
    if (mysqli_num_rows($pending_check) > 0) {
        $response['message'] = "Protocol Error: A pending card request is already awaiting authorization.";
    } else {
        // Create new card request
        $acc_q = mysqli_query($conn, "SELECT account_id FROM accounts WHERE user_id = $user_id ORDER BY available_balance DESC LIMIT 1");
        $acc = mysqli_fetch_assoc($acc_q);
        if ($acc) {
            $acc_id = $acc['account_id'];
            $type_id = intval($_POST['card_type_id'] ?? 1);

            $ins = "INSERT INTO card_requests (user_id, account_id, requested_card_type_id, status, request_date) 
                    VALUES ($user_id, $acc_id, $type_id, 'Pending', CURRENT_TIMESTAMP)";
            if (mysqli_query($conn, $ins)) {
                $response['success'] = true;
                $response['message'] = "Request Submitted: Your application for a new card is now in the staff approval queue.";
            } else {
                $response['message'] = "System Error: Failed to transmit card request: " . mysqli_error($conn);
            }
        } else {
            $response['message'] = "Primary account not found. Please contact support.";
        }
    }
}

echo json_encode($response);
?>