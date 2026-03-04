Here's your admin/content_edit.php with complete error handling and logging:
php<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';
require_once '../functions/error_handler.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$adminId = $_SESSION['admin_id'] ?? null;

//  Get and validate content ID
$contentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($contentId <= 0) {
    logSecurity('INVALID_CONTENT_ID_ACCESS', "Admin: $adminId, ID: " . ($_GET['id'] ?? 'none'));
    die('Invalid content ID');
}

try {
    $sql = "SELECT * FROM educational_content WHERE content_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        logError("Failed to prepare SELECT content", [
            'error' => $conn->error,
            'content_id' => $contentId
        ]);
        die('Database error occurred');
    }
    
    $stmt->bind_param("i", $contentId);
    
    if (!$stmt->execute()) {
        logError("Failed to execute SELECT content", [
            'error' => $stmt->error,
            'content_id' => $contentId
        ]);
        die('Database error occurred');
    }
    
    $result = $stmt->get_result();
    $content = $result->fetch_assoc();
    $stmt->close();
    
    if (!$content) {
        logSecurity('CONTENT_NOT_FOUND', "Admin: $adminId, ID: $contentId");
        die('Content not found');
    }
    
} catch (Exception $e) {
    logError("Exception fetching content", [
        'error' => $e->getMessage(),
        'content_id' => $contentId
    ]);
    die('An error occurred');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_CONTENT_EDIT', "Admin: $adminId, Content: $contentId");
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
    if (strlen($description) > 10000) $errors[] = "Description must be 10,000 characters or less";
    if (empty($contentType)) $errors[] = "Content type is required";
    
    //  Validate content_type/emissions_level against whitelist
    $allowedContentTypes = ['tip', 'article', 'video'];
    if (!in_array($contentType, $allowedContentTypes)) {
        $errors[] = "Invalid content type";
        logSecurity('INVALID_CONTENT_TYPE_EDIT', "Admin: $adminId, Content: $contentId, Attempted: $contentType");
    }
 
    if ($emissionsLevel !== null) {
        $allowedLevels = ['Low', 'Medium', 'High'];
        if (!in_array($emissionsLevel, $allowedLevels)) {
            $errors[] = "Invalid emissions level";
            logSecurity('INVALID_EMISSIONS_LEVEL_EDIT', "Admin: $adminId, Content: $contentId, Attempted: $emissionsLevel");
        }
    }
    
    //  Handle image upload with comprehensive error handling
    $imageData = null;
    $updateImage = false;
    
    if (isset($_FILES['content_image']) && $_FILES['content_image']['error'] == 0) {
        // Determine safe max size
        $mysqlMax = null;
        $res = $conn->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
        if ($res) {
            $row = $res->fetch_assoc();
            $mysqlMax = isset($row['Value']) ? intval($row['Value']) : null;
        } else {
            logError("Failed to get max_allowed_packet", ['error' => $conn->error]);
        }

        $defaultMax = 16 * 1024 * 1024;

        $columnMax = null;
        $colRes = $conn->query("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                                FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE()
                                  AND TABLE_NAME   = 'educational_content'
                                  AND COLUMN_NAME  = 'content_image'");
        if ($colRes && $colRow = $colRes->fetch_assoc()) {
            switch (strtolower($colRow['DATA_TYPE'])) {
                case 'tinyblob':   $columnMax =        255; break;
                case 'blob':       $columnMax =     65535; break;
                case 'mediumblob': $columnMax =  16777215; break;
                case 'longblob':   $columnMax = null;      break;
            }
        } else {
            logError("Failed to get column info for content_image", ['error' => $conn->error]);
        }

        $maxSize = $defaultMax;
        if ($mysqlMax && $mysqlMax > 2048) {
            $maxSize = min($maxSize, max(1024, $mysqlMax - 1024));
        }
        if ($columnMax !== null) {
            $maxSize = min($maxSize, $columnMax);
        }

        // Use finfo for MIME type detection
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['content_image']['tmp_name']);
        $imageInfo = @getimagesize($_FILES['content_image']['tmp_name']);

        if (!in_array($mimeType, $allowedMimes) || $imageInfo === false) {
            $errors[] = "Only valid JPG, PNG, and GIF images are allowed";
            logSecurity('INVALID_IMAGE_UPLOAD_EDIT', "Admin: $adminId, Content: $contentId, MIME: $mimeType");
        } elseif ($_FILES['content_image']['size'] > $maxSize) {
            $limitKB = round($maxSize / 1024);
            $errors[] = "Image is too large (limit: {$limitKB} KB)";
            logSecurity('OVERSIZED_IMAGE_UPLOAD_EDIT', sprintf(
                "Admin: %s, Content: %d, Size: %.2f KB, Limit: %.2f KB",
                $adminId,
                $contentId,
                $_FILES['content_image']['size'] / 1024,
                $maxSize / 1024
            ));
        } else {
            $imageData = @file_get_contents($_FILES['content_image']['tmp_name']);
            if ($imageData === false) {
                $errors[] = "Failed to read uploaded image";
                logError("Failed to read uploaded image file", [
                    'admin_id' => $adminId,
                    'content_id' => $contentId,
                    'tmp_name' => $_FILES['content_image']['tmp_name']
                ]);
                $imageData = null;
            } else {
                $updateImage = true;
            }
        }
    } elseif (isset($_FILES['content_image']) && $_FILES['content_image']['error'] != 4) {
        //  file upload errors
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        $errorCode = $_FILES['content_image']['error'];
        $errorMsg = $uploadErrors[$errorCode] ?? "Unknown error ($errorCode)";
        $errors[] = "Image upload failed: $errorMsg";
        
        logError("Image upload failed during edit", [
            'admin_id' => $adminId,
            'content_id' => $contentId,
            'error_code' => $errorCode,
            'error_msg' => $errorMsg
        ]);
    }
    
    // Only proceed if validation passed
    if (empty($errors)) {
        try {
            if ($updateImage) {
                $sql = "UPDATE educational_content 
                        SET category_id = ?, title = ?, description = ?, 
                            content_type = ?, emissions_level = ?, content_image = ?
                        WHERE content_id = ?";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    logError("Failed to prepare UPDATE with image", [
                        'error' => $conn->error,
                        'content_id' => $contentId
                    ]);
                    $errors[] = "Failed to update content. Please try again.";
                } else {
                    $stmt->bind_param("isssssi", $categoryId, $title, $description, 
                                     $contentType, $emissionsLevel, $imageData, $contentId);
                }
            } else {
                $sql = "UPDATE educational_content 
                        SET category_id = ?, title = ?, description = ?, 
                            content_type = ?, emissions_level = ?
                        WHERE content_id = ?";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    logError("Failed to prepare UPDATE without image", [
                        'error' => $conn->error,
                        'content_id' => $contentId
                    ]);
                    $errors[] = "Failed to update content. Please try again.";
                } else {
                    $stmt->bind_param("issssi", $categoryId, $title, $description, 
                                     $contentType, $emissionsLevel, $contentId);
                }
            }

            // Execute with error handling
            if (isset($stmt) && $stmt) {
                if ($stmt->execute()) {
                    logActivity($adminId, 'CONTENT_UPDATED', sprintf(
                        "ID: %d, Title: %s, Type: %s, Category: %s, Level: %s, Image Updated: %s",
                        $contentId,
                        substr($title, 0, 50),
                        $contentType,
                        $categoryId ?? 'none',
                        $emissionsLevel ?? 'none',
                        $updateImage ? 'yes' : 'no'
                    ));
                    
                    $success = "Content updated successfully!";
                    
                    $refreshStmt = $conn->prepare("SELECT * FROM educational_content WHERE content_id = ?");
                    if ($refreshStmt) {
                        $refreshStmt->bind_param("i", $contentId);
                        $refreshStmt->execute();
                        $content = $refreshStmt->get_result()->fetch_assoc();
                        $refreshStmt->close();
                    }
                } else {
                    logError("Failed to execute UPDATE educational_content", [
                        'error' => $stmt->error,
                        'errno' => $stmt->errno,
                        'admin_id' => $adminId,
                        'content_id' => $contentId,
                        'title' => $title
                    ]);
                    $errors[] = "Failed to update content. Please try again.";
                }
                
                $stmt->close();
            }
            
        } catch (mysqli_sql_exception $e) {
            if (str_contains($e->getMessage(), 'Data too long')) {
                $errors[] = "Image is too large for the database column.";
                logError("Image too large for database column during edit", [
                    'admin_id' => $adminId,
                    'content_id' => $contentId,
                    'error' => $e->getMessage(),
                    'image_size' => $imageData ? strlen($imageData) : 0
                ]);
            } else {
                $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
                logError("Exception while updating content", [
                    'admin_id' => $adminId,
                    'content_id' => $contentId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    } else {
        logActivity($adminId, 'CONTENT_EDIT_VALIDATION_FAILED', 
                   "Content: $contentId, Errors: " . implode(', ', $errors));
    }
}

try {
    $categories = $conn->query("SELECT category_id, category_name FROM emissions_category ORDER BY category_name");
    
    if (!$categories) {
        logError("Failed to fetch categories for edit", ['error' => $conn->error]);
        $categories = null;
    }
} catch (Exception $e) {
    logError("Exception fetching categories for edit", ['error' => $e->getMessage()]);
    $categories = null;
}
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