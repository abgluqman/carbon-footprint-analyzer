<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/error_handler.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $department = trim($_POST['department']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) 
        $errors[] = "Valid email is required";
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9+\-() ]{8,20}$/', $phone)) {
        $errors[] = "Phone number must be 8-20 digits (can include +, -, (), spaces)";
    }
    if (strlen($password) < 8) 
        $errors[] = "Password must be at least 8 characters";
    if (empty($department)) $errors[] = "Department is required";
    
    if (empty($errors)) {
        try {
            
            $check_sql = "SELECT email, phone FROM user WHERE email = ? OR phone = ?";
            $stmt = $conn->prepare($check_sql);
            
            if (!$stmt) {
                logError("Failed to prepare email check query", [
                    'error' => $conn->error,
                    'email' => $email
                ]);
                $errors[] = "System error. Please try again later.";
            } else {
                $stmt->bind_param("s", $email);
                
                if (!$stmt->execute()) {
                    logError("Failed to execute email check query", [
                        'error' => $stmt->error,
                        'email' => $email
                    ]);
                    $errors[] = "System error. Please try again later.";
                } else {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $existing = $result->fetch_assoc();
                        
                        //  to check which field is duplicate
                        if ($existing['email'] === $email) {
                            $errors[] = "Email already registered";
                            logSecurity('REGISTRATION_DUPLICATE_EMAIL', "Email: $email");
                        }
                        if ($existing['phone'] === $phone) {
                            $errors[] = "Phone number already registered";
                            logSecurity('REGISTRATION_DUPLICATE_PHONE', "Phone: $phone");
                        }
                    }
                }
                
                $stmt->close();
            }
            
            if (empty($errors)) {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                if (!$hashed_password) {
                    logError("Password hashing failed", ['email' => $email]);
                    $errors[] = "System error. Please try again later.";
                } else {
                    // Insert using prepared statement
                    $sql = "INSERT INTO user (name, email, phone, password, department) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    
                    if (!$stmt) {
                        logError("Failed to prepare registration query", [
                            'error' => $conn->error,
                            'email' => $email
                        ]);
                        $errors[] = "Registration failed. Please try again.";
                    } else {
                        $stmt->bind_param("ssss", $name, $email, $hashed_password, $department);
                        
                        if ($stmt->execute()) {
                            $newUserId = $stmt->insert_id;
                            
                            logActivity($newUserId, 'USER_REGISTERED', 
                                "Name: $name, Email: $email, Phone: $phone, Department: $department");
                            
                            $_SESSION['success'] = "Registration successful! Please login.";
                            header("Location: login.php");
                            exit();
                        } else {
                            $errorCode = $stmt->errno;
                            $errorMsg = $stmt->error;
                            
                            logError("Registration INSERT failed", [
                                'error_code' => $errorCode,
                                'error_msg' => $errorMsg,
                                'email' => $email,
                                'name' => $name,
                                'department' => $department
                            ]);
                            
                            if ($errorCode == 1062) {
                                $errors[] = "Email or phone number already registered";
                            } else {
                                $errors[] = "Registration failed. Please try again.";
                            }
                        }
                        
                        $stmt->close();
                    }
                }
            }
            
        } catch (Exception $e) {
            logError("Registration exception occurred", [
                'error' => $e->getMessage(),
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
            $errors[] = "An unexpected error occurred. Please try again.";
        }
    } else {
        logSecurity('REGISTRATION_VALIDATION_FAILED', 
            "Email: $email, Phone: $phone, Errors: " . implode(', ', $errors));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Carbon Footprint Analyzer</title>
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
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="text-success">Welcome On Board</h2>
                            <p class="text-muted">Today is a new day. It's your day. You shape it. Register to start make a difference!</p>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" 
                                       name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number or extension</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   pattern="[0-9+\-() ]{8,20}"
                                   required>
                            <small class="text-muted">8-20 digits. Can include: + - ( ) and spaces</small>
                        </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" 
                                       name="password" required>
                                <small class="text-muted">At least 8 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Group Strategy & Growth">Group Strategy & Growth</option>
                                    <option value="Group Human Capital">Group Human Capital</option>
                                    <option value="Group Safety, Security & Sustainability">Group Safety, Security & Sustainability</option>
                                    <option value="Group Finance">Group Finance</option>
                                    <option value="Group Stakeholder Relations">Group Stakeholder Relations</option>
                                    <option value="Group Maintenance & Reliability">Group Maintenance & Reliability</option>
                                    <option value="Group Legal Counsel, Compliance & Integrity">Group Legal Counsel, Compliance & Integrity</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 mb-3">Register</button>
                            <button type="button" class="btn btn-outline-secondary w-100" 
                                    onclick="window.location.href='../index.php'">Cancel</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p class="mb-0">Already have an account? 
                                <a href="login.php" class="text-success">Sign in</a>
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