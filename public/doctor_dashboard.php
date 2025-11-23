<?php
session_start();
// Assuming config.php and dorra_config.php are correctly located relative to this file
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/dorra_config.php';

if (!isset($_SESSION['doctor_id'])) {
    header("Location: ../src/index.php");
    exit;
}

$doctor_name = $_SESSION['first_name'] ?? "Doctor";

// Fetch all local patients
// NOTE: $conn is assumed to be defined and connected from config.php
$patients = $conn->query("SELECT patient_id, first_name, last_name, emr_patient_id FROM tblpatient ORDER BY first_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="shortcut icon" href="assets/images/logo.avif" type="image/x-icon">
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<div class="navbar">
    <div class="logo"><img src="assets/images/logo.avif" alt="App Logo" >SerielleHealth</div>
    <a href="../src/logout.php" class="btn btn-teal">Logout</a>
</div>

<div class="container my-5">

    <div class="card card-custom mx-auto" style="max-width:1000px;">
        <h2 class="mb-3">Welcome Dr. <?php echo htmlspecialchars($doctor_name); ?></h2>
        <p class="mb-4">Select a patient to view encounters, appointments, and AI-assisted EMR suggestions:</p>

        <div class="mb-4">
            <label class="form-label">Patient</label>
            <select id="patientSelect" class="form-select">
                <option value="">-- Choose Patient --</option>
                <?php while($p = $patients->fetch_assoc()): ?>
                    <option value="<?php echo $p['patient_id']; ?>" data-emr="<?php echo $p['emr_patient_id']; ?>">
                        <?php echo htmlspecialchars($p['first_name'] . " " . $p['last_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-4">
            <button id="addEncounterBtn" class="btn btn-teal me-2" style="display:none;">Add Encounter</button>
            <button id="addAppointmentBtn" class="btn btn-primary me-2" style="display:none;">Add Appointment</button>
            <button id="aiEmrBtn" class="btn btn-info" style="display:none;">AI EMR Suggestion</button>
        </div>
    </div>

    <div class="card card-custom mx-auto" style="max-width:1000px;">
        <h4>Patient Encounters</h4>
        <div id="encounterAccordion" class="accordion mb-4">
            <p class="text-center mt-3">No patient selected</p>
        </div>
    </div>

    <div class="card card-custom mx-auto" style="max-width:1000px;">
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
            <tbody>
                <tr><td colspan="4" class="text-center">Please select a patient to view appointments</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card card-custom mx-auto" style="max-width:1000px;">
        <div id="aiCard" class="ai-card" style="display:none;">
            <h5>AI EMR Recommendation</h5>
            <div id="aiResponse">No AI suggestion returned</div>
        </div>
    </div>
</div>

<!-- Modal for Add Encounter (Existing) -->
<div class="modal fade" id="addEncounterModal" tabindex="-1" aria-labelledby="addEncounterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#1A2822; color:#fff;">
            <div class="modal-header">
                <h5 class="modal-title" id="addEncounterModalLabel">Add New Encounter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEncounterForm">
                    <div class="mb-3">
                        <label class="form-label">Diagnosis (optional)</label>
                        <input type="text" class="form-control" name="diagnosis" placeholder="Enter diagnosis if any">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Summary (optional)</label>
                        <textarea class="form-control" name="summary" rows="3" placeholder="Enter summary if any"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Medications (optional)</label>
                        <table class="table table-dark" id="medicationsTable">
                            <thead>
                                <tr>
                                    <th>Drug Name</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-teal" id="addMedBtn">Add Medication</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tests (optional)</label>
                        <table class="table table-dark" id="testsTable">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Result</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-teal" id="addTestBtn">Add Test</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Vitals (optional)</label>
                        <table class="table table-dark" id="vitalsTable">
                            <thead>
                                <tr>
                                    <th>Vital Name</th>
                                    <th>Value</th>
                                    <th>Unit</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-teal" id="addVitalBtn">Add Vital</button>
                    </div>

                    <input type="hidden" id="encounterPatientId" name="patient_id">
                    <button type="submit" class="btn btn-teal">Create Encounter</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- NEW Modal for Add Appointment -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-labelledby="addAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#1A2822; color:#fff;">
            <div class="modal-header">
                <h5 class="modal-title" id="addAppointmentModalLabel">Schedule New Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addAppointmentForm">
                    <div class="mb-3">
                        <label class="form-label">Appointment Date</label>
                        <input type="date" class="form-control" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Appointment Time</label>
                        <input type="time" class="form-control" name="time" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Reason for visit, required preparation, etc."></textarea>
                    </div>
                    <input type="hidden" id="appointmentPatientId" name="patient_id">
                    <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const patientSelect = document.getElementById('patientSelect');
const encounterAccordion = document.getElementById('encounterAccordion');
const addEncounterBtn = document.getElementById('addEncounterBtn');
const addAppointmentBtn = document.getElementById('addAppointmentBtn'); // NEW BUTTON
const aiEmrBtn = document.getElementById('aiEmrBtn');
const aiCard = document.getElementById('aiCard');
const aiResponse = document.getElementById('aiResponse');
const appointmentsTableBody = document.querySelector("#appointmentsTable tbody");

// Helper function to render encounters
function renderEncounters(encounters) {
    if (!encounters || encounters.length === 0 || encounters.error) {
        encounterAccordion.innerHTML = '<p class="text-center mt-3">No encounters found</p>';
        return;
    }
    
    encounterAccordion.innerHTML = encounters.map((e, index) => {
        const id = `collapse${index}`;
        const headerId = `heading${index}`;
        
        // Render Vitals (Vitals object in PHP is converted to key/value pairs here)
        const vitalsHtml = Object.entries(e.vitals || {}).map(([name, data]) => 
            `<li><strong>${name.replace(/_/g, ' ')}:</strong> ${data.value} ${data.unit || ''}</li>`
        ).join('');
        
        // Render Medications (Array)
        const medsHtml = (e.medications || []).map(m => 
            `<li><strong>${m.drug || 'N/A'}:</strong> ${m.status || 'N/A'}</li>`
        ).join('');

        // Render Tests (Array)
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

// PATIENT SELECTION CHANGE HANDLER
patientSelect.addEventListener('change', async () => {
    const patientId = patientSelect.value;
    const emrId = patientSelect.selectedOptions[0]?.dataset?.emr;
    
    // Clear previous data
    encounterAccordion.innerHTML = '';
    appointmentsTableBody.innerHTML = '<tr><td colspan="4" class="text-center">Please select a patient to view appointments</td></tr>';
    aiCard.style.display='none';

    // Toggle buttons
    const isPatientSelected = !!patientId;
    addEncounterBtn.style.display = isPatientSelected ? 'inline-block' : 'none';
    addAppointmentBtn.style.display = isPatientSelected ? 'inline-block' : 'none'; // NEW: Toggle Appointment button
    aiEmrBtn.style.display = isPatientSelected ? 'inline-block' : 'none';

    if (!patientId || !emrId) return;

    // 1. Fetch Encounters (Requires local patientId for PHP lookup)
    try {
        const res = await fetch(`../src/fetch_dorra_encounter.php?patient_id=${patientId}`);
        const encounters = await res.json();
        renderEncounters(encounters);
    } catch(err) { 
        console.error("Error fetching encounters:", err); 
        encounterAccordion.innerHTML = '<p class="text-center mt-3 text-danger">Error fetching encounters</p>';
    }

    // 2. Fetch Appointments (Requires emr_patient_id as per PHP logic)
    try {
        const res = await fetch(`../src/fetch_dorra_appointments.php?emr_patient_id=${emrId}`);
        const appts = await res.json();
        
        if (!appts || appts.length === 0 || appts.error) {
            appointmentsTableBody.innerHTML = '<tr><td colspan="4" class="text-center">No appointments found</td></tr>';
        } else {
            appointmentsTableBody.innerHTML = appts.map(a => `<tr class="${a.status === 'Cancelled' || a.status === 'Missed' ? 'warning' : 'safe'}">
                <td>${a.date || '-'}</td>
                <td>${a.time || '-'}</td>
                <td>${a.status || '-'}</td>
                <td>${a.notes || '-'}</td>
            </tr>`).join('');
        }
    } catch(err) {
        console.error("Error fetching appointments:", err);
        appointmentsTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error fetching appointments</td></tr>';
    }
});

// ADD ENCOUNTER BUTTON (Existing)
addEncounterBtn.addEventListener('click', () => {
    const patientId = patientSelect.value;
    if(!patientId) return alert('Select a patient first');
    document.getElementById('encounterPatientId').value = patientId;
    
    // Clear form
    document.getElementById('addEncounterForm').reset();
    document.querySelector('#medicationsTable tbody').innerHTML = '';
    document.querySelector('#testsTable tbody').innerHTML = '';
    document.querySelector('#vitalsTable tbody').innerHTML = '';
    
    new bootstrap.Modal(document.getElementById('addEncounterModal')).show();
});

// ADD APPOINTMENT BUTTON (NEW)
addAppointmentBtn.addEventListener('click', () => {
    const patientId = patientSelect.value;
    if(!patientId) return alert('Select a patient first');
    
    // Set the hidden input value for the form submission
    document.getElementById('appointmentPatientId').value = patientId;
    
    // Clear form and show modal
    document.getElementById('addAppointmentForm').reset();
    new bootstrap.Modal(document.getElementById('addAppointmentModal')).show();
});


// SUBMIT ENCOUNTER FORM (Existing)
document.getElementById('addEncounterForm').addEventListener('submit', async e=>{
    e.preventDefault();

    const patientId = document.getElementById('encounterPatientId').value;
    if(!patientId) return alert('Local Patient ID missing');

    const form = e.target;
    
    const payload = { 
        patient_id: patientId 
    };

    const diagnosis = form.diagnosis.value.trim();
    const summary = form.summary.value.trim();
    if(diagnosis) payload.diagnosis = diagnosis;
    if(summary) payload.summary = summary;

    // Medications
    const meds = [];
    document.querySelectorAll('#medicationsTable tbody tr').forEach(row=>{
        const drug = row.querySelector('.med-drug')?.value.trim();
        const status = row.querySelector('.med-status')?.value.trim();
        if(drug) meds.push({ drug, status });
    });
    if(meds.length) payload.medications = meds;

    // Tests
    const tests = [];
    document.querySelectorAll('#testsTable tbody tr').forEach(row=>{
        const testName = row.querySelector('.test-name')?.value.trim();
        const result = row.querySelector('.test-result')?.value.trim();
        if(testName) tests.push({ name: testName, result });
    });
    if(tests.length) payload.tests = tests;

    // Vitals
    const vitals = {};
    document.querySelectorAll('#vitalsTable tbody tr').forEach(row=>{
        const name = row.querySelector('.vital-name')?.value.trim();
        const value = row.querySelector('.vital-value')?.value.trim();
        const unit = row.querySelector('.vital-unit')?.value.trim();
        // Convert vital name to snake_case for consistency with API structure
        const snakeCaseName = name.toLowerCase().replace(/\s+/g, '_');
        if(snakeCaseName && value) vitals[snakeCaseName] = { value, unit };
    });
    if(Object.keys(vitals).length) payload.vitals = vitals;

    try{
        const res = await fetch('../src/create_dorra_encounter.php',{
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success){
            alert('Encounter created successfully! ID: ' + data.encounter_id);
            patientSelect.dispatchEvent(new Event('change')); 
            bootstrap.Modal.getInstance(document.getElementById('addEncounterModal')).hide();
        } else alert('Error creating encounter: ' + (data.message || 'Unknown error.'));
    }catch(err){ console.error(err); alert('Fatal error creating encounter.'); }
});


// SUBMIT APPOINTMENT FORM (NEW)
document.getElementById('addAppointmentForm').addEventListener('submit', async e=>{
    e.preventDefault();

    const patientId = document.getElementById('appointmentPatientId').value;
    if(!patientId) return alert('Local Patient ID missing');

    const form = e.target;
    
    const payload = { 
        patient_id: patientId, // Local ID to be mapped in PHP
        date: form.date.value,
        time: form.time.value,
        status: form.status.value,
        notes: form.notes.value.trim()
    };

    try{
        const res = await fetch('../src/create_appointment.php',{ // NEW SCRIPT
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success){
            alert('Appointment scheduled successfully! ID: ' + data.appointment_id);
            // Re-fetch appointments for the current patient
            patientSelect.dispatchEvent(new Event('change')); 
            bootstrap.Modal.getInstance(document.getElementById('addAppointmentModal')).hide();
        } else alert('Error scheduling appointment: ' + (data.message || 'Unknown error.'));
    }catch(err){ console.error(err); alert('Fatal error scheduling appointment.'); }
});


// AI EMR BUTTON (Existing)
aiEmrBtn.addEventListener('click', async ()=>{
    const patientId = patientSelect.value;
    const emrId = patientSelect.selectedOptions[0]?.dataset?.emr;
    
    if(!emrId) return alert('Invalid patient selection');

    aiCard.style.display='block';
    aiResponse.style.color='#ffffff';
    aiResponse.innerHTML='<div class="spinner-border text-teal" role="status"><span class="visually-hidden">Loading...</span></div> Fetching AI recommendation...';

    try{
        const res = await fetch('../src/ai_emr_handler.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                patient_id: patientId, 
                emr_patient_id: emrId, 
                prompt:'Summarize patient history and suggest next steps for diagnosis and treatment. Provide a bulleted list of recommendations.'
            })
        });
        const data = await res.json();
        
        if (data.error_code && data.error_code !== 200) {
            aiResponse.innerHTML = `<span class="text-danger">Error (${data.error_code}): ${data.response || 'API failure'}</span>`;
        } else {
            aiResponse.innerHTML = data.response || 'No AI suggestion returned';
        }

    }catch(err){ 
        console.error(err); 
        aiResponse.innerHTML='<span class="text-danger">Could not connect to AI service.</span>'; 
    }
});

// FUNCTIONS TO MANAGE DYNAMIC ROWS (Medications, Tests, Vitals)
function addMedicationRow() {
    const tableBody = document.querySelector('#medicationsTable tbody');
    const newRow = tableBody.insertRow();
    newRow.innerHTML = `
        <td><input type="text" class="form-control med-drug" placeholder="e.g. Amoxicillin"></td>
        <td>
            <select class="form-select med-status">
                <option value="active">Active</option>
                <option value="completed">Completed</option>
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
    `;
}

function addTestRow() {
    const tableBody = document.querySelector('#testsTable tbody');
    const newRow = tableBody.insertRow();
    newRow.innerHTML = `
        <td><input type="text" class="form-control test-name" placeholder="e.g. CBC"></td>
        <td><input type="text" class="form-control test-result" placeholder="e.g. Normal/Pending"></td>
        <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
    `;
}

function addVitalRow() {
    const tableBody = document.querySelector('#vitalsTable tbody');
    const newRow = tableBody.insertRow();
    newRow.innerHTML = `
        <td><input type="text" class="form-control vital-name" placeholder="e.g. Blood Pressure"></td>
        <td><input type="text" class="form-control vital-value" placeholder="e.g. 120/80"></td>
        <td><input type="text" class="form-control vital-unit" placeholder="e.g. mmHg"></td>
        <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
    `;
}

document.getElementById('addMedBtn').addEventListener('click', addMedicationRow);
document.getElementById('addTestBtn').addEventListener('click', addTestRow);
document.getElementById('addVitalBtn').addEventListener('click', addVitalRow);

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
    }
});
</script>

</body>
</html>
