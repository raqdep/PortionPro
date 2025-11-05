<?php
// Email functions for PortionPro Gmail notifications

require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Email constants are defined in config/email.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send login notification email to user
 */
function sendLoginNotificationEmail($email, $name, $loginTime, $ipAddress, $userAgent) {
    if (!EMAIL_ENABLED || !SEND_LOGIN_NOTIFICATIONS) {
        return true; // Skip sending if disabled
    }
    
    $subject = "🔐 PortionPro Login Notification - " . date('M j, Y g:i A');
    $message = generateLoginNotificationTemplate($name, $loginTime, $ipAddress, $userAgent);
    
    return sendEmail($email, $name, $subject, $message);
}

/**
 * Generate login notification email HTML template
 */
function generateLoginNotificationTemplate($name, $loginTime, $ipAddress, $userAgent) {
    $currentTime = date('M j, Y \a\t g:i A T');
    $loginLocation = getLocationFromIP($ipAddress);
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>PortionPro Login Notification</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .success-badge { 
                background: #27ae60; 
                color: white; 
                font-size: 18px; 
                font-weight: bold; 
                text-align: center; 
                padding: 15px; 
                margin: 20px 0; 
                border-radius: 8px; 
            }
            .info-box { 
                background: #ecf0f1; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid #3498db;
            }
            .warning-box { 
                background: #f39c12; 
                color: white; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 20px 0; 
                text-align: center;
            }
            .footer { text-align: center; margin-top: 30px; color: #7f8c8d; font-size: 14px; }
            .login-details { background: white; padding: 20px; border-radius: 8px; margin: 15px 0; }
            .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #ecf0f1; }
            .detail-label { font-weight: bold; color: #2c3e50; }
            .detail-value { color: #34495e; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 PortionPro Security</h1>
                <p>Login Notification</p>
            </div>
            <div class='content'>
                <div class='success-badge'>
                    ✅ Successful Google OAuth Login
                </div>
                
                <h2>Hello " . htmlspecialchars($name) . "!</h2>
                <p>We're notifying you that your PortionPro account was accessed using Google OAuth authentication.</p>
                
                <div class='info-box'>
                    <h3>📋 Login Details</h3>
                    <div class='login-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>Login Time:</span>
                            <span class='detail-value'>" . $currentTime . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>IP Address:</span>
                            <span class='detail-value'>" . htmlspecialchars($ipAddress) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Location:</span>
                            <span class='detail-value'>" . htmlspecialchars($loginLocation) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Device/Browser:</span>
                            <span class='detail-value'>" . htmlspecialchars(substr($userAgent, 0, 100)) . "...</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Authentication Method:</span>
                            <span class='detail-value'>Google OAuth</span>
                        </div>
                    </div>
                </div>
                
                <div class='warning-box'>
                    <strong>⚠️ Security Alert</strong><br>
                    If you didn't make this login, please secure your account immediately!
                </div>
                
                <p><strong>What to do if this wasn't you:</strong></p>
                <ul>
                    <li>Change your Google account password immediately</li>
                    <li>Review your Google account security settings</li>
                    <li>Enable 2-factor authentication on your Google account</li>
                    <li>Contact our support team if you have concerns</li>
                </ul>
                
                <p>This notification helps keep your account secure by alerting you to all login activity.</p>
            </div>
            <div class='footer'>
                <p>This is an automated security notification from PortionPro</p>
                <p>© " . date('Y') . " PortionPro - Food Costing Calculator</p>
                <p><small>To disable these notifications, contact support.</small></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Get approximate location from IP address
 */
function getLocationFromIP($ip) {
    // For localhost/development
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0) {
        return 'Local Development Environment';
    }
    
    // For production, you could use a geolocation service
    // For now, return the IP address
    return 'IP: ' . $ip;
}

/**
 * Send password reset verification code email
 */
function sendPasswordResetEmail($email, $name, $code) {
    if (!EMAIL_ENABLED || !SEND_PASSWORD_RESET) {
        return true; // Skip sending if disabled
    }
    
    $subject = "🔐 PortionPro Password Reset - Verification Code";
    $message = generatePasswordResetTemplate($name, $code);
    
    return sendEmailWithPHPMailer($email, $name, $subject, $message);
}

/**
 * Generate password reset email HTML template
 */
function generatePasswordResetTemplate($name, $code) {
    $expiryTime = PASSWORD_RESET_EXPIRY_MINUTES;
    $currentTime = date('M j, Y \a\t g:i A T');
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>PortionPro Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; padding: 0; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; }
            .content { padding: 40px 30px; }
            .code-container { background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 30px; text-align: center; margin: 30px 0; }
            .verification-code { font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 8px; margin: 20px 0; font-family: 'Courier New', monospace; }
            .code-label { color: #6c757d; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
            .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 25px 0; }
            .warning-box h3 { color: #856404; margin: 0 0 10px 0; font-size: 16px; }
            .warning-box p { color: #856404; margin: 0; font-size: 14px; }
            .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 25px 0; border-radius: 0 8px 8px 0; }
            .info-box h3 { color: #1976d2; margin: 0 0 10px 0; }
            .info-box p { color: #1976d2; margin: 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 12px; border-top: 1px solid #dee2e6; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; margin: 20px 0; }
            .security-note { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 20px 0; }
            .security-note p { color: #721c24; margin: 0; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 PortionPro</h1>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . "!</h2>
                <p>We received a request to reset your PortionPro account password. Use the verification code below to complete the process.</p>
                
                <div class='code-container'>
                    <div class='code-label'>Your Verification Code</div>
                    <div class='verification-code'>" . $code . "</div>
                    <p style='color: #6c757d; margin: 0; font-size: 14px;'>Enter this code in the password reset form</p>
                </div>
                
                <div class='warning-box'>
                    <h3>⏰ Important</h3>
                    <p>This code will expire in <strong>" . $expiryTime . " minutes</strong> for security reasons.</p>
                </div>
                
                <div class='info-box'>
                    <h3>📋 Request Details</h3>
                    <p><strong>Time:</strong> " . $currentTime . "<br>
                    <strong>Requested from:</strong> Password Reset Form</p>
                </div>
                
                <div class='security-note'>
                    <p><strong>🔒 Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your account remains secure and no changes have been made.</p>
                </div>
                
                <p>If you're having trouble with the verification code, you can request a new one from the login page.</p>
            </div>
            <div class='footer'>
                <p><strong>PortionPro - Food Costing Calculator</strong></p>
                <p>This is an automated security email. Please do not reply to this message.</p>
                <p>© " . date('Y') . " PortionPro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send email using PHPMailer with Gmail SMTP
 */
function sendEmailWithPHPMailer($to, $toName, $subject, $message) {
    if (!EMAIL_ENABLED) {
        return true;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send email using PHP's mail function (fallback)
 */
function sendEmail($to, $toName, $subject, $message) {
    if (!EMAIL_ENABLED) {
        return true;
    }
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'Reply-To: ' . SMTP_FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $headersString = implode("\r\n", $headers);
    
    return mail($to, $subject, $message, $headersString);
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

/**
 * Get user agent string
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Send email verification email
 */
function sendEmailVerificationEmail($email, $name, $verificationToken) {
    if (!EMAIL_ENABLED) {
        return true; // Skip sending if disabled
    }
    
    $subject = "✅ Verify Your PortionPro Account - Complete Registration";
    $message = generateEmailVerificationTemplate($name, $verificationToken);
    
    return sendEmailWithPHPMailer($email, $name, $subject, $message);
}

/**
 * Generate email verification HTML template
 */
function generateEmailVerificationTemplate($name, $verificationToken) {
    $verificationLink = getBaseUrl() . "/verify_email.php?token=" . $verificationToken;
    $currentTime = date('M j, Y \a\t g:i A T');
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verify Your PortionPro Account</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; padding: 0; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #16a085 0%, #27ae60 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; }
            .content { padding: 40px 30px; }
            .verify-button { 
                display: inline-block; 
                background: #16a085; 
                color: white; 
                padding: 15px 40px; 
                text-decoration: none; 
                border-radius: 25px; 
                font-weight: bold; 
                margin: 20px 0; 
                font-size: 16px;
                text-align: center;
            }
            .verify-button:hover { background: #138d75; }
            .info-box { 
                background: #e8f5e8; 
                border-left: 4px solid #27ae60; 
                padding: 20px; 
                margin: 25px 0; 
                border-radius: 0 8px 8px 0; 
            }
            .info-box h3 { color: #27ae60; margin: 0 0 10px 0; }
            .info-box p { color: #27ae60; margin: 0; }
            .warning-box { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                border-radius: 8px; 
                padding: 20px; 
                margin: 25px 0; 
            }
            .warning-box h3 { color: #856404; margin: 0 0 10px 0; font-size: 16px; }
            .warning-box p { color: #856404; margin: 0; font-size: 14px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 12px; border-top: 1px solid #dee2e6; }
            .security-note { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 20px 0; }
            .security-note p { color: #721c24; margin: 0; font-size: 14px; }
            .steps { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .step { margin: 15px 0; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
            .step:last-child { border-bottom: none; }
            .step-number { 
                background: #16a085; 
                color: white; 
                width: 25px; 
                height: 25px; 
                border-radius: 50%; 
                display: inline-flex; 
                align-items: center; 
                justify-content: center; 
                font-weight: bold; 
                margin-right: 10px; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🍽️ PortionPro</h1>
                <p>Complete Your Registration</p>
            </div>
            <div class='content'>
                <h2>Welcome " . htmlspecialchars($name) . "!</h2>
                <p>Thank you for registering with PortionPro! To complete your registration and start using our food costing calculator, please verify your email address.</p>
                
                <div class='info-box'>
                    <h3>📧 Email Verification Required</h3>
                    <p>Click the button below to verify your email address and activate your account.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $verificationLink . "' class='verify-button'>✅ Verify My Email Address</a>
                </div>
                
                <div class='steps'>
                    <h3>🚀 What happens next?</h3>
                    <div class='step'>
                        <span class='step-number'>1</span>
                        <strong>Verify Email:</strong> Click the verification button above
                    </div>
                    <div class='step'>
                        <span class='step-number'>2</span>
                        <strong>Account Activated:</strong> Your account will be fully activated
                    </div>
                    <div class='step'>
                        <span class='step-number'>3</span>
                        <strong>Start Using:</strong> Begin managing your ingredients and recipes
                    </div>
                </div>
                
                <div class='warning-box'>
                    <h3>⏰ Important</h3>
                    <p>This verification link will expire in <strong>24 hours</strong> for security reasons. If you need a new verification email, please contact support.</p>
                </div>
                
                <div class='security-note'>
                    <p><strong>🔒 Security Notice:</strong> If you didn't create this account, please ignore this email. No further action is required.</p>
                </div>
                
                <p><strong>Having trouble with the button?</strong><br>
                Copy and paste this link into your browser:<br>
                <a href='" . $verificationLink . "' style='color: #16a085; word-break: break-all;'>" . $verificationLink . "</a></p>
            </div>
            <div class='footer'>
                <p><strong>PortionPro - Food Costing Calculator</strong></p>
                <p>This is an automated verification email. Please do not reply to this message.</p>
                <p>© " . date('Y') . " PortionPro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Get base URL for the application
 */
function getBaseUrl() {
    // Prefer explicit config if provided
    if (defined('APP_BASE_URL') && APP_BASE_URL) {
        return rtrim(APP_BASE_URL, '/');
    }
    // Fallback to dynamic detection
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/webtry1');
    return rtrim($protocol . '://' . $host . $script, '/');
}

/**
 * Generate a secure verification token
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Check if verification token is valid and not expired
 */
function isVerificationTokenValid($token, $expiresAt) {
    if (empty($token) || empty($expiresAt)) {
        return false;
    }
    
    $now = new DateTime();
    $expiry = new DateTime($expiresAt);
    
    return $now < $expiry;
}
?>
