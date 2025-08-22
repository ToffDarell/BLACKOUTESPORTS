<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form is submitted with required data
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "computer_reservation";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
        exit();
    }

    // Prepare data
    $id = (int)$_POST['id'];
    
    // Delete advisory
    $stmt = $conn->prepare("DELETE FROM advisories WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    $response = ['success' => false];
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Advisory deleted successfully!";
    } else {
        $response['message'] = "Error: " . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    // Invalid request
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}