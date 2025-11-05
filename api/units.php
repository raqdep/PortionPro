<?php
// Units API for PortionPro
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Get all available units
    $stmt = $db->prepare("SELECT DISTINCT from_unit FROM unit_conversions ORDER BY from_unit");
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unit conversions
    $stmt = $db->prepare("SELECT from_unit, to_unit, conversion_factor FROM unit_conversions");
    $stmt->execute();
    $conversions = $stmt->fetchAll();
    
    // Convert to associative array for easier lookup
    $conversionMap = [];
    foreach ($conversions as $conversion) {
        $key = $conversion['from_unit'] . '_to_' . $conversion['to_unit'];
        $conversionMap[$key] = $conversion['conversion_factor'];
    }
    
    echo json_encode([
        'success' => true,
        'units' => $units,
        'conversions' => $conversionMap
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
