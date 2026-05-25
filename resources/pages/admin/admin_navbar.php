<?php
// Determine current route for highlighting the active menu item
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$current_path = rtrim($current_path, '/');
if ($current_path === '') {
    $current_path = '/';
}

$is_dashboard = ($current_path === '/admin/dashboard');
$is_users = str_starts_with($current_path, '/admin/users');
$is_computers = str_starts_with($current_path, '/admin/computers');
$is_reservations = str_starts_with($current_path, '/admin/reservations');
$is_refunds = str_starts_with($current_path, '/admin/refunds');
$is_tournaments = str_starts_with($current_path, '/admin/tournaments');
?>

<header class="site-header">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/admin/dashboard">
                <img src="/images/blackout.jpg" alt="Blackout Esports" onerror="this.src='https://via.placeholder.com/180x50?text=BLACKOUT+ESPORTS'">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_dashboard ? 'active' : ''; ?>" href="/admin/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_users ? 'active' : ''; ?>" href="/admin/users">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_computers ? 'active' : ''; ?>" href="/admin/computers">Computers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_reservations ? 'active' : ''; ?>" href="/admin/reservations">Reservations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_refunds ? 'active' : ''; ?>" href="/admin/refunds">Refunds</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_tournaments ? 'active' : ''; ?>" href="/admin/tournaments">Tournaments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/logout">Log Out</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header> 

