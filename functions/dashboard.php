<?php
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
        return getEmissionLevel($row['total_carbon_emissions']);
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
            AND record_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
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
    // Get user's highest emission category
    $sql = "SELECT ec.category_id, ec.category_name, SUM(ed.emissions_value) as total
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
    $highestCategory = $result->fetch_assoc();
    
    if (!$highestCategory) {
        return [];
    }
    
    // Get latest emission level
    $level = getLatestEmissionLevel($conn, $userId);
    
    // Fetch personalized tips
    $sql = "SELECT title, description, content_type 
            FROM educational_content 
            WHERE (category_id = ? OR category_id IS NULL)
            AND (emissions_level = ? OR emissions_level IS NULL)
            AND content_type = 'tip'
            ORDER BY created_at DESC 
            LIMIT 3";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $highestCategory['category_id'], $level);
    $stmt->execute();
    return $stmt->get_result();
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
?>