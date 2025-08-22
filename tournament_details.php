<?php
session_start();
if (!isset($_GET['id'])) {
    header("Location: tournaments.php");
    exit();
}

$tournament_id = $_GET['id'];

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

// Get tournament details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: tournaments.php");
    exit();
}

$tournament = $result->fetch_assoc();

// Check if user is already registered
$user_registered = false;
$registration_details = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_reg = $conn->prepare("SELECT * FROM tournament_registrations WHERE tournament_id = ? AND user_id = ?");
    $check_reg->bind_param("ii", $tournament_id, $user_id);
    $check_reg->execute();
    $reg_result = $check_reg->get_result();
    
    if ($reg_result->num_rows > 0) {
        $user_registered = true;
        $registration_details = $reg_result->fetch_assoc();
    }
}

// Calculate tournament status
$current_date = date('Y-m-d');
$tournament_date = date('Y-m-d', strtotime($tournament['date']));
$registration_closed = ($tournament['status'] === 'closed' || $tournament['registered_teams'] >= $tournament['max_teams'] || $current_date > $tournament_date);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tournament['name']; ?> - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="tournaments.css">
    <link rel="stylesheet" href="pricing.css">
    <link rel="stylesheet" href="admin_nav.css">

    <style>
        .tournament-detail-banner {
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            height: 300px;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .tournament-detail-banner::before {
            content: '';
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0.1) 100%);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .tournament-detail-banner-content {
            position: absolute;
            bottom: 30px;
            left: 30px;
            color: white;
            max-width: 80%;
        }
        
        .tournament-detail-banner-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .tournament-detail-banner-content p {
            font-size: 1.2rem;
            margin-bottom: 0;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7);
        }
        
        .tournament-info-box {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 0, 0, 0.2);
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .tournament-info-box:hover {
            box-shadow: 0 0 25px rgba(255, 0, 0, 0.2);
        }
        
        .tournament-info-box h3 {
            color: #dc3545;
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .tournament-info-box h3 i {
            margin-right: 10px;
        }
        
        .tournament-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            flex: 1;
            min-width: 120px;
            background-color: rgba(0, 0, 0, 0.4);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            background-color: rgba(220, 53, 69, 0.2);
        }
        
        .stat-item .stat-icon {
            font-size: 2rem;
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .stat-item .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
        }
        
        .stat-item .stat-label {
            font-size: 0.8rem;
            color: #999;
            text-transform: uppercase;
        }
        
        .team-list {
            margin-top: 20px;
        }
        
        .team-item {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .team-item:hover {
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .team-icon {
            width: 40px;
            height: 40px;
            background-color: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin-right: 15px;
        }
        
        .team-details {
            flex: 1;
        }
        
        .team-name {
            font-weight: 600;
            font-size: 1rem;
            color: white;
            margin-bottom: 5px;
        }
        
        .team-captain {
            font-size: 0.85rem;
            color: #999;
        }
        
        .rules-list {
            list-style-type: none;
            padding-left: 0;
        }
        
        .rules-list li {
            padding: 8px 0;
            position: relative;
            padding-left: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .rules-list li:last-child {
            border-bottom: none;
        }
        
        .rules-list li::before {
            content: "\f054";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 11px;
            font-size: 0.7rem;
            color: #dc3545;
        }
        
        .prize-badge {
            display: inline-block;
            padding: 8px 15px;
            background-color: #ffc107;
            color: #000;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .prize-badge i {
            margin-right: 5px;
        }
        
        .user-action-panel {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .user-action-panel .btn {
            flex: 1;
            min-width: 200px;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 30px;
        }
        
        .registration-card {
            background-color: rgba(25, 135, 84, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }
        
        .registration-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .registration-status {
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-paid {
            background-color: rgba(25, 135, 84, 0.3);
            color: #198754;
        }
        
        .status-unpaid {
            background-color: rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        .registration-details-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .registration-detail-item {
            flex: 1;
            min-width: 200px;
        }
        
        .registration-detail-item .label {
            font-size: 0.8rem;
            color: #999;
            margin-bottom: 5px;
        }
        
        .registration-detail-item .value {
            font-size: 1rem;
            color: white;
        }
        
        .team-members-list {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .team-members-list .title {
            font-size: 0.9rem;
            color: #dc3545;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .members-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .member-tag {
            background-color: rgba(0, 0, 0, 0.5);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #ddd;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'; ?>">
                    <img src="images/blackout.jpg" alt="Blackout Esports" onerror="this.src='https://via.placeholder.com/180x50?text=BLACKOUT+ESPORTS'">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
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
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Log Out</a>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pricing.php">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="pricing-section">
        <div class="container">
            <!-- Tournament Banner -->
            <div class="tournament-detail-banner animate__animated animate__fadeIn" style="background-image: url('<?php echo $tournament['image']; ?>');">
                <div class="tournament-detail-banner-content">
                    <h1><?php echo $tournament['name']; ?></h1>
                    <p><?php echo $tournament['game']; ?></p>
                </div>
            </div>
            
            <div class="row">
                <!-- Tournament Details Column -->
                <div class="col-lg-8 animate__animated animate__fadeInLeft">
                    <!-- Tournament Information -->
                    <div class="tournament-info-box">
                        <h3><i class="fas fa-info-circle"></i> Tournament Information</h3>
                        <div class="tournament-stats">
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                                <div class="stat-value"><?php echo date('M j, Y', strtotime($tournament['date'])); ?></div>
                                <div class="stat-label">Date</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                                <div class="stat-value"><?php echo date('g:i A', strtotime($tournament['time'])); ?></div>
                                <div class="stat-label">Time</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fas fa-users"></i></div>
                                <div class="stat-value"><?php echo $tournament['registered_teams']; ?> / <?php echo $tournament['max_teams']; ?></div>
                                <div class="stat-label">Teams</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="stat-value">₱<?php echo number_format($tournament['entry_fee']); ?></div>
                                <div class="stat-label">Entry Fee</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tournament Rules -->
                    <div class="tournament-info-box">
                        <h3><i class="fas fa-gavel"></i> Tournament Rules</h3>
                        <ul class="rules-list">
                            <li>Teams must arrive 30 minutes before the scheduled start time</li>
                            <li>Match formats will be Best of 3 (BO3) for preliminaries and Best of 5 (BO5) for finals</li>
                            <li>Team members must bring their own peripherals (headset, keyboard, mouse)</li>
                            <li>Teams must follow the specific game rules provided by tournament organizers</li>
                            <li>Tournament officials' decisions are final</li>
                            <li>Unsportsmanlike conduct will result in team disqualification</li>
                            <li>Schedule changes may occur and will be communicated to all registered teams</li>
                            <li>Participants must abide by venue rules and regulations</li>
                        </ul>
                    </div>
                    
                    <!-- Prize Information -->
                    <div class="tournament-info-box">
                        <h3><i class="fas fa-trophy"></i> Prize Pool</h3>
                        <?php $clean_prize_pool = floatval(str_replace(['$', '₱', ','], '', $tournament['prize_pool'])); ?>
                        <div class="prize-badge"><i class="fas fa-medal"></i> 1st Place: ₱<?php echo number_format($clean_prize_pool * 0.6); ?></div>
                        <div class="prize-badge"><i class="fas fa-medal"></i> 2nd Place: ₱<?php echo number_format($clean_prize_pool * 0.3); ?></div>
                        <div class="prize-badge"><i class="fas fa-medal"></i> 3rd Place: ₱<?php echo number_format($clean_prize_pool * 0.1); ?></div>
                        
                        <div class="mt-3">
                            <p><strong>Total Prize Pool:</strong> ₱<?php echo number_format($clean_prize_pool); ?></p>
                            <p class="small text-muted">* Prize distribution may be subject to changes based on the number of participating teams</p>
                        </div>
                    </div>
                </div>
                
                <!-- Registration Column -->
                <div class="col-lg-4 animate__animated animate__fadeInRight">
                    <div class="tournament-info-box">
                        <h3><i class="fas fa-clipboard-list"></i> Registration Status</h3>
                        
                        <?php if ($registration_closed): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i> Registration Closed
                            <?php if ($tournament['registered_teams'] >= $tournament['max_teams']): ?>
                            <div class="small mt-1">Maximum number of teams reached</div>
                            <?php elseif ($current_date > $tournament_date): ?>
                            <div class="small mt-1">Tournament date has passed</div>
                            <?php else: ?>
                            <div class="small mt-1">Registration has been closed by organizers</div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> Registration Open
                            <div class="small mt-1">Be quick to secure your spot!</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="tournament-stats mt-4">
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fas fa-users"></i></div>
                                <div class="stat-value"><?php echo $tournament['registered_teams']; ?> / <?php echo $tournament['max_teams']; ?></div>
                                <div class="stat-label">Teams</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                                <?php 
                                $days_left = ceil((strtotime($tournament_date) - strtotime($current_date)) / (60 * 60 * 24));
                                $days_text = $days_left > 0 ? "$days_left Days" : "Today";
                                if ($days_left < 0) $days_text = "Ended";
                                ?>
                                <div class="stat-value"><?php echo $days_text; ?></div>
                                <div class="stat-label"><?php echo $days_left >= 0 ? "Remaining" : "Ago"; ?></div>
                            </div>
                        </div>
                        
                        <div class="user-action-panel">
                            <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php?redirect=tournament_details.php?id=<?php echo $tournament_id; ?>" class="btn btn-danger">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Register
                            </a>
                            <?php elseif ($user_registered): ?>
                            <button class="btn btn-success" disabled>
                                <i class="fas fa-check-circle me-2"></i> Already Registered
                            </button>
                            <?php elseif (!$registration_closed): ?>
                            <a href="tournament_registration.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-danger">
                                <i class="fas fa-clipboard-check me-2"></i> Register Now
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-times-circle me-2"></i> Registration Closed
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($user_registered): ?>
                    <!-- Show Registration Details -->
                    <div class="registration-card">
                        <div class="registration-card-header">
                            <h4><i class="fas fa-clipboard-check me-2"></i> Your Registration</h4>
                            <span class="registration-status <?php echo $registration_details['payment_status'] == 'paid' ? 'status-paid' : 'status-unpaid'; ?>">
                                <i class="fas <?php echo $registration_details['payment_status'] == 'paid' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-1"></i>
                                <?php echo ucfirst($registration_details['payment_status']); ?>
                            </span>
                        </div>
                        
                        <div class="registration-details-row">
                            <div class="registration-detail-item">
                                <div class="label">Team Name</div>
                                <div class="value"><?php echo htmlspecialchars($registration_details['team_name']); ?></div>
                            </div>
                            <div class="registration-detail-item">
                                <div class="label">Captain</div>
                                <div class="value"><?php echo htmlspecialchars($registration_details['team_captain']); ?></div>
                            </div>
                        </div>
                        
                        <div class="registration-details-row">
                            <div class="registration-detail-item">
                                <div class="label">Contact</div>
                                <div class="value"><?php echo htmlspecialchars($registration_details['contact_number']); ?></div>
                            </div>
                            <div class="registration-detail-item">
                                <div class="label">Registered On</div>
                                <div class="value"><?php echo date('M j, Y', strtotime($registration_details['registration_date'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($registration_details['team_members']): ?>
                        <div class="team-members-list">
                            <div class="title"><i class="fas fa-users me-2"></i> Team Members</div>
                            <div class="members-container">
                                <?php 
                                $members = explode(',', $registration_details['team_members']);
                                foreach($members as $member): 
                                ?>
                                <div class="member-tag"><?php echo htmlspecialchars(trim($member)); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($registration_details['payment_status'] == 'unpaid'): ?>
                        <div class="mt-3">
                            <a href="payment_confirmation.php?registration_id=<?php echo $registration_details['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-money-bill-wave me-1"></i> Submit Payment Proof
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
