<?php
require_once '../includes/db_config.php';

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract fields with proper fallbacks
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $father_name = mysqli_real_escape_string($conn, $_POST['father_name'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? 'Other');
    $dob = mysqli_real_escape_string($conn, $_POST['dob'] ?? '');
    $contact = mysqli_real_escape_string($conn, $_POST['contact'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Normalize CNIC (Keep only digits)
    $cnic = preg_replace('/[^0-9]/', '', $_POST['cnic'] ?? '');

    // Appointment logic (Handle NULL for SQL)
    $app_date = mysqli_real_escape_string($conn, $_POST['appointment_date'] ?? '');
    $app_time = mysqli_real_escape_string($conn, $_POST['appointment_time'] ?? '');
    $app_date_sql = !empty($app_date) ? "'$app_date'" : "NULL";
    $app_time_sql = !empty($app_time) ? "'$app_time'" : "NULL";

    // Mandatory Field Validation
    if (empty($full_name) || empty($cnic) || empty($username) || empty($password)) {
        $response['error'] = 'Node Sync Error: Registry requires Full Name, CNIC, Username, and Password.';
        echo json_encode($response);
        exit;
    }

    // Duplicate Check
    $check_query = "SELECT user_id FROM users WHERE username = '$username' OR cnic = '$cnic' LIMIT 1";
    $check_res = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_res) > 0) {
        $response['error'] = 'Conflict: Identity or Username is already anchored in the registry.';
        echo json_encode($response);
        exit;
    }

    // Simplified Password Storage (Note: In production use password_hash)
    $password_hash = mysqli_real_escape_string($conn, $password);

    mysqli_begin_transaction($conn);
    try {
        $insert_user = "INSERT INTO users (cnic, username, password_hash, full_name, father_name, date_of_birth, address, contact_number, email, gender, user_type, is_active) 
                         VALUES ('$cnic', '$username', '$password_hash', '$full_name', '$father_name', '$dob', '$address', '$contact', '$email', '$gender', 'Customer', 0)";

        if (!mysqli_query($conn, $insert_user)) {
            throw new Exception("Authentication Node Failure: " . mysqli_error($conn));
        }

        $user_id = mysqli_insert_id($conn);
        $form_number = "FORM-" . date("Ymd") . "-" . str_pad($user_id, 4, "0", STR_PAD_LEFT);
        $acc_type = intval($_POST['account_type'] ?? 1);

        $insert_prospect = "INSERT INTO prospective_customers (cnic, full_name, father_name, date_of_birth, address, contact_number, email, gender, requested_account_type_id, form_number, status, appointment_date, appointment_time) 
                              VALUES ('$cnic', '$full_name', '$father_name', '$dob', '$address', '$contact', '$email', '$gender', $acc_type, '$form_number', 'Pending', $app_date_sql, $app_time_sql)";

        if (!mysqli_query($conn, $insert_prospect)) {
            throw new Exception("Operational Node Failure: " . mysqli_error($conn));
        }

        mysqli_commit($conn);
        $response['success'] = true;
        $response['form_number'] = $form_number;
        $response['message'] = "Identity Link Established. Form Number: $form_number.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['error'] = 'System Fault: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid request method';
}

echo json_encode($response);
?>