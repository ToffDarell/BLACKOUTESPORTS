<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && isset($_POST['message'])) {
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
    $message = trim($_POST['message']);
    $status = isset($_POST['status']) && $_POST['status'] == 'active' ? 'active' : 'inactive';
    
    // If status is active, deactivate all other advisories first
    if ($status == 'active') {
        $stmt = $conn->prepare("UPDATE advisories SET status = 'inactive' WHERE id != ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    
    // Update advisory
    $stmt = $conn->prepare("UPDATE advisories SET message = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $message, $status, $id);
    
    $response = ['success' => false];
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Advisory updated successfully!";
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