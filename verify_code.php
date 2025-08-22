<?php
session_start();

// Check if email is set
if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $entered_code = trim($_POST['reset_code']);
    $user_email = $_SESSION['reset_email'];
    
    // Get stored code from database
    $stmt = $conn->prepare("SELECT reset_code FROM users WHERE user_email = ?");
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $stmt->bind_result($stored_code);
    $stmt->fetch();
    $stmt->close();

    if ($entered_code === $stored_code) {
        // Code is correct, redirect to reset password page
        header('Location: reset_password.php');
        exit();
    } else {
        $error = "Invalid code. Please try again.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - Blackout Esports</title>
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

            <h2 class="typing-effect">Verify Code</h2>

            <div class="alert alert-info text-center" style="background-color: #000000; color: #ffffff; border: 2px solid #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);">
                A verification code has been sent to your email address. Please check your inbox.
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" role="form">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="reset_code" class="form-label">Enter Verification Code</label>
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
            <div class="text-center mt-3">
                <p>Didn't receive the code? <a href="forgot_password.php">Resend Code</a></p>
            </div>
        </div>
    </div>
</body>

</html>