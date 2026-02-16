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

// Get period filter
$periodFilter = isset($_GET['period']) ? $_GET['period'] : 'daily';

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
    <style>
        /* AGGRESSIVE MODAL FIX - Force stability */
        .modal {
            pointer-events: auto !important;
            transition: none !important; /* Remove ALL transitions */
            animation: none !important; /* Remove ALL animations */
        }
        
        .modal.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            overflow: auto !important;
        }
        
        .modal-backdrop {
            z-index: 9998 !important;
            transition: none !important;
            animation: none !important;
        }
        
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
        
        .modal-dialog {
            pointer-events: auto !important;
            position: relative !important;
            margin: 1.75rem auto !important;
            transition: none !important;
            animation: none !important;
        }
        
        .modal-content {
            position: relative !important;
            transition: none !important;
            animation: none !important;
        }
        
        /* Prevent table hover from interfering */
        .table-hover tbody tr:hover {
            cursor: pointer;
        }
        
        /* Ensure modal buttons work */
        .modal button, .modal a {
            pointer-events: auto !important;
        }
        
        /* Prevent any transforms or opacity changes */
        .modal * {
            transition: none !important;
            animation: none !important;
        }
    </style>
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

                <!-- Period Filter -->
                <div class="mb-4">
                    <div class="btn-group" role="group">
                        <a href="?period=daily" class="btn btn-sm <?php echo $periodFilter == 'daily' ? 'btn-success' : 'btn-outline-success'; ?>">
                            <i class="bi bi-calendar-day"></i> Daily
                        </a>
                        <a href="?period=weekly" class="btn btn-sm <?php echo $periodFilter == 'weekly' ? 'btn-success' : 'btn-outline-success'; ?>">
                            <i class="bi bi-calendar-week"></i> Weekly
                        </a>
                        <a href="?period=monthly" class="btn btn-sm <?php echo $periodFilter == 'monthly' ? 'btn-success' : 'btn-outline-success'; ?>">
                            <i class="bi bi-calendar3"></i> Monthly
                        </a>
                    </div>
                </div>
                
                <?php if ($records->num_rows > 0): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>
                                                <?php 
                                                    $dateHeader = 'Date';
                                                    if ($periodFilter == 'weekly') $dateHeader = 'Week';
                                                    elseif ($periodFilter == 'monthly') $dateHeader = 'Month';
                                                    echo $dateHeader;
                                                ?>
                                            </th>
                                            <th class="text-end">Total Emissions</th>
                                            <th class="text-center">Level</th>
                                            <th class="text-center">Details</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $modalHtml = ''; // Store modal HTML
                                        while ($record = $records->fetch_assoc()):
                                            $level = getEmissionLevel($record['total_carbon_emissions']);
                                            $levelClass = $level == 'Low' ? 'success' : ($level == 'Medium' ? 'warning' : 'danger');
                                            
                                            // Format date display based on period
                                            $dateDisplay = date('d M Y', strtotime($record['record_date']));
                                            $dateCaption = date('l', strtotime($record['record_date']));
                                            
                                            if ($periodFilter == 'weekly') {
                                                $dateTime = new DateTime($record['record_date']);
                                                $weekStart = clone $dateTime;
                                                $weekStart->modify('Monday this week');
                                                $weekEnd = clone $weekStart;
                                                $weekEnd->modify('+6 days');
                                                $dateDisplay = $weekStart->format('d M') . ' - ' . $weekEnd->format('d M Y');
                                                $dateCaption = 'Week ' . $dateTime->format('W');
                                            } elseif ($periodFilter == 'monthly') {
                                                $dateDisplay = date('F Y', strtotime($record['record_date']));
                                                $dateCaption = '';
                                            }
                                            
                                            // Get record details for modal
                                            $detailsSql = "SELECT ec.category_name, ed.emissions_value
                                                    FROM emissions_details ed
                                                    JOIN emissions_category ec ON ed.category_id = ec.category_id
                                                    WHERE ed.record_id = ?
                                                    ORDER BY ed.emissions_value DESC";
                                            $detailsStmt = $conn->prepare($detailsSql);
                                            $detailsStmt->bind_param("i", $record['record_id']);
                                            $detailsStmt->execute();
                                            $details = $detailsStmt->get_result();
                                            
                                            // Build modal HTML and store it
                                            ob_start();
                                            ?>
                                            <!-- Details Modal for Record <?php echo $record['record_id']; ?> -->
                                            <div class="modal" id="detailsModal<?php echo $record['record_id']; ?>" 
                                                 tabindex="-1" 
                                                 aria-labelledby="detailsModalLabel<?php echo $record['record_id']; ?>"
                                                 style="z-index: 9999;">
                                                <div class="modal-dialog" style="z-index: 10000;">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="detailsModalLabel<?php echo $record['record_id']; ?>">
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
                                                            <a href="report.php?id=<?php echo $record['record_id']; ?>" class="btn btn-primary">
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
                                                    <strong><?php echo $dateDisplay; ?></strong>
                                                    <?php if ($dateCaption): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo $dateCaption; ?></small>
                                                    <?php endif; ?>
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
                                                    <button type="button" class="btn btn-sm btn-outline-info view-details-btn"
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
    
    <!-- All Modals Rendered Here (Outside Main Content) -->
    <?php echo $modalHtml ?? ''; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteRecord(recordId) {
            if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                window.location.href = 'delete_record.php?id=' + recordId;
            }
        }

        // MANUAL MODAL CONTROL - Completely bypass Bootstrap's automatic triggering
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing modal fix...');
            
            // Clean up any stuck modal states
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 0 && !document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                backdrops.forEach(el => el.remove());
            }

            // Debounce mechanism
            let isModalOpening = false;
            let currentOpenModal = null;

            // Get all modal trigger buttons
            const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
            console.log('Found', modalTriggers.length, 'modal triggers');
            
            // Manually handle modal opening
            modalTriggers.forEach((trigger, index) => {
                // Remove Bootstrap's automatic handling
                trigger.removeAttribute('data-bs-toggle');
                
                // Get the target modal ID
                const targetId = trigger.getAttribute('data-bs-target');
                const targetModal = document.querySelector(targetId);
                
                if (targetModal) {
                    // Set initial aria-hidden
                    targetModal.setAttribute('aria-hidden', 'true');
                    
                    // Create Bootstrap Modal instance
                    const modalInstance = new bootstrap.Modal(targetModal, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                    
                    // Add single click handler with debounce
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        // Debounce - prevent multiple rapid clicks
                        if (isModalOpening) {
                            console.log('Modal already opening, ignoring click');
                            return;
                        }
                        
                        // If another modal is open, close it first
                        if (currentOpenModal && currentOpenModal !== modalInstance) {
                            currentOpenModal.hide();
                        }
                        
                        isModalOpening = true;
                        console.log('Opening modal:', index);
                        
                        modalInstance.show();
                        currentOpenModal = modalInstance;
                        
                        // Reset flag after modal opens
                        setTimeout(() => {
                            isModalOpening = false;
                        }, 500);
                    }, { capture: true });
                }
            });
            
            // Log modal events and manage aria-hidden
            const modals = document.querySelectorAll('.modal');
            console.log('Found', modals.length, 'modals');
            
            modals.forEach((modal, index) => {
                modal.addEventListener('show.bs.modal', function(e) {
                    console.log('Modal showing:', index);
                    console.trace('Show called from:'); // This will show the call stack
                    modal.setAttribute('aria-hidden', 'false');
                });
                
                modal.addEventListener('shown.bs.modal', function() {
                    console.log('Modal shown:', index);
                    isModalOpening = false;
                });
                
                modal.addEventListener('hide.bs.modal', function(e) {
                    console.log('Modal hiding:', index);
                    console.trace('Hide called from:'); // This will show what's closing it
                });
                
                modal.addEventListener('hidden.bs.modal', function() {
                    console.log('Modal hidden:', index);
                    modal.setAttribute('aria-hidden', 'true');
                    if (currentOpenModal) {
                        currentOpenModal = null;
                    }
                });
            });
        });

        // Sidebar Toggle Functionality - MANUAL ONLY
        const sidebarToggle = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('sidebar');

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
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }
        
        initSidebar();
    </script>
</body>
</html>