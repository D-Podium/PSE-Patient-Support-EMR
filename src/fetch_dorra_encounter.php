<?php
/**
 * get_encounters.php
 * Fetches all historical clinical encounters for a patient from the Dorra EMR API.
 * It maps the local patient ID to the external EMR ID first.
 */
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/dorra_config.php';
header('Content-Type: application/json');

// 1. Authentication check
if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['error'=>'Unauthorized', 'encounters' => []]);
    exit;
}

$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) { 
    echo json_encode(['error'=>'Missing Local Patient ID', 'encounters' => []]); 
    exit; 
}

// 2. Fetch Dorra patient ID from local DB
$emr_patient_id = null;
// NOTE: $conn is assumed to be defined and connected from config.php
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT emr_patient_id FROM tblpatient WHERE patient_id=?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $stmt->bind_result($emr_patient_id);
    $stmt->fetch();
    $stmt->close();
}

if (!$emr_patient_id) { 
    echo json_encode(['error'=>'Patient not mapped to EMR', 'encounters' => []]); 
    exit; 
}

// 3. Fetch encounters from Dorra API (GET /v1/patients/$emr_patient_id/encounters)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $DORRA_API_URL."/v1/patients/$emr_patient_id/encounters");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $DORRA_API_KEY",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

// 4. Handle response
if ($err) {
    error_log("Dorra API Encounters Error: " . $err);
    echo json_encode(['error'=>'API Request Failed', 'encounters' => []]);
    exit;
}

$encounters = json_decode($response, true) ?? [];

// 5. Normalize for frontend
$result = array_map(function($e){
    return [
        'created_at' => $e['created_at'] ?? 'Unknown Date',
        'diagnosis' => $e['diagnosis'] ?? 'No Diagnosis',
        'summary' => $e['summary'] ?? 'No Summary',
        'medications' => $e['medications'] ?? [],
        'tests' => $e['tests'] ?? [],
        'vitals' => $e['vitals'] ?? []
    ];
}, $encounters);

// 6. Return history
echo json_encode($result);
?>