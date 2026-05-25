<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("location:/admin/login");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname, 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$allowed_status = ['Active', 'Deactivated'];
$allowed_membership = ['Member', 'Non-Member', 'Pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_phone = trim($_POST['user_phone'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $membership_status = $_POST['membership_status'] ?? 'Non-Member';

    if ($user_id <= 0) {
        $err = "Invalid user ID.";
    } elseif ($user_name === '' || $user_email === '') {
        $err = "Name and email are required.";
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $err = "Invalid email format.";
    } elseif (!in_array($status, $allowed_status, true)) {
        $err = "Invalid status value.";
    } elseif (!in_array($membership_status, $allowed_membership, true)) {
        $err = "Invalid membership status value.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET user_name = ?, user_email = ?, user_phone = ?, status = ?, membership_status = ? WHERE user_id = ?");
        $stmt->bind_param('sssssi', $user_name, $user_email, $user_phone, $status, $membership_status, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User updated successfully.";
            header("location:/admin/users");
            exit();
        }

        $err = "Failed to update user.";
        $stmt->close();
    }
}

$user_id = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($user_id <= 0) {
    header("location:/admin/users");
    exit();
}

$stmt = $conn->prepare("SELECT user_name, user_email, user_phone, status, membership_status FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error_message'] = "User not found.";
    header("location:/admin/users");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Admin</title>
    <link rel="icon" href="/images/blackout.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/dashcss.css">
    <link rel="stylesheet" href="/css/admin_nav.css">
</head>
<body>
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="/admin/dashboard">
                    <img src="/images/blackout.jpg" alt="Blackout Esports">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/computers">Computers</a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/reservations">Reservations</a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/tournaments">Tournaments</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/users">Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/logout">Log out</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mt-4">
        <div class="content-wrapper">
            <h2 class="page-title text-white">Edit User</h2>

            <?php if (isset($err)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $err; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="user_name" class="form-control" value="<?php echo htmlspecialchars($user['user_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="user_email" class="form-control" value="<?php echo htmlspecialchars($user['user_email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="user_phone" class="form-control" value="<?php echo htmlspecialchars($user['user_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Active" <?php echo ($user['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Deactivated" <?php echo ($user['status'] ?? '') === 'Deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Membership</label>
                                <select name="membership_status" class="form-select">
                                    <option value="Member" <?php echo ($user['membership_status'] ?? '') === 'Member' ? 'selected' : ''; ?>>Member</option>
                                    <option value="Non-Member" <?php echo ($user['membership_status'] ?? 'Non-Member') === 'Non-Member' ? 'selected' : ''; ?>>Non-Member</option>
                                    <option value="Pending" <?php echo ($user['membership_status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="update_user" class="btn btn-danger">Save Changes</button>
                            <a href="/admin/users" class="btn btn-outline-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





