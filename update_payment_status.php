<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if all required parameters are present
if (!isset($_POST['tournament_id']) || !isset($_POST['team_name']) || !isset($_POST['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Get parameters
$tournament_id = (int)$_POST['tournament_id'];
$registration_id = isset($_POST['registration_id']) ? (int)$_POST['registration_id'] : 0;
$team_name = $_POST['team_name'];
$status = $_POST['status'] === 'paid' ? 'paid' : 'unpaid';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Function to send notification email to users when payment is confirmed
function sendTournamentPaymentConfirmationEmail($user_email, $user_name, $team_name, $team_captain, $tournament_name, $tournament_date, $tournament_time, $team_members, $prize_pool, $game) {
    $subject = "Blackout Esports - Tournament Registration Confirmed";
    
    $message = "
    <html>
    <head>
        <title>Tournament Registration Confirmed</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; }
            .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
            .details { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
            .details p strong { color: #dc3545; }
            .details ul { padding-left: 20px; }
            .details li { margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Tournament Registration Confirmed</h2>
            </div>
            <div class='content'>
                <h3>Congratulations, $user_name!</h3>
                <p>Your payment for the tournament has been <strong style='color:#dc3545;'>confirmed</strong>!</p>
                <p>Your team <strong style='color:#dc3545;'>$team_name</strong> is now officially registered for the <strong style='color:#dc3545;'>$tournament_name</strong> tournament.</p>
                
                <div class='details'>
                    <p><strong>Tournament:</strong> $tournament_name</p>
                    <p><strong>Game:</strong> $game</p>
                    <p><strong>Date:</strong> $tournament_date</p>
                    <p><strong>Time:</strong> $tournament_time</p>
                    <p><strong>Prize Pool:</strong> $prize_pool</p>
                    <p><strong>Team Captain:</strong> $team_captain</p>
                    <p><strong>Team Members:</strong></p>
                    <ul>";
    
    // Format team members as list items
    if (!empty($team_members)) {
        $members = explode(',', $team_members);
        foreach ($members as $member) {
            $member = trim($member);
            if (!empty($member)) {
                $message .= "<li>$member</li>";
            }
        }
    } else {
        $message .= "<li>No team members listed</li>";
    }
    
    $message .= "
                    </ul>
                </div>
                
                <p>Please arrive at least 30 minutes before the tournament start time for team check-in.</p>
                <p>Good luck and have fun!</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Blackout Esports. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Check if PHPMailer is installed, if not, fallback to mail()
    $phpmailer_exists = file_exists(__DIR__ . '/vendor/autoload.php');
    
    if ($phpmailer_exists) {
        try {
            // Include the Composer autoloader
            require __DIR__ . '/vendor/autoload.php';
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'topedarell13@gmail.com'; // SMTP username
            $mail->Password = 'cknz fwgu srfs egto'; // SMTP password (use App Password for Gmail)
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('topedarell13@gmail.com', 'Blackout Esports');
            $mail->addAddress($user_email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Send the email
            $mail_sent = $mail->send();
            
            return $mail_sent;
        } catch (Exception $e) {
            error_log("Tournament confirmation email sending failed: " . $e->getMessage());
            
            // Fallback to regular mail function if PHPMailer fails
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Blackout Esports <topedarell13@gmail.com>" . "\r\n";
            
            $mail_sent = mail($user_email, $subject, $message, $headers);
            return $mail_sent;
        }
    } else {
        // Use PHP's mail function as fallback
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Blackout Esports <topedarell13@gmail.com>" . "\r\n";
        
        // For testing in local environment, log the email content
        if (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1')) {
            // Create logs directory if it doesn't exist
            $log_dir = __DIR__ . '/email_logs';
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0777, true);
            }
            
            // Log the email content to a file
            $log_file = $log_dir . '/tournament_confirmation_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
            file_put_contents($log_file, 
                "To: $user_email\r\n" .
                "Subject: $subject\r\n" .
                "Headers: $headers\r\n\r\n" .
                $message
            );
            
            // Return true for local environment as we're just logging
            return true;
        }
        
        // Send the email for production environment
        $mail_sent = mail($user_email, $subject, $message, $headers);
        return $mail_sent;
    }
}

// First check if entry exists in tournament_registrations table
$success = false;
$notification_sent = false;

if ($registration_id > 0) {
    // Update payment status in tournament_registrations table
    $stmt = $conn->prepare("UPDATE tournament_registrations SET payment_status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $registration_id);
    $success = $stmt->execute();
    $stmt->close();
    
    // If status is paid, send notification
    if ($success && $status === 'paid') {
        // Get registration details - ensure team_members is included in the SELECT
        $reg_stmt = $conn->prepare("SELECT tr.*, u.user_name, u.user_email, t.name as tournament_name, 
                                  t.date as tournament_date, t.time as tournament_time, t.prize_pool, t.game 
                                  FROM tournament_registrations tr 
                                  JOIN users u ON tr.user_id = u.user_id 
                                  JOIN tournaments t ON tr.tournament_id = t.id 
                                  WHERE tr.id = ?");
        $reg_stmt->bind_param("i", $registration_id);
        $reg_stmt->execute();
        $reg_result = $reg_stmt->get_result();
        
        if ($reg_result && $reg_result->num_rows > 0) {
            $reg_data = $reg_result->fetch_assoc();
            
            // Format tournament date
            $tournament_date = date('F j, Y', strtotime($reg_data['tournament_date']));
            $tournament_time = $reg_data['tournament_time'];
            
            // Debug team members
            error_log("Team Members Data: " . (isset($reg_data['team_members']) ? $reg_data['team_members'] : 'NOT FOUND'));
            
            // Ensure team members is not empty - use a default if it is
            $team_members = !empty($reg_data['team_members']) ? $reg_data['team_members'] : 'No team members listed';
            
            // Send notification email
            $notification_sent = sendTournamentPaymentConfirmationEmail(
                $reg_data['user_email'],
                $reg_data['user_name'],
                $reg_data['team_name'],
                $reg_data['team_captain'],
                $reg_data['tournament_name'],
                $tournament_date,
                $tournament_time,
                $team_members,
                $reg_data['prize_pool'],
                $reg_data['game']
            );
        }
        $reg_stmt->close();
    }
} else {
    // Check if registration exists in tournament_registrations table
    $stmt = $conn->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND team_name = ?");
    $stmt->bind_param("is", $tournament_id, $team_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Registration exists in tournament_registrations, update it
        $reg_id = $result->fetch_assoc()['id'];
        $update_stmt = $conn->prepare("UPDATE tournament_registrations SET payment_status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $status, $reg_id);
        $success = $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Check if registration exists in tournaments table
        $check_stmt = $conn->prepare("SELECT id FROM tournaments WHERE id = ? AND team_name = ?");
        $check_stmt->bind_param("is", $tournament_id, $team_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Registration exists in tournaments table, migrate it to tournament_registrations with the new status
            $get_stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
            $get_stmt->bind_param("i", $tournament_id);
            $get_stmt->execute();
            $tournament = $get_stmt->get_result()->fetch_assoc();
            $get_stmt->close();
            
            // Find user ID if possible
            $user_id = 0;
            if (!empty($tournament['contact_email'])) {
                $user_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
                $user_stmt->bind_param("s", $tournament['contact_email']);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                if ($user_result->num_rows > 0) {
                    $user_id = $user_result->fetch_assoc()['user_id'];
                }
                $user_stmt->close();
            }
            
            // Insert registration into tournament_registrations table with the specified payment status
            $team_captain = $tournament['captain_name'] ?? $tournament['team_name'];
            $contact_number = $tournament['contact_phone'] ?? '';
            $team_members = $tournament['team_members'] ?? '';
            
            $insert_stmt = $conn->prepare("INSERT INTO tournament_registrations 
                                          (tournament_id, user_id, team_name, team_captain, contact_number, 
                                           team_members, registration_date, payment_status) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
            
            $insert_stmt->bind_param("iisssss", 
                $tournament_id, 
                $user_id, 
                $team_name, 
                $team_captain, 
                $contact_number, 
                $team_members,
                $status
            );
            
            $success = $insert_stmt->execute();
            $insert_stmt->close();
        }
    }
}

// Close database connection
$conn->close();

// Send response
if ($success) {
    $response = ['success' => true, 'status' => $status];
    if ($status === 'paid') {
        $response['notification'] = $notification_sent ? 'sent' : 'failed';
    }
    echo json_encode($response);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update payment status']);
} 