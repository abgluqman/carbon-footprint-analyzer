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

// Build query with filters
$whereClauses = [];
$params = [];
$types = "";

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $whereClauses[] = "ec.content_type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}

if (isset($_GET['level']) && !empty($_GET['level'])) {
    $whereClauses[] = "ec.emissions_level = ?";
    $params[] = $_GET['level'];
    $types .= "s";
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $whereClauses[] = "ec.category_id = ?";
    $params[] = intval($_GET['category']);
    $types .= "i";
}

// Get all educational content with filters
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
        JOIN admin a ON ec.admin_id = a.admin_id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY ec.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $contents = $stmt->get_result();
} else {
    $contents = $conn->query($sql);
}

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
    
    <!-- Main Content -->
    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
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
                            <option value="tip" <?php echo (isset($_GET['type']) && $_GET['type'] == 'tip') ? 'selected' : ''; ?>>Tips</option>
                            <option value="article" <?php echo (isset($_GET['type']) && $_GET['type'] == 'article') ? 'selected' : ''; ?>>Articles</option>
                            <option value="guide" <?php echo (isset($_GET['type']) && $_GET['type'] == 'guide') ? 'selected' : ''; ?>>Guides</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Emission Level</label>
                        <select name="level" class="form-select">
                            <option value="">All Levels</option>
                            <option value="Low" <?php echo (isset($_GET['level']) && $_GET['level'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo (isset($_GET['level']) && $_GET['level'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo (isset($_GET['level']) && $_GET['level'] == 'High') ? 'selected' : ''; ?>>High</option>
                            <option value="General" <?php echo (isset($_GET['level']) && $_GET['level'] == 'General') ? 'selected' : ''; ?>>General</option>
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
                                <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="content.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Content List -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if ($contents && $contents->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Level</th>
                                <th>Author</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($content = $contents->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $content['content_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst(htmlspecialchars($content['content_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $content['category_name'] ? htmlspecialchars($content['category_name']) : '<span class="text-muted">General</span>'; ?>
                                </td>
                                <td>
                                    <?php 
                                    $levelClass = 'secondary';
                                    if ($content['emissions_level'] == 'High') $levelClass = 'danger';
                                    elseif ($content['emissions_level'] == 'Medium') $levelClass = 'warning';
                                    elseif ($content['emissions_level'] == 'Low') $levelClass = 'success';
                                    ?>
                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                        <?php echo htmlspecialchars($content['emissions_level'] ?? 'General'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($content['admin_name']); ?></td>
                                <td>
                                    <small><?php echo date('d M Y', strtotime($content['created_at'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="content_edit.php?id=<?php echo $content['content_id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $content['content_id']; ?>, '<?php echo htmlspecialchars($content['title']); ?>')"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No Educational Content Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($_GET['type']) || !empty($_GET['level']) || !empty($_GET['category'])): ?>
                            Try adjusting your filters or <a href="content.php">clear all filters</a>.
                        <?php else: ?>
                            Start by adding your first educational content.
                        <?php endif; ?>
                    </p>
                    <a href="content_add.php" class="btn btn-success mt-2">
                        <i class="bi bi-plus-circle"></i> Add New Content
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="contentTitle"></strong>?</p>
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
        function confirmDelete(contentId, title) {
            document.getElementById('contentTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = '?delete=1&id=' + contentId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>