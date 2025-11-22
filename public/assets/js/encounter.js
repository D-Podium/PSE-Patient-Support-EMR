// DOM elements
const addEncounterBtn = document.getElementById('addEncounterBtn');
const addEncounterModal = new bootstrap.Modal(document.getElementById('addEncounterModal'));
const addEncounterForm = document.getElementById('addEncounterForm');
const encounterPatientId = document.getElementById('encounterPatientId');

// Open Add Encounter modal
addEncounterBtn?.addEventListener('click', () => {
    const patientSelect = document.getElementById('patientSelect');
    const patientId = patientSelect?.value;
    if (!patientId) return alert('Please select a patient first.');
    encounterPatientId.value = patientId;
    addEncounterForm.reset();
    addEncounterModal.show();
});

// Submit Add Encounter form
addEncounterForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(addEncounterForm);
    const payload = Object.fromEntries(formData.entries());

    // Parse JSON fields if entered as JSON strings
    try { payload.medications = JSON.parse(payload.medications); } catch { return alert("Invalid JSON in Medications"); }
    try { payload.tests = payload.tests ? JSON.parse(payload.tests) : []; } catch { return alert("Invalid JSON in Tests"); }
    try { payload.vitals = payload.vitals ? JSON.parse(payload.vitals) : {}; } catch { return alert("Invalid JSON in Vitals"); }

    try {
        const res = await fetch('../src/create_dorra_encounter.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            alert('Encounter created successfully!');
            addEncounterModal.hide();
            // Refresh encounters
            const patientSelect = document.getElementById('patientSelect');
            patientSelect?.dispatchEvent(new Event('change'));
        } else {
            alert(data.message || 'Failed to create encounter.');
        }
    } catch (err) {
        console.error(err);
        alert('Error creating encounter.');
    }
});
