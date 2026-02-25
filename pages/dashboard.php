<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/emissions.php';
require_once '../functions/dashboard.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


// Matches admin emissions_records.php so both sides always show the same label.
function calcEmissionLevel(float $val): string {
    if ($val < 50)  return 'Low';
    if ($val < 100) return 'Medium';
    return 'High';
}

$userId = $_SESSION['user_id'];

// Generate CSRF token for destructive actions (e.g. delete record)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get dashboard data
$totalEmissions = getUserTotalEmissions($conn, $userId);
$currentMonthEmissions = getCurrentMonthEmissions($conn, $userId);
$previousMonthEmissions = getPreviousMonthEmissions($conn, $userId);
$currentMonthLevel = getCurrentMonthLevel($conn, $userId);
$emissionLevel = getLatestEmissionLevel($conn, $userId);
$highestCategory = getHighestEmissionCategory($conn, $userId);
$emissionHistory = getEmissionHistory($conn, $userId, 5);
$monthlyTrend = getMonthlyEmissionsTrend($conn, $userId, 6);
$categoryBreakdown = getCategoryBreakdown($conn, $userId);
$personalizedTips = getPersonalizedTips($conn, $userId);
$comparison = compareWithPreviousMonth($conn, $userId);
$latestRecord = getLatestEmissionRecord($conn, $userId);

// Calculate month-over-month change
$monthChange = 0;
$monthTrend = 'neutral';
if ($previousMonthEmissions > 0) {
    $monthChange = (($currentMonthEmissions - $previousMonthEmissions) / $previousMonthEmissions) * 100;
    $monthTrend = $monthChange > 0 ? 'up' : 'down';
}

// Get total records count — use prepared statement to prevent SQL injection
$totalRecordsStmt = $conn->prepare("SELECT COUNT(*) as total FROM emissions_record WHERE user_id = ?");
$totalRecordsStmt->bind_param("i", $userId);
$totalRecordsStmt->execute();
$totalRecords = $totalRecordsStmt->get_result()->fetch_assoc()['total'];

// Prepare data for Chart.js
// Build a lookup of month => total from the query results
$trendFromDb = [];
while ($row = $monthlyTrend->fetch_assoc()) {
    $trendFromDb[$row['month']] = round($row['total'], 2);
}

// Always generate all 6 months so the chart is never empty or uneven
$trendLabels = [];
$trendData   = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey      = date('Y-m', strtotime("-$i months"));
    $monthLabel    = date('M Y', strtotime("-$i months"));
    $trendLabels[] = $monthLabel;
    $trendData[]   = $trendFromDb[$monthKey] ?? 0;
}

$categoryLabels = [];
$categoryData = [];
$categoryBreakdown->data_seek(0); // Reset pointer
while ($row = $categoryBreakdown->fetch_assoc()) {
    $categoryLabels[] = $row['category_name'];
    $categoryData[] = round($row['total'], 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Carbon Footprint Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/modal.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Welcome Header -->
                <div class="py-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <p class="text-muted mb-2">
                                <?php 
                                // Display current date in full format
                                echo date('l, F jS Y'); 
                                ?>
                            </p>
                            <h1 class="display-6 fw-normal mb-3">
                                <?php 
                                // Dynamic greeting based on current time
                                $hour = (int)date('H');
                                if ($hour >= 5 && $hour < 12) {
                                    echo 'Good Morning';
                                } elseif ($hour >= 12 && $hour < 18) {
                                    echo 'Good Afternoon';
                                } else {
                                    echo 'Good Evening';
                                }
                                ?>
                            </h1>
                        </div>
                        <div class="text-end">
                            <?php 
                            // Dynamic emoji based on current time
                            $hour = (int)date('H');
                            if ($hour >= 5 && $hour < 12) {
                                // Morning: Sunrise
                                echo '<span style="font-size: 4rem;">🌅</span>';
                            } elseif ($hour >= 12 && $hour < 18) {
                                // Afternoon: Sun
                                echo '<span style="font-size: 4rem;">☀️</span>';
                            } elseif ($hour >= 18 && $hour < 21) {
                                // Evening: Sunset
                                echo '<span style="font-size: 4rem;">🌇</span>';
                            } else {
                                // Night: Moon
                                echo '<span style="font-size: 4rem;">🌙</span>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="fw-normal mb-3">Welcome!</h5>
                        <p class="text-muted mb-4" style="max-width: 800px;">
                            We help you understand your personal carbon emissions. 
                            By input some basic data, you'll gain valuable insights into your impact on climate change. 
                            Ready to take charge of your environmental impact? Let's explore your carbon footprint together.
                        </p>
                        <a href="calculator.php" class="btn btn-success btn-lg px-4">
                            <i class="bi bi-calculator"></i> Start Calculating
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Your emissions have been calculated and saved successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <script>
                        history.replaceState(null, '', 'dashboard.php');
                    </script>
                <?php endif; ?>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <!-- Current Month Emissions Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="text-muted mb-2">
                                            <i class="bi bi-calendar-month"></i> This Month
                                        </h6>
                                        <h2 class="mb-0"><?php echo number_format($currentMonthEmissions, 1); ?></h2>
                                        <small class="text-muted">kg CO₂</small>
                                        
                                        <?php if ($previousMonthEmissions > 0): ?>
                                            <div class="mt-2">
                                                <?php if ($monthTrend == 'up'): ?>
                                                    <small class="text-danger">
                                                        <i class="bi bi-arrow-up-short"></i>
                                                        <?php echo abs(round($monthChange, 1)); ?>% vs last month
                                                    </small>
                                                <?php elseif ($monthTrend == 'down'): ?>
                                                    <small class="text-success">
                                                        <i class="bi bi-arrow-down-short"></i>
                                                        <?php echo abs(round($monthChange, 1)); ?>% vs last month
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <i class="bi bi-dash"></i> No change
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-graph-up text-primary fs-4"></i>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top">
                                    <small class="text-muted">
                                        All-time: <strong><?php echo number_format($totalEmissions, 1); ?> kg</strong>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Last Month Emissions Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="text-muted mb-2">
                                            <i class="bi bi-calendar-check"></i> Last Month
                                        </h6>
                                        <h2 class="mb-0"><?php echo number_format($previousMonthEmissions, 1); ?></h2>
                                        <small class="text-muted">kg CO₂</small>
                                        
                                        <?php 
                                        $prevMonthLevel = calcEmissionLevel((float)$previousMonthEmissions);
                                        $prevLevelClass = $prevMonthLevel == 'Low' ? 'success' : ($prevMonthLevel == 'Medium' ? 'warning' : 'danger');
                                        ?>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo $prevLevelClass; ?> bg-opacity-25 text-<?php echo $prevLevelClass; ?>">
                                                <?php echo $prevMonthLevel; ?> Level
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-secondary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-calendar3 text-secondary fs-4"></i>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top">
                                    <small class="text-muted">
                                        <?php echo date('F Y', strtotime('-1 month')); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Month Level Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="text-muted mb-2">Latest Entry</h6>
                                        
                                        <?php if ($latestRecord): ?>
                                            <?php 
                                            $levelClass = $latestRecord['level'] == 'Low' ? 'success' : ($latestRecord['level'] == 'Medium' ? 'warning' : 'danger');
                                            ?>
                                            <h2 class="mb-1"><?php echo number_format($latestRecord['emissions'], 1); ?> kg</h2>
                                            <small class="text-muted">CO₂ Emissions</small>
                                            
                                            <div class="mt-3">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                                        <?php echo $latestRecord['level']; ?>
                                                    </span>
                                                    <span class="badge bg-secondary bg-opacity-25 text-dark">
                                                        <?php echo ucfirst($latestRecord['period']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar-event"></i>
                                                    <?php echo date('M j, Y', strtotime($latestRecord['date'])); ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted mb-0">No entries yet</p>
                                            <small class="text-muted">Start calculating your emissions</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-activity text-info fs-4"></i>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top">
                                    <a href="calculator.php" class="btn btn-sm btn-outline-success w-100">
                                        <i class="bi bi-plus-circle"></i> Add New Entry
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Highest Category Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Highest Emissions</h6>
                                        <h3 class="mb-0"><?php echo htmlspecialchars((string)$highestCategory); ?></h3>
                                        <small class="text-muted">Category</small>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="#categoryBreakdown" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row g-3 mb-4">
                    <!-- Emissions Trend Chart -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-up"></i> Emissions Trend (Last 6 Months)
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="emissionsTrendChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category Breakdown Chart -->
                    <div class="col-lg-4" id="categoryBreakdown">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-pie-chart"></i> Category Breakdown
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- History and Tips Row -->
                <div class="row g-3 mb-4">
                    <!-- Emission History -->
                    <div class="col-lg-7" id="historySection">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> Recent History
                                    <span class="badge bg-info ms-2"><?php echo $totalRecords; ?> total</span>
                                </h5>
                                <a href="history.php" class="btn btn-sm btn-outline-secondary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php 
                                $dashboardModalHtml = '';
                                if ($emissionHistory->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Date</th>
                                                    <th>Total Emissions</th>
                                                    <th>Level</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $count = 1;
                                                while ($record = $emissionHistory->fetch_assoc()): 
                                                    $level = calcEmissionLevel((float)$record['total_carbon_emissions']);
                                                    $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                                                    $safeRecordId = intval($record['record_id']);

                                                    // Get category breakdown for this record
                                                    $detailsSql = "SELECT ec.category_name, ed.emissions_value
                                                            FROM emissions_details ed
                                                            JOIN emissions_category ec ON ed.category_id = ec.category_id
                                                            WHERE ed.record_id = ?
                                                            ORDER BY ed.emissions_value DESC";
                                                    $detailsStmt = $conn->prepare($detailsSql);
                                                    $detailsStmt->bind_param("i", $record['record_id']);
                                                    $detailsStmt->execute();
                                                    $details = $detailsStmt->get_result();

                                                    // Build modal HTML
                                                    ob_start();
                                                    ?>
                                                    <!-- Details Modal for Record <?php echo $safeRecordId; ?> -->
                                                    <div class="modal" id="dashDetailsModal<?php echo $safeRecordId; ?>"
                                                         tabindex="-1"
                                                         aria-labelledby="dashDetailsModalLabel<?php echo $safeRecordId; ?>"
                                                         style="z-index: 9999;">
                                                        <div class="modal-dialog" style="z-index: 10000;">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="dashDetailsModalLabel<?php echo $safeRecordId; ?>">
                                                                        Emissions Details - <?php echo date('d M Y', strtotime($record['record_date'])); ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <div class="d-flex justify-content-between mb-2">
                                                                            <strong>Total Emissions:</strong>
                                                                            <span class="badge bg-<?php echo $levelClass; ?>">
                                                                                <?php echo number_format($record['total_carbon_emissions'], 2); ?> kg CO₂
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <h6 class="mb-3">Breakdown by Category:</h6>
                                                                    <div class="list-group">
                                                                        <?php if ($details->num_rows > 0): ?>
                                                                            <?php while ($detail = $details->fetch_assoc()): ?>
                                                                                <div class="list-group-item">
                                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                                        <span>
                                                                                            <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i>
                                                                                            <?php echo htmlspecialchars($detail['category_name']); ?>
                                                                                        </span>
                                                                                        <strong><?php echo number_format($detail['emissions_value'], 2); ?> kg CO₂</strong>
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
                                                    $dashboardModalHtml .= ob_get_clean();
                                                    $detailsStmt->close();
                                                ?>
                                                    <tr>
                                                        <td><?php echo $count++; ?></td>
                                                        <td><?php echo date('d M Y', strtotime($record['record_date'])); ?></td>
                                                        <td><?php echo number_format($record['total_carbon_emissions'], 2); ?> kg CO₂</td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $levelClass; ?>"><?php echo $level; ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-outline-primary view-details-btn"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#dashDetailsModal<?php echo $safeRecordId; ?>">
                                                                    <i class="bi bi-eye"></i> View
                                                                </button>
                                                                <a href="report.php?id=<?php echo $safeRecordId; ?>&amp;download=1" class="btn btn-sm btn-outline-success">
                                                                    <i class="bi bi-download"></i> Download
                                                                </a>
                                                                <!-- CSRF-protected delete form instead of bare GET link -->
                                                                <form method="POST" action="delete_record.php" style="display:inline;"
                                                                      onsubmit="return confirm('Are you sure you want to delete this record?');">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <input type="hidden" name="id" value="<?php echo $safeRecordId; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        <i class="bi bi-trash"></i> Delete
                                                                    </button>
                                                                </form>
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
                                        <p class="text-muted mt-3">No emission records yet</p>
                                        <a href="calculator.php" class="btn btn-success">
                                            <i class="bi bi-plus-circle"></i> Add Your First Entry
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personalized Tips -->
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightbulb"></i> Personalized Tips
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($personalizedTips)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($personalizedTips as $tip): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <?php if (!empty($tip['category_name'])): ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success mb-1" style="font-size:0.7rem;">
                                                                <i class="bi bi-tag-fill"></i>
                                                                <?php echo htmlspecialchars($tip['category_name']); ?>
                                                            </span><br>
                                                        <?php endif; ?>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($tip['title']); ?></h6>
                                                    </div>
                                                    <small><i class="bi bi-lightbulb-fill text-warning"></i></small>
                                                </div>
                                                <p class="mb-1 small text-muted">
                                                    <?php echo nl2br(htmlspecialchars($tip['description'])); ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <a href="tips.php" class="btn btn-sm btn-outline-success">
                                            View All Tips <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-lightbulb fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">Start tracking your emissions to get personalized tips!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- All Dashboard Detail Modals (Outside Main Content) -->
    <?php echo $dashboardModalHtml ?? ''; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Emissions Trend Chart
        const trendCtx = document.getElementById('emissionsTrendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
                datasets: [{
                    label: 'Total Emissions (kg CO₂)',
                    data: <?php echo json_encode($trendData); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' kg CO₂';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' kg';
                            }
                        }
                    }
                }
            }
        });
        
        // Category Breakdown Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categoryLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($categoryData); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value.toFixed(2) + ' kg CO₂ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Sidebar Toggle Functionality
        const sidebarToggle = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('sidebar');
        let isModalOpen = false;
        let modalOpeningInProgress = false;
        
        function initSidebar() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                if (!isModalOpen && !modalOpeningInProgress) {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                }
            });
        }
        
        // Handle clicks on modal trigger buttons specifically
        document.addEventListener('click', function(e) {
            const modalTrigger = e.target.closest('[data-bs-toggle="modal"]');
            if (modalTrigger) {
                modalOpeningInProgress = true;
                // Reset flag after modal has time to open
                setTimeout(() => {
                    modalOpeningInProgress = false;
                }, 500);
            }
        }, true); // Use capture phase
        
        // Click handler for sidebar collapse (only on main content area)
        function handleOutsideClick(e) {
            // NEVER run if modal is open or opening
            if (isModalOpen || modalOpeningInProgress) return;

            // Only collapse sidebar when clicking on safe areas
            const isMainContent = e.target.closest('main');
            const isClickableElement = e.target.closest('button, a, input, select, textarea, .table, .card, .btn, .form-control');
            
            // Only proceed if clicking in main content area but NOT on interactive elements
            if (isMainContent && !isClickableElement) {
                if (sidebar && !sidebar.classList.contains('collapsed')) {
                    if (!sidebar.contains(e.target) && !sidebarToggle?.contains(e.target)) {
                        sidebar.classList.add('collapsed');
                        localStorage.setItem('sidebarCollapsed', 'true');
                    }
                }
            }
        }

        // Use a delayed event listener to avoid conflicts
        setTimeout(() => {
            document.addEventListener('click', handleOutsideClick);
            document.addEventListener('touchstart', handleOutsideClick);
        }, 100);
        
        // Monitor all modals for open/close state
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                isModalOpen = true;
                modalOpeningInProgress = true;
            });
            modal.addEventListener('shown.bs.modal', function() {
                isModalOpen = true;
                modalOpeningInProgress = false;
            });
            modal.addEventListener('hide.bs.modal', function() {
                // Keep modal flag during hide animation
            });
            modal.addEventListener('hidden.bs.modal', function() {
                isModalOpen = false;
                modalOpeningInProgress = false;
            });
        });
        
        initSidebar();

        // MANUAL MODAL CONTROL - same as history.php
        document.addEventListener('DOMContentLoaded', function() {
            // Clean up any stuck modal states on load
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 0 && !document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                backdrops.forEach(el => el.remove());
            }

            let isModalOpening = false;
            let currentOpenModal = null;

            const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');

            modalTriggers.forEach(function(trigger) {
                trigger.removeAttribute('data-bs-toggle');

                const targetId = trigger.getAttribute('data-bs-target');
                const targetModal = document.querySelector(targetId);

                if (targetModal) {
                    targetModal.setAttribute('aria-hidden', 'true');

                    const modalInstance = new bootstrap.Modal(targetModal, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });

                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();

                        if (isModalOpening) return;

                        if (currentOpenModal && currentOpenModal !== modalInstance) {
                            currentOpenModal.hide();
                        }

                        isModalOpening = true;
                        modalInstance.show();
                        currentOpenModal = modalInstance;

                        setTimeout(function() { isModalOpening = false; }, 500);
                    }, { capture: true });

                    targetModal.addEventListener('shown.bs.modal', function() {
                        isModalOpening = false;
                        targetModal.setAttribute('aria-hidden', 'false');
                    });

                    targetModal.addEventListener('hidden.bs.modal', function() {
                        targetModal.setAttribute('aria-hidden', 'true');
                        currentOpenModal = null;
                    });
                }
            });
        });
    </script>
</body>
</html>