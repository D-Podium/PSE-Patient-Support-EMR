document.addEventListener('DOMContentLoaded', () => {
    const patientContainer = document.getElementById('patient-cards');

    async function loadPatients() {
        patientContainer.innerHTML = '<p class="text-center">Loading patients...</p>';

        try {
            const res = await fetch('../src/get_patient_meds.php');
            if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
            
            const data = await res.json();

            if (data.status && data.patients.length > 0) {
                // Clear container
                patientContainer.innerHTML = '';

                data.patients.forEach(patient => {
                    // Build medications list
                    const medsHtml = patient.medications.map(med => {
                        let badgeClass = 'bg-success';
                        if (med.severity === 'Caution') badgeClass = 'bg-warning text-dark';
                        if (med.severity === 'Unsafe') badgeClass = 'bg-danger';

                        return `
                            <li class="list-group-item">
                                <strong>${med.med_name}</strong>
                                <span class="badge ${badgeClass} ms-2">${med.severity}</span>
                                <div class="mt-1">
                                    <small>Trimester Safety:</small>
                                    <span class="ms-2" style="color:${med.safe_first_trimester ? '#00D79B' : '#FF7E4F'}">1st ${med.safe_first_trimester ? '✅' : '❌'}</span>
                                    <span class="ms-2" style="color:${med.safe_second_trimester ? '#00D79B' : '#FF7E4F'}">2nd ${med.safe_second_trimester ? '✅' : '❌'}</span>
                                    <span class="ms-2" style="color:${med.safe_third_trimester ? '#00D79B' : '#FF7E4F'}">3rd ${med.safe_third_trimester ? '✅' : '❌'}</span>
                                </div>
                                ${med.fetal_risk ? `<div><small>Fetal Risk: ${med.fetal_risk}</small></div>` : ''}
                                ${med.suggested_alternative ? `<div><small>Alternative: ${med.suggested_alternative}</small></div>` : ''}
                            </li>
                        `;
                    }).join('');

                    // Build patient card
                    const patientCard = document.createElement('div');
                    patientCard.classList.add('card', 'mb-3');
                    patientCard.innerHTML = `
                        <div class="card-body">
                            <h5 class="card-title">${patient.first_name} ${patient.last_name}</h5>
                            <p class="card-subtitle mb-2 text-muted">Trimester: ${patient.trimester || 'N/A'}, Weeks: ${patient.gestational_weeks || 'N/A'}</p>
                            <ul class="list-group list-group-flush">${medsHtml}</ul>
                        </div>
                    `;
                    patientContainer.appendChild(patientCard);
                });
            } else {
                patientContainer.innerHTML = '<p class="text-center">No patients found.</p>';
            }

        } catch (err) {
            patientContainer.innerHTML = `<p class="text-center text-danger">Error loading patients: ${err.message}</p>`;
        }
    }

    loadPatients();
    setInterval(loadPatients, 60000); // Auto-refresh every 60 sec
});
