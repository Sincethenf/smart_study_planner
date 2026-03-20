<?php
// student/rankings.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Update session with latest data
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

// Get leaderboard data
$leaderboard = $conn->query("
    SELECT u.id, u.full_name, u.username, u.profile_picture, u.points,
           r.lessons_completed, r.generated_count,
           RANK() OVER (ORDER BY u.points DESC) as rank_position
    FROM users u
    LEFT JOIN rankings r ON u.id = r.user_id
    WHERE u.role = 'student'
    ORDER BY u.points DESC
    LIMIT 50
");

// Get user's rank
$user_rank = $conn->query("
    SELECT COUNT(*) + 1 as rank
    FROM users
    WHERE role = 'student' AND points > (SELECT points FROM users WHERE id = $user_id)
")->fetch_assoc()['rank'];

// Get top 3 for podium
$top3 = $conn->query("
    SELECT full_name, points, profile_picture
    FROM users
    WHERE role = 'student'
    ORDER BY points DESC
    LIMIT 3
");

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$total_points_all = $conn->query("SELECT SUM(points) as total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
$avg_points = $conn->query("SELECT AVG(points) as avg FROM users WHERE role = 'student'")->fetch_assoc()['avg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rankings - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .rankings-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .rankings-header h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .rankings-header p {
            color: #666;
            font-size: 16px;
        }
        
        /* Podium Styles */
        .podium-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 50px;
            padding: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .podium-item {
            text-align: center;
            position: relative;
        }
        
        .podium-1 { order: 2; }
        .podium-2 { order: 1; }
        .podium-3 { order: 3; }
        
        .podium-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid;
            margin-bottom: 10px;
        }
        
        .podium-1 .podium-avatar {
            width: 120px;
            height: 120px;
            border-color: gold;
        }
        
        .podium-2 .podium-avatar {
            border-color: silver;
        }
        
        .podium-3 .podium-avatar {
            border-color: #cd7f32;
        }
        
        .podium-rank {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .podium-1 .podium-rank {
            background: gold;
            color: #333;
        }
        
        .podium-2 .podium-rank {
            background: silver;
            color: #333;
        }
        
        .podium-3 .podium-rank {
            background: #cd7f32;
        }
        
        .podium-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .podium-points {
            color: #7b2cff;
            font-weight: 600;
        }
        
        .podium-1 .podium-points {
            font-size: 18px;
        }
        
        /* Stats Cards */
        .rankings-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .stat-card i {
            font-size: 32px;
            color: #7b2cff;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        /* User Rank Card */
        .user-rank-card {
            background: linear-gradient(135deg, #7b2cff, #9b4dff);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .user-rank-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-rank-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid white;
            object-fit: cover;
        }
        
        .user-rank-details h4 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .user-rank-details p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .user-rank-badge {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 40px;
            text-align: center;
        }
        
        .user-rank-badge .rank-number {
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
        }
        
        .user-rank-badge .rank-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        /* Leaderboard Table */
        .leaderboard-table {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-header h3 {
            color: #333;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px;
            color: #666;
            font-weight: 500;
            font-size: 14px;
            border-bottom: 2px solid #f1e8ff;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f1e8ff;
        }
        
        .student-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .student-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #f1e8ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7b2cff;
            font-weight: 600;
        }
        
        .student-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .rank-cell {
            font-weight: 600;
        }
        
        .rank-1 { color: gold; }
        .rank-2 { color: silver; }
        .rank-3 { color: #cd7f32; }
        
        .points-cell {
            font-weight: 600;
            color: #7b2cff;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-gold {
            background: gold;
            color: #333;
        }
        
        .badge-silver {
            background: silver;
            color: #333;
        }
        
        .badge-bronze {
            background: #cd7f32;
            color: white;
        }
        
        .current-user-row {
            background: #f1e8ff;
        }
        
        .current-user-row td:first-child {
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        
        .current-user-row td:last-child {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <h2><?php echo SITE_NAME; ?></h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="generate.php"><i class="fas fa-magic"></i> <span>Generate</span></a></li>
                <li><a href="lessons.php"><i class="fas fa-book"></i> <span>Lessons</span></a></li>
                <li><a href="favorites.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
                <li class="active"><a href="rankings.php"><i class="fas fa-trophy"></i> <span>Rankings</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="nav-menu">
                    <a href="generate.php" class="nav-item">Generate</a>
                    <a href="lessons.php" class="nav-item">Lessons</a>
                    <a href="favorites.php" class="nav-item">Favorites</a>
                    <a href="rankings.php" class="nav-item active">Rankings</a>
                    <a href="profile.php" class="nav-item">Profile</a>
                </div>
                
                <div class="user-profile" onclick="window.location.href='profile.php'" style="cursor: pointer;">
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="user-avatar-wrapper">
                        <img src="../assets/uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="Profile" class="user-avatar">
                    </div>
                </div>
            </div>
            
            <!-- Rankings Header -->
            <div class="rankings-header">
                <h2><i class="fas fa-trophy" style="color: gold;"></i> Leaderboard Rankings</h2>
                <p>Top performing students this month</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="rankings-stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <h3><?php echo number_format($total_points_all); ?></h3>
                    <p>Total Points Earned</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <h3><?php echo round($avg_points); ?></h3>
                    <p>Average Points</p>
                </div>
            </div>
            
            <!-- Podium -->
            <?php if ($top3->num_rows > 0): ?>
                <div class="podium-container">
                    <?php 
                    $podium = [];
                    while ($row = $top3->fetch_assoc()) {
                        $podium[] = $row;
                    }
                    
                    // Display 2nd place
                    if (isset($podium[1])): ?>
                        <div class="podium-item podium-2">
                            <div class="podium-rank">2</div>
                            <img src="../assets/uploads/profiles/<?php echo $podium[1]['profile_picture']; ?>" alt="" class="podium-avatar">
                            <div class="podium-name"><?php echo htmlspecialchars($podium[1]['full_name']); ?></div>
                            <div class="podium-points"><?php echo $podium[1]['points']; ?> pts</div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Display 1st place -->
                    <?php if (isset($podium[0])): ?>
                        <div class="podium-item podium-1">
                            <div class="podium-rank">1</div>
                            <img src="../assets/uploads/profiles/<?php echo $podium[0]['profile_picture']; ?>" alt="" class="podium-avatar">
                            <div class="podium-name"><?php echo htmlspecialchars($podium[0]['full_name']); ?></div>
                            <div class="podium-points"><?php echo $podium[0]['points']; ?> pts</div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Display 3rd place -->
                    <?php if (isset($podium[2])): ?>
                        <div class="podium-item podium-3">
                            <div class="podium-rank">3</div>
                            <img src="../assets/uploads/profiles/<?php echo $podium[2]['profile_picture']; ?>" alt="" class="podium-avatar">
                            <div class="podium-name"><?php echo htmlspecialchars($podium[2]['full_name']); ?></div>
                            <div class="podium-points"><?php echo $podium[2]['points']; ?> pts</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- User Rank Card -->
            <div class="user-rank-card">
                <div class="user-rank-info">
                    <img src="../assets/uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="" class="user-rank-avatar">
                    <div class="user-rank-details">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>
                <div class="user-rank-badge">
                    <div class="rank-number">#<?php echo $user_rank; ?></div>
                    <div class="rank-label">Your Rank</div>
                </div>
                <div class="user-rank-badge">
                    <div class="rank-number"><?php echo $user['points']; ?></div>
                    <div class="rank-label">Your Points</div>
                </div>
            </div>
            
            <!-- Leaderboard Table -->
            <div class="leaderboard-table">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Full Leaderboard</h3>
                    <select class="filter-btn" onchange="window.location.href='?filter='+this.value">
                        <option value="all">All Time</option>
                        <option value="month">This Month</option>
                        <option value="week">This Week</option>
                    </select>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Username</th>
                                <th>Points</th>
                                <th>Lessons</th>
                                <th>Generated</th>
                                <th>Badge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($student = $leaderboard->fetch_assoc()): 
                                $is_current_user = ($student['id'] == $user_id);
                            ?>
                            <tr class="<?php echo $is_current_user ? 'current-user-row' : ''; ?>">
                                <td>
                                    <span class="rank-cell rank-<?php echo $student['rank_position']; ?>">
                                        #<?php echo $student['rank_position']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="student-cell">
                                        <div class="student-avatar">
                                            <?php if ($student['profile_picture'] != 'default-avatar.png'): ?>
                                                <img src="../assets/uploads/profiles/<?php echo $student['profile_picture']; ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                        <?php if ($is_current_user): ?>
                                            <span style="color: #7b2cff; font-size: 12px;">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>@<?php echo htmlspecialchars($student['username']); ?></td>
                                <td class="points-cell"><?php echo number_format($student['points']); ?></td>
                                <td><?php echo $student['lessons_completed'] ?? 0; ?></td>
                                <td><?php echo $student['generated_count'] ?? 0; ?></td>
                                <td>
                                    <?php if ($student['rank_position'] == 1): ?>
                                        <span class="badge badge-gold">🥇 Champion</span>
                                    <?php elseif ($student['rank_position'] == 2): ?>
                                        <span class="badge badge-silver">🥈 Silver</span>
                                    <?php elseif ($student['rank_position'] == 3): ?>
                                        <span class="badge badge-bronze">🥉 Bronze</span>
                                    <?php elseif ($student['points'] > 500): ?>
                                        <span class="badge" style="background: #7b2cff; color: white;">💎 Elite</span>
                                    <?php elseif ($student['points'] > 200): ?>
                                        <span class="badge" style="background: #f1e8ff; color: #7b2cff;">⭐ Rising Star</span>
                                    <?php else: ?>
                                        <span class="badge">🌱 Beginner</span>
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