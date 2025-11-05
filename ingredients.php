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
        $unit = sanitizeInput($_POST['unit']);
        $price = floatval($_POST['price']);
        $category = sanitizeInput($_POST['category']);
        
        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO ingredients (user_id, name, unit, price_per_unit, category) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $name, $unit, $price, $category]);
            $message = $result ? 'Ingredient added successfully!' : 'Failed to add ingredient';
        } else {
            $id = intval($_POST['id']);
            $stmt = $db->prepare("UPDATE ingredients SET name = ?, unit = ?, price_per_unit = ?, category = ? WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$name, $unit, $price, $category, $id, $user_id]);
            $message = $result ? 'Ingredient updated successfully!' : 'Failed to update ingredient';
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => $result, 'message' => $message]);
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM ingredients WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$id, $user_id]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Ingredient deleted successfully!' : 'Failed to delete ingredient']);
        exit;
    }
}

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where_conditions = ["user_id = ?"];
$params = [$user_id];

if (!empty($search)) {
    $where_conditions[] = "name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $db->prepare("SELECT * FROM ingredients WHERE $where_clause ORDER BY name ASC");
$stmt->execute($params);
$ingredients = $stmt->fetchAll();

$stmt = $db->prepare("SELECT DISTINCT category FROM ingredients WHERE user_id = ? AND category IS NOT NULL ORDER BY category");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Ingredients - PortionPro</title>
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
                <a href="ingredients.php" class="active">
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
            <h1 class="page-title">Ingredients Management</h1>
            <p class="page-subtitle">Manage your ingredient inventory and pricing</p>
        </div>

        <?php if (empty($ingredients)): ?>
        <!-- Quick Start Guide for New Users -->
        <div class="quick-start-guide">
            <h3> Welcome to PortionPro!</h3>
            <p>Let's get you started by adding your first ingredients. This will help you build accurate recipes and calculate costs.</p>
            
            <div class="quick-start-steps">
                <div class="quick-step">
                    <i class="fas fa-apple-alt"></i>
                    <h4>1. Add Ingredients</h4>
                    <p>Start with your most common ingredients like flour, sugar, butter</p>
                </div>
                <div class="quick-step">
                    <i class="fas fa-tags"></i>
                    <h4>2. Set Prices</h4>
                    <p>Enter current prices per unit (kg, liter, piece, etc.)</p>
                </div>
                <div class="quick-step">
                    <i class="fas fa-book"></i>
                    <h4>3. Create Recipes</h4>
                    <p>Use your ingredients to build recipes with automatic costing</p>
                </div>
                <div class="quick-step">
                    <i class="fas fa-chart-line"></i>
                    <h4>4. Analyze Profits</h4>
                    <p>View reports and optimize your pricing strategy</p>
                </div>
            </div>
            
            <div class="btn-container-center" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="showTutorial()" style="background: white; color: #16a085; border: 2px solid white;">
                    <i class="fas fa-play"></i> Start Tutorial
                </button>
                <button class="btn btn-secondary" onclick="openAddModal()" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white;">
                    <i class="fas fa-plus"></i> Add First Ingredient
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Ingredient Button -->
        <div class="card">
            <h2 class="card-title">Ingredients</h2>
            <div class="card-header card-header-center">
                <div class="btn-container-center">
                    <button class="btn btn-secondary" onclick="showTutorial()">
                        <i class="fas fa-question-circle"></i> How to Add Ingredients
                    </button>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Ingredient
                    </button>
                    <?php if (!empty($ingredients)): ?>
                    <button class="btn btn-success" onclick="showQuickTips()">
                        <i class="fas fa-lightbulb"></i> Quick Tips
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="form-row">
                <div class="form-group">
                    <label for="search">Search Ingredients</label>
                    <input type="text" id="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="category_filter">Filter by Category</label>
                    <select id="category_filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Ingredients Table -->
            <div class="table-container">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Price per Unit</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ingredients)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-apple-alt" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                    No ingredients found. <a href="#" onclick="openAddModal()">Add your first ingredient</a> to get started!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ingredients as $ingredient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ingredient['name']); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['category'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                                    <td><?php echo formatCurrency($ingredient['price_per_unit']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($ingredient['updated_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm" onclick="editIngredient(<?php echo htmlspecialchars(json_encode($ingredient)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteIngredient(<?php echo $ingredient['id']; ?>)">
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

    <!-- Tutorial Modal -->
    <div id="tutorialModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 class="modal-title">How to Add Ingredients - Step by Step Guide</h2>
                <span class="close" onclick="closeTutorial()">&times;</span>
            </div>
            <div style="padding: 20px 0;">
                <div class="tutorial-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Click "Add Ingredient" Button</h3>
                        <p>Click the blue "Add Ingredient" button to open the ingredient form.</p>
                        <div class="step-image">
                            <i class="fas fa-plus-circle" style="font-size: 3rem; color: #16a085;"></i>
                        </div>
                    </div>
                </div>

                <div class="tutorial-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Enter Ingredient Name</h3>
                        <p>Type the name of your ingredient (e.g., "All-Purpose Flour", "Chicken Breast", "Olive Oil").</p>
                        <div class="step-example">
                            <strong>Examples:</strong> Flour, Sugar, Butter, Eggs, Milk, Salt, Pepper, Onions, Tomatoes
                        </div>
                    </div>
                </div>

                <div class="tutorial-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Choose a Category (Optional)</h3>
                        <p>Select or create a category to organize your ingredients (e.g., "Baking", "Vegetables", "Meat").</p>
                        <div class="step-example">
                            <strong>Common Categories:</strong> Baking, Dairy, Vegetables, Meat, Spices, Fruits, Grains
                        </div>
                    </div>
                </div>

                <div class="tutorial-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Select Unit of Measurement</h3>
                        <p>Choose how the ingredient is sold (e.g., kg, lb, liter, cup, piece).</p>
                        <div class="step-example">
                            <strong>Common Units:</strong><br>
                            • <strong>Weight:</strong> kg, lb, oz, g<br>
                            • <strong>Volume:</strong> l, ml, cup, tbsp, tsp<br>
                            • <strong>Count:</strong> piece, dozen, pack
                        </div>
                    </div>
                </div>

                <div class="tutorial-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h3>Enter Price per Unit</h3>
                        <p>Enter the current price for the selected unit (e.g., ₱45.00 per kg).</p>
                        <div class="step-example">
                            <strong>Example:</strong> If you buy 1kg of flour for ₱45.00, enter "45.00"
                        </div>
                    </div>
                </div>

                <div class="tutorial-step">
                    <div class="step-number">6</div>
                    <div class="step-content">
                        <h3>Use Unit Converter (Optional)</h3>
                        <p>If you need to convert between units, use the built-in converter below the form.</p>
                        <div class="step-example">
                            <strong>Example:</strong> Convert 1 lb to kg, or 1 cup to ml
                        </div>
                    </div>
                </div>

                <div class="tutorial-step">
                    <div class="step-number">7</div>
                    <div class="step-content">
                        <h3>Save Your Ingredient</h3>
                        <p>Click "Save Ingredient" to add it to your inventory.</p>
                        <div class="step-tip">
                            <i class="fas fa-lightbulb"></i>
                            <strong>Tip:</strong> Keep ingredient prices updated for accurate recipe costing!
                        </div>
                    </div>
                </div>

                <div class="tutorial-tips">
                    <h3><i class="fas fa-star"></i> Pro Tips for Better Results:</h3>
                    <ul>
                        <li><strong>Be Specific:</strong> Use clear, descriptive names (e.g., "Extra Virgin Olive Oil" instead of just "Oil")</li>
                        <li><strong>Update Prices Regularly:</strong> Ingredient prices change, so update them monthly</li>
                        <li><strong>Use Categories:</strong> Group similar ingredients for easier management</li>
                        <li><strong>Check Units:</strong> Make sure you're using the same unit as your supplier</li>
                        <li><strong>Start Simple:</strong> Begin with your most commonly used ingredients</li>
                    </ul>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn btn-primary" onclick="closeTutorial(); openAddModal();">
                        <i class="fas fa-plus"></i> Start Adding Ingredients
                    </button>
                    <button class="btn btn-secondary" onclick="closeTutorial()">
                        <i class="fas fa-times"></i> Close Tutorial
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Tips Modal -->
    <div id="quickTipsModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">Quick Tips for Managing Ingredients</h2>
                <span class="close" onclick="closeQuickTips()">&times;</span>
            </div>
            <div style="padding: 20px 0;">
                <div class="tutorial-tips">
                    <h3><i class="fas fa-star"></i> Best Practices:</h3>
                    <ul>
                        <li><strong>Update Prices Regularly:</strong> Check and update ingredient prices monthly to maintain accurate recipe costs</li>
                        <li><strong>Use Consistent Units:</strong> Stick to the same unit system (metric or imperial) for easier management</li>
                        <li><strong>Be Specific with Names:</strong> "Extra Virgin Olive Oil" is better than just "Oil"</li>
                        <li><strong>Create Categories:</strong> Group ingredients by type (Baking, Dairy, Vegetables) for better organization</li>
                        <li><strong>Track Usage:</strong> Monitor which ingredients are used most in your recipes</li>
                    </ul>
                </div>

                <div class="tutorial-tips" style="background: #e8f5e8; border-color: #27ae60;">
                    <h3><i class="fas fa-calculator"></i> Cost Management:</h3>
                    <ul>
                        <li><strong>Bulk Buying:</strong> Enter bulk prices and use unit converter for recipe calculations</li>
                        <li><strong>Seasonal Prices:</strong> Update prices when seasonal ingredients change cost</li>
                        <li><strong>Supplier Comparison:</strong> Track prices from different suppliers in ingredient names</li>
                        <li><strong>Waste Factor:</strong> Consider adding 5-10% to ingredient costs for waste and spillage</li>
                    </ul>
                </div>

                <div class="tutorial-tips" style="background: #fff3e0; border-color: #f39c12;">
                    <h3><i class="fas fa-tools"></i> Time-Saving Tips:</h3>
                    <ul>
                        <li><strong>Use Search:</strong> Quickly find ingredients using the search bar</li>
                        <li><strong>Filter by Category:</strong> Use category filter to view specific ingredient types</li>
                        <li><strong>Copy Similar Ingredients:</strong> Edit existing ingredients to create similar ones faster</li>
                        <li><strong>Unit Converter:</strong> Use the built-in converter for quick unit conversions</li>
                    </ul>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn btn-primary" onclick="closeQuickTips(); openAddModal();">
                        <i class="fas fa-plus"></i> Add New Ingredient
                    </button>
                    <button class="btn btn-secondary" onclick="closeQuickTips()">
                        <i class="fas fa-times"></i> Close Tips
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Ingredient Modal -->
    <div id="ingredientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add Ingredient</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="ingredientForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="ingredientId">
                
                <div class="form-group">
                    <label for="name">Ingredient Name *</label>
                    <input type="text" id="name" name="name" required placeholder="e.g., All-Purpose Flour, Chicken Breast, Olive Oil">
                    <small style="color: #7f8c8d; font-size: 0.9rem;">Be specific and descriptive for better organization</small>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" placeholder="e.g., Baking, Dairy, Vegetables, Meat, Spices">
                    <small style="color: #7f8c8d; font-size: 0.9rem;">Optional: Helps organize your ingredients</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="unit">Unit *</label>
                        <select id="unit" name="unit" required>
                            <option value="">Select Unit</option>
                            <?php foreach ($all_units as $unit): ?>
                                <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #7f8c8d; font-size: 0.9rem;">How the ingredient is sold (kg, liter, piece, etc.)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price per Unit *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00" oninput="this.value = parseFloat(this.value).toFixed(2)">
                        <small style="color: #7f8c8d; font-size: 0.9rem;">Enter the current price for the selected unit</small>
                    </div>
                </div>
                
                <div class="unit-converter" id="unitConverter" style="display: none;">
                    <h4>Unit Converter</h4>
                    <div class="converter-row">
                        <div class="converter-input">
                            <label>Convert from:</label>
                            <input type="number" id="convertFrom" placeholder="Enter value">
                        </div>
                        <div class="converter-input">
                            <label>Unit:</label>
                            <select id="convertFromUnit">
                                <option value="">Select Unit</option>
                            </select>
                        </div>
                        <div class="converter-input">
                            <label>To:</label>
                            <select id="convertToUnit">
                                <option value="">Select Unit</option>
                            </select>
                        </div>
                        <div class="converter-input">
                            <label>Result:</label>
                            <input type="text" id="convertResult" readonly>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Ingredient</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/ingredients.js"></script>
</body>
</html>
