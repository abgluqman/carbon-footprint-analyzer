<?php
session_start();
require_once '../config/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

// Get user data
$sql = "SELECT * FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$sql = "SELECT 
            COUNT(record_id) as total_records,
            SUM(total_carbon_emissions) as total_emissions,
            AVG(total_carbon_emissions) as avg_emissions,
            MIN(record_date) as first_record,
            MAX(record_date) as last_record
        FROM emissions_record 
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required";
    }
    
    // Check if email is already taken by another user
    $sql = "SELECT user_id FROM user WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email is already in use by another account";
    }
    
    if (empty($errors)) {
        $sql = "UPDATE user SET name = ?, email = ?, department = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $email, $department, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['department'] = $department;
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $user['name'] = $name;
            $user['email'] = $email;
            $user['department'] = $department;
        } else {
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        $errors[] = "Current password is incorrect";
    }
    
    if (strlen($newPassword) < 8) {
        $errors[] = "New password must be at least 8 characters";
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE user SET password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $errors[] = "Failed to change password. Please try again.";
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
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Profile & Settings</h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Error:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row g-4">
                    <!-- Profile Overview -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 120px; height: 120px;">
                                    <i class="bi bi-person-circle text-success" style="font-size: 5rem;"></i>
                                </div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="mb-3">
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($user['department']); ?>
                                    </span>
                                </p>
                                <p class="small text-muted mb-0">
                                    <i class="bi bi-calendar-check"></i> 
                                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Your Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Total Records</small>
                                        <strong><?php echo $stats['total_records'] ?? 0; ?></strong>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Total Emissions</small>
                                        <strong><?php echo number_format($stats['total_emissions'] ?? 0, 2); ?> kg CO₂</strong>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-warning" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Average per Record</small>
                                        <strong><?php echo number_format($stats['avg_emissions'] ?? 0, 2); ?> kg CO₂</strong>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-info" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                <?php if ($stats['first_record']): ?>
                                    <hr>
                                    <p class="small mb-1">
                                        <i class="bi bi-calendar2-check text-success"></i> 
                                        First record: <?php echo date('d M Y', strtotime($stats['first_record'])); ?>
                                    </p>
                                    <p class="small mb-0">
                                        <i class="bi bi-calendar2-event text-primary"></i> 
                                        Latest record: <?php echo date('d M Y', strtotime($stats['last_record'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Settings Tabs -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" 
                                                data-bs-target="#profile" type="button" role="tab">
                                            <i class="bi bi-person"></i> Profile Information
                                        </button>
                                    </li>
                                  
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Profile Information Tab -->
                                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                        <h5 class="mb-3">Edit Profile Information</h5>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                <small class="text-muted">Used for login and notifications</small>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label for="department" class="form-label">Department</label>
                                                <select class="form-select" id="department" name="department" required>
                                                    <option value="">Select Department</option>
                                                    <?php
                                                    $departments = [
                                                        'Group Strategy & Growth',
                                                        'Group Human Capital',
                                                        'Group Safety, Security & Sustainability',
                                                        'Group Finance',
                                                        'Group Stakeholder Relations',
                                                        'Group Maintenance & Reliability',
                                                        'Group Legal Counsel, Compliance & Corporate Governance',
                                                        'Group Internal Audit',
                                                        'Company Secretary',
                                                        'Other'
                                                    ];
                                                    foreach ($departments as $dept):
                                                    ?>
                                                        <option value="<?php echo $dept; ?>" 
                                                                <?php echo $user['department'] == $dept ? 'selected' : ''; ?>>
                                                            <?php echo $dept; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" name="update_profile" class="btn btn-success">
                                                <i class="bi bi-save"></i> Save Changes
                                            </button>
                                        </form>

                                        <hr class="my-4">
                                        <h5 class="mb-3">Change Password</h5>
                                        <form method="POST" id="changePasswordForm">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" 
                                                       name="current_password" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" 
                                                       name="new_password" minlength="8" required>
                                                <small class="text-muted">Must be at least 8 characters</small>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" 
                                                       name="confirm_password" required>
                                            </div>
                                            
                                            <button type="submit" name="change_password" class="btn btn-warning">
                                                <i class="bi bi-key"></i> Change Password
                                            </button>
                                        </form>

                                        <hr class="my-4">
                                        <div class="alert alert-info">
                                            <h6 class="alert-heading">
                                                <i class="bi bi-info-circle"></i> Password Security Tips
                                            </h6>
                                            <ul class="small mb-0">
                                                <li>Use a unique password you don't use elsewhere</li>
                                                <li>Include uppercase and lowercase letters</li>
                                                <li>Include numbers and special characters</li>
                                                <li>Avoid common words or personal information</li>
                                            </ul>
                                        </div>

                                        <hr class="my-4">
                                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                                            <h6 class="text-danger">
                                                <i class="bi bi-exclamation-triangle"></i> 
                                            </h6>
                                            <p class="small mb-2">
                                                Deleting your account will permanently remove all your data,
                                                including emission records and reports.
                                            </p>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                                <i class="bi bi-trash"></i> Delete Account
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Security tab removed; password form moved into Profile tab -->
                                    
                                    <!-- Preferences removed -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>This action cannot be undone!</strong></p>
                    <p>Deleting your account will:</p>
                    <ul>
                        <li>Permanently delete all your emission records</li>
                        <li>Remove all generated reports</li>
                        <li>Delete your profile information</li>
                        <li>Cancel any pending notifications</li>
                    </ul>
                    <p class="text-muted">
                        If you're sure you want to proceed, please type 
                        <strong>DELETE</strong> in the box below:
                    </p>
                    <input type="text" class="form-control" id="deleteConfirmation" 
                           placeholder="Type DELETE to confirm">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deleteAccount()" id="deleteBtn" disabled>
                        Delete My Account
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable delete button only when "DELETE" is typed
        document.getElementById('deleteConfirmation').addEventListener('input', function(e) {
            const deleteBtn = document.getElementById('deleteBtn');
            if (e.target.value === 'DELETE') {
                deleteBtn.disabled = false;
            } else {
                deleteBtn.disabled = true;
            }
        });
        
        function deleteAccount() {
            // In production, this would submit a form or make an AJAX request
            if (confirm('Final confirmation: Are you absolutely sure?')) {
                window.location.href = 'delete_account.php';
            }
        }
        
        // Client-side validation for Change Password form
        const changePwdForm = document.getElementById('changePasswordForm');
        if (changePwdForm) {
            changePwdForm.addEventListener('submit', function(e) {
                const current = document.getElementById('current_password').value;
                const newPass = document.getElementById('new_password').value;
                const confirmPass = document.getElementById('confirm_password').value;

                if (!current) {
                    alert('Please enter your current password');
                    e.preventDefault();
                    return;
                }

                if (newPass.length < 8) {
                    alert('New password must be at least 8 characters');
                    e.preventDefault();
                    return;
                }

                if (newPass !== confirmPass) {
                    alert('New passwords do not match');
                    e.preventDefault();
                    return;
                }
            });
        }

        // Sidebar Toggle Functionality
        const sidebarToggle = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('sidebar');
        let isModalOpen = false;
        
        function initSidebar() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!isModalOpen) {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                }
            });
        }
        
        // Click handler for sidebar outside
        function handleClick(e) {
            // Never collapse sidebar if a modal is open
            if (isModalOpen) return;
            
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.add('collapsed');
                    localStorage.setItem('sidebarCollapsed', true);
                }
            }
        }
        
        // Touch handler for sidebar outside
        function handleTouch(e) {
            // Never collapse sidebar if a modal is open
            if (isModalOpen) return;
            
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.add('collapsed');
                    localStorage.setItem('sidebarCollapsed', true);
                }
            }
        }
        
        // Attach listeners
        document.addEventListener('click', handleClick);
        document.addEventListener('touchstart', handleTouch);
        
        // Monitor all modals for open/close state
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                isModalOpen = true;
            });
            modal.addEventListener('hidden.bs.modal', function() {
                isModalOpen = false;
            });
        });
        
        initSidebar();
    </script>
</body>
</html>