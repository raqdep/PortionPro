<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/email_functions.php';

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Ensure table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // ignore
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'send_code') {
        $email = sanitizeInput($_POST['email'] ?? '');
        if (!$email) { echo json_encode(['success' => false, 'message' => 'Email is required']); exit; }

        // Check user exists
        $stmt = $db->prepare('SELECT id, username FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) { echo json_encode(['success' => true]); exit; } // do not reveal existence

        // Create code
        $code = str_pad((string)random_int(0, 999999), PASSWORD_RESET_CODE_LENGTH, '0', STR_PAD_LEFT);
        $expires = (new DateTime('+' . PASSWORD_RESET_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

        // Upsert latest
        $stmt = $db->prepare('INSERT INTO password_resets (email, code, expires_at, verified) VALUES (?, ?, ?, 0)');
        $stmt->execute([$email, $code, $expires]);

        // Send email using PHPMailer with Gmail SMTP
        $emailSent = sendPasswordResetEmail($email, $user['username'], $code);
        
        if ($emailSent) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
        } else {
            // Log the error but don't reveal it to the user
            error_log("Failed to send password reset email to: " . $email);
            echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please try again.']);
        }
        exit;
    }

    if ($action === 'verify_code') {
        $email = sanitizeInput($_POST['email'] ?? '');
        $code = sanitizeInput($_POST['code'] ?? '');
        $stmt = $db->prepare('SELECT id FROM password_resets WHERE email = ? AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email, $code]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Invalid or expired code']); exit; }
        $db->prepare('UPDATE password_resets SET verified = 1 WHERE id = ?')->execute([$row['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reset_password') {
        $email = sanitizeInput($_POST['email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($newPassword) < 6) { echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']); exit; }
        if ($newPassword !== $confirm) { echo json_encode(['success' => false, 'message' => 'Passwords do not match']); exit; }

        $stmt = $db->prepare('SELECT id FROM password_resets WHERE email = ? AND verified = 1 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Verification required']); exit; }

        $hash = hashPassword($newPassword);
        $ok = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$hash, $email]);
        if ($ok) {
            $db->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
            echo json_encode(['success' => true, 'message' => 'Password has been reset.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>

