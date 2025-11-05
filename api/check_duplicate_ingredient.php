<?php
// Check for duplicate ingredient names API
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$name = $_GET['name'] ?? '';
$exclude_id = $_GET['exclude_id'] ?? 0; // For edit mode, exclude current ingredient

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Name parameter required']);
    exit;
}

try {
    // Check for duplicate ingredient name (case-insensitive)
    $stmt = $db->prepare("SELECT id, name FROM ingredients WHERE user_id = ? AND LOWER(name) = LOWER(?) AND id != ?");
    $stmt->execute([$user_id, $name, $exclude_id]);
    $existing_ingredient = $stmt->fetch();
    
    if ($existing_ingredient) {
        echo json_encode([
            'success' => true,
            'is_duplicate' => true,
            'existing_name' => $existing_ingredient['name'],
            'message' => "An ingredient with the name \"{$existing_ingredient['name']}\" already exists."
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'is_duplicate' => false,
            'message' => 'Name is available'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
