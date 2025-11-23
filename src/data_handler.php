<?php
// src/data_handler.php

session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/dorra_config.php'; // Ensure API_URL and API_KEY are defined here

// --- Core Helper Functions ---

/**
 * Executes a cURL request to the Dorra API.
 * @param string $endpoint The API path (e.g., "/v1/emr/encounter").
 * @param string $method HTTP method (GET, POST).
 * @param array $payload Data to send (for POST).
 * @return array The decoded JSON response from the API, or an error array.
 */
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
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['error' => true, 'message' => "cURL Error: " . $curl_error, 'error_code' => 0];
    }
    
    $data = json_decode($response, true);
    
    if ($http_code !== 200 && $http_code !== 201) {
        $msg = $data['detail'] ?? "API responded with HTTP code: " . $http_code;
        return ['error' => true, 'message' => $msg, 'error_code' => $http_code];
    }
    
    return $data;
}

/**
 * Helper to fetch EMR patient ID from local DB using the local patient ID.
 * NOTE: Assumes $conn is defined in config.php.
 * @param int $localPatientId
 * @return string|null The emr_patient_id or null.
 */
function getEmrPatientId($localPatientId, $conn): ?string {
    $stmt = $conn->prepare("SELECT emr_patient_id FROM tblpatient WHERE patient_id = ?");
    $stmt->bind_param("i", $localPatientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['emr_patient_id'] ?? null;
}

// --- API Action Handlers ---

function handleFetchEncounters($conn) {
    // Both Doctor and Patient dashboards use the local patient_id to fetch the EMR ID.
    $localPatientId = $_GET['patient_id'] ?? ($_SESSION['patient_id'] ?? null);
    
    if (!$localPatientId) {
        return ['error' => true, 'message' => 'Missing local patient ID.', 'error_code' => 400];
    }

    $emrPatientId = getEmrPatientId($localPatientId, $conn);
    
    if (!$emrPatientId) {
        return ['error' => true, 'message' => 'EMR Patient ID not found for local user.', 'error_code' => 404];
    }

    $endpoint = "/v1/emr/encounter?patient_id=" . urlencode($emrPatientId);
    return callDorraApi($endpoint, 'GET');
}

function handleFetchAppointments($conn) {
    // Both dashboards require the EMR ID for this API call.
    $emrPatientId = $_GET['emr_patient_id'] ?? ($_SESSION['emr_patient_id'] ?? null);
    
    if (!$emrPatientId) {
        return ['error' => true, 'message' => 'Missing EMR patient ID.', 'error_code' => 400];
    }

    $endpoint = "/v1/emr/appointment?patient_id=" . urlencode($emrPatientId);
    return callDorraApi($endpoint, 'GET');
}

function handleCreateEncounter($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $localPatientId = $data['patient_id'] ?? null;
    $doctorId = $_SESSION['doctor_id'] ?? null;
    
    if (!$localPatientId || !$doctorId) {
        return ['error' => true, 'message' => 'Missing patient or doctor session data.', 'error_code' => 401];
    }

    $emrPatientId = getEmrPatientId($localPatientId, $conn);
    if (!$emrPatientId) {
        return ['error' => true, 'message' => 'EMR Patient ID not found.', 'error_code' => 404];
    }

    // Map the payload to the required Dorra format
    $apiPayload = [
        'patient_id' => $emrPatientId,
        'doctor_id' => $doctorId, // Use Doctor's local ID (assuming Dorra uses local doctor IDs or maps them)
        'diagnosis' => $data['diagnosis'] ?? null,
        'summary' => $data['summary'] ?? null,
        'medications' => $data['medications'] ?? [],
        'tests' => $data['tests'] ?? [],
        'vitals' => $data['vitals'] ?? [],
    ];

    $response = callDorraApi("/v1/emr/encounter", 'POST', $apiPayload);
    
    if (isset($response['error'])) {
        return ['success' => false, 'message' => $response['message']];
    }
    
    // Return success message with the new encounter ID
    return ['success' => true, 'encounter_id' => $response['encounter_id'] ?? 'N/A'];
}

function handleCreateAppointment($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $localPatientId = $data['patient_id'] ?? null;
    $doctorId = $_SESSION['doctor_id'] ?? null;

    // Only doctors can create appointments in this system based on the dashboard structure
    if (!$localPatientId || !$doctorId) {
        return ['success' => false, 'message' => 'Unauthorized action or missing data.', 'error_code' => 401];
    }

    $emrPatientId = getEmrPatientId($localPatientId, $conn);
    if (!$emrPatientId) {
        return ['success' => false, 'message' => 'EMR Patient ID not found.', 'error_code' => 404];
    }
    
    $apiPayload = [
        'patient_id' => $emrPatientId,
        'doctor_id' => $doctorId,
        'date' => $data['date'] ?? null,
        'time' => $data['time'] ?? null,
        'status' => $data['status'] ?? 'Scheduled',
        'notes' => $data['notes'] ?? null,
    ];

    $response = callDorraApi("/v1/emr/appointment", 'POST', $apiPayload);
    
    if (isset($response['error'])) {
        return ['success' => false, 'message' => $response['message'], 'api_response' => $response];
    }

    return ['success' => true, 'appointment_id' => $response['appointment_id'] ?? 'N/A'];
}

// --- Main Execution Block ---

// Set header for JSON response
header('Content-Type: application/json');

// Ensure $conn is available from config.php
if (!isset($conn)) {
    echo json_encode(['error' => true, 'message' => 'Database connection failed.', 'error_code' => 500]);
    exit;
}

$action = $_GET['action'] ?? null;
$result = ['error' => true, 'message' => 'Invalid action.', 'error_code' => 400];

switch ($action) {
    case 'fetch_encounters':
        $result = handleFetchEncounters($conn);
        break;
    case 'fetch_appointments':
        $result = handleFetchAppointments($conn);
        break;
    case 'create_encounter':
        $result = handleCreateEncounter($conn);
        break;
    case 'create_appointment':
        $result = handleCreateAppointment($conn);
        break;
    default:
        // Already set to Invalid action
        break;
}

echo json_encode($result);
exit;