<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Get all advisories
$query = "SELECT id, message, status, created_at, updated_at FROM advisories ORDER BY id DESC";
$result = $conn->query($query);

$advisories = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $advisories[] = $row;
    }
}

$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['advisories' => $advisories]);