<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';

//  Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// : Handle user deletion via POST (not GET)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    //  Validate CSRF token using hash_equals to prevent timing attacks
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    $userId = intval($_POST['user_id']);
    
    //  Additional authorization check 
    //  prevent deleting yourself or super admin
    if ($userId == $_SESSION['admin_id']) {
        $error = "You cannot delete your own account";
    } else {
        // Delete user (cascade will handle related records)
        $sql = "DELETE FROM user WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $success = "User deleted successfully";
        } else {
            $error = "Failed to delete user";
        }
    }
}

// Get all users with statistics
$sql = "SELECT 
            u.user_id,
            u.name,
            u.email,
            u.department,
            u.created_at,
            COUNT(DISTINCT er.record_id) as total_records,
            COALESCE(SUM(er.total_carbon_emissions), 0) as total_emissions
        FROM user u
        LEFT JOIN emissions_record er ON u.user_id = er.user_id
        GROUP BY u.user_id
        ORDER BY u.created_at DESC";
$users = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <!-- Main Content -->
    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h2><i class="bi bi-people"></i> User Management</h2>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th class="text-center">Records</th>
                                <th class="text-end">Total Emissions</th>
                                <th class="text-center">Joined</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <?php $safeUserId = intval($user['user_id']); ?>
                                <tr>
                                    <td><?php echo $safeUserId; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['department']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo intval($user['total_records']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($user['total_emissions'], 2); ?> kg CO₂
                                    </td>
                                    <td class="text-center">
                                        <small><?php echo date('d M Y', strtotime($user['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="user_details.php?id=<?php echo $safeUserId; ?>" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $safeUserId; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')"
                                                    title="Delete User">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!--  Delete Confirmation Modal with POST form -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteForm">
                    <!--  CSRF token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="delete_user" value="1">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete user <strong id="userName"></strong>?</p>
                        <p class="text-danger">
                            <i class="bi bi-exclamation-triangle"></i> 
                            This will permanently delete all their emission records and reports.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(userId, userName) {
            document.getElementById('userName').textContent = userName;
            document.getElementById('deleteUserId').value = userId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>