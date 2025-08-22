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
$stmt = $conn->prepare("SELECT user_name, user_email, membership_status FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_name = $row['user_name'];
    $user_email = $row['user_email'];
    $membership_status = $row['membership_status'];
    $membership_expiry = ''; // Set a default empty value
}
$stmt->close();

// Handle profile update
$update_message = '';
$update_message_type = '';

if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['new_name']);
    
    // Validate name
    if (empty($new_name)) {
        $update_message = "Name cannot be empty";
        $update_message_type = "danger";
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET user_name = ? WHERE user_id = ?");
        $update_stmt->bind_param('si', $new_name, $user_id);
        
        if ($update_stmt->execute()) {
            $user_name = $new_name; // Update the displayed name
            $update_message = "Profile updated successfully!";
            $update_message_type = "success";
        } else {
            $update_message = "Error updating profile: " . $conn->error;
            $update_message_type = "danger";
        }
        $update_stmt->close();
    }
}

// Handle password reset request
$password_message = '';
$password_message_type = '';

if (isset($_POST['reset_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if new password and confirm password match
    if ($new_password !== $confirm_password) {
        $password_message = "New passwords do not match";
        $password_message_type = "danger";
    } else {
        // Verify current password
        $pass_check = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $pass_check->bind_param('i', $user_id);
        $pass_check->execute();
        $pass_result = $pass_check->get_result();
        if ($pass_row = $pass_result->fetch_assoc()) {
            $stored_password = $pass_row['password'];
            
            // Verify password (assuming hashed password)
            if (password_verify($current_password, $stored_password)) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $pass_update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $pass_update->bind_param('si', $hashed_password, $user_id);
                
                if ($pass_update->execute()) {
                    $password_message = "Password updated successfully!";
                    $password_message_type = "success";
                } else {
                    $password_message = "Error updating password: " . $conn->error;
                    $password_message_type = "danger";
                }
                $pass_update->close();
            } else {
                $password_message = "Current password is incorrect";
                $password_message_type = "danger";
            }
        }
        $pass_check->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Blackout Esports</title>
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
        
        /* Profile specific styles */
        .profile-container {
            background-color: #151515;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid #2c2c2c;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background-color: #252525;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #dc3545;
            margin-right: 20px;
            border: 2px solid #dc3545;
        }
        
        .profile-name {
            color: #ffffff;
            margin-bottom: 5px;
        }
        
        .profile-email {
            color: #bbbbbb;
            font-size: 0.9rem;
        }
        
        .profile-section {
            background-color: #1d1d1d;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        
        .profile-section-title {
            color: #ffffff;
            margin-bottom: 20px;
            font-size: 1.4rem;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .profile-section-title i {
            margin-right: 10px;
            color: #dc3545;
        }
        
        .membership-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background-color: #252525;
            color: #dc3545;
            margin-bottom: 20px;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .membership-premium {
            color: gold;
            border-color: rgba(255, 215, 0, 0.3);
        }
        
        .membership-detail {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: #dddddd;
        }
        
        .membership-detail i {
            width: 25px;
            color: #dc3545;
            margin-right: 10px;
        }
        
        .form-label {
            color: #ffffff;
        }
        
        .btn-update {
            background-color: #dc3545;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn-update:hover {
            background-color: #c82333;
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
                                <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-id-card me-2"></i>My Profile</a></li>
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

    <!-- Profile Section -->
    <section class="pricing-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>MY PROFILE</h1>
                        <p>Manage your account details</p>
                    </div>
                    
                    <!-- Profile Container -->
                    <div class="profile-container animate__animated animate__fadeIn">
                        <!-- Profile Header -->
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h2 class="profile-name"><?php echo $user_name; ?></h2>
                                <p class="profile-email"><?php echo $user_email; ?></p>
                            </div>
                        </div>

                        <!-- Membership Section -->
                        <div class="profile-section">
                            <h3 class="profile-section-title">
                                <i class="fas fa-crown"></i> Membership Status
                            </h3>
                            
                            <?php
                            $badge_class = ($membership_status === 'Premium') ? 'membership-badge membership-premium' : 'membership-badge';
                            $membership_icon = ($membership_status === 'Premium') ? 'fas fa-crown' : 'fas fa-user';
                            ?>
                            
                            <span class="<?php echo $badge_class; ?>">
                                <i class="<?php echo $membership_icon; ?> me-2"></i>
                                <?php echo $membership_status; ?> Member
                            </span>
                            
                            <div class="membership-details mt-4">
                                <div class="membership-detail">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>
                                        <?php if ($membership_status === 'Regular'): ?>
                                            <strong>Status:</strong> Regular Member
                                        <?php else: ?>
                                            <strong>Status:</strong> Premium Member
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($membership_status === 'Premium'): ?>
                                <div class="membership-detail">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>
                                        <?php if (!empty($membership_expiry)): ?>
                                        <strong>Expires on:</strong> <?php echo date('F j, Y', strtotime($membership_expiry)); ?>
                                        (<?php 
                                            $today = new DateTime();
                                            $expiry = new DateTime($membership_expiry);
                                            $diff = $today->diff($expiry);
                                            echo $diff->days; ?> days remaining)
                                        <?php else: ?>
                                        <strong>Status:</strong> Premium membership active
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="membership-detail">
                                    <i class="fas fa-tag"></i>
                                    <span>
                                        <?php if ($membership_status === 'Premium'): ?>
                                            <strong>Benefits:</strong> Priority booking, 10% discount on all reservations, exclusive tournaments access
                                        <?php else: ?>
                                            <strong>Upgrade to Premium:</strong> Get priority booking, discounts and more!
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($membership_status !== 'Premium'): ?>
                                <div class="mt-4">
                                    <a href="membership.php" class="btn btn-outline-danger">
                                        <i class="fas fa-arrow-circle-up me-2"></i>Upgrade Membership
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Update Profile Section -->
                        <div class="profile-section">
                            <h3 class="profile-section-title">
                                <i class="fas fa-user-edit"></i> Update Profile
                            </h3>
                            
                            <?php if (!empty($update_message)): ?>
                            <div class="alert alert-<?php echo $update_message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $update_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="new_name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="new_name" name="new_name" value="<?php echo $user_name; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" value="<?php echo $user_email; ?>" disabled>
                                    <div class="form-text text-muted">Email cannot be changed.</div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-update">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </form>
                        </div>
                        
                        <!-- Change Password Section -->
                        <div class="profile-section">
                            <h3 class="profile-section-title">
                                <i class="fas fa-lock"></i> Change Password
                            </h3>
                            
                            <?php if (!empty($password_message)): ?>
                            <div class="alert alert-<?php echo $password_message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $password_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="reset_password" class="btn btn-update">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
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
