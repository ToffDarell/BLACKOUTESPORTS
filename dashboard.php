<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("location:login.php");
    exit();
}

// Include the advisory check
include('check_advisory.php');

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
$stmt = $conn->prepare("SELECT user_name, user_email, membership_status FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
    $membership_status = $row['membership_status'];
}
$stmt->close();

// Fetch user's active reservations
$reservations = [];
$res_query = "SELECT * FROM reservations 
              WHERE user_id = ? 
              AND (status = 'Pending' OR status = 'Confirmed' OR status = 'Refund Requested')
              ORDER BY reservation_date, start_time";
$res_stmt = $conn->prepare($res_query);
$res_stmt->bind_param('i', $user_id);
$res_stmt->execute();
$res_result = $res_stmt->get_result();
while ($res = $res_result->fetch_assoc()) {
    $reservations[] = $res;
}
$res_stmt->close();

// Check for messages from session (e.g., after cancellation or refund request)
$message = '';
$messageType = '';
if (isset($_SESSION['message']) && isset($_SESSION['messageType'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    // Clear the session variables
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="pricing.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="chatbot.css">
    <style>
        /* Profile Icon Styles */
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
        
        /* Reservation Card Styles */
        .reservation-card {
            background-color: #151515;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            border: 1px solid #2c2c2c;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .reservation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            border-color: rgba(220, 53, 69, 0.3);
        }
        
        .reservation-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #000000, #dc3545);
        }
        
        .reservation-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background-color:rgb(25, 107, 44);
            color: white;
        }
        
        .status-refund-requested {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .reservation-title {
            color: #ff6b6b;
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 700;
            display: inline-block;
            position: relative;
        }
        
        .reservation-title:after {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 40px;
            height: 3px;
            background: #ff6b6b;
        }
        
        .reservation-info {
            background-color: rgba(30, 30, 30, 0.6);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
        }
        
        .reservation-info p {
            margin-bottom: 12px;
            color: #ddd;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .reservation-info p:last-child {
            margin-bottom: 0;
        }
        
        .reservation-info strong {
            color: #fff;
            margin-right: 10px;
            min-width: 60px;
            display: inline-block;
        }
        
        .reservation-info i {
            margin-right: 10px;
            color: #dc3545;
            font-size: 1.2rem;
        }
        
        .cancel-button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .cancel-button:hover {
            background-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }
        
        .cancel-button i {
            font-size: 1rem;
            margin-right: 8px;
        }
        
        .no-reservations {
            text-align: center;
            padding: 40px 30px;
            background-color: #151515;
            border-radius: 10px;
            color: #aaa;
            border: 1px solid #2c2c2c;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .no-reservations i {
            color: #dc3545;
            opacity: 0.7;
        }
        
        .no-reservations h4 {
            color: #fff;
            margin: 15px 0;
            font-weight: 600;
        }
        
        .time-badge {
            display: inline-block;
            background-color: #212529;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .computer-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #212529, #343a40);
            border-radius: 50%;
            font-size: 1.5rem;
            color: #dc3545;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(220, 53, 69, 0.3);
        }
        
        .computer-icon i {
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.3));
        }
        
        .reservation-section-heading {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            display: inline-block;
            padding-bottom: 15px;
        }
        
        .reservation-section-heading:after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, #dc3545, transparent);
        }
        
        .reservation-section-title-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .countdown-timer {
            margin-top: 15px;
            background-color: rgba(20, 20, 20, 0.5);
            border-radius: 8px;
            padding: 12px 15px;
            border-left: 3px solid #dc3545;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .countdown-timer i {
            color: #ffc107;
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .countdown {
            font-weight: 700;
            background-color: #212529;
            padding: 8px 15px;
            border-radius: 5px;
            color: #ffc107;
            letter-spacing: 1px;
            font-size: 1.2rem;
            text-align: center;
        }
        
        .countdown.in-progress {
            color: #28a745 !important;
            font-weight: 700;
            animation: pulse-green 2s infinite;
        }
        
        .countdown.ended {
            color: #dc3545 !important;
        }
        
        .countdown.pending {
            color: #f8d7da;
            background-color: #dc3545;
            animation: none;
            font-weight: 600;
        }
        
        
        
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
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
                            <a class="nav-link active" href="dashboard.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="computers.php">Computers</a>
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

    <!-- Welcome Section -->
    <section class="pricing-section">
        <div class="container">
            <?php if (!empty($message)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-12">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>WELCOME TO BLACKOUT ESPORTS</h1>
                        <p>The Ultimate Gaming Experience</p>
                    </div>
                </div>
            </div>
            
            <!-- User Welcome Card -->
            <div class="row justify-content-center">
                <div class="col-lg-8 animate__animated animate__fadeIn">
                    <div class="pricing-card">
                        <h2 class="pricing-title">Welcome <?php echo $user_name; ?></h2>
                        <p>We're excited to have you at Blackout Esports. Ready to dominate the game?</p>
                        
                        <div class="info-badge mt-4">
                            <i class="fas fa-info-circle"></i>
                            Explore our services below and make the most of your time at Blackout Esports.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User's Reservations Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="reservation-section-title-container">
                        <h2 class="reservation-section-heading animate__animated animate__fadeIn">
                            <i class="fas fa-calendar-check me-3"></i>Your Reservations
                        </h2>
                    </div>
                </div>
                
                <?php if (!empty($reservations)): ?>
                    <?php foreach ($reservations as $reservation): 
                        // Calculate reservation time in timestamp format for comparison
                        $reservation_datetime = strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
                        $cutoff_time = $reservation_datetime - (60 * 60); // 1 hour before
                        $current_time = time();
                        
                        // Determine if cancellation is possible (before 1hr cutoff time)
                        $can_cancel = $current_time < $cutoff_time;
                        
                        // Format status for display
                        $status_class = '';
                        switch ($reservation['status']) {
                            case 'Pending':
                                $status_class = 'status-pending';
                                break;
                            case 'Confirmed':
                                $status_class = 'status-confirmed';
                                break;
                            case 'Refund Requested':
                                $status_class = 'status-refund-requested';
                                break;
                        }
                    ?>
                    <div class="col-lg-6 mb-4 animate__animated animate__fadeInUp">
                        <div class="reservation-card">
                            <span class="reservation-status <?php echo $status_class; ?>"><?php echo $reservation['status']; ?></span>
                            <div class="d-flex align-items-center mb-3">
                                <div class="computer-icon">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <h3 class="reservation-title mb-0 ms-3">Computer #<?php echo htmlspecialchars($reservation['computer_number']); ?></h3>
                            </div>
                            
                            <div class="reservation-info">
                                <p><i class="fas fa-calendar-day"></i><strong>Date:</strong> <?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?></p>
                                <p><i class="fas fa-clock"></i><strong>Time:</strong> 
                                    <span class="time-badge"><?php echo date('g:i A', strtotime($reservation['start_time'])); ?></span> 
                                    to 
                                    <span class="time-badge"><?php echo date('g:i A', strtotime($reservation['end_time'])); ?></span>
                                </p>
                                
                                <?php 
                                // Calculate the time remaining until the reservation
                                $now = new DateTime();
                                $reservationDateTime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
                                $reservationEndTime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['end_time']);
                                $interval = $now->diff($reservationDateTime);
                                
                                if ($reservation['status'] === 'Pending'): ?>
                                <p class="countdown-timer">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span class="countdown pending">
                                        Pending Confirmation
                                    </span>
                                </p>
                                <?php elseif ($now < $reservationDateTime): ?>
                                <p class="countdown-timer">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span class="countdown" 
                                          data-reservation-time="<?php echo $reservation['reservation_date'] . ' ' . $reservation['start_time']; ?>"
                                          data-end-time="<?php echo $reservation['reservation_date'] . ' ' . $reservation['end_time']; ?>">
                                        <?php
                                        $days = $interval->days;
                                        $hours = $interval->h;
                                        $minutes = $interval->i;
                                        
                                        if ($days > 0) {
                                            echo $days . 'd ' . $hours . 'h ' . $minutes . 'm';
                                        } else {
                                            echo $hours . 'h ' . $minutes . 'm';
                                        }
                                        ?>
                                    </span>
                                </p>
                                <?php elseif ($now <= $reservationEndTime && $reservation['status'] === 'Confirmed'): ?>
                                <p class="countdown-timer">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span class="countdown in-progress" 
                                          data-reservation-time="<?php echo $reservation['reservation_date'] . ' ' . $reservation['start_time']; ?>"
                                          data-end-time="<?php echo $reservation['reservation_date'] . ' ' . $reservation['end_time']; ?>"
                                          data-reservation-id="<?php echo $reservation['reservation_id']; ?>">
                                    </span>
                                </p>
                                <?php endif; ?>
                                <?php if ($reservation['status'] !== 'Refund Requested'): ?>
                                    <div class="mt-4">
                                        <?php if ($can_cancel): ?>
                                            <?php if ($reservation['status'] === 'Confirmed'): ?>
                                                <button type="button" class="cancel-button" 
                                                        onclick="showCancellationModal(<?php echo $reservation['reservation_id']; ?>, '<?php echo htmlspecialchars($reservation['computer_number']); ?>', '<?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?>', '<?php echo date('g:i A', strtotime($reservation['start_time'])); ?>', '<?php echo date('g:i A', strtotime($reservation['end_time'])); ?>', true)">
                                                    <i class="fas fa-times-circle"></i> Cancel Reservation
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="cancel-button" 
                                                        onclick="showCancellationModal(<?php echo $reservation['reservation_id']; ?>, '<?php echo htmlspecialchars($reservation['computer_number']); ?>', '<?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?>', '<?php echo date('g:i A', strtotime($reservation['start_time'])); ?>', '<?php echo date('g:i A', strtotime($reservation['end_time'])); ?>', false)">
                                                    <i class="fas fa-times-circle"></i> Cancel Reservation
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-info-circle me-2"></i> Cancellation is only available 1 hour before the scheduled time.</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 animate__animated animate__fadeIn">
                        <div class="no-reservations">
                            <i class="fas fa-calendar-times mb-3" style="font-size: 3rem;"></i>
                            <h4>No Active Reservations</h4>
                            <p>You don't have any active reservations. Ready to start gaming?</p>
                            <a href="computers.php" class="btn btn-danger mt-3">Make a Reservation</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Features -->
            <div class="row mt-5">
                <div class="col-lg-4 mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="pricing-card h-100">
                        <div class="text-center mb-4">
                            <i class="fas fa-desktop text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="pricing-title text-center">High-End Gaming PCs</h3>
                        <p>Experience gaming on our state-of-the-art computers with the latest hardware, designed for maximum performance and minimal latency.</p>
                        <div class="text-center mt-4">
                            <a href="computers.php" class="btn btn-outline-danger">
                                <i class="fas fa-desktop me-2"></i>Browse Computers
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4 animate__animated animate__fadeInUp animate__delay-2s">
                    <div class="pricing-card h-100">
                        <div class="text-center mb-4">
                            <i class="fas fa-trophy text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="pricing-title text-center">Weekly Tournaments</h3>
                        <p>Compete in our regular tournaments across various games with cash prizes and exclusive rewards for the champions.</p>
                        <div class="text-center mt-4">
                            <a href="tournaments.php" class="btn btn-outline-danger">
                                <i class="fas fa-trophy me-2"></i>View Tournaments
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4 animate__animated animate__fadeInUp animate__delay-3s">
                    <div class="pricing-card h-100">
                        <div class="text-center mb-4">
                            <i class="fas fa-users text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="pricing-title text-center">Gaming Community</h3>
                        <p>Join our thriving community of gamers, make new friends, form teams, and share your gaming experiences.</p>
                        <div class="text-center mt-4">
                            <a href="membership.php" class="btn btn-outline-danger">
                                <i class="fas fa-user-plus me-2"></i>Join Community
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="py-5 bg-dark">
        <div class="container">
            <div class="row animate__animated animate__fadeIn">
                <div class="col-md-3 col-6 text-center mb-4">
                    <div class="calculator" style="padding: 20px;">
                        <div class="text-danger" style="font-size: 3rem; font-weight: 700;">30+</div>
                        <div style="font-size: 1.2rem;">Gaming PCs</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 text-center mb-4">
                    <div class="calculator" style="padding: 20px;">
                        <div class="text-danger" style="font-size: 3rem; font-weight: 700;">50+</div>
                        <div style="font-size: 1.2rem;">Games</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 text-center mb-4">
                    <div class="calculator" style="padding: 20px;">
                        <div class="text-danger" style="font-size: 3rem; font-weight: 700;">1000+</div>
                        <div style="font-size: 1.2rem;">Members</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 text-center mb-4">
                    <div class="calculator" style="padding: 20px;">
                        <div class="text-danger" style="font-size: 3rem; font-weight: 700;">10+</div>
                        <div style="font-size: 1.2rem;">Tournaments/Month</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="pricing-section pt-0">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 animate__animated animate__fadeInUp">
                    <div class="calculator">
                        <h3 class="calculator-title"><i class="fas fa-gamepad me-2"></i>Ready to Play?</h3>
                        
                        <div class="text-center my-4">
                            <p>Don't miss out on the ultimate gaming experience. Reserve a computer or join a tournament today!</p>
                            
                            <div class="d-grid gap-3 d-md-flex justify-content-md-center mt-4">
                                <a href="computers.php" class="btn btn-danger btn-lg">
                                    <i class="fas fa-desktop me-2"></i>Reserve a Computer
                                </a>
                                <a href="tournaments.php" class="btn btn-outline-danger btn-lg">
                                    <i class="fas fa-trophy me-2"></i>Join a Tournament
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cancellation Modal -->
    <div class="modal fade" id="cancellationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background-color: #2a2a2a; color: #fff;">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Reservation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="normalCancellation">
                        <p>Are you sure you want to cancel this reservation?</p>
                        <div id="reservationDetails" class="mb-3" style="background-color: #333; padding: 10px; border-radius: 5px;">
                            <!-- Reservation details will be filled by JavaScript -->
                        </div>
                        
                        <form id="cancellationForm" action="cancel_reservation.php" method="post">
                            <input type="hidden" id="reservation_id" name="reservation_id">
                            
                            <div class="mb-3">
                                <label for="cancellation_reason" class="form-label">Reason for cancellation</label>
                                <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required
                                          style="background-color: #333; color: #fff; border-color: #444;"></textarea>
                            </div>
                        </form>
                    </div>
                    
                    <div id="paidCancellation" style="display: none;">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>This reservation has already been paid and confirmed.</strong>
                        </div>
                        <p>If you cancel now, you will need to request a refund separately.</p>
                        <p>Would you like to proceed with cancellation and request a refund?</p>
                        <div id="paidReservationDetails" class="mb-3" style="background-color: #333; padding: 10px; border-radius: 5px;">
                            <!-- Paid reservation details will be filled by JavaScript -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: #444;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Cancel Reservation</button>
                    <a href="#" class="btn btn-primary" id="refundBtn" style="display: none;">Request Refund</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Blackout Esports</h5>
                    <p class="mb-3">The Ultimate Gaming Experience</p>
                    <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Malaybalay Bukidnon</p>
                    <p class="mb-0"><i class="fas fa-phone me-2"></i>+63 912 345 6789</p>
                    <p class="mb-0"><i class="fas fa-envelope me-2"></i>info@blackoutesports.com</p>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Links</h5>
                    <ul class="list-unstyled mb-0">
                        <li><a href="dashboard.php" class="footer-link"><i class="fas fa-home me-2"></i>Home</a></li>
                        <li><a href="computers.php" class="footer-link"><i class="fas fa-desktop me-2"></i>Computers</a></li>
                        <li><a href="pricing.php" class="footer-link"><i class="fas fa-tags me-2"></i>Pricing</a></li>
                        <li><a href="tournaments.php" class="footer-link"><i class="fas fa-trophy me-2"></i>Tournaments</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Support</h5>
                    <ul class="list-unstyled mb-0">
                        <li><a href="#" class="footer-link"><i class="fas fa-question-circle me-2"></i>FAQs</a></li>
                        <li><a href="#" class="footer-link"><i class="fas fa-book me-2"></i>Terms of Service</a></li>
                        <li><a href="#" class="footer-link"><i class="fas fa-shield-alt me-2"></i>Privacy Policy</a></li>
                        <li><a href="#" class="footer-link"><i class="fas fa-headset me-2"></i>Contact Support</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Connect</h5>
                    <div class="social-icons">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-discord"></i></a>
                    </div>
                   
                </div>
            </div>
            
            <hr class="footer-divider">
            
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0">&copy; 2025 Blackout Esports. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle cancellation modal
        function showCancellationModal(reservationId, computerNumber, date, startTime, endTime, isPaid) {
            document.getElementById('reservation_id').value = reservationId;
            
            // Set the reservation details
            const details = `
                <p><strong>Computer:</strong> ${computerNumber}</p>
                <p><strong>Date:</strong> ${date}</p>
                <p><strong>Time:</strong> ${startTime} to ${endTime}</p>
            `;
            
            document.getElementById('reservationDetails').innerHTML = details;
            document.getElementById('paidReservationDetails').innerHTML = details;
            
            if (isPaid) {
                // Show paid cancellation UI
                document.getElementById('normalCancellation').style.display = 'none';
                document.getElementById('paidCancellation').style.display = 'block';
                document.getElementById('confirmCancel').style.display = 'none';
                document.getElementById('refundBtn').style.display = 'inline-block';
                document.getElementById('refundBtn').href = 'request_refund.php?reservation_id=' + reservationId;
            } else {
                // Show normal cancellation UI
                document.getElementById('normalCancellation').style.display = 'block';
                document.getElementById('paidCancellation').style.display = 'none';
                document.getElementById('confirmCancel').style.display = 'inline-block';
                document.getElementById('refundBtn').style.display = 'none';
            }
            
            // Show the modal
            const cancellationModal = new bootstrap.Modal(document.getElementById('cancellationModal'));
            cancellationModal.show();
        }
        
        // Add event listener for normal cancellation
        document.getElementById('confirmCancel').addEventListener('click', function() {
            document.getElementById('cancellationForm').submit();
        });
        
        
        
        // Update countdowns in real-time
        function updateCountdowns() {
            const countdownElements = document.querySelectorAll('.countdown:not(.pending)');
            
            countdownElements.forEach(function(element) {
                const reservationTime = new Date(element.getAttribute('data-reservation-time')).getTime();
                const reservationEndTime = new Date(element.getAttribute('data-end-time')).getTime();
                const now = new Date().getTime();
                const timeToStart = reservationTime - now;
                const timeToEnd = reservationEndTime - now;
                
                // If reservation hasn't started yet
                if (timeToStart > 0) {
                    // Calculate days, hours, minutes to start
                    const days = Math.floor(timeToStart / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeToStart % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeToStart % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeToStart % (1000 * 60)) / 1000);
                    
                    let displayText = '';
                    
                    if (days > 0) {
                        displayText = `${days}d ${hours}h ${minutes}m`;
                    } else if (hours > 0) {
                        displayText = `${hours}h ${minutes}m ${seconds}s`;
                    } else {
                        displayText = `${minutes}m ${seconds}s`;
                    }
                    
                    element.innerHTML = displayText;
                    element.style.color = '#ffc107'; // Yellow for upcoming
                    element.classList.remove('in-progress', 'ended');
                }
                // If reservation is in progress
                else if (timeToEnd > 0) {
                    // Calculate hours, minutes, seconds remaining
                    const hours = Math.floor(timeToEnd / (1000 * 60 * 60));
                    const minutes = Math.floor((timeToEnd % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeToEnd % (1000 * 60)) / 1000);
                    
                    let displayText = '';
                    
                    if (hours > 0) {
                        displayText = `${hours}h ${minutes}m ${seconds}s left`;
                    } else if (minutes > 0) {
                        displayText = `${minutes}m ${seconds}s left`;
                    } else {
                        displayText = `${seconds}s left`;
                    }
                    
                    element.innerHTML = displayText;
                    element.style.color = '#28a745'; // Green for in progress
                    element.classList.add('in-progress');
                    element.classList.remove('ended');
                } 
                // If reservation has ended
                else {
                    element.innerHTML = 'Ended';
                    element.style.color = '#dc3545'; // Red for ended
                    element.classList.add('ended');
                    element.classList.remove('in-progress');
                }
            });
        }
        
        // Update countdowns immediately and then every second
        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    </script>
</body>
</html>
