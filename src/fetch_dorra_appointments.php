<?php
/**
 * get_appointments.php
 * Fetches scheduled appointments for a patient from the Dorra EMR API.
 * Uses the emr_patient_id provided as a GET parameter.
 */
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/dorra_config.php';

header('Content-Type: application/json');

// 1. Authentication check
if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['error'=>'Unauthorized', 'appointments' => []]);
    exit;
}

// 2. Get EMR patient ID from query parameter
$emr_patient_id = $_GET['emr_patient_id'] ?? null;

if (!$emr_patient_id) {
    echo json_encode(['error'=>'Missing EMR Patient ID', 'appointments' => []]);
    exit;
}

// 3. Fetch appointments from Dorra EMR API (GET /appointments)
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $DORRA_API_URL . "/appointments?patient_id=" . urlencode($emr_patient_id),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $DORRA_API_KEY",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

// 4. Handle response
if ($err) {
    error_log("Dorra API Appointments Error: " . $err);
    echo json_encode(['error'=>'API Request Failed', 'appointments' => []]);
    exit;
}

$data = json_decode($response, true);

// 5. Convert to consistent format for frontend
$appointments = [];
if (isset($data['appointments']) && is_array($data['appointments'])) {
    foreach ($data['appointments'] as $a) {
        $appointments[] = [
            'date' => $a['date'] ?? 'N/A',
            'time' => $a['time'] ?? 'N/A',
            'status' => $a['status'] ?? 'Scheduled',
            'notes' => $a['notes'] ?? ''
        ];
    }
}

// 6. Return normalized list
echo json_encode($appointments);
?>