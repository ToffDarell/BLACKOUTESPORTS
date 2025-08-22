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

// Enable error logging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get pending reservations count
$pending_query = "SELECT COUNT(*) as count FROM reservations WHERE status = 'Pending' AND notification_status = 'Unread'";
$pending_result = $conn->query($pending_query);
$pending_count = $pending_result->fetch_assoc()['count'];

// Handle form submissions
$message = '';
$messageType = '';

// Add new computer
if (isset($_POST['add_computer'])) {
    $computer_number = $_POST['computer_number'];
    $status = $_POST['status'];
    $specs = $_POST['specs'];
    
    // Set default specs if empty
    if (empty($specs)) {
        $specs = "Intel Core i7-12700K, 32GB DDR5 RAM, NVIDIA RTX 3080, 1TB NVMe SSD, 240Hz Gaming Monitor";
    }

    // Check if computer number already exists
    $check_query = "SELECT computer_id FROM computers WHERE computer_number = ?";
    $check_stmt = $conn->prepare($check_query);

    if (!$check_stmt) {
        die("Prepare failed: " . $conn->error); // Log error if prepare fails
    }

    $check_stmt->bind_param('s', $computer_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "Computer with number $computer_number already exists.";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare("INSERT INTO computers (computer_number, status, specs) VALUES (?, ?, ?)");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error); // Log error if prepare fails
        }

        $stmt->bind_param('sss', $computer_number, $status, $specs);
        
        if ($stmt->execute()) {
            $message = "Computer added successfully!";
            $messageType = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Update computer
if (isset($_POST['update_computer'])) {
    $computer_id = $_POST['computer_id'];
    $computer_number = $_POST['computer_number'];
    $status = $_POST['status'];
    $specs = $_POST['specs'];

    // Set default specs if empty
    if (empty($specs)) {
        $specs = "Intel Core i7-12700K, 32GB DDR5 RAM, NVIDIA RTX 3080, 1TB NVMe SSD, 240Hz Gaming Monitor";
    }

    $stmt = $conn->prepare("UPDATE computers SET computer_number = ?, status = ?, specs = ? WHERE computer_id = ?");
    $stmt->bind_param('sssi', $computer_number, $status, $specs, $computer_id);
    
    if ($stmt->execute()) {
        $message = "Computer updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "danger";
    }
    $stmt->close();
}

// Delete computer
if (isset($_GET['delete'])) {
    $computer_number = $_GET['delete'];
    
    // Check if there are active reservations for this computer
    $check_query = "SELECT reservation_id FROM reservations WHERE computer_number = ? AND STATUS = 'reserved'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $computer_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "Cannot delete computer. It has active reservations.";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM computers WHERE computer_number = ?");
        $stmt->bind_param('s', $computer_number);
        
        if ($stmt->execute()) {
            $message = "Computer deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Fetch computers
$query = "SELECT * FROM computers ORDER BY CAST(REPLACE(computer_number, 'PC', '') AS UNSIGNED)";

$result = $conn->query($query);
$computers = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $computers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Computers | Blackout Esports</title>
    <link rel="icon" href="images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
   
    <link rel="stylesheet" href="computers.css">
    <link rel="stylesheet" href="manage_computer.css">
    <link rel="stylesheet" href="admin_nav.css">
   
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
                            <a class="nav-link" href="admin_dashboard.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_computers.php">Computers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_reservations.php">
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

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="content-wrapper p-4 rounded-3">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2 class="page-title animate__animated animate__fadeIn">Manage Computers</h2>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary add-computer-btn" data-bs-toggle="modal" data-bs-target="#addComputerModal">
                        <i class="fas fa-plus-circle"></i> Add New Computer
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <?php if (empty($computers)): ?>
                    <div class="col-12">
                        <div class="alert alert-info animate__animated animate__fadeIn">
                            <i class="fas fa-info-circle"></i> No computers found. Add your first computer using the button above.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($computers as $computer): ?>
                        <?php 
                        $statusClass = '';
                        if ($computer['status'] == 'available') {
                            $statusClass = 'available';
                        } elseif ($computer['status'] == 'reserved') {
                            $statusClass = 'reserved';
                        } elseif ($computer['status'] == 'maintenance') {
                            $statusClass = 'maintenance';
                        }
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card computer-card <?php echo $statusClass; ?> animate__animated animate__fadeIn">
                                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                    <span>PC #<?php echo htmlspecialchars($computer['computer_number']); ?></span>
                                </div>
                                
                                <!-- PC Icon based on status -->
                                <div class="computer-icon mt-3 <?php echo ($statusClass == 'reserved') ? 'pulse-animation' : ''; ?>">
                                    <?php if ($computer['status'] == 'available'): ?>
                                        <i class="fas fa-desktop"></i>
                                    <?php elseif ($computer['status'] == 'reserved'): ?>
                                        <i class="fas fa-desktop"></i>
                                    <?php elseif ($computer['status'] == 'maintenance'): ?>
                                        <i class="fas fa-desktop"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <p><strong>Status:</strong> 
                                        <?php if ($computer['status'] == 'available'): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif ($computer['status'] == 'reserved'): ?>
                                            <span class="badge bg-danger">Reserved</span>
                                        <?php elseif ($computer['status'] == 'maintenance'): ?>
                                            <span class="badge bg-warning text-dark">Maintenance</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($computer['specs']): ?>
                                        <p><strong>Specs:</strong> <?php echo htmlspecialchars($computer['specs']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer d-flex justify-content-between bg-dark text-white border-top border-secondary">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editComputerModal" 
                                        data-id="<?php echo $computer['computer_id']; ?>"
                                        data-number="<?php echo htmlspecialchars($computer['computer_number']); ?>"
                                        data-status="<?php echo htmlspecialchars($computer['status']); ?>"
                                        data-specs="<?php echo htmlspecialchars($computer['specs']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="manage_computers.php?delete=<?php echo $computer['computer_number']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this computer?');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Computer Modal -->
    <div class="modal fade" id="addComputerModal" tabindex="-1" aria-labelledby="addComputerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white border-bottom border-secondary text-center w-100">
                    <h5 class="modal-title text-white text-uppercase w-100 text-center" id="addComputerModalLabel"><i class="fas fa-desktop me-2"></i>ADD NEW COMPUTER</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_computers.php" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="computer_number" class="form-label">Computer Number</label>
                            <input type="text" class="form-control" id="computer_number" name="computer_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="available" selected>Available</option>
                                <option value="reserved">Reserved</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="specs" class="form-label">Computer Specifications</label>
                            <textarea class="form-control" id="specs" name="specs" rows="3" placeholder="CPU, RAM, GPU, etc.">Intel Core i7-12700K, 32GB DDR5 RAM, NVIDIA RTX 4090, 1TB NVMe SSD, 240Hz Gaming Monitor</textarea>
                            <div class="form-text">Default gaming PC specs provided. Edit as needed.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_computer" class="btn btn-primary">Add Computer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Computer Modal -->
    <div class="modal fade" id="editComputerModal" tabindex="-1" aria-labelledby="editComputerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white border-bottom border-secondary text-center w-100">
                    <h5 class="modal-title text-white text-uppercase w-100 text-center" id="editComputerModalLabel"><i class="fas fa-edit me-2"></i>EDIT COMPUTER</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_computers.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" id="edit_computer_id" name="computer_id">
                        <div class="mb-3">
                            <label for="edit_computer_number" class="form-label">Computer Number</label>
                            <input type="text" class="form-control" id="edit_computer_number" name="computer_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="reserved">Reserved</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_specs" class="form-label">Computer Specifications</label>
                            <textarea class="form-control" id="edit_specs" name="specs" rows="3"></textarea>
                            <div class="form-text">Gaming PC specifications (CPU, RAM, GPU, storage, etc.)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_computer" class="btn btn-primary">Update Computer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Populate edit modal with computer data
        const editComputerModal = document.getElementById('editComputerModal');
        if (editComputerModal) {
            editComputerModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const computerId = button.getAttribute('data-id');
                const computerNumber = button.getAttribute('data-number');
                const status = button.getAttribute('data-status');
                const specs = button.getAttribute('data-specs');
                
                console.log('Modal Data:', { computerId, computerNumber, status, specs });
                
                const modal = this;
                modal.querySelector('#edit_computer_id').value = computerId;
                modal.querySelector('#edit_computer_number').value = computerNumber;
                modal.querySelector('#edit_status').value = status;
                modal.querySelector('#edit_specs').value = specs || '';
            });
        }

        // Debug form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Form submitting:', this.action, new FormData(this));
            });
        });

        // Live countdown timer for reserved computers
        function updateCountdowns() {
            const countdowns = document.querySelectorAll('.countdown');
            countdowns.forEach(countdown => {
                const minutes = parseInt(countdown.getAttribute('data-minutes'), 10);
                if (minutes > 0) {
                    const hours = Math.floor(minutes / 60);
                    const remainingMinutes = minutes % 60;
                    countdown.textContent = `${hours}h ${remainingMinutes}m`;
                    countdown.setAttribute('data-minutes', minutes - 1);
                } else {
                    countdown.textContent = 'Expired';
                }
            });
        }

        setInterval(updateCountdowns, 60000);
    </script>
</body>

</html>