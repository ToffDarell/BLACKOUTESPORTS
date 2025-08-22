<?php
// Start session
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

// Fetch admin details
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT admin_name, admin_email FROM admins WHERE admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$stmt->bind_result($admin_name, $admin_email);
$stmt->fetch();
$stmt->close();

// Fetch total reservations
$query = "SELECT COUNT(reservation_id) AS total_reservations FROM reservations";
$result = $conn->query($query);

if ($result === false) {
    error_log("Query failed: " . $conn->error);
    $total_reservations = 0; // Default value in case of error
} else {
    $total_reservations = $result->fetch_assoc()['total_reservations'];
}

// Fetch available vs reserved computers
$query = "SELECT COUNT(computer_id) AS total_computers FROM computers";
$result = $conn->query($query);

if ($result === false) {
    error_log("Query failed: " . $conn->error);
    $total_computers = 0; // Default value in case of error
} else {
    $total_computers = $result->fetch_assoc()['total_computers'];
}

// Count currently active reservations
$query = "SELECT COUNT(*) AS reserved_computers FROM computers WHERE status = 'reserved'";
$result = $conn->query($query);

if ($result === false) {
    error_log("Query failed: " . $conn->error);
    $reserved_computers = 0; // Default value in case of error
} else {
    $reserved_computers = $result->fetch_assoc()['reserved_computers'];
}

// Fetch registered users
$query = "SELECT COUNT(user_id) AS total_users FROM users";
$result = $conn->query($query);

if ($result === false) {
    error_log("Query failed: " . $conn->error);
    $total_users = 0; // Default value in case of error
} else {
    $total_users = $result->fetch_assoc()['total_users'];
}

// Fetch reservation schedule
$query = "SELECT reservation_id, user_id, computer_id, reservation_date, reservation_time FROM reservations WHERE reservation_date = CURDATE() OR reservation_date = CURDATE() + INTERVAL 1 WEEK";
$result = $conn->query($query);

if ($result === false) {
    error_log("Query failed: " . $conn->error);
    $reservation_schedule = []; // Default value in case of error
} else {
    $reservation_schedule = $result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="pricing.css">
    
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
                            <a class="nav-link active" href="admin_dashboard.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_reservations.php">Reservations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_tournament.php">Tournaments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_logout.php">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Advisory Button -->
    <div class="advisory-btn" id="advisoryBtn">
        <i class="fas fa-bullhorn"></i>
    </div>

    <!-- Advisory Modal -->
    <div class="modal fade" id="advisoryModal" tabindex="-1" aria-labelledby="advisoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-danger">
                    <h5 class="modal-title" id="advisoryModalLabel">System Advisory Management</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <ul class="nav nav-tabs" id="advisoryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="newAdvisory-tab" data-bs-toggle="tab" data-bs-target="#newAdvisory" type="button" role="tab" aria-controls="newAdvisory" aria-selected="true">New Advisory</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manageAdvisories-tab" data-bs-toggle="tab" data-bs-target="#manageAdvisories" type="button" role="tab" aria-controls="manageAdvisories" aria-selected="false">Manage Advisories</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="advisoryTabsContent">
                    <!-- New Advisory Tab -->
                    <div class="tab-pane fade show active" id="newAdvisory" role="tabpanel" aria-labelledby="newAdvisory-tab">
                        <form id="advisoryForm" method="post" action="post_advisory.php">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="advisoryMessage" class="form-label">Advisory Message</label>
                                    <textarea class="form-control bg-dark text-white" id="advisoryMessage" name="message" rows="3" placeholder="Enter advisory message (e.g., 'Shop Closed', 'Under Maintenance')" required></textarea>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="advisoryStatus" name="status" value="active">
                                    <label class="form-check-label" for="advisoryStatus">Make Advisory Active</label>
                                </div>
                                <div class="text-muted small mt-2">
                                    <i class="fas fa-info-circle"></i> Only one advisory can be active at a time. Activating a new advisory will deactivate any currently active advisory.
                                </div>
                            </div>
                            <div class="modal-footer border-danger">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Post Advisory</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Manage Advisories Tab -->
                    <div class="tab-pane fade" id="manageAdvisories" role="tabpanel" aria-labelledby="manageAdvisories-tab">
                        <div class="modal-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">All Advisories</h6>
                                <button id="refreshAdvisories" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover" id="advisoriesTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Message</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="advisoriesList">
                                        <!-- Advisory list will be loaded via AJAX -->
                                        <tr>
                                            <td colspan="5" class="text-center">Loading advisories...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Advisory Modal -->
    <div class="modal fade" id="editAdvisoryModal" tabindex="-1" aria-labelledby="editAdvisoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-danger">
                    <h5 class="modal-title" id="editAdvisoryModalLabel">Edit Advisory</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAdvisoryForm" method="post" action="update_advisory.php">
                    <div class="modal-body">
                        <input type="hidden" id="editAdvisoryId" name="id">
                        <div class="mb-3">
                            <label for="editAdvisoryMessage" class="form-label">Advisory Message</label>
                            <textarea class="form-control bg-dark text-white" id="editAdvisoryMessage" name="message" rows="3" required></textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editAdvisoryStatus" name="status" value="active">
                            <label class="form-check-label" for="editAdvisoryStatus">Make Advisory Active</label>
                        </div>
                    </div>
                    <div class="modal-footer border-danger">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Update Advisory</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Dashboard Section -->
    <section class="pricing-section">
        <div class="container">
            <?php if (isset($_SESSION['advisory_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <?php echo $_SESSION['advisory_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['advisory_message']); ?>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-12">
                    <div class="pricing-header animate__animated animate__fadeIn">
                        <h1>ADMIN DASHBOARD</h1>
                        <p>Welcome back, <?php echo $admin_name; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Overview Cards -->
            <div class="row mt-4">
                <div class="col-md-3 mb-4 animate__animated animate__fadeIn">
                    <div class="pricing-card text-center">
                        <div class="text-danger mb-3">
                            <i class="fas fa-users" style="font-size: 2.5rem;"></i>
                        </div>
                        <h2 style="font-size: 2rem;"><?php echo $total_users; ?></h2>
                        <p class="mb-0">Total Users</p>
                        <div class="mt-3">
                            <a href="manage_users.php" class="btn btn-outline-danger btn-sm">Manage Users</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4 animate__animated animate__fadeIn">
                    <div class="pricing-card text-center">
                        <div class="text-danger mb-3">
                            <i class="fas fa-desktop" style="font-size: 2.5rem;"></i>
                        </div>
                        <h2 style="font-size: 2rem;"><?php echo $total_computers; ?></h2>
                        <p class="mb-0">Total Computers</p>
                        <div class="mt-3">
                            <a href="manage_computers.php" class="btn btn-outline-danger btn-sm">Manage Computers</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4 animate__animated animate__fadeIn">
                    <div class="pricing-card text-center">
                        <div class="text-danger mb-3">
                            <i class="fas fa-calendar-check" style="font-size: 2.5rem;"></i>
                        </div>
                        <h2 style="font-size: 2rem;"><?php echo $total_reservations; ?></h2>
                        <p class="mb-0">Total Reservations</p>
                        <div class="mt-3">
                            <a href="manage_reservations.php" class="btn btn-outline-danger btn-sm">View Reservations</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4 animate__animated animate__fadeIn">
                    <div class="pricing-card text-center">
                        <div class="text-danger mb-3">
                            <i class="fas fa-server" style="font-size: 2.5rem;"></i>
                        </div>
                        <h2 style="font-size: 2rem;"><?php echo $reserved_computers; ?>/<?php echo $total_computers; ?></h2>
                        <p class="mb-0">Reserved Computers</p>
                        <div class="mt-3">
                            <a href="manage_computers.php" class="btn btn-outline-danger btn-sm">Check Status</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="pricing-card animate__animated animate__fadeIn">
                        <h3 class="pricing-title">Quick Actions</h3>
                        
                        <div class="row mt-4">
                            <div class="col-md-3 mb-3">
                                <a href="manage_computers.php" class="btn btn-danger w-100">
                                    <i class="fas fa-desktop me-2"></i>Manage Computers
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="manage_reservations.php" class="btn btn-danger w-100">
                                    <i class="fas fa-calendar-alt me-2"></i>Manage Reservations
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="add_tournament.php" class="btn btn-danger w-100">
                                    <i class="fas fa-trophy me-2"></i>Add Tournament
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="manage_users.php" class="btn btn-danger w-100">
                                    <i class="fas fa-user-cog me-2"></i>Manage Users
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#printReportModal">
                                    <i class="fas fa-print me-2"></i>Print Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            
            
            <!-- System Status -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="calculator animate__animated animate__fadeIn">
                        <h3 class="calculator-title"><i class="fas fa-server me-2"></i>System Status</h3>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-circle text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Database Connection</h6>
                                        <small class="text-muted">Active</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-circle text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Reservation System</h6>
                                        <small class="text-muted">Operational</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-circle text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">User Authentication</h6>
                                        <small class="text-muted">Secure</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">System running normally. Last checked: <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Print Report Modal -->
    <div class="modal fade" id="printReportModal" tabindex="-1" aria-labelledby="printReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-danger">
                    <h5 class="modal-title" id="printReportModalLabel">Print Reports</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="printReportForm" method="post" action="generate_report.php" target="_blank">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="reportType" class="form-label">Report Type</label>
                            <select class="form-select bg-dark text-white" id="reportType" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="reservations">Reservation Summary Report</option>
                                <option value="transactions">Transaction Report</option>
                                <option value="users">User List</option>
                                <option value="refunds">Refund Requests Report</option>
                                <option value="tournaments">Tournament Registrations</option>
                                <option value="advisories">Advisory Logs</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dateRange" class="form-label">Date Range</label>
                            <select class="form-select bg-dark text-white" id="dateRange" name="date_range">
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="this_month">This Month</option>
                                <option value="custom">Custom Date Range</option>
                            </select>
                        </div>
                        
                        <div class="row mb-3 custom-date-range" style="display: none;">
                            <div class="col-md-6">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control bg-dark text-white" id="startDate" name="start_date">
                            </div>
                            <div class="col-md-6">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control bg-dark text-white" id="endDate" name="end_date">
                            </div>
                        </div>
                        
                        <div class="mb-3 reservation-status" style="display: none;">
                            <label for="reservationStatus" class="form-label">Status</label>
                            <select class="form-select bg-dark text-white" id="reservationStatus" name="reservation_status">
                                <option value="all">All Statuses</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 user-status" style="display: none;">
                            <label for="userStatus" class="form-label">Membership Status</label>
                            <select class="form-select bg-dark text-white" id="userStatus" name="user_status">
                                <option value="all">All Users</option>
                                <option value="member">Members</option>
                                <option value="non-member">Non-Members</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 refund-status" style="display: none;">
                            <label for="refundStatus" class="form-label">Refund Status</label>
                            <select class="form-select bg-dark text-white" id="refundStatus" name="refund_status">
                                <option value="all">All Statuses</option>
                                <option value="approved">Approved</option>
                                <option value="declined">Declined</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 tournament-status" style="display: none;">
                            <label for="tournamentStatus" class="form-label">Payment Status</label>
                            <select class="form-select bg-dark text-white" id="tournamentStatus" name="tournament_status">
                                <option value="all">All Statuses</option>
                                <option value="paid">Paid</option>
                                <option value="unpaid">Unpaid</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 advisory-status" style="display: none;">
                            <label for="advisoryStatus" class="form-label">Advisory Status</label>
                            <select class="form-select bg-dark text-white" id="advisoryStatus" name="advisory_status">
                                <option value="all">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-danger">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        .advisory-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            z-index: 999;
            transition: all 0.3s ease;
        }
        
        .advisory-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        
        .advisory-btn i {
            font-size: 24px;
        }
    </style>
    
    <script>
        $(document).ready(function() {
            // Initialize the advisory button
            $('#advisoryBtn').click(function() {
                $('#advisoryModal').modal('show');
            });
            
            // Load current advisory status for the new advisory form
            loadAdvisoryStatus();
            
            // Load all advisories when the manage tab is clicked
            $('#manageAdvisories-tab').on('click', function() {
                loadAllAdvisories();
            });
            
            // Refresh advisories list
            $('#refreshAdvisories').on('click', function() {
                loadAllAdvisories();
            });
            
            // Print Report Modal
            $('#dateRange').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('.custom-date-range').show();
                } else {
                    $('.custom-date-range').hide();
                }
            });
            
            $('#reportType').on('change', function() {
                // Hide all status selectors
                $('.reservation-status, .user-status, .refund-status, .tournament-status, .advisory-status').hide();
                
                // Show relevant status selector based on report type
                switch($(this).val()) {
                    case 'reservations':
                        $('.reservation-status').show();
                        break;
                    case 'users':
                        $('.user-status').show();
                        break;
                    case 'refunds':
                        $('.refund-status').show();
                        break;
                    case 'tournaments':
                        $('.tournament-status').show();
                        break;
                    case 'advisories':
                    case 'scheduled_advisories':
                        $('.advisory-status').show();
                        break;
                }
            });
            
            // Load current advisory for the new advisory form
            function loadAdvisoryStatus() {
                $.ajax({
                    url: 'get_advisory_status.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.advisory) {
                            $('#advisoryMessage').val(response.advisory.message);
                            if (response.advisory.status === 'active') {
                                $('#advisoryStatus').prop('checked', true);
                            } else {
                                $('#advisoryStatus').prop('checked', false);
                            }
                        }
                    }
                });
            }
            
            // Load all advisories for the management tab
            function loadAllAdvisories() {
                $.ajax({
                    url: 'get_all_advisories.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.advisories && response.advisories.length > 0) {
                            let html = '';
                            
                            $.each(response.advisories, function(index, advisory) {
                                const statusClass = advisory.status === 'active' ? 'bg-success' : 'bg-secondary';
                                const statusText = advisory.status === 'active' ? 'Active' : 'Inactive';
                                const date = new Date(advisory.created_at).toLocaleString();
                                
                                html += `
                                <tr data-id="${advisory.id}" data-message="${advisory.message}" data-status="${advisory.status}">
                                    <td>${advisory.id}</td>
                                    <td>${advisory.message}</td>
                                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                                    <td>${date}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-advisory" data-id="${advisory.id}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-${advisory.status === 'active' ? 'warning' : 'success'} toggle-status" data-id="${advisory.id}" data-status="${advisory.status}">
                                            <i class="fas fa-${advisory.status === 'active' ? 'ban' : 'check'}"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-advisory" data-id="${advisory.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                `;
                            });
                            
                            $('#advisoriesList').html(html);
                            
                            // Edit advisory event
                            $('.edit-advisory').on('click', function() {
                                const id = $(this).data('id');
                                const row = $(`tr[data-id="${id}"]`);
                                const message = row.data('message');
                                const status = row.data('status');
                                
                                $('#editAdvisoryId').val(id);
                                $('#editAdvisoryMessage').val(message);
                                $('#editAdvisoryStatus').prop('checked', status === 'active');
                                
                                $('#editAdvisoryModal').modal('show');
                            });
                            
                            // Toggle status event
                            $('.toggle-status').on('click', function() {
                                const id = $(this).data('id');
                                const currentStatus = $(this).data('status');
                                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                                
                                toggleAdvisoryStatus(id, newStatus);
                            });
                            
                            // Delete advisory event
                            $('.delete-advisory').on('click', function() {
                                const id = $(this).data('id');
                                
                                if (confirm('Are you sure you want to delete this advisory?')) {
                                    deleteAdvisory(id);
                                }
                            });
                        } else {
                            $('#advisoriesList').html('<tr><td colspan="5" class="text-center">No advisories found</td></tr>');
                        }
                    },
                    error: function() {
                        $('#advisoriesList').html('<tr><td colspan="5" class="text-center text-danger">Error loading advisories</td></tr>');
                    }
                });
            }
            
            // Toggle advisory status function
            function toggleAdvisoryStatus(id, newStatus) {
                $.ajax({
                    url: 'update_advisory_status.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: id,
                        status: newStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload the advisories list
                            loadAllAdvisories();
                            
                            // Show alert
                            alert('Advisory status updated successfully!');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error updating advisory status. Please try again.');
                    }
                });
            }
            
            // Delete advisory function
            function deleteAdvisory(id) {
                $.ajax({
                    url: 'delete_advisory.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload the advisories list
                            loadAllAdvisories();
                            
                            // Show alert
                            alert('Advisory deleted successfully!');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error deleting advisory. Please try again.');
                    }
                });
            }
            
            // Submit edit advisory form
            $('#editAdvisoryForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: 'update_advisory.php',
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Close the modal
                            $('#editAdvisoryModal').modal('hide');
                            
                            // Reload the advisories list
                            loadAllAdvisories();
                            
                            // Show alert
                            alert('Advisory updated successfully!');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error updating advisory. Please try again.');
                    }
                });
            });
        });
    </script>
</body>

</html>
