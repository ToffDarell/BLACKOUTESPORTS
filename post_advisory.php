<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("location:admin_login.php");
    exit();
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Prepare data
    $message = trim($_POST['message']);
    $status = isset($_POST['status']) && $_POST['status'] == 'active' ? 'active' : 'inactive';
    
    // If status is active, deactivate all other advisories first
    if ($status == 'active') {
        $stmt = $conn->prepare("UPDATE advisories SET status = 'inactive'");
        $stmt->execute();
    }
    
    // Insert new advisory
    $stmt = $conn->prepare("INSERT INTO advisories (message, status) VALUES (?, ?)");
    $stmt->bind_param("ss", $message, $status);
    
    $response = ['success' => false];
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Advisory posted successfully!";
    } else {
        $response['message'] = "Error: " . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
    
    // Return JSON response if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // Redirect back to dashboard with a message
        $_SESSION['advisory_message'] = $response['message'];
        header("location: admin_dashboard.php");
        exit();
    }
} else {
    // Not a POST request, redirect to dashboard
    header("location: admin_dashboard.php");
    exit();
}