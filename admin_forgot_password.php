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
    $admin_email = $_POST['admin_email'];

    // Check if email exists in admins table
    $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE admin_email = ?");
    $stmt->bind_param('s', $admin_email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Generate reset code
        $reset_code = rand(100000, 999999);

        // Store reset code in database
        $update_stmt = $conn->prepare("UPDATE admins SET reset_code = ? WHERE admin_email = ?");
        $update_stmt->bind_param('ss', $reset_code, $admin_email);
        $update_stmt->execute();
        $update_stmt->close();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'topefromyt@gmail.com';
            $mail->Password = 'dbfw bkwc ebca ixum'; // App password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('topefromyt@gmail.com', 'Blackout Esports');
            $mail->addAddress($admin_email);

            $mail->isHTML(true);
            $mail->Subject = 'Admin Password Reset Code';
            $mail->Body = "<h3>Your admin account reset code is: <b>$reset_code</b></h3>";
            $mail->AltBody = "Your admin account reset code is: $reset_code";

            $mail->send();
            $_SESSION['admin_reset_code'] = $reset_code;
            $_SESSION['admin_reset_email'] = $admin_email;

            header('Location: admin_verify_code.php');
            exit();

        } catch (Exception $e) {
            echo "<p class='text-danger text-center'>Mailer Error: {$mail->ErrorInfo}</p>";
        }
    } else {
        echo "<p class='text-danger text-center'>Admin email not found. Please try again.</p>";
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
    <title>Admin Forgot Password - Blackout Esports</title>
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
            <div class="admin-badge">ADMIN AREA</div>
            <div class="logo">
                <img src="images/blackout.jpg" alt="Blackout Esports Logo"
                    onerror="this.src='https://via.placeholder.com/220x80?text=BLACKOUT+ESPORTS'"
                    class="animate__animated animate__fadeIn">
            </div>

            <h2>FORGOT PASSWORD</h2>

            <form method="POST" role="form">
                <div class="form-section">
                    <div class="mb-3">
                        <label for="email" class="form-label">Admin Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="admin_email" class="form-control" id="email"
                                placeholder="Enter your admin email" required>
                        </div>
                    </div>
                </div>

                <button type="submit" name="send_code" class="btn btn-primary animate__animated animate__pulse">
                    Send Reset Code
                </button>
            </form>
            <div class="text-center mt-3">
                <p>Remembered your password? <a href="admin_login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="particles.js"></script>
</body>

</html>
