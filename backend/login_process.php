<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response['error'] = 'Access credentials required.';
        echo json_encode($response);
        exit;
    }

    $query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if ($user['is_active'] == 0) {
            $response['error'] = 'User Account Inactive. Contact Administrator.';
            echo json_encode($response);
            exit;
        }

        if ($password === $user['password_hash']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];

            $response['success'] = true;
            $response['user_type'] = $user['user_type'];

            if ($user['user_type'] === 'Staff') {
                $staff_q = mysqli_query($conn, "SELECT staff_role FROM staff WHERE user_id = " . $user['user_id']);
                if ($staff_q && $staff = mysqli_fetch_assoc($staff_q)) {
                    $response['role'] = $staff['staff_role'];
                } else {
                    $response['error'] = 'Staff role mapping failed: ' . mysqli_error($conn);
                    echo json_encode($response);
                    exit;
                }
            }

            // Simple Audit
            $ip = $_SERVER['REMOTE_ADDR'];
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, ip_address) VALUES (" . $user['user_id'] . ", 'AUTH_SUCCESS', '$ip')");

        } else {
            $response['error'] = 'Invalid Access Key.';
        }
    } else {
        $response['error'] = 'Identity not found in node registry.';
    }
} else {
    $response['error'] = 'Invalid request vector.';
}

echo json_encode($response);
?>