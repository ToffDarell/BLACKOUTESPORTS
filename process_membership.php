<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access");
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add this function at the top of the file
function sendMembershipEmail($to, $user_name, $start_date, $end_date) {
    $subject = "Blackout Esports - Membership Approved!";
    
    $message = "
    <html>
    <head>
        <title>Membership Approved</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; }
            .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
            .details { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
            .details p strong { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Membership Approved</h2>
            </div>
            <div class='content'>
                <h3>Congratulations, $user_name!</h3>
                <p>Your membership application has been <strong style='color:#dc3545;'>approved</strong>!</p>
                <p>You are now an official member of Blackout Esports and can enjoy all member benefits including discounted rates and priority booking.</p>
                
                <div class='details'>
                    <p><strong>Membership Start Date:</strong> $start_date</p>
                    <p><strong>Membership End Date:</strong> $end_date</p>
                </div>
                
                <p>Remember to make a new payment before your membership expires to continue enjoying your benefits.</p>
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
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Send the email
            $mail_sent = $mail->send();
            
            return $mail_sent;
        } catch (Exception $e) {
            error_log("Membership email sending failed: " . $e->getMessage());
            
            // Fallback to regular mail function if PHPMailer fails
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Blackout Esports <topedarell13@gmail.com>" . "\r\n";
            
            $mail_sent = mail($to, $subject, $message, $headers);
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
            $log_file = $log_dir . '/membership_email_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
            file_put_contents($log_file, 
                "To: $to\r\n" .
                "Subject: $subject\r\n" .
                "Headers: $headers\r\n\r\n" .
                $message
            );
            
            // Return true for local environment as we're just logging
            return true;
        }
        
        // Send the email for production environment
        $mail_sent = mail($to, $subject, $message, $headers);
        return $mail_sent;
    }
}

// Update the function to accept a decline reason
function sendMembershipDeclinedEmail($to, $user_name, $decline_reason = '') {
    $subject = "Blackout Esports - Membership Application Status";
    
    // If no custom reason, use a default message
    $reason_html = '';
    if (!empty($decline_reason)) {
        $reason_html = "
        <div class='reason'>
            <p><strong>Reason for declining:</strong></p>
            <p>" . htmlspecialchars($decline_reason) . "</p>
        </div>";
    }
    
    $message = "
    <html>
    <head>
        <title>Membership Application Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; }
            .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
            .alert { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
            .alert p strong { color: #dc3545; }
            .reason { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
            .reason p strong { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Membership Application Update</h2>
            </div>
            <div class='content'>
                <p>Hello $user_name,</p>
                <p>We've reviewed your membership application for Blackout Esports.</p>
                
                <div class='alert'>
                    <p>Your membership application has been <strong>declined</strong> at this time.</p>
                </div>
                
                $reason_html
                
                " . (empty($decline_reason) ? "<p>This could be due to one of the following reasons:</p>
                <ul>
                    <li>Incomplete or invalid payment information</li>
                    <li>Payment verification issues</li>
                    <li>System processing error</li>
                </ul>" : "") . "
                
                <p>You're welcome to submit a new application or contact our support team for more information.</p>
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
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Send the email
            $mail_sent = $mail->send();
            
            return $mail_sent;
        } catch (Exception $e) {
            error_log("Membership declined email sending failed: " . $e->getMessage());
            
            // Fallback to regular mail function if PHPMailer fails
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Blackout Esports <topedarell13@gmail.com>" . "\r\n";
            
            $mail_sent = mail($to, $subject, $message, $headers);
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
            $log_file = $log_dir . '/membership_declined_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
            file_put_contents($log_file, 
                "To: $to\r\n" .
                "Subject: $subject\r\n" .
                "Headers: $headers\r\n\r\n" .
                $message
            );
            
            // Return true for local environment as we're just logging
            return true;
        }
        
        // Send the email for production environment
        $mail_sent = mail($to, $subject, $message, $headers);
        return $mail_sent;
    }
}

// For regular user membership application
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['approve_membership']) && !isset($_POST['reject_membership'])) {
    $user_id = $_SESSION['user_id'];
    $phone = $_POST['phone'];
    
    // Handle file upload
    if(isset($_FILES["paymentProof"]) && $_FILES["paymentProof"]["error"] == 0) {
        $target_dir = "uploads/payment_proofs/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["paymentProof"]["name"], PATHINFO_EXTENSION));
        $file_name = "payment_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;
        $upload_path = $target_file; // Store the relative path
        
        if (move_uploaded_file($_FILES["paymentProof"]["tmp_name"], $target_file)) {
            // Update user record with payment proof and pending status
            $stmt = $conn->prepare("UPDATE users SET user_phone = ?, membership_status = 'Pending', payment_proof = ? WHERE user_id = ?");
            
            if ($stmt === false) {
                die("Error preparing statement: " . $conn->error);
            }
            
            $stmt->bind_param("ssi", $phone, $upload_path, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Membership application submitted successfully!";
            } else {
                $_SESSION['error'] = "Error processing application: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Error uploading file.";
        }
    } else {
        $_SESSION['error'] = "No file uploaded or file upload error.";
    }
    
    header("Location: membership.php");
    exit();
}

// For admin approval/rejection
if (isset($_POST['approve_membership']) || isset($_POST['reject_membership'])) {
    // Check if user is admin
    if (!isset($_SESSION['admin_id'])) {
        die("Unauthorized access");
    }

    $user_id = $_POST['user_id'];
    
    if (isset($_POST['approve_membership'])) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Get user email first
            $stmt = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user_result = $stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_email = $user_data['user_email'];
            $user_name = $user_data['user_name'];
            
            // Insert into memberships table
            $stmt = $conn->prepare("INSERT INTO memberships (user_id, join_date) VALUES (?, NOW())");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            
            // Get the join date that was just inserted
            $stmt = $conn->prepare("SELECT join_date FROM memberships WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $date_result = $stmt->get_result();
            $date_data = $date_result->fetch_assoc();
            $join_date = $date_data['join_date'];
            
            // Calculate end date (1 month from join date)
            $start_date = date('F d, Y', strtotime($join_date));
            $end_date = date('F d, Y', strtotime("$join_date +1 month"));
            
            // Update user status
            $stmt = $conn->prepare("UPDATE users SET membership_status = 'Member' WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            
            // Send email using the new function
            $email_sent = sendMembershipEmail($user_email, $user_name, $start_date, $end_date);
            
            // Store notification data in the memberships table
            $notification_title = "Membership Approved";
            $notification_message = "Your membership application has been approved! Your membership is valid from $start_date to $end_date.";
            
            $stmt = $conn->prepare("UPDATE memberships SET title = ?, message = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $notification_title, $notification_message, $user_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['message'] = "Membership approved successfully!" . 
                ($email_sent ? " An email has been sent to the user." : " Email could not be sent, but a notification has been stored.");
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error approving membership: " . $e->getMessage();
        }
    } else {
        // Get the decline reason if provided
        $decline_reason = isset($_POST['decline_reason']) ? $_POST['decline_reason'] : '';
        
        // Get user email first
        $stmt = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_email = $user_data['user_email'];
        $user_name = $user_data['user_name'];
        
        // Update user status
        $stmt = $conn->prepare("UPDATE users SET membership_status = 'Non-Member' WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Send decline notification email with reason
        $email_sent = sendMembershipDeclinedEmail($user_email, $user_name, $decline_reason);
        
        // Insert membership record with rejection notification
        $notification_title = "Membership Application Declined";
        $notification_message = "Your membership application has been declined.";
        if (!empty($decline_reason)) {
            $notification_message .= " Reason: " . $decline_reason;
        }
        $notification_message .= " You may submit a new application or contact support for more information.";
        
        // Check if user already has a membership record
        $stmt = $conn->prepare("SELECT membership_id FROM memberships WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE memberships SET title = ?, message = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $notification_title, $notification_message, $user_id);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO memberships (user_id, join_date, title, message) VALUES (?, NOW(), ?, ?)");
            $stmt->bind_param('iss', $user_id, $notification_title, $notification_message);
        }
        $stmt->execute();
        
        $_SESSION['message'] = "Membership rejected." . 
            ($email_sent ? " A notification email has been sent to the user." : " Email could not be sent, but a notification has been stored.");
    }

    // Redirect back to manage_users.php for admin actions
    header("Location: manage_users.php");
    exit();
}

$conn->close();
?>
