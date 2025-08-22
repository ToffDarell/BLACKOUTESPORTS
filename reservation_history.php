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
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
}
$stmt->close();

// Set default period filter (default: last 30 days)
$period = isset($_GET['period']) ? $_GET['period'] : '30';
// Remove status filter variable
// $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Define period options and their time
$period_options = [
    '7' => "Last 7 days",
    '30' => "Last 30 days", 
    '90' => "Last 3 months",
    '365' => "Last year",
    'all' => "All time"
];

// Calculate date range based on selected period
if ($period !== 'all') {
    $end_date = date('Y-m-d'); // Today
    $start_date = date('Y-m-d', strtotime("-$period days"));
    $date_filter = " AND reservation_date BETWEEN ? AND ?";
    $date_params = [$start_date, $end_date];
} else {
    $date_filter = "";
    $date_params = [];
}

// Remove status filter code
// Build the status filter
// if ($status_filter !== 'all') {
//     $status_sql_filter = " AND status = ?";
//     $status_param = [$status_filter];
// } else {
//     $status_sql_filter = "";
//     $status_param = [];
// }

// Fetch user's reservations with filters
$reservations = [];

// Check if total_amount column exists
$check_columns = $conn->query("SHOW COLUMNS FROM reservations LIKE 'total_amount'");
$has_amount = ($check_columns && $check_columns->num_rows > 0);

// Build the query based on whether total_amount exists
if ($has_amount) {
    $res_query = "SELECT * FROM reservations WHERE user_id = ?";
} else {
    // Column doesn't exist, add a default value
    $res_query = "SELECT *, 0.00 AS total_amount FROM reservations WHERE user_id = ?";
}

// Add date filter if needed
if ($period !== 'all') {
    $res_query .= " AND reservation_date BETWEEN ? AND ?";
}

// Add ordering
$res_query .= " ORDER BY reservation_date DESC, start_time DESC";

// Prepare the statement
$res_stmt = $conn->prepare($res_query);

// Check if the prepare was successful
if ($res_stmt === false) {
    die("Error in preparing statement: " . $conn->error);
}

// Bind parameters dynamically
if ($period !== 'all') {
    // Only date filter
    $res_stmt->bind_param('iss', $user_id, $date_params[0], $date_params[1]);
} else {
    // No filters
    $res_stmt->bind_param('i', $user_id);
}

// Just after binding parameters and before executing
$res_stmt->execute();
$res_result = $res_stmt->get_result();

// Debug: Count rows found
$reservation_count = $res_result->num_rows;

while ($res = $res_result->fetch_assoc()) {
    // Add debug info for each reservation
    if (!isset($res['total_amount'])) {
        // Update the reservation with a default amount if it doesn't exist
        $res['total_amount'] = 0.00;
    }
    // Now add this record to our array
    $reservations[] = $res;
}
$res_stmt->close();

// Get reservation statistics
$total_completed = 0;
$total_cancelled = 0;
$total_spent = 0;
$total_refunded = 0;

$stats_query = "SELECT 
                COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'Cancelled' OR status = 'Refunded' THEN 1 END) as cancelled_count,
                SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as total_spent,
                SUM(CASE WHEN status = 'Refunded' THEN total_amount ELSE 0 END) as total_refunded
                FROM reservations 
                WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_query);

// Check if the prepare was successful
if ($stats_stmt === false) {
    // If stats query fails, just continue with default values
    // This might happen if columns like total_amount don't exist
    error_log("Error in preparing stats statement: " . $conn->error);
} else {
    $stats_stmt->bind_param('i', $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    if ($stats = $stats_result->fetch_assoc()) {
        $total_completed = $stats['completed_count'] ?: 0;
        $total_cancelled = $stats['cancelled_count'] ?: 0;
        $total_spent = $stats['total_spent'] ?: 0;
        $total_refunded = $stats['total_refunded'] ?: 0;
    }
    $stats_stmt->close();
}

// After database checks, add this code to check if total_amount column exists
$check_columns = $conn->query("SHOW COLUMNS FROM reservations LIKE 'total_amount'");
$has_amount = ($check_columns && $check_columns->num_rows > 0);

// If total_amount column doesn't exist, add it
if (!$has_amount) {
    $conn->query("ALTER TABLE reservations ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00");
    // Log this action
    error_log("Added missing total_amount column to reservations table");
}

// Move this line from its current position after the stats query
// to the end of the PHP section right before the HTML
// $conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation History - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="pricing.css">
    <link rel="stylesheet" href="footer.css">
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
        
        /* Reservation history specific styles */
        .history-container {
            background-color: #151515;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid #2c2c2c;
        }
        
        .reservation-filters {
            background-color: #1d1d1d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        
        .stats-card {
            background-color: #1d1d1d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        
        .reservation-card {
            background-color: #1d1d1d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .reservation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .reservation-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        
        .status-cancelled {
            background-color: #6c757d;
            color: white;
        }
        
        .status-refunded {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-confirmed {
            background-color: #28a745;
            color: white;
        }
        
        .status-refund-requested {
            background-color: #fd7e14;
            color: white;
        }
        
        .reservation-info {
            display: flex;
            align-items: center;
            color: #dddddd;
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
            margin-right: 15px;
        }
        
        .reservation-details {
            flex-grow: 1;
        }
        
        .reservation-date {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .reservation-time {
            display: inline-block;
            background-color: #212529;
            color: #fff;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.85rem;
            margin: 0 3px;
        }
        
        .reservation-payment {
            display: flex;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #333;
        }
        
        .form-select {
            background-color: #212529;
            color: #fff;
            border-color: #444;
        }
        
        .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
            border-color: #dc3545;
        }
        
        .no-reservations {
            text-align: center;
            padding: 40px 30px;
            background-color: #1d1d1d;
            border-radius: 10px;
            color: #aaa;
            border-left: 4px solid #dc3545;
        }
        
        .no-reservations i {
            color: #dc3545;
            opacity: 0.7;
            font-size: 3rem;
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
                            <a class="nav-link" href="tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="membership.php">Membership</a>
                        </li>
                        <!-- Profile Icon with Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle active" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i> <?php echo $user_name; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="profileDropdown">
                                <li><h6 class="dropdown-header">Signed in as <strong><?php echo $user_name; ?></strong></h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item active" href="reservation_history.php"><i class="fas fa-history me-2"></i>Reservation History</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Log Out</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Reservation History Section -->
    <section class="pricing-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>RESERVATION HISTORY</h1>
                        <p>View and manage your past reservations</p>
                    </div>
                    
                    <!-- History Container -->
                    <div class="history-container animate__animated animate__fadeIn">
                        
                        <?php if (!$has_amount): ?>
                        <div class="alert alert-info mb-4" style="background-color: #1d1d1d; border-color: #3d5a80; color: #edf2f4;">
                            <i class="fas fa-info-circle me-2 text-primary"></i>
                            <strong>System Notice:</strong> The 'total_amount' column was automatically added to the reservations table. All existing reservations have a default amount of ₱0.00. Please update them with actual amounts if needed.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Filters -->
                        <div class="reservation-filters">
                            <h3 class="mb-3 text-white"><i class="fas fa-filter me-2 text-danger"></i>Filter Reservations</h3>
                            <form action="" method="get" class="row g-3">
                                <div class="col-md-12">
                                    <label for="period" class="form-label text-white">Time Period</label>
                                    <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                                        <?php foreach ($period_options as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($period == $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Reservations List -->
                        <h3 class="mb-4 text-white"><i class="fas fa-history me-2 text-danger"></i>Reservation Records</h3>
                        
                        <?php if (!empty($reservations)): ?>
                            <?php foreach ($reservations as $reservation): 
                                // Format status class
                                switch ($reservation['status']) {
                                    case 'Completed':
                                        $status_class = 'status-completed';
                                        break;
                                    case 'Cancelled':
                                        $status_class = 'status-cancelled';
                                        break;
                                    case 'Refunded':
                                        $status_class = 'status-refunded';
                                        break;
                                    case 'Pending':
                                        $status_class = 'status-pending';
                                        break;
                                    case 'Confirmed':
                                        $status_class = 'status-confirmed';
                                        break;
                                    case 'Refund Requested':
                                        $status_class = 'status-refund-requested';
                                        break;
                                    default:
                                        $status_class = '';
                                }
                            ?>
                            <div class="reservation-card">
                                <span class="reservation-status <?php echo $status_class; ?>"><?php echo $reservation['status']; ?></span>
                                
                                <div class="reservation-info">
                                    <div class="computer-icon">
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                    
                                    <div class="reservation-details">
                                        <h4 class="mb-1">Computer #<?php echo htmlspecialchars($reservation['computer_number']); ?></h4>
                                        <div class="reservation-date mb-2">
                                            <i class="fas fa-calendar-day me-1"></i> 
                                            <?php echo date('l, F j, Y', strtotime($reservation['reservation_date'])); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-clock me-1"></i> 
                                            <span class="reservation-time"><?php echo date('g:i A', strtotime($reservation['start_time'])); ?></span> 
                                            to 
                                            <span class="reservation-time"><?php echo date('g:i A', strtotime($reservation['end_time'])); ?></span>
                                            
                                            <?php
                                            // Calculate duration
                                            $start = new DateTime($reservation['start_time']);
                                            $end = new DateTime($reservation['end_time']);
                                            $interval = $start->diff($end);
                                            $hours = $interval->h;
                                            $minutes = $interval->i;
                                            
                                            $duration = '';
                                            if ($hours > 0) {
                                                $duration .= $hours . 'h ';
                                            }
                                            if ($minutes > 0) {
                                                $duration .= $minutes . 'm';
                                            }
                                            ?>
                                            
                                            <span class="text-muted ms-2">(<?php echo $duration; ?>)</span>
                                        </div>
                                        
                                        <div class="reservation-payment">
                                            <?php if ($reservation['status'] === 'Refunded'): ?>
                                                <span class="text-white">Amount: ₱<?php echo isset($reservation['total_amount']) ? number_format($reservation['total_amount'], 2) : '0.00'; ?> (refunded)</span>
                                            <?php else: ?>
                                                <span class="text-white">Amount: ₱<?php echo isset($reservation['total_amount']) ? number_format($reservation['total_amount'], 2) : '0.00'; ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($reservation['payment_method']) && !empty($reservation['payment_method'])): ?>
                                                <span class="ms-3 text-muted">
                                                    <i class="fas fa-credit-card me-1"></i> 
                                                    <?php echo $reservation['payment_method']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-reservations">
                                <i class="fas fa-calendar-times mb-3"></i>
                                <h4 class="text-white">No Reservations Found</h4>
                                <p>You don't have any reservations for the selected filters.</p>
                                <p class="text-muted">(Found <?php echo $reservation_count; ?> reservations in database)</p>
                                <p class="mb-0">Try changing the filter options or make a new reservation.</p>
                                <a href="computers.php" class="btn btn-danger mt-4">
                                    <i class="fas fa-desktop me-2"></i>Browse Computers
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
</body>

</html>