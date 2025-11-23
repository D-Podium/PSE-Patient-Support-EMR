<?php
// src/config.php

require __DIR__ . '/../vendor/autoload.php'; // Composer autoload

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// === API Constants ===
define('API_URL', $_ENV['API_URL']);
define('API_KEY', $_ENV['API_KEY']);

// === Database Connection ===
$conn = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME']
);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset for proper encoding
$conn->set_charset("utf8");

// === Helper Function: API Call ===
function callAPI($endpoint, $method = 'GET', $data = []) {
    $url = rtrim(API_URL, '/') . '/' . ltrim($endpoint, '/');

    $headers = [
        "Authorization: Bearer " . API_KEY,
        "Content-Type: application/json"
    ];

    $ch = curl_init();

    if ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return [
            'status' => false,
            'message' => curl_error($ch)
        ];
    }

    curl_close($ch);

    return json_decode($response, true);
}
?>
