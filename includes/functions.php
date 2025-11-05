<?php
// Utility functions for PortionPro

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate CSRF token
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in (supports both traditional and Google OAuth)
function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) || isset($_SESSION['user']);
}

// Get current user ID (supports both traditional and Google OAuth)
function getCurrentUserId() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for traditional session
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    
    // Check for Google OAuth session
    if (isset($_SESSION['user']['id'])) {
        return $_SESSION['user']['id'];
    }
    
    return null;
}

// Get current user information (supports both traditional and Google OAuth)
function getCurrentUser() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for Google OAuth session first
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    
    // Fallback to traditional session data
    $user = [];
    if (isset($_SESSION['user_id'])) {
        $user['id'] = $_SESSION['user_id'];
    }
    if (isset($_SESSION['username'])) {
        $user['name'] = $_SESSION['username'];
    }
    if (isset($_SESSION['email'])) {
        $user['email'] = $_SESSION['email'];
    }
    if (isset($_SESSION['business_name'])) {
        $user['business_name'] = $_SESSION['business_name'];
    }
    
    return !empty($user) ? $user : null;
}

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Calculate cost per serving
function calculateCostPerServing($totalCost, $servings) {
    return $servings > 0 ? $totalCost / $servings : 0;
}

// Calculate selling price with profit margin
function calculateSellingPrice($costPerServing, $profitMargin) {
    return $costPerServing * (1 + ($profitMargin / 100));
}

// Calculate break-even point
function calculateBreakEvenPoint($fixedCosts, $sellingPrice, $costPerServing) {
    $contributionMargin = $sellingPrice - $costPerServing;
    return $contributionMargin > 0 ? ceil($fixedCosts / $contributionMargin) : 0;
}

// Convert units using the unit conversion table
function convertUnit($value, $fromUnit, $toUnit, $pdo) {
    if ($fromUnit === $toUnit) {
        return $value;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT conversion_factor FROM unit_conversions WHERE from_unit = ? AND to_unit = ?");
        $stmt->execute([$fromUnit, $toUnit]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $value * $result['conversion_factor'];
        }
        
        // If direct conversion not found, try reverse conversion
        $stmt = $pdo->prepare("SELECT conversion_factor FROM unit_conversions WHERE from_unit = ? AND to_unit = ?");
        $stmt->execute([$toUnit, $fromUnit]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $value / $result['conversion_factor'];
        }
        
        return $value; // Return original value if no conversion found
    } catch (PDOException $e) {
        return $value;
    }
}

// Get available units for a category
function getAvailableUnits($category, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT from_unit FROM unit_conversions WHERE category = ? ORDER BY from_unit");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

// Generate Excel file for reports
function generateExcelReport($data, $filename) {
	require_once __DIR__ . '/../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

	// Handle empty datasets gracefully
	if (empty($data)) {
		$sheet->setCellValue('A1', 'No data available');
	} else {
		// Set headers
		$headers = array_keys($data[0]);
		$col = 1;
		foreach ($headers as $header) {
			$sheet->setCellValueByColumnAndRow($col, 1, $header);
			$col++;
		}
		
		// Set data
		$row = 2;
		foreach ($data as $rowData) {
			$col = 1;
			foreach ($rowData as $value) {
				$sheet->setCellValueByColumnAndRow($col, $row, $value);
				$col++;
			}
			$row++;
		}
	}
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
