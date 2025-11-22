<?php
/**
 * dorra_config.php
 * Dorra EMR API configuration using .env
 */

require_once __DIR__ . '/../vendor/autoload.php'; // If using vlucas/phpdotenv

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Define constants from .env
define('DORRA_API_URL', $_ENV['API_URL'] ?? '');
define('DORRA_API_KEY', $_ENV['API_KEY'] ?? '');

// Ensure API config exists
if (empty(DORRA_API_URL) || empty(DORRA_API_KEY)) {
    die("Dorra API configuration missing in .env");
}

/**
 * Common headers for Dorra API requests
 */
function dorra_headers() {
    return [
        "Authorization: Token " . DORRA_API_KEY,
        "Content-Type: application/json",
        "Accept: application/json"
    ];
}

/**
 * GET request helper
 */
function dorra_get($endpoint) {
    $ch = curl_init(DORRA_API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    if(curl_errno($ch)) {
        error_log('Dorra GET Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return json_decode($res, true);
}

/**
 * POST request helper
 */
function dorra_post($endpoint, $data = []) {
    $ch = curl_init(DORRA_API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $res = curl_exec($ch);
    if(curl_errno($ch)) {
        error_log('Dorra POST Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return json_decode($res, true);
}

/**
 * PATCH request helper
 */
function dorra_patch($endpoint, $data = []) {
    $ch = curl_init(DORRA_API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $res = curl_exec($ch);
    if(curl_errno($ch)) {
        error_log('Dorra PATCH Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return json_decode($res, true);
}

/**
 * DELETE request helper
 */
function dorra_delete($endpoint) {
    $ch = curl_init(DORRA_API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $res = curl_exec($ch);
    if(curl_errno($ch)) {
        error_log('Dorra DELETE Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return json_decode($res, true);
}
?>