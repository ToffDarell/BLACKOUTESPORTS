<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("location:admin_login.php");
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

// Handle user status updates
if (isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param('si', $status, $user_id);
    $stmt->execute();
    
    // Set success message
    $_SESSION['success_message'] = "User status updated to " . $status . "!";
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related reservations
        $stmt = $conn->prepare("DELETE FROM reservations WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Delete related memberships
        $stmt = $conn->prepare("DELETE FROM memberships WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Finally delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success_message'] = "User deleted successfully!";
    } catch (Exception $e) {
        // Roll back the transaction if something failed
        $conn->rollback();
        $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
    }
}

// Update the query to include membership information
try {
    $query = "SELECT u.*, 
              COUNT(DISTINCT r.reservation_id) as reservation_count,
              DATE_FORMAT(COALESCE(u.date_registered, NOW()), '%M %d, %Y') as formatted_date,
              m.membership_id,
              DATE_FORMAT(m.join_date, '%M %d, %Y') as member_since
              FROM users u 
              LEFT JOIN reservations r ON u.user_id = r.user_id 
              LEFT JOIN memberships m ON u.user_id = m.user_id
              GROUP BY u.user_id 
              ORDER BY FIELD(u.membership_status, 'Pending', 'Member', 'Non-Member'), u.date_registered DESC";

    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    // Get user statistics
    $total_users = $result->num_rows;
    $active_users = 0;
    $inactive_users = 0;
    $active_members = 0;
    $total_reservations = 0;

    while($row = $result->fetch_assoc()) {
        if($row['status'] == 'Active') $active_users++;
        else $inactive_users++;
        if($row['membership_id']) $active_members++;
        $total_reservations += $row['reservation_count'];
        $users[] = $row;
    }
    $result->data_seek(0);
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Admin</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashcss.css">
    <link rel="stylesheet" href="admin_nav.css">
    <link rel="stylesheet" href="manage_users.css">
        
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="admin_dashboard.php">
                    <img src="images/blackout.jpg" alt="Blackout Esports">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="manage_computers.php">Computers</a></li>
                        <li class="nav-item"><a class="nav-link" href="manage_reservations.php">Reservations</a></li>
                        <li class="nav-item"><a class="nav-link" href="add_tournament.php">Tournaments</a></li>
                        <li class="nav-item"><a class="nav-link active" href="manage_users.php">Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_logout.php">Log out</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mt-4">
        <div class="content-wrapper">
            <h2 class="page-title text-white">Manage Users</h2>

            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-2 justify-content-center">
                <div class="col-md-2" >
                    <div class="stats-card">
                        <h3><?php echo $total_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3><?php echo $active_users; ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3><?php echo $inactive_users; ?></h3>
                        <p>Inactive Users</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3><?php echo $active_members; ?></h3>
                        <p>Active Members</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3><?php echo $total_reservations; ?></h3>
                        <p>Total Reservations</p>
                    </div>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                    <select class="form-select" id="statusFilter" style="max-width: 150px;">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Deactivated">Deactivated</option>
                    </select>
                    <select class="form-select" id="membershipFilter" style="max-width: 150px;">
                        <option value="">All Members</option>
                        <option value="member">Members</option>
                        <option value="non-member">Non-Members</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;color: white;">User ID</th>
                                        <th style="text-align: center;color: white;">Full Name</th>
                                        <th style="text-align: center;color: white;">Email</th>
                                        <th style="text-align: center;color: white;">Phone</th>
                                        <th style="text-align: center;color: white;">Status</th>
                                        <th style="text-align: center;color: white;">Member Since</th>
                                        <th style="text-align: center;color: white;">Membership Status</th>
                                        <th style="text-align: center;color: white;">Payment Proof</th>
                                        <th style="text-align: center;color: white;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['user_phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($row['status'] ?? 'active'); ?>">
                                                <?php echo $row['status'] ?? 'Active'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo ($row['membership_status'] === 'Member') ? date('M d, Y', strtotime($row['member_since'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo match($row['membership_status']) {
                                                    'Pending' => 'bg-warning',
                                                    'Member' => 'bg-success',
                                                    default => 'bg-secondary'
                                                };
                                            ?>">
                                                <?php echo $row['membership_status'] ?? 'Non-Member'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['payment_proof']): ?>
                                                <a href="<?php echo htmlspecialchars($row['payment_proof']); ?>" target="_blank" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-image"></i> View Proof
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                                                                <td>                                            <div class="action-buttons">                                                <button class="btn btn-edit" title="Edit User"                                                     onclick="window.location.href='edit_user.php?user_id=<?php echo $row['user_id']; ?>'">                                                    <i class="fas fa-edit"></i>                                                </button>                                                <form method="POST" class="d-inline">                                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">                                                    <input type="hidden" name="status" value="<?php echo $row['status'] === 'Active' ? 'Deactivated' : 'Active'; ?>">                                                    <button type="submit" name="update_status" class="btn <?php echo $row['status'] === 'Active' ? 'btn-toggle' : 'btn-success'; ?>"                                                         title="<?php echo $row['status'] === 'Active' ? 'Deactivate User' : 'Activate User'; ?>">                                                        <i class="fas <?php echo $row['status'] === 'Active' ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>                                                    </button>                                                </form>                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">                                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">                                                    <button type="submit" name="delete_user" class="btn btn-delete" title="Delete User">                                                        <i class="fas fa-trash"></i>                                                    </button>                                                </form>
                                                <?php if ($row['membership_status'] === 'Pending'): ?>
                                                    <form method="POST" action="process_membership.php" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                        <button type="submit" name="approve_membership" class="btn btn-success btn-sm" title="Approve Membership">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" title="Reject Membership" 
                                                            onclick="showDeclineModal(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['user_name'])); ?>')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No users found in the system.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this modal at the end of the page before closing body tag -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-labelledby="declineModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-black text-white border" style="border: 2px solid #dc3545 !important; box-shadow: 0 0 15px rgba(220, 53, 69, 0.5);">
                <div class="modal-header" style="background-color: #111111; border-bottom: 1px solid #dc3545;">
                    <h5 class="modal-title fw-bold" style="color: #dc3545;" id="declineModalLabel"><i class="fas fa-times-circle me-2"></i>Decline Membership</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="process_membership.php" id="declineForm">
                    <div class="modal-body" style="background-color: #181818;">
                        <div class="alert bg-dark text-white mb-4" style="border-left: 4px solid #dc3545; border-color: #dc3545;">
                            <p class="mb-0">You are about to decline the membership application for <strong id="userName" style="color: #dc3545;"></strong>.</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="declineReasonSelect" class="form-label fw-bold" style="color: #dc3545;">
                                <i class="fas fa-exclamation-triangle me-1"></i> Select reason for declining:
                            </label>
                            <select class="form-select bg-dark text-light" id="declineReasonSelect" name="decline_reason_select" onchange="toggleCustomReason()" 
                                   style="border: 2px solid #dc3545; box-shadow: 0 0 5px rgba(220, 53, 69, 0.3);">
                                <option value="">-- Select a reason --</option>
                                <option value="Invalid payment information">Invalid payment information</option>
                                <option value="Payment verification failed">Payment verification failed</option>
                                <option value="Invalid payment proof">Invalid payment proof</option>
                                <option value="Insufficient payment amount">Insufficient payment amount</option>
                                <option value="User account issues">User account issues</option>
                                <option value="other">Other (specify)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="customReasonContainer" style="display: none;">
                            <label for="customReason" class="form-label fw-bold" style="color: #dc3545;">
                                <i class="fas fa-pen me-1"></i> Specify reason:
                            </label>
                            <textarea class="form-control bg-dark text-light" id="customReason" name="custom_reason" rows="3" 
                                     placeholder="Enter detailed reason" style="border: 2px solid #dc3545; box-shadow: 0 0 5px rgba(220, 53, 69, 0.3);"></textarea>
                        </div>
                        
                        <input type="hidden" name="decline_reason" id="finalReason" value="">
                        <input type="hidden" name="user_id" id="userId" value="">
                        <input type="hidden" name="reject_membership" value="1">
                    </div>
                    <div class="modal-footer" style="background-color: #111111; border-top: 1px solid #dc3545;">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" onclick="prepareSubmission()" 
                               style="background-color: #dc3545; color: white; border-color: #dc3545; box-shadow: 0 0 10px rgba(220, 53, 69, 0.4);">
                            <i class="fas fa-times me-1"></i> Confirm Decline
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('membershipFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const membershipFilter = document.getElementById('membershipFilter').value;
            const tableRows = document.querySelectorAll('table tbody tr');

            tableRows.forEach(row => {
                const name = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                const status = row.cells[4].textContent.trim();
                const isMember = row.cells[6].textContent.includes('Member');
                
                const matchesSearch = name.includes(searchText) || email.includes(searchText);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesMembership = !membershipFilter || 
                    (membershipFilter === 'member' && isMember) || 
                    (membershipFilter === 'non-member' && !isMember);

                row.style.display = (matchesSearch && matchesStatus && matchesMembership) ? '' : 'none';
            });
        }

        function showDeclineModal(userId, userName) {
            document.getElementById('userId').value = userId;
            document.getElementById('userName').textContent = userName;
            
            // Reset form fields
            document.getElementById('declineReasonSelect').value = '';
            document.getElementById('customReason').value = '';
            document.getElementById('customReasonContainer').style.display = 'none';
            
            // Show the modal
            var declineModal = new bootstrap.Modal(document.getElementById('declineModal'));
            declineModal.show();
        }
        
        function toggleCustomReason() {
            var reasonSelect = document.getElementById('declineReasonSelect');
            var customReasonContainer = document.getElementById('customReasonContainer');
            
            if (reasonSelect.value === 'other') {
                customReasonContainer.style.display = 'block';
            } else {
                customReasonContainer.style.display = 'none';
            }
        }
        
        function prepareSubmission() {
            var reasonSelect = document.getElementById('declineReasonSelect');
            var customReason = document.getElementById('customReason');
            var finalReason = document.getElementById('finalReason');
            
            if (reasonSelect.value === 'other') {
                finalReason.value = customReason.value;
            } else {
                finalReason.value = reasonSelect.value;
            }
        }
    </script>
</body>
</html>
