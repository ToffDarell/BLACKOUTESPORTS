<?php
// Start session
session_start();

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include notification function
require_once('notify_reservation_user.php');

// Process only POST requests with necessary parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['update_status'])) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "computer_reservation";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Get parameters
    $reservation_id = $_POST['reservation_id'];
    $status = $_POST['update_status'];
    $auto_complete = isset($_POST['auto_complete']) && $_POST['auto_complete'] === 'true';

    // Make sure the reservation exists and get the computer number
    $check_query = $conn->prepare("
        SELECT r.*, u.user_name, u.user_email 
        FROM reservations r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.reservation_id = ?
    ");
    $check_query->bind_param('i', $reservation_id);
    $check_query->execute();
    $result = $check_query->get_result();

    if ($row = $result->fetch_assoc()) {
        // Update reservation status
        $update_status = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
        $update_status->bind_param('si', $status, $reservation_id);

        if ($update_status->execute()) {
            // If status is 'Completed' or 'Time Expired', make the computer available
            if ($status === 'Completed' || $status === 'Time Expired') {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
                $update_computer->bind_param('s', $row['computer_number']);
                $update_computer->execute();
            }

            // Send email notification
            $email_sent = sendReservationStatusEmail(
                $row['user_email'],
                $row['user_name'],
                $status,
                $row['computer_number'],
                $row['reservation_date'],
                $row['start_time'],
                $row['end_time'],
                '', // Empty decline reason
                $reservation_id
            );

            // Return success response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Session status updated successfully',
                'email_sent' => $email_sent,
                'status' => $status,
                'auto_completed' => $auto_complete
            ]);
        } else {
            // Return error response
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
    } else {
        // Return error if reservation not found
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
    }
} else {
    // Return error for invalid request
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
}
?> 