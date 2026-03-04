<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/error_handler.php';

// Redirect if already logged in as admin
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
        logSecurity('ADMIN_LOGIN_EMPTY_FIELDS', "Email: $email");
    } else {
        try {
            // Prepared statement prevents SQL injection
            $sql = "SELECT admin_id, name, email, password FROM admin WHERE email = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                logError("Failed to prepare admin login query", [
                    'error' => $conn->error,
                    'email' => $email
                ]);
                $error = "System error. Please try again later.";
            } else {
                $stmt->bind_param("s", $email);
                
                if (!$stmt->execute()) {
                    logError("Failed to execute admin login query", [
                        'error' => $stmt->error,
                        'email' => $email
                    ]);
                    $error = "System error. Please try again later.";
                } else {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 1) {
                        $admin = $result->fetch_assoc();
                        
                        // Password verification with hashed password
                        if (password_verify($password, $admin['password'])) {
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            $_SESSION['admin_id'] = $admin['admin_id'];
                            $_SESSION['admin_name'] = $admin['name'];
                            $_SESSION['admin_email'] = $admin['email'];
                            $_SESSION['is_admin'] = true;
                            
                            logActivity($admin['admin_id'], 'ADMIN_LOGIN_SUCCESS', "Admin: {$admin['name']}");
                            
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            logSecurity('ADMIN_LOGIN_WRONG_PASSWORD', "Email: $email");
                            $error = "Invalid email or password";
                        }
                    } else {
                        logSecurity('ADMIN_LOGIN_NOT_FOUND', "Email: $email");
                        $error = "Invalid email or password";
                    }
                }
                
                $stmt->close();
            }
        } catch (Exception $e) {
            //  Catch any unexpected errors
            logError("Admin login exception occurred", [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            $error = "An unexpected error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Carbon Footprint Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body style="
    background-image: url('../assets/images/Admin-background.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="text-success">Admin Login</h2>
                            <p class="text-muted">Carbon Footprint Analyzer</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" 
                                           name="email" placeholder="admin@example.com" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" 
                                           name="password" placeholder="Enter your password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right"></i> Sign In
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="../index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Back to Main Site
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>