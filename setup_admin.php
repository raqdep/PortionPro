<?php
/**
 * Admin Setup Script
 * Creates the default admin user and activity logs table
 * Run this script once to set up the admin account
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

try {
    echo "Setting up admin user and activity logs...\n\n";
    
    // Create activity_logs table
    echo "1. Creating activity_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activity_type ENUM('login', 'logout') NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_activity_type (activity_type),
        INDEX idx_created_at (created_at)
    )";
    $db->exec($sql);
    echo "   ✓ Activity logs table created successfully\n\n";
    
    // Admin credentials
    $adminEmail = 'portionpronc@gmail.com';
    $adminPassword = 'PortionPro123!!!';
    $adminUsername = 'admin';
    $adminBusiness = 'PortionPro Admin';
    
    // Hash the password
    $passwordHash = hashPassword($adminPassword);
    
    // Check if admin user already exists
    echo "2. Checking for existing admin user...\n";
    $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // Update existing user to admin
        echo "   User found. Updating to admin role...\n";
        $stmt = $db->prepare("UPDATE users SET 
            username = ?,
            password_hash = ?,
            business_name = ?,
            role = 'admin',
            is_verified = TRUE
            WHERE email = ?");
        $stmt->execute([$adminUsername, $passwordHash, $adminBusiness, $adminEmail]);
        echo "   ✓ Admin user updated successfully\n\n";
    } else {
        // Insert new admin user
        echo "   Creating new admin user...\n";
        $stmt = $db->prepare("INSERT INTO users 
            (username, email, password_hash, business_name, role, is_verified, created_at)
            VALUES (?, ?, ?, ?, 'admin', TRUE, CURRENT_TIMESTAMP)");
        $stmt->execute([$adminUsername, $adminEmail, $passwordHash, $adminBusiness]);
        echo "   ✓ Admin user created successfully\n\n";
    }
    
    echo "========================================\n";
    echo "ADMIN SETUP COMPLETED SUCCESSFULLY!\n";
    echo "========================================\n\n";
    echo "Admin Login Credentials (For Administrators Only):\n";
    echo "Email: $adminEmail\n";
    echo "Password: $adminPassword\n\n";
    echo "⚠️  IMPORTANT NOTES:\n";
    echo "- This is a special administrative account\n";
    echo "- Regular users should register their own accounts\n";
    echo "- Keep these credentials secure!\n\n";
    echo "You can now login at: login.php\n\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
