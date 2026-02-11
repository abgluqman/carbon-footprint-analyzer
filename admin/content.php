<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';

// Handle content deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $contentId = intval($_GET['id']);
    
    $sql = "DELETE FROM educational_content WHERE content_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $contentId);
    
    if ($stmt->execute()) {
        $success = "Content deleted successfully";
    } else {
        $error = "Failed to delete content";
    }
}

// Get all educational content
$sql = "SELECT 
            ec.content_id,
            ec.title,
            ec.content_type,
            ec.emissions_level,
            ec.created_at,
            cat.category_name,
            a.name as admin_name
        FROM educational_content ec
        LEFT JOIN emissions_category cat ON ec.category_id = cat.category_id
        JOIN admin a ON ec.admin_id = a.admin_id
        ORDER BY ec.created_at DESC";
$contents = $conn->query($sql);

// Get categories for filter
$categories = $conn->query("SELECT * FROM emissions_category ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educational Content - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text"></i> Educational Content Management</h2>
            <a href="content_add.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Add New Content
            </a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Content Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="tip">Tips</option>
                            <option value="article">Articles</option>
                            <option value="guide">Guides</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Emission Level</label>
                        <select name="level" class="form-select">
                            <option value="">All Levels</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Content List -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <th