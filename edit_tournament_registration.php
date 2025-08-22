<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Make sure the tournament_registrations table exists with the correct structure
$check_table = $conn->query("SHOW TABLES LIKE 'tournament_registrations'");
if ($check_table->num_rows === 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE tournament_registrations (
        id INT(11) NOT NULL AUTO_INCREMENT,
        tournament_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        team_name VARCHAR(255) NOT NULL,
        team_captain VARCHAR(255) NOT NULL,
        contact_number VARCHAR(50) NOT NULL,
        team_members TEXT NOT NULL,
        registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        payment_status ENUM('paid','unpaid') DEFAULT 'unpaid',
        PRIMARY KEY (id),
        KEY tournament_id (tournament_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($create_table_sql)) {
        die("Error creating tournament_registrations table: " . $conn->error . "<br>SQL: " . $create_table_sql);
    }
    
    // Log success message
    error_log("Created tournament_registrations table successfully");
} else {
    // Check if the structure is correct
    $structure_check = $conn->query("DESCRIBE tournament_registrations");
    $has_notes_column = false;
    $has_payment_status_column = false;
    
    while ($col = $structure_check->fetch_assoc()) {
        if ($col['Field'] === 'notes') {
            $has_notes_column = true;
        }
        if ($col['Field'] === 'payment_status') {
            $has_payment_status_column = true;
        }
    }
    
    // Add missing columns if needed
    if (!$has_notes_column) {
        $conn->query("ALTER TABLE tournament_registrations ADD COLUMN notes TEXT AFTER registration_date");
    }
    
    if (!$has_payment_status_column) {
        $conn->query("ALTER TABLE tournament_registrations ADD COLUMN payment_status ENUM('paid','unpaid') DEFAULT 'unpaid' AFTER notes");
    }
}

// Initialize variables
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$registration_id = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : 0;
$team_name = isset($_GET['team']) ? $_GET['team'] : '';
$registration = null;
$tournament = null;
$error_message = '';
$success_message = '';

// Get tournament details
if ($tournament_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    if ($stmt === false) {
        $error_message = "Error preparing tournament query: " . $conn->error;
    } else {
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $tournament = $result->fetch_assoc();
        } else {
            $error_message = "Tournament not found";
        }
        $stmt->close();
    }
} else {
    $error_message = "Invalid tournament ID";
}

// Get registration details
if (empty($error_message)) {
    if ($registration_id > 0) {
        // Get from tournament_registrations table
        $stmt = $conn->prepare("SELECT * FROM tournament_registrations WHERE id = ?");
        if ($stmt === false) {
            $error_message = "Error preparing registration query: " . $conn->error;
        } else {
            $stmt->bind_param("i", $registration_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $registration = $result->fetch_assoc();
                
                // Get user info if available
                if ($registration['user_id'] > 0) {
                    $user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                    if ($user_stmt === false) {
                        $error_message = "Error preparing user query: " . $conn->error;
                    } else {
                        $user_stmt->bind_param("i", $registration['user_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        if ($user_result->num_rows > 0) {
                            $user_info = $user_result->fetch_assoc();
                            $registration['user_name'] = $user_info['user_name'];
                            $registration['user_email'] = $user_info['user_email'];
                            $registration['phone_number'] = $user_info['phone_number'] ?? '';
                        }
                        $user_stmt->close();
                    }
                }
            }
            $stmt->close();
        }
    } elseif (!empty($team_name)) {
        // Check tournament_registrations table first
        $stmt = $conn->prepare("SELECT * FROM tournament_registrations WHERE tournament_id = ? AND team_name = ?");
        if ($stmt === false) {
            $error_message = "Error preparing tournament_registrations query: " . $conn->error;
        } else {
            $stmt->bind_param("is", $tournament_id, $team_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $registration = $result->fetch_assoc();
                $registration_id = $registration['id'];
                
                // Get user info if available
                if ($registration['user_id'] > 0) {
                    $user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                    if ($user_stmt === false) {
                        $error_message = "Error preparing user query: " . $conn->error;
                    } else {
                        $user_stmt->bind_param("i", $registration['user_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        if ($user_result->num_rows > 0) {
                            $user_info = $user_result->fetch_assoc();
                            $registration['user_name'] = $user_info['user_name'];
                            $registration['user_email'] = $user_info['user_email'];
                            $registration['phone_number'] = $user_info['phone_number'] ?? '';
                        }
                        $user_stmt->close();
                    }
                }
            } else {
                // Check tournaments table
                $stmt2 = $conn->prepare("SELECT * FROM tournaments WHERE id = ? AND team_name = ?");
                if ($stmt2 === false) {
                    $error_message = "Error preparing tournaments query: " . $conn->error;
                } else {
                    $stmt2->bind_param("is", $tournament_id, $team_name);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    
                    if ($result2->num_rows > 0) {
                        $tournament_data = $result2->fetch_assoc();
                        
                        // Create registration from tournaments data
                        $registration = [
                            'id' => 0,
                            'tournament_id' => $tournament_id,
                            'user_id' => 0,
                            'team_name' => $tournament_data['team_name'],
                            'team_captain' => $tournament_data['captain_name'] ?? $tournament_data['team_name'],
                            'contact_number' => $tournament_data['contact_phone'] ?? '',
                            'team_members' => $tournament_data['team_members'] ?? '',
                            'payment_status' => 'unpaid',
                            'user_name' => $tournament_data['captain_name'] ?? '',
                            'user_email' => $tournament_data['contact_email'] ?? ''
                        ];
                    }
                    $stmt2->close();
                }
            }
            $stmt->close();
        }
    }
    
    if ($registration === null) {
        $error_message = "Registration not found";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    // Get form data
    $team_name = $_POST['team_name'] ?? '';
    $team_captain = $_POST['team_captain'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $team_members = $_POST['team_members'] ?? '';
    $payment_status = isset($_POST['payment_status']) && $_POST['payment_status'] === 'paid' ? 'paid' : 'unpaid';
    $user_name = $_POST['user_name'] ?? '';
    $user_email = $_POST['user_email'] ?? '';
    
    if (empty($team_name) || empty($team_captain)) {
        $error_message = "Team name and captain are required";
    } else {
        // Update or create registration
        if ($registration_id > 0) {
            // Update existing registration
            $stmt = $conn->prepare("UPDATE tournament_registrations SET 
                                    team_name = ?, 
                                    team_captain = ?, 
                                    contact_number = ?, 
                                    team_members = ?, 
                                    payment_status = ? 
                                    WHERE id = ?");
            
            if ($stmt === false) {
                $error_message = "Error preparing update statement: " . $conn->error;
            } else {
                $stmt->bind_param("sssssi", 
                    $team_name, 
                    $team_captain, 
                    $contact_number, 
                    $team_members, 
                    $payment_status, 
                    $registration_id
                );
                
                if ($stmt->execute()) {
                    $success_message = "Registration updated successfully";
                    
                    // Also update tournament's registered_teams count if needed
                    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tournament_registrations WHERE tournament_id = ?");
                    if ($count_stmt) {
                        $count_stmt->bind_param("i", $tournament_id);
                        $count_stmt->execute();
                        $count_result = $count_stmt->get_result();
                        $reg_count = $count_result->fetch_assoc()['count'];
                        
                        $update_count = $conn->prepare("UPDATE tournaments SET registered_teams = ? WHERE id = ?");
                        if ($update_count) {
                            $update_count->bind_param("ii", $reg_count, $tournament_id);
                            $update_count->execute();
                            $update_count->close();
                        }
                        
                        $count_stmt->close();
                    }
                } else {
                    $error_message = "Error updating registration: " . $conn->error;
                }
                
                $stmt->close();
            }
        } else {
            // Create new registration
            $stmt = $conn->prepare("INSERT INTO tournament_registrations 
                                    (tournament_id, user_id, team_name, team_captain, contact_number, 
                                    team_members, registration_date, payment_status) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
            
            if ($stmt === false) {
                $error_message = "Error preparing insert statement: " . $conn->error;
            } else {
                // Try to find user ID if email is provided
                $user_id = 0;
                if (!empty($user_email)) {
                    $user_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
                    if ($user_stmt) {
                        $user_stmt->bind_param("s", $user_email);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        if ($user_result->num_rows > 0) {
                            $user_id = $user_result->fetch_assoc()['user_id'];
                        }
                        $user_stmt->close();
                    }
                }
                
                $stmt->bind_param("iisssss", 
                    $tournament_id, 
                    $user_id, 
                    $team_name, 
                    $team_captain, 
                    $contact_number, 
                    $team_members, 
                    $payment_status
                );
                
                if ($stmt->execute()) {
                    $success_message = "Registration created successfully";
                    $registration_id = $conn->insert_id;
                    
                    // Update tournament's registered_teams count
                    $update_count = $conn->prepare("UPDATE tournaments SET registered_teams = registered_teams + 1 WHERE id = ?");
                    if ($update_count) {
                        $update_count->bind_param("i", $tournament_id);
                        $update_count->execute();
                        $update_count->close();
                    }
                } else {
                    $error_message = "Error creating registration: " . $conn->error;
                }
                
                $stmt->close();
            }
        }
        
        // If successful, redirect back to the tournament registrations view
        if (!empty($success_message)) {
            header("Location: manage_tournaments.php?view_registrations=$tournament_id&msg=" . urlencode($success_message));
            exit();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tournament Registration - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="tournaments.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="pricing.css">
    
    <style>
        body {
            color: #fff;
            height: inherit;
        }
        
        .admin-section {
            padding: 40px 0;
        }
        
        .admin-card {
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.2);
            border: 1px solid rgba(255, 0, 0, 0.1);
            padding: 25px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }
        
        .admin-card:hover {
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.3);
        }
        
        .section-title {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .message-alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #fff;
        }
        
        .message-success {
            background-color: rgba(25, 135, 84, 0.2);
            border-left: 4px solid #198754;
        }
        
        .message-error {
            background-color: rgba(220, 53, 69, 0.2);
            border-left: 4px solid #dc3545;
        }
        
        .form-label {
            color: #fff;
            font-weight: 500;
        }
        
        .btn-back {
            margin-bottom: 20px;
        }
        
        .tournament-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.3);
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(255, 0, 0, 0.25);
            border-color: rgba(255, 0, 0, 0.5);
        }
        
        .form-text {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Dark placeholders */
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <img src="images/blackout.jpg" alt="Blackout Esports" onerror="this.src='https://via.placeholder.com/180x50?text=BLACKOUT+ESPORTS'">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_reservations.php">Reservations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="add_tournament.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_login.php">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="admin-section">
        <div class="container">
            <div class="tournament-header animate__animated animate__fadeIn">
                <div>
                    <a href="manage_tournaments.php?view_registrations=<?php echo $tournament_id; ?>" class="btn btn-outline-light btn-back">
                        <i class="fas fa-arrow-left me-2"></i> Back to Registrations
                    </a>
                    <h2 class="section-title">
                        <i class="fas fa-edit me-2"></i> Edit Registration
                    </h2>
                    <?php if (!empty($tournament)): ?>
                    <div class="text-muted mb-3">
                        Tournament: <?php echo htmlspecialchars($tournament['name']); ?> (<?php echo htmlspecialchars($tournament['game']); ?>)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
            <div class="message-alert message-error animate__animated animate__fadeIn">
                <i class="fas fa-times-circle me-2"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="message-alert message-success animate__animated animate__fadeIn">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (empty($error_message) || !empty($registration)): ?>
            <div class="admin-card animate__animated animate__fadeIn">
                <form method="post" action="">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="user_name" class="form-label">Participant Name</label>
                            <input type="text" class="form-control" id="user_name" name="user_name" value="<?php echo htmlspecialchars($registration['user_name'] ?? ''); ?>">
                            <div class="form-text">Name of the person registering for the tournament</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="user_email" name="user_email" value="<?php echo htmlspecialchars($registration['user_email'] ?? ''); ?>">
                            <div class="form-text">Contact email for tournament updates</div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="team_name" class="form-label">Team Name *</label>
                            <input type="text" class="form-control" id="team_name" name="team_name" value="<?php echo htmlspecialchars($registration['team_name'] ?? ''); ?>" required>
                            <div class="form-text">Name of the team participating in the tournament</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="team_captain" class="form-label">Team Captain *</label>
                            <input type="text" class="form-control" id="team_captain" name="team_captain" value="<?php echo htmlspecialchars($registration['team_captain'] ?? ''); ?>" required>
                            <div class="form-text">Name of the team captain or main contact</div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($registration['contact_number'] ?? ''); ?>">
                            <div class="form-text">Phone number for tournament day communication</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <option value="unpaid" <?php echo (isset($registration['payment_status']) && $registration['payment_status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="paid" <?php echo (isset($registration['payment_status']) && $registration['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                            <div class="form-text">Current payment status for this registration</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="team_members" class="form-label">Team Members</label>
                        <textarea class="form-control" id="team_members" name="team_members" rows="3"><?php echo htmlspecialchars($registration['team_members'] ?? ''); ?></textarea>
                        <div class="form-text">List of team members (comma separated)</div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="manage_tournaments.php?view_registrations=<?php echo $tournament_id; ?>" class="btn btn-outline-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 