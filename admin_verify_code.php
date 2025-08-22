<?php
session_start();

// Check if email was stored in session
if (!isset($_SESSION['admin_reset_email'])) {
    header('Location: admin_forgot_password.php');
    exit();
}

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = '';
$success_message = '';

// Verify code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $input_code = $_POST['reset_code'];
    $admin_email = $_SESSION['admin_reset_email'];
    
    // Get stored code from database
    $stmt = $conn->prepare("SELECT reset_code FROM admins WHERE admin_email = ?");
    $stmt->bind_param('s', $admin_email);
    $stmt->execute();
    $stmt->bind_result($stored_code);
    $stmt->fetch();
    $stmt->close();
    
    if ($input_code == $stored_code) {
        // Code is valid, show password reset form
        $_SESSION['admin_code_verified'] = true;
    } else {
        $error_message = 'Invalid code. Please try again.';
    }
}

// Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!isset($_SESSION['admin_code_verified']) || $_SESSION['admin_code_verified'] !== true) {
        $error_message = 'Please verify your code first.';
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } else if (strlen($new_password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } else {
            // Update password in database
            $admin_email = $_SESSION['admin_reset_email'];
            $hashed_password = sha1(md5($new_password));
            
            $stmt = $conn->prepare("UPDATE admins SET admin_password = ?, reset_code = NULL WHERE admin_email = ?");
            $stmt->bind_param('ss', $hashed_password, $admin_email);
            $result = $stmt->execute();
            
            if ($result) {
                $success_message = 'Password has been reset successfully.';
                $_SESSION['admin_reset_success'] = 'Password has been reset successfully! You can now login with your new password.';
                
                // Clear session variables
                unset($_SESSION['admin_reset_email']);
                unset($_SESSION['admin_code_verified']);
                
                // Redirect to login after 3 seconds
                header("refresh:3;url=admin_login.php");
            } else {
                $error_message = 'Failed to reset password. Please try again.';
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
    <title>Admin Password Recovery - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="badges.css">
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <div class="admin-badge">ADMIN AREA</div>
            <div class="logo">
                <img src="images/blackout.jpg" alt="Blackout Esports Logo"
                    onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'"
                    class="animate__animated animate__fadeIn">
            </div>

            <h2>PASSWORD RECOVERY</h2>
            
            <div class="alert alert-info text-center success_message" style="background-color: #000000; color: #ffffff; border: 2px solid #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);">
                A verification code has been sent to your admin email address. Please check your inbox.
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success" style="background-color: #000000; color: #ffffff; border: 2px solid #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (!isset($_SESSION['admin_code_verified']) || $_SESSION['admin_code_verified'] !== true): ?>
                <!-- Code verification form -->
                <form method="POST" role="form">
                    <div class="form-section">
                        <div class="mb-3">
                            <label for="reset_code" class="form-label">Verification Code</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="text" name="reset_code" class="form-control" id="reset_code"
                                    placeholder="Enter the code sent to your email" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="verify_code" class="btn btn-primary animate__animated animate__pulse">
                        Verify Code
                    </button>
                </form>
            <?php else: ?>
                <!-- Password reset form -->
                <form method="POST" role="form">
                    <div class="form-section">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="new_password" class="form-control" id="new_password"
                                    placeholder="Enter new password" required>
                                <button type="button" class="toggle-password" tabindex="-1" onclick="togglePasswordVisibility('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="confirm_password" class="form-control" id="confirm_password"
                                    placeholder="Confirm new password" required>
                                <button type="button" class="toggle-password" tabindex="-1" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary animate__animated animate__pulse">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <p>Remembered your password? <a href="admin_login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        function togglePasswordVisibility(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const icon = document.querySelector(`#${fieldId} + .toggle-password i`);
            
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