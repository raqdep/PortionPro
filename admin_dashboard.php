<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

date_default_timezone_set('Asia/Manila');

if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("SELECT id, username, email, business_name, role, is_verified, google_id, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    $totalUsers = count($users);
    $activeUsers = count(array_filter($users, function($u) { return $u['is_verified']; }));
    $googleUsers = count(array_filter($users, function($u) { return !empty($u['google_id']); }));
    $portionproUsers = $totalUsers - $googleUsers;
    
    $stmt = $db->prepare("
        SELECT al.*, u.username, u.email 
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $activityLogs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $users = [];
    $activityLogs = [];
    $totalUsers = 0;
    $activeUsers = 0;
    $googleUsers = 0;
    $portionproUsers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PortionPro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body.admin-dashboard {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #16a085 100%);
            background-attachment: fixed;
        }

        .admin-navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #16a085 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .admin-badge {
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .logo-image {
            height: 40px;
            width: auto;
            margin-right: 8px;
            vertical-align: middle;
        }

        .logo-text {
            font-weight: bold;
            margin: 0;
        }

        .user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .admin-stat-card {
            background: linear-gradient(135deg, #16a085 0%, #f39c12 100%);
            color: white;
            padding: 16px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(22, 160, 133, 0.3);
        }

        .admin-stat-card.secondary {
            background: linear-gradient(135deg, #3498db 0%, #9b59b6 100%);
        }

        .admin-stat-card h3 {
            font-size: 1.8rem;
            margin: 0 0 4px 0;
            font-weight: 700;
        }

        .admin-stat-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .admin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 2px solid rgba(52, 73, 94, 0.1);
        }

        .admin-tab {
            padding: 8px 16px;
            background: transparent;
            border: none;
            color: #34495e;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .admin-tab.active {
            color: #16a085;
            border-bottom-color: #16a085;
        }

        .admin-tab:hover {
            color: #16a085;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .admin-table th {
            background: #2c3e50;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .admin-table td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(52, 73, 94, 0.1);
        }

        .admin-table tr:hover {
            background: rgba(22, 160, 133, 0.05);
        }

        .badge {
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-admin {
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            color: white;
        }

        .badge-google {
            background: #4285f4;
            color: white;
        }

        .badge-portionpro {
            background: #16a085;
            color: white;
        }

        .activity-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .activity-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .activity-icon.login {
            background: #d4edda;
            color: #155724;
        }

        .activity-icon.logout {
            background: #f8d7da;
            color: #721c24;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .btn-action {
            padding: 4px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 4px;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .btn-verify {
            background: #16a085;
            color: white;
        }

        .btn-verify:hover {
            background: #138d75;
        }

        .empty-state {
            text-align: center;
            padding: 32px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        
        .tab-content h2 {
            font-size: 1.3rem;
            margin-bottom: 12px;
            color: #2c3e50;
        }
        
        .admin-main-content {
            padding: 20px 15px;
        }
        
        .admin-page-header {
            margin-bottom: 20px;
        }
        
        .admin-page-title {
            font-size: 1.8rem;
            margin-bottom: 4px;
            color: #ffffff;
        }
        
        .admin-page-subtitle {
            font-size: 0.9rem;
            color: #ffffff;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 12px;
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Navigation -->
    <nav class="navbar admin-navbar">
        <div class="navbar-content">
            <a href="admin_dashboard.php" class="navbar-brand">
                <img src="logo/PortionPro-fill.png" alt="PortionPro Logo" class="logo-image">
                <span class="logo-text">PortionPro</span>
                <span class="admin-badge">Admin</span>
            </a>
            <div class="navbar-menu">
                <a href="admin_dashboard.php" class="active">
                    <i class="fas fa-shield-alt"></i> Admin Dashboard
                </a>
            </div>
            <div class="user-menu">
                <button class="user-btn" onclick="logout()">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="admin-main-content">
        <div class="admin-page-header">
            <h1 class="admin-page-title">Admin Dashboard</h1>
            <p class="admin-page-subtitle">Manage users and monitor activity</p>
        </div>

        <!-- Statistics -->
        <div class="admin-stats">
            <div class="admin-stat-card">
                <h3><?php echo $totalUsers; ?></h3>
                <p><i class="fas fa-users"></i> Total Users</p>
            </div>
            <div class="admin-stat-card secondary">
                <h3><?php echo $activeUsers; ?></h3>
                <p><i class="fas fa-user-check"></i> Active Users</p>
            </div>
            <div class="admin-stat-card" style="background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);">
                <h3><?php echo $googleUsers; ?></h3>
                <p><i class="fab fa-google"></i> Google Sign-ins</p>
            </div>
            <div class="admin-stat-card" style="background: linear-gradient(135deg, #16a085 0%, #27ae60 100%);">
                <h3><?php echo $portionproUsers; ?></h3>
                <p><i class="fas fa-utensils"></i> PortionPro Sign-ins</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="admin-card">
            <div class="admin-tabs">
                <button class="admin-tab active" onclick="switchTab('users')">
                    <i class="fas fa-users"></i> Users Management
                </button>
                <button class="admin-tab" onclick="switchTab('activity')">
                    <i class="fas fa-history"></i> Activity Logs
                </button>
            </div>

            <!-- Users Tab -->
            <div id="users-tab" class="tab-content active">
                <h2>
                    <i class="fas fa-users"></i> All Users
                </h2>
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No users found</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Business Name</th>
                                    <th>Role</th>
                                    <th>Sign-in Method</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['business_name']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge badge-admin">
                                                    <i class="fas fa-shield-alt"></i> Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-info">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['google_id'])): ?>
                                                <span class="badge badge-google">
                                                    <i class="fab fa-google"></i> Google
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-portionpro">
                                                    <i class="fas fa-utensils"></i> PortionPro
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_verified']): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check-circle"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <?php if (!$user['is_verified']): ?>
                                                    <button class="btn-action btn-verify" onclick="verifyUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-check"></i> Verify
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="badge badge-admin">Protected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity Logs Tab -->
            <div id="activity-tab" class="tab-content">
                <h2>
                    <i class="fas fa-history"></i> Recent Activity
                </h2>
                <?php if (empty($activityLogs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity logs found</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>IP Address</th>
                                    <th>User Agent</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="activity-type">
                                                <div class="activity-icon <?php echo $log['activity_type']; ?>">
                                                    <?php if ($log['activity_type'] === 'login'): ?>
                                                        <i class="fas fa-sign-in-alt"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sign-out-alt"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <span style="text-transform: capitalize; font-weight: 600;">
                                                    <?php echo $log['activity_type']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                                        <td><?php echo htmlspecialchars($log['email']); ?></td>
                                        <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($log['user_agent']); ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.admin-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        function logout() {
            Swal.fire({
                title: 'Logout',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#16a085',
                cancelButtonColor: '#95a5a6'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        async function deleteUser(userId, username) {
            const result = await Swal.fire({
                title: 'Delete User',
                html: `Are you sure you want to delete user <strong>${username}</strong>?<br><br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/admin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_user&user_id=${userId}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'User has been deleted successfully.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to delete user'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while deleting the user'
                    });
                }
            }
        }

        async function verifyUser(userId) {
            try {
                const response = await fetch('api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_user&user_id=${userId}`
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Verified!',
                        text: 'User has been verified successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to verify user'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while verifying the user'
                });
            }
        }
    </script>
</body>
</html>
