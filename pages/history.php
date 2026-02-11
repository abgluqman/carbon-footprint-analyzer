<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/emissions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total records
$sql = "SELECT COUNT(*) as total FROM emissions_record WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get emission records with pagination
$sql = "SELECT * FROM emissions_record 
        WHERE user_id = ? 
        ORDER BY record_date DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $userId, $perPage, $offset);
$stmt->execute();
$records = $stmt->get_result();
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
                    <h1 class="h2">Emission History</h1>
                    <div>
                        <span class="text-muted">Total Records: <?php echo $totalRecords; ?></span>
                    </div>
                </div>
                
                <?php if ($records->num_rows > 0): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-end">Total Emissions</th>
                                            <th class="text-center">Level</th>
                                            <th class="text-center">Details</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($record = $records->fetch_assoc()): 
                                            $level = getEmissionLevel($record['total_carbon_emissions']);
                                            $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                                            
                                            // Get record details
                                            $sql = "SELECT ec.category_name, ed.emissions_value 
                                                    FROM emissions_details ed
                                                    JOIN emissions_category ec ON ed.category_id = ec.category_id
                                                    WHERE ed.record_id = ?
                                                    ORDER BY ed.emissions_value DESC";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->bind_param("i", $record['record_id']);
                                            $stmt->execute();
                                            $details = $stmt->get_result();
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('d M Y', strtotime($record['record_date'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('l', strtotime($record['record_date'])); ?>
                                                    </small>
                                                </td>
                                                <td class="text-end">
                                                    <strong><?php echo number_format($record['total_carbon_emissions'], 2); ?></strong>
                                                    <small class="text-muted">kg CO₂</small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?php echo $levelClass; ?>">
                                                        <?php echo $level; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#detailsModal<?php echo $record['record_id']; ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="report.php?id=<?php echo $record['record_id']; ?>" 
                                                           class="btn btn-outline-primary" title="Generate Report">
                                                            <i class="bi bi-file-pdf"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteRecord(<?php echo $record['record_id']; ?>)"
                                                                title="Delete Record">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Details Modal -->
                                            <div class="modal fade" id="detailsModal<?php echo $record['record_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                Emissions Details - <?php echo date('d M Y', strtotime($record['record_date'])); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <a href="report.php?id=<?php echo $record['record_id']; ?>" 
                                                               class="btn btn-primary">
                                                                <i class="bi bi-file-pdf"></i> Generate Report
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
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
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <h4 class="mt-3">No Emission Records Yet</h4>
                            <p class="text-muted">Start tracking your carbon footprint today!</p>
                            <a href="calculator.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add First Record
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteRecord(recordId) {
            if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                window.location.href = 'delete_record.php?id=' + recordId;
            }
        }
    </script>
</body>
</html>