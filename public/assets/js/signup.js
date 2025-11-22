// ---- ELEMENTS ----
const roleSelect = document.getElementById('role');
const genderSelect = document.getElementById('gender');
const patientFields = document.getElementById('patient-fields');
const gestWeeks = document.getElementById('gestational_weeks');

// ---- DEFAULT VALUES ----
if (gestWeeks) {
    gestWeeks.value = 42; // Default gestational weeks
}

// ---- FUNCTION: UPDATE ROLE UI ----
function updateRoleUI() {
    if (roleSelect.value === 'patient') {
        // Show patient fields with fade-in
        patientFields.style.display = 'flex';
        patientFields.style.opacity = '0';
        setTimeout(() => { patientFields.style.opacity = '1'; }, 50);

        // Restrict gender to ONLY female
        genderSelect.innerHTML = `
            <option value="Female" class="bg-card-bg">Female</option>
        `;
    } 
    else if (roleSelect.value === 'doctor') {
        // Hide patient fields with fade-out
        patientFields.style.opacity = '0';
        setTimeout(() => { patientFields.style.display = 'none'; }, 200);

        // Restore full gender list
        genderSelect.innerHTML = `
            <option value="" class="bg-card-bg">Select</option>
            <option value="Male" class="bg-card-bg">Male</option>
            <option value="Female" class="bg-card-bg">Female</option>
            <option value="Other" class="bg-card-bg">Other</option>
        `;
    }
}

// ---- INIT ON PAGE LOAD ----
document.addEventListener('DOMContentLoaded', () => {
    updateRoleUI();  
});

// ---- UPDATE WHEN ROLE CHANGES ----
roleSelect.addEventListener('change', updateRoleUI);

// ---- ENSURE TEXT TYPED IS ALWAYS VISIBLE (white text) ----
document.querySelectorAll("input, select").forEach(field => {
    field.style.color = "#fff";
    field.addEventListener("input", () => {
        field.style.color = "#fff";
    });
});
