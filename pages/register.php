<?php
session_start();
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);
    $department = mysqli_real_escape_string($conn, trim($_POST['department']));
    
    // Validation
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) 
        $errors[] = "Valid email is required";
    if (strlen($password) < 8) 
        $errors[] = "Password must be at least 8 characters";
    if (empty($department)) $errors[] = "Department is required";
    
    // Check if email exists
    $check_email = "SELECT email FROM user WHERE email = '$email'";
    $result = $conn->query($check_email);
    if ($result->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO user (name, email, password, department) 
                VALUES ('$name', '$email', '$hashed_password', '$department')";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
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
<body class="bg-light">
    <body style="
    background-image: url('../assets/images/landing-page.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;"
    >
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
                                        <li><?php echo $error; ?></li>
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
                                    <option value="Other">Group Legal Counsel, Compliance & Integrity</option>
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