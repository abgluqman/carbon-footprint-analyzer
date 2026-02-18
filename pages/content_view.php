<?php
// Simple JSON endpoint to return educational content details by id
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db_connection.php';

$id = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid content id']);
    exit;
}

$sql = "SELECT content_id, title, description, content_type, emissions_level, content_image FROM educational_content WHERE content_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Content not found']);
    exit;
}

// If image exists, base64-encode it for transport
$imageBase64 = null;
if (!empty($row['content_image'])) {
    $imageBase64 = base64_encode($row['content_image']);
}

echo json_encode([
    'success' => true,
    'content' => [
        'content_id' => $row['content_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'content_type' => $row['content_type'],
        'emissions_level' => $row['emissions_level'],
        'image_base64' => $imageBase64
    ]
]);

exit;
