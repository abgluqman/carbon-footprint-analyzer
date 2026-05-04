<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/error_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

try {
    $stmt = $conn->prepare('SELECT user_id, name, email, phone, department, password, created_at FROM user WHERE user_id = ?');
    if (!$stmt) {
        logError('Failed to prepare user fetch', ['error' => $conn->error, 'user_id' => $userId]);
        throw new Exception('Database error');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT COUNT(record_id) as total_records,
                                  SUM(total_carbon_emissions) as total_emissions,
                                  AVG(total_carbon_emissions) as avg_emissions,
                                  MIN(record_date) as first_record,
                                  MAX(record_date) as last_record
                           FROM emissions_record
                           WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    logError('Profile initialization failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
    die('An unexpected error occurred.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PROFILE', 'User ID: ' . $userId);
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);

    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9+\\-() ]{8,20}$/', $phone)) {
        $errors[] = 'Phone number must be 8–20 characters';
    }
    if (empty($department)) $errors[] = 'Department is required';

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare('SELECT user_id FROM user WHERE (email = ? OR phone = ?) AND user_id != ?');
            $stmt->bind_param('ssi', $email, $phone, $userId);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Email or phone number is already used by another account';
                logSecurity('PROFILE_DUPLICATE', 'User ID: ' . $userId);
            }
            $stmt->close();

            if (empty($errors)) {
                $stmt = $conn->prepare('UPDATE user SET name = ?, email = ?, phone = ?, department = ? WHERE user_id = ?');
                $stmt->bind_param('ssssi', $name, $email, $phone, $department, $userId);

                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['department'] = $department;

                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['department'] = $department;

                    logActivity($userId, 'PROFILE_UPDATED', 'Profile updated');
                    $success = 'Profile updated successfully!';
                } else {
                    $errors[] = 'Failed to update profile.';
                }

                $stmt->close();
            }
        } catch (Exception $e) {
            logError('Profile update failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
            $errors[] = 'An unexpected error occurred.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_PASSWORD', 'User ID: ' . $userId);
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    try {
        $stmt = $conn->prepare('SELECT password FROM user WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $passwordRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($currentPassword, $passwordRow['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        if (strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters';
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[\\W_]/', $newPassword)) {
            $errors[] = 'New password must contain uppercase, number and special character';
        }
        if ($newPassword !== $confirmPassword) $errors[] = 'New passwords do not match';

        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE user SET password = ? WHERE user_id = ?');
            $stmt->bind_param('si', $hashedPassword, $userId);

            if ($stmt->execute()) {
                logActivity($userId, 'PASSWORD_CHANGED', 'Password changed');
                $success = 'Password changed successfully!';
            } else {
                $errors[] = 'Failed to change password.';
            }

            $stmt->close();
        }
    } catch (Exception $e) {
        logError('Password change failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
        $errors[] = 'An unexpected error occurred.';
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
</head>
<body>
<?php include '../includes/navigation.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Profile & Settings</h1>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>Error:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 120px; height: 120px;">
                                <i class="bi bi-person-circle text-success" style="font-size: 5rem;"></i>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="text-muted mb-2"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone'] ?? '-'); ?></p>
                            <p class="mb-3"><span class="badge bg-secondary"><?php echo htmlspecialchars($user['department']); ?></span></p>
                            <p class="small text-muted mb-0"><i class="bi bi-calendar-check"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="mb-3">Edit Profile Information</h5>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="[0-9+\-() ]{8,20}" required>
                                    <small class="text-muted">Example: +60123456789 or 0123456789</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" required>
                                </div>

                                <button type="submit" name="update_profile" class="btn btn-success">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                            </form>

                            <hr class="my-4">

                            <h5 class="mb-3">Change Password</h5>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" minlength="8" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
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
