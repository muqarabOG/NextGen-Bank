<?php
// chatbot_api.php

header('Content-Type: application/json');
ob_start();
ob_clean();

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';

if (!$message) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

// URL of your Node.js local proxy
// Using 127.0.0.1 instead of localhost to avoid IPv6 resolution delays in some XAMPP setups
$proxy_url = 'http://127.0.0.1:3000/chat';

// Prepare POST payload
$payload = json_encode(['message' => $message]);

// Use cURL to send the request to local Node proxy
$ch = curl_init($proxy_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    error_log("Chatbot API Error: " . $error_msg);
    echo json_encode(['reply' => 'Connection fault between PHP and AI Node. (Error: ' . $error_msg . ')']);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code !== 200) {
        error_log("Chatbot Proxy returned HTTP " . $http_code . ": " . $response);
        echo json_encode(['reply' => 'AI Node returned error code ' . $http_code]);
    } else {
        $resData = json_decode($response, true);
        $reply = $resData['reply'] ?? 'Empty response from AI services.';
        echo json_encode(['reply' => $reply]);
    }
}

curl_close($ch);
