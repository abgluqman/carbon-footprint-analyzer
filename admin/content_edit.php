<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';

$contentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$success = '';

if ($contentId <= 0) {
    header("Location: content.php");
    exit();
}

// Get existing content
$sql = "SELECT * FROM educational_content WHERE content_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contentId);
$stmt->execute();
$content = $stmt->get_result()->fetch_assoc();

if (!$content) {
    header("Location: content.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $contentType = $_POST['content_type'];
    $emissionsLevel = $_POST['emissions_level'];
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    
    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($contentType)) $errors[] = "Content type is required";
    
    // Handle image upload
    $updateImage = false;
    $imageData = null;
    
    if (isset($_FILES['content_image']) && $_FILES['content_image']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

        // Determine safe max size based on MySQL server's max_allowed_packet
        $mysqlMax = null;
        $res = $conn->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
        if ($res) {
            $row = $res->fetch_assoc();
            $mysqlMax = isset($row['Value']) ? intval($row['Value']) : null;
        }

        $defaultMax = 5 * 1024 * 1024; // 5MB
        if ($mysqlMax && $mysqlMax > 2048) {
            $maxSize = min($defaultMax, max(1024, $mysqlMax - 1024));
        } else {
            $maxSize = $defaultMax;
        }

        if (!in_array($_FILES['content_image']['type'], $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['content_image']['size'] > $maxSize) {
            $errors[] = "Image is too large. Maximum allowed by server: " . round($maxSize / 1024) . " KB. Please resize before uploading.";
        } else {
            $imageData = file_get_contents($_FILES['content_image']['tmp_name']);
            $updateImage = true;
        }
    }
    
    if (empty($errors)) {
        if ($updateImage) {
            $sql = "UPDATE educational_content 
                    SET category_id = ?, title = ?, description = ?, 
                        content_type = ?, emissions_level = ?, content_image = ?
                    WHERE content_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssi", $categoryId, $title, $description, 
                            $contentType, $emissionsLevel, $imageData, $contentId);
        } else {
            $sql = "UPDATE educational_content 
                    SET category_id = ?, title = ?, description = ?, 
                        content_type = ?, emissions_level = ?
                    WHERE content_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssi", $categoryId, $title, $description, 
                            $contentType, $emissionsLevel, $contentId);
        }
        
        if ($stmt->execute()) {
            $success = "Content updated successfully!";
            // Refresh content data
            $stmt = $conn->prepare("SELECT * FROM educational_content WHERE content_id = ?");
            $stmt->bind_param("i", $contentId);
            $stmt->execute();
            $content = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "Failed to update content. Please try again.";
        }
    }
}

// Get categories
$categories = $conn->query("SELECT * FROM emissions_category ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Content - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil"></i> Edit Educational Content</h2>
            <a href="content.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
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
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($content['title']); ?>" 
                                       placeholder="Enter content title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="8" placeholder="Enter content description" required><?php echo htmlspecialchars($content['description']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="content_type" name="content_type" required>
                                        <option value="">Select Type</option>
                                        <option value="tip" <?php echo $content['content_type'] == 'tip' ? 'selected' : ''; ?>>
                                            Tip
                                        </option>
                                        <option value="article" <?php echo $content['content_type'] == 'article' ? 'selected' : ''; ?>>
                                            Article
                                        </option>
                                        <option value="guide" <?php echo $content['content_type'] == 'guide' ? 'selected' : ''; ?>>
                                            Guide
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="emissions_level" class="form-label">Emission Level</label>
                                    <select class="form-select" id="emissions_level" name="emissions_level">
                                        <option value="">General (All Levels)</option>
                                        <option value="Low" <?php echo $content['emissions_level'] == 'Low' ? 'selected' : ''; ?>>
                                            Low Emitters
                                        </option>
                                        <option value="Medium" <?php echo $content['emissions_level'] == 'Medium' ? 'selected' : ''; ?>>
                                            Medium Emitters
                                        </option>
                                        <option value="High" <?php echo $content['emissions_level'] == 'High' ? 'selected' : ''; ?>>
                                            High Emitters
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">General (All Categories)</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"
                                                <?php echo ($content['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="content_image" class="form-label">Image</label>
                                <?php if ($content['content_image']): ?>
                                    <div class="mb-2">
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($content['content_image']); ?>" 
                                             class="img-thumbnail" style="max-width: 300px;">
                                        <p class="small text-muted mt-1">Current image (upload new to replace)</p>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="content_image" name="content_image" 
                                       accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-save"></i> Update Content
                                </button>
                                <a href="content.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-info-circle"></i> Content Information
                        </h5>
                        <hr>
                        <p class="mb-2">
                            <strong>Content ID:</strong> <?php echo $content['content_id']; ?>
                        </p>
                        <p class="mb-2">
                            <strong>Created:</strong> 
                            <?php echo date('d M Y, h:i A', strtotime($content['created_at'])); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Last Updated:</strong> 
                            <?php echo $success ? 'Just now' : 'N/A'; ?>
                        </p>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm mt-3 bg-danger bg-opacity-10">
                    <div class="card-body">
                        <h5 class="card-title text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Are You Sure?
                        </h5>
                        <hr>
                        <p class="small mb-3">
                            Deleting this content is permanent and cannot be undone.
                        </p>
                        <button type="button" class="btn btn-danger w-100" 
                                onclick="confirmDelete(<?php echo $content['content_id']; ?>)">
                            <i class="bi bi-trash"></i> Delete This Content
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this content?</p>
                    <p class="text-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Content</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(contentId) {
            document.getElementById('confirmDeleteBtn').href = 'content.php?delete=1&id=' + contentId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>