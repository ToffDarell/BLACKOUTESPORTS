<?php
session_start();

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

// SIMPLE DIRECT DELETE - Debug with output
if (isset($_GET['direct_delete_reg'])) {
    $reg_id = $_GET['direct_delete_reg'];
    $tournament_id = isset($_GET['tid']) ? $_GET['tid'] : '0';
    $success = false;
    
    // Super simple direct query
    $delete_query = "DELETE FROM tournament_registrations WHERE id = $reg_id";
    if ($conn->query($delete_query)) {
        $success = true;
        // Update the tournament count
        if ($tournament_id > 0) {
            $count_query = "SELECT COUNT(*) as count FROM tournament_registrations WHERE tournament_id = $tournament_id";
            $count_result = $conn->query($count_query);
            if ($count_result && $count_result->num_rows > 0) {
                $reg_count = $count_result->fetch_assoc()['count'];
                $update_query = "UPDATE tournaments SET registered_teams = $reg_count WHERE id = $tournament_id";
                $conn->query($update_query);
            }
        }
        echo "<script>alert('Registration deleted successfully! ID: $reg_id');</script>";
    } else {
        echo "<script>alert('Error deleting registration ID $reg_id: " . $conn->error . "');</script>";
    }
    
    // Force redirection after showing message
    echo "<script>window.location = 'manage_tournaments.php?view_registrations=$tournament_id';</script>";
    exit();
}

// Remove all registrations for a tournament
if (isset($_GET['clear_all_registrations'])) {
    $tournament_id = $_GET['clear_all_registrations'];
    $success = false;
    
    // Super simple direct query to remove all registrations
    $delete_query = "DELETE FROM tournament_registrations WHERE tournament_id = $tournament_id";
    
    if ($conn->query($delete_query)) {
        $success = true;
        // Update the registered_teams count to 0
        $update_query = "UPDATE tournaments SET registered_teams = 0 WHERE id = $tournament_id";
        $conn->query($update_query);
        
        echo "<script>alert('All registrations removed successfully from tournament ID: $tournament_id!');</script>";
    } else {
        echo "<script>alert('Error removing registrations from tournament ID $tournament_id: " . $conn->error . "');</script>";
    }
    
    // Force redirection after showing message
    echo "<script>window.location = 'manage_tournaments.php?view_registrations=$tournament_id';</script>";
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("location:admin_login.php");
    exit();
}

// Check and create the tournament_registrations table if needed
$conn->query("CREATE TABLE IF NOT EXISTS tournament_registrations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle tournament deletion
if (isset($_GET['delete_id'])) {
    $tournament_id = $_GET['delete_id'];
    
    // Delete tournament
    $stmt = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $stmt->close();
    
    // Also delete registrations for this tournament
    $stmt = $conn->prepare("DELETE FROM tournament_registrations WHERE tournament_id = ?");
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: manage_tournaments.php?msg=Tournament deleted successfully");
    exit();
}

// Get all tournaments
$tournaments = [];
$result = $conn->query("SELECT * FROM tournaments ORDER BY date DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tournament_id = $row['id'];
        $reg_count = 0;
        
        // Check for registrations in tournament_registrations table
        $reg_check = $conn->prepare("SELECT COUNT(*) as count FROM tournament_registrations WHERE tournament_id = ?");
        if ($reg_check) {
            $reg_check->bind_param("i", $tournament_id);
            $reg_check->execute();
            $reg_result = $reg_check->get_result();
            if ($reg_result) {
                $reg_count = $reg_result->fetch_assoc()['count'];
            }
            $reg_check->close();
        }
        
        // Update the registered_teams count if it's wrong
        if ($row['registered_teams'] != $reg_count) {
            $update_count = $conn->prepare("UPDATE tournaments SET registered_teams = ? WHERE id = ?");
            if ($update_count) {
                $update_count->bind_param("ii", $reg_count, $tournament_id);
                $update_count->execute();
                $update_count->close();
                
                // Also update the row we're about to return
                $row['registered_teams'] = $reg_count;
            }
        }
        
        $tournaments[] = $row;
    }
}

// Get registration details for a specific tournament if requested
$registrations = [];
$tournament_details = null;

if (isset($_GET['view_registrations'])) {
    $tournament_id = $_GET['view_registrations'];
    
    // Get tournament details
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    if ($stmt === false) {
        die("Error preparing tournament query: " . $conn->error);
    }
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tournament_details = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tournament_details) {
        header("Location: manage_tournaments.php?msg=Tournament not found");
        exit();
    }
    
    // Get registrations from the tournament_registrations table
    $fields = "tr.*, u.user_id, u.user_name, u.user_email";
    $user_columns_result = $conn->query("SHOW COLUMNS FROM users");
    if ($user_columns_result) {
        $user_columns = [];
        while ($column = $user_columns_result->fetch_assoc()) {
            $user_columns[] = $column['Field'];
        }
        
        if (in_array('phone_number', $user_columns)) {
            $fields .= ", u.phone_number";
        }
        if (in_array('is_member', $user_columns)) {
            $fields .= ", u.is_member";
        }
    }
    
    $query = "SELECT $fields FROM tournament_registrations tr
              LEFT JOIN users u ON tr.user_id = u.user_id
              WHERE tr.tournament_id = ?
              ORDER BY tr.registration_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Make sure we have user info even if JOIN didn't work
            if (empty($row['user_name']) && !empty($row['team_captain'])) {
                $row['user_name'] = $row['team_captain'];
            }
            $registrations[] = $row;
        }
    }
    $stmt->close();
    
    // Update the registered_teams count in the tournament details
    $tournament_details['registered_teams'] = count($registrations);
    
    // Update the database with the correct count if needed
    if ($tournament_details['registered_teams'] != count($registrations)) {
        $update_count = $conn->prepare("UPDATE tournaments SET registered_teams = ? WHERE id = ?");
        $reg_count = count($registrations);
        $update_count->bind_param("ii", $reg_count, $tournament_id);
        $update_count->execute();
        $update_count->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tournaments - Blackout Esports</title>
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
            height: 100%;
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
        
        .table {
            color: #fff;
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: rgba(220, 53, 69, 0.3);
            color: #fff;
            font-weight: 600;
            border-color: rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .table td img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
            border-radius: 10px;
        }
        
        .badge-open {
            background-color: #198754;
        }
        
        .badge-closed {
            background-color: #dc3545;
        }
        
        .action-btn {
            padding: 5px 10px;
            margin: 0;
            border-radius: 4px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
        }
        
        .action-btn i {
            margin-right: 5px;
        }
        
        .message-alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: rgba(25, 135, 84, 0.2);
            border-left: 4px solid #198754;
            color: #fff;
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
        
        .tournament-title {
            font-size: 1.5rem;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }
        
        .tournament-title i {
            margin-right: 10px;
            color: #dc3545;
        }
        
        .tournament-info {
            font-size: 0.9rem;
            color: #aaa;
            margin-top: 5px;
            display: flex;
            gap: 15px;
        }
        
        .tournament-info div {
            display: flex;
            align-items: center;
        }
        
        .tournament-info i {
            margin-right: 5px;
            opacity: 0.7;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #aaa;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.6;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #fff;
        }
        
        .empty-state p {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .registration-card {
            background-color: rgba(20, 20, 20, 0.7);
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        
        .registration-card:hover {
            background-color: rgba(40, 40, 40, 0.7);
        }
        
        .registration-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .registration-name {
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .registration-email {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .registration-date {
            color: #aaa;
            font-size: 0.85rem;
            text-align: right;
        }
        
        .registration-team {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .team-header {
            font-weight: 600;
            margin-bottom: 5px;
            color: #dc3545;
        }
        
        .admin-action-btns {
            margin-bottom: 30px;
        }
        
        .admin-action-btns .btn {
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .admin-action-btns .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .admin-action-btns .btn.active {
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.5);
        }
        
        .admin-action-btns .btn i {
            opacity: 0.8;
        }
        
        .user-icon {
            font-size: 1.5rem;
            color: #aaa;
            margin-right: 10px;
        }
        
        .member-badge {
            display: inline-block;
            background-color: #ffc107;
            color: #000;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .registration-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }
        
        .registration-email, .registration-phone {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .reg-date, .reg-time {
            text-align: right;
            color: #aaa;
            font-size: 0.85rem;
        }
        
        .payment-status {
            margin-top: 10px;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            text-align: center;
        }
        
        .payment-status.paid {
            background-color: rgba(25, 135, 84, 0.2);
            color: #198754;
        }
        
        .payment-status.unpaid {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .team-subheader {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #dc3545;
            opacity: 0.8;
        }
        
        .member-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .member-item {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .notes-content {
            font-size: 0.9rem;
            background-color: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
        
        .payment-details {
            font-size: 0.9rem;
            background-color: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
        
        .payment-ref {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-proof {
            margin-top: 10px;
        }
        
        .registration-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 12px;
        }
        
        @media (max-width: 768px) {
            .registration-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .action-btn {
                width: 100%;
            }
        }
        h2 {
            color: #dc3545;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            
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
                    

                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_logout.php">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="admin-section">
        <div class="container">
            <?php if (isset($_GET['msg'])): ?>
            <div class="message-alert animate__animated animate__fadeIn">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_GET['msg']; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['view_registrations'])): ?>
                <!-- Tournament Registration Details View -->
                <div class="tournament-header animate__animated animate__fadeIn">
                    <div>
                        <a href="manage_tournaments.php" class="btn btn-outline-light btn-back">
                            <i class="fas fa-arrow-left me-2"></i> Back to Tournaments
                        </a>
                        <h2 class="tournament-title">
                            <i class="fas fa-trophy"></i> <?php echo $tournament_details['name']; ?>
                        </h2>
                        <div class="tournament-info">
                            <div><i class="fas fa-gamepad"></i> <?php echo $tournament_details['game']; ?></div>
                            <div><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($tournament_details['date'])); ?></div>
                            <div><i class="fas fa-users"></i> <?php echo count($registrations); ?> of <?php echo $tournament_details['max_teams']; ?> Teams</div>
                        </div>
                    </div>
                    <div>
                        <a href="edit_tournament.php?id=<?php echo $tournament_details['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i> Edit Tournament
                        </a>
                        <a href="manage_tournaments.php?clear_all_registrations=<?php echo $tournament_details['id']; ?>" class="btn btn-danger ms-2" onclick="return confirm('WARNING: This will delete ALL registrations for this tournament. This cannot be undone. Continue?');">
                            <i class="fas fa-trash-alt me-2"></i> Clear All Registrations
                        </a>
                    </div>
                </div>
                
                <div class="admin-card animate__animated animate__fadeIn">
                    <h3 class="section-title">
                        <i class="fas fa-clipboard-list me-2"></i> Registered Teams
                    </h3>
                    
                    <?php if (count($registrations) > 0): ?>
                        <div class="row">
                            <?php foreach ($registrations as $reg): ?>
                            <div class="col-lg-6">
                                <div class="registration-card">
                                    <div class="registration-header">
                                        <div>
                                            <div class="registration-name">
                                                <i class="fas fa-user-circle user-icon"></i>
                                                <?php echo htmlspecialchars($reg['user_name']); ?>
                                                <?php if(isset($reg['is_member']) && $reg['is_member']): ?>
                                                <span class="member-badge"><i class="fas fa-star me-1"></i> Member</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="registration-details">
                                                <div class="registration-email"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($reg['user_email']); ?></div>
                                                <?php if(isset($reg['phone_number']) && !empty($reg['phone_number'])): ?>
                                                <div class="registration-phone"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($reg['phone_number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="registration-date">
                                            <div class="reg-date"><i class="far fa-calendar-alt me-1"></i> <?php echo date('M j, Y', strtotime($reg['registration_date'])); ?></div>
                                            <div class="reg-time"><i class="far fa-clock me-1"></i> <?php echo date('g:i A', strtotime($reg['registration_date'])); ?></div>
                                            <?php if(isset($reg['payment_status'])): ?>
                                            <div class="payment-status <?php echo ($reg['payment_status'] == 'paid') ? 'paid' : 'unpaid'; ?>">
                                                <i class="fas <?php echo ($reg['payment_status'] == 'paid') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-1"></i> 
                                                <?php echo ucfirst($reg['payment_status']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="registration-team">
                                        <div class="team-header"><i class="fas fa-users me-2"></i> Team Information</div>
                                        <div class="row mt-2">
                                            <div class="col-md-4 mb-2"><strong>Team Name:</strong> <?php echo htmlspecialchars($reg['team_name']); ?></div>
                                            <div class="col-md-4 mb-2"><strong>Captain:</strong> <?php echo htmlspecialchars($reg['team_captain']); ?></div>
                                            <div class="col-md-4 mb-2"><strong>Contact:</strong> <?php echo htmlspecialchars($reg['contact_number']); ?></div>
                                        </div>
                                        
                                        <?php if(isset($reg['team_members']) && !empty($reg['team_members'])): ?>
                                        <div class="mt-3">
                                            <div class="team-subheader"><i class="fas fa-user-friends me-2"></i> Team Members</div>
                                            <div class="member-list">
                                                <?php 
                                                $members = explode(',', $reg['team_members']);
                                                foreach($members as $member): 
                                                ?>
                                                <div class="member-item"><?php echo htmlspecialchars(trim($member)); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($reg['reference_number']) || isset($reg['payment_proof'])): ?>
                                        <div class="mt-3">
                                            <div class="team-subheader"><i class="fas fa-money-bill-wave me-2"></i> Payment Information</div>
                                            <div class="payment-details">
                                                <?php if(isset($reg['reference_number']) && !empty($reg['reference_number'])): ?>
                                                <div class="payment-ref mb-2">
                                                    <strong><i class="fas fa-receipt me-2"></i>GCash Reference:</strong> 
                                                    <?php echo htmlspecialchars($reg['reference_number']); ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if(isset($reg['payment_proof']) && !empty($reg['payment_proof'])): ?>
                                                <div class="payment-proof">
                                                    <strong><i class="fas fa-image me-2"></i>Payment Proof:</strong>
                                                    <div class="mt-2">
                                                        <a href="uploads/payment_proofs/<?php echo htmlspecialchars($reg['payment_proof']); ?>" 
                                                           class="btn btn-sm btn-info" target="_blank">
                                                            <i class="fas fa-eye me-1"></i> View Receipt
                                                        </a>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="registration-actions">
                                        <a href="edit_tournament_registration.php?tournament_id=<?php echo $tournament_details['id']; ?>&registration_id=<?php echo isset($reg['id']) ? $reg['id'] : 0; ?>&team=<?php echo urlencode($reg['team_name']); ?>" class="btn btn-sm btn-warning action-btn">
                                            <i class="fas fa-edit"></i> Edit Details
                                        </a>
                                        
                                        <?php if(isset($reg['payment_status']) && $reg['payment_status'] == 'paid'): ?>
                                        <button class="btn btn-sm btn-success action-btn toggle-payment" data-reg-id="<?php echo isset($reg['id']) ? $reg['id'] : 0; ?>" data-tournament-id="<?php echo $tournament_details['id']; ?>" data-team="<?php echo htmlspecialchars($reg['team_name']); ?>" data-status="paid">
                                            <i class="fas fa-times-circle me-1"></i> Mark as Unpaid
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-primary action-btn toggle-payment" data-reg-id="<?php echo isset($reg['id']) ? $reg['id'] : 0; ?>" data-tournament-id="<?php echo $tournament_details['id']; ?>" data-team="<?php echo htmlspecialchars($reg['team_name']); ?>" data-status="unpaid">
                                            <i class="fas fa-check-circle me-1"></i> Verify Payment
                                        </button>
                                        <?php endif; ?>
                                        
                                        <a href="manage_tournaments.php?direct_delete_reg=<?php echo isset($reg['id']) ? $reg['id'] : 0; ?>&tid=<?php echo $tournament_details['id']; ?>" class="btn btn-sm btn-danger action-btn" onclick="return confirm('Are you sure you want to delete this registration?');">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <h3>No Registrations Yet</h3>
                            <p>There are no teams registered for this tournament yet. Check back later or promote the tournament to attract participants.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Tournament List View -->
                <h2 class="section-title mb-4 animate__animated animate__fadeIn text-center"></i>Manage Tournaments</h2>
                
                <!-- Admin Navigation Buttons -->
                <div class="d-flex justify-content-center mb-4 animate__animated animate__fadeIn">
                    <div class="btn-group admin-action-btns" role="group" aria-label="Tournament Admin Actions">
                        <a href="add_tournament.php" class="btn btn-outline-light">
                            <i class="fas fa-plus-circle me-2"></i> Add Tournament
                        </a>
                        <a href="manage_tournaments.php" class="btn btn-danger active">
                            <i class="fas fa-list me-2"></i> Manage Tournaments
                        </a>
                    </div>
                </div>
                
                <div class="admin-card animate__animated animate__fadeIn">
                    <?php if (count($tournaments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Game</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Teams</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tournaments as $tournament): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $tournament['image']; ?>" alt="<?php echo $tournament['name']; ?>" onerror="this.src='https://via.placeholder.com/50x50?text=NO+IMAGE'">
                                    </td>
                                    <td><?php echo $tournament['name']; ?></td>
                                    <td><?php echo $tournament['game']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($tournament['date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $tournament['status'] === 'open' ? 'badge-open' : 'badge-closed'; ?>">
                                            <?php echo ucfirst($tournament['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $tournament['registered_teams']; ?>/<?php echo $tournament['max_teams']; ?></td>
                                    <td>
                                        <a href="manage_tournaments.php?view_registrations=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-info action-btn">
                                            <i class="fas fa-users"></i> Registrations
                                        </a>
                                        <a href="edit_tournament.php?id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-warning action-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="#" class="btn btn-sm btn-danger action-btn" onclick="confirmDelete(<?php echo $tournament['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <h3>No Tournaments Created</h3>
                        <p>You haven't created any tournaments yet. Click the "Add New Tournament" button to create your first tournament.</p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function confirmDelete(tournamentId) {
            if (confirm("Are you sure you want to delete this tournament? This will also delete all registrations for this tournament.")) {
                window.location.href = "manage_tournaments.php?delete_id=" + tournamentId;
            }
        }
        
        $(document).ready(function() {
            // Animation for table rows
            $('tbody tr').each(function(index) {
                $(this).addClass('animate__animated animate__fadeIn');
                $(this).css('animation-delay', (index * 0.05) + 's');
            });
            
            // Handle payment status toggle
            $('.toggle-payment').on('click', function() {
                var regId = $(this).data('reg-id');
                var tournamentId = $(this).data('tournament-id');
                var teamName = $(this).data('team');
                var currentStatus = $(this).data('status');
                var newStatus = currentStatus === 'paid' ? 'unpaid' : 'paid';
                var $button = $(this); // Store the button reference
                
                // Confirm before changing status
                if (confirm('Are you sure you want to mark ' + teamName + ' as ' + newStatus + '?')) {
                    // Show loading state
                    $button.html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');
                    $button.prop('disabled', true);
                    
                    // Send AJAX request to update payment status
                    $.ajax({
                        url: 'update_payment_status.php',
                        type: 'POST',
                        data: {
                            registration_id: regId,
                            tournament_id: tournamentId,
                            team_name: teamName,
                            status: newStatus
                        },
                        success: function(response) {
                            // Reload the page to show updated status
                            location.reload();
                        },
                        error: function() {
                            alert('Error updating payment status. Please try again.');
                            // Reset button
                            var icon = currentStatus === 'paid' ? 'fa-times-circle' : 'fa-check-circle';
                            var text = currentStatus === 'paid' ? 'Mark as Unpaid' : 'Mark as Paid';
                            $button.html('<i class="fas ' + icon + ' me-1"></i> ' + text);
                            $button.prop('disabled', false);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 