<?php
// Email Verification API for PortionPro
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/email_functions.php';

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
    if ($action === 'resend_verification') {
        handleResendVerification($db);
    } elseif ($action === 'check_verification_status') {
        handleCheckVerificationStatus($db);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleResendVerification($db) {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address is required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, username, email, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with this email address']);
        return;
    }
    
    if ($user['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'This email address is already verified']);
        return;
    }
    
    // Generate new verification token
    $verificationToken = generateVerificationToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Update user with new verification token
    $stmt = $db->prepare("UPDATE users SET verification_token = ?, verification_expires_at = ? WHERE id = ?");
    $result = $stmt->execute([$verificationToken, $expiresAt, $user['id']]);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Failed to generate verification token']);
        return;
    }
    
    // Send verification email
    $emailSent = sendEmailVerificationEmail($user['email'], $user['username'], $verificationToken);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true, 
            'message' => 'Verification email sent successfully! Please check your inbox and spam folder.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send verification email. Please try again later.'
        ]);
    }
}

function handleCheckVerificationStatus($db) {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address is required']);
        return;
    }
    
    // Check if user exists and is verified
    $stmt = $db->prepare("SELECT id, username, email, is_verified, verification_expires_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with this email address']);
        return;
    }
    
    if ($user['is_verified']) {
        echo json_encode([
            'success' => true, 
            'verified' => true,
            'message' => 'Email is verified. You can now log in.'
        ]);
    } else {
        $canResend = true;
        $message = 'Email is not verified yet.';
        
        // Check if verification token is still valid
        if (!empty($user['verification_expires_at'])) {
            $canResend = !isVerificationTokenValid('dummy', $user['verification_expires_at']);
            if (!$canResend) {
                $message = 'Email verification is pending. Please check your email for the verification link.';
            } else {
                $message = 'Email verification has expired. You can request a new verification email.';
            }
        }
        
        echo json_encode([
            'success' => true, 
            'verified' => false,
            'can_resend' => $canResend,
            'message' => $message
        ]);
    }
}
?>
