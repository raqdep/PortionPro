<?php
// Recipe Ingredients API for PortionPro
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

$recipe_id = $_GET['recipe_id'] ?? 0;

try {
    $stmt = $db->prepare("
        SELECT ri.*, i.name as ingredient_name, i.price_per_unit
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ? AND i.user_id = ?
        ORDER BY ri.id
    ");
    $stmt->execute([$recipe_id, $user_id]);
    $ingredients = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'ingredients' => $ingredients
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
