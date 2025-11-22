<?php
/**
 * create_appointment.php
 * Endpoint for submitting a new patient appointment to the Dorra EMR system.
 * It maps the local patient ID to the external EMR ID and calls the /appointments endpoint.
 */
session_start();
// NOTE: Assuming config.php and dorra_config.php are correctly located relative to this file
require __DIR__ . '/config.php';
require __DIR__ . '/dorra_config.php';

header('Content-Type: application/json');

// 1. Authentication check
if(!isset($_SESSION['doctor_id'])){
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

// 2. Read input data
$input = json_decode(file_get_contents('php://input'), true);
if(!$input || !isset($input['patient_id'], $input['date'], $input['time'], $input['status'])){
    echo json_encode(['success'=>false,'message'=>'Missing required appointment data (patient_id, date, time, status)']);
    exit;
}

$patient_id = $input['patient_id'];
$date = $input['date'];
$time = $input['time'];
$status = $input['status'];
$notes = $input['notes'] ?? '';

// 3. Map local patient_id to emr_patient_id from local DB (tblpatient)
// This step is crucial for bridging the local patient tracking system with the external EMR.
$emr_patient_id = null;
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT emr_patient_id FROM tblpatient WHERE patient_id=?");
    $stmt->bind_param('i',$patient_id);
    $stmt->execute();
    $stmt->bind_result($emr_patient_id);
    $stmt->fetch();
    $stmt->close();
}

if(!$emr_patient_id){
    // If we cannot find the EMR ID, we cannot schedule the appointment in Dorra.
    echo json_encode(['success'=>false,'message'=>'Patient not found or not mapped to EMR']);
    exit;
}

// 4. Build API payload for Dorra EMR
$payload = [
    'patient_id' => $emr_patient_id,
    'date' => $date,
    'time' => $time,
    'status' => $status,
    'notes' => $notes
];

// 5. Send payload to Dorra API (POST /appointments) using cURL
$ch = curl_init();
curl_setopt_array($ch,[
    CURLOPT_URL => $DORRA_API_URL.'/appointments',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer '.$DORRA_API_KEY,
        'Content-Type: application/json'
    ],
    CURLOPT_HEADER => false
]);

// Capture the response and any error
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

// 6. Handle response and error checking
if($err){
    error_log("Dorra API Appointment cURL Error: " . $err);
    echo json_encode(['success'=>false,'message'=>'API request error: '.$err]);
    exit;
}

// Check for non-200/201 HTTP status codes, which indicate an API-side error
if ($http_code != 200 && $http_code != 201) {
    $detail = json_decode($response, true) ?? ['raw_response' => $response];
    $message = "API returned HTTP $http_code. Details: " . json_encode($detail);
    error_log("Dorra API Appointment Failed (HTTP $http_code): " . $message);
    echo json_encode(['success'=>false, 'message' => "API Error (Code $http_code). Check server logs for details."]);
    exit;
}

// Decode response body
$resp = json_decode($response,true);

if(isset($resp['id'])){
    // Success: Appointment ID returned from Dorra EMR
    echo json_encode(['success'=>true, 'appointment_id' => $resp['id']]);
}else{
    // Failure: No 'id' in response, but status was 200/201, or error message was missing.
    $errorMessage = $resp['message'] ?? 'Unknown API error during appointment scheduling. Response Body: ' . $response;
    error_log("Dorra API Appointment Failed: " . $errorMessage);
    echo json_encode(['success'=>false,'message'=>$errorMessage]);
}
?>