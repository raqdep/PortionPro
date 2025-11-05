<?php
// Email configuration for PortionPro Gmail notifications
// Gmail SMTP settings for sending login notifications and password resets

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'kivrue067@gmail.com');
define('SMTP_PASSWORD', 'urdk njjt jtlp gsgd');
define('SMTP_FROM_EMAIL', 'kivrue067@gmail.com'); // Replace with your Gmail
define('SMTP_FROM_NAME', 'PortionPro Security');

// Email settings
define('EMAIL_ENABLED', true); // Set to false to disable email sending
define('SEND_LOGIN_NOTIFICATIONS', true); // Send email on every Google OAuth login
define('SEND_PASSWORD_RESET', true); // Send password reset emails

// Security settings
define('PASSWORD_RESET_CODE_LENGTH', 6);
define('PASSWORD_RESET_EXPIRY_MINUTES', 15);

// Base URL for generating absolute links in emails (set to your local or public URL)
// Examples:
//   Local XAMPP:            http://localhost/webtry1
//   Local over LAN (WiFi):  http://192.168.1.10/webtry1
//   Production:             https://yourdomain.com
define('APP_BASE_URL', 'http://localhost/webtry1');


?>
