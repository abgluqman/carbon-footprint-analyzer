<?php
require_once __DIR__ . '/emissions.php';

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
    //  Fetch period from database and use period-aware calculation
    $sql = "SELECT total_carbon_emissions, period
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
        $period = $row['period'] ?? 'daily'; // Default to daily if not set
        return getEmissionLevel($row['total_carbon_emissions'], $period);
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
    //  Secondary sort by record_id for same-date records
    $sql = "SELECT record_id, record_date, total_carbon_emissions, period 
            FROM emissions_record 
            WHERE user_id = ? 
            ORDER BY record_date DESC, record_id DESC 
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
    //  general tips (no category, no emission level)
    $getGeneralTips = function() use ($conn) {
        $stmt = $conn->prepare(
            "SELECT title, description, content_type, NULL as category_name
             FROM educational_content
             WHERE content_type = 'tip'
               AND (category_id IS NULL OR category_id = 0)
               AND (emissions_level IS NULL OR emissions_level = '')
             ORDER BY created_at DESC
             LIMIT 3"
        );
        $stmt->execute();
        $tips = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tips[] = $row;
        }
        return $tips;
    };

    // Get user's latest emission record with period
    $stmt = $conn->prepare(
        "SELECT record_id, total_carbon_emissions, period
         FROM emissions_record
         WHERE user_id = ?
         ORDER BY record_date DESC
         LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $latestRecord = $stmt->get_result()->fetch_assoc();

    if (!$latestRecord) {
        return $getGeneralTips();
    }

    //  Use period-aware emission level calculation
    $period = $latestRecord['period'] ?? 'daily';
    $level = getEmissionLevel($latestRecord['total_carbon_emissions'], $period);

    // Try to get categories from emissions_details (breakdown per category)
    $stmt = $conn->prepare(
        "SELECT ec.category_id, ec.category_name, ed.emissions_value
         FROM emissions_details ed
         JOIN emissions_category ec ON ed.category_id = ec.category_id
         WHERE ed.record_id = ?
         ORDER BY ed.emissions_value DESC"
    );
    $stmt->bind_param("i", $latestRecord['record_id']);
    $stmt->execute();
    $latestCategories = $stmt->get_result();

    // If emissions_details has no rows, the breakdown isn't stored per-category.
    // Fall back to finding tips by emission level only (no category filter).
    if ($latestCategories->num_rows === 0) {
        $stmt = $conn->prepare(
            "SELECT title, description, content_type, NULL as category_name
             FROM educational_content
             WHERE content_type = 'tip'
               AND emissions_level = ?
             ORDER BY created_at DESC
             LIMIT 3"
        );
        $stmt->bind_param("s", $level);
        $stmt->execute();
        $result = $stmt->get_result();
        $tips = [];
        while ($row = $result->fetch_assoc()) {
            $tips[] = $row;
        }
        // If  nothing, return general tips
        return !empty($tips) ? $tips : $getGeneralTips();
    }

    $tips = [];
    $usedContentIds = []; // prevent duplicate tips

    while ($category = $latestCategories->fetch_assoc()) {
        $stmt = $conn->prepare(
            "SELECT content_id, title, description, content_type, ? as category_name
             FROM educational_content
             WHERE content_type = 'tip'
               AND category_id = ?
               AND emissions_level = ?
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->bind_param("sis", $category['category_name'], $category['category_id'], $level);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row && !in_array($row['content_id'], $usedContentIds)) {
            $usedContentIds[] = $row['content_id'];
            $tips[] = $row;
        } else {
            $stmt = $conn->prepare(
                "SELECT content_id, title, description, content_type, ? as category_name
                 FROM educational_content
                 WHERE content_type = 'tip'
                   AND category_id = ?
                 ORDER BY
                   CASE WHEN emissions_level = ? THEN 0
                        WHEN emissions_level IS NULL OR emissions_level = '' THEN 1
                        ELSE 2 END,
                   created_at DESC
                 LIMIT 1"
            );
            $stmt->bind_param("sis", $category['category_name'], $category['category_id'], $level);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row && !in_array($row['content_id'], $usedContentIds)) {
                $usedContentIds[] = $row['content_id'];
                $tips[] = $row;
            }
        }

        if (count($tips) >= 3) break;
    }

    // Last resort: general tips
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
    //  Use 'monthly' period for current month total
    return getEmissionLevel($currentTotal, 'monthly');
}

function getLatestEmissionRecord($conn, $userId) {
    $sql = "SELECT total_carbon_emissions, record_date, period
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
        $emissions = $row['total_carbon_emissions'];
        $period = $row['period'] ?? 'daily'; // Use stored period or default to daily
        
        return [
            'emissions' => $emissions,
            'date'      => $row['record_date'],
            'period'    => $period,
            'level'     => getEmissionLevel($emissions, $period) // ✅ FIXED: Use period-aware calculation
        ];
    }
    
    return null;
}
?>