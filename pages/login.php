<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/error_handler.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
        logSecurity('LOGIN_ATTEMPT_EMPTY_FIELDS', "Email: $email");
    } else {
        try {
            // Prepared statement to prevent SQL injection
            $sql = "SELECT user_id, name, email, password, department 
                    FROM user WHERE email = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                logError("Failed to prepare login query", [
                    'error' => $conn->error,
                    'email' => $email
                ]);
                $error = "System error. Please try again later.";
            } else {
                $stmt->bind_param("s", $email);
                
                if (!$stmt->execute()) {
                    logError("Failed to execute login query", [
                        'error' => $stmt->error,
                        'email' => $email
                    ]);
                    $error = "System error. Please try again later.";
                } else {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 1) {
                        $user = $result->fetch_assoc();
                        
                        // Password verification with hashed password
                        if (password_verify($password, $user['password'])) {
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['department'] = $user['department'];
                            
                            logActivity($user['user_id'], 'LOGIN_SUCCESS', "User: {$user['name']}");
                            
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            logSecurity('LOGIN_FAILED_WRONG_PASSWORD', "Email: $email");
                            $error = "Invalid email or password";
                        }
                    } else {
                        logSecurity('LOGIN_FAILED_USER_NOT_FOUND', "Email: $email");
                        $error = "Invalid email or password";
                    }
                }
                
                $stmt->close();
            }
        } catch (Exception $e) {
            logError("Login exception occurred", [
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
    <title>Login - Carbon Footprint Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body style="
    background-image: url('../assets/images/user-login-no.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;">
    <div class="container">
        <div class="row justify-content-start align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="text-success">Welcome Back👋</h2>
                            <p class="text-muted">Today is a new day. It's your day. You shape it. Sign in to start calculate your footprint!</p>
                        </div>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo htmlspecialchars($_SESSION['success']); 
                                unset($_SESSION['success']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" 
                                       name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 mb-3">Sign In</button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="register.php" class="text-success">Register</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>