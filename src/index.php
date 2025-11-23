<?php
session_start();
require __DIR__ . '/../src/config.php'; 

// If already logged in redirect
if(isset($_SESSION['patient_id'])){
    header("Location: dashboard.php");
    exit;
}
if(isset($_SESSION['doctor_id'])){
    header("Location: doctor_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // 1️⃣ Try logging in as patient
    $p_sql = "SELECT * FROM tblpatient WHERE email='$email' LIMIT 1";
    $p_result = $conn->query($p_sql);

    if ($p_result && $p_result->num_rows > 0) {
        $user = $p_result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['patient_id'] = $user['patient_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];

            header("Location: ../public/dashboard.php");
            exit;
        } 
    }

    // 2️⃣ Try logging in as doctor
    $d_sql = "SELECT * FROM tbldoctor WHERE email='$email' LIMIT 1";
    $d_result = $conn->query($d_sql);

    if ($d_result && $d_result->num_rows > 0) {
        $doc = $d_result->fetch_assoc();

        if (password_verify($password, $doc['password'])) {
            $_SESSION['doctor_id'] = $doc['doctor_id'];
            $_SESSION['first_name'] = $doc['first_name'];
            $_SESSION['last_name']  = $doc['last_name'];

            header("Location: ../public/doctor_dashboard.php");
            exit;
        }
    }

    // If both fail
    $error = "Invalid login credentials.";
}
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - SerielleHealth</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../public/assets/css/signin.css">
<link rel="shortcut icon" href="../public/assets/images/logo.avif" type="image/x-icon">
</head>
<body>

<div class="container vh-100 d-flex justify-content-center align-items-center">

<div class="card w-100" style="max-width:400px;">

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

    <h4 class="text-center mb-4" style="color:#ccc;">Welcome Back</h4>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-light">Email</label>
            <input type="email" class="form-control" name="email" placeholder="Enter email" required>
        </div>

        <div class="mb-3">
            <label class="form-label text-light">Password</label>
            <input type="password" class="form-control" name="password" placeholder="Enter password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-2">Login</button>
    </form>

    <p class="mt-4 text-center" style="color:#ccc;">
        Don't have an account? 
        <a href="signup.php" class="coral-link">Sign Up</a>
    </p>

</div>

</div>

</body>
</html>
