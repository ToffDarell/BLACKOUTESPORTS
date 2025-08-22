<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

// Check if user is a member
$user_id = $_SESSION['user_id'];
$is_member = false;
$member_query = "SELECT m.membership_id FROM memberships m 
                JOIN users u ON m.user_id = u.user_id 
                WHERE u.user_id = ? AND u.membership_status = 'Member'";
$stmt = $conn->prepare($member_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $is_member = true;
}
$stmt->close();

// Define rates
$member_rate = 25;
$regular_rate = 30;

// Define rates based on hours
$member_rates = [
    1 => 25,
    2 => 50,
    3 => 75,
    4 => 100,
    5 => 125
];

$regular_rates = [
    1 => 30,
    2 => 60,
    3 => 90,
    4 => 130,
    5 => 130
];

// Get the computer_id and computer_number from URL parameters
$selected_computer_id = isset($_GET['id']) ? $_GET['id'] : null;
$selected_computer_number = isset($_GET['number']) ? $_GET['number'] : null;

// Handle reservation submission
if (isset($_POST['submit_reservation'])) {
    $user_id = $_SESSION['user_id'];
    $computer_number = $_POST['computer_number'];
    $reservation_date = $_POST['reservation_date'];
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration']; // Duration in hours
    
    // Validate start time is within operating hours (9:00 AM to 9:00 PM)
    $start_hour = (int)date('H', strtotime($start_time));
    $start_minute = (int)date('i', strtotime($start_time));
    
    if ($start_hour < 9 || ($start_hour > 21) || ($start_hour == 21 && $start_minute > 0)) {
        $message = "Please select a start time between 9:00 AM and 9:00 PM.";
        $messageType = "danger";
        $upload_error = true;
    }
    
    // Calculate end time
    $datetime_start = $reservation_date . ' ' . $start_time;
    $end_time = date('H:i:s', strtotime($start_time . " + $duration hours"));
    
    // Validate that the end time doesn't exceed closing time (10:00 PM)
    $end_hour = (int)date('H', strtotime($end_time));
    $end_minute = (int)date('i', strtotime($end_time));
    
    if ($end_hour > 22 || ($end_hour == 22 && $end_minute > 0)) {
        $message = "Your reservation would end after our 10:00 PM closing time. Please adjust your start time or duration.";
        $messageType = "danger";
        $upload_error = true;
    }

    // Handle payment proof upload
    $screenshot_receipt = '';
    $upload_error = false;
    
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['size'] > 0) {
        $target_dir = "uploads/payments/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["payment_proof"]["name"], PATHINFO_EXTENSION));
        $new_filename = $user_id . '_' . date('YmdHis') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check file size (5MB max)
        if ($_FILES["payment_proof"]["size"] > 5000000) {
            $message = "Sorry, your file is too large. Maximum size is 5MB.";
            $messageType = "danger";
            $upload_error = true;
        }
        
        // Allow certain file formats
        $allowed_extensions = array("jpg", "jpeg", "png", "pdf");
        if (!in_array($file_extension, $allowed_extensions)) {
            $message = "Sorry, only JPG, JPEG, PNG & PDF files are allowed.";
            $messageType = "danger";
            $upload_error = true;
        }
        
        // If no errors, try to upload file
        if (!$upload_error) {
            if (move_uploaded_file($_FILES["payment_proof"]["tmp_name"], $target_file)) {
                $screenshot_receipt = $target_file;
            } else {
                $message = "Sorry, there was an error uploading your file.";
                $messageType = "danger";
                $upload_error = true;
            }
        }
    } else {
        $message = "Please upload proof of payment.";
        $messageType = "danger";
        $upload_error = true;
    }
    
    // Only proceed if there are no upload errors
    if (!$upload_error) {
        // Calculate total amount using the pricing structure
        if ($is_member) {
            $total_amount = isset($member_rates[$duration]) ? $member_rates[$duration] : $member_rate * $duration;
        } else {
            $total_amount = isset($regular_rates[$duration]) ? $regular_rates[$duration] : $regular_rate * $duration;
        }
        
        // Check if computer is available for the selected time
        $check_query = "SELECT reservation_id FROM reservations 
                      WHERE computer_number = ? 
                      AND reservation_date = ? 
                      AND status = 'Confirmed'
                      AND (
                          (start_time <= ? AND end_time > ?)
                          OR
                          (start_time < ? AND start_time >= ?)
                      )";

        $check_stmt = $conn->prepare($check_query);

        if (!$check_stmt) {
            die("Prepare failed: " . $conn->error); // This will show the actual error
        }

        $check_stmt->bind_param('isssss', 
            $computer_number, 
            $reservation_date, 
            $start_time, 
            $start_time,
            $end_time,
            $start_time
        );

        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "This computer is already reserved for the selected time period.";
            $messageType = "danger";
        } else {
            $insert_query = "INSERT INTO reservations (user_id, computer_number, reservation_date, start_time, end_time, screenshot_receipt, status, notification_status, total_amount) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Pending', 'Unread', ?)";

            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('iissssd', $user_id, $computer_number, $reservation_date, $start_time, $end_time, $screenshot_receipt, $total_amount);

            if ($stmt->execute()) {
                $message = "Reservation request submitted! Waiting for admin approval.";
                $messageType = "success";
            } else {
                $message = "Error making reservation: " . $stmt->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch computer details if we have a computer_id
$computer_details = null;
if ($selected_computer_id) {
    $query = "SELECT * FROM computers WHERE computer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $selected_computer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $computer_details = $result->fetch_assoc();
    }
    $stmt->close();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Reservation | Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="computers.css">
    <link rel="stylesheet" href="make_reservations.css">
    <link rel="stylesheet" href="admin_nav.css">
    <style>
        .notification-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: none;
        }
        .form-text {
            color: white;
        }
        
        .payment-alert {
            background-color:#404040;
            color: white;
            border-color: #333;
        }
        
        .payment-alert strong {
            color: white;
        }
        
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


    <!-- Main Content -->
    <div class="container">
        <div class="reservation-container">
            <h2 class="reservation-title">
                Make a Reservation
                <?php if ($selected_computer_number): ?>
                <span class="badge bg-danger">PC #<?php echo htmlspecialchars($selected_computer_number); ?></span>
                <?php endif; ?>
            </h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> animate__animated animate__fadeIn"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-section">
                    <!-- Hidden input for computer number -->
                    <input type="hidden" name="computer_number" value="<?php echo htmlspecialchars($selected_computer_number); ?>">

                    <div class="mb-4">
                        <label for="reservation_date" class="form-label">
                            <i class="fas fa-calendar me-2"></i>Date
                        </label>
                        <input type="date" class="form-control" name="reservation_date" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="start_time" class="form-label">
                            <i class="fas fa-clock me-2"></i>Start Time
                        </label>
                        <input type="time" class="form-control" name="start_time" min="09:00" max="21:00" step="1800" required>
                        <div class="form-text">
                            Operating hours: 8:00 AM - 10:00 PM 
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="duration" class="form-label">
                            <i class="fas fa-hourglass-half me-2"></i>Duration
                        </label>
                        <select class="form-select" id="duration" name="duration" required onchange="calculateTotal()">
                            <option value="">Select Duration</option>
                            <option value="1">1 hour</option>
                            <option value="2">2 hours</option>
                            <option value="3">3 hours</option>
                            <option value="4">4 hours</option>
                            <option value="5">5 hours</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <div class="card text-white" style="background-color: #404040; border-color: #333;">
                            <div class="card-body">
                                <h5 class="card-title">Pricing Information</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Your Status:</span>
                                    <span>
                                        <?php if ($is_member): ?>
                                            <strong class="text-success">Member (Discounted Rates)</strong>
                                        <?php else: ?>
                                            <strong class="text-danger">Non-Member (Standard Rates)</strong>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Hours:</span>
                                    <span id="hours-display">-</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Amount:</span>
                                    <span id="total-amount" class="fw-bold">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-money-bill-wave me-2"></i>Payment Proof
                        </label>
                        <div class="alert payment-alert mb-3">
                            <strong>Please send the payment to: GCash: 09123456789</strong><br>
                            <strong>Total Amount: <span id="payment-amount">₱0</span></strong><br>
                           
                        </div>
                        <input type="file" class="form-control" id="paymentProofInput" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">
                            Accepted formats: JPG, JPEG, PNG, PDF (Max: 5MB)
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" name="submit_reservation" class="btn btn-primary btn-lg">
                        <i class="fas fa-check-circle me-2"></i>Confirm Reservation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="notificationPopup" class="notification-popup alert" role="alert"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Price calculation
        const isMember = <?php echo $is_member ? 'true' : 'false'; ?>;
        
        // Define pricing tiers based on the price list
        const memberRates = {
            1: 25,
            2: 50,
            3: 75,
            4: 100,
            5: 125
        };
        
        const regularRates = {
            1: 30,
            2: 60,
            3: 90,
            4: 130,
            5: 130
        };
        
        function calculateTotal() {
            const hoursSelect = document.getElementById('duration');
            const hoursDisplay = document.getElementById('hours-display');
            const totalDisplay = document.getElementById('total-amount');
            const paymentAmountDisplay = document.getElementById('payment-amount');
            
            if (hoursSelect.value === '') {
                hoursDisplay.textContent = '-';
                totalDisplay.textContent = '-';
                paymentAmountDisplay.textContent = '₱0';
                return;
            }
            
            const hours = parseInt(hoursSelect.value);
            
            // Get the price based on hours and membership
            const memberTotal = memberRates[hours];
            const regularTotal = regularRates[hours];
            const total = isMember ? memberTotal : regularTotal;
            
            hoursDisplay.textContent = hours > 1 ? `${hours} hours` : `${hours} hour`;
            totalDisplay.textContent = `₱${total}`;
            paymentAmountDisplay.textContent = `₱${total}`;
            
            // Highlight the savings if member
            if (isMember) {
                const savings = regularTotal - memberTotal;
                totalDisplay.innerHTML = `₱${memberTotal} <span class="text-success ms-2">(Saved ₱${savings})</span>`;
            }
            
            // Highlight the special promo price for 5 hours
            if (hours === 5) {
                if (isMember) {
                    totalDisplay.innerHTML = `₱${memberTotal} <span class="text-success ms-2">(Promo price! Was ₱150)</span>`;
                } else {
                    totalDisplay.innerHTML = `₱${regularTotal} <span class="text-success ms-2">(Promo price! Was ₱150)</span>`;
                }
            }
        }
        
        // Time validation and slot calculations
        function validateTimeAndSlots() {
            const durationSelect = document.getElementById('duration');
            const startTimeInput = document.getElementById('start_time');
            
            if (!startTimeInput.value) return;
            
            const hours = parseInt(durationSelect.value || 1);
            const startTime = startTimeInput.value;
            
            // Calculate end time
            const [startHour, startMinute] = startTime.split(':').map(Number);
            let endHour = startHour + hours;
            let endMinute = startMinute;
            
            // Format for display
            const formattedStartTime = formatTimeDisplay(startHour, startMinute);
            const formattedEndTime = formatTimeDisplay(endHour, endMinute);
            
            // Check if end time is after closing (10 PM)
            if (endHour > 22 || (endHour === 22 && endMinute > 0)) {
                alert(`Your reservation would end at ${formattedEndTime}, which is after our 10:00 PM closing time. Please adjust your start time or duration.`);
                startTimeInput.value = '';
            }
        }
        
        function formatTimeDisplay(hours, minutes) {
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // Convert 0 to 12
            const minutesFormatted = minutes.toString().padStart(2, '0');
            return `${hours}:${minutesFormatted} ${ampm}`;
        }
        
        // Add event listeners for time validation
        document.getElementById('start_time').addEventListener('change', validateTimeAndSlots);
        document.getElementById('duration').addEventListener('change', validateTimeAndSlots);
        
        // Calculate on page load if a duration is already selected
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });

        function checkNotifications() {
            fetch('check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasNotification) {
                        const popup = document.getElementById('notificationPopup');
                        popup.textContent = data.message;
                        popup.className = `notification-popup alert alert-${data.status === 'Approved' ? 'success' : 'danger'} show`;
                        popup.style.display = 'block';
                        
                        // Hide popup after 5 seconds
                        setTimeout(() => {
                            popup.style.display = 'none';
                        }, 5000);

                        // Mark notification as read
                        fetch('check_notifications.php?mark_read=1');
                    }
                });
        }

        // Check for notifications every 10 seconds
        setInterval(checkNotifications, 10000);
        // Check immediately on page load
        checkNotifications();
    </script>
</body>
</html>
<?php $conn->close(); ?>
