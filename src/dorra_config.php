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
 * Helper to build the final URL safely (avoids // duplicate slashes)
 * and is where the SSL fix is applied.
 */
function dorra_build_url($endpoint) {
    // 1. Remove leading slash from the endpoint path if it exists
    $clean_endpoint = ltrim($endpoint, '/'); 
    // 2. Ensure DORRA_API_URL ends with a single slash for safe concatenation
    $base_url = rtrim(DORRA_API_URL, '/') . '/';
    // 3. Combine them safely
    return $base_url . $clean_endpoint;
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
    $ch = curl_init(dorra_build_url($endpoint)); // Use the safe URL builder
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // --- FIX: Disable SSL verification for local dev environment ---
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // ---------------------------------------------------------------
    
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
    $ch = curl_init(dorra_build_url($endpoint)); // Use the safe URL builder
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // --- FIX: Disable SSL verification for local dev environment ---
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // ---------------------------------------------------------------
    
    $res = curl_exec($ch);
    
    if(curl_errno($ch)) {
        // Return structured failure info for better debugging in the caller function
        return ['http_code' => 0, 'raw' => null, 'json' => null, 'error' => curl_error($ch)];
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Return structured response for detailed error checking in the main signup script
    return [
        'http_code' => $http_code,
        'raw' => $res,
        'json' => json_decode($res, true),
        'error' => null
    ];
}

/**
 * PATCH request helper
 */
function dorra_patch($endpoint, $data = []) {
    $ch = curl_init(dorra_build_url($endpoint)); // Use the safe URL builder
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // --- FIX: Disable SSL verification for local dev environment ---
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // ---------------------------------------------------------------
    
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
    $ch = curl_init(dorra_build_url($endpoint)); // Use the safe URL builder
    curl_setopt($ch, CURLOPT_HTTPHEADER, dorra_headers());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    // --- FIX: Disable SSL verification for local dev environment ---
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // ---------------------------------------------------------------
    
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