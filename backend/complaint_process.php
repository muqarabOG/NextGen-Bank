<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['error'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Submit New Complaint
if (isset($_POST['category_id'])) {
    $cat_id = intval($_POST['category_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $ticket = "NG" . date('Ymd') . rand(10, 99);

    $query = "INSERT INTO complaints (user_id, category_id, ticket_number, title, description, status) 
              VALUES ($user_id, $cat_id, '$ticket', '$title', '$desc', 'Open')";

    if (mysqli_query($conn, $query)) {
        $response['success'] = true;
        $response['ticket_id'] = $ticket;
    } else {
        $response['error'] = 'Database error: ' . mysqli_error($conn);
    }
}
// 2. Track Complaint
elseif (isset($_POST['action']) && $_POST['action'] === 'track') {
    $ticket = mysqli_real_escape_string($conn, $_POST['ticket_id']);
    $query = "SELECT * FROM complaints WHERE ticket_number = '$ticket' AND user_id = $user_id LIMIT 1";
    $res = mysqli_query($conn, $query);
    if ($complaint = mysqli_fetch_assoc($res)) {
        $response['success'] = true;
        // Fetch updates
        $updates_q = mysqli_query($conn, "SELECT * FROM complaint_updates WHERE complaint_id = " . $complaint['complaint_id'] . " ORDER BY created_at DESC");
        $updates = [];
        while ($u = mysqli_fetch_assoc($updates_q))
            $updates[] = $u;
        $complaint['updates'] = $updates;
        $response['complaint'] = $complaint;
    } else {
        $response['error'] = 'Ticket not found or unauthorized access.';
    }
}

echo json_encode($response);
?>