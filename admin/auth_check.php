<?php
// Admin authentication check
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
?>