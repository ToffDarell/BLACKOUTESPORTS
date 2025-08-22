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

$message = '';
$messageType = '';

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
        
        // Update refund request status
        $update_query = "UPDATE refund_requests SET 
                        refund_status = ?, 
                        refund_proof = ?, 
                        gcash_reference_number = ?, 
                        refund_date = " . ($status === 'Refunded' ? "NOW()" : "NULL") . " 
                        WHERE refund_id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sssi', $status, $refund_proof, $gcash_reference, $refund_id);
        
        if ($stmt->execute()) {
            // Update reservation status accordingly
            $res_status = $status === 'Declined' ? 'Confirmed' : ($status === 'Refunded' ? 'Refunded' : 'Refund ' . $status);
            $update_res = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
            $update_res->bind_param('si', $res_status, $reservation_id);
            $update_res->execute();
            
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
                'Refund ' . $status,
                $reservation['computer_number'],
                $reservation['reservation_date'],
                $reservation['start_time'],
                $reservation['end_time'],
                $status === 'Declined' ? "Your refund request was declined." : 
                    ($status === 'Refunded' ? "Your refund has been processed. GCash Reference: " . $gcash_reference : 
                    "Your refund request has been approved and is being processed."),
                $reservation_id
            );
            
            $message = "Refund request has been " . strtolower($status) . " successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating refund request: " . $stmt->error;
            $messageType = "danger";
        }
    }
}

// Fetch all refund requests with user and reservation details
$query = "SELECT r.*, u.user_name, u.user_email, res.computer_number, res.reservation_date, 
          res.start_time, res.end_time, res.status AS reservation_status, res.screenshot_receipt 
          FROM refund_requests r 
          JOIN users u ON r.user_id = u.user_id 
          JOIN reservations res ON r.reservation_id = res.reservation_id 
          ORDER BY r.request_date DESC";

$result = $conn->query($query);
$refund_requests = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $refund_requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Refunds | Admin</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="manage_reservations.css">
    <style>
        .refund-table th, .refund-table td {
            vertical-align: middle;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .status-declined {
            background-color: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .status-refunded {
            background-color: #cce5ff;
            color: #004085;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .refund-reason {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .refund-reason:hover {
            white-space: normal;
            word-wrap: break-word;
        }
        
        .modal-content {
            background-color: #2a2a2a;
            color: #fff;
        }
        
        .modal-header {
            border-bottom: 1px solid #444;
        }
        
        .modal-footer {
            border-top: 1px solid #444;
        }
        
        .form-control, .form-select {
            background-color: #333;
            color: #fff;
            border: 1px solid #444;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #333;
            color: #fff;
            border-color: #666;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
        }
        
        .receipt-thumbnail, .refund-proof-thumbnail {
            max-width: 100px;
            max-height: 60px;
            cursor: pointer;
        }
        
        .nav-link.active {
            background-color: #ff6b6b !important;
            color: white !important;
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
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_reservations.php">Reservations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_refunds.php">Refunds</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_tournaments.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_logout.php">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <?php if (isset($message) && !empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mt-3" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card bg-dark mt-3 mb-3">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Manage Refund Requests</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="refundsTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">All</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">Approved</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="declined-tab" data-bs-toggle="tab" data-bs-target="#declined" type="button" role="tab">Declined</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="refunded-tab" data-bs-toggle="tab" data-bs-target="#refunded" type="button" role="tab">Refunded</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="refundsTabContent">
                            <?php
                            $tab_statuses = [
                                'all' => ['Pending', 'Approved', 'Declined', 'Refunded'],
                                'pending' => ['Pending'],
                                'approved' => ['Approved'],
                                'declined' => ['Declined'],
                                'refunded' => ['Refunded']
                            ];
                            
                            foreach ($tab_statuses as $tab_id => $statuses): 
                                $is_active = $tab_id === 'all' ? ' show active' : '';
                            ?>
                            <div class="tab-pane fade<?php echo $is_active; ?>" id="<?php echo $tab_id; ?>" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover refund-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Computer</th>
                                                <th>Date & Time</th>
                                                <th>Payment Receipt</th>
                                                <th>Reason</th>
                                                <th>Request Date</th>
                                                <th>Status</th>
                                                <th>Refund Proof</th>
                                                <th>GCash Reference</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $found_records = false;
                                            foreach ($refund_requests as $refund): 
                                                if ($tab_id === 'all' || in_array($refund['refund_status'], $statuses)):
                                                    $found_records = true;
                                            ?>
                                            <tr>
                                                <td><?php echo $refund['refund_id']; ?></td>
                                                <td><?php echo htmlspecialchars($refund['user_name']) . '<br><small>' . htmlspecialchars($refund['user_email']) . '</small>'; ?></td>
                                                <td><?php echo htmlspecialchars($refund['computer_number']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($refund['reservation_date']) . '<br>'; ?>
                                                    <small><?php echo htmlspecialchars($refund['start_time'] . ' - ' . $refund['end_time']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($refund['screenshot_receipt'])): ?>
                                                        <img src="<?php echo htmlspecialchars($refund['screenshot_receipt']); ?>" class="receipt-thumbnail" alt="Payment Receipt" 
                                                             onclick="showImageModal('<?php echo htmlspecialchars($refund['screenshot_receipt']); ?>', 'Payment Receipt')">
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td class="refund-reason"><?php echo htmlspecialchars($refund['reason']); ?></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($refund['request_date'])); ?></td>
                                                <td>
                                                    <span class="status-<?php echo strtolower($refund['refund_status']); ?>">
                                                        <?php echo $refund['refund_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($refund['refund_proof'])): ?>
                                                        <img src="<?php echo htmlspecialchars($refund['refund_proof']); ?>" class="refund-proof-thumbnail" alt="Refund Proof" 
                                                             onclick="showImageModal('<?php echo htmlspecialchars($refund['refund_proof']); ?>', 'Refund Proof')">
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo !empty($refund['gcash_reference_number']) ? htmlspecialchars($refund['gcash_reference_number']) : 'N/A'; ?></td>
                                                <td>
                                                    <?php if ($refund['refund_status'] === 'Pending'): ?>
                                                        <button class="btn btn-sm btn-success mb-1" onclick="showApproveModal(<?php echo $refund['refund_id']; ?>)">Approve</button>
                                                        <button class="btn btn-sm btn-danger" onclick="showDeclineModal(<?php echo $refund['refund_id']; ?>)">Decline</button>
                                                    <?php elseif ($refund['refund_status'] === 'Approved'): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="showRefundedModal(<?php echo $refund['refund_id']; ?>)">Mark as Refunded</button>
                                                    <?php else: ?>
                                                        -
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
    </div>
    
    <!-- Modals -->
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Refund Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <p>Are you sure you want to approve this refund request?</p>
                        <p class="text-warning">Note: This will set the status to 'Approved' but you'll need to process the actual refund later.</p>
                        <input type="hidden" name="refund_id" id="approve_refund_id">
                        <input type="hidden" name="status" value="Approved">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_refund" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Decline Refund Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Are you sure you want to decline this refund request?</p>
                        <input type="hidden" name="refund_id" id="decline_refund_id">
                        <input type="hidden" name="status" value="Declined">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_refund" class="btn btn-danger">Decline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Refunded Modal -->
    <div class="modal fade" id="refundedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Refund as Processed</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="refunded_refund_id">
                        <input type="hidden" name="status" value="Refunded">
                        
                        <div class="mb-3">
                            <label for="gcash_reference" class="form-label">GCash Reference Number</label>
                            <input type="text" class="form-control" id="gcash_reference" name="gcash_reference" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="refund_proof" class="form-label">Refund Proof (Screenshot of GCash Transaction)</label>
                            <input type="file" class="form-control" id="refund_proof" name="refund_proof" accept="image/*,application/pdf" required>
                            <small class="form-text text-muted">Upload a screenshot showing the GCash refund transaction.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_refund" class="btn btn-primary">Mark as Refunded</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Image</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Full size image" style="max-width: 100%;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showApproveModal(refundId) {
            document.getElementById('approve_refund_id').value = refundId;
            var modal = new bootstrap.Modal(document.getElementById('approveModal'));
            modal.show();
        }
        
        function showDeclineModal(refundId) {
            document.getElementById('decline_refund_id').value = refundId;
            var modal = new bootstrap.Modal(document.getElementById('declineModal'));
            modal.show();
        }
        
        function showRefundedModal(refundId) {
            document.getElementById('refunded_refund_id').value = refundId;
            var modal = new bootstrap.Modal(document.getElementById('refundedModal'));
            modal.show();
        }
        
        function showImageModal(imageSrc, title) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModalTitle').textContent = title;
            var modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
    </script>
</body>
</html> 