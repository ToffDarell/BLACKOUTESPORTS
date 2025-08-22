<?php
session_start();
// Only unset user-specific session variables
unset($_SESSION["user_id"]);
unset($_SESSION["user_name"]);
unset($_SESSION["user_email"]);
unset($_SESSION["is_member"]);
// Preserve any admin sessions that might exist
// Redirect to login page
header("location:login.php");
exit();
?>