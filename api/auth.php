<?php
// Authentication API for PortionPro
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Manila/Philippines
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? 'login';
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    if ($action === 'login') {
        handleLogin($db);
    } elseif ($action === 'register') {
        handleRegister($db);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleLogin($db) {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT id, username, email, password_hash, business_name, role, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && verifyPassword($password, $user['password_hash'])) {
        // Check if email is verified
        if (!$user['is_verified']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.',
                'requires_verification' => true,
                'email' => $user['email']
            ]);
            return;
        }
        
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['business_name'] = $user['business_name'];
        $_SESSION['role'] = $user['role'];
        
        // Log the login activity
        logActivity($db, $user['id'], 'login');
        
        // Redirect admin users to admin dashboard
        $redirectUrl = ($user['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirectUrl,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'business_name' => $user['business_name'],
                'role' => $user['role']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    }
}

function handleRegister($db) {
    require_once '../includes/email_functions.php';
    
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $business_name = sanitizeInput($_POST['business_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($business_name) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        return;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Check if username or email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        return;
    }
    
    // Generate verification token
    $verificationToken = generateVerificationToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Create new user (unverified by default)
    $password_hash = hashPassword($password);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, business_name, role, is_verified, verification_token, verification_expires_at) VALUES (?, ?, ?, ?, 'user', FALSE, ?, ?)");
    
    if ($stmt->execute([$username, $email, $password_hash, $business_name, $verificationToken, $expiresAt])) {
        // Send verification email
        $emailSent = sendEmailVerificationEmail($email, $username, $verificationToken);
        
        if ($emailSent) {
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! Please check your email to verify your account.',
                'requires_verification' => true
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! However, we could not send the verification email. Please contact support.',
                'requires_verification' => true
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}

function logActivity($db, $userId, $activityType) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $activityType, $ipAddress, $userAgent]);
    } catch (PDOException $e) {
        // Silently fail - don't break login/logout if logging fails
        error_log("Activity logging failed: " . $e->getMessage());
    }
}
?>
