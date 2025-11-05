<?php
// Test Email Configuration for PortionPro
// Use this for testing without Gmail setup

// Test configuration (no real email sending)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_USERNAME', 'test@localhost');
define('SMTP_PASSWORD', 'test');
define('SMTP_FROM_EMAIL', 'test@localhost');
define('SMTP_FROM_NAME', 'PortionPro Test');

// Email settings
define('EMAIL_ENABLED', true); // Disable real email sending for testing
define('SEND_LOGIN_NOTIFICATIONS', true);
define('SEND_PASSWORD_RESET', true);

// Security settings
define('PASSWORD_RESET_CODE_LENGTH', 6);
define('PASSWORD_RESET_EXPIRY_MINUTES', 15);
?>
