<?php
/**
 * create_encounter.php
 * Endpoint for submitting a new patient encounter to the Dorra EMR system.
 * It first maps the local patient ID to the external EMR ID.
 */
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/dorra_config.php';

header('Content-Type: application/json');

// 1. Authentication check
if(!isset($_SESSION['doctor_id'])){
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

// 2. Read input data (from JSON body)
$input = json_decode(file_get_contents('php://input'), true);
if(!$input || !isset($input['patient_id'])){
    echo json_encode(['success'=>false,'message'=>'Invalid data']);
    exit;
}

$patient_id = $input['patient_id'];
$diagnosis = $input['diagnosis'] ?? '';
$summary = $input['summary'] ?? '';
$medications = $input['medications'] ?? [];
$tests = $input['tests'] ?? [];
$vitals = $input['vitals'] ?? [];

// 3. Map local patient_id to emr_patient_id from local DB (tblpatient)
$emr_patient_id = null;
// NOTE: $conn is assumed to be defined and connected from config.php
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT emr_patient_id FROM tblpatient WHERE patient_id=?");
    $stmt->bind_param('i',$patient_id);
    $stmt->execute();
    $stmt->bind_result($emr_patient_id);
    $stmt->fetch();
    $stmt->close();
}

if(!$emr_patient_id){
    echo json_encode(['success'=>false,'message'=>'Patient not found or not mapped to EMR']);
    exit;
}

// 4. Build API payload
$payload = ['patient_id'=>$emr_patient_id];
if($diagnosis) $payload['diagnosis'] = $diagnosis;
if($summary) $payload['summary'] = $summary;
if(!empty($medications)) $payload['medications'] = $medications;
if(!empty($tests)) $payload['tests'] = $tests;
if(!empty($vitals)) $payload['vitals'] = $vitals;

// 5. Send payload to Dorra API (POST /encounters)
$ch = curl_init();
curl_setopt_array($ch,[
    CURLOPT_URL => $DORRA_API_URL.'/encounters',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer '.$DORRA_API_KEY,
        'Content-Type: application/json'
    ]
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

// 6. Handle response
if($err){
    error_log("Dorra API Encounter Error: " . $err);
    echo json_encode(['success'=>false,'message'=>'API request error: '.$err]);
    exit;
}

$resp = json_decode($response,true);
if(isset($resp['id'])){
    echo json_encode(['success'=>true, 'encounter_id' => $resp['id']]);
}else{
    $errorMessage = $resp['message'] ?? 'Unknown API error during encounter creation';
    error_log("Dorra API Encounter Failed: " . $errorMessage);
    echo json_encode(['success'=>false,'message'=>$errorMessage]);
}
?>