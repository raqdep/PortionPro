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

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $report_type = $_GET['type'] ?? 'recipes';
    
    if ($report_type === 'recipes') {
        exportRecipesReport($db, $user_id);
    } elseif ($report_type === 'ingredients') {
        exportIngredientsReport($db, $user_id);
    } elseif ($report_type === 'profitability') {
        exportProfitabilityReport($db, $user_id);
    } elseif ($report_type === 'recipe_details') {
        $recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        exportSingleRecipeDetailsReport($db, $user_id, $recipe_id);
    }
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT r.*
        FROM recipes r
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $recipes = $stmt->fetchAll();

    foreach ($recipes as &$recipeRow) {
        $recipeId = (int)$recipeRow['id'];
        $servings = max(1, (int)$recipeRow['servings']);

        $stmtIng = $db->prepare("
            SELECT ri.quantity as recipe_quantity, ri.unit as recipe_unit, i.unit as ingredient_unit, i.price_per_unit
            FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.id
            WHERE ri.recipe_id = ? AND i.user_id = ?
        ");
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
        $recipeRow['total_cost'] = $totalCost;
        $recipeRow['cost_per_serving'] = $totalCost / $servings;
    }
    unset($recipeRow);

    $total_recipes = count($recipes);
    $total_cost = $total_recipes > 0 ? array_sum(array_column($recipes, 'total_cost')) : 0;
    $total_servings = $total_recipes > 0 ? array_sum(array_column($recipes, 'servings')) : 0;
    $avg_cost_per_serving = $total_recipes > 0 && $total_servings > 0 ? $total_cost / $total_servings : 0;
    $avg_profit_margin = $total_recipes > 0 ? array_sum(array_column($recipes, 'profit_margin')) / $total_recipes : 0;

    $stmt = $db->prepare("SELECT * FROM ingredients WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$user_id]);
    $ingredients = $stmt->fetchAll();

    $stmtUse = $db->prepare("SELECT quantity, unit FROM recipe_ingredients WHERE ingredient_id = ?");
    foreach ($ingredients as &$ing) {
        $ingredientId = (int)$ing['id'];
        $ingredientUnit = $ing['unit'];
        $stmtUse->execute([$ingredientId]);
        $rows = $stmtUse->fetchAll();

        $usageCount = count($rows);
        $totalUsedConverted = 0.0;
        foreach ($rows as $row) {
            $qty = (float)$row['quantity'];
            $fromUnit = $row['unit'];
            $totalUsedConverted += (float)convertUnit($qty, $fromUnit, $ingredientUnit, $db);
        }
        $ing['usage_count'] = $usageCount;
        $ing['total_quantity_used'] = $totalUsedConverted;
        $ing['total_cost_used'] = $totalUsedConverted * (float)$ing['price_per_unit'];
    }
    unset($ing);

    $most_profitable = array_map(function($recipe) {
        $servings = max(1, (int)$recipe['servings']);
        $suggested_price = calculateSellingPrice($recipe['cost_per_serving'], $recipe['profit_margin']);
        $profit_per_serving = $suggested_price - $recipe['cost_per_serving'];
        $total_profit = $profit_per_serving * $servings;
        return array_merge($recipe, [
            'suggested_price' => $suggested_price,
            'profit_per_serving' => $profit_per_serving,
            'total_profit' => $total_profit,
            'profit_margin_amount' => $profit_per_serving
        ]);
    }, $recipes);

    // Sort by profit per serving
    usort($most_profitable, function($a, $b) {
        return $b['profit_per_serving'] <=> $a['profit_per_serving'];
    });

} catch (PDOException $e) {
    $recipes = [];
    $ingredients = [];
    $most_profitable = [];
    $total_recipes = 0;
    $total_cost = 0;
    $avg_cost_per_serving = 0;
    $avg_profit_margin = 0;
}

// Export functions
function exportRecipesReport($db, $user_id) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    
    // Remove default sheet
    $spreadsheet->removeSheetByIndex(0);
    
    // Get all recipes
    $stmt = $db->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $recipes = $stmt->fetchAll();
    
    $sheetIndex = 0;
    foreach ($recipes as $recipe) {
        $recipeId = (int)$recipe['id'];
        $servings = max(1, (int)$recipe['servings']);
        
        // Create a new sheet for each recipe
        $recipeSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, substr($recipe['name'], 0, 31)); // Excel sheet name limit
        $spreadsheet->addSheet($recipeSheet, $sheetIndex);
        $recipeSheet->setTitle(substr($recipe['name'], 0, 31));
        
        // Recipe header information
        $recipeSheet->setCellValue('A1', 'RECIPE DETAILS');
        $recipeSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        
        $recipeSheet->setCellValue('A3', 'Recipe Name:');
        $recipeSheet->setCellValue('B3', $recipe['name']);
        $recipeSheet->setCellValue('A4', 'Description:');
        $recipeSheet->setCellValue('B4', $recipe['description'] ?: 'No description');
        $recipeSheet->setCellValue('A5', 'Servings:');
        $recipeSheet->setCellValue('B5', $servings);
        $recipeSheet->setCellValue('A6', 'Profit Margin:');
        $recipeSheet->setCellValue('B6', $recipe['profit_margin'] . '%');
        $recipeSheet->setCellValue('A7', 'Created Date:');
        $recipeSheet->setCellValue('B7', $recipe['created_at']);
        
        // Get recipe ingredients with proper cost calculation
        $stmtIng = $db->prepare("
            SELECT i.name AS ingredient_name, i.unit AS ingredient_unit, i.price_per_unit,
                   ri.quantity AS recipe_quantity, ri.unit AS recipe_unit
            FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.id
            WHERE ri.recipe_id = ? AND i.user_id = ?
            ORDER BY i.name ASC
        ");
        $stmtIng->execute([$recipeId, $user_id]);
        $ingredients = $stmtIng->fetchAll();
        
        // Ingredients table headers
        $row = 9;
        $recipeSheet->setCellValue('A' . $row, 'INGREDIENT BREAKDOWN');
        $recipeSheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row += 2;
        
        $recipeSheet->setCellValue('A' . $row, 'Ingredient Name');
        $recipeSheet->setCellValue('B' . $row, 'Recipe Quantity');
        $recipeSheet->setCellValue('C' . $row, 'Recipe Unit');
        $recipeSheet->setCellValue('D' . $row, 'Converted Quantity');
        $recipeSheet->setCellValue('E' . $row, 'Ingredient Unit');
        $recipeSheet->setCellValue('F' . $row, 'Price per Unit (₱)');
        $recipeSheet->setCellValue('G' . $row, 'Line Cost (₱)');
        
        // Style headers
        $headerRange = 'A' . $row . ':G' . $row;
        $recipeSheet->getStyle($headerRange)->getFont()->setBold(true);
        $recipeSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $recipeSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
        
        $row++;
        $totalCost = 0.0;
        
        // Add ingredient data
        foreach ($ingredients as $ingredient) {
            $qty = (float)$ingredient['recipe_quantity'];
            $fromUnit = $ingredient['recipe_unit'];
            $toUnit = $ingredient['ingredient_unit'];
            $converted = convertUnit($qty, $fromUnit, $toUnit, $db);
            $lineCost = ((float)$converted) * ((float)$ingredient['price_per_unit']);
            $totalCost += $lineCost;
            
            $recipeSheet->setCellValue('A' . $row, $ingredient['ingredient_name']);
            $recipeSheet->setCellValue('B' . $row, number_format($qty, 3));
            $recipeSheet->setCellValue('C' . $row, $fromUnit);
            $recipeSheet->setCellValue('D' . $row, number_format($converted, 3));
            $recipeSheet->setCellValue('E' . $row, $toUnit);
            $recipeSheet->setCellValue('F' . $row, number_format($ingredient['price_per_unit'], 4));
            $recipeSheet->setCellValue('G' . $row, number_format($lineCost, 4));
            $row++;
        }
        
        // Cost summary
        $row += 2;
        $recipeSheet->setCellValue('A' . $row, 'COST SUMMARY');
        $recipeSheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row += 2;
        
        $costPerServing = $totalCost / $servings;
        $suggestedPrice = calculateSellingPrice($costPerServing, $recipe['profit_margin']);
        $profitPerServing = $suggestedPrice - $costPerServing;
        $totalProfit = $profitPerServing * $servings;
        
        $recipeSheet->setCellValue('A' . $row, 'Total Recipe Cost:');
        $recipeSheet->setCellValue('B' . $row, '₱' . number_format($totalCost, 2));
        $row++;
        $recipeSheet->setCellValue('A' . $row, 'Cost per Serving:');
        $recipeSheet->setCellValue('B' . $row, '₱' . number_format($costPerServing, 2));
        $row++;
        $recipeSheet->setCellValue('A' . $row, 'Suggested Price per Serving:');
        $recipeSheet->setCellValue('B' . $row, '₱' . number_format($suggestedPrice, 2));
        $row++;
        $recipeSheet->setCellValue('A' . $row, 'Profit per Serving:');
        $recipeSheet->setCellValue('B' . $row, '₱' . number_format($profitPerServing, 2));
        $row++;
        $recipeSheet->setCellValue('A' . $row, 'Total Profit (' . $servings . ' servings):');
        $recipeSheet->setCellValue('B' . $row, '₱' . number_format($totalProfit, 2));
        $row++;
        $recipeSheet->setCellValue('A' . $row, 'Profit Margin:');
        $recipeSheet->setCellValue('B' . $row, $recipe['profit_margin'] . '%');
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $recipeSheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $sheetIndex++;
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="recipes_detailed_report_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportIngredientsReport($db, $user_id) {
    $stmt = $db->prepare("
        SELECT i.name, i.category, i.unit, i.price_per_unit,
               COUNT(ri.id) as usage_count,
               SUM(ri.quantity) as total_quantity_used,
               i.updated_at
        FROM ingredients i
        LEFT JOIN recipe_ingredients ri ON i.id = ri.ingredient_id
        WHERE i.user_id = ?
        GROUP BY i.id
        ORDER BY i.name ASC
    ");
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll();
    
    generateExcelReport($data, 'ingredients_report_' . date('Y-m-d'));
}

function exportProfitabilityReport($db, $user_id) {
    // Create a comprehensive profitability report with multiple sheets
    require_once __DIR__ . '/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    
    // Remove default sheet
    $spreadsheet->removeSheetByIndex(0);
    
    // 1. INGREDIENT COST SUMMARY
    $ingredientSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ingredient Cost Summary');
    $spreadsheet->addSheet($ingredientSheet, 0);
    $ingredientSheet->setTitle('Ingredient Cost Summary');
    
    // Get all ingredients with proper cost calculation based on actual recipe usage
    $stmt = $db->prepare("SELECT * FROM ingredients WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$user_id]);
    $ingredients = $stmt->fetchAll();
    
    // Calculate actual costs for each ingredient based on recipe usage with unit conversions
    $ingredientCosts = [];
    foreach ($ingredients as $ingredient) {
        $ingredientId = (int)$ingredient['id'];
        $ingredientUnit = $ingredient['unit'];
        $pricePerUnit = (float)$ingredient['price_per_unit'];
        
        // Get all recipe usages for this ingredient
        $stmtUse = $db->prepare("
            SELECT ri.quantity as recipe_quantity, ri.unit as recipe_unit, r.name as recipe_name
            FROM recipe_ingredients ri
            JOIN recipes r ON ri.recipe_id = r.id
            WHERE ri.ingredient_id = ? AND r.user_id = ?
        ");
        $stmtUse->execute([$ingredientId, $user_id]);
        $usages = $stmtUse->fetchAll();
        
        $totalQuantityUsed = 0.0;
        $totalCostUsed = 0.0;
        $usageCount = count($usages);
        
        foreach ($usages as $usage) {
            $qty = (float)$usage['recipe_quantity'];
            $fromUnit = $usage['recipe_unit'];
            $convertedQty = convertUnit($qty, $fromUnit, $ingredientUnit, $db);
            $totalQuantityUsed += (float)$convertedQty;
            $totalCostUsed += (float)$convertedQty * $pricePerUnit;
        }
        
        $ingredientCosts[] = [
            'name' => $ingredient['name'],
            'category' => $ingredient['category'] ?? 'Uncategorized',
            'unit' => $ingredient['unit'],
            'price_per_unit' => $pricePerUnit,
            'total_quantity_used' => $totalQuantityUsed,
            'total_cost_used' => $totalCostUsed,
            'usage_count' => $usageCount,
            'updated_at' => $ingredient['updated_at']
        ];
    }
    
    // Add title and headers
    $ingredientSheet->setCellValue('A1', 'INGREDIENT COST SUMMARY');
    $ingredientSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $ingredientSheet->setCellValue('A2', 'List of all ingredients used across recipes with actual costs based on recipe usage');
    $ingredientSheet->getStyle('A2')->getFont()->setItalic(true);
    
    // Add ingredient cost summary headers
    $ingredientSheet->setCellValue('A4', 'Ingredient Name');
    $ingredientSheet->setCellValue('B4', 'Category');
    $ingredientSheet->setCellValue('C4', 'Unit');
    $ingredientSheet->setCellValue('D4', 'Unit Cost (₱)');
    $ingredientSheet->setCellValue('E4', 'Total Quantity Used (Converted)');
    $ingredientSheet->setCellValue('F4', 'Total Cost Used (₱)');
    $ingredientSheet->setCellValue('G4', 'Usage Count');
    $ingredientSheet->setCellValue('H4', 'Date of Last Price Update');
    
    // Style headers
    $headerRange = 'A4:H4';
    $ingredientSheet->getStyle($headerRange)->getFont()->setBold(true);
    $ingredientSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $ingredientSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    $row = 5;
    foreach ($ingredientCosts as $ingredient) {
        $ingredientSheet->setCellValue('A' . $row, $ingredient['name']);
        $ingredientSheet->setCellValue('B' . $row, $ingredient['category']);
        $ingredientSheet->setCellValue('C' . $row, $ingredient['unit']);
        $ingredientSheet->setCellValue('D' . $row, number_format($ingredient['price_per_unit'], 2));
        $ingredientSheet->setCellValue('E' . $row, number_format($ingredient['total_quantity_used'], 2));
        $ingredientSheet->setCellValue('F' . $row, number_format($ingredient['total_cost_used'], 2));
        $ingredientSheet->setCellValue('G' . $row, $ingredient['usage_count']);
        $ingredientSheet->setCellValue('H' . $row, $ingredient['updated_at']);
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'H') as $col) {
        $ingredientSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // 2. RECIPE COST BREAKDOWN
    $recipeSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Recipe Cost Breakdown');
    $spreadsheet->addSheet($recipeSheet, 1);
    $recipeSheet->setTitle('Recipe Cost Breakdown');
    
    $stmt = $db->prepare("SELECT r.id, r.name, r.servings, r.profit_margin, r.created_at FROM recipes r WHERE r.user_id = ? ORDER BY r.created_at DESC");
    $stmt->execute([$user_id]);
    $recipes = $stmt->fetchAll();

    // Add title and headers
    $recipeSheet->setCellValue('A1', 'RECIPE COST BREAKDOWN');
    $recipeSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $recipeSheet->setCellValue('A2', 'Each recipe with its total cost, portion size, and cost per portion');
    $recipeSheet->getStyle('A2')->getFont()->setItalic(true);

    // Add recipe cost breakdown headers
    $recipeSheet->setCellValue('A4', 'Recipe Name');
    $recipeSheet->setCellValue('B4', 'Number of Servings per Batch');
    $recipeSheet->setCellValue('C4', 'Total Cost (₱)');
    $recipeSheet->setCellValue('D4', 'Cost per Portion (₱)');
    $recipeSheet->setCellValue('E4', 'Suggested Price (₱)');
    $recipeSheet->setCellValue('F4', 'Profit per Portion (₱)');
    $recipeSheet->setCellValue('G4', 'Total Profit (₱)');
    $recipeSheet->setCellValue('H4', 'Profit Margin %');
    $recipeSheet->setCellValue('I4', 'Created Date');
    
    // Style headers
    $headerRange = 'A4:I4';
    $recipeSheet->getStyle($headerRange)->getFont()->setBold(true);
    $recipeSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $recipeSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    $row = 5;
    foreach ($recipes as $recipe) {
        $recipeId = (int)$recipe['id'];
        $servings = max(1, (int)$recipe['servings']);

        $stmtIng = $db->prepare("SELECT ri.quantity as recipe_quantity, ri.unit as recipe_unit, i.unit as ingredient_unit, i.price_per_unit FROM recipe_ingredients ri JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = ? AND i.user_id = ?");
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

        $costPerServing = $servings > 0 ? $totalCost / $servings : 0;
        $suggestedPrice = calculateSellingPrice($costPerServing, $recipe['profit_margin']);
        $profitPerServing = $suggestedPrice - $costPerServing;

        // Fix division by zero error
        $profitMarginPercentage = 0;
        if ($suggestedPrice > 0) {
            $profitMarginPercentage = (($suggestedPrice - $costPerServing) / $suggestedPrice) * 100;
        }

        $recipeSheet->setCellValue('A' . $row, $recipe['name']);
        $recipeSheet->setCellValue('B' . $row, $servings);
        $recipeSheet->setCellValue('C' . $row, number_format($totalCost, 2));
        $recipeSheet->setCellValue('D' . $row, number_format($costPerServing, 2));
        $recipeSheet->setCellValue('E' . $row, number_format($suggestedPrice, 2));
        $recipeSheet->setCellValue('F' . $row, number_format($profitPerServing, 2));
        $recipeSheet->setCellValue('G' . $row, number_format($profitPerServing * $servings, 2));
        $recipeSheet->setCellValue('H' . $row, number_format($profitMarginPercentage, 1) . '%');
        $recipeSheet->setCellValue('I' . $row, $recipe['created_at']);
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $recipeSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // 3. PRICING RECOMMENDATIONS
    $pricingSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Pricing Recommendations');
    $spreadsheet->addSheet($pricingSheet, 2);
    $pricingSheet->setTitle('Pricing Recommendations');
    
    // Add title and headers
    $pricingSheet->setCellValue('A1', 'PRICING RECOMMENDATIONS');
    $pricingSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $pricingSheet->setCellValue('A2', 'Suggested selling price per portion, markup percentage applied, and profit margin');
    $pricingSheet->getStyle('A2')->getFont()->setItalic(true);
    
    $pricingSheet->setCellValue('A4', 'Recipe Name');
    $pricingSheet->setCellValue('B4', 'Current Cost per Portion (₱)');
    $pricingSheet->setCellValue('C4', 'Suggested Selling Price (₱)');
    $pricingSheet->setCellValue('D4', 'Markup Percentage Applied (%)');
    $pricingSheet->setCellValue('E4', 'Profit Margin per Portion (%)');
    $pricingSheet->setCellValue('F4', 'Profit per Portion (₱)');
    $pricingSheet->setCellValue('G4', 'Total Profit per Recipe (₱)');
    
    // Style headers
    $headerRange = 'A4:G4';
    $pricingSheet->getStyle($headerRange)->getFont()->setBold(true);
    $pricingSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $pricingSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    $row = 5;
    foreach ($recipes as $recipe) {
        $recipeId = (int)$recipe['id'];
        $servings = max(1, (int)$recipe['servings']);

        $stmtIng = $db->prepare("SELECT ri.quantity as recipe_quantity, ri.unit as recipe_unit, i.unit as ingredient_unit, i.price_per_unit FROM recipe_ingredients ri JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = ? AND i.user_id = ?");
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

        $costPerServing = $servings > 0 ? $totalCost / $servings : 0;
        $suggestedPrice = calculateSellingPrice($costPerServing, $recipe['profit_margin']);
        $profitPerServing = $suggestedPrice - $costPerServing;
        $markupPercentage = $costPerServing > 0 ? (($suggestedPrice - $costPerServing) / $costPerServing) * 100 : 0;
        $profitMarginPercentage = $suggestedPrice > 0 ? (($suggestedPrice - $costPerServing) / $suggestedPrice) * 100 : 0;

        $pricingSheet->setCellValue('A' . $row, $recipe['name']);
        $pricingSheet->setCellValue('B' . $row, number_format($costPerServing, 2));
        $pricingSheet->setCellValue('C' . $row, number_format($suggestedPrice, 2));
        $pricingSheet->setCellValue('D' . $row, number_format($markupPercentage, 1) . '%');
        $pricingSheet->setCellValue('E' . $row, number_format($profitMarginPercentage, 1) . '%');
        $pricingSheet->setCellValue('F' . $row, number_format($profitPerServing, 2));
        $pricingSheet->setCellValue('G' . $row, number_format($profitPerServing * $servings, 2));
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $pricingSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // 4. MENU PROFITABILITY OVERVIEW
    $menuSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Menu Profitability Overview');
    $spreadsheet->addSheet($menuSheet, 3);
    $menuSheet->setTitle('Menu Profitability Overview');
    
    // Add title and headers
    $menuSheet->setCellValue('A1', 'MENU PROFITABILITY OVERVIEW');
    $menuSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $menuSheet->setCellValue('A2', 'Comparison of cost vs selling price, gross profit per item, and profitability ranking');
    $menuSheet->getStyle('A2')->getFont()->setItalic(true);
    
    $menuSheet->setCellValue('A4', 'Recipe Name');
    $menuSheet->setCellValue('B4', 'Cost vs Selling Price');
    $menuSheet->setCellValue('C4', 'Gross Profit per Item (₱)');
    $menuSheet->setCellValue('D4', 'Profitability Ranking');
    $menuSheet->setCellValue('E4', 'Profit Margin %');
    $menuSheet->setCellValue('F4', 'Total Profit per Batch (₱)');
    
    // Style headers
    $headerRange = 'A4:F4';
    $menuSheet->getStyle($headerRange)->getFont()->setBold(true);
    $menuSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $menuSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    // Calculate profitability data and sort by profit per serving
    $profitabilityData = [];
    foreach ($recipes as $recipe) {
        $recipeId = (int)$recipe['id'];
        $servings = max(1, (int)$recipe['servings']);

        $stmtIng = $db->prepare("SELECT ri.quantity as recipe_quantity, ri.unit as recipe_unit, i.unit as ingredient_unit, i.price_per_unit FROM recipe_ingredients ri JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = ? AND i.user_id = ?");
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

        $costPerServing = $servings > 0 ? $totalCost / $servings : 0;
        $suggestedPrice = calculateSellingPrice($costPerServing, $recipe['profit_margin']);
        $profitPerServing = $suggestedPrice - $costPerServing;
        $profitMarginPercentage = $suggestedPrice > 0 ? (($suggestedPrice - $costPerServing) / $suggestedPrice) * 100 : 0;

        $profitabilityData[] = [
            'name' => $recipe['name'],
            'cost_per_serving' => $costPerServing,
            'suggested_price' => $suggestedPrice,
            'profit_per_serving' => $profitPerServing,
            'profit_margin' => $profitMarginPercentage,
            'total_profit' => $profitPerServing * $servings
        ];
    }
    
    // Sort by profit per serving (descending)
    usort($profitabilityData, function($a, $b) {
        return $b['profit_per_serving'] <=> $a['profit_per_serving'];
    });
    
    $row = 5;
    $ranking = 1;
    foreach ($profitabilityData as $data) {
        $menuSheet->setCellValue('A' . $row, $data['name']);
        $menuSheet->setCellValue('B' . $row, '₱' . number_format($data['cost_per_serving'], 2) . ' vs ₱' . number_format($data['suggested_price'], 2));
        $menuSheet->setCellValue('C' . $row, number_format($data['profit_per_serving'], 2));
        $menuSheet->setCellValue('D' . $row, $ranking);
        $menuSheet->setCellValue('E' . $row, number_format($data['profit_margin'], 1) . '%');
        $menuSheet->setCellValue('F' . $row, number_format($data['total_profit'], 2));
        $row++;
        $ranking++;
    }
    
    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $menuSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // 5. ANALYTICS AND INSIGHTS
    $analyticsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Analytics and Insights');
    $spreadsheet->addSheet($analyticsSheet, 4);
    $analyticsSheet->setTitle('Analytics and Insights');
    
    // Add main title
    $analyticsSheet->setCellValue('A1', 'ANALYTICS AND INSIGHTS');
    $analyticsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $analyticsSheet->setCellValue('A2', 'Visual charts and comprehensive business analysis');
    $analyticsSheet->getStyle('A2')->getFont()->setItalic(true);
    
    // Most profitable items
    $analyticsSheet->setCellValue('A4', 'MOST PROFITABLE ITEMS');
    $analyticsSheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
    $analyticsSheet->setCellValue('A5', 'Recipe Name');
    $analyticsSheet->setCellValue('B5', 'Profit per Serving (₱)');
    $analyticsSheet->setCellValue('C5', 'Total Profit per Batch (₱)');
    $analyticsSheet->setCellValue('D5', 'Profit Margin %');
    $analyticsSheet->setCellValue('E5', 'Ranking');
    
    // Style headers
    $headerRange = 'A5:E5';
    $analyticsSheet->getStyle($headerRange)->getFont()->setBold(true);
    $analyticsSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $analyticsSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    $row = 6;
    $ranking = 1;
    foreach (array_slice($profitabilityData, 0, 10) as $data) {
        $analyticsSheet->setCellValue('A' . $row, $data['name']);
        $analyticsSheet->setCellValue('B' . $row, number_format($data['profit_per_serving'], 2));
        $analyticsSheet->setCellValue('C' . $row, number_format($data['total_profit'], 2));
        $analyticsSheet->setCellValue('D' . $row, number_format($data['profit_margin'], 1) . '%');
        $analyticsSheet->setCellValue('E' . $row, $ranking);
        $row++;
        $ranking++;
    }
    
    // Cost distribution analysis
    $row += 2;
    $analyticsSheet->setCellValue('A' . $row, 'COST DISTRIBUTION ACROSS INGREDIENTS');
    $analyticsSheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    $analyticsSheet->setCellValue('A' . $row, 'Ingredient Name');
    $analyticsSheet->setCellValue('B' . $row, 'Total Cost Used (₱)');
    $analyticsSheet->setCellValue('C' . $row, 'Percentage of Total Cost (%)');
    $analyticsSheet->setCellValue('D' . $row, 'Usage Count');
    $analyticsSheet->setCellValue('E' . $row, 'Average Cost per Use (₱)');
    
    // Style headers
    $headerRange = 'A' . $row . ':E' . $row;
    $analyticsSheet->getStyle($headerRange)->getFont()->setBold(true);
    $analyticsSheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $analyticsSheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    $totalIngredientCost = 0;
    foreach ($ingredientCosts as $ingredient) {
        $totalIngredientCost += $ingredient['total_cost_used'];
    }
    
    $row++;
    foreach ($ingredientCosts as $ingredient) {
        $ingredientCost = $ingredient['total_cost_used'];
        $percentage = $totalIngredientCost > 0 ? ($ingredientCost / $totalIngredientCost) * 100 : 0;
        $avgCostPerUse = $ingredient['usage_count'] > 0 ? $ingredientCost / $ingredient['usage_count'] : 0;
        
        $analyticsSheet->setCellValue('A' . $row, $ingredient['name']);
        $analyticsSheet->setCellValue('B' . $row, number_format($ingredientCost, 2));
        $analyticsSheet->setCellValue('C' . $row, number_format($percentage, 1) . '%');
        $analyticsSheet->setCellValue('D' . $row, $ingredient['usage_count']);
        $analyticsSheet->setCellValue('E' . $row, number_format($avgCostPerUse, 2));
        $row++;
    }
    
    // Business Summary
    $row += 2;
    $analyticsSheet->setCellValue('A' . $row, 'BUSINESS SUMMARY');
    $analyticsSheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $totalRecipes = count($recipes);
    $totalCost = array_sum(array_column($profitabilityData, 'cost_per_serving'));
    $totalRevenue = array_sum(array_column($profitabilityData, 'suggested_price'));
    $totalProfit = array_sum(array_column($profitabilityData, 'profit_per_serving'));
    $avgProfitMargin = $totalRecipes > 0 ? array_sum(array_column($profitabilityData, 'profit_margin')) / $totalRecipes : 0;
    
    $analyticsSheet->setCellValue('A' . $row, 'Total Recipes:');
    $analyticsSheet->setCellValue('B' . $row, $totalRecipes);
    $row++;
    $analyticsSheet->setCellValue('A' . $row, 'Total Ingredient Cost:');
    $analyticsSheet->setCellValue('B' . $row, '₱' . number_format($totalIngredientCost, 2));
    $row++;
    $analyticsSheet->setCellValue('A' . $row, 'Average Profit Margin:');
    $analyticsSheet->setCellValue('B' . $row, number_format($avgProfitMargin, 1) . '%');
    $row++;
    $analyticsSheet->setCellValue('A' . $row, 'Most Profitable Recipe:');
    $analyticsSheet->setCellValue('B' . $row, !empty($profitabilityData) ? $profitabilityData[0]['name'] : 'N/A');
    $row++;
    $analyticsSheet->setCellValue('A' . $row, 'Highest Profit per Serving:');
    $analyticsSheet->setCellValue('B' . $row, !empty($profitabilityData) ? '₱' . number_format($profitabilityData[0]['profit_per_serving'], 2) : 'N/A');
    
    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $analyticsSheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="profitability_report_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportSingleRecipeDetailsReport($db, $user_id, $recipe_id) {
    if ($recipe_id <= 0) {
        // Fallback to empty export
        generateExcelReport([], 'recipe_details_' . date('Y-m-d'));
        return;
    }

    // Fetch recipe base info
    $stmt = $db->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
    $stmt->execute([$recipe_id, $user_id]);
    $recipe = $stmt->fetch();

    if (!$recipe) {
        generateExcelReport([], 'recipe_details_' . date('Y-m-d'));
        return;
    }

    require_once __DIR__ . '/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    $servings = max(1, (int)$recipe['servings']);

    // Recipe header information
    $worksheet->setCellValue('A1', 'RECIPE DETAILS');
    $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    
    $worksheet->setCellValue('A3', 'Recipe Name:');
    $worksheet->setCellValue('B3', $recipe['name']);
    $worksheet->setCellValue('A4', 'Description:');
    $worksheet->setCellValue('B4', $recipe['description'] ?: 'No description');
    $worksheet->setCellValue('A5', 'Servings:');
    $worksheet->setCellValue('B5', $servings);
    $worksheet->setCellValue('A6', 'Profit Margin:');
    $worksheet->setCellValue('B6', $recipe['profit_margin'] . '%');
    $worksheet->setCellValue('A7', 'Created Date:');
    $worksheet->setCellValue('B7', $recipe['created_at']);
    
    // Get recipe ingredients with proper cost calculation
    $stmtIng = $db->prepare("
        SELECT i.name AS ingredient_name, i.unit AS ingredient_unit, i.price_per_unit,
                ri.quantity AS recipe_quantity, ri.unit AS recipe_unit
         FROM recipe_ingredients ri
         JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ? AND i.user_id = ?
        ORDER BY i.name ASC
    ");
    $stmtIng->execute([$recipe_id, $user_id]);
    $ingredients = $stmtIng->fetchAll();
    
    // Ingredients table headers
    $row = 9;
    $worksheet->setCellValue('A' . $row, 'INGREDIENT BREAKDOWN');
    $worksheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $row += 2;
    
    $worksheet->setCellValue('A' . $row, 'Ingredient Name');
    $worksheet->setCellValue('B' . $row, 'Recipe Quantity');
    $worksheet->setCellValue('C' . $row, 'Recipe Unit');
    $worksheet->setCellValue('D' . $row, 'Converted Quantity');
    $worksheet->setCellValue('E' . $row, 'Ingredient Unit');
    $worksheet->setCellValue('F' . $row, 'Price per Unit (₱)');
    $worksheet->setCellValue('G' . $row, 'Line Cost (₱)');
    
    // Style headers
    $headerRange = 'A' . $row . ':G' . $row;
    $worksheet->getStyle($headerRange)->getFont()->setBold(true);
    $worksheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $worksheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('E8F4FD');
    
    $row++;
    $totalCost = 0.0;

    // Add ingredient data
    foreach ($ingredients as $ingredient) {
        $qty = (float)$ingredient['recipe_quantity'];
        $fromUnit = $ingredient['recipe_unit'];
        $toUnit = $ingredient['ingredient_unit'];
        $converted = convertUnit($qty, $fromUnit, $toUnit, $db);
        $lineCost = ((float)$converted) * ((float)$ingredient['price_per_unit']);
        $totalCost += $lineCost;

        $worksheet->setCellValue('A' . $row, $ingredient['ingredient_name']);
        $worksheet->setCellValue('B' . $row, number_format($qty, 3));
        $worksheet->setCellValue('C' . $row, $fromUnit);
        $worksheet->setCellValue('D' . $row, number_format($converted, 3));
        $worksheet->setCellValue('E' . $row, $toUnit);
        $worksheet->setCellValue('F' . $row, number_format($ingredient['price_per_unit'], 4));
        $worksheet->setCellValue('G' . $row, number_format($lineCost, 4));
        $row++;
    }
    
    // Cost summary
    $row += 2;
    $worksheet->setCellValue('A' . $row, 'COST SUMMARY');
    $worksheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $row += 2;

    $costPerServing = $totalCost / $servings;
    $suggestedPrice = calculateSellingPrice($costPerServing, $recipe['profit_margin']);
    $profitPerServing = $suggestedPrice - $costPerServing;
    $totalProfit = $profitPerServing * $servings;
    
    $worksheet->setCellValue('A' . $row, 'Total Recipe Cost:');
    $worksheet->setCellValue('B' . $row, '₱' . number_format($totalCost, 2));
    $row++;
    $worksheet->setCellValue('A' . $row, 'Cost per Serving:');
    $worksheet->setCellValue('B' . $row, '₱' . number_format($costPerServing, 2));
    $row++;
    $worksheet->setCellValue('A' . $row, 'Suggested Price per Serving:');
    $worksheet->setCellValue('B' . $row, '₱' . number_format($suggestedPrice, 2));
    $row++;
    $worksheet->setCellValue('A' . $row, 'Profit per Serving:');
    $worksheet->setCellValue('B' . $row, '₱' . number_format($profitPerServing, 2));
    $row++;
    $worksheet->setCellValue('A' . $row, 'Total Profit (' . $servings . ' servings):');
    $worksheet->setCellValue('B' . $row, '₱' . number_format($totalProfit, 2));
    $row++;
    $worksheet->setCellValue('A' . $row, 'Profit Margin:');
    $worksheet->setCellValue('B' . $row, $recipe['profit_margin'] . '%');
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $worksheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set headers for download
    $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $recipe['name']);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="recipe_details_' . $safeName . '_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PortionPro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="recipes.php">
                    <i class="fas fa-book"></i> Recipes
                </a>
                <a href="reports.php" class="active">
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
            <h1 class="page-title">Business Reports & Analytics</h1>
            <p class="page-subtitle">Analyze your business performance and make data-driven decisions</p>
        </div>

        <!-- Export Buttons -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Export Reports</h2>
            </div>
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <a href="?export=excel&type=recipes" class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i> Export Recipes Report
                </a>
                <a href="?export=excel&type=ingredients" class="btn btn-primary">
                    <i class="fas fa-file-excel"></i> Export Ingredients Report
                </a>
                <a href="?export=excel&type=profitability" class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i> Export Profitability Report
                </a>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $total_recipes; ?></div>
                <div class="stat-label">Total Recipes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($total_cost); ?></div>
                <div class="stat-label">Total Recipe Cost</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value"><?php echo number_format($avg_profit_margin, 1); ?>%</div>
                <div class="stat-label">Avg Profit Margin</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($avg_cost_per_serving); ?></div>
                <div class="stat-label">Avg Cost per Serving</div>
            </div>
        </div>

        <!-- Explanation Toggle -->
        <div class="card">
            <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <h2 class="card-title" style="margin: 0;">How are these calculated?</h2>
                <button type="button" class="btn btn-secondary" onclick="toggleExplanation()">
                    <i class="fas fa-info-circle"></i> Show Explanation
                </button>
            </div>
            <div id="explanationPanel" style="display: none; padding: 15px; color: #2c3e50;">
                <h3 style="margin-top: 0;">Formulas & Computation</h3>
                <ul style="margin: 0 0 10px 18px;">
                    <li><strong>Total Recipe Cost</strong>: Sum of each recipe’s ingredient cost, where ingredient cost = (recipe quantity converted to ingredient base unit) × (price per unit).</li>
                    <li><strong>Average Cost per Serving</strong>: Total Recipe Cost ÷ Total Servings across all recipes.</li>
                </ul>
                <div style="background: #f8f9fa; border: 1px solid #ecf0f1; border-radius: 6px; padding: 12px;">
                    <p style="margin: 0 0 6px 0;"><strong>Current Values</strong></p>
                    <p style="margin: 0;">Total Recipe Cost = <strong><?php echo formatCurrency($total_cost); ?></strong></p>
                    <p style="margin: 0;">Total Servings = <strong><?php echo number_format($total_servings); ?></strong></p>
                    <p style="margin: 0;">Average Cost per Serving = <strong><?php echo formatCurrency($avg_cost_per_serving); ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Most Profitable Recipes -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Most Profitable Recipes</h2>
                <p style="color: #7f8c8d; margin: 0;">Ranked by profit per serving</p>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Recipe Name</th>
                            <th>Servings</th>
                            <th>Cost per Serving</th>
                            <th>Suggested Price</th>
                            <th>Profit per Serving</th>
                            <th>Total Profit</th>
                            <th>Profit Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($most_profitable)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-chart-line" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                    No recipes found. <a href="recipes.php">Create your first recipe</a> to see profitability analysis!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($most_profitable, 0, 10) as $recipe): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                                    <td><?php echo $recipe['servings']; ?></td>
                                    <td><?php echo formatCurrency($recipe['cost_per_serving']); ?></td>
                                    <td><?php echo formatCurrency($recipe['suggested_price']); ?></td>
                                    <td style="color: #27ae60; font-weight: bold;"><?php echo formatCurrency($recipe['profit_per_serving']); ?></td>
                                    <td style="color: #27ae60; font-weight: bold;"><?php echo formatCurrency($recipe['total_profit']); ?></td>
                                    <td><?php echo $recipe['profit_margin']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ingredient Usage Analysis -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Ingredient Usage Analysis</h2>
                <p style="color: #7f8c8d; margin: 0;">Most used ingredients in your recipes</p>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Category</th>
                            <th>Price per Unit</th>
                            <th>Times Used</th>
                            <th>Total Quantity Used</th>
                            <th>Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ingredients)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-apple-alt" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                    No ingredients found. <a href="ingredients.php">Add ingredients</a> to see usage analysis!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($ingredients, 0, 15) as $ingredient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ingredient['name']); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['category'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo formatCurrency($ingredient['price_per_unit']); ?></td>
                                    <td><?php echo $ingredient['usage_count']; ?></td>
                                    <td><?php echo number_format($ingredient['total_quantity_used'], 2); ?> <?php echo htmlspecialchars($ingredient['unit']); ?></td>
                                    <td><?php echo formatCurrency($ingredient['total_cost_used']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cost Analysis Chart -->
        <?php if (!empty($recipes)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recipe Cost Analysis</h2>
                <p style="color: #7f8c8d; margin: 0;">Visual breakdown of recipe costs and profitability</p>
            </div>
            <div style="height: 400px;">
                <canvas id="costChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Business Recommendations -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Business Recommendations</h2>
                <p style="color: #7f8c8d; margin: 0;">AI-powered insights for your business</p>
            </div>
            <div id="recommendations">
                <?php
                $recommendations = [];
                
                if ($total_recipes > 0) {
                    // Find recipes with low profit margins
                    $low_margin_recipes = array_filter($most_profitable, function($recipe) {
                        return $recipe['profit_margin'] < 25;
                    });
                    
                    if (!empty($low_margin_recipes)) {
                        $recommendations[] = [
                            'type' => 'warning',
                            'icon' => 'fas fa-exclamation-triangle',
                            'title' => 'Low Profit Margin Alert',
                            'message' => 'You have ' . count($low_margin_recipes) . ' recipes with profit margins below 25%. Consider reviewing ingredient costs or increasing prices.'
                        ];
                    }
                    
                    // Find most profitable recipe
                    if (!empty($most_profitable)) {
                        $best_recipe = $most_profitable[0];
                        $recommendations[] = [
                            'type' => 'success',
                            'icon' => 'fas fa-star',
                            'title' => 'Top Performer',
                            'message' => '"' . $best_recipe['name'] . '" is your most profitable recipe with ₱' . number_format($best_recipe['profit_per_serving'], 2) . ' profit per serving. Consider promoting this item!'
                        ];
                    }
                    
                    // Ingredient usage recommendations
                    $unused_ingredients = array_filter($ingredients, function($ingredient) {
                        return $ingredient['usage_count'] == 0;
                    });
                    
                    if (!empty($unused_ingredients)) {
                        $recommendations[] = [
                            'type' => 'info',
                            'icon' => 'fas fa-lightbulb',
                            'title' => 'Unused Ingredients',
                            'message' => 'You have ' . count($unused_ingredients) . ' ingredients that aren\'t being used in any recipes. Consider creating new recipes or removing unused ingredients.'
                        ];
                    }
                } else {
                    $recommendations[] = [
                        'type' => 'info',
                        'icon' => 'fas fa-info-circle',
                        'title' => 'Get Started',
                        'message' => 'Create your first recipe to unlock detailed analytics and business insights!'
                    ];
                }
                
                foreach ($recommendations as $rec): ?>
                    <div class="alert alert-<?php echo $rec['type']; ?>" style="margin-bottom: 15px;">
                        <i class="<?php echo $rec['icon']; ?>"></i>
                        <strong><?php echo $rec['title']; ?>:</strong> <?php echo $rec['message']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Pass PHP data to JavaScript
        const recipes = <?php echo json_encode($most_profitable); ?>;
        
        // Initialize cost chart
        document.addEventListener('DOMContentLoaded', function() {
            if (recipes.length > 0) {
                initializeCostChart();
            }
        });
        
        function toggleExplanation() {
            const panel = document.getElementById('explanationPanel');
            const isHidden = panel.style.display === 'none';
            panel.style.display = isHidden ? 'block' : 'none';
        }
        
        function initializeCostChart() {
            const ctx = document.getElementById('costChart').getContext('2d');
            const recipeNames = recipes.slice(0, 10).map(r => r.name);
            const costs = recipes.slice(0, 10).map(r => parseFloat(r.cost_per_serving));
            const profits = recipes.slice(0, 10).map(r => parseFloat(r.profit_per_serving));
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: recipeNames,
                    datasets: [{
                        label: 'Cost per Serving',
                        data: costs,
                        backgroundColor: '#16a085',
                        borderColor: '#138d75',
                        borderWidth: 1
                    }, {
                        label: 'Profit per Serving',
                        data: profits,
                        backgroundColor: '#f39c12',
                        borderColor: '#e67e22',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toFixed(2);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Recipe Cost vs Profit Analysis'
                        }
                    }
                }
            });
        }
        
        // Logout function
        function logout() {
            Swal.fire({
                title: 'Logout',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, logout!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'api/logout.php';
                }
            });
        }
    </script>
</body>
</html>
