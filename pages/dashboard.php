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

$userId = $_SESSION['user_id'];

// Get dashboard data
$totalEmissions = getUserTotalEmissions($conn, $userId);
$emissionLevel = getLatestEmissionLevel($conn, $userId);
$highestCategory = getHighestEmissionCategory($conn, $userId);
$emissionHistory = getEmissionHistory($conn, $userId, 5);
$monthlyTrend = getMonthlyEmissionsTrend($conn, $userId, 6);
$categoryBreakdown = getCategoryBreakdown($conn, $userId);
$personalizedTips = getPersonalizedTips($conn, $userId);
$comparison = compareWithPreviousMonth($conn, $userId);

// Prepare data for Chart.js
$trendLabels = [];
$trendData = [];
while ($row = $monthlyTrend->fetch_assoc()) {
    $trendLabels[] = date('M Y', strtotime($row['month'] . '-01'));
    $trendData[] = round($row['total'], 2);
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="calculator.php" class="btn btn-sm btn-success">
                                <i class="bi bi-calculator"></i> New Entry
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Your emissions have been calculated and saved successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <!-- Total Emissions Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Emissions</h6>
                                        <h3 class="mb-0"><?php echo number_format($totalEmissions, 2); ?></h3>
                                        <small class="text-muted">kg CO₂</small>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-cloud text-primary fs-4"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#totalEmissionsModal">
                                        <i class="bi bi-download"></i> Export
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emissions Level Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Emissions Level</h6>
                                        <h3 class="mb-0">
                                            <?php 
                                            $levelClass = $emissionLevel == 'Low' ? 'success' : ($emissionLevel == 'Medium' ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $levelClass; ?>"><?php echo $emissionLevel; ?></span>
                                        </h3>
                                        <small class="text-muted">Current status</small>
                                    </div>
                                    <div class="bg-<?php echo $levelClass; ?> bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-speedometer2 text-<?php echo $levelClass; ?> fs-4"></i>
                                    </div>
                                </div>
                                <?php if ($comparison): ?>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <?php if ($comparison['trend'] == 'up'): ?>
                                                <i class="bi bi-arrow-up text-danger"></i>
                                                <span class="text-danger"><?php echo abs(round($comparison['change'], 1)); ?>% increase</span>
                                            <?php else: ?>
                                                <i class="bi bi-arrow-down text-success"></i>
                                                <span class="text-success"><?php echo abs(round($comparison['change'], 1)); ?>% decrease</span>
                                            <?php endif; ?>
                                            from last month
                                        </small>
                                    </div>
                                <?php endif; ?>
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
                                        <h3 class="mb-0"><?php echo $highestCategory; ?></h3>
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
                    
                    <!-- Records Count Card -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Records</h6>
                                        <h3 class="mb-0"><?php echo $emissionHistory->num_rows; ?></h3>
                                        <small class="text-muted">Entries logged</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-journal-text text-info fs-4"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="#historySection" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-clock-history"></i> View History
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
                                </h5>
                                <a href="history.php" class="btn btn-sm btn-outline-secondary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if ($emissionHistory->num_rows > 0): ?>
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
                                                    $level = getEmissionLevel($record['total_carbon_emissions']);
                                                    $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
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
                                                                <a href="report.php?id=<?php echo $record['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye"></i> View
                                                                </a>
                                                                <a href="report.php?id=<?php echo $record['id']; ?>&download=1" 
                                                                   class="btn btn-sm btn-outline-success">
                                                                    <i class="bi bi-download"></i> Download
                                                                </a> 
                                                                <a href="delete_record.php?id=<?php echo $record['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this record?');">
                                                                    <i class="bi bi-trash"></i> Delete
                                                                <a>
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
                                <?php if ($personalizedTips->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while ($tip = $personalizedTips->fetch_assoc()): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($tip['title']); ?></h6>
                                                    <small><i class="bi bi-lightbulb-fill text-warning"></i></small>
                                                </div>
                                                <p class="mb-1 small text-muted">
                                                    <?php echo htmlspecialchars(substr($tip['description'], 0, 100)) . '...'; ?>
                                                </p>
                                            </div>
                                        <?php endwhile; ?>
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
        
        function initSidebar() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }
        
        initSidebar();
    </script>
</body>
</html>