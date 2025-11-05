<?php
// Email configuration template for PortionPro Gmail notifications
// Copy this file to email.php and update with your actual Gmail credentials

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'kivrue067@gmail.com'); // Replace with your Gmail
define('SMTP_PASSWORD', 'urdk njjt jtlp gsgd'); // Replace with your Gmail App Password
define('SMTP_FROM_EMAIL', 'kivrue067@gmail.com'); // Replace with your Gmail
define('SMTP_FROM_NAME', 'PortionPro Security');

// Email settings
define('EMAIL_ENABLED', true); // Set to false to disable email sending
define('SEND_LOGIN_NOTIFICATIONS', true); // Send email on every Google OAuth login
define('SEND_PASSWORD_RESET', true); // Send password reset emails

// Security settings
define('PASSWORD_RESET_CODE_LENGTH', 6);
define('PASSWORD_RESET_EXPIRY_MINUTES', 15);

/*
SETUP INSTRUCTIONS:

1. Enable 2-Factor Authentication on your Gmail account:
   - Go to Google Account settings
   - Navigate to Security → 2-Step Verification
   - Enable 2-Step Verification if not already enabled

2. Generate App Password:
   - In Google Account settings, go to Security
   - Under "2-Step Verification", click "App passwords"
   - Select "Mail" as the app
   - Select "Other" as the device and enter "PortionPro"
   - Copy the generated 16-character password

3. Update this file:
   - Replace 'your-email@gmail.com' with your actual Gmail address
   - Replace 'your-app-password' with the 16-character app password from step 2
   - Save this file as 'email.php' (remove .example from filename)

4. Test the configuration:
   - Try the forgot password feature
   - Check your Gmail inbox for the verification code
   - Check PHP error logs if emails aren't being sent

IMPORTANT SECURITY NOTES:
- Never use your regular Gmail password
- Always use App Passwords for SMTP authentication
- Keep your email.php file secure and don't commit it to version control
- The App Password should be 16 characters without spaces
*/
?>
