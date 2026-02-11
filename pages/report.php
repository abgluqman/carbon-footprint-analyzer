<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/pdf_generator.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recordId <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Verify record belongs to user
$sql = "SELECT record_id FROM emissions_record WHERE record_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $recordId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: dashboard.php");
    exit();
}

// Check if report already exists
$sql = "SELECT pdf_path FROM report WHERE record_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$reportResult = $stmt->get_result();
$existingReport = $reportResult->fetch_assoc();

$pdfPath = null;
$error = null;

// Generate new report if requested or doesn't exist
if (isset($_GET['generate']) || !$existingReport) {
    $pdfPath = generateCarbonReport($conn, $recordId, $userId);
    if (!$pdfPath) {
        $error = "Failed to generate report. Please try again.";
    }
} else {
    $pdfPath = $existingReport['pdf_path'];
}

// Handle download
if (isset($_GET['download']) && $pdfPath) {
    $fullPath = __DIR__ . '/../' . $pdfPath;
    if (file_exists($fullPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit();
    } else {
        $error = "Report file not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report - Carbon Footprint Analyzer</title>
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
                    <h1 class="h2">Carbon Footprint Report</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($pdfPath): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 4rem;"></i>
                                </div>
                                <div class="col-md-7">
                                    <h5 class="mb-1">Carbon Footprint Report</h5>
                                    <p class="text-muted mb-2">
                                        <small>
                                            <i class="bi bi-calendar"></i> 
                                            Generated: <?php echo date('d M Y, h:i A'); ?>
                                        </small>
                                    </p>
                                    <p class="mb-0">
                                        <small class="text-muted">
                                            This report contains your detailed carbon emissions analysis, 
                                            breakdown by category, historical trends, and personalized recommendations.
                                        </small>
                                    </p>
                                </div>
                                <div class="col-md-3 text-end">
                                    <a href="?id=<?php echo $recordId; ?>&download=1" 
                                       class="btn btn-success mb-2 w-100">
                                        <i class="bi bi-download"></i> Download PDF
                                    </a>
                                    <a href="?id=<?php echo $recordId; ?>&generate=1" 
                                       class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-arrow-clockwise"></i> Regenerate
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PDF Preview -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-eye"></i> Report Preview
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <iframe src="../<?php echo $pdfPath; ?>" 
                                    style="width: 100%; height: 800px; border: none;">
                            </iframe>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-file-earmark-x text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No Report Available</h4>
                            <p class="text-muted">Click the button below to generate your report</p>
                            <a href="?id=<?php echo $recordId; ?>&generate=1" class="btn btn-success">
                                <i class="bi bi-file-earmark-plus"></i> Generate Report
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