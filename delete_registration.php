<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Log incoming request
error_log("Delete Request: " . json_encode($_POST));

// Check if all required parameters are present
if (!isset($_POST['tournament_id']) || !isset($_POST['team_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Get parameters
$tournament_id = (int)$_POST['tournament_id'];
$registration_id = isset($_POST['registration_id']) ? (int)$_POST['registration_id'] : 0;
$team_name = $_POST['team_name'];

// Log the parameters
error_log("Parameters: tournament_id={$tournament_id}, registration_id={$registration_id}, team_name={$team_name}");

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$success = false;
$message = '';

// First, try to delete from tournament_registrations table if registration_id is provided
if ($registration_id > 0) {
    $stmt = $conn->prepare("DELETE FROM tournament_registrations WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $registration_id);
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            error_log("Deleted registration with ID {$registration_id}, affected rows: {$affected}");
            $success = $affected > 0;
            $message = $success ? "Registration deleted successfully" : "No registration found with ID {$registration_id}";
        } else {
            $message = "Error deleting registration: " . $conn->error;
            error_log($message);
        }
        $stmt->close();
    } else {
        $message = "Error preparing delete statement: " . $conn->error;
        error_log($message);
    }
} else {
    // Try to find registration in tournament_registrations table using tournament_id and team_name
    error_log("Looking for registration with tournament_id={$tournament_id} and team_name={$team_name}");
    $stmt = $conn->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND team_name = ?");
    if ($stmt) {
        $stmt->bind_param("is", $tournament_id, $team_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $reg_id = $row['id'];
            error_log("Found registration ID: {$reg_id}");
            
            $delete_stmt = $conn->prepare("DELETE FROM tournament_registrations WHERE id = ?");
            if ($delete_stmt) {
                $delete_stmt->bind_param("i", $reg_id);
                if ($delete_stmt->execute()) {
                    $affected = $delete_stmt->affected_rows;
                    error_log("Deleted registration, affected rows: {$affected}");
                    $success = $affected > 0;
                    $message = $success ? "Registration deleted successfully" : "Failed to delete registration";
                } else {
                    $message = "Error deleting registration: " . $conn->error;
                    error_log($message);
                }
                $delete_stmt->close();
            } else {
                $message = "Error preparing delete statement: " . $conn->error;
                error_log($message);
            }
        } else {
            error_log("Registration not found in tournament_registrations table, checking tournaments table");
            // If not found in tournament_registrations, check if it's in tournaments table
            $check_stmt = $conn->prepare("SELECT id FROM tournaments WHERE id = ? AND team_name = ?");
            if ($check_stmt) {
                $check_stmt->bind_param("is", $tournament_id, $team_name);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    error_log("Found team in tournaments table with ID: {$tournament_id}");
                    // Update the tournaments table to clear the registration fields
                    $update_stmt = $conn->prepare("UPDATE tournaments SET 
                                              team_name = NULL, 
                                              captain_name = NULL,
                                              contact_email = NULL,
                                              contact_phone = NULL,
                                              team_members = NULL,
                                              registered_teams = GREATEST(registered_teams - 1, 0)
                                              WHERE id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("i", $tournament_id);
                        if ($update_stmt->execute()) {
                            $affected = $update_stmt->affected_rows;
                            error_log("Updated tournament, affected rows: {$affected}");
                            $success = $affected > 0;
                            $message = $success ? "Team removed from the tournament" : "Failed to remove team";
                        } else {
                            $message = "Error removing team: " . $conn->error;
                            error_log($message);
                        }
                        $update_stmt->close();
                    } else {
                        $message = "Error preparing update statement: " . $conn->error;
                        error_log($message);
                    }
                } else {
                    $message = "Registration not found in either table";
                    error_log($message);
                }
                $check_stmt->close();
            } else {
                $message = "Error checking tournaments table: " . $conn->error;
                error_log($message);
            }
        }
        $stmt->close();
    } else {
        $message = "Error preparing select statement: " . $conn->error;
        error_log($message);
    }
}

// If deletion was successful, update the registered_teams count
if ($success) {
    // Only update count if we deleted from tournament_registrations (not necessary if updating tournaments table directly)
    if (strpos($message, "Registration deleted successfully") === 0) {
        // Count registrations for this tournament
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tournament_registrations WHERE tournament_id = ?");
        if ($count_stmt) {
            $count_stmt->bind_param("i", $tournament_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $reg_count = $count_result->fetch_assoc()['count'];
            error_log("Registration count for tournament {$tournament_id}: {$reg_count}");
            
            // Update tournament's registered_teams count
            $update_count = $conn->prepare("UPDATE tournaments SET registered_teams = ? WHERE id = ?");
            if ($update_count) {
                $update_count->bind_param("ii", $reg_count, $tournament_id);
                if ($update_count->execute()) {
                    error_log("Updated tournament registration count to {$reg_count}");
                } else {
                    error_log("Error updating registration count: " . $conn->error);
                }
                $update_count->close();
            } else {
                error_log("Error preparing count update statement: " . $conn->error);
            }
            $count_stmt->close();
        } else {
            error_log("Error preparing count statement: " . $conn->error);
        }
    }
}

// Close database connection
$conn->close();

// Log final result
error_log("Final result: success=" . ($success ? "true" : "false") . ", message={$message}");

// Send response
$response = ['success' => $success, 'message' => $message];
echo json_encode($response);
exit(); 