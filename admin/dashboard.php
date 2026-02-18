<?php
session_start();
require_once '../config/db_connection.php';
require_once 'auth_check.php';

// Get statistics
// Total users
$sql = "SELECT COUNT(*) as total FROM user";
$totalUsers = $conn->query($sql)->fetch_assoc()['total'];

// Total records
$sql = "SELECT COUNT(*) as total FROM emissions_record";
$totalRecords = $conn->query($sql)->fetch_assoc()['total'];

// Total emissions
$sql = "SELECT SUM(total_carbon_emissions) as total FROM emissions_record";
$totalEmissions = $conn->query($sql)->fetch_assoc()['total'] ?? 0;

// Average emissions per user
$avgEmissions = $totalUsers > 0 ? $totalEmissions / $totalUsers : 0;

// Emissions by level
$sql = "SELECT 
            CASE 
                WHEN total_carbon_emissions < 50 THEN 'Low'
                WHEN total_carbon_emissions < 100 THEN 'Medium'
                ELSE 'High'
            END as level,
            COUNT(*) as count
        FROM emissions_record
        GROUP BY level";
$levelResult = $conn->query($sql);
$levelData = ['Low' => 0, 'Medium' => 0, 'High' => 0];
while ($row = $levelResult->fetch_assoc()) {
    $levelData[$row['level']] = $row['count'];
}

// Recent users
$sql = "SELECT user_id, name, email, department, created_at 
        FROM user 
        ORDER BY created_at DESC 
        LIMIT 5";
$recentUsers = $conn->query($sql);

// Recent records
$sql = "SELECT er.record_id, er.record_date, er.total_carbon_emissions, 
               u.name as user_name
        FROM emissions_record er
        JOIN user u ON er.user_id = u.user_id
        ORDER BY er.record_date DESC
        LIMIT 5";
$recentRecords = $conn->query($sql);

// Monthly trend
$sql = "SELECT 
            DATE_FORMAT(record_date, '%b %Y') as month,
            COUNT(*) as records,
            SUM(total_carbon_emissions) as total_emissions
        FROM emissions_record
        WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(record_date, '%Y-%m')
        ORDER BY record_date ASC";
$monthlyTrend = $conn->query($sql);

$trendLabels = [];
$trendRecords = [];
$trendEmissions = [];
while ($row = $monthlyTrend->fetch_assoc()) {
    $trendLabels[] = $row['month'];
    $trendRecords[] = $row['records'];
    $trendEmissions[] = round($row['total_emissions'], 2);
}

// Category breakdown
$sql = "SELECT ec.category_name, SUM(ed.emissions_value) as total
        FROM emissions_details ed
        JOIN emissions_category ec ON ed.category_id = ec.category_id
        GROUP BY ec.category_id
        ORDER BY total DESC";
$categoryBreakdown = $conn->query($sql);

$categoryLabels = [];
$categoryData = [];
while ($row = $categoryBreakdown->fetch_assoc()) {
    $categoryLabels[] = $row['category_name'];
    $categoryData[] = round($row['total'], 2);
}

// Department breakdown
$sql = "SELECT u.department, COUNT(DISTINCT u.user_id) as users, 
               COUNT(er.record_id) as records,
               COALESCE(SUM(er.total_carbon_emissions), 0) as total_emissions
        FROM user u
        LEFT JOIN emissions_record er ON u.user_id = er.user_id
        GROUP BY u.department
        ORDER BY total_emissions DESC";
$departmentData = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Carbon Footprint Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <!-- Main Content -->
    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
            <div class="text-muted">
                <i class="bi bi-calendar"></i> <?php echo date('l, d F Y'); ?>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <!-- Total Users -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total Users</h6>
                                <h2 class="mb-0"><?php echo number_format($totalUsers); ?></h2>
                                <small class="text-muted">Registered accounts</small>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-people text-primary fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Records -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total Records</h6>
                                <h2 class="mb-0"><?php echo number_format($totalRecords); ?></h2>
                                <small class="text-muted">Emission entries</small>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-journal-text text-success fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Emissions -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total Emissions</h6>
                                <h2 class="mb-0"><?php echo number_format($totalEmissions, 0); ?></h2>
                                <small class="text-muted">kg CO₂</small>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="bi bi-cloud text-warning fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Average per User -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Average per User</h6>
                                <h2 class="mb-0"><?php echo number_format($avgEmissions, 1); ?></h2>
                                <small class="text-muted">kg CO₂</small>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-bar-chart text-info fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Emission Levels Overview -->
        <div class="row g-3 mb-4">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-speedometer"></i> Emissions Distribution by Level</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="p-3">
                                    <div class="bg-success bg-opacity-10 p-4 rounded mb-2">
                                        <i class="bi bi-check-circle text-success fs-1"></i>
                                    </div>
                                    <h3 class="text-success"><?php echo $levelData['Low']; ?></h3>
                                    <p class="text-muted mb-0">Low Emissions</p>
                                    <small class="text-muted">&lt; 50 kg CO₂</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3">
                                    <div class="bg-warning bg-opacity-10 p-4 rounded mb-2">
                                        <i class="bi bi-exclamation-circle text-warning fs-1"></i>
                                    </div>
                                    <h3 class="text-warning"><?php echo $levelData['Medium']; ?></h3>
                                    <p class="text-muted mb-0">Medium Emissions</p>
                                    <small class="text-muted">50-100 kg CO₂</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3">
                                    <div class="bg-danger bg-opacity-10 p-4 rounded mb-2">
                                        <i class="bi bi-x-circle text-danger fs-1"></i>
                                    </div>
                                    <h3 class="text-danger"><?php echo $levelData['High']; ?></h3>
                                    <p class="text-muted mb-0">High Emissions</p>
                                    <small class="text-muted">&gt; 100 kg CO₂</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <!-- Monthly Trend -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> 6-Month Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Category Breakdown -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Category Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Department Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Users</th>
                                        <th class="text-center">Records</th>
                                        <th class="text-end">Total Emissions</th>
                                        <th class="text-end">Avg per User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($dept = $departmentData->fetch_assoc()): 
                                        $avgPerUser = $dept['users'] > 0 ? $dept['total_emissions'] / $dept['users'] : 0;
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($dept['department']); ?></strong></td>
                                            <td class="text-center"><?php echo $dept['users']; ?></td>
                                            <td class="text-center"><?php echo $dept['records']; ?></td>
                                            <td class="text-end"><?php echo number_format($dept['total_emissions'], 2); ?> kg CO₂</td>
                                            <td class="text-end"><?php echo number_format($avgPerUser, 2); ?> kg CO₂</td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row g-3">
            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Recent Users</h5>
                        <a href="users.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php while ($user = $recentUsers->fetch_assoc()): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($user['department']); ?>
                                            </p>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Records -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Emissions</h5>
                        <a href="reports.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php while ($record = $recentRecords->fetch_assoc()): 
                                $level = $record['total_carbon_emissions'] < 50 ? 'Low' : 
                                        ($record['total_carbon_emissions'] < 100 ? 'Medium' : 'High');
                                $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                            ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($record['user_name']); ?></h6>
                                            <p class="mb-1 small">
                                                <strong><?php echo number_format($record['total_carbon_emissions'], 2); ?> kg CO₂</strong>
                                                <span class="badge bg-<?php echo $levelClass; ?> ms-2"><?php echo $level; ?></span>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <i class="bi bi-calendar"></i> 
                                                <?php echo date('d M Y', strtotime($record['record_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
                datasets: [
                    {
                        label: 'Total Emissions (kg CO₂)',
                        data: <?php echo json_encode($trendEmissions); ?>,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        yAxisID: 'y',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Number of Records',
                        data: <?php echo json_encode($trendRecords); ?>,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Emissions (kg CO₂)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Records Count'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        
        // Category Breakdown Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
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
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
    </main>
</body>
</html>