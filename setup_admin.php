<?php

//  Prevent running in production

die('⚠️ SECURITY: This file should be deleted after initial setup. Comment out line 21 to proceed.');

require_once 'config/db_connection.php';

// Admin credentials 
$name = 'Administrator';
$email = 'admin@carbonanalyzer.com';
$password = 'Admin@123'; // 

// Validate password strength
if (strlen($password) < 8) {
    die('Error: Password must be at least 8 characters long');
}

//  Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin exists
$sql = "SELECT * FROM admin WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $update_sql = "UPDATE admin SET password = ?, name = ? WHERE email = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sss", $hashed_password, $name, $email);
    
    if ($update_stmt->execute()) {
        echo "✓ Admin password updated successfully!\n";
        echo "Email: " . htmlspecialchars($email) . "\n";
        echo "Password: " . htmlspecialchars($password) . "\n\n";
        echo "⚠️ IMPORTANT: Delete this file (setup_admin.php) immediately!\n";
        echo "⚠️ IMPORTANT: Change your admin password after first login!\n";
    } else {
        echo "✗ Error updating password: " . htmlspecialchars($conn->error);
    }
} else {
    // Create new admin
    $insert_sql = "INSERT INTO admin (name, email, password, created_at) VALUES (?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sss", $name, $email, $hashed_password);
    
    if ($insert_stmt->execute()) {
        echo "✓ Admin account created successfully!\n";
        echo "Email: " . htmlspecialchars($email) . "\n";
        echo "Password: " . htmlspecialchars($password) . "\n\n";
        echo "⚠️ IMPORTANT: Delete this file (setup_admin.php) immediately!\n";
        echo "⚠️ IMPORTANT: Change your admin password after first login!\n";
    } else {
        echo "✗ Error creating admin: " . htmlspecialchars($conn->error);
    }
}

$conn->close();

