<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Process cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    $cancellation_reason = isset($_POST['cancellation_reason']) ? $_POST['cancellation_reason'] : '';
    
    // Verify the reservation belongs to the current user
    $check_query = "SELECT * FROM reservations WHERE reservation_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $reservation_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Reservation not found or doesn't belong to user
        $message = "Invalid reservation selected.";
        $messageType = "danger";
    } else {
        $reservation = $result->fetch_assoc();
        
        // Check if the reservation can be cancelled (not in the past, and at least 1 hour before start time)
        $reservation_datetime = strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
        $cutoff_time = $reservation_datetime - (60 * 60); // 1 hour before reservation
        $current_time = time();
        
        if ($current_time > $reservation_datetime) {
            $message = "Cannot cancel a reservation that has already started or ended.";
            $messageType = "danger";
        } elseif ($current_time > $cutoff_time) {
            $message = "Reservations can only be cancelled at least 1 hour before the scheduled time.";
            $messageType = "danger";
        } else {
            // Check if payment is already done (status is Confirmed)
            if ($reservation['status'] === 'Confirmed') {
                // If confirmed, handle as refund request
                header("Location: request_refund.php?reservation_id=" . $reservation_id);
                exit();
            } else {
                // For pending reservations, just cancel
                $update_query = "UPDATE reservations SET 
                                status = 'Cancelled', 
                                cancellation_reason = ?, 
                                cancellation_time = NOW() 
                                WHERE reservation_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param('si', $cancellation_reason, $reservation_id);
                
                if ($update_stmt->execute()) {
                    $message = "Your reservation has been successfully cancelled.";
                    $messageType = "success";
                    
                    // Also update computer status if needed
                    if ($reservation['status'] === 'Confirmed') {
                        $update_computer = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
                        $update_computer->bind_param('s', $reservation['computer_number']);
                        $update_computer->execute();
                    }
                    
                    // Send cancellation email notification
                    require_once('notify_reservation_user.php');
                    
                    // Get user details
                    $user_query = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
                    $user_query->bind_param('i', $user_id);
                    $user_query->execute();
                    $user_result = $user_query->get_result();
                    $user = $user_result->fetch_assoc();
                    
                    sendReservationStatusEmail(
                        $user['user_email'],
                        $user['user_name'],
                        'Cancelled',
                        $reservation['computer_number'],
                        $reservation['reservation_date'],
                        $reservation['start_time'],
                        $reservation['end_time'],
                        "User cancelled: " . $cancellation_reason,
                        $reservation_id
                    );
                } else {
                    $message = "Error cancelling reservation: " . $update_stmt->error;
                    $messageType = "danger";
                }
                $update_stmt->close();
            }
        }
    }
    $check_stmt->close();
}

// Redirect back to user dashboard with message
$_SESSION['message'] = $message;
$_SESSION['messageType'] = $messageType;
header("Location: dashboard.php");
exit();
?> 