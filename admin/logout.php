<?php
session_start();

// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['is_admin']);

// Destroy session
session_destroy();

// Redirect to admin login
header("Location: login.php");
exit();
?>