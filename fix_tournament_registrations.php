<?php
// This is a one-time script to fix the tournament registrations
// Run this file once then delete it

session_start();

// Only allow admins to run this script
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access");
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

echo "<h1>Tournament Registration Fix Utility</h1>";

// Step 1: Create the tournament_registrations table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS tournament_registrations (
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

if ($conn->query($create_table)) {
    echo "<p>Tournament registration table created or already exists.</p>";
} else {
    die("<p>Error creating table: " . $conn->error . "</p>");
}

// Step 2: Find tournaments with registration data but missing in tournament_registrations
$query = "SELECT t.id, t.team_name, t.captain_name, t.contact_email, t.contact_phone, t.team_members
          FROM tournaments t
          LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id
          WHERE t.team_name IS NOT NULL 
          AND t.team_name != '' 
          AND tr.id IS NULL";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo "<p>No tournaments with registration data found.</p>";
} else {
    echo "<p>Found " . $result->num_rows . " tournaments with registration data to transfer.</p>";
    
    // Get a list of registered users
    $users = [];
    $user_result = $conn->query("SELECT user_id, user_name, user_email FROM users");
    while ($row = $user_result->fetch_assoc()) {
        $users[$row['user_email']] = $row;
    }
    
    $count = 0;
    while ($tournament = $result->fetch_assoc()) {
        $tournament_id = $tournament['id'];
        $team_name = $tournament['team_name'];
        $team_captain = $tournament['captain_name'];
        $contact_email = $tournament['contact_email'];
        $contact_phone = $tournament['contact_phone'];
        $team_members = $tournament['team_members'];
        
        // Try to find a user ID based on email
        $user_id = 0;
        if (isset($users[$contact_email])) {
            $user_id = $users[$contact_email]['user_id'];
        }
        
        // If no user found, use admin ID as a fallback
        if ($user_id === 0) {
            $user_id = $_SESSION['admin_id'];
        }
        
        // Insert into tournament_registrations
        $insert = "INSERT INTO tournament_registrations 
                   (tournament_id, user_id, team_name, team_captain, contact_number, team_members, registration_date) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insert);
        if (!$stmt) {
            echo "<p>Error preparing statement: " . $conn->error . "</p>";
            continue;
        }
        
        $stmt->bind_param("iissss", 
            $tournament_id, 
            $user_id,
            $team_name,
            $team_captain,
            $contact_phone,
            $team_members
        );
        
        if ($stmt->execute()) {
            $count++;
            echo "<p>Transferred registration for tournament ID $tournament_id, team '$team_name'</p>";
        } else {
            echo "<p>Error transferring registration for tournament ID $tournament_id: " . $stmt->error . "</p>";
        }
    }
    
    echo "<h2>Fixed $count tournament registrations</h2>";
}

echo "<p><a href='manage_tournaments.php'>Return to Manage Tournaments</a></p>";

$conn->close();
?> 