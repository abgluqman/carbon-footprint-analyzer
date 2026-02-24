<?php
// Helper functions for emission level detection
// (These should eventually be in emissions.php)

if (!function_exists('getEmissionLevelAuto')) {
    function getEmissionLevelAuto($totalEmissions) {
        // Smart auto-detection based on typical emission ranges
        if ($totalEmissions <= 0) {
            return 'Low';
        }
        
        // Daily range: Typically 0-60 kg
        if ($totalEmissions < 60) {
            if ($totalEmissions < 10) return 'Low';
            if ($totalEmissions < 25) return 'Medium';
            return 'High';
        }
        
        // Weekly range: Typically 60-400 kg
        if ($totalEmissions < 400) {
            if ($totalEmissions < 70) return 'Low';
            if ($totalEmissions < 175) return 'Medium';
            return 'High';
        }
        
        // Monthly range: Typically 400+ kg
        if ($totalEmissions < 300) return 'Low';
        if ($totalEmissions < 750) return 'Medium';
        return 'High';
    }
}

if (!function_exists('detectPeriodFromAmount')) {
    function detectPeriodFromAmount($totalEmissions) {
        // Helper function to detect which period based on amount
        if ($totalEmissions < 60) {
            return 'daily';
        } elseif ($totalEmissions < 400) {
            return 'weekly';
        } else {
            return 'monthly';
        }
    }
}

function getUserTotalEmissions($conn, $userId) {
    $sql = "SELECT SUM(total_carbon_emissions) as total 
            FROM emissions_record 
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getLatestEmissionLevel($conn, $userId) {
    $sql = "SELECT total_carbon_emissions 
            FROM emissions_record 
            WHERE user_id = ? 
            ORDER BY record_date DESC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        // Use smart auto-detection - no database changes needed!
        return getEmissionLevelAuto($row['total_carbon_emissions']);
    }
    return 'N/A';
}

function getHighestEmissionCategory($conn, $userId) {
    $sql = "SELECT ec.category_name, SUM(ed.emissions_value) as total
            FROM emissions_details ed
            JOIN emissions_record er ON ed.record_id = er.record_id
            JOIN emissions_category ec ON ed.category_id = ec.category_id
            WHERE er.user_id = ?
            GROUP BY ec.category_id
            ORDER BY total DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? $row['category_name'] : 'N/A';
}

function getEmissionHistory($conn, $userId, $limit = 5) {
    $sql = "SELECT record_id, record_date, total_carbon_emissions 
            FROM emissions_record 
            WHERE user_id = ? 
            ORDER BY record_date DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function getMonthlyEmissionsTrend($conn, $userId, $months = 6) {
    $sql = "SELECT 
                DATE_FORMAT(record_date, '%Y-%m') as month,
                SUM(total_carbon_emissions) as total
            FROM emissions_record
            WHERE user_id = ? 
            AND record_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(record_date, '%Y-%m')
            ORDER BY month ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $months);
    $stmt->execute();
    return $stmt->get_result();
}

function getCategoryBreakdown($conn, $userId) {
    $sql = "SELECT 
                ec.category_name,
                SUM(ed.emissions_value) as total,
                COUNT(DISTINCT er.record_id) as count
            FROM emissions_details ed
            JOIN emissions_record er ON ed.record_id = er.record_id
            JOIN emissions_category ec ON ed.category_id = ec.category_id
            WHERE er.user_id = ?
            GROUP BY ec.category_id
            ORDER BY total DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result();
}

function getPersonalizedTips($conn, $userId) {
    // Helper: fetch general tips as a plain array (fallback)
    $getGeneralTips = function() use ($conn) {
        $stmt = $conn->prepare(
            "SELECT title, description, content_type, NULL as category_name
             FROM educational_content
             WHERE content_type = 'tip'
             ORDER BY created_at DESC
             LIMIT 3"
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $tips = [];
        while ($row = $result->fetch_assoc()) {
            $tips[] = $row;
        }
        return $tips;
    };

    // Get user's LATEST emission record
    $latestRecordSql = "SELECT record_id 
                        FROM emissions_record 
                        WHERE user_id = ? 
                        ORDER BY record_date DESC 
                        LIMIT 1";
    $stmt = $conn->prepare($latestRecordSql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $latestRecord = $stmt->get_result()->fetch_assoc();

    if (!$latestRecord) {
        // User has no emissions yet - return general tips
        return $getGeneralTips();
    }

    // Get categories from the LATEST record, ordered by highest emissions first
    $sql = "SELECT ec.category_id, ec.category_name, ed.emissions_value
            FROM emissions_details ed
            JOIN emissions_category ec ON ed.category_id = ec.category_id
            WHERE ed.record_id = ?
            ORDER BY ed.emissions_value DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $latestRecord['record_id']);
    $stmt->execute();
    $latestCategories = $stmt->get_result();

    if ($latestCategories->num_rows === 0) {
        return $getGeneralTips();
    }

    // Get the user's current month emission level
    $currentMonthEmissions = getCurrentMonthEmissions($conn, $userId);
    $level = getEmissionLevelAuto($currentMonthEmissions);
    
    // If current month has no data, use latest record level as fallback
    if ($currentMonthEmissions == 0) {
        $level = getLatestEmissionLevel($conn, $userId);
    }

    // For each category in latest record (highest first), fetch matching tips
    $tips = [];
    while ($category = $latestCategories->fetch_assoc()) {
        $sql = "SELECT title, description, content_type, ? as category_name
                FROM educational_content
                WHERE category_id = ?
                AND emissions_level = ?
                AND content_type = 'tip'
                ORDER BY created_at DESC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sis",
            $category['category_name'],
            $category['category_id'],
            $level
        );
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $tips[] = $result->fetch_assoc();
        }
        
        // Stop after getting 3 tips
        if (count($tips) >= 3) {
            break;
        }
    }

    // If no level-specific tips found, fall back to general tips
    return !empty($tips) ? $tips : $getGeneralTips();
}

function compareWithPreviousMonth($conn, $userId) {
    $sql = "SELECT 
                DATE_FORMAT(record_date, '%Y-%m') as month,
                SUM(total_carbon_emissions) as total
            FROM emissions_record
            WHERE user_id = ?
            AND record_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
            GROUP BY DATE_FORMAT(record_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 2";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    if (count($data) == 2) {
        $current = $data[0]['total'];
        $previous = $data[1]['total'];
        $change = (($current - $previous) / $previous) * 100;
        
        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
            'trend' => $change > 0 ? 'up' : 'down'
        ];
    }
    
    return null;
}

function getCurrentMonthEmissions($conn, $userId) {
    $sql = "SELECT COALESCE(SUM(total_carbon_emissions), 0) as total
            FROM emissions_record
            WHERE user_id = ?
            AND MONTH(record_date) = MONTH(CURDATE())
            AND YEAR(record_date) = YEAR(CURDATE())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];
}

function getPreviousMonthEmissions($conn, $userId) {
    $sql = "SELECT COALESCE(SUM(total_carbon_emissions), 0) as total
            FROM emissions_record
            WHERE user_id = ?
            AND MONTH(record_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND YEAR(record_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];
}

function getCurrentMonthLevel($conn, $userId) {
    $currentTotal = getCurrentMonthEmissions($conn, $userId);
    return getEmissionLevel($currentTotal, 'monthly'); // Use monthly thresholds
}

function getLatestEmissionRecord($conn, $userId) {
    $sql = "SELECT total_carbon_emissions, record_date 
            FROM emissions_record 
            WHERE user_id = ? 
            ORDER BY record_date DESC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        // Detect period based on emission amount
        $emissions = $row['total_carbon_emissions'];
        $period = detectPeriodFromAmount($emissions);
        
        return [
            'emissions' => $emissions,
            'date' => $row['record_date'],
            'period' => $period,
            'level' => getEmissionLevel($emissions, $period)
        ];
    }
    
    return null;
}
?>