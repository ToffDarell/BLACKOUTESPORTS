<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if (isset($_POST['signup'])) {
    $admin_name = trim($_POST['admin_name']);
    $admin_email = trim($_POST['admin_email']);
    $admin_phone = trim($_POST['admin_phone']);
    $admin_password = trim($_POST['admin_password']);
    $admin_confirm_password = trim($_POST['admin_confirm_password']);

    // Validate input
    if (empty($admin_name) || empty($admin_email) || empty($admin_phone) || empty($admin_password) || empty($admin_confirm_password)) {
        $err = "All fields are required.";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $err = "Invalid email format.";
    } elseif ($admin_password !== $admin_confirm_password) {
        $err = "Passwords do not match.";
    } else {
        $hashed_password = sha1(md5($admin_password)); // You should use password_hash() instead for real applications

        $stmt = $conn->prepare("INSERT INTO admins (admin_name, admin_email, admin_phone, admin_password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $admin_name, $admin_email, $admin_phone, $hashed_password);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Registration successful! Please login to continue.";
            header("location: admin_login.php");
            exit;
        } else {
            $err = "Error: Unable to register admin. Maybe email already exists.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="badges.css">
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

            <?php if (isset($err)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $err; ?>
                </div>
            <?php endif; ?>

            <form method="POST" role="form">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="admin_name" class="form-label">Admin Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="admin_name" class="form-control" id="admin_name" placeholder="Enter your name">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="admin_email" class="form-control" id="email"
                                placeholder="Enter your email">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div id="emailAvailability" class="form-text"></div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" name="admin_phone" class="form-control" id="phone"
                                placeholder="Enter your phone number">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="admin_password" class="form-control" id="password"
                                placeholder="Create a password">
                            <button type="button" class="toggle-password" tabindex="-1" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="admin_confirm_password" class="form-control"
                                id="confirm_password" placeholder="Confirm your password">
                            <button type="button" class="toggle-password" tabindex="-1" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div id="passwordMatch" class="form-text"></div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="" id="termsCheck" required>
                        <label class="form-check-label" for="termsCheck">
                            I agree to the Terms of Service and Privacy Policy
                        </label>
                        <div class="invalid-feedback">
                            You must agree before submitting.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" name="signup">
                        Sign Up
                    </button>
                </div>
            </form>
            <div class="text-center mt-3">
                <p>Already have an admin account? <a href="admin_login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId) {
            const passwordInput = document.getElementById(inputId);
            const icon = passwordInput.nextElementSibling.querySelector('i');
            
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

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            
            // Remove all classes
            strengthBar.className = 'password-strength';
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = '';
                return;
            }
            
            // Check strength
            if (password.length < 8) {
                strengthBar.classList.add('strength-weak');
                strengthBar.style.width = '30%';
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#ffffff';
            } else if (password.length >= 8 && password.length < 12) {
                strengthBar.classList.add('strength-medium');
                strengthBar.style.width = '60%';
                strengthText.textContent = 'Medium strength';
                strengthText.style.color = '#ffffff';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthBar.style.width = '100%';
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#ffffff';
            }
        });

        // Check if passwords match
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                passwordMatch.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                passwordMatch.textContent = 'Passwords match';
                passwordMatch.style.color = '#2ecc71';
            } else {
                passwordMatch.textContent = 'Passwords do not match';
                passwordMatch.style.color = '#dc3545';
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="particles.js"></script>
</body>

</html>