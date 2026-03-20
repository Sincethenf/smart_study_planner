<?php
// admin/dashboard.php
require_once '../config/database.php';
requireAdmin();

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
$total_lessons = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
$active_today = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_activity WHERE activity_date = CURDATE()")->fetch_assoc()['count'];

// Get recent users
$recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <h2><?php echo SITE_NAME; ?> Admin</h2>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
                <li><a href="lessons.php"><i class="fas fa-book"></i> <span>Lessons</span></a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-line"></i> <span>Analytics</span></a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <h3>Admin Dashboard</h3>
                <div class="user-profile">
                    <div class="user-info">
                        <h4><?php echo $_SESSION['full_name']; ?></h4>
                        <p>Administrator</p>
                    </div>
                    <img src="../assets/uploads/profiles/default-avatar.png" alt="Profile">
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $total_students; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Teachers</h3>
                        <div class="stat-number"><?php echo $total_teachers; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Lessons</h3>
                        <div class="stat-number"><?php echo $total_lessons; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Active Today</h3>
                        <div class="stat-number"><?php echo $active_today; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Users</h3>
                    <a href="users.php" class="btn btn-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if($user['is_active']): ?>
                                        <span style="color: green;">Active</span>
                                    <?php else: ?>
                                        <span style="color: red;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>