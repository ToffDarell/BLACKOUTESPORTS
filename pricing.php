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
$stmt = $conn->prepare("
    SELECT u.user_name, u.user_email, 
    CASE WHEN m.membership_id IS NOT NULL AND u.membership_status = 'Member' 
    THEN 1 ELSE 0 END as is_member 
    FROM users u 
    LEFT JOIN memberships m ON u.user_id = m.user_id 
    WHERE u.user_id = ?
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param('i', $user_id);
if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
    $is_member = $row['is_member'];
} else {
    die("Error: User not found");
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
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
                            <a class="nav-link active" href="pricing.php">Pricing</a>
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

    <!-- Pricing Section -->
    <section class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>COMPUTER RESERVATION PRICING</h1>
                        <p>Your current status: 
                            <span class="membership-badge <?php echo $is_member ? 'member' : 'non-member'; ?>">
                                <?php echo $is_member ? 'Member' : 'Non-Member'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto animate__animated animate__fadeIn">
                    <div class="pricing-card">
                        <h2 class="pricing-title text-center mb-4">Hourly Rates</h2>
                        
                        <table class="pricing-table">
                            <thead>
                                <tr>
                                    <th>Hours Reserved</th>
                                    <th>Non-Member Price</th>
                                    <th>Member Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-clock me-2"></i>1 Hour</td>
                                    <td>₱30</td>
                                    <td>₱25</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-clock me-2"></i>2 Hours</td>
                                    <td>₱60</td>
                                    <td>₱50</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-clock me-2"></i>3 Hours</td>
                                    <td>₱90</td>
                                    <td>₱75</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-clock me-2"></i>4 Hours</td>
                                    <td>₱130</td>
                                    <td>₱100</td>
                                </tr>
                                <tr class="highlight">
                                    <td><i class="fas fa-clock me-2"></i>5 Hours</td>
                                    <td><s class="text-muted">₱150</s> ₱130</td>
                                    <td><s class="text-muted">₱150</s> ₱125</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="info-badge">
                            <i class="fas fa-info-circle"></i>
                            <strong>Members save 20%</strong> on all computer reservations! Plus, enjoy our special 5-hour promo for additional savings.
                        </div>
                    </div>
                    
                    <!-- Price Calculator -->
                    <div class="calculator animate__animated animate__fadeIn">
                        <h3 class="calculator-title"><i class="fas fa-calculator me-2"></i>Price Calculator</h3>
                        
                        <div class="form-group mb-3">
                            <label for="hours" class="form-label">Select Hours:</label>
                            <select class="form-select" id="hours">
                                <option value="1">1 Hour</option>
                                <option value="2">2 Hours</option>
                                <option value="3">3 Hours</option>
                                <option value="4">4 Hours</option>
                                <option value="5">5 Hours (Special Promo)</option>
                            </select>
                        </div>
                        
                        <div class="price-display">
                            <div>Your Total:</div>
                            <div id="total-price">₱<?php echo $is_member ? '25' : '30'; ?></div>
                            <?php if ($is_member): ?>
                                <div class="mt-2"><small class="text-muted"><i class="fas fa-tag"></i> Member price applied</small></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="computers.php" class="btn btn-danger btn-lg">
                                <i class="fas fa-desktop me-2"></i>Reserve a Computer
                            </a>
                            
                            <?php if (!$is_member): ?>
                                <div class="mt-3">
                                    <a href="membership.php" class="text-decoration-none text-light">
                                        <i class="fas fa-crown text-warning"></i> Become a member to save 20% on every reservation!
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Define pricing
            const pricing = {
                nonMember: {
                    1: 30,
                    2: 60,
                    3: 90,
                    4: 120,
                    5: 130  // Promo price
                },
                member: {
                    1: 25,
                    2: 50,
                    3: 75,
                    4: 100,
                    5: 120   // Promo price
                }
            };
            
            // Get user membership status
            const isMember = <?php echo $is_member ? 'true' : 'false'; ?>;
            
            // Update price when hours change
            $('#hours').on('change', function() {
                const hours = parseInt($(this).val());
                const price = isMember ? pricing.member[hours] : pricing.nonMember[hours];
                
                // Animate price change
                const $priceElement = $('#total-price');
                $priceElement.fadeOut(200, function() {
                    $(this).text('₱' + price).fadeIn(200);
                });
                
                // Highlight special offer
                if (hours === 5) {
                    $priceElement.addClass('text-danger');
                } else {
                    $priceElement.removeClass('text-danger');
                }
            });
            
           
        });
    </script>
</body>

</html>
