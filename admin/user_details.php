<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/emissions.php';
require_once 'auth_check.php';

// Get user ID from query string
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId == 0) {
    header("Location: users.php");
    exit();
}

// Get user information - ✅ ADDED: Include profile_photo
$sql = "SELECT * FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: users.php");
    exit();
}

// Get user statistics
$sql = "SELECT 
            COUNT(*) as total_records,
            COALESCE(SUM(total_carbon_emissions), 0) as total_emissions,
            COALESCE(AVG(total_carbon_emissions), 0) as avg_emissions,
            MIN(record_date) as first_record,
            MAX(record_date) as last_record
        FROM emissions_record
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get emission level distribution
$sql = "SELECT 
            CASE 
                WHEN total_carbon_emissions < 50 THEN 'Low'
                WHEN total_carbon_emissions < 100 THEN 'Medium'
                ELSE 'High'
            END as level,
            COUNT(*) as count
        FROM emissions_record
        WHERE user_id = ?
        GROUP BY level";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$levelResult = $stmt->get_result();
$levelData = ['Low' => 0, 'Medium' => 0, 'High' => 0];
while ($row = $levelResult->fetch_assoc()) {
    $levelData[$row['level']] = $row['count'];
}

// Get category breakdown
$sql = "SELECT ec.category_name, SUM(ed.emissions_value) as total
        FROM emissions_details ed
        JOIN emissions_record er ON ed.record_id = er.record_id
        JOIN emissions_category ec ON ed.category_id = ec.category_id
        WHERE er.user_id = ?
        GROUP BY ec.category_id
        ORDER BY total DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$categoryBreakdown = $stmt->get_result();

$categoryLabels = [];
$categoryData = [];
while ($row = $categoryBreakdown->fetch_assoc()) {
    $categoryLabels[] = $row['category_name'];
    $categoryData[] = round($row['total'], 2);
}

// Get recent emission records
$sql = "SELECT record_id, record_date, total_carbon_emissions, period
        FROM emissions_record
        WHERE user_id = ?
        ORDER BY record_date DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentRecords = $stmt->get_result();

// Get monthly trend (last 6 months)
$sql = "SELECT 
            DATE_FORMAT(record_date, '%Y-%m') as month,
            SUM(total_carbon_emissions) as total
        FROM emissions_record
        WHERE user_id = ?
        AND record_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(record_date, '%Y-%m')
        ORDER BY month ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$monthlyTrend = $stmt->get_result();

$trendLabels = [];
$trendData = [];
while ($row = $monthlyTrend->fetch_assoc()) {
    $trendLabels[] = date('M Y', strtotime($row['month'] . '-01'));
    $trendData[] = round($row['total'], 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ✅ ADDED: Profile photo styles */
        .profile-photo-admin {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #198754;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .default-avatar-admin {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <!-- Main Content -->
    <main class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h2><i class="bi bi-person-circle"></i> User Details</h2>
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>
        
        <!-- User Info Card -->
        <div class="row g-3 mb-4">
            <!-- ✅ UPDATED: User card with profile photo -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <!-- ✅ ADDED: Profile photo display -->
                        <div class="text-center mb-4">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($user['profile_photo']); ?>" 
                                     alt="<?php echo htmlspecialchars($user['name']); ?> Profile Photo" 
                                     class="profile-photo-admin">
                            <?php else: ?>
                                <div class="default-avatar-admin mx-auto">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mb-3">
                            <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        
                        <!-- ✅ ADDED: Phone number display -->
                        <hr>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Phone</small>
                            <p class="mb-0">
                                <i class="bi bi-telephone"></i>
                                <strong><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></strong>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Department</small>
                            <p class="mb-0"><strong><?php echo htmlspecialchars($user['department']); ?></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">User ID</small>
                            <p class="mb-0"><strong>#<?php echo $user['user_id']; ?></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Joined</small>
                            <p class="mb-0"><strong><?php echo date('d M Y', strtotime($user['created_at'])); ?></strong></p>
                        </div>
                        
                        <?php if ($stats['first_record']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">First Record</small>
                            <p class="mb-0"><strong><?php echo date('d M Y', strtotime($stats['first_record'])); ?></strong></p>
                        </div>
                        
                        <div>
                            <small class="text-muted d-block">Last Activity</small>
                            <p class="mb-0"><strong><?php echo date('d M Y', strtotime($stats['last_record'])); ?></strong></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="col-lg-8">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">
                                            <i class="bi bi-journal-text"></i> Total Records
                                        </h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['total_records']); ?></h2>
                                        <small class="text-muted">Emissions logged</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-journal-text text-info fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">
                                            <i class="bi bi-cloud"></i> Total Emissions
                                        </h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['total_emissions'], 2); ?></h2>
                                        <small class="text-muted">kg CO<sub>2</sub></small>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-cloud text-danger fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">
                                            <i class="bi bi-bar-chart"></i> Average per Record
                                        </h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['avg_emissions'], 2); ?></h2>
                                        <small class="text-muted">kg CO<sub>2</sub></small>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-bar-chart text-warning fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="text-muted mb-3">
                                    <i class="bi bi-diagram-3"></i> Emission Levels
                                </h6>
                                <div class="d-flex justify-content-between mb-2 align-items-center">
                                    <span class="badge bg-success">Low</span>
                                    <strong><?php echo $levelData['Low']; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 align-items-center">
                                    <span class="badge bg-warning">Medium</span>
                                    <strong><?php echo $levelData['Medium']; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-danger">High</span>
                                    <strong><?php echo $levelData['High']; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <?php if (!empty($trendData)): ?>
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Emissions Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> By Category</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Records -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Emission Records</h5>
            </div>
            <div class="card-body">
                <?php if ($recentRecords->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Record ID</th>
                                <th>Date</th>
                                <!-- ✅ ADDED: Period type column -->
                                <th>Period</th>
                                <th class="text-end">Emissions</th>
                                <th class="text-center">Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $recentRecords->fetch_assoc()): 
                                $recordPeriod = $record['period'] ?? 'daily';
                                $level = getEmissionLevel($record['total_carbon_emissions'], $recordPeriod);
                                $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $record['record_id']; ?></strong>
                                </td>
                                <td><?php echo date('d M Y', strtotime($record['record_date'])); ?></td>
                                <!-- ✅ ADDED: Period badge -->
                                <td>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-calendar-<?php echo $recordPeriod == 'daily' ? 'day' : ($recordPeriod == 'weekly' ? 'week' : '3'); ?>"></i>
                                        <?php echo ucfirst($recordPeriod); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo number_format($record['total_carbon_emissions'], 2); ?></strong> kg CO<sub>2</sub>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $levelClass; ?>"><?php echo $level; ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 2rem; color: #ccc;"></i>
                    <p class="text-muted mt-3">No emission records yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($trendData)): ?>
    <script>
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
                datasets: [{
                    label: 'Emissions (kg CO₂)',
                    data: <?php echo json_encode($trendData); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgb(75, 192, 192)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'kg CO₂'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });
        
        // Category Chart
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
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>