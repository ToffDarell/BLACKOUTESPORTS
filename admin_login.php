<?php
// Start session
session_start();

// reCAPTCHA secret key
$recaptcha_secret = "6Lc5BCcrAAAAAJDeCkS1vwFD9s8vGlFS_292v1D0";

// Check for Google login success message
if (isset($_SESSION['admin_success'])) {
    $success = $_SESSION['admin_success'];
    unset($_SESSION['admin_success']);
}

// Check for success message after admin signup
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// Check for password reset success message
if (isset($_SESSION['admin_reset_success'])) {
    $success = $_SESSION['admin_reset_success'];
    unset($_SESSION['admin_reset_success']);
}

// Check for Google login error message
if (isset($_SESSION['admin_error'])) {
    $err = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

// Only destroy admin session if explicitly logging out from admin area
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    // Only destroy admin-related session variables
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_id'])) {
    header("location:admin_dashboard.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$err = ""; // default error message

if (isset($_POST['login'])) {
    $user_email = trim($_POST['user_email']);
    $user_password = trim($_POST['user_password']);
    
    // Verify reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'];
    $verify_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$recaptcha_secret.'&response='.$recaptcha_response);
    $response_data = json_decode($verify_response);

    if (!$response_data->success) {
        $err = "Please complete the reCAPTCHA verification.";
    }
    else if (empty($user_email) || empty($user_password)) {
        $err = "Email and Password cannot be empty.";
    } else {
        $user_password = sha1(md5($user_password)); // double encryption

        // Check admin login from admins table
        $stmt = $conn->prepare("SELECT admin_id, admin_name FROM admins WHERE admin_email = ? AND admin_password = ?");
        $stmt->bind_param('ss', $user_email, $user_password);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($admin_id, $admin_name);
            $stmt->fetch();

            // Set session
            $_SESSION['admin_id'] = $admin_id; // Set admin ID in session
            $_SESSION['admin_name'] = $admin_name;

            // Redirect to dashboard
            header("location:admin_dashboard.php");
            exit();
        } else {
            $err = "Incorrect email or password.";
        }

        $stmt->close();
    }
}

// Handle Google login for admins
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'google') {
    $admin_email = $_SESSION['user_email'];
    
    // Check if admin exists in database
    $stmt = $conn->prepare("SELECT admin_id, admin_name FROM admins WHERE admin_email = ?");
    $stmt->bind_param('s', $admin_email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // Admin exists, log them in
        $stmt->bind_result($admin_id, $admin_name);
        $stmt->fetch();
        
        $_SESSION['admin_id'] = $admin_id;
        $_SESSION['admin_name'] = $admin_name;
        
        // Unset the Google session variables to avoid conflicts
        unset($_SESSION['user_type']);
        unset($_SESSION['user_email']);
        
        header("location:admin_dashboard.php");
        exit();
    } else {
        $err = "No admin account found with this Google account. Please contact the system administrator.";
    }
    
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="badges.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body>
    <!-- Particles JS Background -->
    <div id="particles-js"></div>
    
    <!-- Main Content -->
    <div class="container">
        <div class="signup-container">
            <div class="admin-badge">ADMIN AREA</div>
            <div class="logo">
                <img src="images/blackout.jpg" alt="Blackout Esports Logo"
                    onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'">
            </div>

            <h2>BLACKOUT ESPORTS</h2>

            <?php if (!empty($err)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $err; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success" role="alert" style="background-color: #000000; color: #ffffff; border: 2px solid #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" role="form">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="user_email" class="form-control" id="email"
                                placeholder="Enter your email">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div id="emailAvailability" class="form-text"></div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="user_password" class="form-control" id="password"
                                placeholder="Enter your password">
                            <button type="button" class="toggle-password" tabindex="-1" onclick="togglePasswordVisibility()">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="g-recaptcha" data-sitekey="6Lc5BCcrAAAAAOeBh1PZSfClWIB-gv2gX2kflAQy"></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4" name="login">
                        <i class="fas fa-shield-alt me-2"></i> Admin Login
                    </button>
                </div>
            </form>

            <div class="divider">OR</div>

            <a href="googleAuth/google-admin-login.php" class="google-btn">
                <img src="https://www.google.com/favicon.ico" alt="Google Logo">
                Sign in with Google
            </a>

            <div class="text-center mt-3">
                <p>Don't have an admin account? <a href="admin_signup.php">Sign up here</a></p>
            </div>
            <div class="text-center mt-3">
                <p>Forgot your password? <a href="admin_forgot_password.php">Reset Password</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="particles.js"></script>
</body>

</html>
