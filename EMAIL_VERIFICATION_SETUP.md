# Email Verification Setup Guide for PortionPro

This guide explains how to set up and use the email verification system in your PortionPro application.

## Overview

The email verification system ensures that users must verify their email addresses before they can log in to their accounts. This prevents fake registrations and ensures all users have valid email addresses.

## Features

✅ **Email Format Validation** - Validates email format on both frontend and backend  
✅ **Verification Token System** - Secure tokens with 24-hour expiration  
✅ **Beautiful Email Templates** - Professional HTML email templates  
✅ **Resend Verification** - Users can request new verification emails  
✅ **Login Protection** - Unverified users cannot log in  
✅ **Database Integration** - Seamless integration with existing user system  

## Setup Instructions

### 1. Database Migration

First, run the database migration to add email verification fields:

```sql
-- Run this SQL script in your MySQL database
SOURCE database/migration_email_verification.sql;
```

Or manually execute the SQL commands in `database/migration_email_verification.sql`.

### 2. Email Configuration

Update your email configuration in `config/email.php`:

```php
// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-actual-email@gmail.com');
define('SMTP_PASSWORD', 'your-16-character-app-password');
define('SMTP_FROM_EMAIL', 'your-actual-email@gmail.com');
define('SMTP_FROM_NAME', 'PortionPro Security');

// Email settings
define('EMAIL_ENABLED', true);
define('SEND_LOGIN_NOTIFICATIONS', true);
define('SEND_PASSWORD_RESET', true);
```

### 3. Gmail App Password Setup

1. Go to your Google Account settings
2. Enable 2-Factor Authentication
3. Generate an App Password for "Mail"
4. Use this 16-character password in your email configuration

## How It Works

### Registration Flow

1. **User Registers** → Account created with `is_verified = FALSE`
2. **Verification Email Sent** → User receives email with verification link
3. **User Clicks Link** → Account verified, `is_verified = TRUE`
4. **User Can Login** → Full access to PortionPro features

### Login Flow

1. **User Attempts Login** → System checks `is_verified` status
2. **If Not Verified** → Shows verification required message
3. **If Verified** → Normal login process

### Email Verification Process

1. **Token Generation** → Secure 64-character token created
2. **Email Sent** → Beautiful HTML email with verification link
3. **Link Clicked** → Token validated and account activated
4. **Token Expired** → User can request new verification email

## Files Added/Modified

### New Files
- `database/migration_email_verification.sql` - Database schema updates
- `api/email_verification.php` - API for resending verification emails
- `verify_email.php` - Email verification page
- `EMAIL_VERIFICATION_SETUP.md` - This setup guide

### Modified Files
- `api/auth.php` - Updated registration and login logic
- `includes/email_functions.php` - Added verification email functions
- `assets/js/auth.js` - Updated frontend to handle verification flow

## API Endpoints

### POST /api/email_verification.php

**Resend Verification Email:**
```javascript
const formData = new FormData();
formData.append('action', 'resend_verification');
formData.append('email', 'user@example.com');

fetch('api/email_verification.php', {
    method: 'POST',
    body: formData
});
```

**Check Verification Status:**
```javascript
const formData = new FormData();
formData.append('action', 'check_verification_status');
formData.append('email', 'user@example.com');

fetch('api/email_verification.php', {
    method: 'POST',
    body: formData
});
```

## Database Schema

The following fields are added to the `users` table:

```sql
ALTER TABLE users 
ADD COLUMN is_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN verification_token VARCHAR(255) UNIQUE,
ADD COLUMN verification_expires_at TIMESTAMP NULL,
ADD COLUMN verified_at TIMESTAMP NULL;
```

## Email Template Features

- **Responsive Design** - Works on all devices
- **Branded Styling** - Matches PortionPro design
- **Clear Instructions** - Step-by-step verification process
- **Security Warnings** - Alerts for suspicious activity
- **Fallback Links** - Text links if buttons don't work

## Security Features

- **Token Expiration** - 24-hour token validity
- **One-Time Use** - Tokens are deleted after verification
- **Secure Generation** - Cryptographically secure random tokens
- **Email Validation** - Both format and domain validation
- **Rate Limiting** - Prevents spam verification emails

## Testing the System

### 1. Test Registration
1. Register a new account
2. Check that verification email is sent
3. Verify the email contains the correct link

### 2. Test Verification
1. Click the verification link in the email
2. Verify the account is activated
3. Test that login now works

### 3. Test Login Protection
1. Try to login with unverified account
2. Verify the verification required message appears
3. Test the resend verification functionality

## Troubleshooting

### Email Not Sending
- Check Gmail App Password configuration
- Verify SMTP settings in `config/email.php`
- Check server logs for error messages

### Verification Link Not Working
- Check that `verify_email.php` is accessible
- Verify database connection
- Check token expiration (24 hours)

### Database Errors
- Run the migration script
- Check database permissions
- Verify table structure

## Customization

### Email Template
Edit `generateEmailVerificationTemplate()` in `includes/email_functions.php` to customize the email design.

### Token Expiration
Change the 24-hour expiration in `api/auth.php`:
```php
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
```

### Verification Page
Customize `verify_email.php` to match your brand colors and messaging.

## Support

If you encounter any issues with the email verification system:

1. Check the server error logs
2. Verify all configuration settings
3. Test with a simple email first
4. Contact support if problems persist

---

**Note:** This system is designed to work with Gmail SMTP. For other email providers, update the SMTP settings in `config/email.php` accordingly.
