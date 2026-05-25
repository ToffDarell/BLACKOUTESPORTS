<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_PAGES', APP_ROOT . '/resources/pages');

set_include_path(APP_ROOT . PATH_SEPARATOR . APP_PAGES . PATH_SEPARATOR . get_include_path());

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = $path ?? '/';
$path = rawurldecode($path);
$path = trim($path, '/');

if ($path === '') {
    $path = 'login';
}

if (str_ends_with($path, '.php')) {
    $path = substr($path, 0, -4);
}

$overrides = [
    'signup' => 'sign.php',
    'sign' => 'sign.php',
    'register' => 'sign.php',
    'admin' => 'admin/admin_dashboard.php',
    'admin/login' => 'admin/admin_login.php',
    'admin/signup' => 'admin/admin_signup.php',
    'admin/forgot-password' => 'admin/admin_forgot_password.php',
    'admin/verify-code' => 'admin/admin_verify_code.php',
    'admin/logout' => 'admin/admin_logout.php',
    'admin/dashboard' => 'admin/admin_dashboard.php',
    'admin/users' => 'admin/manage_users.php',
    'admin/users/edit' => 'admin/edit_user.php',
    'admin/reservations' => 'admin/manage_reservations.php',
    'admin/computers' => 'admin/manage_computers.php',
    'admin/tournaments' => 'admin/manage_tournaments.php',
    'admin/tournaments/add' => 'admin/add_tournament.php',
    'admin/tournaments/edit' => 'admin/edit_tournament.php',
    'admin/tournaments/registrations/edit' => 'admin/edit_tournament_registration.php',
    'admin/tournaments/registrations/fix' => 'admin/fix_tournament_registrations.php',
    'admin/refunds' => 'admin/manage_refunds.php',
    'admin/reports' => 'admin/generate_report.php',
    'admin/advisories/list' => 'admin/get_all_advisories.php',
    'admin/advisories/status' => 'admin/get_advisory_status.php',
    'admin/advisories/post' => 'admin/post_advisory.php',
    'admin/advisories/update' => 'admin/update_advisory.php',
    'admin/advisories/update-status' => 'admin/update_advisory_status.php',
    'admin/advisories/delete' => 'admin/delete_advisory.php',
    'admin/tournaments/registrations/check' => 'admin/check_registrations.php',
    'admin/tournaments/registrations/delete' => 'admin/delete_registration.php',
    'admin_login' => 'admin/admin_login.php',
    'admin_signup' => 'admin/admin_signup.php',
    'admin_forgot_password' => 'admin/admin_forgot_password.php',
    'admin_verify_code' => 'admin/admin_verify_code.php',
    'admin_logout' => 'admin/admin_logout.php',
    'admin_dashboard' => 'admin/admin_dashboard.php',
    'manage_users' => 'admin/manage_users.php',
    'edit_user' => 'admin/edit_user.php',
    'manage_reservations' => 'admin/manage_reservations.php',
    'manage_computers' => 'admin/manage_computers.php',
    'manage_tournaments' => 'admin/manage_tournaments.php',
    'manage_refunds' => 'admin/manage_refunds.php',
    'add_tournament' => 'admin/add_tournament.php',
    'edit_tournament' => 'admin/edit_tournament.php',
    'edit_tournament_registration' => 'admin/edit_tournament_registration.php',
    'fix_tournament_registrations' => 'admin/fix_tournament_registrations.php',
    'generate_report' => 'admin/generate_report.php',
    'get_all_advisories' => 'admin/get_all_advisories.php',
    'get_advisory_status' => 'admin/get_advisory_status.php',
    'post_advisory' => 'admin/post_advisory.php',
    'update_advisory' => 'admin/update_advisory.php',
    'update_advisory_status' => 'admin/update_advisory_status.php',
    'delete_advisory' => 'admin/delete_advisory.php',
    'check_registrations' => 'admin/check_registrations.php',
    'delete_registration' => 'admin/delete_registration.php'
];

$candidates = [];

if (isset($overrides[$path])) {
    $candidates[] = $overrides[$path];
}

$candidates[] = $path . '.php';

$slash_to_underscore = str_replace('/', '_', $path);
if ($slash_to_underscore !== $path) {
    $candidates[] = $slash_to_underscore . '.php';
}

$segments = explode('/', $path);
if (count($segments) > 1) {
    $last_segment = end($segments);
    $candidates[] = $last_segment . '.php';
}

foreach ($candidates as $relative) {
    $relative = ltrim($relative, '/');
    if (str_contains($relative, '..')) {
        continue;
    }

    $full = APP_PAGES . '/' . $relative;
    if (is_file($full)) {
        require $full;
        exit;
    }
}

http_response_code(404);
$not_found = APP_ROOT . '/resources/errors/404.php';
if (is_file($not_found)) {
    require $not_found;
} else {
    echo '404 Not Found';
}
