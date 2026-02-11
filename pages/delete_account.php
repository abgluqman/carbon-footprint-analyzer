<?php
session_start();
require_once '../config/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Delete user account (cascade will delete all related records)
    $sql = "DELETE FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        // Destroy session
        session_destroy();
        
        // Redirect to homepage with message
        header("Location: ../index.php?deleted=1");
        exit();
    } else {
        $_SESSION['error'] = "Failed to delete account. Please try again.";
        header("Location: profile.php");
        exit();
    }
}

// If accessed via GET, redirect to profile
header("Location: profile.php");
exit();
?>