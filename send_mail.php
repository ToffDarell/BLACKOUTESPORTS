<?php
// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;   

// Load PHPMailer autoloader - adjust path as needed
require 'vendor/autoload.php';
require_once 'gmail_config.php';

/**
 * Send email using PHPMailer with Gmail SMTP
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $fromName Sender name
 * @return bool True if email sent successfully, false otherwise
 */
function sendGmail($to, $subject, $message, $fromName = GMAIL_NAME) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;                      // 0 = no output, 1 = errors, 2 = detailed
        $mail->isSMTP();                           // Use SMTP
        $mail->Host       = 'smtp.gmail.com';      // Gmail SMTP server
        $mail->SMTPAuth   = true;                  // Enable SMTP authentication
        $mail->Username   = 'topedarell13@gmail.com';        // SMTP username from config
        $mail->Password   = 'cknz fwgu srfs egto';        // SMTP password from config
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                   // TCP port to connect to
        
        // Recipients
        $mail->setFrom('topedarell13@gmail.com', $fromName);
        $mail->addAddress($to);                    // Add a recipient
        
        // Content
        $mail->isHTML(true);                       // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        // Attempt to send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log any errors
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>