<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $contentType = $_POST['content_type'];
    $emissionsLevel = $_POST['emissions_level'];
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $adminId = $_SESSION['admin_id'];
    
    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($contentType)) $errors[] = "Content type is required";
    
    // Handle image upload
    $imageData = null;
    if (isset($_FILES['content_image']) && $_FILES['content_image']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['content_image']['type'], $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['content_image']['size'] > $maxSize) {
            $errors[] = "Image size must be less than 5MB";
        } else {
            $imageData = file_get_contents($_FILES['content_image']['tmp_name']);
        }
    }
    
    if (empty($errors)) {
        $sql = "INSERT INTO educational_content 
                (admin_id, category_id, title, description, content_type, emissions_level, content_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssss", $adminId, $categoryId, $title, $description, $contentType, $emissionsLevel, $imageData);
        
        if ($stmt->execute()) {
            $success = "Content added successfully!";
            // Clear form
            $_POST = array();
        } else {
            $errors[] = "Failed to add content. Please try again.";
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
    <title>Add Content - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-plus-circle"></i> Add Educational Content</h2>
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
                <a href="content.php" class="alert-link">View all content</a>
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
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                       placeholder="Enter content title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="8" placeholder="Enter content description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <small class="text-muted">Provide detailed information, tips, or guidance</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="content_type" name="content_type" required>
                                        <option value="">Select Type</option>
                                        <option value="tip" <?php echo (isset($_POST['content_type']) && $_POST['content_type'] == 'tip') ? 'selected' : ''; ?>>
                                            Tip
                                        </option>
                                        <option value="article" <?php echo (isset($_POST['content_type']) && $_POST['content_type'] == 'article') ? 'selected' : ''; ?>>
                                            Article
                                        </option>
                                        <option value="guide" <?php echo (isset($_POST['content_type']) && $_POST['content_type'] == 'guide') ? 'selected' : ''; ?>>
                                            Guide
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="emissions_level" class="form-label">Emission Level</label>
                                    <select class="form-select" id="emissions_level" name="emissions_level">
                                        <option value="">General (All Levels)</option>
                                        <option value="Low" <?php echo (isset($_POST['emissions_level']) && $_POST['emissions_level'] == 'Low') ? 'selected' : ''; ?>>
                                            Low Emitters
                                        </option>
                                        <option value="Medium" <?php echo (isset($_POST['emissions_level']) && $_POST['emissions_level'] == 'Medium') ? 'selected' : ''; ?>>
                                            Medium Emitters
                                        </option>
                                        <option value="High" <?php echo (isset($_POST['emissions_level']) && $_POST['emissions_level'] == 'High') ? 'selected' : ''; ?>>
                                            High Emitters
                                        </option>
                                    </select>
                                    <small class="text-muted">Target specific emission levels for personalized tips</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">General (All Categories)</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted">Link to specific emission category or leave as general</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="content_image" class="form-label">Image (Optional)</label>
                                <input type="file" class="form-control" id="content_image" name="content_image" 
                                       accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-save"></i> Save Content
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
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-info-circle"></i> Content Guidelines
                        </h5>
                        <hr>
                        
                        <h6 class="text-success">Tips</h6>
                        <ul class="small">
                            <li>Short, actionable advice (50-150 words)</li>
                            <li>Focus on one specific action</li>
                            <li>Include measurable benefits when possible</li>
                        </ul>
                        
                        <h6 class="text-success mt-3">Articles</h6>
                        <ul class="small">
                            <li>In-depth educational content (200-500 words)</li>
                            <li>Explain concepts and research</li>
                            <li>Provide context and examples</li>
                        </ul>
                        
                        <h6 class="text-success mt-3">Guides</h6>
                        <ul class="small">
                            <li>Step-by-step instructions (300+ words)</li>
                            <li>Comprehensive how-to content</li>
                            <li>Include multiple actionable steps</li>
                        </ul>
                        
                        <hr>
                        
                        <h6 class="text-success">Best Practices</h6>
                        <ul class="small mb-0">
                            <li>Use clear, simple language</li>
                            <li>Be specific and actionable</li>
                            <li>Include relevant statistics when available</li>
                            <li>Keep user motivation in mind</li>
                            <li>Review for accuracy before publishing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>