<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/error_handler.php';

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
        logError("Failed to prepare SELECT user", ['error' => $conn->error, 'user_id' => $userId]);
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
    logError("Exception fetching user profile", ['error' => $e->getMessage(), 'user_id' => $userId]);
    die('An error occurred');
}

// ✅ ADDED: Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photo'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PHOTO_UPLOAD', 'User ID: ' . $userId);
        $errors[] = "Security token validation failed.";
    } else if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
        
        // Get file info
        $file = $_FILES['profile_photo'];
        $fileSize = $file['size'];
        $tmpName = $file['tmp_name'];
        
        // Check file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            $sizeMB = round($fileSize / (1024 * 1024), 2);
            $errors[] = "Image is too large ($sizeMB MB). Maximum size: 5MB";
            logSecurity('OVERSIZED_PROFILE_PHOTO', "User: $userId, Size: $sizeMB MB");
        } else {
            // Validate MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = "Invalid image type. Only JPG, PNG, and GIF are allowed.";
                logSecurity('INVALID_PROFILE_PHOTO_UPLOAD', "User: $userId, MIME: $mimeType");
            } else {
                // Validate it's actually an image
                if (!getimagesize($tmpName)) {
                    $errors[] = "File is not a valid image.";
                    logSecurity('INVALID_PROFILE_PHOTO_UPLOAD', "User: $userId, Not a valid image");
                } else {
                    // Read file content
                    $imageData = file_get_contents($tmpName);
                    
                    if ($imageData === false) {
                        $errors[] = "Failed to read the image file.";
                        logError("Failed to read profile photo", ['user_id' => $userId, 'tmp_name' => $tmpName]);
                    } else {
                        // ✅ Upload to database
                        try {
                            $sql = "UPDATE user SET profile_photo = ? WHERE user_id = ?";
                            $stmt = $conn->prepare($sql);
                            
                            if (!$stmt) {
                                $errors[] = "Database error: " . $conn->error;
                                logError("Failed to prepare UPDATE profile photo", ['error' => $conn->error, 'user_id' => $userId]);
                            } else {
                                $stmt->bind_param("si", $imageData, $userId);
                                
                                if ($stmt->execute()) {
                                    $user['profile_photo'] = $imageData;
                                    logActivity($userId, 'PROFILE_PHOTO_UPLOADED', 
                                        "Size: " . round($fileSize/1024, 2) . " KB, Type: $mimeType");
                                    $success = "Profile photo uploaded successfully!";
                                } else {
                                    $errno = $stmt->errno;
                                    $error = $stmt->error;
                                    
                                    logError("Failed to execute UPDATE profile photo", 
                                        ['error' => $error, 'errno' => $errno, 'user_id' => $userId]);
                                    
                                    if ($errno == 1406) {
                                        $errors[] = "Image data is too large for database.";
                                    } else {
                                        $errors[] = "Failed to upload photo: $error";
                                    }
                                }
                                
                                $stmt->close();
                            }
                        } catch (Exception $e) {
                            $errors[] = "Error uploading photo: " . $e->getMessage();
                            logError("Exception uploading profile photo", ['user_id' => $userId, 'error' => $e->getMessage()]);
                        }
                    }
                }
            }
        }
    } else if (isset($_FILES['profile_photo'])) {
        $fileError = $_FILES['profile_photo']['error'];
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
        ];
        
        $errorMsg = $uploadErrors[$fileError] ?? "Unknown upload error ($fileError)";
        $errors[] = "Upload failed: $errorMsg";
        logError("Profile photo upload error", ['user_id' => $userId, 'error_code' => $fileError]);
    } else {
        $errors[] = "Please select a photo to upload.";
    }
}

// ✅ Handle profile photo removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_photo'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PHOTO_REMOVE', 'User ID: ' . $userId);
        $errors[] = "Security token validation failed.";
    } else {
        try {
            // Set profile_photo to NULL (no parameter needed)
            $sql = "UPDATE user SET profile_photo = NULL WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
                logError("Failed to prepare remove profile photo", ['error' => $conn->error, 'user_id' => $userId]);
            } else {
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $user['profile_photo'] = null;
                    logActivity($userId, 'PROFILE_PHOTO_REMOVED', "User removed profile photo");
                    $success = "Profile photo removed successfully!";
                } else {
                    $errors[] = "Failed to remove photo: " . $stmt->error;
                    logError("Failed to remove profile photo", ['error' => $stmt->error, 'user_id' => $userId]);
                }
                
                $stmt->close();
            }
        } catch (Exception $e) {
            $errors[] = "Error removing photo: " . $e->getMessage();
            logError("Exception removing profile photo", ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PROFILE', 'User ID: ' . $userId);
        $errors[] = "Security token validation failed.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        // Validation
        if (empty($name)) $errors[] = "Name is required";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
        if (empty($phone) || !preg_match('/^[0-9+\-() ]{8,20}$/', $phone)) $errors[] = "Phone must be 8-20 digits";
        if (empty($department)) $errors[] = "Department is required";
        
        if (empty($errors)) {
            try {
                // Check duplicates
                $check_sql = "SELECT user_id FROM user WHERE (email = ? OR phone = ?) AND user_id != ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("ssi", $email, $phone, $userId);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = "Email or phone already in use";
                } else {
                    $stmt->close();
                    
                    // Update profile
                    $sql = "UPDATE user SET name = ?, email = ?, phone = ?, department = ? WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $name, $email, $phone, $department, $userId);
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_name'] = $name;
                        $user['name'] = $name;
                        $user['email'] = $email;
                        $user['phone'] = $phone;
                        $user['department'] = $department;
                        
                        logActivity($userId, 'PROFILE_UPDATED', "Name: $name, Email: $email, Phone: $phone");
                        $success = "Profile updated successfully!";
                    } else {
                        $errors[] = "Failed to update profile: " . $stmt->error;
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                $errors[] = "Error updating profile: " . $e->getMessage();
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PASSWORD_CHANGE', 'User ID: ' . $userId);
        $errors[] = "Security token validation failed.";
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) $errors[] = "Current password is required";
        if (strlen($newPassword) < 8) $errors[] = "New password must be at least 8 characters";
        if ($newPassword !== $confirmPassword) $errors[] = "Passwords do not match";
        
        if (empty($errors)) {
            try {
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
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $sql = "UPDATE user SET password = ? WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashedPassword, $userId);
                    
                    if ($stmt->execute()) {
                        logActivity($userId, 'PASSWORD_CHANGED', "User changed password");
                        $success = "Password changed successfully!";
                    } else {
                        $errors[] = "Failed to change password: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $errors[] = "Error changing password: " . $e->getMessage();
            }
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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

                <!-- Profile Photo Section -->
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-camera"></i> Profile Photo</h5>
                            </div>
                            <div class="card-body text-center py-5">
                                <?php if (!empty($user['profile_photo'])): ?>
                                    <img src="data:image/jpeg;base64,<?php echo base64_encode($user['profile_photo']); ?>" 
                                         alt="Profile Photo" class="profile-photo-preview mb-4">
                                <?php else: ?>
                                    <div class="default-avatar mb-4 mx-auto">
                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <!-- Upload Form -->
                                    <form method="POST" enctype="multipart/form-data" class="mb-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="input-group mb-2" style="max-width: 400px; margin: 0 auto;">
                                            <input type="file" class="form-control" name="profile_photo" 
                                                   accept="image/jpeg,image/png,image/gif" required>
                                            <button type="submit" name="upload_photo" class="btn btn-success">
                                                <i class="bi bi-upload"></i> Upload
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Remove Form -->
                                    <?php if (!empty($user['profile_photo'])): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" name="remove_photo" class="btn btn-outline-danger"
                                                    onclick="return confirm('Remove your profile photo?')">
                                                <i class="bi bi-trash"></i> Remove Photo
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted d-block">
                                            Allowed formats: JPG, PNG, GIF<br>
                                            Maximum size: 5MB
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               pattern="[0-9+\-() ]{8,20}" required>
                                        <small class="text-muted">e.g., +60123456789 or 0123456789</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">Select Department</option>
                                            <option value="Group Strategy & Growth" <?php echo ($user['department'] ?? '') == 'Group Strategy & Growth' ? 'selected' : ''; ?>>Group Strategy & Growth</option>
                                            <option value="Group Human Capital" <?php echo ($user['department'] ?? '') == 'Group Human Capital' ? 'selected' : ''; ?>>Group Human Capital</option>
                                            <option value="Group Safety, Security & Sustainability" <?php echo ($user['department'] ?? '') == 'Group Safety, Security & Sustainability' ? 'selected' : ''; ?>>Group Safety, Security & Sustainability</option>
                                            <option value="Group Finance" <?php echo ($user['department'] ?? '') == 'Group Finance' ? 'selected' : ''; ?>>Group Finance</option>
                                            <option value="Group Stakeholder Relations" <?php echo ($user['department'] ?? '') == 'Group Stakeholder Relations' ? 'selected' : ''; ?>>Group Stakeholder Relations</option>
                                            <option value="Group Maintenance & Reliability" <?php echo ($user['department'] ?? '') == 'Group Maintenance & Reliability' ? 'selected' : ''; ?>>Group Maintenance & Reliability</option>
                                            <option value="Group Legal Counsel, Compliance & Integrity" <?php echo ($user['department'] ?? '') == 'Group Legal Counsel, Compliance & Integrity' ? 'selected' : ''; ?>>Group Legal Counsel, Compliance & Integrity</option>
                                            <option value="Other" <?php echo ($user['department'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-success w-100">
                                        <i class="bi bi-check-circle"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-lock"></i> Change Password</h5>
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
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-warning w-100">
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