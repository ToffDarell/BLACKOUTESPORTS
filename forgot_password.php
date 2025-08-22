<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $user_email = $_POST['user_email'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Generate reset code
        $reset_code = rand(100000, 999999);

        // Store reset code in database
        $update_stmt = $conn->prepare("UPDATE users SET reset_code = ? WHERE user_email = ?");
        $update_stmt->bind_param('ss', $reset_code, $user_email);
        $update_stmt->execute();
        $update_stmt->close();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'topedarell13@gmail.com';
            $mail->Password = 'cknz fwgu srfs egto'; // App password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('topedarell13@gmail.com', 'Blackout Esports');
            $mail->addAddress($user_email);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code';
            $mail->Body = "<h3>Your reset code is: <b>$reset_code</b></h3>";
            $mail->AltBody = "Your reset code is: $reset_code";

            $mail->send();
            $_SESSION['reset_code'] = $reset_code;
            $_SESSION['reset_email'] = $user_email;

            header('Location: verify_code.php');
            exit();

        } catch (Exception $e) {
            echo "<p class='text-danger text-center'>Mailer Error: {$mail->ErrorInfo}</p>";
        }
    } else {
        echo "<p class='text-danger text-center'>Email not found. Please try again.</p>";
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
    <title>Forgot Password - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="badges.css">
</head>

<body>
    <!-- Particles JS Background -->
    <div id="particles-js"></div>
    
    <div class="container">
        <div class="signup-container">
            <div class="user-badge">RESET PASSWORD</div>
            <div class="logo">
                <img src="images/blackout.jpg" alt="Blackout Esports Logo"
                    onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'"
                    class="animate__animated animate__fadeIn">
            </div>

            <h2>FORGOT PASSWORD</h2>

            <form method="POST" role="form">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="user_email" class="form-control" id="email"
                                placeholder="Enter your email" required>
                        </div>
                    </div>
                </div>

                <button type="submit" name="send_code" class="btn btn-primary animate__animated animate__pulse">
                    Send Code
                </button>
            </form>
            <div class="text-center mt-3">
                <p>Remembered your password? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="particles.js"></script>
</body>

</html>