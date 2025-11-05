<?php
// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', '220597499269-0kv6oagckcqe64eftqoji8vjgktklodt.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/webtry1/callback.php');

// Database configuration for PortionPro
class Database {
    private $host = 'mathtry-db.c9aqi8mg6z1y.ap-southeast-2.rds.amazonaws.com';
    private $db_name = 'portionpro';
    private $username = 'admin';
    private $password = 'mathtry123';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Set MySQL timezone to Manila/Philippines
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
?>
