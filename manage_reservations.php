<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("location:admin_login.php");
    exit();
}

// Include the notification function
require_once('notify_reservation_user.php');

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

// Get pending reservations count
$pending_query = "SELECT COUNT(*) as count FROM reservations WHERE status = 'Pending'";
$pending_result = $conn->query($pending_query);
$pending_count = $pending_result->fetch_assoc()['count'];

// Get pending refund requests count
$pending_refunds_query = "SELECT COUNT(*) as count FROM refund_requests WHERE refund_status = 'Pending'";
$pending_refunds_result = $conn->query($pending_refunds_query);
$pending_refunds_count = $pending_refunds_result ? $pending_refunds_result->fetch_assoc()['count'] : 0;

// Check for expired sessions that haven't been marked as completed
$current_datetime = date('Y-m-d H:i:s');
$expired_sessions_query = $conn->prepare("
    SELECT r.*, u.user_name, u.user_email 
    FROM reservations r 
    JOIN users u ON r.user_id = u.user_id 
    WHERE r.status = 'Confirmed' 
    AND CONCAT(r.reservation_date, ' ', r.end_time) < ?
");
$expired_sessions_query->bind_param('s', $current_datetime);
$expired_sessions_query->execute();
$expired_result = $expired_sessions_query->get_result();

// Process expired sessions
while ($expired_session = $expired_result->fetch_assoc()) {
    // Update the session status to completed
    $update_expired = $conn->prepare("UPDATE reservations SET status = 'Completed' WHERE reservation_id = ?");
    $update_expired->bind_param('i', $expired_session['reservation_id']);
    
    if ($update_expired->execute()) {
        // Update computer status to available
        $update_computer = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
        $update_computer->bind_param('s', $expired_session['computer_number']);
        $update_computer->execute();
        
        // No email notification sent automatically
        echo "<!-- Auto-completed expired session: " . $expired_session['reservation_id'] . " (No email sent) -->";
    }
}

// Ensure computers with refunded reservations are marked as available
$refunded_check = $conn->prepare("
    SELECT r.computer_number, r.reservation_id
    FROM reservations r
    WHERE r.status = 'Refunded' OR r.status LIKE 'Refund%Approved'
");
$refunded_check->execute();
$refunded_result = $refunded_check->get_result();

// Process refunded reservations
while ($refunded = $refunded_result->fetch_assoc()) {
    // Update computer status to available
    $update_comp = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
    $update_comp->bind_param('s', $refunded['computer_number']);
    $update_comp->execute();
    
    echo "<!-- Computer " . $refunded['computer_number'] . " (reservation " . $refunded['reservation_id'] . ") set to available on page load due to refund -->";
}

// Handle approval/rejection
if (isset($_POST['update_status'])) {
    $reservation_id = $_POST['reservation_id'];
    $status = $_POST['update_status']; 
    $computer_number = $_POST['computer_number'];
    $auto_complete = isset($_POST['auto_complete']) ? true : false;
    
    // Get decline reason if status is Declined
    $decline_reason = '';
    if ($status === 'Declined' && isset($_POST['decline_reason'])) {
        $decline_reason = $_POST['decline_reason'];
        
        // Add custom reason if provided
        if ($decline_reason === 'other' && isset($_POST['decline_custom_reason']) && !empty($_POST['decline_custom_reason'])) {
            $decline_reason = $_POST['decline_custom_reason'];
        } elseif (isset($_POST['decline_custom_reason']) && !empty($_POST['decline_custom_reason'])) {
            $decline_reason .= ": " . $_POST['decline_custom_reason'];
        }
    }

    // Debug the status value
    echo "<script>console.log('Processing reservation status update. Status: " . $status . "');</script>";
    
    // Validate status value to ensure it's one of the expected values
    if ($status !== 'Confirmed' && $status !== 'Declined' && $status !== 'Completed') {
        echo "<script>console.error('Invalid status value: " . $status . "');</script>";
    } else {
        // Update with appropriate email notification status only for manual actions, not auto-complete
        $email_status = $auto_complete ? '' : ($status === 'Confirmed' ? 'confirmed' : ($status === 'Declined' ? 'declined' : 'completed'));
        
        if ($auto_complete) {
            // For auto-complete, just update the status without changing email notification status
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
            $stmt->bind_param('si', $status, $reservation_id);
        } else {
            // For manual actions, update both status and email notification status
            if ($status === 'Declined') {
                $stmt = $conn->prepare("UPDATE reservations SET status = ?, notification_status = 'Read', email_notification_sent = ?, decline_reason = ? WHERE reservation_id = ?");
                $stmt->bind_param('sssi', $status, $email_status, $decline_reason, $reservation_id);
            } else {
                $stmt = $conn->prepare("UPDATE reservations SET status = ?, notification_status = 'Read', email_notification_sent = ? WHERE reservation_id = ?");
                $stmt->bind_param('ssi', $status, $email_status, $reservation_id);
            }
        }
        
        if ($stmt->execute()) {
            // Get user details for email notification
            $user_query = $conn->prepare("SELECT r.*, u.user_name, u.user_email 
                                         FROM reservations r 
                                         JOIN users u ON r.user_id = u.user_id
                                         WHERE r.reservation_id = ?");
            $user_query->bind_param('i', $reservation_id);
            $user_query->execute();
            $result = $user_query->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Only send email for manual actions, not auto-complete
                if (!$auto_complete) {
                    // Send email notification to the user
                    $email_sent = sendReservationStatusEmail(
                        $row['user_email'],
                        $row['user_name'],
                        $status === 'Completed' ? 'Session Ended' : $status,
                        $row['computer_number'],
                        $row['reservation_date'],
                        $row['start_time'],
                        $row['end_time'],
                        $decline_reason,
                        $reservation_id
                    );
                    
                    if ($email_sent) {
                        echo "<script>console.log('Email notification sent to user');</script>";
                        $message = "Reservation " . strtolower($status) . " successfully! Email notification sent to " . $row['user_name'] . ".";
                    } else {
                        echo "<script>console.error('Failed to send email notification');</script>";
                        $message = "Reservation " . strtolower($status) . " successfully, but failed to send email notification.";
                    }
                } else {
                    $message = "Reservation " . strtolower($status) . " automatically!";
                }
                $messageType = "success";
            } else {
                $message = "Reservation " . strtolower($status) . " successfully!";
                $messageType = "success";
            }
            
            // Update computer status if reservation is confirmed
            if ($status == 'Confirmed') {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'reserved' WHERE computer_number = ?");
                $update_computer->bind_param('s', $computer_number);
                $update_computer->execute();
            }
            // Update computer status if reservation is completed
            elseif ($status == 'Completed') {
                $update_computer = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
                $update_computer->bind_param('s', $computer_number);
                $update_computer->execute();
            }
            
            // Debug successful update
            echo "<script>console.log('Successfully updated reservation " . $reservation_id . " to " . $status . "');</script>";
        } else {
            // Debug failed update
            echo "<script>console.error('Failed to update reservation status: " . $stmt->error . "');</script>";
        }
    }
}

// Handle refund status update
if (isset($_POST['update_refund'])) {
    $refund_id = $_POST['refund_id'];
    $status = $_POST['status'];
    $gcash_reference = isset($_POST['gcash_reference']) ? $_POST['gcash_reference'] : null;
    
    // Process refund proof upload
    $refund_proof = '';
    $upload_error = false;
    
    if ($status === 'Approved' || $status === 'Refunded') {
        if (isset($_FILES['refund_proof']) && $_FILES['refund_proof']['size'] > 0) {
            $target_dir = "uploads/refunds/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES["refund_proof"]["name"], PATHINFO_EXTENSION));
            $new_filename = 'refund_' . $refund_id . '_' . date('YmdHis') . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check file size (5MB max)
            if ($_FILES["refund_proof"]["size"] > 5000000) {
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
                if (move_uploaded_file($_FILES["refund_proof"]["tmp_name"], $target_file)) {
                    $refund_proof = $target_file;
                } else {
                    $message = "Sorry, there was an error uploading your file.";
                    $messageType = "danger";
                    $upload_error = true;
                }
            }
        } elseif ($status === 'Refunded') {
            $message = "Please upload proof of refund.";
            $messageType = "danger";
            $upload_error = true;
        }
    }
    
    if (!$upload_error || $status === 'Declined') {
        // Get reservation_id for this refund
        $get_res_id = $conn->prepare("SELECT reservation_id, user_id FROM refund_requests WHERE refund_id = ?");
        $get_res_id->bind_param('i', $refund_id);
        $get_res_id->execute();
        $res_result = $get_res_id->get_result();
        $refund_data = $res_result->fetch_assoc();
        $reservation_id = $refund_data['reservation_id'];
        $user_id = $refund_data['user_id'];
        
        // Get decline reason if status is Declined
        $decline_reason = '';
        if ($status === 'Declined' && isset($_POST['refund_decline_reason'])) {
            $decline_reason = $_POST['refund_decline_reason'];
            
            // Add custom reason if provided
            if ($decline_reason === 'other' && isset($_POST['refund_decline_custom_reason']) && !empty($_POST['refund_decline_custom_reason'])) {
                $decline_reason = $_POST['refund_decline_custom_reason'];
            } elseif (isset($_POST['refund_decline_custom_reason']) && !empty($_POST['refund_decline_custom_reason'])) {
                $decline_reason .= ": " . $_POST['refund_decline_custom_reason'];
            }
        }
        
        // Update refund request status
        $update_query = "UPDATE refund_requests SET 
                        refund_status = ?, 
                        refund_proof = ?, 
                        gcash_reference_number = ?,
                        decline_reason = " . ($status === 'Declined' ? "?" : "NULL") . ",
                        refund_date = " . ($status === 'Refunded' ? "NOW()" : "NULL") . " 
                        WHERE refund_id = ?";
        
        if ($status === 'Declined') {
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ssssi', $status, $refund_proof, $gcash_reference, $decline_reason, $refund_id);
        } else {
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('sssi', $status, $refund_proof, $gcash_reference, $refund_id);
        }
        
        if ($stmt->execute()) {
            // Update reservation status accordingly
            $res_status = $status === 'Declined' ? 'Confirmed' : ($status === 'Refunded' ? 'Refunded' : 'Refund ' . $status);
            $update_res = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
            $update_res->bind_param('si', $res_status, $reservation_id);
            $update_res->execute();
            
            // If refund is approved or refunded, make the computer available
            // For declined requests, keep the computer reserved
            if ($status === 'Approved' || $status === 'Refunded') {
                // Get computer number for this reservation
                $get_comp = $conn->prepare("SELECT computer_number FROM reservations WHERE reservation_id = ?");
                $get_comp->bind_param('i', $reservation_id);
                $get_comp->execute();
                $comp_result = $get_comp->get_result();
                
                if ($comp_data = $comp_result->fetch_assoc()) {
                    $computer_number = $comp_data['computer_number'];
                    
                    // Update computer status to available
                    $update_comp = $conn->prepare("UPDATE computers SET status = 'available' WHERE computer_number = ?");
                    $update_comp->bind_param('s', $computer_number);
                    $update_comp->execute();
                    
                    // Log the computer status change
                    echo "<!-- Computer " . $computer_number . " set to available due to refund -->";
                }
            } else if ($status === 'Declined') {
                // For declined refunds, ensure the computer remains reserved
                $get_comp = $conn->prepare("SELECT computer_number FROM reservations WHERE reservation_id = ?");
                $get_comp->bind_param('i', $reservation_id);
                $get_comp->execute();
                $comp_result = $get_comp->get_result();
                
                if ($comp_data = $comp_result->fetch_assoc()) {
                    $computer_number = $comp_data['computer_number'];
                    
                    // Update computer status to reserved
                    $update_comp = $conn->prepare("UPDATE computers SET status = 'reserved' WHERE computer_number = ?");
                    $update_comp->bind_param('s', $computer_number);
                    $update_comp->execute();
                    
                    // Log the computer status change
                    echo "<!-- Computer " . $computer_number . " remains reserved due to declined refund -->";
                }
            }
            
            // Get reservation details for notification
            $res_query = $conn->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
            $res_query->bind_param('i', $reservation_id);
            $res_query->execute();
            $res_result = $res_query->get_result();
            $reservation = $res_result->fetch_assoc();
            
            // Get user details
            $user_query = $conn->prepare("SELECT user_name, user_email FROM users WHERE user_id = ?");
            $user_query->bind_param('i', $user_id);
            $user_query->execute();
            $user_result = $user_query->get_result();
            $user = $user_result->fetch_assoc();
            
            // Send notification email
            sendReservationStatusEmail(
                $user['user_email'],
                $user['user_name'],
                $status === 'Approved' ? 'Refund Approved' : 
                    ($status === 'Refunded' ? 'Refund Refunded' : 
                    ($status === 'Declined' ? 'Refund Declined' : 'Refund ' . $status)),
                $reservation['computer_number'],
                $reservation['reservation_date'],
                $reservation['start_time'],
                $reservation['end_time'],
                $status === 'Declined' ? $decline_reason : 
                    ($status === 'Refunded' ? "Your refund has been processed. GCash Reference: " . $gcash_reference : 
                    "Your refund request has been approved and is being processed."),
                $reservation_id,
                $status === 'Refunded' ? $refund_proof : null // Pass the refund proof image for refunded status
            );
            
            $message = "Refund request has been " . strtolower($status) . " successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating refund request: " . $stmt->error;
            $messageType = "danger";
        }
    }
}

// Fetch reservations with user details
try {
    $query = "SELECT r.*, 
              r.status AS reservation_status,
              r.email_notification_sent,
              u.user_id, 
              u.user_name AS username, 
              u.user_email AS email 
              FROM reservations r 
              JOIN users u ON r.user_id = u.user_id 
              ORDER BY r.reservation_date, r.start_time";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure status is properly trimmed and stored
        $row['status'] = trim($row['status']);
        $reservations[] = $row;
    }
    
    // Debug: Check the structure of the first reservation if available
    if (!empty($reservations)) {
        echo "<!-- Debug: First reservation keys: " . implode(", ", array_keys($reservations[0])) . " -->";
        echo "<!-- Debug: First reservation status: " . $reservations[0]['status'] . " -->";
        
        // Dump first reservation completely
        echo "<!-- Debug: First reservation complete data: " . json_encode($reservations[0]) . " -->";
         
        // Add debugging to check all statuses
        echo "<!-- Debugging all reservation statuses: -->";
        foreach ($reservations as $idx => $res) {
            echo "<!-- Reservation " . $idx . ": Status='" . $res['status'] . "', Date='" . $res['reservation_date'] . "', Start='" . $res['start_time'] . "', End='" . $res['end_time'] . "' -->";
        }
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Fetch all refund requests with user and reservation details
$refund_requests = [];
try {
    $refund_query = "SELECT r.*, u.user_name, u.user_email, res.computer_number, res.reservation_date, 
              res.start_time, res.end_time, res.status AS reservation_status, res.screenshot_receipt 
              FROM refund_requests r 
              JOIN users u ON r.user_id = u.user_id 
              JOIN reservations res ON r.reservation_id = res.reservation_id 
              ORDER BY r.request_date DESC";
    
    $refund_result = $conn->query($refund_query);
    if ($refund_result) {
        while ($row = $refund_result->fetch_assoc()) {
            $refund_requests[] = $row;
        }
    }
} catch (Exception $e) {
    $refund_error_message = "Refund data error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations | Admin</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="manage_reservations.css">
    
    <style>
        /* Improved spacing for refund tabs */
        #refundSubTabs .nav-item {
            margin: 0 5px;
        }
        
        #refundSubTabs .nav-link {
            padding: 10px 15px;
            font-weight: 500;
            border-radius: 6px;
        }
        
        /* Status badges without backgrounds */
        .badge.text-dark, .badge.text-success, .badge.text-danger, .badge.text-primary {
            font-size: 0.95rem;
            font-weight: 600;
            background: none;
        }
        
        /* Improved table spacing */
        .table-responsive table {
            min-width: 100%;
            table-layout: auto;
        }
        
        .reason-column {
            min-width: 180px;
        }
        
        /* Reservation status styling */
        .status-badge {
            font-weight: 600;
            padding: 0.5em 0.75em;
        }
        
        .status-pending {
            color: #f4a261;
        }
        
        .status-confirmed {
            color: #2ecc71;
        }
        
        .status-declined {
            color: #e74c3c;
        }
        
        .status-completed {
            color: #7f8c8d;
        }
        
        .status-refunded, .status-refund-approved {
            color:white;
        }
    </style>

</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="admin_dashboard.php">
                    <img src="images/blackout.jpg" alt="Blackout Esports" onerror="this.src='https://via.placeholder.com/180x50?text=BLACKOUT+ESPORTS'">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
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
                            <a class="nav-link active" href="manage_reservations.php">
                                Reservations
                                <?php if ($pending_count > 0): ?>
                                    <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_tournament.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_logout.php">Log out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mt-4">
        <div class="content-wrapper p-4 rounded-3">
            <h2 class="page-title animate__animated animate__fadeIn">Manage Reservations</h2>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger animate__animated animate__fadeIn">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php elseif (isset($message) && isset($messageType)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show animate__animated animate__fadeIn">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!isset($error_message)): ?>
                <!-- Navigation tabs -->
                <ul class="nav nav-tabs mb-4 animate__animated animate__fadeIn" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-flex align-items-center gap-2" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations" type="button" role="tab" aria-controls="reservations" aria-selected="true">
                            </i> Reservations
                            <?php if ($pending_count > 0): ?>
                                <span class="badge rounded-pill bg-danger"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-flex align-items-center gap-2" id="refunds-tab" data-bs-toggle="tab" data-bs-target="#refunds" type="button" role="tab" aria-controls="refunds" aria-selected="false">
                            </i> Refund Requests
                            <?php if ($pending_refunds_count > 0): ?>
                                <span class="badge rounded-pill bg-danger"><?php echo $pending_refunds_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="myTabContent">
                    <!-- Reservations Tab -->
                    <div class="tab-pane fade show active animate__animated animate__fadeIn" id="reservations" role="tabpanel" aria-labelledby="reservations-tab">
                        <div class="card shadow-sm border-0 animate__animated animate__fadeIn">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Active Reservations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th class="user-column">User</th>
                                                <th>Computer</th>
                                                <th class="date-column">Date</th>
                                                <th class="time-column">Time</th>
                                                <th>Payment</th>
                                                <th class="time-left-column">Time Left</th>
                                                <th>Status</th>
                                                <th>Email</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reservations as $row): ?>
                                                <tr class="<?php echo $row['notification_status'] == 'Unread' ? 'new-reservation' : ''; ?>">
                                                    <td class="user-column">
                                                        <div class="user-info">
                                                            <span class="user-name"><?php echo htmlspecialchars($row['username']); ?></span>
                                                            <span class="user-email"><?php echo htmlspecialchars($row['email']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>PC #<?php echo htmlspecialchars($row['computer_number']); ?></td>
                                                    <td class="date-column"><?php echo htmlspecialchars($row['reservation_date']); ?></td>
                                                    <td class="time-column"><?php echo htmlspecialchars($row['start_time']) . ' - ' . htmlspecialchars($row['end_time']); ?></td>
                                                    <td>
                                                        <?php if (!empty($row['screenshot_receipt'])): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger view-payment" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#paymentModal" 
                                                                    data-payment="<?php echo htmlspecialchars($row['screenshot_receipt']); ?>"
                                                                    data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                                    data-pc="<?php echo htmlspecialchars($row['computer_number']); ?>"
                                                                    data-date="<?php echo htmlspecialchars($row['reservation_date']); ?>">
                                                                </i> View Receipt
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">No Receipt</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="time-left-column">
                                                        <span class="countdown" 
                                                            data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                                            data-start="<?php echo $row['reservation_date'] . ' ' . $row['start_time']; ?>"
                                                            data-end="<?php echo $row['reservation_date'] . ' ' . $row['end_time']; ?>"
                                                            data-reservation-id="<?php echo $row['reservation_id']; ?>"
                                                            data-computer-number="<?php echo $row['computer_number']; ?>">
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['status'] === 'Completed'): ?>
                                                            <span class="status-badge" style="background-color: #6c757d; color: white;">
                                                                Session Ended
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-badge status-<?php echo strtolower(trim($row['status'])); ?>" 
                                                                  data-original-status="<?php echo htmlspecialchars($row['status']); ?>">
                                                                <?php echo htmlspecialchars($row['status']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        // Email notification status
                                                        $email_status = isset($row['email_notification_sent']) ? $row['email_notification_sent'] : 'no';
                                                        $email_badge_class = 'bg-secondary';
                                                        $email_icon = 'fa-envelope';
                                                        
                                                        if ($email_status == 'no') {
                                                            $email_badge_class = 'bg-secondary';
                                                            $email_tooltip = 'No email sent';
                                                        } elseif ($email_status == 'confirmed') {
                                                            $email_badge_class = 'bg-success';
                                                            $email_tooltip = 'Confirmation email sent';
                                                            $email_icon = 'fa-envelope-open-text';
                                                        } elseif ($email_status == 'declined') {
                                                            $email_badge_class = 'bg-danger';
                                                            $email_tooltip = 'Decline email sent';
                                                            $email_icon = 'fa-envelope-open-text';
                                                        } elseif ($email_status == 'expired') {
                                                            $email_badge_class = 'bg-warning';
                                                            $email_tooltip = 'Expiration email sent';
                                                            $email_icon = 'fa-envelope-open-text';
                                                        } elseif ($email_status == 'completed') {
                                                            $email_badge_class = 'bg-info';
                                                            $email_tooltip = 'Completion email sent';
                                                            $email_icon = 'fa-envelope-open-text';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $email_badge_class; ?>" data-bs-toggle="tooltip" title="<?php echo $email_tooltip; ?>">
                                                            <i class="fas <?php echo $email_icon; ?>"></i>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['status'] == 'Pending'): ?>
                                                        <div class="d-flex gap-2">
                                                            <form action="" method="POST" class="d-inline">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                                                                <input type="hidden" name="computer_number" value="<?php echo $row['computer_number']; ?>">
                                                                <button type="submit" name="update_status" value="Confirmed" class="btn btn-confirm">
                                                                    <i class="fas fa-check"></i> Confirm
                                                                </button>
                                                            </form>
                                                            
                                                            <button type="button" class="btn btn-decline decline-button" 
                                                                   data-reservation-id="<?php echo $row['reservation_id']; ?>" 
                                                                   data-computer-number="<?php echo $row['computer_number']; ?>">
                                                                <i class="fas fa-times"></i> Decline
                                                            </button>
                                                        </div>
                                                        <?php elseif ($row['status'] == 'Confirmed'): ?>
                                                            <?php
                                                            // Check if the session is currently active
                                                            $now = new DateTime();
                                                            
                                                            // Debug the raw date and time values
                                                            echo "<!-- Raw date: " . $row['reservation_date'] . " -->";
                                                            echo "<!-- Raw start time: " . $row['start_time'] . " -->";
                                                            echo "<!-- Raw end time: " . $row['end_time'] . " -->";
                                                            
                                                            // Create a button for confirmed reservations
                                                            ?>
                                                            <form action="" method="POST" class="d-inline">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                                                                <input type="hidden" name="computer_number" value="<?php echo $row['computer_number']; ?>">
                                                                <button type="submit" name="update_status" value="Completed" class="btn btn-danger">
                                                                    <i class="fas fa-stop-circle "></i> End Session
                                                                </button>
                                                            </form>
                                                        <?php elseif ($row['status'] == 'Declined'): ?>
                                                            <span class="text-danger"><i class="fas fa-times-circle"></i> Rejected</span>
                                                        <?php elseif ($row['status'] == 'Completed'): ?>
                                                            <span class="text-info"><i class="fas fa-flag-checkered"></i> Completed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Refund Requests Tab -->
                    <div class="tab-pane fade animate__animated animate__fadeIn" id="refunds" role="tabpanel" aria-labelledby="refunds-tab">
                        <div class="card shadow-sm border-0 animate__animated animate__fadeIn">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Refund Requests</h5>
                            </div>
                            <div class="card-body">
                                <!-- Refund requests sub-tabs -->
                                <ul class="nav nav-pills nav-fill mb-4 animate__animated animate__fadeIn" id="refundSubTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link w-100" id="all-refunds-tab" data-bs-toggle="pill" data-bs-target="#all-refunds" type="button" role="tab">
                                            <i class="fas fa-list me-1"></i> All
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link w-100" id="pending-refunds-tab" data-bs-toggle="pill" data-bs-target="#pending-refunds" type="button" role="tab">
                                            <i class="fas fa-clock me-1"></i> Pending
                                            <?php if ($pending_refunds_count > 0): ?>
                                                <span class="badge rounded-pill bg-danger ms-1"><?php echo $pending_refunds_count; ?></span>
                                            <?php endif; ?>
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link w-100" id="approved-refunds-tab" data-bs-toggle="pill" data-bs-target="#approved-refunds" type="button" role="tab">
                                            <i class="fas fa-check me-1"></i> Approved
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link w-100" id="declined-refunds-tab" data-bs-toggle="pill" data-bs-target="#declined-refunds" type="button" role="tab">
                                            <i class="fas fa-times me-1"></i> Declined
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link w-100" id="refunded-refunds-tab" data-bs-toggle="pill" data-bs-target="#refunded-refunds" type="button" role="tab">
                                            <i class="fas fa-check-circle me-1"></i> Refunded
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="refundSubTabsContent">
                                    <?php
                                    $refund_statuses = [
                                        'all-refunds' => ['Pending', 'Approved', 'Declined', 'Refunded'],
                                        'pending-refunds' => ['Pending'],
                                        'approved-refunds' => ['Approved'],
                                        'declined-refunds' => ['Declined'],
                                        'refunded-refunds' => ['Refunded']
                                    ];
                                    
                                    foreach ($refund_statuses as $tab_id => $statuses): 
                                        $is_active = $tab_id === 'all-refunds' ? ' show active' : '';
                                    ?>
                                    <div class="tab-pane fade<?php echo $is_active; ?>" id="<?php echo $tab_id; ?>" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th class="user-column">User</th>
                                                        <th>Computer</th>
                                                        <th class="date-column">Date & Time</th>
                                                        <th class="receipt-column">Payment Receipt</th>
                                                        <th class="reason-column">Reason</th>
                                                        <th class="request-date-column">Request Date</th>
                                                        <th>Status</th>
                                                        <th class="proof-column">Refund Proof</th>
                                                        <th class="gcash-column">GCash Reference</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $found_records = false;
                                                    foreach ($refund_requests as $refund): 
                                                        if ($tab_id === 'all-refunds' || in_array($refund['refund_status'], $statuses)):
                                                            $found_records = true;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $refund['refund_id']; ?></td>
                                                        <td class="user-column">
                                                            <div class="user-info">
                                                                <span class="user-name"><?php echo htmlspecialchars($refund['user_name']); ?></span>
                                                                <span class="user-email"><?php echo htmlspecialchars($refund['user_email']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>PC #<?php echo htmlspecialchars($refund['computer_number']); ?></td>
                                                        <td class="date-column">
                                                            <?php echo htmlspecialchars($refund['reservation_date']) . '<br>'; ?>
                                                            <small><?php echo htmlspecialchars($refund['start_time'] . ' - ' . $refund['end_time']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($refund['screenshot_receipt'])): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-danger view-payment" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#paymentModal" 
                                                                        data-payment="<?php echo htmlspecialchars($refund['screenshot_receipt']); ?>"
                                                                        data-username="<?php echo htmlspecialchars($refund['user_name']); ?>"
                                                                        data-pc="<?php echo htmlspecialchars($refund['computer_number']); ?>"
                                                                        data-date="<?php echo htmlspecialchars($refund['reservation_date']); ?>">
                                                                   </i> View Receipt
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">No Receipt</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="reason-column text-truncate" style="max-width: 150px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($refund['reason']); ?>">
                                                            <?php echo htmlspecialchars($refund['reason']); ?>
                                                        </td>
                                                        <td class="request-date-column"><?php echo date('M d, Y g:i A', strtotime($refund['request_date'])); ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch (strtolower($refund['refund_status'])) {
                                                                case 'pending':
                                                                    $status_class = 'text-dark';
                                                                    break;
                                                                case 'approved':
                                                                    $status_class = 'text-success';
                                                                    break;
                                                                case 'declined':
                                                                    $status_class = 'text-danger';
                                                                    break;
                                                                case 'refunded':
                                                                    $status_class = 'text-primary';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo $refund['refund_status']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="proof-column">
                                                            <?php if (!empty($refund['refund_proof'])): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-danger view-payment" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#paymentModal" 
                                                                        data-payment="<?php echo htmlspecialchars($refund['refund_proof']); ?>"
                                                                        data-username="<?php echo htmlspecialchars($refund['user_name']); ?>"
                                                                        data-pc="<?php echo htmlspecialchars($refund['computer_number']); ?>"
                                                                        data-date="<?php echo htmlspecialchars($refund['reservation_date']); ?>">
                                                                    </i> View Proof
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="gcash-column"><?php echo !empty($refund['gcash_reference_number']) ? htmlspecialchars($refund['gcash_reference_number']) : 'N/A'; ?></td>
                                                        <td>
                                                            <?php if ($refund['refund_status'] === 'Pending'): ?>
                                                                <div class="d-flex gap-2">
                                                                    <button class="btn btn-sm btn-danger" onclick="showApproveRefundModal(<?php echo $refund['refund_id']; ?>)">
                                                                    </i> Approve
                                                                    </button>
                                                                    <button class="btn btn-sm btn-danger" onclick="showDeclineRefundModal(<?php echo $refund['refund_id']; ?>)">
                                                                    </i> Decline
                                                                    </button>
                                                                </div>
                                                            <?php elseif ($refund['refund_status'] === 'Approved'): ?>
                                                                <button class="btn btn-sm btn-danger" onclick="showRefundedModal(<?php echo $refund['refund_id']; ?>)">
                                                                    </i> Mark as Refunded
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    
                                                    if (!$found_records):
                                                    ?>
                                                    <tr>
                                                        <td colspan="11" class="text-center">No refund requests found.</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Receipt Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header bg-dark text-white border-bottom border-secondary">
                    <h5 class="modal-title text-white" id="paymentModalLabel"><i class="fas fa-receipt me-2"></i>Payment Receipt</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-dark text-white">
                    <div class="payment-details mb-4 p-3 bg-black rounded border border-secondary">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1 text-white"><strong class="text-white"><i class="fas fa-user me-2"></i>User:</strong></p>
                                <p class="ms-4 text-white" id="payment-username"></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1 text-white"><strong class="text-white"><i class="fas fa-desktop me-2"></i>Computer:</strong></p>
                                <p class="ms-4 text-white">PC #<span class="text-white" id="payment-pc"></span></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1 text-white"><strong class="text-white"><i class="fas fa-calendar-alt me-2"></i>Date:</strong></p>
                                <p class="ms-4 text-white"><span class="text-white" id="payment-date"></span></p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <div id="payment-container" class="mb-3 p-3 border border-secondary rounded bg-black">
                            <!-- Payment proof will be displayed here -->
                            <div class="spinner-border text-light" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-dark border-top border-secondary">
                    <a href="#" class="btn btn-primary" id="download-payment" download>
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Reason Modal -->
    <div class="modal fade" id="declineReasonModal" tabindex="-1" aria-labelledby="declineReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="declineReasonModalLabel"><i class="fas fa-times-circle me-2"></i>Decline Reservation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" id="declineForm">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>Please provide a reason for declining this reservation:
                        </div>
                        <input type="hidden" name="reservation_id" id="decline_reservation_id">
                        <input type="hidden" name="computer_number" id="decline_computer_number">
                        <input type="hidden" name="update_status" value="Declined">
                        <div class="mb-3">
                            <label for="decline_reason" class="form-label">
                                <i class="fas fa-list-ul me-2"></i>Reason
                            </label>
                            <select class="form-select" id="decline_reason" name="decline_reason">
                                <option value="">-- Select a reason --</option>
                                <option value="Incomplete payment information">Incomplete payment information</option>
                                <option value="Computer unavailable due to maintenance">Computer unavailable due to maintenance</option>
                                <option value="Schedule conflict with tournament/event">Schedule conflict with tournament/event</option>
                                <option value="Invalid reservation details">Invalid reservation details</option>
                                <option value="other">Other (specify below)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="decline_custom_reason" class="form-label">
                                <i class="fas fa-comment-alt me-2"></i>Additional Comments
                            </label>
                            <textarea class="form-control" id="decline_custom_reason" name="decline_custom_reason" rows="3" placeholder="Please provide more details about the reason for declining..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i>Decline Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Refund Action Modals -->
    <!-- Approve Refund Modal -->
    <div class="modal fade" id="approveRefundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Approve Refund Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will approve the refund request but you will need to process the actual refund later.
                        </div>
                        <p>Are you sure you want to approve this refund request?</p>
                        <input type="hidden" name="refund_id" id="approve_refund_id">
                        <input type="hidden" name="status" value="Approved">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="update_refund" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Approve Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Decline Refund Modal -->
    <div class="modal fade" id="declineRefundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Decline Refund Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action will permanently decline the refund request and cannot be undone.
                        </div>
                        <p>Are you sure you want to decline this refund request?</p>
                        <input type="hidden" name="refund_id" id="decline_refund_id">
                        <input type="hidden" name="status" value="Declined">
                        
                        <div class="mb-3">
                            <label for="refund_decline_reason" class="form-label">
                                <i class="fas fa-list-ul me-2"></i>Reason for Declining
                            </label>
                            <select class="form-select" id="refund_decline_reason" name="refund_decline_reason" required>
                                <option value="">-- Select a reason --</option>
                                <option value="Invalid refund request">Invalid refund request</option>
                                <option value="Past cancellation window">Past cancellation window</option>
                                <option value="Technical issues">Technical issues</option>
                                <option value="GCash service unavailable">GCash service unavailable</option>
                                <option value="Refund policy violation">Refund policy violation</option>
                                <option value="other">Other (specify below)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="refund_decline_custom_reason" class="form-label">
                                <i class="fas fa-comment-alt me-2"></i>Additional Comments
                            </label>
                            <textarea class="form-control" id="refund_decline_custom_reason" name="refund_decline_custom_reason" rows="3" placeholder="Please provide more details about why this refund request is being declined..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="update_refund" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i>Decline Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark as Refunded Modal -->
    <div class="modal fade" id="refundedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Mark Refund as Processed</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Please provide the GCash reference number and upload proof of the refund transaction.
                        </div>
                        <input type="hidden" name="refund_id" id="refunded_refund_id">
                        <input type="hidden" name="status" value="Refunded">
                        
                        <div class="mb-3">
                            <label for="gcash_reference" class="form-label"><i class="fas fa-hashtag me-2"></i>GCash Reference Number</label>
                            <input type="text" class="form-control" id="gcash_reference" name="gcash_reference" required 
                                   placeholder="e.g. 9012345678">
                            <div class="form-text">Enter the transaction reference number from GCash</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="refund_proof" class="form-label"><i class="fas fa-file-image me-2"></i>Refund Proof</label>
                            <input type="file" class="form-control" id="refund_proof" name="refund_proof" accept="image/*,application/pdf" required>
                            <div class="form-text">Upload a screenshot showing the GCash refund transaction (JPG, PNG or PDF only)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="update_refund" class="btn btn-danger">
                            <i class="fas fa-check-circle me-2"></i>Mark as Refunded
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Staggered animation for elements on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add entrance animations with staggered delay
            setTimeout(() => {
                document.querySelectorAll('.card').forEach((card, index) => {
                    card.classList.add('animate__animated', 'animate__fadeInUp');
                    card.style.animationDelay = (index * 0.15) + 's';
                    card.style.opacity = '0';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                    }, 100);
                });
            }, 300);
        });
    
        // Initialize Bootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize tabs with animation
            const tabLinks = document.querySelectorAll('.nav-link');
            tabLinks.forEach(tabLink => {
                tabLink.addEventListener('click', function() {
                    // Add a slight animation when changing tabs
                    const targetId = this.getAttribute('data-bs-target');
                    const targetPane = document.querySelector(targetId);
                    if (targetPane) {
                        targetPane.style.opacity = '0';
                        setTimeout(() => {
                            targetPane.style.transition = 'opacity 0.3s ease-in-out';
                            targetPane.style.opacity = '1';
                        }, 150);
                    }
                });
            });
            
            // Add animation to status badges
            const statusBadges = document.querySelectorAll('.status-badge');
            statusBadges.forEach(badge => {
                badge.style.transition = 'transform 0.2s ease-in-out';
                badge.addEventListener('mouseover', function() {
                    this.style.transform = 'scale(1.1)';
                });
                badge.addEventListener('mouseout', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Add row hover effect
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseover', function() {
                    this.style.transition = 'background-color 0.2s ease-in-out';
                    this.style.backgroundColor = 'rgba(0, 123, 255, 0.05)';
                });
                row.addEventListener('mouseout', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        // Enhanced payment modal handling
        document.addEventListener('DOMContentLoaded', function() {
            const paymentModal = document.getElementById('paymentModal');
            if (paymentModal) {
                paymentModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const paymentPath = button.getAttribute('data-payment');
                    const username = button.getAttribute('data-username');
                    const pc = button.getAttribute('data-pc');
                    const date = button.getAttribute('data-date');
                    
                    // Set modal content
                    document.getElementById('payment-username').textContent = username;
                    document.getElementById('payment-pc').textContent = pc;
                    document.getElementById('payment-date').textContent = date;
                    
                    // Set download link
                    const downloadLink = document.getElementById('download-payment');
                    downloadLink.href = paymentPath;
                    
                    // Display image or PDF
                    const container = document.getElementById('payment-container');
                    // Clear previous content but show loading spinner
                    container.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>';
                    
                    // Make sure paymentPath is not empty
                    if (!paymentPath) {
                        container.innerHTML = '<div class="alert alert-warning text-white"><i class="fas fa-exclamation-triangle me-2"></i>Receipt path is invalid or empty.</div>';
                        return;
                    }
                    
                    try {
                        const fileExtension = paymentPath.split('.').pop().toLowerCase();
                        
                        // Delay slightly to show loading animation
                        setTimeout(() => {
                            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                                // It's an image
                                const img = document.createElement('img');
                                img.src = paymentPath;
                                img.className = 'img-fluid';
                                img.style.maxHeight = '500px';
                                img.alt = 'Payment Proof';
                                img.onerror = function() {
                                    container.innerHTML = `<div class="alert alert-danger text-white">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Could not load image. File might not exist at path: ${paymentPath}
                                    </div>`;
                                };
                                
                                // Clear the container and add the image
                                container.innerHTML = '';
                                container.appendChild(img);
                                
                                // Add zoom functionality
                                img.style.cursor = 'zoom-in';
                                img.addEventListener('click', function() {
                                    if (this.style.maxHeight === '500px') {
                                        this.style.maxHeight = '100%';
                                        this.style.cursor = 'zoom-out';
                                    } else {
                                        this.style.maxHeight = '500px';
                                        this.style.cursor = 'zoom-in';
                                    }
                                });
                                
                                // Log to console for debugging
                                console.log("Loading image from: " + paymentPath);
                            } else if (fileExtension === 'pdf') {
                                // It's a PDF
                                const embed = document.createElement('embed');
                                embed.src = paymentPath;
                                embed.type = 'application/pdf';
                                embed.width = '100%';
                                embed.height = '500px';
                                
                                // Clear the container and add the PDF
                                container.innerHTML = '';
                                container.appendChild(embed);
                                
                                // Log to console for debugging
                                console.log("Loading PDF from: " + paymentPath);
                            } else {
                                container.innerHTML = '<div class="alert alert-warning text-white"><i class="fas fa-file-alt me-2"></i>Cannot preview this file type. Please download to view.</div>';
                            }
                        }, 500); // Small delay to show loading spinner
                    } catch (e) {
                        container.innerHTML = `<div class="alert alert-danger text-white"><i class="fas fa-exclamation-circle me-2"></i>Error displaying file: ${e.message}</div>`;
                        console.error("Error displaying payment proof:", e);
                    }
                });
            }
        });

        // Enhanced countdown timer
        function updateCountdowns() {
            const countdowns = document.querySelectorAll('.countdown');
            const now = new Date();

            countdowns.forEach(element => {
                const status = element.dataset.status.trim();
                const startTime = new Date(element.dataset.start);
                const endTime = new Date(element.dataset.end);

                if (status !== 'Confirmed') {
                    // Show appropriate message based on status
                    if (status === 'Pending') {
                        element.innerHTML = '<i class="fas fa-hourglass-half me-1"></i> Waiting for approval';
                        element.style.color = '#f4a261'; // Orange
                    } else if (status === 'Declined') {
                        element.innerHTML = '<i class="fas fa-ban me-1"></i> Reservation declined';
                        element.style.color = '#e63946'; // Red
                    } else if (status === 'Completed') {
                        element.innerHTML = '<i class="fas fa-check-circle me-1"></i> Session ended';
                        element.style.color = '#7f8c8d'; // Gray
                    } else {
                        // Any other status just shows the status name
                        element.innerHTML = '<i class="fas fa-info-circle me-1"></i> ' + status;
                        element.style.color = '#7f8c8d'; // Gray
                    }
                    return;
                }

                if (now < startTime) {
                    // Countdown to start (only for confirmed reservations)
                    const diff = startTime - now;
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    // Format hours, minutes, and seconds to have leading zeros if needed
                    const formattedHours = hours.toString().padStart(2, '0');
                    const formattedMinutes = minutes.toString().padStart(2, '0');
                    const formattedSeconds = seconds.toString().padStart(2, '0');
                    
                    element.innerHTML = `<i class="fas fa-clock me-1"></i> Starts in: 
                        <span class="time-digit">${formattedHours}</span><span class="time-separator">:</span>
                        <span class="time-digit">${formattedMinutes}</span><span class="time-separator">:</span>
                        <span class="time-digit">${formattedSeconds}</span>`;
                    element.style.color = '#2a9d8f'; // Teal
                } else if (now >= startTime && now < endTime) {
                    // Active session countdown
                    const diff = endTime - now;
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    // Format hours, minutes, and seconds to have leading zeros if needed
                    const formattedHours = hours.toString().padStart(2, '0');
                    const formattedMinutes = minutes.toString().padStart(2, '0');
                    const formattedSeconds = seconds.toString().padStart(2, '0');
                    
                    // Add visual indicator when time is running low (less than 15 minutes)
                    if (diff < 15 * 60 * 1000) {
                        element.innerHTML = `<i class="fas fa-exclamation-circle me-1 text-danger"></i> Time left: 
                            <span class="time-digit text-danger">${formattedHours}</span><span class="time-separator">:</span>
                            <span class="time-digit text-danger">${formattedMinutes}</span><span class="time-separator">:</span>
                            <span class="time-digit text-danger">${formattedSeconds}</span>`;
                        
                        // Add blinking effect for low time
                        element.style.animation = 'blink 1s linear infinite';
                    } else {
                        element.innerHTML = `<i class="fas fa-hourglass-half me-1"></i> Time left: 
                            <span class="time-digit">${formattedHours}</span><span class="time-separator">:</span>
                            <span class="time-digit">${formattedMinutes}</span><span class="time-separator">:</span>
                            <span class="time-digit">${formattedSeconds}</span>`;
                        element.style.color = '#2ecc71'; // Green
                        element.style.animation = 'none';
                    }
                } else {
                    // Session ended
                    element.innerHTML = '<i class="fas fa-history me-1"></i> Session ended';
                    element.style.color = '#7f8c8d'; // Gray
                }
            });
        }

        // Add blinking animation for low time
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes blink {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
        `;
        document.head.appendChild(style);

        // Update countdown every second
        setInterval(updateCountdowns, 1000);
        // Initial update
        updateCountdowns();

        // Function to check for ended sessions
        function checkForEndedSessions() {
            const countdowns = document.querySelectorAll('.countdown');
            const now = new Date();
            
            countdowns.forEach(element => {
                const status = element.dataset.status.trim();
                const reservationId = element.dataset.reservationId;
                const computerNumber = element.dataset.computerNumber;
                const endTime = new Date(element.dataset.end);
                
                // If session just ended (within the last few seconds)
                if (status === 'Confirmed' && 
                    now >= endTime && 
                    (now - endTime) < 60000 && // Within 1 minute of ending to ensure we don't miss any
                    !element.dataset.endNotificationSent) {
                    
                    console.log('Detected session that needs to be ended: ' + reservationId);
                    
                    // Mark as notified to prevent multiple notifications
                    element.dataset.endNotificationSent = 'true';
                    
                    // Show a notification on the page
                    const notifContainer = document.createElement('div');
                    notifContainer.className = 'position-fixed bottom-0 end-0 p-3';
                    notifContainer.style.zIndex = '5';
                    
                    notifContainer.innerHTML = `
                        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="toast-header bg-info text-white">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong class="me-auto">Session Complete</strong>
                                <small>Just now</small>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                Reservation #${reservationId} has ended automatically.
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(notifContainer);
                    
                    // Create a form to submit directly - more reliable than fetch
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.pathname;
                    form.style.display = 'none';
                    
                    // Add the necessary fields
                    const fields = {
                        'reservation_id': reservationId,
                        'update_status': 'Completed',
                        'computer_number': computerNumber,
                        'auto_complete': 'true'
                        // 'send_email_notification' removed to prevent auto email sending
                    };
                    
                    // Create input fields and add to form
                    for (const [name, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    }
                    
                    // Add form to document and submit it
                    document.body.appendChild(form);
                    
                    console.log('Submitting form to complete session: ' + reservationId);
                    
                    // Submit after a short delay to ensure notification shows
                    setTimeout(() => {
                        form.submit();
                    }, 1000);
                }
            });
        }
        
        // Run immediately on page load
        checkForEndedSessions();
        
        // Check for ended sessions frequently (every 15 seconds)
        setInterval(checkForEndedSessions, 15000);

        // Handle decline button clicks with improved feedback
        document.addEventListener('DOMContentLoaded', function() {
            // Get all decline buttons
            const declineButtons = document.querySelectorAll('.decline-button');
            
            declineButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Add a subtle animation to button click
                    this.classList.add('animate__animated', 'animate__pulse');
                    
                    // Get the reservation ID and computer number from the data attributes
                    const reservationId = this.getAttribute('data-reservation-id');
                    const computerNumber = this.getAttribute('data-computer-number');
                    
                    // Set values in the modal form
                    document.getElementById('decline_reservation_id').value = reservationId;
                    document.getElementById('decline_computer_number').value = computerNumber;
                    
                    // Show the modal
                    const declineModal = new bootstrap.Modal(document.getElementById('declineReasonModal'));
                    declineModal.show();
                });
            });
            
            // Add validation to the decline form
            const declineForm = document.getElementById('declineForm');
            if (declineForm) {
                declineForm.addEventListener('submit', function(e) {
                    const reasonSelect = document.getElementById('decline_reason');
                    const customReason = document.getElementById('decline_custom_reason');
                    
                    if (reasonSelect.value === '') {
                        e.preventDefault();
                        alert('Please select a reason for declining this reservation.');
                    } else if (reasonSelect.value === 'other' && customReason.value.trim() === '') {
                        e.preventDefault();
                        alert('Please provide details for your "Other" reason.');
                    }
                });
            }
            
            // Add validation to the refund decline form
            const refundDeclineForm = document.getElementById('declineRefundModal').querySelector('form');
            if (refundDeclineForm) {
                refundDeclineForm.addEventListener('submit', function(e) {
                    const reasonSelect = document.getElementById('refund_decline_reason');
                    const customReason = document.getElementById('refund_decline_custom_reason');
                    
                    if (reasonSelect.value === '') {
                        e.preventDefault();
                        alert('Please select a reason for declining this refund request.');
                    } else if (reasonSelect.value === 'other' && customReason.value.trim() === '') {
                        e.preventDefault();
                        alert('Please provide details for your "Other" reason.');
                    }
                });
            }
        });

        // Add functions for refund modals
        function showApproveRefundModal(refundId) {
            document.getElementById('approve_refund_id').value = refundId;
            var modal = new bootstrap.Modal(document.getElementById('approveRefundModal'));
            modal.show();
        }
        
        function showDeclineRefundModal(refundId) {
            document.getElementById('decline_refund_id').value = refundId;
            var modal = new bootstrap.Modal(document.getElementById('declineRefundModal'));
            modal.show();
        }
        
        function showRefundedModal(refundId) {
            document.getElementById('refunded_refund_id').value = refundId;
            var modal = new bootstrap.Modal(document.getElementById('refundedModal'));
            modal.show();
        }
    </script>
</body>
</html>