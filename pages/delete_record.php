<?php
session_start();
require_once '../config/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recordId > 0) {
    // Verify record belongs to user
    $sql = "SELECT record_id FROM emissions_record WHERE record_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $recordId, $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Delete record (cascade will delete related records)
        $sql = "DELETE FROM emissions_record WHERE record_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $recordId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Record deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete record";
        }
    } else {
        $_SESSION['error'] = "Record not found or access denied";
    }
}

// Redirect back to dashboard or history based on referrer
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
if (strpos($referrer, 'dashboard.php') !== false) {
    header("Location: dashboard.php");
} else {
    header("Location: history.php");
}
exit();
?>