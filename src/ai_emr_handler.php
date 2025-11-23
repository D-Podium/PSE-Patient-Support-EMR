<?php
// src/ai_handler.php

session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/dorra_config.php'; 

// Use the core callDorraApi function defined in data_handler.php (or copy it here for a standalone script)
// For simplicity, I'll copy the helper function here:
function callDorraApi(string $endpoint, string $method = 'GET', array $payload = []): array {
    if (!defined('API_URL') || !defined('API_KEY')) {
        return ['error' => true, 'message' => 'API configuration missing.', 'error_code' => 500];
    }
    
    $ch = curl_init(API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        "Authorization: Token " . API_KEY,
        "Content-Type: application/json"
    ];
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 201) {
        $msg = json_decode($response, true)['detail'] ?? "API responded with HTTP code: " . $http_code;
        return ['error' => true, 'message' => $msg, 'error_code' => $http_code];
    }
    
    return json_decode($response, true);
}


// Set header for JSON response
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$result = ['error_code' => 400, 'response' => 'Invalid AI action.'];

// --- Doctor's AI EMR Suggestion ---
if (isset($_SESSION['doctor_id']) && $action === 'emr_suggestion') {
    $data = json_decode(file_get_contents('php://input'), true);
    $emrPatientId = $data['emr_patient_id'] ?? null;
    $prompt = $data['prompt'] ?? 'Suggest next steps.';
    
    if (!$emrPatientId) {
        $result = ['error_code' => 400, 'response' => 'Missing EMR Patient ID.'];
    } else {
        $apiPayload = [
            'patient_id' => $emrPatientId,
            'prompt' => $prompt
        ];
        
        $response = callDorraApi("/v1/ai/emr-suggestion", 'POST', $apiPayload);
        
        if (isset($response['error'])) {
            $result = ['error_code' => $response['error_code'], 'response' => $response['message']];
        } else {
            $result = ['error_code' => 200, 'response' => $response['suggestion'] ?? 'No suggestion returned.'];
        }
    }
} 
// --- Patient's Drug Safety Query ---
else if (isset($_SESSION['patient_id']) && $action === 'drug_safety') {
    // Note: The patient dashboard uses POST request with form data (ai_query) via a redirect, not JSON input
    $query = $_POST['ai_query'] ?? null; 
    $emrPatientId = $_SESSION['emr_patient_id'] ?? null;

    if (!$query || !$emrPatientId) {
        $result = ['error_code' => 400, 'response' => 'Missing drug query or EMR Patient ID.'];
    } else {
        $apiPayload = [
            'query' => $query,
            'patient_id' => $emrPatientId
        ];
        
        $response = callDorraApi("/v1/ai/drug-safety", 'POST', $apiPayload);
        
        if (isset($response['error'])) {
            $result = ['error_code' => $response['error_code'], 'response' => $response['message']];
        } else {
            // Replicate the HTML formatting needed by the Patient Dashboard JS
             $html = "<div class='ai-card'>";
             $html .= "<h5>Drug: <strong>".htmlspecialchars($response['drug'] ?? $query)."</strong></h5>";
             $html .= "<ul>";
             $html .= "<li>1st Trimester: <strong>".htmlspecialchars($response['1st_trimester'] ?? 'Unknown')."</strong></li>";
             $html .= "<li>2nd Trimester: <strong>".htmlspecialchars($response['2nd_trimester'] ?? 'Unknown')."</strong></li>";
             $html .= "<li>3rd Trimester: <strong>".htmlspecialchars($response['3rd_trimester'] ?? 'Unknown')."</strong></li>";
             $html .= "<li>Fetal Risk: <strong>".htmlspecialchars($response['fetal_risk'] ?? 'Unknown')."</strong></li>";
             $html .= "<li>Safer Alternatives: <strong>".htmlspecialchars($response['alternatives'] ?? 'No alternatives available')."</strong></li>";
             $html .= "</ul></div>";

            $result = ['error_code' => 200, 'response' => $html];
        }
    }
}

echo json_encode($result);
exit;