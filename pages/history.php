<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/emissions.php';
require_once '../functions/error_handler.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ ADDED: Whitelist period filter
$allowedPeriods = ['all', 'daily', 'weekly', 'monthly'];
$periodFilter = isset($_GET['period']) && in_array($_GET['period'], $allowedPeriods)
    ? $_GET['period']
    : 'all';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// ✅ MODIFIED: Get total records with period filter
if ($periodFilter === 'all') {
    $sql = "SELECT COUNT(*) as total FROM emissions_record WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
} else {
    $sql = "SELECT COUNT(*) as total FROM emissions_record WHERE user_id = ? AND period = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $periodFilter);
}

$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);
$stmt->close();

// ✅ MODIFIED: Get emission records with period filter
if ($periodFilter === 'all') {
    $sql = "SELECT record_id, user_id, record_date, total_carbon_emissions, period 
            FROM emissions_record 
            WHERE user_id = ? 
            ORDER BY record_date DESC, record_id DESC 
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $userId, $perPage, $offset);
} else {
    $sql = "SELECT record_id, user_id, record_date, total_carbon_emissions, period 
            FROM emissions_record 
            WHERE user_id = ? AND period = ?
            ORDER BY record_date DESC, record_id DESC 
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $userId, $periodFilter, $perPage, $offset);
}

if (!$stmt->execute()) {
    logError("Failed to fetch emission records", [
        'error' => $stmt->error,
        'user_id' => $userId,
        'period_filter' => $periodFilter
    ]);
    $records = null;
} else {
    $records = $stmt->get_result();
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emission History - Carbon Footprint Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        .period-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .badge-daily {
            background-color: #0d6efd;
            color: white;
        }
        
        .badge-weekly {
            background-color: #6610f2;
            color: white;
        }
        
        .badge-monthly {
            background-color: #d63384;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-clock-history"></i> Emission History
                    </h1>
                    <div>
                        <span class="text-muted">Total Records: <?php echo number_format($totalRecords); ?></span>
                    </div>
                </div>

                <!-- ✅ IMPROVED: Period Filter with better design -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-0">
                                    <i class="bi bi-funnel"></i> Filter by Period Type
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group w-100" role="group">
                                    <a href="?period=all" 
                                       class="btn btn-sm <?php echo $periodFilter == 'all' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        <i class="bi bi-collection"></i> All
                                        <?php 
                                        $allCount = $conn->query("SELECT COUNT(*) as c FROM emissions_record WHERE user_id = $userId")->fetch_assoc()['c'];
                                        echo $allCount > 0 ? "($allCount)" : '';
                                        ?>
                                    </a>
                                    <a href="?period=daily" 
                                       class="btn btn-sm <?php echo $periodFilter == 'daily' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        <i class="bi bi-calendar-day"></i> Daily
                                        <?php 
                                        $dailyCount = $conn->query("SELECT COUNT(*) as c FROM emissions_record WHERE user_id = $userId AND period = 'daily'")->fetch_assoc()['c'];
                                        echo $dailyCount > 0 ? "($dailyCount)" : '';
                                        ?>
                                    </a>
                                    <a href="?period=weekly" 
                                       class="btn btn-sm <?php echo $periodFilter == 'weekly' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        <i class="bi bi-calendar-week"></i> Weekly
                                        <?php 
                                        $weeklyCount = $conn->query("SELECT COUNT(*) as c FROM emissions_record WHERE user_id = $userId AND period = 'weekly'")->fetch_assoc()['c'];
                                        echo $weeklyCount > 0 ? "($weeklyCount)" : '';
                                        ?>
                                    </a>
                                    <a href="?period=monthly" 
                                       class="btn btn-sm <?php echo $periodFilter == 'monthly' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        <i class="bi bi-calendar3"></i> Monthly
                                        <?php 
                                        $monthlyCount = $conn->query("SELECT COUNT(*) as c FROM emissions_record WHERE user_id = $userId AND period = 'monthly'")->fetch_assoc()['c'];
                                        echo $monthlyCount > 0 ? "($monthlyCount)" : '';
                                        ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($records && $records->num_rows > 0): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date/Period</th>
                                            <th>Period Type</th>
                                            <th class="text-end">Total Emissions</th>
                                            <th class="text-center">Level</th>
                                            <th class="text-center">Details</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $modalHtml = '';
                                        while ($record = $records->fetch_assoc()):
                                            $recordPeriod = $record['period'] ?? 'daily';
                                            $level = getEmissionLevel((float)$record['total_carbon_emissions'], $recordPeriod);
                                            $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                                            $safeRecordId = intval($record['record_id']);
                                            
                                            // ✅ IMPROVED: Format date based on period type
                                            $dateDisplay = '';
                                            $dateCaption = '';
                                            
                                            switch ($recordPeriod) {
                                                case 'daily':
                                                    $dateDisplay = date('d M Y', strtotime($record['record_date']));
                                                    $dateCaption = date('l', strtotime($record['record_date'])); // Day name
                                                    break;
                                                    
                                                case 'weekly':
                                                    $dateTime = new DateTime($record['record_date']);
                                                    $weekStart = clone $dateTime;
                                                    $weekStart->modify('Monday this week');
                                                    $weekEnd = clone $weekStart;
                                                    $weekEnd->modify('+6 days');
                                                    $dateDisplay = $weekStart->format('d M') . ' - ' . $weekEnd->format('d M Y');
                                                    $dateCaption = 'Week ' . $dateTime->format('W, Y');
                                                    break;
                                                    
                                                case 'monthly':
                                                    $dateDisplay = date('F Y', strtotime($record['record_date']));
                                                    $dateCaption = date('M Y', strtotime($record['record_date']));
                                                    break;
                                            }
                                            
                                            // Get category breakdown
                                            $detailsSql = "SELECT ec.category_name, ed.emissions_value
                                                    FROM emissions_details ed
                                                    JOIN emissions_category ec ON ed.category_id = ec.category_id
                                                    WHERE ed.record_id = ?
                                                    ORDER BY ed.emissions_value DESC";
                                            $detailsStmt = $conn->prepare($detailsSql);
                                            $detailsStmt->bind_param("i", $record['record_id']);
                                            $detailsStmt->execute();
                                            $details = $detailsStmt->get_result();
                                            
                                            // Build modal
                                            ob_start();
                                            ?>
                                            <div class="modal fade" id="detailsModal<?php echo $safeRecordId; ?>" 
                                                 tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                <i class="bi bi-info-circle"></i> 
                                                                Emissions Details
                                                                <span class="badge badge-<?php echo $recordPeriod; ?> ms-2">
                                                                    <?php echo ucfirst($recordPeriod); ?>
                                                                </span>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <div class="d-flex justify-content-between mb-2">
                                                                    <strong>Period:</strong>
                                                                    <span><?php echo $dateDisplay; ?></span>
                                                                </div>
                                                                <div class="d-flex justify-content-between mb-2">
                                                                    <strong>Type:</strong>
                                                                    <span class="badge badge-<?php echo $recordPeriod; ?>">
                                                                        <i class="bi bi-calendar-<?php echo $recordPeriod == 'daily' ? 'day' : ($recordPeriod == 'weekly' ? 'week' : '3'); ?>"></i>
                                                                        <?php echo ucfirst($recordPeriod); ?>
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex justify-content-between mb-2">
                                                                    <strong>Total Emissions:</strong>
                                                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                                                        <?php echo number_format($record['total_carbon_emissions'], 2); ?> kg CO<sub>2</sub>
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex justify-content-between">
                                                                    <strong>Emission Level:</strong>
                                                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                                                        <?php echo $level; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            <h6 class="mb-3 mt-4">
                                                                <i class="bi bi-pie-chart"></i> Breakdown by Category:
                                                            </h6>
                                                            <div class="list-group">
                                                                <?php if ($details->num_rows > 0): ?>
                                                                    <?php while ($detail = $details->fetch_assoc()): ?>
                                                                        <div class="list-group-item">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <span>
                                                                                    <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i>
                                                                                    <?php echo htmlspecialchars($detail['category_name']); ?>
                                                                                </span>
                                                                                <strong><?php echo number_format($detail['emissions_value'], 2); ?> kg CO<sub>2</sub></strong>
                                                                            </div>
                                                                        </div>
                                                                    <?php endwhile; ?>
                                                                <?php else: ?>
                                                                    <div class="list-group-item">
                                                                        <small class="text-muted">No category breakdown available</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <a href="report.php?id=<?php echo $safeRecordId; ?>" class="btn btn-primary">
                                                                <i class="bi bi-file-pdf"></i> Generate Report
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                            $modalHtml .= ob_get_clean();
                                            $detailsStmt->close();
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($dateDisplay); ?></strong>
                                                    <?php if ($dateCaption): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($dateCaption); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <!-- ✅ ADDED: Period type badge -->
                                                    <span class="badge period-badge badge-<?php echo $recordPeriod; ?>">
                                                        <i class="bi bi-calendar-<?php echo $recordPeriod == 'daily' ? 'day' : ($recordPeriod == 'weekly' ? 'week' : '3'); ?>"></i>
                                                        <?php echo ucfirst($recordPeriod); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <strong><?php echo number_format($record['total_carbon_emissions'], 2); ?></strong>
                                                    <small class="text-muted">kg CO<sub>2</sub></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                                        <?php echo $level; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-info"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#detailsModal<?php echo $safeRecordId; ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="report.php?id=<?php echo $safeRecordId; ?>" 
                                                           class="btn btn-outline-primary" title="Generate Report">
                                                            <i class="bi bi-file-pdf"></i>
                                                        </a>
                                                        
                                                        <form method="POST" action="delete_record.php" style="display:inline;"
                                                              onsubmit="return confirm('Are you sure you want to delete this record? This action cannot be undone.');">
                                                            <input type="hidden" name="csrf_token" 
                                                                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="id" value="<?php echo $safeRecordId; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete Record">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?period=<?php echo $periodFilter; ?>&page=<?php echo $page - 1; ?>">
                                                    <i class="bi bi-chevron-left"></i> Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        if ($startPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?period=<?php echo $periodFilter; ?>&page=1">1</a>
                                            </li>
                                            <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?period=<?php echo $periodFilter; ?>&page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?period=<?php echo $periodFilter; ?>&page=<?php echo $totalPages; ?>">
                                                    <?php echo $totalPages; ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?period=<?php echo $periodFilter; ?>&page=<?php echo $page + 1; ?>">
                                                    Next <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <h4 class="mt-3">
                                <?php if ($periodFilter === 'all'): ?>
                                    No Emission Records Yet
                                <?php else: ?>
                                    No <?php echo ucfirst($periodFilter); ?> Records Found
                                <?php endif; ?>
                            </h4>
                            <p class="text-muted">
                                <?php if ($periodFilter === 'all'): ?>
                                    Start tracking your carbon footprint today!
                                <?php else: ?>
                                    Try selecting a different period filter or add new <?php echo $periodFilter; ?> records.
                                <?php endif; ?>
                            </p>
                            <a href="calculator.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add New Record
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php echo $modalHtml ?? ''; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>