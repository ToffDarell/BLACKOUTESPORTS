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

// Fetch user details and membership info
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.user_name, u.user_email, m.join_date, u.membership_status, m.title, m.message 
                         FROM users u 
                         LEFT JOIN memberships m ON u.user_id = m.user_id 
                         WHERE u.user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
    $membership_status = $row['membership_status'];
    $join_date = $row['join_date'];
    $notification_title = $row['title'];
    $notification_message = $row['message'];
    if ($join_date) {
        $start_date = date('F d, Y', strtotime($join_date));
        $end_date = date('F d, Y', strtotime("$join_date +1 month"));
    }
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="membership.css">
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
                            <a class="nav-link" href="pricing.php">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="membership.php">Membership</a>
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

    <!-- Membership Section -->
    <section class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <?php if (isset($membership_status) && $membership_status === 'Member' && isset($start_date) && isset($end_date)): ?>
                        <?php
                            $today = new DateTime();
                            $start = new DateTime($join_date);
                            $end = new DateTime($end_date);
                            $total_days = $start->diff($end)->days;
                            $days_left = $today < $end ? $today->diff($end)->days : 0;
                            $progress = $total_days > 0 ? round((($total_days - $days_left) / $total_days) * 100) : 100;
                        ?>
                        <div class="row justify-content-center mb-4">
                            <div class="col-lg-7">
                                <div class="pricing-card animate__animated animate__fadeIn" style="border: 2px solid #dc3545; box-shadow: 0 4px 24px rgba(220,53,69,0.10); background: #181818; color: #fff;">
                                    <div class="card-body p-4" style="color: #fff;">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3">
                                                <span class="bg-danger bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px; font-size: 2rem; border: 2px solid #dc3545; background: rgba(220,53,69,0.15); color: #fff;">
                                                    <i class="fas fa-crown" style="color: #dc3545;"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="mb-0" style="color: #fff;">Blackout Membership <span class="badge bg-danger ms-2">Active</span></h4>
                                                <small style="color: #fff;">Enjoy your exclusive benefits!</small>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2 mb-md-0">
                                                <div class="rounded p-2" style="background: rgba(220,53,69,0.10); color: #fff;">
                                                    <i  style="color: #dc3545;"></i>
                                                    <span class="fw-bold">Registered:</span> <?php echo $start_date; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="rounded p-2" style="background: rgba(220,53,69,0.10); color: #fff;">
                                                    <i  style="color: #dc3545;"></i>
                                                    <span class="fw-bold">Ends:</span> <?php echo $end_date; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="progress" style="height: 18px; background: #222;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $progress; ?>%; color: #fff;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $days_left > 0 ? "$days_left day" . ($days_left > 1 ? 's' : '') . " left" : "Expired"; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($notification_title) && !empty($notification_message)): ?>
                                        <div class="mt-3 mb-2">
                                            <div class="p-3 rounded" style="background: rgba(220,53,69,0.1); border-left: 3px solid #dc3545;">
                                                <h5 style="color: #dc3545;"><i class="fas fa-bell me-2"></i><?php echo htmlspecialchars($notification_title); ?></h5>
                                                <p class="mb-0"><?php echo htmlspecialchars($notification_message); ?></p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($days_left === 0): ?>
                                            <div class="alert alert-danger mt-3 mb-0 p-2 text-center" style="color: #fff; background: #dc3545; border: none;"><i class="fas fa-exclamation-triangle me-2"></i>Your membership has expired. Please renew to continue enjoying member benefits.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-6 animate__animated animate__fadeIn">
                    <div class="pricing-card">
                        <h2 class="pricing-title"></i>Blackout Member</h2>
                        <div class="price-display">
                            <div>Monthly Fee:</div>
                            <div>₱200</div>
                        </div>
                        
                        <div class="hourly-rates mb-4">
                            <div class="info-badge">
                                <i class="fas fa-info-circle"></i>
                                <strong>Member Rate: ₱20/hour</strong> (Regular Rate: ₱30/hour)
                                <div class="mt-2 text-danger">Save ₱10 per hour!</div>
                            </div>
                        </div>
                        
                        <h3 class="mt-4 mb-3">Member Benefits</h3>
                        <ul class="benefits-list list-unstyled mt-4">
                            <li><i class="fas fa-check text-success me-2"></i>Discounted hourly rate</li>
                            <li><i class="fas fa-check text-success me-2"></i>Priority booking</li>
                            <li><i class="fas fa-check text-success me-2"></i>Premium peripherals</li>
                            <li><i class="fas fa-check text-success me-2"></i>Member-only events</li>
                        </ul>
                        
                        <div class="text-center mt-4">
                            <button class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#registrationModal">
                                </i>Become a Member
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Modal for Registration -->
            <div class="modal fade" id="registrationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content bg-dark">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Membership Registration</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="membershipForm" method="POST" action="process_membership.php" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="fullName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Membership Fee: ₱200</label>
                                    <div class="alert alert-info">
                                        Please send payment to: GCash: 09123456789
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="paymentProof" class="form-label">Upload Payment Proof</label>
                                    <input type="file" class="form-control" id="paymentProof" name="paymentProof" accept="image/*" required>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">I accept the Terms & Conditions</label>
                                </div>
                                
                                <button type="submit" class="btn btn-danger w-100">Submit Membership Application</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>

</html>
