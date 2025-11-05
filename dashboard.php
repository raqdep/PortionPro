<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ingredients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_ingredients = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM recipes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_recipes = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_recipes = $stmt->fetchAll();

    $all_costs_per_serving = [];
    foreach ($recent_recipes as &$recipeRow) {
        $recipeId = (int)$recipeRow['id'];
        $servings = max(1, (int)$recipeRow['servings']);
        
        $stmtIng = $db->prepare("SELECT ri.quantity as recipe_quantity, ri.unit as recipe_unit, i.unit as ingredient_unit, i.price_per_unit
                                  FROM recipe_ingredients ri
                                  JOIN ingredients i ON ri.ingredient_id = i.id
                                  WHERE ri.recipe_id = ? AND i.user_id = ?");
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
        $suggestedPrice = calculateSellingPrice($costPerServing, $recipeRow['profit_margin']);
        
        $recipeRow['total_cost'] = $totalCost;
        $recipeRow['cost_per_serving'] = $costPerServing;
        $recipeRow['suggested_price'] = $suggestedPrice;
        $recipeRow['profit_per_serving'] = $suggestedPrice - $costPerServing;
        $recipeRow['total_profit'] = $recipeRow['profit_per_serving'] * $servings;
        
        $all_costs_per_serving[] = $costPerServing;
    }
    unset($recipeRow);

    // Average cost per serving (based on computed values)
    $avg_cost = !empty($all_costs_per_serving) ? array_sum($all_costs_per_serving) / count($all_costs_per_serving) : 0;
    
} catch (PDOException $e) {
    $total_ingredients = 0;
    $total_recipes = 0;
    $avg_cost = 0;
    $recent_recipes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PortionPro</title>
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
    min-height: 100vh;
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
                <a href="dashboard.php" class="active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="ingredients.php">
                    <i class="fas fa-apple-alt"></i> Ingredients
                </a>
                <a href="recipes.php">
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
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php 
                $welcomeName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 
                              (isset($_SESSION['business_name']) ? $_SESSION['business_name'] : 
                              (isset($_SESSION['username']) ? $_SESSION['username'] : 'User'));
                echo htmlspecialchars($welcomeName); 
            ?>!</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-apple-alt"></i>
                </div>
                <div class="stat-value"><?php echo $total_ingredients; ?></div>
                <div class="stat-label">Total Ingredients</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $total_recipes; ?></div>
                <div class="stat-label">Total Recipes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($avg_cost); ?></div>
                <div class="stat-label">Avg Cost per Serving</div>
            </div>
            
        </div>

        <!-- Recent Recipes -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title centered">Recent Recipes</h2>
                <a href="recipes.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Recipe
                </a>
            </div>
            <?php if (empty($recent_recipes)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No recipes found. <a href="recipes.php">Create your first recipe</a> to get started!
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Recipe Name</th>
                                <th>Servings</th>
                                <th>Total Cost</th>
                                <th>Cost per Serving</th>
                                <th>Suggested Price</th>
                                <th>Profit per Serving</th>
                                <th>Total Profit</th>
                                <th>Profit Margin</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_recipes as $recipe): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                                    <td><?php echo $recipe['servings']; ?></td>
                                    <td><?php echo formatCurrency($recipe['total_cost']); ?></td>
                                    <td><?php echo formatCurrency($recipe['cost_per_serving']); ?></td>
                                    <td><?php echo formatCurrency($recipe['suggested_price']); ?></td>
                                    <td><?php echo formatCurrency($recipe['profit_per_serving']); ?></td>
                                    <td><?php echo formatCurrency($recipe['total_profit']); ?></td>
                                    <td><?php echo $recipe['profit_margin']; ?>%</td>
                                    <td><?php echo date('M j, Y', strtotime($recipe['created_at'])); ?></td>
                                    <td>
                                        <a href="recipes.php?action=edit&id=<?php echo $recipe['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <a href="ingredients.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Ingredient
                </a>
                <a href="recipes.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i> Create Recipe
                </a>
                <a href="reports.php" class="btn btn-success">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
                <a href="ingredients.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Update Prices
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
