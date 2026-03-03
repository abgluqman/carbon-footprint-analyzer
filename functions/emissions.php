<?php
// Emission factors (kg CO2 per unit)
class EmissionFactors {
    // Electricity: kg CO2 per kWh
    const ELECTRICITY = 0.67;
    
    // Fuel: kg CO2 per liter
    const PETROL = 2.31;
    const DIESEL = 2.68;
    
    // Water: kg CO2 per liter (treatment and distribution)
    const WATER = 0.00034;
    
    // Waste: kg CO2 per kg
    const WASTE_RECYCLABLE = 0.20;
    const WASTE_NON_RECYCLABLE = 1.20;
    
    // Paper: kg CO2 per page (A4)
    const PAPER = 0.0055;
    
    // Food: kg CO2 per meal
    const FOOD_MEAT = 5.0;
    const FOOD_VEGETARIAN = 0.38;
    const FOOD_VEGAN = 0.28;
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

/**
 * ✅ IMPROVED: Calculate emission level based on total emissions AND period type
 * Different periods have different thresholds because:
 * - 50 kg/day = 1,500 kg/month (VERY HIGH!)
 * - 50 kg/week = 215 kg/month (Medium)
 * - 50 kg/month = Excellent (Low)
 * 
 * @param float $totalEmissions - Total CO2 emissions in kg
 * @param string $period - 'daily', 'weekly', or 'monthly' (default: 'monthly')
 * @return string - 'Low', 'Medium', or 'High'
 */
function getEmissionLevel($totalEmissions, $period = 'monthly') {
    // Period-specific thresholds (all normalized to ~monthly equivalent)
    $thresholds = [
        'daily' => [
            'low' => 3,      // < 3 kg/day = Low (~90 kg/month)
            'medium' => 10   // 3-10 kg/day = Medium (~90-300 kg/month)
                            // > 10 kg/day = High (>300 kg/month)
        ],
        'weekly' => [
            'low' => 20,     // < 20 kg/week = Low (~85 kg/month)
            'medium' => 70   // 20-70 kg/week = Medium (~85-300 kg/month)
                            // > 70 kg/week = High (>300 kg/month)
        ],
        'monthly' => [
            'low' => 100,    // < 100 kg/month = Low
            'medium' => 300  // 100-300 kg/month = Medium
                            // > 300 kg/month = High
        ]
    ];
    
    // Default to monthly if period not recognized (backward compatibility)
    if (!isset($thresholds[$period])) {
        $period = 'monthly';
    }
    
    $limits = $thresholds[$period];
    
    if ($totalEmissions < $limits['low']) {
        return 'Low';
    } elseif ($totalEmissions < $limits['medium']) {
        return 'Medium';
    } else {
        return 'High';
    }
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
    
    // Insert emissions record (period saved to DB)
    $sql = "INSERT INTO emissions_record (user_id, record_date, total_carbon_emissions, period) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isds", $userId, $recordDate, $totalEmissions, $period);
    
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