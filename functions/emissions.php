<?php
// Emission factors (kg CO2 per unit)
class EmissionFactors {
    // Electricity: kg CO2 per kWh
    const ELECTRICITY = 0.85;
    
    // Fuel: kg CO2 per liter
    const PETROL = 2.31;
    const DIESEL = 2.68;
    
    // Water: kg CO2 per liter (treatment and distribution)
    const WATER = 0.000298;
    
    // Waste: kg CO2 per kg
    const WASTE_RECYCLABLE = 0.21;
    const WASTE_NON_RECYCLABLE = 0.5;
    
    // Paper: kg CO2 per page (A4)
    const PAPER = 0.01;
    
    // Food: kg CO2 per meal
    const FOOD_MEAT = 7.2;
    const FOOD_VEGETARIAN = 2.5;
    const FOOD_VEGAN = 1.5;
}

function calculateElectricityEmissions($kwh) {
    return $kwh * EmissionFactors::ELECTRICITY;
}

function calculateFuelEmissions($liters, $fuelType = 'petrol') {
    $factor = $fuelType === 'diesel' ? EmissionFactors::DIESEL : EmissionFactors::PETROL;
    return $liters * $factor;
}

function calculateWaterEmissions($liters) {
    return $liters * EmissionFactors::WATER;
}

function calculateWasteEmissions($kg, $type = 'non-recyclable') {
    $factor = $type === 'recyclable' ? EmissionFactors::WASTE_RECYCLABLE : EmissionFactors::WASTE_NON_RECYCLABLE;
    return $kg * $factor;
}

function calculatePaperEmissions($pages) {
    return $pages * EmissionFactors::PAPER;
}

function calculateFoodEmissions($mealType, $count = 1) {
    $factors = [
        'meat' => EmissionFactors::FOOD_MEAT,
        'vegetarian' => EmissionFactors::FOOD_VEGETARIAN,
        'vegan' => EmissionFactors::FOOD_VEGAN
    ];
    
    return ($factors[$mealType] ?? EmissionFactors::FOOD_MEAT) * $count;
}

function getEmissionLevel($totalEmissions) {
    if ($totalEmissions < 50) return 'Low';
    if ($totalEmissions < 100) return 'Medium';
    return 'High';
}

function saveEmissionsRecord($conn, $userId, $emissionsData, $period = 'daily', $recordDateTime = null) {
    $totalEmissions = array_sum(array_column($emissionsData, 'emissions'));
    
    // Use provided datetime or default to current
    if ($recordDateTime) {
        $recordDate = substr($recordDateTime, 0, 10);
    } else {
        $recordDate = date('Y-m-d');
        $recordDateTime = date('Y-m-d H:i:s');
    }
    
    // Ensure recordDateTime is a valid datetime format
    if (strlen($recordDateTime) == 10) {
        $recordDateTime = $recordDateTime . ' ' . date('H:i:s');
    }
    
    // Calculate period key based on the date and period type
    $periodKey = calculatePeriodKey($recordDate, $period);
    
    // Insert emissions record
    $sql = "INSERT INTO emissions_record (user_id, record_date, total_carbon_emissions) 
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isd", $userId, $recordDate, $totalEmissions);
    
    $stmt->execute();
    $recordId = $stmt->insert_id;
    
    // Insert emissions details
    $sql = "INSERT INTO emissions_details (record_id, category_id, input_value, emissions_value) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($emissionsData as $data) {
        $stmt->bind_param("iisd", 
            $recordId, 
            $data['category_id'], 
            $data['input'], 
            $data['emissions']
        );
        $stmt->execute();
    }
    
    return $recordId;
}

function calculatePeriodKey($date, $period) {
    $dateTime = new DateTime($date);
    
    switch ($period) {
        case 'weekly':
            // Get the week number and year
            $week = $dateTime->format('W');
            $year = $dateTime->format('Y');
            return $year . '-W' . $week;
            
        case 'monthly':
            // Get year and month
            return $dateTime->format('Y-m');
            
        case 'daily':
        default:
            // Return the date itself for daily
            return $dateTime->format('Y-m-d');
    }
}
?>