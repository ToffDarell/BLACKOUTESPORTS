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

// Check if tournament ID is provided
if (!isset($_GET['id'])) {
    header("location:manage_tournaments.php");
    exit();
}

$tournament_id = $_GET['id'];

// Get tournament details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("location:manage_tournaments.php?msg=Tournament not found");
    exit();
}

$tournament = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $game = $_POST['game'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $prize_pool = $_POST['prize_pool'];
    $entry_fee = $_POST['entry_fee'];
    $max_teams = $_POST['max_teams'];
    $status = $_POST['status'];
    $winner = isset($_POST['winner']) ? $_POST['winner'] : null;

    // Check if a new image was uploaded
    if ($_FILES["image"]["size"] > 0) {
        $upload_dir = "images/tournaments/";
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/website/' . $upload_dir;
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                die('Failed to create folders...');
            }
        }
        
        $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $file_name;
        $db_file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Delete old image if it exists and isn't a placeholder
            if (!empty($tournament['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/website/' . $tournament['image'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/website/' . $tournament['image']);
            }
            
            // Update with new image
            if ($status === 'closed' && !empty($winner)) {
                $stmt = $conn->prepare("UPDATE tournaments SET name = ?, game = ?, date = ?, time = ?, prize_pool = ?, entry_fee = ?, max_teams = ?, image = ?, status = ?, winner = ? WHERE id = ?");
                $stmt->bind_param("ssssssssssi", $name, $game, $date, $time, $prize_pool, $entry_fee, $max_teams, $db_file_path, $status, $winner, $tournament_id);
            } else {
                $stmt = $conn->prepare("UPDATE tournaments SET name = ?, game = ?, date = ?, time = ?, prize_pool = ?, entry_fee = ?, max_teams = ?, image = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssssssi", $name, $game, $date, $time, $prize_pool, $entry_fee, $max_teams, $db_file_path, $status, $tournament_id);
            }
        } else {
            die('Failed to upload file...');
        }
    } else {
        // No new image uploaded, update without changing image
        if ($status === 'closed' && !empty($winner)) {
            $stmt = $conn->prepare("UPDATE tournaments SET name = ?, game = ?, date = ?, time = ?, prize_pool = ?, entry_fee = ?, max_teams = ?, status = ?, winner = ? WHERE id = ?");
            $stmt->bind_param("sssssssssi", $name, $game, $date, $time, $prize_pool, $entry_fee, $max_teams, $status, $winner, $tournament_id);
        } else {
            $stmt = $conn->prepare("UPDATE tournaments SET name = ?, game = ?, date = ?, time = ?, prize_pool = ?, entry_fee = ?, max_teams = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssssssi", $name, $game, $date, $time, $prize_pool, $entry_fee, $max_teams, $status, $tournament_id);
        }
    }
    
    if ($stmt->execute()) {
        header("Location: manage_tournaments.php?msg=Tournament updated successfully");
        exit();
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tournament - Blackout Esports</title>
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
        .tournament-form {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.2);
            border: 1px solid rgba(255, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .tournament-form:hover {
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.3);
        }
        
        .form-section-title {
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 0, 0, 0.3);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.1rem;
            font-weight: 600;
            position: relative;
            display: inline-block;
            text-transform: uppercase;
        }
        
        .form-section-title:after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -1px;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ff0000, transparent);
        }
        
        .form-label {
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            background-color: rgba(10, 0, 0, 0.6);
            border: 1px solid rgba(255, 0, 0, 0.1);
            color: #fff;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: rgba(51, 0, 0, 0.7);
            border-color:#dc3545;
            box-shadow: 0 0 0 0.25rem rgba(255, 0, 0, 0.25);
            color: #fff;
        }
        
        .btn-primary {
            background: linear-gradient(45deg,#dc3545;, #cc0000);
            border: none;
            padding: 6px 15px;
            font-weight: 600;
            border-radius: 4px;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.3);
        }
        
        .btn-secondary {
            background-color: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
            padding: 6px 15px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.3);
        }
        
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 5px;
            border: 1px dashed rgba(255, 0, 0, 0.3);
            padding: 20px;
            text-align: center;
            background-color: rgba(10, 0, 0, 0.6);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-wrapper:hover {
            border-color:#dc3545;
            background-color: rgba(51, 0, 0, 0.7);
        }
        
        .section-title {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }
        
        .small-text {
            font-size: 0.8rem;
            color: #999;
            margin-top: 5px;
        }
        
        .tournament-section {
            padding: 40px 0;
            position: relative;
        }
        
        .file-upload-icon i {
            color:#dc3545;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .file-upload-text {
            color: #ffffff;
        }
        
        .current-image {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .current-image img {
            max-width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }
        
        .winner-section {
            display: none;
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
                            <a class="nav-link" href="admin_login.php">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="tournament-section">
        <div class="container">
            <h2 class="section-title mb-4 animate__animated animate__fadeIn">
                <i class="fas fa-edit me-2"></i>Edit Tournament
            </h2>
            
            <!-- Admin Navigation Buttons -->
            <div class="d-flex justify-content-center mb-4 animate__animated animate__fadeIn">
                <div class="btn-group admin-action-btns" role="group" aria-label="Tournament Admin Actions">
                    <a href="add_tournament.php" class="btn btn-outline-light">
                        <i class="fas fa-plus-circle me-2"></i> Add Tournament
                    </a>
                    <a href="manage_tournaments.php" class="btn btn-outline-light">
                        <i class="fas fa-list me-2"></i> Manage Tournaments
                    </a>
                    <a href="#" class="btn btn-danger active">
                        <i class="fas fa-edit me-2"></i> Edit Tournament
                    </a>
                </div>
            </div>
            
            <div class="tournament-form animate__animated animate__fadeInUp">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-section-title"><i class="fas fa-info-circle me-2"></i>Tournament Details</div>
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-font me-2"></i>Tournament Name</label>
                        <input type="text" class="form-control" name="name" value="<?php echo $tournament['name']; ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-gamepad me-2"></i>Game</label>
                        <select class="form-select" name="game" required>
                            <option value="" disabled>Select a game</option>
                            <option value="Valorant" <?php echo ($tournament['game'] == 'Valorant') ? 'selected' : ''; ?>>Valorant</option>
                            <option value="Dota 2" <?php echo ($tournament['game'] == 'Dota 2') ? 'selected' : ''; ?>>Dota 2</option>
                            <option value="CS:GO 2" <?php echo ($tournament['game'] == 'CS:GO 2' || $tournament['game'] == 'Counter-Strike') ? 'selected' : ''; ?>>CS:GO 2</option>
                            <option value="Warzone" <?php echo ($tournament['game'] == 'Warzone') ? 'selected' : ''; ?>>Warzone</option>
                            <option value="League of Legends" <?php echo ($tournament['game'] == 'League of Legends') ? 'selected' : ''; ?>>League of Legends</option>
                        </select>
                    </div>

                    <div class="form-section-title mt-5"><i class="fas fa-calendar-alt me-2"></i>Schedule</div>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label"><i class="fas fa-calendar-day me-2"></i>Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo $tournament['date']; ?>" required>
                            <div class="small-text">When will the tournament take place?</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label"><i class="fas fa-clock me-2"></i>Time</label>
                            <input type="time" class="form-control" name="time" value="<?php echo $tournament['time']; ?>" required>
                            <div class="small-text">Start time of the tournament</div>
                        </div>
                    </div>

                    <div class="form-section-title mt-4"><i class="fas fa-dollar-sign me-2"></i>Prize & Entry</div>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label"><i class="fas fa-trophy me-2"></i>Prize Pool</label>
                            <input type="text" class="form-control" name="prize_pool" value="<?php echo $tournament['prize_pool']; ?>" required>
                            <div class="small-text">Total prize money for winners</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label"><i class="fas fa-ticket-alt me-2"></i>Entry Fee</label>
                            <input type="text" class="form-control" name="entry_fee" value="<?php echo $tournament['entry_fee']; ?>" required>
                            <div class="small-text">Fee to participate in the tournament</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-users me-2"></i>Maximum Teams</label>
                        <input type="number" class="form-control" name="max_teams" value="<?php echo $tournament['max_teams']; ?>" min="2" required>
                        <div class="small-text">Maximum number of teams that can participate</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-flag me-2"></i>Status</label>
                        <select class="form-select" name="status" id="statusSelect" required>
                            <option value="open" <?php echo ($tournament['status'] == 'open') ? 'selected' : ''; ?>>Open</option>
                            <option value="closed" <?php echo ($tournament['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                        </select>
                        <div class="small-text">Current status of the tournament</div>
                    </div>
                    
                    <div class="mb-4 winner-section" id="winnerSection">
                        <label class="form-label"><i class="fas fa-crown me-2"></i>Winner Team</label>
                        <input type="text" class="form-control" name="winner" value="<?php echo isset($tournament['winner']) ? $tournament['winner'] : ''; ?>">
                        <div class="small-text">Enter the name of the winning team</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label mb-2"><i class="fas fa-image me-2"></i>Tournament Image</label>
                        
                        <div class="current-image">
                            <p>Current Image:</p>
                            <img src="<?php echo $tournament['image']; ?>" alt="<?php echo $tournament['name']; ?>" onerror="this.src='https://via.placeholder.com/300x150?text=NO+IMAGE'">
                        </div>
                        
                        <div class="file-upload-wrapper">
                            <div class="file-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="file-upload-text">Change image (optional)</div>
                            <div class="small-text">Recommended size: 1200 x 600 pixels</div>
                            <input type="file" class="file-input" name="image" accept="image/*">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-5">
                        <a href="manage_tournaments.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Add hover animation to form sections
            $('.form-section-title').hover(
                function() { $(this).addClass('animate__animated animate__pulse'); },
                function() { $(this).removeClass('animate__animated animate__pulse'); }
            );
            
            // Image upload preview
            $('input[type="file"]').change(function(e) {
                if (e.target.files.length > 0) {
                    const fileName = e.target.files[0].name;
                    $('.file-upload-text').text(fileName);
                    $('.file-upload-wrapper').css('border-style', 'solid');
                }
            });
            
            // Show/hide winner section based on status
            function toggleWinnerSection() {
                if ($('#statusSelect').val() === 'closed') {
                    $('#winnerSection').show();
                } else {
                    $('#winnerSection').hide();
                }
            }
            
            // Initialize winner section
            toggleWinnerSection();
            
            // Status change event
            $('#statusSelect').change(toggleWinnerSection);
        });
    </script>
</body>
</html> 