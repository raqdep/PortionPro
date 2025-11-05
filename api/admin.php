<?php
/**
 * Admin API for User Management
 * Handles admin operations like deleting users and verifying users
 */

session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    switch ($action) {
        case 'delete_user':
            handleDeleteUser($db);
            break;
        case 'verify_user':
            handleVerifyUser($db);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleDeleteUser($db) {
    $userId = $_POST['user_id'] ?? 0;
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Check if user exists and is not an admin
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    if ($user['role'] === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin users']);
        return;
    }
    
    // Delete user (cascade will delete related data)
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    
    if ($stmt->execute([$userId])) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

function handleVerifyUser($db) {
    $userId = $_POST['user_id'] ?? 0;
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Update user verification status
    $stmt = $db->prepare("UPDATE users SET is_verified = TRUE WHERE id = ?");
    
    if ($stmt->execute([$userId])) {
        echo json_encode(['success' => true, 'message' => 'User verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to verify user']);
    }
}
?>
