<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/emissions.php';
require_once 'auth_check.php';

// Get filter parameters
$filterDepartment = isset($_GET['department']) && !empty($_GET['department']) ? $_GET['department'] : '';
$filterPeriod = isset($_GET['period']) && !empty($_GET['period']) ? $_GET['period'] : '';
$filterLevel = isset($_GET['level']) && !empty($_GET['level']) ? $_GET['level'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build WHERE clauses for filters
$whereClauses = [];
$params = [];
$types = "";

if ($filterDepartment) {
    $whereClauses[] = "u.department = ?";
    $params[] = $filterDepartment;
    $types .= "s";
}

if ($filterPeriod) {
    switch ($filterPeriod) {
        case 'today':
            $whereClauses[] = "DATE(er.record_date) = CURDATE()";
            break;
        case 'week':
            $whereClauses[] = "er.record_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereClauses[] = "er.record_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $whereClauses[] = "er.record_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
    }
}

if ($filterLevel) {
    switch ($filterLevel) {
        case 'Low':
            $whereClauses[] = "er.total_carbon_emissions < 50";
            break;
        case 'Medium':
            $whereClauses[] = "er.total_carbon_emissions >= 50 AND er.total_carbon_emissions < 100";
            break;
        case 'High':
            $whereClauses[] = "er.total_carbon_emissions >= 100";
            break;
    }
}

$whereSQL = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

// Get total records count with filters
$countSql = "SELECT COUNT(*) as total 
             FROM emissions_record er
             JOIN user u ON er.user_id = u.user_id" . $whereSQL;

if (!empty($params)) {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $totalRecords = $conn->query($countSql)->fetch_assoc()['total'];
}

$totalPages = ceil($totalRecords / $perPage);

// Get emission records with user info and filters
$sql = "SELECT er.record_id, er.record_date, er.total_carbon_emissions, 
               u.user_id, u.name as user_name, u.department
        FROM emissions_record er
        JOIN user u ON er.user_id = u.user_id" . 
        $whereSQL . "
        ORDER BY er.record_date DESC
        LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result();

// Get distinct departments for filter dropdown
$departments = $conn->query("SELECT DISTINCT department FROM user ORDER BY department");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emission Records - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <!-- Main Content -->
    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h2><i class="bi bi-clock-history"></i> All Emission Records</h2>
            <div class="text-muted">
                Total: <?php echo number_format($totalRecords); ?> records
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                        <?php echo ($filterDepartment == $dept['department']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Period</label>
                        <select name="period" class="form-select">
                            <option value="">All Time</option>
                            <option value="today" <?php echo ($filterPeriod == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo ($filterPeriod == 'week') ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo ($filterPeriod == 'month') ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo ($filterPeriod == 'year') ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Emission Level</label>
                        <select name="level" class="form-select">
                            <option value="">All Levels</option>
                            <option value="Low" <?php echo ($filterLevel == 'Low') ? 'selected' : ''; ?>>Low (&lt; 50 kg)</option>
                            <option value="Medium" <?php echo ($filterLevel == 'Medium') ? 'selected' : ''; ?>>Medium (50-100 kg)</option>
                            <option value="High" <?php echo ($filterLevel == 'High') ? 'selected' : ''; ?>>High (&gt; 100 kg)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="emissions_records.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if ($records->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Record ID</th>
                                <th>Date</th>
                                <th>User</th>
                                <th>Department</th>
                                <th class="text-end">Total Emissions</th>
                                <th class="text-center">Level</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $records->fetch_assoc()): 
                                $level = getEmissionLevel($record['total_carbon_emissions']);
                                $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><strong>#<?php echo $record['record_id']; ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($record['record_date'])); ?></td>
                                <td>
                                    <a href="user_details.php?id=<?php echo $record['user_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($record['user_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($record['department']); ?></td>
                                <td class="text-end">
                                    <strong><?php echo number_format($record['total_carbon_emissions'], 2); ?></strong> kg CO₂
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                        <?php echo $level; ?>
                                    </span>
                                </td>
                                
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): 
                    // Build query string for pagination links
                    $queryParams = [];
                    if ($filterDepartment) $queryParams[] = 'department=' . urlencode($filterDepartment);
                    if ($filterPeriod) $queryParams[] = 'period=' . urlencode($filterPeriod);
                    if ($filterLevel) $queryParams[] = 'level=' . urlencode($filterLevel);
                    $queryString = !empty($queryParams) ? '&' . implode('&', $queryParams) : '';
                ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1<?php echo $queryString; ?>">1</a></li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $queryString; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>"><?php echo $totalPages; ?></a></li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No Emission Records Yet</h5>
                    <p class="text-muted">Records will appear here once users start tracking their emissions.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>