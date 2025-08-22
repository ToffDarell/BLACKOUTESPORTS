<?php
// Start session
session_start();

// Preserve admin session if it exists
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : null;

// Check for Google login success message
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check for success message after signup
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// Check for password reset success message
if (isset($_SESSION['reset_success'])) {
    $success = $_SESSION['reset_success'];
    unset($_SESSION['reset_success']);
}

// Check for Google login error message
if (isset($_SESSION['error'])) {
    $err = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Restore admin session if it existed
if ($admin_id) {
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_name'] = $admin_name;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

// reCAPTCHA secret key
$recaptcha_secret = "6Lc5BCcrAAAAAJDeCkS1vwFD9s8vGlFS_292v1D0";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
        $user_password = sha1(md5($user_password)); // double encrypt to increase security

        $stmt = $conn->prepare("SELECT user_email, user_password, user_id FROM users WHERE (user_email = ? AND user_password = ?)"); // SQL to log in user
        $stmt->bind_param('ss', $user_email, $user_password); // bind fetched parameters
        $stmt->execute(); // execute bind
        $stmt->bind_result($db_email, $db_password, $user_id); // bind result
        $rs = $stmt->fetch();

        if ($rs && $db_email === $user_email && $db_password === $user_password) {
            $_SESSION['user_id'] = $user_id;
            header("location:dashboard.php");
        } else {
            $err = "Incorrect Authentication Credentials";
        }
        $stmt->close();
    }
}

// Handle Google login
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'google') {
    // Check if required Google login session variables exist
    if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_name'])) {
        // Only show this error if this is an actual Google login attempt, not a logout
        if (isset($_GET['google_login']) && $_GET['google_login'] === 'true') {
            $err = "Missing Google login information. Please try again.";
        } else {
            // If this is a regular session expiration or logout, just unset the session variables
            unset($_SESSION['user_type']);
        }
    } else {
        $user_email = $_SESSION['user_email'];
        
        // Check if user exists in database
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
        if ($stmt === false) {
            $err = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param('s', $user_email);
            $stmt->execute();
            $stmt->bind_result($user_id);
            $rs = $stmt->fetch();
            
            if ($rs) {
                // User exists, log them in
                $_SESSION['user_id'] = $user_id;
                header("location:dashboard.php");
                exit();
            } else {
                // User doesn't exist, create a new account
                $user_name = $_SESSION['user_name'];
                $user_image = isset($_SESSION['user_image']) ? $_SESSION['user_image'] : '';
                
                // Generate a random password for the user
                $random_password = bin2hex(random_bytes(8));
                $hashed_password = sha1(md5($random_password));
                
                // Insert new user
                $insert_stmt = $conn->prepare("INSERT INTO users (user_name, user_email, user_password, user_image) VALUES (?, ?, ?, ?)");
                if ($insert_stmt === false) {
                    $err = "Database error while creating account: " . $conn->error;
                } else {
                    $insert_stmt->bind_param('ssss', $user_name, $user_email, $hashed_password, $user_image);
                    
                    if ($insert_stmt->execute()) {
                        $new_user_id = $conn->insert_id;
                        $_SESSION['user_id'] = $new_user_id;
                        header("location:dashboard.php");
                        exit();
                    } else {
                        $err = "Failed to create account. Please try again.";
                    }
                    $insert_stmt->close();
                }
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <div class="user-badge">PLAYER LOGIN</div>
            <div class="logo">
                <img src="images/blackout.jpg" alt="Blackout Esports Logo"
                    onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'">
            </div>

            <h2>BLACKOUT ESPORTS</h2>

            <?php if (isset($err)): ?>
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
                        <i class="fas fa-gamepad me-2"></i> Login
                    </button>
                </div>
            </form>

            <div class="divider">OR</div>

            <a href="googleAuth/google-login.php" class="google-btn">
                <img src="https://www.google.com/favicon.ico" alt="Google Logo">
                Sign in with Google
            </a>

            <div class="text-center mt-3">
                <p>Don't have an account? <a href="sign.php">Sign up here</a></p>
                <p>Forgot Password? <a href="forgot_password.php">Reset Password</a></p>
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