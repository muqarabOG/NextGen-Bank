<?php
// chatbot_api.php

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';

if (!$message) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

// URL of your Node.js local proxy
$proxy_url = 'http://localhost:3000/chat';

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
    echo json_encode(['reply' => 'Error contacting local proxy: ' . curl_error($ch)]);
} else {
    $resData = json_decode($response, true);
    $reply = $resData['reply'] ?? 'No reply from proxy.';
    echo json_encode(['reply' => $reply]);
}

curl_close($ch);
