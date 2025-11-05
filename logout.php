<?php
session_start();
require_once 'config/database.php';

// Set timezone to Manila/Philippines
date_default_timezone_set('Asia/Manila');

// Log logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $userId = $_SESSION['user_id'];
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
            $stmt->execute([$userId, $ipAddress, $userAgent]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break logout if logging fails
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Clear all session data
session_unset();

// Destroy the session
session_destroy();

// Redirect to home page
header('Location: index.php');
exit();
?>
