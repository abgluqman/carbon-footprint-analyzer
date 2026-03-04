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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$errors = [];

// Whitelist period from GET
$allowedPeriods = ['daily', 'weekly', 'monthly'];
$period = isset($_GET['period']) && in_array($_GET['period'], $allowedPeriods)
    ? $_GET['period']
    : 'daily';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurity('CSRF_VIOLATION_CALCULATOR', 'User ID: ' . $_SESSION['user_id']);
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $emissionsData = [];
    $userId = $_SESSION['user_id'];

    // Whitelist period from POST
    $period = isset($_POST['period']) && in_array($_POST['period'], $allowedPeriods)
        ? $_POST['period']
        : 'daily';

    $recordDateRaw = $_POST['record_date'] ?? date('Y-m-d');
    $parsedDate = DateTime::createFromFormat('Y-m-d', $recordDateRaw);
    $recordDate = ($parsedDate && $parsedDate->format('Y-m-d') === $recordDateRaw && $recordDateRaw <= date('Y-m-d'))
        ? $recordDateRaw
        : date('Y-m-d');

    if ($recordDateRaw !== $recordDate) {
        logSecurity('INVALID_DATE_SUBMITTED', "User: $userId, Submitted: $recordDateRaw, Used: $recordDate");
    }

    // Electricity
    if (!empty($_POST['electricity_kwh'])) {
        $kwh = floatval($_POST['electricity_kwh']);
        if ($kwh < 0 || $kwh > 1000000) {
            $errors[] = "Invalid electricity value.";
            logSecurity('INVALID_INPUT_ELECTRICITY', "User: $userId, Value: $kwh");
        } else {
            $emissionsData[] = [
                'category_id' => 1,
                'input' => $kwh,
                'emissions' => calculateElectricityEmissions($kwh)
            ];
        }
    }

    // Fuel
    if (!empty($_POST['fuel_liters'])) {
        $liters = floatval($_POST['fuel_liters']);
        $allowedFuelTypes = ['petrol', 'diesel'];
        $fuelType = isset($_POST['fuel_type']) && in_array($_POST['fuel_type'], $allowedFuelTypes)
            ? $_POST['fuel_type'] : 'petrol';
        if ($liters < 0 || $liters > 100000) {
            $errors[] = "Invalid fuel value.";
            logSecurity('INVALID_INPUT_FUEL', "User: $userId, Value: $liters");
        } else {
            $emissionsData[] = [
                'category_id' => 2,
                'input' => $liters . '|' . $fuelType,
                'emissions' => calculateFuelEmissions($liters, $fuelType)
            ];
        }
    }

    // Water
    if (!empty($_POST['water_liters'])) {
        $liters = floatval($_POST['water_liters']);
        if ($liters < 0 || $liters > 1000000) {
            $errors[] = "Invalid water value.";
            logSecurity('INVALID_INPUT_WATER', "User: $userId, Value: $liters");
        } else {
            $emissionsData[] = [
                'category_id' => 3,
                'input' => $liters,
                'emissions' => calculateWaterEmissions($liters)
            ];
        }
    }

    // Waste
    if (!empty($_POST['waste_kg'])) {
        $kg = floatval($_POST['waste_kg']);
        $allowedWasteTypes = ['recyclable', 'non-recyclable'];
        $wasteType = isset($_POST['waste_type']) && in_array($_POST['waste_type'], $allowedWasteTypes)
            ? $_POST['waste_type'] : 'non-recyclable';
        if ($kg < 0 || $kg > 100000) {
            $errors[] = "Invalid waste value.";
            logSecurity('INVALID_INPUT_WASTE', "User: $userId, Value: $kg");
        } else {
            $emissionsData[] = [
                'category_id' => 4,
                'input' => $kg . '|' . $wasteType,
                'emissions' => calculateWasteEmissions($kg, $wasteType)
            ];
        }
    }

    // Paper
    if (!empty($_POST['paper_pages'])) {
        $pages = intval($_POST['paper_pages']);
        if ($pages < 0 || $pages > 100000) {
            $errors[] = "Invalid paper value.";
            logSecurity('INVALID_INPUT_PAPER', "User: $userId, Value: $pages");
        } else {
            $emissionsData[] = [
                'category_id' => 5,
                'input' => $pages,
                'emissions' => calculatePaperEmissions($pages)
            ];
        }
    }

    // Food
    if (!empty($_POST['food_meals'])) {
        $meals = intval($_POST['food_meals']);
        $allowedFoodTypes = ['meat', 'vegetarian', 'vegan'];
        $foodType = isset($_POST['food_type']) && in_array($_POST['food_type'], $allowedFoodTypes)
            ? $_POST['food_type'] : 'meat';
        if ($meals < 0 || $meals > 10000) {
            $errors[] = "Invalid food value.";
            logSecurity('INVALID_INPUT_FOOD', "User: $userId, Value: $meals");
        } else {
            $emissionsData[] = [
                'category_id' => 6,
                'input' => $meals . '|' . $foodType,
                'emissions' => calculateFoodEmissions($foodType, $meals)
            ];
        }
    }

    if (!empty($emissionsData) && empty($errors)) {
        try {
            $recordId = saveEmissionsRecord($conn, $userId, $emissionsData, $period, $recordDate);
            
            if (!$recordId) {
                logError("Failed to save emission record", [
                    'user_id' => $userId,
                    'period' => $period,
                    'date' => $recordDate,
                    'categories' => count($emissionsData)
                ]);
                $errors[] = "Failed to save your emissions data. Please try again.";
            } else {
                $totalEmissions = array_sum(array_column($emissionsData, 'emissions'));
                
                logActivity($userId, 'EMISSION_ADDED', sprintf(
                    "Period: %s, Date: %s, Total: %.2f kg, Categories: %d",
                    $period,
                    $recordDate,
                    $totalEmissions,
                    count($emissionsData)
                ));
                
                $success = "Emissions calculated and saved successfully!";
                
                // Redirect to dashboard
                header("Location: dashboard.php?success=1");
                exit();
            }
            
        } catch (Exception $e) {
            logError("Exception while saving emission record", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $errors[] = "An error occurred while saving your data. Please try again.";
        }
        
    } elseif (empty($emissionsData) && empty($errors)) {
        $errors[] = "Please enter at least one consumption value";
        logActivity($userId, 'EMISSION_SUBMIT_EMPTY', "No data entered");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carbon Calculator - Carbon Footprint Analyzer</title>
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
                    <h1 class="h2">Carbon Calculator</h1>
                    <div class="text-muted"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="calculatorForm">
                            <!-- CSRF token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                            <!-- Period Selection (controlled by sidebar) -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-calendar3"></i> Calculation Period
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-success text-white">
                                            <i class="bi bi-calendar3"></i>
                                        </span>
                                        <input type="text" class="form-control fw-bold text-capitalize" 
                                               value="<?php echo htmlspecialchars($period); ?>" readonly>
                                        <input type="hidden" name="period" id="period" value="<?php echo htmlspecialchars($period); ?>">
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="bi bi-info-circle"></i> Change period using the sidebar menu
                                    </small>
                                </div>
                            </div>

                            <!-- Date Selection -->
                            <div class="row mb-4 pb-4 border-bottom">
                                <div class="col-md-6">
                                    <label for="record_date" class="form-label">
                                        <i class="bi bi-calendar"></i> <?php echo ucfirst($period); ?> Date
                                    </label>
                                    <input type="date" class="form-control" id="record_date" name="record_date"
                                           value="<?php echo isset($_POST['record_date']) ? htmlspecialchars($_POST['record_date']) : date('Y-m-d'); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>">
                                    <small class="text-muted d-block mt-1">
                                        <span id="periodInfo">For daily emissions recorded today</span>
                                    </small>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Electricity -->
                                <div class="col-md-6 mb-3">
                                    <label for="electricity_kwh" class="form-label">
                                        <i class="bi bi-lightning-charge text-warning"></i> Electricity (kWh)
                                    </label>
                                    <input type="number" class="form-control" id="electricity_kwh" 
                                           name="electricity_kwh" step="0.01" min="0" 
                                           placeholder="Enter electricity consumption">
                                </div>
                                
                                <!-- Fuel -->
                                <div class="col-md-6 mb-3">
                                    <label for="fuel_liters" class="form-label">
                                        <i class="bi bi-fuel-pump text-danger"></i> Fuel (Liters)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="fuel_liters" 
                                               name="fuel_liters" step="0.01" min="0" 
                                               placeholder="Enter fuel consumption">
                                        <select class="form-select" name="fuel_type" style="max-width: 120px;">
                                            <option value="petrol">Petrol</option>
                                            <option value="diesel">Diesel</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Water -->
                                <div class="col-md-6 mb-3">
                                    <label for="water_liters" class="form-label">
                                        <i class="bi bi-droplet text-info"></i> Water (Liters)
                                    </label>
                                    <input type="number" class="form-control" id="water_liters" 
                                           name="water_liters" step="0.01" min="0" 
                                           placeholder="Enter water consumption">
                                </div>
                                
                                <!-- Waste -->
                                <div class="col-md-6 mb-3">
                                    <label for="waste_kg" class="form-label">
                                        <i class="bi bi-trash text-secondary"></i> Waste (Kg)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="waste_kg" 
                                               name="waste_kg" step="0.01" min="0" 
                                               placeholder="Enter waste generated">
                                        <select class="form-select" name="waste_type" style="max-width: 150px;">
                                            <option value="recyclable">Recyclable</option>
                                            <option value="non-recyclable">Non-recyclable</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Paper -->
                                <div class="col-md-6 mb-3">
                                    <label for="paper_pages" class="form-label">
                                        <i class="bi bi-file-earmark-text text-primary"></i> Paper (Pages)
                                    </label>
                                    <input type="number" class="form-control" id="paper_pages" 
                                           name="paper_pages" min="0" 
                                           placeholder="Enter pages printed">
                                </div>
                                
                                <!-- Food -->
                                <div class="col-md-6 mb-3">
                                    <label for="food_meals" class="form-label">
                                        <i class="bi bi-egg-fried text-success"></i> Food (Meals)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="food_meals" 
                                               name="food_meals" min="0" 
                                               placeholder="Number of meals">
                                        <select class="form-select" name="food_type" style="max-width: 150px;">
                                            <option value="meat">Meat-based</option>
                                            <option value="vegetarian">Vegetarian</option>
                                            <option value="vegan">Vegan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-calculator"></i> Calculate Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form Validation
        const calculatorForm = document.getElementById('calculatorForm');
        
        if (calculatorForm) {
            calculatorForm.addEventListener('submit', function(e) {
                const inputs = [
                    document.getElementById('electricity_kwh'),
                    document.getElementById('fuel_liters'),
                    document.getElementById('water_liters'),
                    document.getElementById('waste_kg'),
                    document.getElementById('paper_pages'),
                    document.getElementById('food_meals')
                ];
                
                const hasValue = inputs.some(input => input && input.value && parseFloat(input.value) > 0);
                
                if (!hasValue) {
                    e.preventDefault();
                    alert('Please enter at least one consumption value');
                    return false;
                }
            });
        }

        // Initialize period UI on page load 
        document.addEventListener('DOMContentLoaded', function() {
            const period = document.getElementById('period').value;
            const periodInfo = document.getElementById('periodInfo');
            const dateLabel = document.querySelector('label[for="record_date"]');
            const messages = {
                daily: 'For daily emissions recorded today',
                weekly: 'For weekly emissions (week containing this date)',
                monthly: 'For monthly emissions (month containing this date)'
            };
            const labels = {
                daily: '<i class="bi bi-calendar"></i> Daily Date',
                weekly: '<i class="bi bi-calendar"></i> Week Starting Date',
                monthly: '<i class="bi bi-calendar"></i> Month Date'
            };
            if (periodInfo) periodInfo.textContent = messages[period] || messages.daily;
            if (dateLabel) dateLabel.innerHTML = labels[period] || labels.daily;
        });

        // Sidebar Toggle Functionality
        const sidebarToggle = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('sidebar');
        let isModalOpen = false;
        
        function initSidebar() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!isModalOpen) {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                }
            });
        }
        
        // Click handler for sidebar outside
        function handleClick(e) {
            if (isModalOpen) return;
            
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.add('collapsed');
                    localStorage.setItem('sidebarCollapsed', true);
                }
            }
        }
        
        // Touch handler for sidebar outside
        function handleTouch(e) {
            if (isModalOpen) return;
            
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.add('collapsed');
                    localStorage.setItem('sidebarCollapsed', true);
                }
            }
        }
        
        // Attach listeners
        document.addEventListener('click', handleClick);
        document.addEventListener('touchstart', handleTouch);
        
        // Monitor all modals for open/close state
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                isModalOpen = true;
            });
            modal.addEventListener('hidden.bs.modal', function() {
                isModalOpen = false;
            });
        });
        
        // Initialize period UI on page load 
        document.addEventListener('DOMContentLoaded', function() {
            const currentPeriod = document.getElementById('period').value;
            updatePeriodUI(currentPeriod);
        });

        initSidebar();
    </script>
</body>
</html>