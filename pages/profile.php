<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/error_handler.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

// Get current user data
try {
    $sql = "SELECT name, email, phone, department, profile_photo FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        logError("Failed to prepare SELECT user", [
            'error' => $conn->error,
            'user_id' => $userId
        ]);
        die('Database error occurred');
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
} catch (Exception $e) {
    logError("Exception fetching user profile", [
        'error' => $e->getMessage(),
        'user_id' => $userId
    ]);
    die('An error occurred');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PROFILE', 'User ID: ' . $userId);
        die('CSRF token validation failed.');
    }
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    
    // Validation
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) 
        $errors[] = "Valid email is required";
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9+\-() ]{8,20}$/', $phone)) {
        $errors[] = "Phone number must be 8-20 digits";
    }
    
    if (empty($department)) $errors[] = "Department is required";
    
    if (empty($errors)) {
        try {
            // Check if email or phone is taken by another user
            $check_sql = "SELECT user_id FROM user WHERE (email = ? OR phone = ?) AND user_id != ?";
            $stmt = $conn->prepare($check_sql);
            
            if (!$stmt) {
                logError("Failed to prepare duplicate check", [
                    'error' => $conn->error,
                    'user_id' => $userId
                ]);
                $errors[] = "System error. Please try again.";
            } else {
                $stmt->bind_param("ssi", $email, $phone, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $errors[] = "Email or phone number already in use by another account";
                    logSecurity('PROFILE_UPDATE_DUPLICATE', "User: $userId, Email: $email, Phone: $phone");
                }
                $stmt->close();
            }
            
            if (empty($errors)) {
                $sql = "UPDATE user SET name = ?, email = ?, phone = ?, department = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    logError("Failed to prepare UPDATE user", [
                        'error' => $conn->error,
                        'user_id' => $userId
                    ]);
                    $errors[] = "Failed to update profile.";
                } else {
                    $stmt->bind_param("ssssi", $name, $email, $phone, $department, $userId);
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['department'] = $department;
                        
                        $user['name'] = $name;
                        $user['email'] = $email;
                        $user['phone'] = $phone;
                        $user['department'] = $department;
                        
                        logActivity($userId, 'PROFILE_UPDATED', 
                            "Name: $name, Email: $email, Phone: $phone, Dept: $department");
                        
                        $success = "Profile updated successfully!";
                    } else {
                        logError("Failed to execute UPDATE user", [
                            'error' => $stmt->error,
                            'user_id' => $userId
                        ]);
                        $errors[] = "Failed to update profile.";
                    }
                    
                    $stmt->close();
                }
            }
            
        } catch (Exception $e) {
            logError("Exception updating profile", [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            $errors[] = "An unexpected error occurred.";
        }
    }
}

// Profile photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photo'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PHOTO_UPLOAD', 'User ID: ' . $userId);
        die('CSRF token validation failed.');
    }
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $mysqlMax = null;
        $res = $conn->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
        if ($res) {
            $row = $res->fetch_assoc();
            $mysqlMax = isset($row['Value']) ? intval($row['Value']) : null;
        }

        $defaultMax = 5 * 1024 * 1024; // 5MB default
        $maxSize = $defaultMax;
        if ($mysqlMax && $mysqlMax > 2048) {
            $maxSize = min($defaultMax, max(1024, $mysqlMax - 1024));
        }

        // Validate image
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['profile_photo']['tmp_name']);
        $imageInfo = @getimagesize($_FILES['profile_photo']['tmp_name']);

        if (!in_array($mimeType, $allowedMimes) || $imageInfo === false) {
            $errors[] = "Only valid JPG, PNG, and GIF images are allowed";
            logSecurity('INVALID_PROFILE_PHOTO_UPLOAD', "User: $userId, MIME: $mimeType");
        } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
            $limitMB = round($maxSize / (1024 * 1024), 2);
            $errors[] = "Image is too large. Maximum size: {$limitMB} MB";
            logSecurity('OVERSIZED_PROFILE_PHOTO', sprintf(
                "User: %s, Size: %.2f MB, Limit: %.2f MB",
                $userId,
                $_FILES['profile_photo']['size'] / (1024 * 1024),
                $limitMB
            ));
        } else {
            // Read and resize image 
            $imageData = @file_get_contents($_FILES['profile_photo']['tmp_name']);
            
            if ($imageData === false) {
                $errors[] = "Failed to read uploaded image";
                logError("Failed to read profile photo", [
                    'user_id' => $userId,
                    'tmp_name' => $_FILES['profile_photo']['tmp_name']
                ]);
            } else {
                try {
                    $sql = "UPDATE `user` 
        SET profile_photo = ? 
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    logError("Failed to prepare UPDATE profile photo", [
        'error' => $conn->error,
        'user_id' => $userId
    ]);

    $errors[] = "Failed to upload photo.";

} else {

    // Bind actual image data directly
    $stmt->bind_param("si", $imageData, $userId);

    if ($stmt->execute()) {

        $user['profile_photo'] = $imageData;

        logActivity($userId, 'PROFILE_PHOTO_UPLOADED', sprintf(
            "Size: %.2f KB, Type: %s",
            strlen($imageData) / 1024,
            $mimeType
        ));

        $success = "Profile photo uploaded successfully!";

    } else {

        logError("Failed to execute UPDATE profile photo", [
            'error' => $stmt->error,
            'errno' => $stmt->errno,
            'user_id' => $userId
        ]);

        $errors[] = "Failed to upload photo: " . $stmt->error;
    }

    $stmt->close();
}
                    
                } catch (mysqli_sql_exception $e) {
                    if (str_contains($e->getMessage(), 'Data too long')) {
                        $errors[] = "Image is too large for database. Please use a smaller image (max 16MB).";
                        logError("Profile photo too large for DB", [
                            'user_id' => $userId,
                            'size' => strlen($imageData)
                        ]);
                    } else {
                        $errors[] = "Database error occurred: " . $e->getMessage();
                        logError("Exception uploading profile photo", [
                            'user_id' => $userId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] != 4) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        $errorCode = $_FILES['profile_photo']['error'];
        $errorMsg = $uploadErrors[$errorCode] ?? "Unknown error ($errorCode)";
        $errors[] = "Photo upload failed: $errorMsg";
        
        logError("Profile photo upload error", [
            'user_id' => $userId,
            'error_code' => $errorCode
        ]);
    } else {
        $errors[] = "Please select a photo to upload";
    }
}

// Profile photo removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_photo'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PHOTO_REMOVE', 'User ID: ' . $userId);
        die('CSRF token validation failed.');
    }
    
    try {
        $sql = "UPDATE `user` 
        SET profile_photo = ? 
        WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            logError("Failed to prepare remove profile photo", [
                'error' => $conn->error,
                'user_id' => $userId
            ]);
            $errors[] = "Failed to remove photo.";
        } else {
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $user['profile_photo'] = null;
                
                logActivity($userId, 'PROFILE_PHOTO_REMOVED', "User removed profile photo");
                
                $success = "Profile photo removed successfully!";
            } else {
                logError("Failed to execute remove profile photo", [
                    'error' => $stmt->error,
                    'user_id' => $userId
                ]);
                $errors[] = "Failed to remove photo.";
            }
            
            $stmt->close();
        }
        
    } catch (Exception $e) {
        logError("Exception removing profile photo", [
            'user_id' => $userId,
            'error' => $e->getMessage()
        ]);
        $errors[] = "An unexpected error occurred.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PASSWORD_CHANGE', 'User ID: ' . $userId);
        die('CSRF token validation failed.');
    }
    
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($currentPassword)) $errors[] = "Current password is required";
    if (strlen($newPassword) < 8) $errors[] = "New password must be at least 8 characters";
    if ($newPassword !== $confirmPassword) $errors[] = "New passwords do not match";
    
    if (empty($errors)) {
        try {
            // Verify current password
            $sql = "SELECT password FROM user WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!password_verify($currentPassword, $result['password'])) {
                $errors[] = "Current password is incorrect";
                logSecurity('PASSWORD_CHANGE_WRONG_CURRENT', "User: $userId");
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $sql = "UPDATE user SET password = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    logActivity($userId, 'PASSWORD_CHANGED', "User changed password");
                    $success = "Password changed successfully!";
                } else {
                    logError("Failed to update password", [
                        'error' => $stmt->error,
                        'user_id' => $userId
                    ]);
                    $errors[] = "Failed to change password.";
                }
                
                $stmt->close();
            }
            
        } catch (Exception $e) {
            logError("Exception changing password", [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            $errors[] = "An unexpected error occurred.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Carbon Footprint Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        .profile-photo-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #198754;
        }
        .default-avatar {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-person-circle"></i> My Profile</h1>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong><i class="bi bi-exclamation-triangle"></i> Errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile photo -->
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-camera"></i> Profile Photo</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!empty($user['profile_photo'])): ?>
                                    <img src="data:image/jpeg;base64,<?php echo base64_encode($user['profile_photo']); ?>" 
                                         alt="Profile Photo" class="profile-photo-preview mb-3">
                                <?php else: ?>
                                    <div class="default-avatar mb-3 mx-auto">
                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <form method="POST" enctype="multipart/form-data" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="mb-2">
                                            <input type="file" class="form-control d-inline-block" name="profile_photo" 
                                                   accept="image/jpeg,image/png,image/gif" style="max-width: 300px;">
                                        </div>
                                        <button type="submit" name="upload_photo" class="btn btn-success me-2">
                                            <i class="bi bi-upload"></i> Upload Photo
                                        </button>
                                    </form>
                                    
                                    <?php if (!empty($user['profile_photo'])): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" name="remove_photo" class="btn btn-outline-danger"
                                                    onclick="return confirm('Are you sure you want to remove your profile photo?')">
                                                <i class="bi bi-trash"></i> Remove Photo
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Allowed: JPG, PNG, GIF. Max size: 5MB. Recommended: Square image, 300x300px or larger.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Information -->
                    <div class="col-md-6 mb-4 d-flex">
                        <div class="card w-100">
                            <div class="card-header">
                                <h5><i class="bi bi-person"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               pattern="[0-9+\-() ]{8,20}"
                                               required>
                                        <small class="text-muted">Format: +60123456789 or 0123456789</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="Group Strategy & Growth" <?php echo $user['department'] == 'Group Strategy & Growth' ? 'selected' : ''; ?>>Group Strategy & Growth</option>
                                            <option value="Group Human Capital" <?php echo $user['department'] == 'Group Human Capital' ? 'selected' : ''; ?>>Group Human Capital</option>
                                            <option value="Group Safety, Security & Sustainability" <?php echo $user['department'] == 'Group Safety, Security & Sustainability' ? 'selected' : ''; ?>>Group Safety, Security & Sustainability</option>
                                            <option value="Group Finance" <?php echo $user['department'] == 'Group Finance' ? 'selected' : ''; ?>>Group Finance</option>
                                            <option value="Group Stakeholder Relations" <?php echo $user['department'] == 'Group Stakeholder Relations' ? 'selected' : ''; ?>>Group Stakeholder Relations</option>
                                            <option value="Group Maintenance & Reliability" <?php echo $user['department'] == 'Group Maintenance & Reliability' ? 'selected' : ''; ?>>Group Maintenance & Reliability</option>
                                            <option value="Group Legal Counsel, Compliance & Integrity" <?php echo $user['department'] == 'Group Legal Counsel, Compliance & Integrity' ? 'selected' : ''; ?>>Group Legal Counsel, Compliance & Integrity</option>
                                            <option value="Other" <?php echo $user['department'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6 mb-4 d-flex">
                        <div class="card w-100">
                            <div class="card-header">
                                <h5><i class="bi bi-lock"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="8" required>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>