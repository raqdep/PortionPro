-- Migration for Admin User Management and Activity Logs
-- This migration adds activity logging functionality and creates the default admin user

USE portionpro;

-- Create activity_logs table to track user login/logout
CREATE TABLE IF NOT EXISTS activity_logs (
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
);

-- Insert default admin user
-- Email: portionpronc@gmail.com
-- Password: PortionPro123!!!
INSERT INTO users (username, email, password_hash, business_name, role, is_verified, created_at)
VALUES (
    'admin',
    'portionpronc@gmail.com',
    '$2y$10$YzJQMGE5ZjE5ZjE5ZjE5ZOxKGZvZGE5ZjE5ZjE5ZjE5ZjE5ZjE5Zj',  -- This will be replaced by actual hash
    'PortionPro Admin',
    'admin',
    TRUE,
    CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    role = 'admin',
    is_verified = TRUE;
