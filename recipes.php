<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $servings = intval($_POST['servings']);
        $profit_margin = floatval($_POST['profit_margin']);
        $ingredients = $_POST['ingredients'] ?? [];
        
        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO recipes (user_id, name, description, servings, profit_margin) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $description, $servings, $profit_margin]);
            $recipe_id = $db->lastInsertId();
        } else {
            $recipe_id = intval($_POST['recipe_id']);
            $stmt = $db->prepare("UPDATE recipes SET name = ?, description = ?, servings = ?, profit_margin = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $description, $servings, $profit_margin, $recipe_id, $user_id]);
            
            $stmt = $db->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
            $stmt->execute([$recipe_id]);
        }
        
        foreach ($ingredients as $ingredient) {
            if (!empty($ingredient['ingredient_id']) && !empty($ingredient['quantity'])) {
                $stmt = $db->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $recipe_id,
                    $ingredient['ingredient_id'],
                    $ingredient['quantity'],
                    $ingredient['unit']
                ]);
            }
        }
        
        $message = 'Recipe ' . ($action === 'add' ? 'created' : 'updated') . ' successfully!';
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM recipes WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$id, $user_id]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Recipe deleted successfully!' : 'Failed to delete recipe']);
        exit;
    }
}

$search = $_GET['search'] ?? '';

$where_conditions = ["r.user_id = ?"];
$params = [$user_id];

if (!empty($search)) {
	$where_conditions[] = "r.name LIKE ?";
	$params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $db->prepare(
	"SELECT r.*
	 FROM recipes r
	 WHERE $where_clause
	 ORDER BY r.created_at DESC"
);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

foreach ($recipes as &$recipe) {
	$recipeId = (int)$recipe['id'];
	$servings = max(1, (int)$recipe['servings']);

	$stmtIng = $db->prepare(
		"SELECT ri.quantity AS recipe_quantity, ri.unit AS recipe_unit, i.unit AS ingredient_unit, i.price_per_unit
		 FROM recipe_ingredients ri
		 JOIN ingredients i ON ri.ingredient_id = i.id
		 WHERE ri.recipe_id = ? AND i.user_id = ?"
	);
	$stmtIng->execute([$recipeId, $user_id]);
	$ings = $stmtIng->fetchAll();

	$totalCost = 0.0;
	foreach ($ings as $ing) {
		$qty = (float)$ing['recipe_quantity'];
		$fromUnit = $ing['recipe_unit'];
		$toUnit = $ing['ingredient_unit'];
		$converted = convertUnit($qty, $fromUnit, $toUnit, $db);
		$totalCost += ((float)$converted) * ((float)$ing['price_per_unit']);
	}
	$costPerServing = $totalCost / $servings;
	$recipe['total_cost'] = $totalCost;
	$recipe['cost_per_serving'] = $costPerServing;
}
unset($recipe);

// Get user's ingredients for recipe builder
$stmt = $db->prepare("SELECT * FROM ingredients WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user_id]);
$ingredients = $stmt->fetchAll();

// Get available units
$units = getAvailableUnits('weight', $db);
$volume_units = getAvailableUnits('volume', $db);
$count_units = getAvailableUnits('count', $db);
$all_units = array_merge($units, $volume_units, $count_units);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes - PortionPro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
body.dashboard {
    background-image: url('bg/bg1.png');
    background-size: cover;
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-position: center;
}

.user-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
}

/* Brand logo */
.logo-image {
    height: 40px;
    width: auto;
    margin-right: 8px;
    vertical-align: middle;
}
.logo-text {
    font-weight: bold;
    margin: 0;
}
</style>
</head>
<body class="dashboard">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="navbar-brand">
                <img src="logo/PortionPro-fill.png" alt="PortionPro Logo" class="logo-image">
                <span class="logo-text">PortionPro</span>
            </a>
            <div class="navbar-menu">
                <a href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="ingredients.php">
                    <i class="fas fa-apple-alt"></i> Ingredients
                </a>
                <a href="recipes.php" class="active">
                    <i class="fas fa-book"></i> Recipes
                </a>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </div>
            <div class="user-menu">
                <button class="user-btn" onclick="logout()">
                    <?php if (isset($_SESSION['user']['picture'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['user']['picture']); ?>" alt="Profile" class="user-avatar">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                    <?php 
                    $displayName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 
                                  (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
                    echo htmlspecialchars($displayName); 
                    ?>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Recipe Management</h1>
            <p class="page-subtitle">Create and manage your recipes with automatic cost calculations</p>
        </div>

        <!-- Add Recipe Button -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recipes</h2>
                <button class="btn btn-primary btn-narrow" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Recipe
                </button>
            </div>
            
            <!-- Search -->
            <div class="form-group" style="max-width: 400px;">
                <label for="search">Search Recipes</label>
                <input type="text" id="search" placeholder="Search by recipe name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <!-- Recipes Table -->
            <div class="table-container">
                <table class="table table-condensed">
                    <thead>
                        <tr>
                            <th>Recipe Name</th>
                            <th>Description</th>
                            <th>Servings</th>
                            <th>Total Cost</th>
                            <th>Cost per Serving</th>
                            <th>Suggested Price</th>
                            <th>Profit per Serving</th>
                            <th>Total Profit</th>
                            <th>Profit Margin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recipes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                    No recipes found. <a href="#" onclick="openAddModal()">Create your first recipe</a> to get started!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recipes as $recipe): ?>
                                <?php 
                                $suggested_price = calculateSellingPrice($recipe['cost_per_serving'], $recipe['profit_margin']);
                                $profit_per_serving = $suggested_price - $recipe['cost_per_serving'];
                                $total_profit = $profit_per_serving * max(1, (int)$recipe['servings']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($recipe['description'], 0, 50)) . (strlen($recipe['description']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo $recipe['servings']; ?></td>
                                    <td><?php echo formatCurrency($recipe['total_cost']); ?></td>
                                    <td><?php echo formatCurrency($recipe['cost_per_serving']); ?></td>
                                    <td><?php echo formatCurrency($suggested_price); ?></td>
                                    <td><?php echo formatCurrency($profit_per_serving); ?></td>
                                    <td><?php echo formatCurrency($total_profit); ?></td>
                                    <td><?php echo $recipe['profit_margin']; ?>%</td>
                                    <td class="table-actions">
                                        <button class="btn btn-secondary btn-sm" onclick="editRecipe(<?php echo htmlspecialchars(json_encode($recipe)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-success btn-sm" onclick="viewRecipe(<?php echo $recipe['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteRecipe(<?php echo $recipe['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Recipe Modal -->
    <div id="recipeModal" class="modal">
        <div class="modal-content" style="max-width: 950px;">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add Recipe</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="recipeForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="recipe_id" id="recipeId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Recipe Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="servings">Servings *</label>
                        <input type="number" id="servings" name="servings" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="profit_margin">Profit Margin (%)</label>
                    <input type="number" id="profit_margin" name="profit_margin" min="0" max="100" step="0.01" value="30" oninput="if(this.value) this.value = parseFloat(this.value).toFixed(2)">
                </div>
                
                <div class="form-group">
                    <label>Ingredients</label>
                    <div id="ingredientsList">
                        <!-- Ingredients will be added dynamically -->
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addIngredientRow()">
                        <i class="fas fa-plus"></i> Add Ingredient
                    </button>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Recipe</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Recipe Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 1200px;">
            <div class="modal-header">
                <h2 class="modal-title">Recipe Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="recipeDetails">
                <!-- Recipe details will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Pass PHP data to JavaScript
        const ingredients = <?php echo json_encode($ingredients); ?>;
        const units = <?php echo json_encode($all_units); ?>;
        
        function exportRecipeDetails(recipeId) {
    window.location.href = 'reports.php?export=excel&type=recipe_details&id=' + recipeId;
}

function printRecipeReport(recipeId) {
    window.open('reports.php?print=recipe_details&id=' + recipeId, '_blank');
}
    </script>
    <script src="assets/js/recipes.js"></script>
</body>
</html>
