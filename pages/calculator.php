<?php
session_start();
require_once '../config/db_connection.php';
require_once '../functions/emissions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$errors = [];
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emissionsData = [];
    
    // Get period and date/time info
    $period = $_POST['period'] ?? 'daily';
    $recordDate = $_POST['record_date'] ?? date('Y-m-d');
    $recordTime = $_POST['record_time'] ?? date('H:i');
    $recordDateTime = $recordDate . ' ' . $recordTime;
    
    // Electricity
    if (!empty($_POST['electricity_kwh'])) {
        $kwh = floatval($_POST['electricity_kwh']);
        $emissionsData[] = [
            'category_id' => 1,
            'input' => $kwh,
            'emissions' => calculateElectricityEmissions($kwh)
        ];
    }
    
    // Fuel
    if (!empty($_POST['fuel_liters'])) {
        $liters = floatval($_POST['fuel_liters']);
        $fuelType = $_POST['fuel_type'] ?? 'petrol';
        $emissionsData[] = [
            'category_id' => 2,
            'input' => $liters . '|' . $fuelType,
            'emissions' => calculateFuelEmissions($liters, $fuelType)
        ];
    }
    
    // Water
    if (!empty($_POST['water_liters'])) {
        $liters = floatval($_POST['water_liters']);
        $emissionsData[] = [
            'category_id' => 3,
            'input' => $liters,
            'emissions' => calculateWaterEmissions($liters)
        ];
    }
    
    // Waste
    if (!empty($_POST['waste_kg'])) {
        $kg = floatval($_POST['waste_kg']);
        $wasteType = $_POST['waste_type'] ?? 'non-recyclable';
        $emissionsData[] = [
            'category_id' => 4,
            'input' => $kg . '|' . $wasteType,
            'emissions' => calculateWasteEmissions($kg, $wasteType)
        ];
    }
    
    // Paper
    if (!empty($_POST['paper_pages'])) {
        $pages = intval($_POST['paper_pages']);
        $emissionsData[] = [
            'category_id' => 5,
            'input' => $pages,
            'emissions' => calculatePaperEmissions($pages)
        ];
    }
    
    // Food
    if (!empty($_POST['food_meals'])) {
        $meals = intval($_POST['food_meals']);
        $foodType = $_POST['food_type'] ?? 'meat';
        $emissionsData[] = [
            'category_id' => 6,
            'input' => $meals . '|' . $foodType,
            'emissions' => calculateFoodEmissions($foodType, $meals)
        ];
    }
    
    if (!empty($emissionsData)) {
        $recordId = saveEmissionsRecord($conn, $_SESSION['user_id'], $emissionsData, $period, $recordDateTime);
        $success = "Emissions calculated and saved successfully!";
        
        // Redirect to dashboard
        header("Location: dashboard.php?success=1");
        exit();
    } else {
        $errors[] = "Please enter at least one consumption value";
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
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="calculatorForm">
                            <!-- Period Selection -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="period" class="form-label fw-bold">
                                        <i class="bi bi-calendar3"></i> Calculation Period
                                    </label>
                                    <select class="form-select" id="period" name="period" onchange="updatePeriodUI(this.value)">
                                        <option value="daily" <?php echo $period == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo $period == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Date and Time Selection -->
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
                                <div class="col-md-6">
                                    <label for="record_time" class="form-label">
                                        <i class="bi bi-clock"></i> Time (Optional)
                                    </label>
                                    <input type="time" class="form-control" id="record_time" name="record_time"
                                           value="<?php echo isset($_POST['record_time']) ? htmlspecialchars($_POST['record_time']) : date('H:i'); ?>">
                                    <small class="text-muted d-block mt-1">Use current time if not specified</small>
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

        // Select Period from Sidebar
        function selectPeriod(period) {
            const formPeriod = document.getElementById('period');
            if (formPeriod) {
                formPeriod.value = period;
                updatePeriodUI(period);
            }
        }

        // Update Period UI
        function updatePeriodUI(period) {
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
        }

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
            // Never collapse sidebar if a modal is open
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
            // Never collapse sidebar if a modal is open
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
        
        initSidebar();
    </script>
</body>
</html>