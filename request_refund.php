<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get reservation details if ID is in the URL
$reservation_details = null;
if (isset($_GET['reservation_id'])) {
    $reservation_id = $_GET['reservation_id'];
    
    // Verify the reservation belongs to the current user
    $check_query = "SELECT r.*, u.user_name, u.user_email FROM reservations r
                    JOIN users u ON r.user_id = u.user_id
                    WHERE r.reservation_id = ? AND r.user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $reservation_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Reservation not found or doesn't belong to user
        $message = "Invalid reservation selected.";
        $messageType = "danger";
    } else {
        $reservation_details = $result->fetch_assoc();
        
        // Check if the reservation is already paid and confirmed
        if ($reservation_details['status'] !== 'Confirmed') {
            $message = "Refunds can only be requested for confirmed reservations.";
            $messageType = "warning";
            $_SESSION['message'] = $message;
            $_SESSION['messageType'] = $messageType;
            header("Location: dashboard.php");
            exit();
        }
    }
}

// Process refund request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund'])) {
    $reservation_id = $_POST['reservation_id'];
    $reason = $_POST['reason'];
    
    // Verify the reservation belongs to the current user
    $check_query = "SELECT * FROM reservations WHERE reservation_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $reservation_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = "Invalid reservation selected.";
        $messageType = "danger";
    } else {
        // Check if a refund request already exists
        $check_refund = "SELECT * FROM refund_requests WHERE reservation_id = ?";
        $refund_stmt = $conn->prepare($check_refund);
        $refund_stmt->bind_param('i', $reservation_id);
        $refund_stmt->execute();
        $refund_result = $refund_stmt->get_result();
        
        if ($refund_result->num_rows > 0) {
            $message = "A refund request for this reservation already exists.";
            $messageType = "warning";
        } else {
            // Insert refund request
            $insert_query = "INSERT INTO refund_requests (user_id, reservation_id, reason) 
                            VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            
            if (!$insert_stmt) {
                $message = "Error preparing query: " . $conn->error;
                $messageType = "danger";
            } else {
                $insert_stmt->bind_param('iis', $user_id, $reservation_id, $reason);
                
                if ($insert_stmt->execute()) {
                    // Update reservation status to 'Refund Requested'
                    $update_query = "UPDATE reservations SET 
                                    status = 'Refund Requested',
                                    cancellation_reason = ?,
                                    cancellation_time = NOW()
                                    WHERE reservation_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    
                    if (!$update_stmt) {
                        $message = "Error preparing update query: " . $conn->error;
                        $messageType = "danger";
                    } else {
                        $update_stmt->bind_param('si', $reason, $reservation_id);
                        $update_stmt->execute();
                        
                        // Update computer status
                        $reservation = $result->fetch_assoc();
                        $update_computer = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
                        
                        if (!$update_computer) {
                            $message = "Error preparing computer update: " . $conn->error;
                            $messageType = "danger";
                        } else {
                            $update_computer->bind_param('s', $reservation['computer_number']);
                            $update_computer->execute();
                            
                            $message = "Your refund request has been submitted successfully. The admin will review it soon.";
                            $messageType = "success";
                            
                            // Send email notification about refund request
                            require_once('notify_reservation_user.php');
                            
                            // Get user details
                            $user_query = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
                            
                            if ($user_query) {
                                $user_query->bind_param('i', $user_id);
                                $user_query->execute();
                                $user_result = $user_query->get_result();
                                $user = $user_result->fetch_assoc();
                                
                                sendReservationStatusEmail(
                                    $user['user_email'],
                                    $user['user_name'],
                                    'Refund Requested',
                                    $reservation['computer_number'],
                                    $reservation['reservation_date'],
                                    $reservation['start_time'],
                                    $reservation['end_time'],
                                    "Refund request reason: " . $reason,
                                    $reservation_id
                                );
                            }
                            
                            // Redirect to dashboard
                            $_SESSION['message'] = $message;
                            $_SESSION['messageType'] = $messageType;
                            header("Location: dashboard.php");
                            exit();
                        }
                    }
                } else {
                    $message = "Error submitting refund request: " . $insert_stmt->error;
                    $messageType = "danger";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Refund | Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="pricing.css">
    <style>
        .refund-section {
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        
        .refund-section::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(rgba(220, 53, 69, 0.1), transparent 70%);
            top: -150px;
            right: -150px;
            border-radius: 50%;
        }
        
        .refund-section::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(rgba(220, 53, 69, 0.1), transparent 70%);
            bottom: -150px;
            left: -150px;
            border-radius: 50%;
        }
        
        .refund-card {
            background: #151515;
            border-radius: 16px;
            padding: 40px 60px;
            margin-bottom: 30px;
            transition: all 0.4s ease;
            border: 1px solid #2c2c2c;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            
        }
        
        .refund-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        .refund-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #000000, #dc3545);
        }
        
        .refund-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .refund-header h1 {
            color: #ffffff;
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .refund-title {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .refund-title:after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #000000, #dc3545);
        }
        
        .reservation-info {
            background-color: #1a1a1a;
            padding: 20px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .reservation-info p {
            color: #ddd;
            margin-bottom: 8px;
            font-size: 1.1rem;
            word-break: keep-all;
            white-space: nowrap;
        }
        
        .reservation-info strong {
            color: #fff;
            margin-right: 5px;
        }
        
        .form-label {
            color: #ddd;
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 500;
        }
        
        textarea.form-control {
            background-color: #212529;
            color: #fff;
            border: 1px solid #2c2c2c;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            transition: all 0.3s;
            resize: vertical;
        }
        
        textarea.form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
            background-color: #2c3034;
        }
        
        .btn-cancel {
            background-color: #333;
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background-color: #444;
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .btn-request-refund {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-request-refund:hover {
            background: #bb2d3b;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-request-refund::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
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
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <section class="refund-section">
        <div class="container">
            <?php if (isset($message) && !empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mt-3 animate__animated animate__fadeIn" role="alert">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-9 mx-auto animate__animated animate__fadeIn">
                    <div class="refund-header">
                        <h1>REQUEST A REFUND</h1>
                    </div>
                    
                    <div class="refund-card animate__animated animate__fadeIn">
                        <?php if ($reservation_details): ?>
                            <div class="reservation-info animate__animated animate__fadeIn">
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><strong><i class="fas fa-desktop me-2"></i>Computer:</strong> PC #<?php echo htmlspecialchars($reservation_details['computer_number']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong><i class="fas fa-calendar-alt me-2"></i>Date:</strong> <?php echo htmlspecialchars($reservation_details['reservation_date']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong><i class="fas fa-clock me-2"></i>Time:</strong> <span style="white-space: nowrap;"><?php echo htmlspecialchars($reservation_details['start_time']) . ' to ' . htmlspecialchars($reservation_details['end_time']); ?></span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="request_refund.php" class="animate__animated animate__fadeIn">
                                <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_details['reservation_id']); ?>">
                                
                                <div class="mb-4">
                                    <label for="reason_select" class="form-label"><i class="fas fa-comment-alt me-2"></i>Reason for Refund Request</label>
                                    <select class="form-control" id="reason_select" name="reason_select" style="background-color: #212529; color: #fff; border: 1px solid #2c2c2c; border-radius: 8px; padding: 12px 15px; margin-bottom: 10px; transition: all 0.3s;">
                                        <option value="" style="color: #fff;">-- Select a reason --</option>
                                        <option value="Schedule conflict" style="color: #fff;">Schedule conflict</option>
                                        <option value="No longer needed" style="color: #fff;">No longer needed</option>
                                        <option value="Technical issues" style="color: #fff;">Technical issues</option>
                                        <option value="Service not as expected" style="color: #fff;">Service not as expected</option>
                                        <option value="other" style="color: #fff;">Other (please specify)</option>
                                    </select>
                                    
                                    <div id="other_reason_container" style="display: none;">
                                        <label for="reason" class="form-label mt-3"><i class="fas fa-pencil-alt me-2"></i>Please specify</label>
                                        <textarea class="form-control" id="reason" name="reason" rows="4" placeholder="Please explain why you're requesting a refund..." style="color: #fff;"></textarea>
                                    </div>
                                    
                                    <input type="hidden" id="final_reason" name="reason" value="">
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="dashboard.php" class="btn btn-cancel"><i class="fas fa-times me-2"></i>Cancel</a>
                                    <button type="submit" name="submit_refund" class="btn btn-request-refund" id="submit_btn"><i class="fas fa-check-circle me-2"></i>Submit Refund Request</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger animate__animated animate__fadeIn">
                                <i class="fas fa-exclamation-triangle me-2"></i>Invalid reservation or reservation not found. Please go back to the <a href="dashboard.php" class="alert-link">dashboard</a>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add subtle animations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add staggered animation delay to elements
            const animatedElements = document.querySelectorAll('.animate__fadeIn');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = (index * 0.15) + 's';
            });
            
            // Handle reason selection logic
            const reasonSelect = document.getElementById('reason_select');
            const otherReasonContainer = document.getElementById('other_reason_container');
            const reasonTextarea = document.getElementById('reason');
            const finalReasonInput = document.getElementById('final_reason');
            const submitBtn = document.getElementById('submit_btn');
            
            // Show/hide other reason textarea based on selection
            reasonSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherReasonContainer.style.display = 'block';
                    reasonTextarea.setAttribute('required', 'required');
                    finalReasonInput.value = '';
                } else {
                    otherReasonContainer.style.display = 'none';
                    reasonTextarea.removeAttribute('required');
                    finalReasonInput.value = this.value;
                }
            });
            
            // When user types in the other reason textarea, update the hidden field
            reasonTextarea.addEventListener('input', function() {
                finalReasonInput.value = this.value;
            });
            
            // Validate before submission
            document.querySelector('form').addEventListener('submit', function(e) {
                const selectedReason = reasonSelect.value;
                
                if (selectedReason === '') {
                    e.preventDefault();
                    alert('Please select a reason for your refund request.');
                    return false;
                }
                
                if (selectedReason === 'other' && reasonTextarea.value.trim() === '') {
                    e.preventDefault();
                    alert('Please specify your reason for the refund request.');
                    return false;
                }
                
                if (selectedReason === 'other') {
                    finalReasonInput.value = reasonTextarea.value;
                } else {
                    finalReasonInput.value = selectedReason;
                }
                
                return true;
            });
        });
    </script>
</body>
</html> 