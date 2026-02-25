<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';

// ✅ SECURITY: Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    // ✅ SECURITY: Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $contentType = $_POST['content_type'];
    $emissionsLevel = !empty($_POST['emissions_level']) ? $_POST['emissions_level'] : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    
    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (strlen($title) > 150) $errors[] = "Title must be 150 characters or less";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($contentType)) $errors[] = "Content type is required";
    
    // ✅ SECURITY: Validate content_type against whitelist
    $allowedContentTypes = ['tip', 'article', 'video'];
    if (!in_array($contentType, $allowedContentTypes)) {
        $errors[] = "Invalid content type";
    }
    
    // ✅ SECURITY: Validate emissions_level against whitelist
    if ($emissionsLevel !== null) {
        $allowedLevels = ['Low', 'Medium', 'High'];
        if (!in_array($emissionsLevel, $allowedLevels)) {
            $errors[] = "Invalid emissions level";
        }
    }
    
    // Handle image removal request
    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';

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

        // ✅ SECURITY: Additional file validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['content_image']['tmp_name']);
        // Note: finfo_close() is deprecated in PHP 8.3+, resource is auto-closed
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($_FILES['content_image']['type'], $allowedTypes) || !in_array($mimeType, $allowedMimes)) {
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
            // New image uploaded — replace existing
            $sql = "UPDATE educational_content 
                    SET category_id = ?, title = ?, description = ?, 
                        content_type = ?, emissions_level = ?, content_image = ?
                    WHERE content_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssi", $categoryId, $title, $description, 
                            $contentType, $emissionsLevel, $imageData, $contentId);
        } elseif ($removeImage) {
            // Remove image — set to NULL
            $sql = "UPDATE educational_content 
                    SET category_id = ?, title = ?, description = ?, 
                        content_type = ?, emissions_level = ?, content_image = NULL
                    WHERE content_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssi", $categoryId, $title, $description, 
                            $contentType, $emissionsLevel, $contentId);
        } else {
            // No image change — keep existing
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
$categories = $conn->query("SELECT category_id, category_name FROM emissions_category ORDER BY category_name");
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
            <h2><i class="bi bi-pencil-square"></i> Edit Educational Content</h2>
            <a href="content.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Error:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <!-- ✅ SECURITY: XSS protection -->
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <!-- ✅ SECURITY: XSS protection -->
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <a href="content.php" class="alert-link">View all content</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- ✅ SECURITY: CSRF token -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($content['title']); ?>" 
                                       placeholder="Enter content title" required maxlength="150">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="8" placeholder="Enter content description" required><?php echo htmlspecialchars($content['description']); ?></textarea>
                                <small class="text-muted">Provide detailed information, tips, or guidance</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="content_type" name="content_type" required>
                                        <option value="">Select Type</option>
                                        <option value="tip" <?php echo ($content['content_type'] == 'tip') ? 'selected' : ''; ?>>
                                            Tip
                                        </option>
                                        <option value="article" <?php echo ($content['content_type'] == 'article') ? 'selected' : ''; ?>>
                                            Article
                                        </option>
                                        <option value="video" <?php echo ($content['content_type'] == 'video') ? 'selected' : ''; ?>>
                                            Video
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="emissions_level" class="form-label">Emission Level</label>
                                    <select class="form-select" id="emissions_level" name="emissions_level">
                                        <option value="">General (All Levels)</option>
                                        <option value="Low" <?php echo ($content['emissions_level'] == 'Low') ? 'selected' : ''; ?>>
                                            Low Emitters
                                        </option>
                                        <option value="Medium" <?php echo ($content['emissions_level'] == 'Medium') ? 'selected' : ''; ?>>
                                            Medium Emitters
                                        </option>
                                        <option value="High" <?php echo ($content['emissions_level'] == 'High') ? 'selected' : ''; ?>>
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
                                                <?php echo ($content['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted">Link to specific emission category or leave as general</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Image (Optional)</label>

                                <?php if ($content['content_image']): ?>
                                    <?php
                                        // Detect actual MIME type from binary data
                                        $finfo    = new finfo(FILEINFO_MIME_TYPE);
                                        $imgMime  = $finfo->buffer($content['content_image']);
                                        $allowed  = ['image/jpeg', 'image/png', 'image/gif'];
                                        $imgMime  = in_array($imgMime, $allowed) ? $imgMime : 'image/jpeg';
                                        $imgB64   = base64_encode($content['content_image']);
                                    ?>
                                    <!-- Current image preview -->
                                    <div class="mb-3 p-2 border rounded bg-light" id="currentImagePreview">
                                        <p class="text-muted small mb-2">
                                            <i class="bi bi-image"></i> Current image:
                                        </p>
                                        <img src="data:<?php echo $imgMime; ?>;base64,<?php echo $imgB64; ?>"
                                             alt="Current content image"
                                             class="img-fluid rounded"
                                             style="max-height: 250px; object-fit: contain;">
                                        <div class="mt-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="remove_image" id="remove_image" value="1"
                                                       onchange="toggleRemoveImage(this)">
                                                <label class="form-check-label text-danger small" for="remove_image">
                                                    <i class="bi bi-trash"></i> Remove current image
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <label for="content_image" class="form-label small text-muted">
                                    <?php echo $content['content_image'] ? 'Upload new image to replace:' : 'Upload image:'; ?>
                                </label>
                                <input type="file" class="form-control" id="content_image" name="content_image"
                                       accept="image/jpeg,image/png,image/gif"
                                       onchange="previewNewImage(this)">
                                <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF</small>

                                <!-- Preview of newly selected image -->
                                <div id="newImagePreview" class="mt-2" style="display:none;">
                                    <p class="text-muted small mb-1"><i class="bi bi-image"></i> New image preview:</p>
                                    <img id="newImagePreviewImg" src="" alt="New image preview"
                                         class="img-fluid rounded"
                                         style="max-height: 200px; object-fit: contain;">
                                </div>
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
                        
                        <h6 class="text-success mt-3">Videos</h6>
                        <ul class="small">
                            <li>Educational video content</li>
                            <li>Multimedia presentations</li>
                            <li>Step-by-step visual guides</li>
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
    <script>
        // Show preview of newly selected image
        function previewNewImage(input) {
            const preview = document.getElementById('newImagePreview');
            const previewImg = document.getElementById('newImagePreviewImg');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        // Dim current image preview when remove is checked
        function toggleRemoveImage(checkbox) {
            const currentPreview = document.getElementById('currentImagePreview');
            if (currentPreview) {
                currentPreview.style.opacity = checkbox.checked ? '0.3' : '1';
            }
        }
    </script>
</body>
</html>