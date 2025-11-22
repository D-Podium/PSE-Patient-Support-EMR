<?php
session_start();
require __DIR__ . '/../src/config.php'; // Load DB + API keys
require __DIR__ . '/../src/dorra_config.php'; // Required for API_URL and API_KEY, assuming config.php only holds DB

if(!isset($_SESSION['patient_id'])) {
    header("Location: ../src/index.php");
    exit;
}

// Assume these are set during patient login, based on the doctor dashboard structure
$local_patient_id = $_SESSION['patient_id'];
$emr_patient_id = $_SESSION['emr_patient_id'] ?? null; 
$first_name = $_SESSION['first_name'] ?? 'Patient';


// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../src/index.php");
    exit;
}

// Fetch list of valid drugs from Dorra PharmaVigilance API for autocomplete
$valid_drugs = [];
// Assuming API_URL and API_KEY are defined in dorra_config.php or config.php
if (defined('API_URL') && defined('API_KEY')) {
    $ch = curl_init(API_URL . "/v1/pharmavigilance/interactions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . API_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $drug_data = json_decode($response, true);
    if(isset($drug_data['results']) && is_array($drug_data['results'])) {
        foreach($drug_data['results'] as $d) {
            if(isset($d['drug_name'])) {
                $valid_drugs[] = $d['drug_name'];
            }
        }
    }
}


// Handle AI/PSE query
if(isset($_POST['ai_query'])) {
    $query = $_POST['ai_query'];
    
    // Check if EMR ID is available before making the call
    if(!$emr_patient_id) {
        $api_data = ['error_message' => 'EMR Patient ID not found in session.'];
    } else {
        $ch = curl_init(API_URL . "/v1/ai/drug-safety");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Token " . API_KEY,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'query' => $query,
            'patient_id' => $emr_patient_id // Use EMR ID for Dorra API
        ]));
        $response = curl_exec($ch);
        curl_close($ch);

        $api_data = json_decode($response, true);
    }


    // Fallback if API fails or no data
    if(empty($api_data) || isset($api_data['error_message'])) {
        $html = "<div class='alert alert-danger mt-3'>Error querying AI safety engine. " . ($api_data['error_message'] ?? 'API connection failed.') . "</div>";
    } else {
        $html = "<div class='ai-card'>";
        $html .= "<h5>Drug: <strong>".htmlspecialchars($api_data['drug'] ?? $query)."</strong></h5>";
        $html .= "<ul>";
        $html .= "<li>1st Trimester: <strong>".htmlspecialchars($api_data['1st_trimester'] ?? 'Unknown')."</strong></li>";
        $html .= "<li>2nd Trimester: <strong>".htmlspecialchars($api_data['2nd_trimester'] ?? 'Unknown')."</strong></li>";
        $html .= "<li>3rd Trimester: <strong>".htmlspecialchars($api_data['3rd_trimester'] ?? 'Unknown')."</strong></li>";
        $html .= "<li>Fetal Risk: <strong>".htmlspecialchars($api_data['fetal_risk'] ?? 'Unknown')."</strong></li>";
        $html .= "<li>Safer Alternatives: <strong>".htmlspecialchars($api_data['alternatives'] ?? 'No alternatives available')."</strong></li>";
        $html .= "</ul></div>";
    }

    header('Content-Type: application/json');
    echo json_encode(['response' => $html]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Dashboard</title>
<link rel="shortcut icon" href="assets/images/logo.avif" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #121212; color: #fff; font-family: 'Inter', sans-serif; }
.container { max-width: 900px; }
.card { background-color: #1F1F1F; border-radius: 12px; padding: 20px; margin-top: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); color: #e0e0e0; }
.form-control, .form-select { background-color: #2A2A2A; color: #fff; border: 1px solid #555; border-radius: 8px; }
.form-control::placeholder { color: #ccc; }
.form-control:focus, .form-select:focus { background-color: #2A2A2A; color: #fff; border-color: #00D79B; box-shadow: 0 0 0 0.25rem rgba(0,215,155,0.25); }
.btn-success { background-color: #00D79B; border: none; border-radius: 8px; font-weight: 600; }
.btn-success:hover { background-color: #00C68A; }
.btn-danger { background-color: #FF4B5C; border: none; border-radius: 8px; }
a.text-light { color: #00D79B; font-weight: 500; }
a.text-light:hover { color: #48A6FF; text-decoration: none; }
.alert { border-radius: 8px; }
.ai-card { background-color: #2F2F2F; padding: 15px; border-radius: 10px; margin-top: 15px; color: #fff; }
.ai-card h5 { margin-bottom: 10px; }
.ai-card ul { list-style-type: none; padding-left: 0; }
.ai-card ul li { margin-bottom: 5px; }
.autocomplete-suggestions { background: #2A2A2A; border: 1px solid #555; border-radius: 6px; margin-top: 2px; max-height: 150px; overflow-y: auto; }
.autocomplete-suggestion { padding: 5px 10px; cursor: pointer; }
.autocomplete-suggestion:hover { background-color: #00D79B; color: #000; }
.accordion-button { background-color: #2F2F2F !important; color: #fff !important; border-radius: 10px !important; }
.accordion-body { background-color: #2F2F2F !important; color: #e0e0e0 !important; }
.table-custom { color: #e0e0e0; }
.table-custom th { border-bottom: 1px solid #555; }
.warning { background-color: #402800; } /* Subtle background for missed/cancelled appts */
</style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mt-4">
        <h3>Welcome, <?= htmlspecialchars($first_name) ?>!</h3>
        <a href="?logout=1" class="btn btn-danger">Logout</a>
    </div>

    <input type="hidden" id="localPatientId" value="<?= htmlspecialchars($local_patient_id) ?>">
    <input type="hidden" id="emrPatientId" value="<?= htmlspecialchars($emr_patient_id) ?>">

    <div class="card">
        <h4>Pregnancy Safety Engine (PSE)</h4>
        <p>Check if your medication is safe for each trimester and find safer alternatives.</p>
        <input type="text" id="ai-input" class="form-control mb-2" placeholder="Enter medication name" autocomplete="off">
        <div id="suggestions" class="autocomplete-suggestions"></div>
        <div id="ai-response"></div>
    </div>
    
    ---

    <div class="card">
        <h4>Recent Encounters & History</h4>
        <div id="encounterAccordion" class="accordion mb-4">
            <p class="text-center mt-3">Loading past encounters...</p>
        </div>
    </div>

    ---
    
    <div class="card">
        <h4>Appointments</h4>
        <table class="table table-custom" id="appointmentsTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody id="appointmentsTableBody">
                <tr><td colspan="4" class="text-center">Loading appointments...</td></tr>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const localPatientId = document.getElementById('localPatientId').value;
const emrPatientId = document.getElementById('emrPatientId').value;
const encounterAccordion = document.getElementById('encounterAccordion');
const appointmentsTableBody = document.getElementById('appointmentsTableBody');

// --- PSE JavaScript (Unchanged) ---
const aiInput = document.getElementById('ai-input');
const aiResponse = document.getElementById('ai-response');
const suggestions = document.getElementById('suggestions');

const validDrugs = <?= json_encode($valid_drugs) ?>;

// Autocomplete feature
aiInput.addEventListener('input', function() {
    const value = this.value.toLowerCase();
    suggestions.innerHTML = '';
    if(!value) return;
    const matches = validDrugs.filter(d => d.toLowerCase().includes(value));
    matches.forEach(match => {
        const div = document.createElement('div');
        div.classList.add('autocomplete-suggestion');
        div.textContent = match;
        div.addEventListener('click', () => {
            aiInput.value = match;
            suggestions.innerHTML = '';
            sendQuery(match);
        });
        suggestions.appendChild(div);
    });
});

document.addEventListener('click', e => {
    if(e.target !== aiInput) suggestions.innerHTML = '';
});

// Send query when Enter is pressed
aiInput.addEventListener('keypress', function(e) {
    if(e.key === 'Enter') {
        const query = aiInput.value.trim();
        if(!query) return;
        suggestions.innerHTML = '';
        sendQuery(query);
    }
});

function sendQuery(query) {
    aiResponse.innerHTML = "<em>Loading...</em>";
    fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "ai_query=" + encodeURIComponent(query)
    })
    .then(res => res.json())
    .then(data => {
        aiResponse.innerHTML = data.response || "<em>No response from AI.</em>";
        // Remove the timeout for better user experience, or use a shorter one
        // setTimeout(() => { aiResponse.innerHTML = ""; }, 40000); 
    })
    .catch(err => {
        aiResponse.innerHTML = "<em>Error fetching response.</em>";
        console.error(err);
    });
    aiInput.value = "";
}
// --- END PSE JavaScript ---


// --- Patient Data Fetching and Rendering ---

/**
 * Helper function to render patient encounters (Copied/adapted from Doctor Dashboard)
 * Encounters are fetched using the local patient ID via a PHP handler.
 */
function renderEncounters(encounters) {
    if (!encounters || encounters.length === 0 || encounters.error) {
        encounterAccordion.innerHTML = '<p class="text-center mt-3">No encounters found in your record.</p>';
        return;
    }
    
    encounterAccordion.innerHTML = encounters.map((e, index) => {
        const id = `collapse${index}`;
        const headerId = `heading${index}`;
        
        // Render Vitals
        const vitalsHtml = Object.entries(e.vitals || {}).map(([name, data]) => 
            `<li><strong>${name.replace(/_/g, ' ')}:</strong> ${data.value} ${data.unit || ''}</li>`
        ).join('');
        
        // Render Medications
        const medsHtml = (e.medications || []).map(m => 
            `<li><strong>${m.drug || 'N/A'}:</strong> ${m.status || 'N/A'}</li>`
        ).join('');

        // Render Tests
        const testsHtml = (e.tests || []).map(t => 
            `<li><strong>${t.name || 'N/A'}:</strong> ${t.result || 'N/A'}</li>`
        ).join('');

        return `
            <div class="accordion-item" style="background:#1A2822; border-color: #1F3B33;">
                <h2 class="accordion-header" id="${headerId}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${id}" aria-expanded="false" aria-controls="${id}">
                        Encounter on ${e.created_at ? new Date(e.created_at).toLocaleDateString() : 'N/A'} - Diagnosis: ${e.diagnosis || 'None'}
                    </button>
                </h2>
                <div id="${id}" class="accordion-collapse collapse" aria-labelledby="${headerId}" data-bs-parent="#encounterAccordion">
                    <div class="accordion-body text-light">
                        <p><strong>Summary:</strong> ${e.summary || 'N/A'}</p>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <h6>Vitals</h6>
                                <ul class="list-unstyled">${vitalsHtml || '<li>N/A</li>'}</ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Medications</h6>
                                <ul class="list-unstyled">${medsHtml || '<li>N/A</li>'}</ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Tests</h6>
                                <ul class="list-unstyled">${testsHtml || '<li>N/A</li>'}</ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Helper function to render patient appointments (Copied/adapted from Doctor Dashboard)
 * Appointments are fetched using the EMR patient ID via a PHP handler.
 */
function renderAppointments(appts) {
    if (!appts || appts.length === 0 || appts.error) {
        appointmentsTableBody.innerHTML = '<tr><td colspan="4" class="text-center">No upcoming or past appointments found.</td></tr>';
        return;
    }

    appointmentsTableBody.innerHTML = appts.map(a => `<tr class="${a.status === 'Cancelled' || a.status === 'Missed' ? 'warning' : ''}">
        <td>${a.date || '-'}</td>
        <td>${a.time || '-'}</td>
        <td>${a.status || '-'}</td>
        <td>${a.notes || '-'}</td>
    </tr>`).join('');
}


/**
 * Main function to fetch all patient EMR data on load
 */
async function fetchPatientData() {
    if (!localPatientId || !emrPatientId) {
        encounterAccordion.innerHTML = '<p class="text-center mt-3 text-danger">Patient ID missing. Cannot load history.</p>';
        appointmentsTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Patient EMR ID missing. Cannot load appointments.</td></tr>';
        return;
    }

    // 1. Fetch Encounters
    try {
        const res = await fetch(`../src/fetch_dorra_encounter.php?patient_id=${localPatientId}`);
        const encounters = await res.json();
        renderEncounters(encounters);
    } catch(err) { 
        console.error("Error fetching encounters:", err); 
        encounterAccordion.innerHTML = '<p class="text-center mt-3 text-danger">Error fetching encounters</p>';
    }

    // 2. Fetch Appointments
    try {
        const res = await fetch(`../src/fetch_dorra_appointments.php?emr_patient_id=${emrPatientId}`);
        const appts = await res.json();
        renderAppointments(appts);
    } catch(err) {
        console.error("Error fetching appointments:", err);
        appointmentsTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error fetching appointments</td></tr>';
    }
}

// Execute the fetch function on page load
document.addEventListener('DOMContentLoaded', fetchPatientData);
</script>

</body>
</html>