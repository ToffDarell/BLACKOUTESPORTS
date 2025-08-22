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
$stmt = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_email);
$stmt->fetch();
$stmt->close();

// Define specific games list
$games = [
    ['name' => 'Valorant'],
    ['name' => 'Dota 2'],
    ['name' => 'CS:GO 2'],
    ['name' => 'Warzone'],
    ['name' => 'League of Legends']
];

// Get selected game
$selected_game = isset($_GET['game']) ? $_GET['game'] : '';

// Get upcoming tournaments from database
$upcoming_tournaments = [];
$current_date = date('Y-m-d');

$query = "SELECT * FROM tournaments WHERE status='open' AND date >= '$current_date'";
if (!empty($selected_game)) {
    $query .= " AND game = '$selected_game'";
}
$query .= " ORDER BY date ASC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $upcoming_tournaments[] = $row;
    }
}

// Get past tournaments from database
$past_tournaments = [];
$query = "SELECT * FROM tournaments WHERE status='open' AND date < '$current_date'";
if (!empty($selected_game)) {
    $query .= " AND game = '$selected_game'";
}
$query .= " ORDER BY date DESC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Update status to closed for past tournaments
        $tournament_id = $row['id'];
        $conn->query("UPDATE tournaments SET status='closed' WHERE id = $tournament_id");
        $past_tournaments[] = $row;
    }
}

// Also get already closed tournaments
$query = "SELECT * FROM tournaments WHERE status='closed'";
if (!empty($selected_game)) {
    $query .= " AND game = '$selected_game'";
}
$query .= " ORDER BY date DESC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $past_tournaments[] = $row;
    }
}

$registered_tournaments = [];
$result = $conn->query("SELECT tournament_id FROM tournament_registrations WHERE user_id = $user_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $registered_tournaments[] = $row['tournament_id'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments - Blackout Esports</title>
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

        .game-dropdown-container {
            background-color: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .game-dropdown {
            height: 50px;
            padding: 0 20px;
            font-size: 1rem;
            color: #fff;
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        
        .game-dropdown:focus {
            outline: none;
            border-color: #dc3545;
        }
        
        .game-dropdown option {
            background-color: #2a2a2a;
            color: #fff;
            padding: 10px;
        }
        
        .game-icon {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .filter-btn {
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .tournament-section {
            display: none;
        }
        
        .tournament-section.active {
            display: block;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .section-title .badge {
            background-color: #dc3545;
            padding: 8px 15px;
            font-size: 0.9rem;
            border-radius: 20px;
        }
        
        .game-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }
        
        .game-card {
            background-color: #2a2a2a;
            border-radius: 10px;
            padding: 15px;
            width: 150px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .game-card:hover {
            background-color: #3a3a3a;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
            border-color: #dc3545;
        }
        
        .game-card.active {
            background-color: #3a3a3a;
            border-color: #dc3545;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.5);
        }
        
        .game-card-icon {
            font-size: 2rem;
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .game-card-title {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .tournament-card {
            background-color: #1a1a1a;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 0, 0, 0.1);
            height: 100%;
            position: relative;
        }
        
        .tournament-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.4);
            border-color: rgba(220, 53, 69, 0.5);
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

    <!-- Tournament Section -->
    <section class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>ESPORTS TOURNAMENTS</h1>
                        <?php if (isset($_SESSION['is_member'])): ?>
                        <p>Your current status: 
                            <span class="membership-badge <?php echo $_SESSION['is_member'] ? 'member' : 'non-member'; ?>">
                                <?php echo $_SESSION['is_member'] ? 'Member' : 'Non-Member'; ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="text-end mb-4 animate__animated animate__fadeIn">
                <a href="add_tournament.php" class="btn btn-danger">
                    <i class="fas fa-plus"></i> Add Tournament
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Game Selection Cards -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="game-dropdown-container animate__animated animate__fadeIn">
                        <h2 class="pricing-title mb-4">SELECT A GAME</h2>
                        <form id="gameSelectForm" action="tournaments.php" method="GET" class="row g-3 align-items-end">
                            <div class="col-lg-9 col-md-8">
                                <select name="game" id="gameDropdown" class="game-dropdown" onchange="this.form.submit()">
                                    <option value="">-- All Games --</option>
                                    <?php foreach ($games as $game): ?>
                                    <option value="<?php echo $game['name']; ?>" <?php echo ($selected_game == $game['name']) ? 'selected' : ''; ?>>
                                        <?php echo $game['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <button type="submit" class="btn btn-danger w-100 filter-btn">
                                    <i class="fas fa-filter me-2"></i> Filter Tournaments
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Tournaments -->
            <div class="row mt-4 tournament-section <?php echo (!empty($selected_game) || empty($selected_game)) ? 'active' : ''; ?>">
                <div class="col-12">
                    <div class="pricing-card animate__animated animate__fadeIn">
                        <div class="section-title">
                            <h2 class="pricing-title">UPCOMING TOURNAMENTS</h2>
                            <?php if (!empty($selected_game)): ?>
                            <span class="badge"><?php echo $selected_game; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($upcoming_tournaments as $index => $tournament): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="tournament-card animate__animated animate__fadeIn" <?php echo 0.1 * $index; ?>s">
                                    <div class="tournament-status-ribbon">OPEN</div>
                                    <div class="tournament-image" style="background-image: url('<?php echo $tournament['image']; ?>')">
                                        <span class="tournament-game-badge"><?php echo $tournament['game']; ?></span>
                                    </div>
                                    <div class="tournament-details">
                                        <h5 class="tournament-title"><?php echo $tournament['name']; ?></h5>
                                        <div class="tournament-info">
                                            <i class="far fa-calendar-alt"></i>
                                            <span><?php echo date('F j, Y', strtotime($tournament['date'])); ?></span>
                                        </div>
                                        <div class="tournament-info">
                                            <i class="far fa-clock"></i>
                                            <span><?php echo $tournament['time']; ?></span>
                                        </div>
                                        <div class="tournament-info">
                                            <i class="fas fa-coins"></i>
                                            <span>Prize Pool: <?php echo $tournament['prize_pool']; ?></span>
                                        </div>
                                        <div class="tournament-info">
                                            <i class="fas fa-ticket-alt"></i>
                                            <span>Entry Fee: <?php echo $tournament['entry_fee']; ?></span>
                                        </div>
                                        <div class="tournament-progress">
                                            <div class="progress">
                                                <div class="progress-bar bg-danger" role="progressbar" 
                                                    style="width: <?php echo ($tournament['registered_teams'] / $tournament['max_teams']) * 100; ?>%" 
                                                    aria-valuenow="<?php echo $tournament['registered_teams']; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="<?php echo $tournament['max_teams']; ?>">
                                                </div>
                                            </div>
                                            <div class="tournament-progress-text">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo $tournament['registered_teams']; ?> of <?php echo $tournament['max_teams']; ?> teams registered</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-3">
                                            <a href="tournament_details.php?id=<?php echo $tournament['id']; ?>" class="btn btn-outline-danger">
                                                <i class="fas fa-info-circle"></i> Details
                                            </a>
                                            <?php if (!in_array($tournament['id'], $registered_tournaments)): ?>
                                            <a href="tournament_registration.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-danger">
                                                <i class="fas fa-sign-in-alt"></i> Register Now
                                            </a>
                                            <?php else: ?>
                                            <button class="btn btn-success" disabled>
                                                <i class="fas fa-check-circle"></i> Registered
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($upcoming_tournaments)): ?>
                        <div class="info-badge animate__animated animate__fadeIn">
                            <i class="fas fa-info-circle"></i>
                            No upcoming tournaments 
                            <?php if (!empty($selected_game)): ?>
                                for <?php echo $selected_game; ?>
                            <?php endif; ?> 
                            at the moment. Check back later for new tournament announcements!
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Past Tournaments -->
            <div class="row mt-5 tournament-section <?php echo (!empty($selected_game) || empty($selected_game)) ? 'active' : ''; ?>">
                <div class="col-12">
                    <div class="pricing-card animate__animated animate__fadeIn">
                        <div class="section-title">
                            <h2 class="pricing-title">PAST TOURNAMENTS</h2>
                            <?php if (!empty($selected_game)): ?>
                            <span class="badge"><?php echo $selected_game; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($past_tournaments as $index => $tournament): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="tournament-card animate__animated animate__fadeIn" style="animation-delay: <?php echo 0.1 * $index; ?>s">
                                    <div class="tournament-image" style="background-image: url('<?php echo $tournament['image']; ?>')">
                                        <span class="tournament-game-badge"><?php echo $tournament['game']; ?></span>
                                    </div>
                                    <div class="tournament-details">
                                        <h5 class="tournament-title"><?php echo $tournament['name']; ?></h5>
                                        <div class="winner-team mb-3">
                                            <i class="fas fa-crown"></i> 
                                            <?php echo isset($tournament['winner']) ? $tournament['winner'] : 'Winner TBD'; ?>
                                        </div>
                                        <div class="tournament-info">
                                            <i class="far fa-calendar-alt"></i>
                                            <span><?php echo date('F j, Y', strtotime($tournament['date'])); ?></span>
                                        </div>
                                        <div class="tournament-info">
                                            <i class="fas fa-coins"></i>
                                            <span>Prize: <?php echo $tournament['prize_pool']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($past_tournaments)): ?>
                        <div class="info-badge animate__animated animate__fadeIn">
                            <i class="fas fa-info-circle"></i>
                            No past tournaments
                            <?php if (!empty($selected_game)): ?>
                                for <?php echo $selected_game; ?>
                            <?php endif; ?>
                            recorded yet. Stay tuned for our first tournament!
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto-submit form when dropdown changes
            $('#gameDropdown').change(function() {
                $('#gameSelectForm').submit();
            });
            
            // Add pulse animation on hover to tournament cards
            $('.tournament-card').hover(
                function() { 
                    $(this).find('.tournament-game-badge').addClass('animate__animated animate__pulse');
                    $(this).find('.tournament-title').addClass('animate__animated animate__pulse'); 
                },
                function() { 
                    $(this).find('.tournament-game-badge').removeClass('animate__animated animate__pulse');
                    $(this).find('.tournament-title').removeClass('animate__animated animate__pulse'); 
                }
            );
        });
    </script>
</body>

</html>
