<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'config/database.php';
require_once 'includes/email_functions.php';
session_start();

date_default_timezone_set('Asia/Manila');

if (isset($_GET['code'])) {
    try {
        $code = $_GET['code'];
        $client_id = GOOGLE_CLIENT_ID;
        $client_secret = GOOGLE_CLIENT_SECRET;
        $redirect_uri = GOOGLE_REDIRECT_URI;
        
        $token_url = 'https://oauth2.googleapis.com/token';
        $post_data = [
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Token exchange failed with HTTP code: ' . $http_code);
        }
        
        $token_data = json_decode($response, true);
        
        if (isset($token_data['error'])) {
            throw new Exception('Error fetching access token: ' . $token_data['error']);
        }
        
        $access_token = $token_data['access_token'];
        
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $access_token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $user_response = curl_exec($ch);
        $user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($user_http_code !== 200) {
            throw new Exception('User info fetch failed with HTTP code: ' . $user_http_code);
        }
        
        $user_data = json_decode($user_response, true);
        
        if (isset($user_data['error'])) {
            throw new Exception('Error fetching user info: ' . $user_data['error']);
        }
        
        $google_id = $user_data['id'];
        $name = $user_data['name'];
        $email = $user_data['email'];
        $picture = $user_data['picture'] ?? '';
        
        $db = new Database();
        $conn = $db->getConnection();
        
        if ($conn) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ?");
            $stmt->execute([$google_id]);
            $existing_user = $stmt->fetch();
            
            if (!$existing_user) {
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $email_user = $stmt->fetch();
                
                if ($email_user) {
                    $stmt = $conn->prepare("UPDATE users SET google_id = ?, picture = ?, is_verified = TRUE WHERE email = ?");
                    $stmt->execute([$google_id, $picture, $email]);
                    $user_id = $email_user['id'];
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (google_id, username, email, picture, role, is_verified) VALUES (?, ?, ?, ?, 'user', TRUE)");
                    $stmt->execute([$google_id, $name, $email, $picture]);
                    $user_id = $conn->lastInsertId();
                }
            } else {
                $user_id = $existing_user['id'];
            }
            
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userRole = $stmt->fetchColumn();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $userRole;
            $_SESSION['user'] = [
                'id' => $user_id,
                'name' => $name,
                'email' => $email,
                'picture' => $picture,
                'google_id' => $google_id,
                'role' => $userRole
            ];
            
            // Log login activity
            try {
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                $stmt->execute([$user_id, $ipAddress, $userAgent]);
            } catch (Exception $e) {
                error_log("Activity logging failed: " . $e->getMessage());
            }
            
            // Send login notification email
            $loginTime = date('Y-m-d H:i:s');
            $ipAddress = getClientIP();
            $userAgent = getUserAgent();
            
            $emailSent = sendLoginNotificationEmail($email, $name, $loginTime, $ipAddress, $userAgent);
            
            // Log the email sending (for debugging)
            if ($emailSent) {
                error_log("Login notification sent to $email for user $user_id");
            } else {
                error_log("Failed to send login notification to $email for user $user_id");
            }
            
            // Redirect based on role
            $redirectUrl = ($userRole === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
            header('Location: ' . $redirectUrl);
            exit();
            
        } else {
            throw new Exception('Database connection failed');
        }
        
    } catch (Exception $e) {
        error_log('OAuth callback error: ' . $e->getMessage());
        $_SESSION['error'] = 'Authentication failed. Please try again.';
        header('Location: login.php');
        exit();
    }
} else {
    // No authorization code received
    $_SESSION['error'] = 'Authorization failed. Please try again.';
    header('Location: login.php');
    exit();
}
?>
