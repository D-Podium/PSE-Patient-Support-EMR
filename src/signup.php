<?php
session_start();
// NOTE: Assuming this file is located in a structure where /../src/config.php is accessible.
// The file path for config.php and the database/API logic is kept as per your original request.
require __DIR__ . '/../src/config.php'; // Database + .env loaded

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- SERVER-SIDE LOGIC (Remains the same as your original code) ---
    // Assuming $conn is available from config.php and is a mysqli connection
    if (!isset($conn) || $conn->connect_error) {
        $error = "Database connection failed. Please check config.";
    } else {
        // Sanitize user input
        $first_name = $conn->real_escape_string($_POST['first_name']);
        $last_name 	= $conn->real_escape_string($_POST['last_name']);
        $email 	    = $conn->real_escape_string($_POST['email']);
        $password 	= password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
        $role 	    = $conn->real_escape_string($_POST['role']); // 'patient' or 'doctor'
        $dob 	    = $conn->real_escape_string($_POST['dob']);
        $gender 	= $conn->real_escape_string($_POST['gender']);
        $phone 	    = $conn->real_escape_string($_POST['phone']);
        
        // Optional patient fields
        $trimester 	= $role === 'patient' ? ($conn->real_escape_string($_POST['trimester']) ?: NULL) : NULL;
        $weeks 	    = $role === 'patient' ? ($conn->real_escape_string($_POST['gestational_weeks']) ?: 42) : NULL;
    
        if($role === 'patient') {
            // 1. Create patient on Dorra EMR API using AI endpoint
            // NOTE: API_URL and API_KEY must be defined in your /../src/config.php
            // $apiUrl = (defined('API_URL') ? API_URL : 'https://hackathon-api.aheadafrica.org');
            // $apiKey = (defined('API_KEY') ? API_KEY : 'YOUR_API_KEY_HERE');

            $prompt = "Create patient $first_name $last_name with email $email, DOB $dob, Gender 'Female', Phone $phone";

            $ch = curl_init(API_URL . "/v1/ai/patient");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Token " . API_KEY,
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => $prompt]));
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err || $http_code >= 400) {
                $error = "Dorra API error ($http_code): " . ($err ?: $response);
            } else {
                $data = json_decode($response, true);
                // Dorra's AI endpoint usually returns the patient object directly if successful, or an error payload.
                if (!isset($data['id'])) {
                    $error = "Failed to create patient on Dorra EMR. Response: " . htmlspecialchars($response);
                } else {
                    $emr_patient_id = $data['id'];

                    // 2. Insert into local tblpatient
                    $sql = "INSERT INTO tblpatient 
                        (emr_patient_id, first_name, last_name, email, password, trimester, gestational_weeks, created_at)
                        VALUES ('$emr_patient_id', '$first_name', '$last_name', '$email', '$password', '$trimester', '$weeks', NOW())";

                    if ($conn->query($sql)) {
                        $_SESSION['patient_id'] = $conn->insert_id;
                        $_SESSION['emr_patient_id'] = $emr_patient_id;
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['role'] = 'patient';
                        $success = "Account created successfully! Redirecting to dashboard...";
                        header("Location: ../public/dashboard.php");
                        // exit;
                    } else {
                        $error = "Error creating local patient: " . $conn->error;
                    }
                }
            }
        } elseif ($role === 'doctor') {
            // Insert into tbldoctor table
            $sql = "INSERT INTO tbldoctor 
                (first_name, last_name, email, password, created_at)
                VALUES ('$first_name', '$last_name', '$email', '$password', NOW())";
            if ($conn->query($sql)) {
                $_SESSION['doctor_id'] = $conn->insert_id;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['role'] = 'doctor';
                $success = "Doctor account created successfully! Redirecting to dashboard...";
                header("Location: ../public/doctor_dashboard.php");
                // exit;
            } else {
                $error = "Error creating doctor account: " . $conn->error;
            }
        } else {
            $error = "Invalid role selected.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | EMR Safety Engine</title>
    <link rel="shortcut icon" href="../public/assets/images/logo.avif" type="image/x-icon">
    <!-- Load Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/assets/css/signup.css">
</head>
<body>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6">
                <div class="card card-custom p-4 p-md-5">

                    <!-- Logo Section -->
                    <div class="d-flex justify-content-center align-items-center mb-4 gap-3 logo-section">
                        <img src="../public/assets/images/logo.avif" 
                            alt="App Logo" 
                            class="img-fluid" 
                            style="width:72px; height:72px; border-radius:0.5rem;">

                        <h1 class="app-title m-0" 
                            style="font-size:1.8rem; font-weight:700; color:var(--color-accent-teal);">
                            Serielle Health
                        </h1>
                    </div>

                    <!-- Title -->
                    <h2 class="text-3xl font-weight-bold text-center mb-2" style="color:var(--color-text-light);">
                        Create Account
                    </h2>
                    <p class="text-center text-muted mb-4">
                        Join our platform as a <span class="link-coral">Patient</span> or <span class="link-coral">Doctor</span>.
                    </p>

                    <!-- Error/Success Messages -->
                    <?php if(isset($error)): ?>
                        <div id="error-message" class="alert alert-custom-error mb-4" role="alert">
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($success)): ?>
                        <div id="success-message" class="alert alert-custom-success mb-4" role="alert">
                            <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        
                        <!-- Role Selection -->
                        <div class="mb-3">
                            <label for="role" class="form-label text-white">Role</label>
                            <select id="role" name="role" required class="form-select form-select-custom p-3">
                                <option value="" class="bg-card-bg text-muted">Select your role</option>
                                <option value="patient" class="bg-card-bg">Patient (Pregnant)</option>
                                <option value="doctor" class="bg-card-bg">Doctor / Clinician</option>
                            </select>
                        </div>

                        <!-- Name Fields (Responsive Grid) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label text-white">First Name</label>
                                <input type="text" id="first_name" name="first_name" required placeholder="John" class="form-control form-control-custom p-3">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label text-white">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required placeholder="Doe" class="form-control form-control-custom p-3">
                            </div>
                        </div>

                        <!-- Email and Password -->
                        <div class="mb-3">
                            <label for="email" class="form-label text-white">Email</label>
                            <input type="email" id="email" name="email" required placeholder="you@example.com" class="form-control form-control-custom p-3">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label text-white">Password</label>
                            <input type="password" id="password" name="password" required placeholder="••••••••" class="form-control form-control-custom p-3">
                        </div>

                        <!-- DOB, Gender, Phone (Responsive Grid) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="dob" class="form-label text-white">DOB</label>
                                <input type="date" id="dob" name="dob" value="<?= date('Y-m-d') ?>" required class="form-control form-control-custom p-3">
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="form-label text-white">Gender</label>
                                <select id="gender" name="gender" required class="form-select form-select-custom p-3">
                                    <option value="" class="bg-card-bg">Select</option>
                                    <option value="Male" class="bg-card-bg">Male</option>
                                    <option value="Female" class="bg-card-bg">Female</option>
                                    <option value="Other" class="bg-card-bg">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="phone" class="form-label text-white">Phone</label>
                                <input type="tel" id="phone" name="phone" required placeholder="+254..." class="form-control form-control-custom p-3">
                            </div>
                        </div>

                        <!-- Patient-Specific Fields (Hidden by default, shown in a row) -->
                        <div id="patient-fields" class="row g-3 pt-3 mb-4 border-top border-secondary-subtle" style="display:none;">
                            <div class="col-md-6">
                                <label for="trimester" class="form-label text-white">Trimester</label>
                                <select id="trimester" name="trimester" class="form-select form-select-custom p-3">
                                    <option value="" class="bg-card-bg">Select trimester</option>
                                    <option value="1" class="bg-card-bg">1st</option>
                                    <option value="2" class="bg-card-bg">2nd</option>
                                    <option value="3" class="bg-card-bg">3rd</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="gestational_weeks" class="form-label text-white">Gestational Weeks</label>
                                <input type="number" id="gestational_weeks" name="gestational_weeks" value="42" min="0" max="42" placeholder="42" class="form-control form-control-custom p-3">
                            </div>
                        </div>

                        <!-- Submit Button (Primary Accent Teal) -->
                        <button type="submit" class="btn btn-teal w-100 mt-3">
                            Sign Up Now
                        </button>
                    </form>

                    <!-- Login Link -->
                    <p class="mt-4 text-center" style="color: #fff; font-size: 0.9rem;">
                        Already have an account? 
                        <a href="index.php" style="color: #00D79B; font-weight: 500; text-decoration: none;">Log In</a>
                    </p>

                </div>
            </div>
        </div>
    </div>

    <!-- Load Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript for Role Toggle and Redirection -->
    <script src="../public/assets/js/signup.js"></script>

</body>
</html>