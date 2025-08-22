<?php
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

// Get the active advisory
$query = "SELECT id, message, status, created_at FROM advisories WHERE status = 'active' ORDER BY id DESC LIMIT 1";
$result = $conn->query($query);

$response = [];

if ($result && $result->num_rows > 0) {
    $response['advisory'] = $result->fetch_assoc();
} else {
    // No active advisory, get the most recent one
    $query = "SELECT id, message, status, created_at FROM advisories ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $response['advisory'] = $result->fetch_assoc();
    } else {
        $response['advisory'] = null;
    }
}

$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);