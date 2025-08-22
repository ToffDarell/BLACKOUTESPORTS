<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("location:admin_login.php");
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

// Get tournament ID from URL if provided
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

echo "<h1>Tournament Registration Diagnostic</h1>";

// Check if tournament_registrations table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'tournament_registrations'");
if ($tableCheck->num_rows == 0) {
    echo "<p>The tournament_registrations table does not exist!</p>";
    
    // Create the table
    echo "<p>Attempting to create the table...</p>";
    $createTable = "CREATE TABLE IF NOT EXISTS tournament_registrations (
        id INT(11) NOT NULL AUTO_INCREMENT,
        tournament_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        team_name VARCHAR(255) NOT NULL,
        team_captain VARCHAR(255) NOT NULL,
        contact_number VARCHAR(50) NOT NULL,
        team_members TEXT NOT NULL,
        registration_date DATETIME NOT NULL,
        notes TEXT,
        payment_status ENUM('paid','unpaid') DEFAULT 'unpaid',
        PRIMARY KEY (id),
        KEY tournament_id (tournament_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($createTable)) {
        echo "<p>Table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>The tournament_registrations table exists.</p>";
    
    // Show table structure
    echo "<h2>Table Structure:</h2>";
    $result = $conn->query("DESCRIBE tournament_registrations");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Count all registrations
$result = $conn->query("SELECT COUNT(*) as total FROM tournament_registrations");
$row = $result->fetch_assoc();
echo "<h2>Total Registrations: " . $row['total'] . "</h2>";

// List tournaments
echo "<h2>Tournaments:</h2>";
$result = $conn->query("SELECT * FROM tournaments ORDER BY date DESC");
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Game</th><th>Date</th><th>Registered Teams</th><th>Action</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['name'] . "</td>";
    echo "<td>" . $row['game'] . "</td>";
    echo "<td>" . $row['date'] . "</td>";
    echo "<td>" . $row['registered_teams'] . "</td>";
    echo "<td><a href='check_registrations.php?tournament_id=" . $row['id'] . "'>View Registrations</a></td>";
    echo "</tr>";
}
echo "</table>";

// If a tournament ID is provided, show its registrations
if ($tournament_id > 0) {
    echo "<h2>Registrations for Tournament ID: $tournament_id</h2>";
    
    // Get tournament details
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tournament = $result->fetch_assoc();
    
    if ($tournament) {
        echo "<p>Tournament: " . $tournament['name'] . " (" . $tournament['game'] . ")</p>";
        echo "<p>Registered Teams (from tournaments table): " . $tournament['registered_teams'] . "</p>";
        
        // Check actual registrations
        $stmt = $conn->prepare("SELECT tr.*, u.user_name, u.user_email FROM tournament_registrations tr
                                LEFT JOIN users u ON tr.user_id = u.user_id 
                                WHERE tr.tournament_id = ?");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p>Actual registrations found: " . $result->num_rows . "</p>";
            echo "<table border='1'><tr><th>ID</th><th>User</th><th>Team Name</th><th>Captain</th><th>Registration Date</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['user_name'] . " (" . $row['user_email'] . ")</td>";
                echo "<td>" . $row['team_name'] . "</td>";
                echo "<td>" . $row['team_captain'] . "</td>";
                echo "<td>" . $row['registration_date'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Option to fix the count
            if ($tournament['registered_teams'] != $result->num_rows) {
                echo "<p>The registered_teams count in the tournaments table (" . $tournament['registered_teams'] . 
                     ") doesn't match the actual number of registrations (" . $result->num_rows . ").</p>";
                echo "<p><a href='check_registrations.php?tournament_id=$tournament_id&fix=1'>Fix the count</a></p>";
            }
        } else {
            echo "<p>No registrations found for this tournament.</p>";
            
            // If registered_teams > 0 but no actual registrations, offer to reset
            if ($tournament['registered_teams'] > 0) {
                echo "<p>The tournaments table shows " . $tournament['registered_teams'] . " registered teams, but there are no actual registrations.</p>";
                echo "<p><a href='check_registrations.php?tournament_id=$tournament_id&fix=1'>Reset the count to 0</a></p>";
            }
        }
    } else {
        echo "<p>Tournament not found!</p>";
    }
    
    // Fix the count if requested
    if (isset($_GET['fix']) && $_GET['fix'] == 1) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tournament_registrations WHERE tournament_id = ?");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $actual_count = $row['count'];
        
        $stmt = $conn->prepare("UPDATE tournaments SET registered_teams = ? WHERE id = ?");
        $stmt->bind_param("ii", $actual_count, $tournament_id);
        
        if ($stmt->execute()) {
            echo "<p>Count fixed successfully! Registered teams set to " . $actual_count . ".</p>";
            echo "<p><a href='check_registrations.php?tournament_id=$tournament_id'>Refresh</a></p>";
        } else {
            echo "<p>Error fixing count: " . $conn->error . "</p>";
        }
    }
}

$conn->close();
?> 