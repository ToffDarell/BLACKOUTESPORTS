<?php
// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendReservationStatusEmail($user_email, $username, $status, $computer_number, $reservation_date, $start_time, $end_time, $decline_reason = '', $reservation_id = null, $attachment_path = null) {
    // Email configuration
    $to = $user_email;
    $subject = "Reservation " . ucfirst(strtolower($status)) . " - Blackout Esports";
    
    // Email content based on status
    if ($status == 'Confirmed') {
        $message = "
        <html>
        <head>
            <title>Reservation Confirmed</title>
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
                    <h2>Reservation Confirmed</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>Your reservation has been <strong style='color:#dc3545;'>confirmed</strong>! Here are the details:</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <p>Please arrive a few minutes before your scheduled time. Your PC will be ready for you.</p>
                    <p>If you need to cancel your reservation, please do so at least 2 hours before your scheduled time.</p>
                    <p>Thank you for choosing <span style='color: #dc3545; font-weight: bold;'>Blackout Esports</span>!</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " Blackout Esports. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'confirmed';
    } elseif ($status == 'Declined') {
        // Include specific decline reason if provided
        $reason_html = '';
        if (!empty($decline_reason)) {
            $reason_html = "
            <div class='reason'>
                <p><strong>Reason:</strong> " . htmlspecialchars($decline_reason) . "</p>
            </div>";
        }
        
        $message = "
        <html>
        <head>
            <title>Reservation Declined</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
                .details { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
                .details p strong { color: #dc3545; }
                .reason { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 3px solid #dc3545; color: #fff; }
                .reason p strong { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Reservation Declined</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>We're sorry to inform you that your reservation has been <strong style='color: #dc3545;'>declined</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    $reason_html
                    
                    " . (empty($decline_reason) ? "<p>This may be due to one of the following reasons:</p>
                    <ul>
                        <li>Incomplete payment information</li>
                        <li>The computer is unavailable due to maintenance</li>
                        <li>Schedule conflict with a tournament or event</li>
                    </ul>" : "") . "
                    
                    <p>Please feel free to make another reservation or contact us for more information.</p>
                    <p>Thank you for your understanding.</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " Blackout Esports. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'declined';
    } elseif ($status == 'Time Expired') {
        $message = "
        <html>
        <head>
            <title>Session Time Expired</title>
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
                    <h2>Session Time Expired</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>Your reserved session time has <strong style='color: #dc3545;'>expired</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <p>If you're still using the computer, please inform our staff or make an additional reservation if needed.</p>
                    <p>Thank you for choosing <span style='color: #dc3545; font-weight: bold;'>Blackout Esports</span>!</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " Blackout Esports. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'expired';
    } elseif ($status == 'Completed') {
        $message = "
        <html>
        <head>
            <title>Reservation Completed</title>
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
                    <h2>Reservation Completed</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>Your session has been <strong style='color: #dc3545;'>completed</strong>. Thank you for using our services!</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <p>We hope you enjoyed your gaming experience at <span style='color: #dc3545; font-weight: bold;'>Blackout Esports</span>.</p>
                    <p>Feel free to make another reservation anytime!</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " Blackout Esports. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'completed';
    } elseif ($status == 'Refund Approved') {
        $message = "
        <html>
        <head>
            <title>Refund Request Approved</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
                .details { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
                .details p strong { color: #dc3545; }
                .note { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 3px solid #dc3545; color: #fff; }
                .note p strong { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Refund Request Approved</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>Good news! Your refund request has been <strong style='color: #dc3545;'>approved</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <div class='note'>
                        <p><strong>Note:</strong> $decline_reason</p>
                        <p>Your refund is now being processed and you will receive another notification once it has been completed.</p>
                    </div>
                    
                    <p>Thank you for your patience and understanding.</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " <span style='color: #dc3545;'>Blackout Esports</span>. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'refund_approved';
    } elseif ($status == 'Refund Declined') {
        $message = "
        <html>
        <head>
            <title>Refund Request Declined</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
                .details { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
                .details p strong { color: #dc3545; }
                .reason { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 3px solid #dc3545; color: #fff; }
                .reason p strong { color: #dc3545; }
                .notice { padding: 10px 15px; background-color: rgba(220, 53, 69, 0.1); border-left: 3px solid #dc3545; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Refund Request Declined</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>We're sorry to inform you that your refund request has been <strong style='color: #dc3545;'>declined</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <div class='reason'>
                        <p><strong>Reason for declining:</strong></p>
                        <p>" . htmlspecialchars($decline_reason) . "</p>
                    </div>
                    
                    <div class='notice'>
                        <p>Your reservation remains active. You can still use the computer at the scheduled time.</p>
                    </div>
                    
                    <p>If you have any questions about this decision or need further assistance, please contact our support team.</p>
                    <p>Thank you for your understanding.</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " <span style='color: #dc3545;'>Blackout Esports</span>. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'refund_declined';
    } elseif ($status == 'Refund Refunded') {
        $message = "
        <html>
        <head>
            <title>Refund Processed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .footer { background-color: #222; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; color: #ddd; }
                .details { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; color: #fff; border-left: 3px solid #dc3545; }
                .details p strong { color: #dc3545; }
                .proof { background-color: #222; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 3px solid #dc3545; color: #fff; }
                .proof p strong { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Refund Processed Successfully</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>We're pleased to inform you that your refund has been <strong style='color: #dc3545;'>successfully processed</strong>!</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <div class='proof'>
                        <p><strong>Important:</strong> $decline_reason</p>
                        <p>A proof of the refund transaction has been attached to this email for your records.</p>
                    </div>
                    
                    <p>We appreciate your business and hope to see you again soon.</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " <span style='color: #dc3545;'>Blackout Esports</span>. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = 'refunded';
    } else {
        // Default message for any other status
        $message = "
        <html>
        <head>
            <title>Reservation Update</title>
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
                    <h2>Reservation Update</h2>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>Your reservation status has been updated to <strong style='color: #dc3545;'>$status</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Computer:</strong> PC #$computer_number</p>
                        <p><strong>Date:</strong> $reservation_date</p>
                        <p><strong>Time:</strong> $start_time - $end_time</p>
                    </div>
                    
                    <p>If you have any questions, please contact us.</p>
                    <p>Thank you for choosing <span style='color: #dc3545; font-weight: bold;'>Blackout Esports</span>!</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " <span style='color: #dc3545;'>Blackout Esports</span>. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        $notification_type = strtolower($status);
    }
    
    // Check if PHPMailer is installed, if not, fallback to mail()
    $phpmailer_exists = file_exists(__DIR__ . '/vendor/autoload.php');
    
    if ($phpmailer_exists) {
        try {
            // Include the Composer autoloader
            require __DIR__ . '/vendor/autoload.php';
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
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
            
            // Add attachment if provided
            if ($attachment_path && file_exists($attachment_path)) {
                $mail->addAttachment($attachment_path, 'Refund_Proof.'.pathinfo($attachment_path, PATHINFO_EXTENSION));
            }
            
            // Send the email
            $mail_sent = $mail->send();
            
            // Update database with notification status if reservation_id is provided
            if ($mail_sent && $reservation_id) {
                updateEmailNotificationStatus($reservation_id, $notification_type);
            }
            
            return $mail_sent;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            
            // Fallback to regular mail function if PHPMailer fails
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Blackout Esports <topedarell13@gmail.com>" . "\r\n";
            
            $mail_sent = mail($to, $subject, $message, $headers);
            
            // Update database with notification status if reservation_id is provided
            if ($mail_sent && $reservation_id) {
                updateEmailNotificationStatus($reservation_id, $notification_type);
            }
            
            return $mail_sent;
        }
    } else {
        // Use PHP's mail function as fallback
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Blackout Esports <topedarell13@gmail.com>" . "\r\n";
        
        // For testing in local environment, log the email content
        if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
            // Create logs directory if it doesn't exist
            $log_dir = __DIR__ . '/email_logs';
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0777, true);
            }
            
            // Log the email content to a file
            $log_file = $log_dir . '/email_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
            file_put_contents($log_file, 
                "To: $to\r\n" .
                "Subject: $subject\r\n" .
                "Headers: $headers\r\n" .
                "Attachment: " . ($attachment_path ? $attachment_path : 'None') . "\r\n\r\n" .
                $message
            );
            
            // Copy attachment if it exists (for local testing)
            if ($attachment_path && file_exists($attachment_path)) {
                copy($attachment_path, $log_dir . '/attachment_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '_' . basename($attachment_path));
            }
            
            // Update database with notification status if reservation_id is provided
            if ($reservation_id) {
                updateEmailNotificationStatus($reservation_id, $notification_type);
            }
            
            // Return true for local environment as we're just logging
            return true;
        }
        
        // Send the email for production environment
        $mail_sent = mail($to, $subject, $message, $headers);
        
        // Update database with notification status if reservation_id is provided
        if ($mail_sent && $reservation_id) {
            updateEmailNotificationStatus($reservation_id, $notification_type);
        }
        
        return $mail_sent;
    }
}

// Function to update the email notification status in the database
function updateEmailNotificationStatus($reservation_id, $notification_type) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "computer_reservation";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }

    // Update the email_notification_sent column
    $stmt = $conn->prepare("UPDATE reservations SET email_notification_sent = ? WHERE reservation_id = ?");
    $stmt->bind_param('si', $notification_type, $reservation_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();

    return $result;
}
?> 