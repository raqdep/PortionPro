# Admin User Management System

## Overview
The PortionPro application now includes a comprehensive admin user management system with activity logging functionality.

## Features

### Admin Dashboard
- **User Management**: View, verify, and delete users
- **Activity Logs**: Monitor user login/logout activities
- **Real-time Statistics**: Track total users and active users
- **Modern UI**: Clean interface matching the website's color palette

### Activity Logging
- Automatically logs all user login and logout events
- Tracks IP addresses and user agents
- Displays activity history in an easy-to-read format

## Admin Account (For Administrators Only)

### Login Credentials
- **Email**: `portionpronc@gmail.com`
- **Password**: `PortionPro123!!!`

### Important Notes
⚠️ **SECURITY**: Please keep these credentials secure. The admin account has full access to user management functions.

⚠️ **NOT A DEFAULT LOGIN**: This is a special administrative account. Regular users should:
- Register their own accounts via the registration form
- Sign in with Google OAuth
- NOT use the admin credentials

## Setup Instructions

### 1. Run the Setup Script
The admin user and activity logs table have been created automatically. If you need to run the setup again:

```bash
php setup_admin.php
```

This script will:
- Create the `activity_logs` table
- Create or update the admin user account
- Set the default admin credentials

### 2. Access the Admin Dashboard
1. Navigate to `login.php`
2. Enter the admin credentials
3. You will be automatically redirected to the admin dashboard

## Admin Features

### User Management
- **View All Users**: See complete user list with details
- **Verify Users**: Manually verify user accounts
- **Delete Users**: Remove user accounts (admin accounts are protected)
- **User Status**: View verification status and role

### Activity Monitoring
- **Login Events**: Track when users log in
- **Logout Events**: Track when users log out
- **IP Tracking**: Monitor login locations
- **User Agent**: See what devices/browsers users are using

## Database Schema

### activity_logs Table
```sql
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Files Added/Modified

### New Files
- `admin_dashboard.php` - Admin dashboard interface
- `api/admin.php` - Admin API endpoints
- `setup_admin.php` - Admin setup script
- `database/migration_admin_activity_logs.sql` - Database migration
- `ADMIN_SETUP.md` - This documentation

### Modified Files
- `api/auth.php` - Added activity logging and admin redirect
- `logout.php` - Added logout activity logging
- `callback.php` - Added activity logging for Google OAuth
- `assets/js/auth.js` - Updated to handle admin redirect

## Color Palette
The admin dashboard uses the same color scheme as the main website:
- **Primary**: #16a085 (Teal)
- **Secondary**: #f39c12 (Orange)
- **Dark**: #2c3e50 (Dark Blue)
- **Accent**: #34495e (Gray Blue)

## API Endpoints

### Admin API (`api/admin.php`)

#### Delete User
```
POST /api/admin.php
action=delete_user&user_id={id}
```

#### Verify User
```
POST /api/admin.php
action=verify_user&user_id={id}
```

## Security Features
- Admin-only access control
- Protected admin accounts (cannot be deleted)
- Session-based authentication
- CSRF protection
- SQL injection prevention

## Usage

### Logging In as Admin
1. Go to the login page
2. Enter admin email and password
3. System automatically redirects to admin dashboard

### Managing Users
1. Navigate to "Users Management" tab
2. View all registered users
3. Use action buttons to verify or delete users

### Viewing Activity Logs
1. Navigate to "Activity Logs" tab
2. View recent login/logout activities
3. Monitor user behavior and access patterns

## Troubleshooting

### Admin User Not Created
Run the setup script manually:
```bash
php setup_admin.php
```

### Activity Logs Not Recording
Check if the `activity_logs` table exists:
```sql
SHOW TABLES LIKE 'activity_logs';
```

### Cannot Access Admin Dashboard
Ensure you're logged in with the admin account and the session has the correct role.

## Future Enhancements
- Export activity logs to CSV/Excel
- Advanced filtering and search
- User role management
- Email notifications for admin actions
- Activity log retention policies

## Support
For issues or questions, please contact the development team.
