<?php
session_start();
// Only unset admin-specific session variables
unset($_SESSION["admin_id"]);
unset($_SESSION["admin_name"]);
// Redirect to login page with logout parameter
header("location:admin_login.php?logout=true");
exit();
?>