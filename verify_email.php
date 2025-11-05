<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/email_functions.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = 'info';
$showLoginButton = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    
    try {
        // Find user with this verification token
        $stmt = $db->prepare("SELECT id, username, email, is_verified, verification_expires_at FROM users WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $message = 'Invalid verification link. Please check your email or request a new verification email.';
            $messageType = 'error';
        } elseif ($user['is_verified']) {
            $message = 'Your email has already been verified! You can now log in to your account.';
            $messageType = 'success';
            $showLoginButton = true;
        } elseif (!isVerificationTokenValid($token, $user['verification_expires_at'])) {
            $message = 'This verification link has expired. Please request a new verification email.';
            $messageType = 'error';
        } else {
            // Verify the user
            $stmt = $db->prepare("UPDATE users SET is_verified = TRUE, verified_at = NOW(), verification_token = NULL, verification_expires_at = NULL WHERE id = ?");
            $result = $stmt->execute([$user['id']]);
            
            if ($result) {
                $message = 'Congratulations! Your email has been successfully verified. You can now log in to your PortionPro account.';
                $messageType = 'success';
                $showLoginButton = true;
            } else {
                $message = 'An error occurred during verification. Please try again or contact support.';
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        $message = 'An error occurred during verification. Please try again later.';
        $messageType = 'error';
        error_log("Email verification error: " . $e->getMessage());
    }
} else {
    $message = 'No verification token provided. Please check your email for the verification link.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - PortionPro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #16a085 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
            pointer-events: none;
        }
        
        .verification-container {
            background: rgba(247, 249, 249, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(44, 62, 80, 0.3), 0 0 0 1px rgba(247, 249, 249, 0.2);
            padding: 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            position: relative;
            animation: slideUp 0.8s ease-out;
            border: 1px solid rgba(247, 249, 249, 0.3);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .verification-icon {
            font-size: 5rem;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
            position: relative;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .success .verification-icon {
            color: #16a085;
            text-shadow: 0 0 20px rgba(22, 160, 133, 0.4);
        }
        
        .error .verification-icon {
            color: #e74c3c;
            text-shadow: 0 0 20px rgba(231, 76, 60, 0.4);
        }
        
        .info .verification-icon {
            color: #f39c12;
            text-shadow: 0 0 20px rgba(243, 156, 18, 0.4);
        }
        
        .verification-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .verification-message {
            font-size: 1.2rem;
            line-height: 1.7;
            margin-bottom: 40px;
            color: #34495e;
            font-weight: 400;
        }
        
        .verification-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 16px 120px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: #16a085;
            color: white;
            box-shadow: 0 10px 25px rgba(22, 160, 133, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(22, 160, 133, 0.4);
        }
        
        .btn-secondary {
            background: #34495e;
            color: white;
            box-shadow: 0 10px 25px rgba(52, 73, 94, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(52, 73, 94, 0.4);
        }
        
        .logo {
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .logo img {
            height: 60px;
            width: auto;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
        }
        
        .logo-text {
            font-size: 2rem;
            font-weight: 800;
            color: #16a085;
        }
        
        .verification-steps {
            background: linear-gradient(135deg, #f7f9f9, #ecf0f1);
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
            border: 1px solid rgba(247, 249, 249, 0.5);
            box-shadow: 0 10px 25px rgba(44, 62, 80, 0.1);
        }
        
        .verification-steps h4 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 15px 0;
            padding: 20px;
            background: white;
            border-radius: 15px;
            border-left: 5px solid #16a085;
            box-shadow: 0 5px 15px rgba(44, 62, 80, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .step::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, #16a085, #f39c12);
            transition: width 0.3s ease;
            opacity: 0.1;
        }
        
        .step:hover::before {
            width: 100%;
        }
        
        .step:hover {
            transform: translateX(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .step-number {
            background: linear-gradient(135deg, #16a085, #f39c12);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 20px;
            font-size: 1.1rem;
            box-shadow: 0 5px 15px rgba(22, 160, 133, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .step.completed {
            border-left-color: #16a085;
        }
        
        .step.completed .step-number {
            background: linear-gradient(135deg, #16a085, #27ae60);
            box-shadow: 0 5px 15px rgba(22, 160, 133, 0.3);
        }
        
        .step-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .step-content strong {
            display: block;
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .step-content small {
            color: #34495e;
            font-size: 0.9rem;
        }
        
        .footer-info {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid rgba(22, 160, 133, 0.2);
            color: #34495e;
            font-size: 0.95rem;
            background: linear-gradient(135deg, #f7f9f9, #ecf0f1);
            border-radius: 15px;
            padding: 25px;
        }
        
        .footer-info p {
            margin: 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(247, 249, 249, 0.15);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-circle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-circle:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .floating-circle:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 80%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        @media (max-width: 768px) {
            .verification-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .verification-title {
                font-size: 1.8rem;
            }
            
            .verification-message {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 14px 30px;
                font-size: 0.95rem;
            }
            
            .verification-actions {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
    </div>
    
    <div class="verification-container <?php echo $messageType; ?>">
        <div class="logo">
            <img src="logo/PortionPro-fill.png" alt="PortionPro Logo">
            <span class="logo-text">PortionPro</span>
        </div>
        
        <div class="verification-icon">
            <?php if ($messageType === 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php elseif ($messageType === 'error'): ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php else: ?>
                <i class="fas fa-info-circle"></i>
            <?php endif; ?>
        </div>
        
        <h1 class="verification-title">
            <?php if ($messageType === 'success'): ?>
                Email Verified Successfully!
            <?php elseif ($messageType === 'error'): ?>
                Verification Failed
            <?php else: ?>
                Email Verification
            <?php endif; ?>
        </h1>
        
        <p class="verification-message"><?php echo htmlspecialchars($message); ?></p>
        
        <?php if ($messageType === 'success'): ?>
        <div class="verification-steps">
            <h4><i class="fas fa-rocket"></i> What's Next?</h4>
            <div class="step completed">
                <div class="step-number">1</div>
                <div class="step-content">
                    <strong>Email Verified</strong>
                    <small>Your email address has been confirmed</small>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <strong>Log In to Your Account</strong>
                    <small>Access your PortionPro dashboard</small>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <strong>Start Managing Your Business</strong>
                    <small>Add ingredients, create recipes, and analyze costs</small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="verification-actions">
            <?php if ($showLoginButton): ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Log In to Your Account
                </a>
            <?php endif; ?>
        </div>
        
        <div class="footer-info">
            <p><i class="fas fa-shield-alt"></i> Your account security is our priority</p>
            <p><i class="fas fa-headset"></i> Need help? Contact our support team for assistance.</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
