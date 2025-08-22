<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("location:login.php");
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

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.user_name, u.user_email, 
                        CASE WHEN m.membership_id IS NOT NULL AND u.membership_status = 'Member' 
                        THEN 1 ELSE 0 END as is_member 
                        FROM users u 
                        LEFT JOIN memberships m ON u.user_id = m.user_id 
                        WHERE u.user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
    $is_member = $row['is_member'];
} else {
    $is_member = 0;
}
$stmt->close();

// Fetch computers from the database - Updated to match admin's table structure
$computers = [];
$query = "SELECT computer_id, computer_number, status, specs FROM computers";
$result = $conn->query($query);

if ($result === false) {
    // Log the error and display a user-friendly message
    error_log("Database query failed: " . $conn->error);
    echo "<p class='text-center text-danger'>Error fetching computers. Please try again later.</p>";
} else {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $computers[] = $row;
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
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <title>Available Computers - Blackout Esports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="pricing.css">
    <link rel="stylesheet" href="computers.css">
    
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
        
        .computers-section {
            padding: 60px 0;
            background: #0c0c0c;
            position: relative;
            overflow: hidden;
        }
        
        .computers-section::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(rgba(220, 53, 69, 0.1), transparent 70%);
            top: -150px;
            right: -150px;
            border-radius: 50%;
        }
        
        .computers-section::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(rgba(220, 53, 69, 0.1), transparent 70%);
            bottom: -150px;
            left: -150px;
            border-radius: 50%;
        }
        
        .computer-card {
            background: #151515;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.4s ease;
            border: 1px solid #2c2c2c;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        
        .computer-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        .computer-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #000000, #dc3545);
        }
        
        .computer-icon {
            font-size: 48px;
            margin-bottom: 15px;
            text-align: center;
            color: #dc3545;
            transition: all 0.3s ease;
        }
        
        .computer-card:hover .computer-icon {
            transform: scale(1.1);
        }
        
        .card-header {
            background: transparent;
            border-bottom: none;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            padding-bottom: 15px;
        }
        
        .card-body {
            padding: 15px 0;
            text-align: center;
        }
        
        .badge {
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 50px;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .badge.bg-success {
            background: linear-gradient(45deg, #198754, #20c997) !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(45deg, #dc3545, #bb2d3b) !important;
        }
        
        .badge.bg-warning {
            background: linear-gradient(45deg, #ffc107, #ffca2c) !important;
        }
        
        .reserve-btn {
            background: #dc3545;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            margin-top: 15px;
            color: white;
        }
        
        .reserve-btn:hover {
            background: #bb2d3b;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .computers-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .computers-header h1 {
            color: #ffffff;
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .computers-header p {
            color: #aaa;
            font-size: 18px;
        }
        
        .no-computers {
            background: #151515;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            border: 1px solid #2c2c2c;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin: 30px auto;
            max-width: 600px;
        }
        
        .no-computers i {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .no-computers h4 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .no-computers p {
            color: #aaa;
        }
        
        .btn-secondary {
            background-color: #2c3034;
            border: none;
            color: white;
            transition: all 0.3s ease;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 15px;
            opacity: 0.8;
        }
        
        .specs-info {
            color: #aaa;
            margin: 10px 0;
            font-size: 15px;
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
                            <a class="nav-link" href="dashboard.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pricing.php">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="membership.php">Membership</a>
                        </li>
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

    <section class="computers-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="computers-header animate__animated animate__fadeIn">
                        <h1>AVAILABLE COMPUTERS</h1>
                       
                    </div>
                </div>
            </div>
            
            <?php if (empty($computers)): ?>
                <div class="no-computers animate__animated animate__fadeIn">
                    <i class="fas fa-desktop"></i>
                    <h4>No computers available at the moment</h4>
                    <p>Please check back later or contact staff for assistance.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($computers as $computer): ?>
                        <div class="col-md-4 mb-4">
                            <div class="computer-card animate__animated animate__fadeIn">
                                <div class="card-header">
                                    PC #<?= htmlspecialchars($computer['computer_number']) ?>
                                </div>
                                
                                <!-- PC Icon -->
                                <div class="computer-icon">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                
                                <div class="card-body">
                                    <?php if ($computer['status'] == 'available'): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php elseif ($computer['status'] == 'reserved'): ?>
                                        <span class="badge bg-danger">Reserved</span>
                                    <?php elseif ($computer['status'] == 'maintenance'): ?>
                                        <span class="badge bg-warning text-dark">Maintenance</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($computer['specs'])): ?>
                                        <p class="specs-info"><?= htmlspecialchars($computer['specs']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($computer['status'] == 'available'): ?>
                                        <a href="make_reservations.php?id=<?= $computer['computer_id'] ?>&number=<?= $computer['computer_number'] ?>" class="btn reserve-btn">
                                           </i>Reserve Now
                                        </a>
                                    <?php elseif ($computer['status'] == 'reserved'): ?>
                                        <button class="btn btn-secondary" disabled>Currently Reserved</button>
                                    <?php elseif ($computer['status'] == 'maintenance'): ?>
                                        <button class="btn btn-secondary" disabled>Under Maintenance</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Add animation to cards
            $('.computer-card').hover(
                function() { $(this).find('.computer-icon').addClass('animate__animated animate__pulse'); },
                function() { $(this).find('.computer-icon').removeClass('animate__animated animate__pulse'); }
            );
        });
    </script>
</body>
</html>