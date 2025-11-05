<?php
// Recipe Details API for PortionPro
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

$recipe_id = $_GET['id'] ?? 0;

try {
    // Get base recipe info (without pre-aggregated costs; we'll compute with conversions)
    $stmt = $db->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
    $stmt->execute([$recipe_id, $user_id]);
    $recipe = $stmt->fetch();
    
    if (!$recipe) {
        echo json_encode(['success' => false, 'message' => 'Recipe not found']);
        exit;
    }
    
    // Get recipe ingredients and compute detailed costs with unit conversions
    $stmt = $db->prepare("
        SELECT 
            ri.id as recipe_ingredient_id,
            ri.ingredient_id,
            ri.quantity as recipe_quantity,
            ri.unit as recipe_unit,
            i.name as ingredient_name,
            i.unit as ingredient_unit,
            i.price_per_unit
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ? AND i.user_id = ?
        ORDER BY ri.id
    ");
    $stmt->execute([$recipe_id, $user_id]);
    $rows = $stmt->fetchAll();

    $ingredients = [];
    $total_cost = 0.0;

    foreach ($rows as $row) {
        $recipeQty = (float)$row['recipe_quantity'];
        $fromUnit = $row['recipe_unit'];
        $toUnit = $row['ingredient_unit'];

        // Convert quantity from recipe unit to ingredient's base unit
        $convertedQty = convertUnit($recipeQty, $fromUnit, $toUnit, $db);

        // Derive conversion factor (avoid divide-by-zero)
        $conversionFactor = ($recipeQty > 0) ? ($convertedQty / $recipeQty) : 0;

        $lineCost = (float)$convertedQty * (float)$row['price_per_unit'];
        $total_cost += $lineCost;

        $ingredients[] = [
            'recipe_ingredient_id' => (int)$row['recipe_ingredient_id'],
            'ingredient_id' => (int)$row['ingredient_id'],
            'ingredient_name' => $row['ingredient_name'],
            'price_per_unit' => (float)$row['price_per_unit'],
            'recipe_quantity' => (float)$recipeQty,
            'recipe_unit' => $fromUnit,
            'ingredient_unit' => $toUnit,
            'conversion_factor' => (float)$conversionFactor,
            'converted_quantity' => (float)$convertedQty,
            'cost' => (float)$lineCost
        ];
    }

    $servings = max(1, (int)$recipe['servings']);
    $cost_per_serving = $total_cost / $servings;
    
    // Calculate suggested price and profit details
    $suggested_price = calculateSellingPrice($cost_per_serving, $recipe['profit_margin']);

    $recipe['total_cost'] = (float)$total_cost;
    $recipe['cost_per_serving'] = (float)$cost_per_serving;
    $recipe['ingredients'] = $ingredients;
    $recipe['suggested_price'] = (float)$suggested_price;
    $recipe['profit_per_serving'] = (float)($suggested_price - $cost_per_serving);
    $recipe['total_profit'] = (float)($recipe['profit_per_serving'] * $servings);
    
    echo json_encode([
        'success' => true,
        'recipe' => $recipe
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
