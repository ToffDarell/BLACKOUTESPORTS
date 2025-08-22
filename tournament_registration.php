<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['tournament_id'])) {
    header("Location: tournaments.php");
    exit();
}

$tournament_id = $_GET['tournament_id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "computer_reservation";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $user_id = $_SESSION['user_id'];
    $tournament_id = $_POST['tournament_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if tournament exists and has space
        $check_query = "SELECT registered_teams, max_teams FROM tournaments WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare check query: " . $conn->error);
        }
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Tournament not found");
        }
        
        $tournament = $result->fetch_assoc();
        if ($tournament['registered_teams'] >= $tournament['max_teams']) {
            throw new Exception("Tournament is full");
        }
        
        // Check if user is already registered for this tournament
        $check_reg_query = "SELECT * FROM tournament_registrations WHERE tournament_id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_reg_query);
        if ($stmt) {
            $stmt->bind_param("ii", $tournament_id, $user_id);
            $stmt->execute();
            $reg_result = $stmt->get_result();
            if ($reg_result && $reg_result->num_rows > 0) {
                throw new Exception("You are already registered for this tournament");
            }
        }
        
        // Handle payment proof upload
        $payment_proof_filename = null;
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
            // Create directory if it doesn't exist
            $upload_dir = 'uploads/payment_proofs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $payment_proof_filename = 'payment_' . time() . '_' . $user_id . '_' . $tournament_id . '.' . $file_extension;
            
            // Move uploaded file
            $upload_path = $upload_dir . $payment_proof_filename;
            if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload payment proof");
            }
        }
        
        // Insert into tournament_registrations table
        $insert_query = "INSERT INTO tournament_registrations (tournament_id, user_id, team_name, team_captain, contact_number, team_members, registration_date, reference_number, payment_proof, payment_status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'unpaid')";
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            // Log detailed error info
            error_log("Failed to prepare insert query: " . $conn->error . " (Error code: " . $conn->errno . ")");
            
            throw new Exception("Failed to prepare insert query: " . $conn->error);
        }
        
        // Get payment reference number from form
        $payment_reference = isset($_POST['payment_reference']) ? $_POST['payment_reference'] : '';
        
        $stmt->bind_param("iissssss", 
            $tournament_id, 
            $user_id,
            $_POST['team_name'],
            $_POST['captain_name'],
            $_POST['contact_phone'],
            $_POST['team_members'],
            $payment_reference,
            $payment_proof_filename
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert registration: " . $stmt->error);
        }
        
        // Update tournament registered_teams count
        $update_query = "UPDATE tournaments SET registered_teams = registered_teams + 1 WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare update query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $tournament_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update tournament: " . $stmt->error);
        }
        
        $conn->commit();
        header("Location: tournaments.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }

    $conn->close();
}

// Database connection for fetching user details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Registration - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="tournaments.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="pricing.css">
    <style>
        /* Profile Icon Styles - Matching dashboard */
        .nav-item.dropdown .nav-link {
            display: flex;
            align-items: center;
        }
        
        .nav-item.dropdown .fa-user-circle {
            font-size: 2rem;
            margin-right: 5px;
            color: #dc3545;
        }
        
        /* Remove dropdown indicator and fix alignment */
        .nav-item.dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .dropdown-menu-dark {
            background-color: #252525;
            border: 1px solid #333;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }
        
        .dropdown-item:hover {
            background-color: rgba(220, 53, 69, 0.15);
        }
        
        .dropdown-item i {
            color: #dc3545;
        }
        
        /* Override Bootstrap active class colors */
        .dropdown-item.active, 
        .dropdown-item:active {
            background-color: rgba(220, 53, 69, 0.2) !important;
            color: #fff !important;
        }
        
        .nav-link.active {
            color: #dc3545 !important;
            background-color: transparent !important;
        }
        
        /* Form input styling */
        .form-control, .form-select {
            background-color: #404040;
            border: 1px solid #505050;
            color: #ffffff !important;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #505050;
            border-color: #dc3545;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.2);
            color: #ffffff !important;
        }
        
        textarea.form-control {
            background-color: #404040;
            color: #ffffff !important;
        }
        
        /* Ensure text is white when typing */
        input.form-control, 
        select.form-select, 
        textarea.form-control {
            color: #ffffff !important;
        }
        
        /* Text selection color */
        input.form-control::selection,
        textarea.form-control::selection {
            background-color: #dc3545;
            color: #ffffff;
        }
        
        .form-control::placeholder {
            color: #aaaaaa;
        }
        
        .text-muted {
            color: #aaaaaa !important;
        }
        
        input[type="file"].form-control {
            padding: 8px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <img src="images/blackout.jpg" alt="Blackout Esports" onerror="this.src='https://via.placeholder.com/180x50?text=BLACKOUT+ESPORTS'">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pricing.php">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="membership.php">Membership</a>
                        </li>
                        <!-- Profile Icon with Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i> <?php echo $user_name; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="profileDropdown">
                                <li><h6 class="dropdown-header">Signed in as <strong><?php echo $user_name; ?></strong></h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="reservation_history.php"><i class="fas fa-history me-2"></i>Reservation History</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Log Out</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>Tournament Registration</h1>
                        <p>Complete the form below to register your team</p>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8 mx-auto animate__animated animate__fadeInUp">
                    <div class="pricing-card">
                        <h2 class="pricing-title mb-4"><i class="fas fa-trophy me-2"></i>Team Information</h2>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                            
                            <div class="mb-4">
                                <label class="form-label" style="color: #dc3545;"><i class="fas fa-users me-2"></i>Team Name</label>
                                <input type="text" class="form-control" name="team_name" required placeholder="Enter your team name">
                            </div>

                            <div class="mb-4">
                                <label class="form-label" style="color: #dc3545;"><i class="fas fa-user-shield me-2"></i>Team Captain (Your Name)</label>
                                <input type="text" class="form-control" name="captain_name" required placeholder="Enter captain's full name">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label class="form-label" style="color: #dc3545;"><i class="fas fa-envelope me-2"></i>Contact Email</label>
                                        <input type="email" class="form-control" name="contact_email" required placeholder="Enter contact email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label class="form-label" style="color: #dc3545;"><i class="fas fa-phone me-2"></i>Contact Phone</label>
                                        <input type="tel" class="form-control" name="contact_phone" required placeholder="Enter contact phone">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" style="color: #dc3545;"><i class="fas fa-money-bill-wave me-2"></i>Payment Information</label>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Please send your payment to GCash number: <strong>09123456789</strong>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: #dc3545;"><i class="fas fa-receipt me-2"></i>GCash Reference Number</label>
                                        <input type="text" class="form-control" name="payment_reference" placeholder="Enter GCash reference number">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: #dc3545;"><i class="fas fa-image me-2"></i>Payment Screenshot</label>
                                        <input type="file" class="form-control" name="payment_proof" accept="image/*">
                                        <small class="text-muted">Upload a screenshot of your GCash payment confirmation</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" style="color: #dc3545;"><i class="fas fa-user-friends me-2"></i>Team Members</label>
                                <textarea class="form-control" name="team_members" rows="5" required placeholder="List all team member names (one per line, including substitutes if any)"></textarea>
                                <small class="text-muted">Make sure to include all participants that will represent your team</small>
                            </div>

                            <div class="info-badge">
                                <i class="fas fa-info-circle"></i>
                                By submitting this form, you agree to the tournament rules and terms of participation. Teams must arrive 30 minutes before the scheduled match time.
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="tournaments.php" class="btn btn-outline-danger">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Tournaments
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-check me-2"></i>Submit Registration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
