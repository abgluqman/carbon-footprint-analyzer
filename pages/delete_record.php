<?php
session_start();
require_once '../config/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// CSRF validation
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['error'] = "Invalid request";
    header("Location: dashboard.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$recordId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($recordId > 0) {
    // Verify record belongs to user
    $sql = "SELECT record_id FROM emissions_record WHERE record_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $recordId, $userId);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();

        // Delete record
        $deleteSql = "DELETE FROM emissions_record WHERE record_id = ? AND user_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("ii", $recordId, $userId);

        if ($deleteStmt->execute()) {
            $_SESSION['success'] = "Record deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete record";
        }

        $deleteStmt->close();
    } else {
        $_SESSION['error'] = "Record not found or access denied";
        $stmt->close();
    }
}

$referrer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';

if (strpos($referrer, 'dashboard.php') !== false) {
    header("Location: dashboard.php");
} else {
    header("Location: history.php");
}
exit();