<?php
session_start();

// Clear all session data (not just individual keys)
$_SESSION = array();

//  Destroy the session cookie properly
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

//  Destroy the session
session_destroy();

// Redirect to admin login
header("Location: login.php");
exit();
?>