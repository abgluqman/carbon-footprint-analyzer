<?php
session_start();
require_once '../config/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get user's emission level for personalized content
$sql = "SELECT total_carbon_emissions 
        FROM emissions_record 
        WHERE user_id = ? 
        ORDER BY record_date DESC 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$lastRecord = $result->fetch_assoc();

$userLevel = 'General';
if ($lastRecord) {
    $emissions = $lastRecord['total_carbon_emissions'];
    if ($emissions < 50) $userLevel = 'Low';
    elseif ($emissions < 100) $userLevel = 'Medium';
    else $userLevel = 'High';
}

// Get filter parameters
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Build query
$sql = "SELECT 
            ec.content_id,
            ec.title,
            ec.description,
            ec.content_type,
            ec.emissions_level,
            ec.content_image,
            ec.created_at,
            cat.category_name
        FROM educational_content ec
        LEFT JOIN emissions_category cat ON ec.category_id = cat.category_id
        WHERE 1=1";

$params = [];
$types = '';

// Add filters
if ($filterType) {
    $sql .= " AND ec.content_type = ?";
    $params[] = $filterType;
    $types .= 's';
}

if ($filterCategory) {
    $sql .= " AND ec.category_id = ?";
    $params[] = $filterCategory;
    $types .= 'i';
}

$sql .= " ORDER BY 
            CASE 
                WHEN ec.emissions_level = ? THEN 1
                WHEN ec.emissions_level IS NULL THEN 2
                ELSE 3
            END,
            ec.created_at DESC";
$params[] = $userLevel;
$types .= 's';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$contents = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM emissions_category ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tips & Education - Carbon Footprint Analyzer</title>
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
                    <h1 class="h2">Tips & Educational Resources</h1>
                    <div class="text-muted">
                        Your emission level: 
                        <span class="badge bg-<?php 
                            echo $userLevel == 'Low' ? 'success' : ($userLevel == 'Medium' ? 'warning' : 'danger'); 
                        ?>">
                            <?php echo $userLevel; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Content Type</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="tip" <?php echo $filterType == 'tip' ? 'selected' : ''; ?>>
                                        Tips
                                    </option>
                                    <option value="article" <?php echo $filterType == 'article' ? 'selected' : ''; ?>>
                                        Articles
                                    </option>
                                    <option value="guide" <?php echo $filterType == 'guide' ? 'selected' : ''; ?>>
                                        Guides
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"
                                                <?php echo $filterCategory == $cat['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <a href="tips.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Content Grid -->
                <?php if ($contents->num_rows > 0): ?>
                    <div class="row g-4">
                        <?php while ($content = $contents->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <?php if ($content['content_image']): ?>
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($content['content_image']); ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($content['title']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-success bg-opacity-10 d-flex align-items-center justify-content-center" 
                                             style="height: 200px;">
                                            <i class="bi bi-lightbulb text-success" style="font-size: 4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($content['content_type']); ?>
                                            </span>
                                            <?php if ($content['emissions_level']): ?>
                                                <span class="badge bg-<?php 
                                                    echo $content['emissions_level'] == 'Low' ? 'success' : 
                                                        ($content['emissions_level'] == 'Medium' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $content['emissions_level']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h5 class="card-title"><?php echo htmlspecialchars($content['title']); ?></h5>
                                        
                                        <p class="card-text text-muted">
                                            <?php echo htmlspecialchars(substr($content['description'], 0, 150)) . '...'; ?>
                                        </p>
                                        
                                        <?php if ($content['category_name']): ?>
                                            <p class="small text-muted mb-2">
                                                <i class="bi bi-tag"></i> <?php echo htmlspecialchars($content['category_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-footer bg-white border-0">
                                        <button type="button" class="btn btn-outline-success w-100" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#contentModal<?php echo $content['content_id']; ?>">
                                            <i class="bi bi-book"></i> Read More
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Content Modal -->
                            <div class="modal fade" id="contentModal<?php echo $content['content_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($content['title']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if ($content['content_image']): ?>
                                                <img src="data:image/jpeg;base64,<?php echo base64_encode($content['content_image']); ?>" 
                                                     class="img-fluid mb-3 rounded" 
                                                     alt="<?php echo htmlspecialchars($content['title']); ?>">
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <span class="badge bg-info me-2">
                                                    <?php echo ucfirst($content['content_type']); ?>
                                                </span>
                                                <?php if ($content['category_name']): ?>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($content['category_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p style="white-space: pre-line;"><?php echo htmlspecialchars($content['description']); ?></p>
                                            
                                            <hr>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-calendar"></i> 
                                                Published: <?php echo date('d M Y', strtotime($content['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <h4 class="mt-3">No Content Found</h4>
                            <p class="text-muted">Try adjusting your filters or check back later for new content.</p>
                            <a href="tips.php" class="btn btn-success">
                                <i class="bi bi-arrow-clockwise"></i> Reset Filters
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>