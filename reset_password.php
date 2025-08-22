<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure the reset email is set in the session
if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password) {
        $hashed_password = sha1(md5($new_password)); // Double encryption for security
        $user_email = $_SESSION['reset_email'];

        // Update password in the database
        $stmt = $conn->prepare("UPDATE users SET user_password = ?, reset_code = NULL WHERE user_email = ?");
        $stmt->bind_param('ss', $hashed_password, $user_email);

        if ($stmt->execute()) {
            $success = "Password reset successfully! You will be redirected to login page.";
            $_SESSION['reset_success'] = "Password reset successfully! You can now login with your new password.";
            unset($_SESSION['reset_email']);
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = "Failed to reset password. Please try again.";
        }

        $stmt->close();
    } else {
        $error = "Passwords do not match. Please try again.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <div class="signup-container neon-border">
            <div class="logo">
                <img src="images/blackout.jpg" alt="Blackout Esports Logo"
                    onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'"
                    class="animate__animated animate__fadeIn">
            </div>

            <h2 class="typing-effect">Reset Password</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success text-center" style="background-color: #000000; color: #ffffff; border: 2px solid #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);">
                    <?= $success ?>
                </div>
            <?php else: ?>
                <form method="POST" role="form">
                    <div class="form-section">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="new_password" class="form-control" id="new_password"
                                    placeholder="Enter your new password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="confirm_password" class="form-control" id="confirm_password"
                                    placeholder="Confirm your new password" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary animate__animated animate__pulse">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>