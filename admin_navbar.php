<?php
// Get current page filename for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

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
                        <a class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_computers.php') ? 'active' : ''; ?>" href="manage_computers.php">Computers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_reservations.php') ? 'active' : ''; ?>" href="manage_reservations.php">Reservations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_refunds.php') ? 'active' : ''; ?>" href="manage_refunds.php">Refunds</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_tournaments.php') ? 'active' : ''; ?>" href="manage_tournaments.php">Tournaments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_logout.php">Log Out</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header> 