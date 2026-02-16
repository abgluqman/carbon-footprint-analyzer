<?php
require_once 'config/db_connection.php';

// Admin credentials
$email = 'admin@carbonanalyzer.com';
$hashed_password = '$2y$10$CADRWFce4SRDOykWCvxDHeG2saW3wAMp8CQbz6yfPFXvoQ05rsm16'; // Admin@123

// Check if admin exists
$sql = "SELECT * FROM admin WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $update_sql = "UPDATE admin SET password = ? WHERE email = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ss", $hashed_password, $email);
    
    if ($update_stmt->execute()) {
        echo "✓ Admin password updated successfully!\n";
        echo "Email: " . $email . "\n";
        echo "Password: Admin@123\n";
    } else {
        echo "✗ Error updating password: " . $conn->error;
    }
} else {
    // Create new admin
    $name = 'Administrator';
    $insert_sql = "INSERT INTO admin (name, email, password) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sss", $name, $email, $hashed_password);
    
    if ($insert_stmt->execute()) {
        echo "✓ Admin account created successfully!\n";
        echo "Email: " . $email . "\n";
        echo "Password: Admin@123\n";
    } else {
        echo "✗ Error creating admin: " . $conn->error;
    }
}

$conn->close();
?>
