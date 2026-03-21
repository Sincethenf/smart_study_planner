<?php

require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$_SESSION['full_name'] = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

$streak = $user['login_streak'];

function getUserAnalytics($conn, $user_id, $days)
{
   $data = [];
   $labels = [];

   for ($i = $days - 1; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $labels[] = date('D, M d', strtotime($date));

      $stmt = $conn->prepare("SELECT COALESCE(SUM(count), 0) as total FROM user_activity WHERE user_id = ? AND activity_date = ?");
      $stmt->bind_param("is", $user_id, $date);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();

      $data[] = (int)$row['total'];
   }

   return ['labels' => $labels, 'data' => $data];
}

// Get data for different time ranges
$analytics_7d = getUserAnalytics($conn, $user_id, 7);
$analytics_14d = getUserAnalytics($conn, $user_id, 14);
$analytics_30d = getUserAnalytics($conn, $user_id, 30);

// Get activity breakdown
$breakdown_query = $conn->prepare("
    SELECT 
        activity_type,
        SUM(count) as total
    FROM user_activity 
    WHERE user_id = ? 
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY activity_type
");
$breakdown_query->bind_param("i", $user_id);
$breakdown_query->execute();
$breakdown = $breakdown_query->get_result();

$activity_types = [];
$activity_counts = [];
$activity_colors = ['#7b2cff', '#ff8c3a', '#00C851', '#ffbb33'];

while ($row = $breakdown->fetch_assoc()) {
   $activity_types[] = ucfirst($row['activity_type']);
   $activity_counts[] = $row['total'];
}

// If no activities, add default data
if (empty($activity_types)) {
   $activity_types = ['Login', 'Lesson', 'Generate', 'Favorite'];
   $activity_counts = [0, 0, 0, 0];
}

// Get comparison with previous period
$current_period = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM user_activity 
    WHERE user_id = ? 
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$current_period->bind_param("i", $user_id);
$current_period->execute();
$current = $current_period->get_result()->fetch_assoc()['total'];

$previous_period = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM user_activity 
    WHERE user_id = ? 
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        AND activity_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$previous_period->bind_param("i", $user_id);
$previous_period->execute();
$previous = $previous_period->get_result()->fetch_assoc()['total'];

// Calculate trend
if ($previous > 0) {
   $trend = round((($current - $previous) / $previous) * 100, 1);
} else {
   $trend = 100;
}

// Get total lessons
$lessons_total = $conn->prepare("SELECT COUNT(*) as count FROM user_activity WHERE user_id = ? AND activity_type = 'lesson'");
$lessons_total->bind_param("i", $user_id);
$lessons_total->execute();
$lessons_count = $lessons_total->get_result()->fetch_assoc()['count'];

// Get total generated content
$generated_total = $conn->prepare("SELECT COUNT(*) as count FROM user_activity WHERE user_id = ? AND activity_type = 'generate'");
$generated_total->bind_param("i", $user_id);
$generated_total->execute();
$generated_count = $generated_total->get_result()->fetch_assoc()['count'];

// Get student list
$students = $conn->query("
    SELECT u.id, u.full_name, u.student_id, u.profile_picture, 
           r.total_points,
           RANK() OVER (ORDER BY r.total_points DESC) as rank_position
    FROM users u
    JOIN rankings r ON u.id = r.user_id
    WHERE u.role = 'student'
    ORDER BY r.total_points DESC
    LIMIT 10
");

// Get recent activity
$recent_activity = $conn->prepare("
    SELECT activity_type, activity_date, count
    FROM user_activity 
    WHERE user_id = ? 
    ORDER BY activity_date DESC, created_at DESC
    LIMIT 5
");
$recent_activity->bind_param("i", $user_id);
$recent_activity->execute();
$recent = $recent_activity->get_result();

// Get daily activity for the last 7 days (for the table)
$daily_activity = $conn->prepare("
    SELECT activity_date, 
           SUM(CASE WHEN activity_type = 'login' THEN count ELSE 0 END) as logins,
           SUM(CASE WHEN activity_type = 'lesson' THEN count ELSE 0 END) as lessons,
           SUM(CASE WHEN activity_type = 'generate' THEN count ELSE 0 END) as generates,
           SUM(CASE WHEN activity_type = 'favorite' THEN count ELSE 0 END) as favorites,
           SUM(count) as total
    FROM user_activity 
    WHERE user_id = ? 
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY activity_date
    ORDER BY activity_date DESC
");
$daily_activity->bind_param("i", $user_id);
$daily_activity->execute();
$daily = $daily_activity->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="../assets/css/style.css">
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   <style>
      /* Additional analytics styles */
      .analytics-grid {
         display: grid;
         grid-template-columns: repeat(4, 1fr);
         gap: 20px;
         margin-bottom: 30px;
      }

      .analytics-card {
         background: var(--white);
         border-radius: 15px;
         padding: 20px;
         box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
         transition: transform 0.3s ease;
      }

      .analytics-card:hover {
         transform: translateY(-5px);
      }

      .analytics-icon {
         width: 50px;
         height: 50px;
         background: var(--light-purple);
         border-radius: 12px;
         display: flex;
         align-items: center;
         justify-content: center;
         margin-bottom: 15px;
      }

      .analytics-icon i {
         font-size: 24px;
         color: var(--primary-purple);
      }

      .analytics-value {
         font-size: 28px;
         font-weight: 700;
         color: var(--dark);
         margin-bottom: 5px;
      }

      .analytics-label {
         color: var(--gray);
         font-size: 14px;
         margin-bottom: 10px;
      }

      .trend-indicator {
         display: inline-flex;
         align-items: center;
         padding: 5px 10px;
         border-radius: 20px;
         font-size: 12px;
         font-weight: 500;
      }

      .trend-up {
         background: #e6ffe9;
         color: #00C851;
      }

      .trend-down {
         background: #ffe6e6;
         color: #ff4444;
      }

      .chart-row {
         display: grid;
         grid-template-columns: 2fr 1fr;
         gap: 20px;
         margin-bottom: 30px;
      }

      .chart-container {
         background: var(--white);
         border-radius: 20px;
         padding: 25px;
         box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      }

      .chart-header {
         display: flex;
         justify-content: space-between;
         align-items: center;
         margin-bottom: 20px;
      }

      .chart-header h3 {
         color: var(--dark);
         font-size: 18px;
      }

      .time-range-selector {
         display: flex;
         gap: 10px;
      }

      .time-btn {
         padding: 6px 12px;
         border: 2px solid var(--light-purple);
         border-radius: 8px;
         background: var(--white);
         color: var(--gray);
         font-size: 13px;
         cursor: pointer;
         transition: all 0.3s ease;
      }

      .time-btn.active {
         background: var(--primary-purple);
         color: var(--white);
         border-color: var(--primary-purple);
      }

      .time-btn:hover {
         background: var(--light-purple);
         color: var(--primary-purple);
      }

      .chart-wrapper {
         height: 300px;
         position: relative;
      }

      .stats-mini-grid {
         display: grid;
         grid-template-columns: repeat(4, 1fr);
         gap: 15px;
         margin-top: 20px;
         padding-top: 20px;
         border-top: 1px solid var(--light-purple);
      }

      .stat-mini-card {
         background: var(--light-purple);
         border-radius: 12px;
         padding: 15px;
         text-align: center;
      }

      .stat-mini-value {
         font-size: 20px;
         font-weight: 600;
         color: var(--primary-purple);
         margin-bottom: 5px;
      }

      .stat-mini-label {
         font-size: 12px;
         color: var(--gray);
      }

      .recent-activity {
         background: var(--white);
         border-radius: 15px;
         padding: 20px;
         margin-bottom: 30px;
      }

      .activity-item {
         display: flex;
         align-items: center;
         padding: 15px 0;
         border-bottom: 1px solid var(--light-purple);
      }

      .activity-item:last-child {
         border-bottom: none;
      }

      .activity-icon {
         width: 40px;
         height: 40px;
         background: var(--light-purple);
         border-radius: 10px;
         display: flex;
         align-items: center;
         justify-content: center;
         margin-right: 15px;
      }

      .activity-icon i {
         font-size: 18px;
         color: var(--primary-purple);
      }

      .activity-details {
         flex: 1;
      }

      .activity-title {
         font-weight: 600;
         color: var(--dark);
         margin-bottom: 3px;
      }

      .activity-meta {
         font-size: 12px;
         color: var(--gray);
      }

      .activity-count {
         font-weight: 600;
         color: var(--primary-purple);
      }

      .badge {
         padding: 4px 8px;
         border-radius: 12px;
         font-size: 11px;
         font-weight: 500;
         background: var(--light-purple);
         color: var(--primary-purple);
      }

      .daily-table {
         width: 100%;
         border-collapse: collapse;
         margin-top: 15px;
      }

      .daily-table th {
         background: var(--light-purple);
         padding: 12px;
         font-size: 13px;
         font-weight: 600;
         color: var(--dark);
         text-align: left;
      }

      .daily-table td {
         padding: 10px 12px;
         border-bottom: 1px solid var(--light-purple);
         font-size: 13px;
      }

      .daily-table tr:hover {
         background: #fafafa;
      }

      .activity-badge {
         display: inline-block;
         padding: 3px 8px;
         border-radius: 12px;
         font-size: 11px;
         font-weight: 500;
      }

      .badge-login {
         background: #e3f2fd;
         color: #1976d2;
      }

      .badge-lesson {
         background: #f3e5f5;
         color: #7b1fa2;
      }

      .badge-generate {
         background: #e8f5e9;
         color: #388e3c;
      }

      .badge-favorite {
         background: #fff3e0;
         color: #f57c00;
      }

      .section-title {
         display: flex;
         align-items: center;
         gap: 10px;
         margin-bottom: 20px;
      }

      .section-title i {
         font-size: 24px;
         color: var(--primary-purple);
      }

      .section-title h2 {
         color: var(--dark);
         font-size: 22px;
      }

      .fade-in {
         animation: fadeIn 0.5s ease-in-out;
      }

      @keyframes fadeIn {
         from {
            opacity: 0;
            transform: translateY(20px);
         }

         to {
            opacity: 1;
            transform: translateY(0);
         }
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
            <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="generate.php"><i class="fas fa-magic"></i> <span>Generate</span></a></li>
            <li><a href="lessons.php"><i class="fas fa-book"></i> <span>Lessons</span></a></li>
            <li><a href="favorites.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
            <li><a href="rankings.php"><i class="fas fa-trophy"></i> <span>Rankings</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
         </ul>
      </div>

      <div class="main-content">
         <div class="top-nav">
            <div class="nav-menu">
               <a href="generate.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'generate.php' ? 'active' : ''; ?>">
                  <i class="fas fa-magic"></i> Generate
               </a>
               <a href="lessons.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lessons.php' ? 'active' : ''; ?>">
                  <i class="fas fa-book"></i> Lessons
               </a>
               <a href="favorites.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'favorites.php' ? 'active' : ''; ?>">
                  <i class="fas fa-heart"></i> Favorites
               </a>
               <a href="rankings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rankings.php' ? 'active' : ''; ?>">
                  <i class="fas fa-trophy"></i> Rankings
               </a>
               <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                  <i class="fas fa-user"></i> Profile
               </a>
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

         <!-- Streak Card -->
         <div class="streak-card fade-in">
            <div class="streak-icon">
               <i class="fas fa-fire"></i>
            </div>
            <div class="streak-content">
               <h3>Login Streak</h3>
               <div class="streak-number">🔥 <?php echo $streak; ?> Days</div>
               <p>Keep learning every day!</p>
            </div>
         </div>
         <br>

         <!-- Analytics Cards -->
         <div class="analytics-grid fade-in">
            <div class="analytics-card">
               <div class="analytics-icon">
                  <i class="fas fa-chart-line"></i>
               </div>
               <div class="analytics-value"><?php echo $current; ?></div>
               <div class="analytics-label">Activities (7 days)</div>
               <div class="trend-indicator <?php echo $trend >= 0 ? 'trend-up' : 'trend-down'; ?>">
                  <i class="fas fa-arrow-<?php echo $trend >= 0 ? 'up' : 'down'; ?>"></i>
                  <?php echo abs($trend); ?>% vs last period
               </div>
            </div>

            <div class="analytics-card">
               <div class="analytics-icon">
                  <i class="fas fa-book-open"></i>
               </div>
               <div class="analytics-value"><?php echo $lessons_count; ?></div>
               <div class="analytics-label">Lessons Completed</div>
            </div>

            <div class="analytics-card">
               <div class="analytics-icon">
                  <i class="fas fa-magic"></i>
               </div>
               <div class="analytics-value"><?php echo $generated_count; ?></div>
               <div class="analytics-label">Content Generated</div>
            </div>

            <div class="analytics-card">
               <div class="analytics-icon">
                  <i class="fas fa-trophy"></i>
               </div>
               <div class="analytics-value"><?php echo $user['points']; ?></div>
               <div class="analytics-label">Total Points</div>
            </div>
         </div>

         <!-- Analytics Charts Row -->
         <div class="chart-row fade-in">
            <!-- Line Chart - Activity Over Time -->
            <div class="chart-container">
               <div class="chart-header">
                  <h3><i class="fas fa-chart-line" style="color: var(--primary-purple);"></i> Activity Analytics</h3>
                  <div class="time-range-selector" id="timeRangeSelector">
                     <button class="time-btn active" onclick="updateChart(7)">7 Days</button>
                     <button class="time-btn" onclick="updateChart(14)">14 Days</button>
                     <button class="time-btn" onclick="updateChart(30)">30 Days</button>
                  </div>
               </div>
               <div class="chart-wrapper">
                  <canvas id="activityChart"></canvas>
               </div>
               <div class="stats-mini-grid" id="miniStats">
                  <div class="stat-mini-card">
                     <div class="stat-mini-value" id="totalActivities"><?php echo array_sum($analytics_7d['data']); ?></div>
                     <div class="stat-mini-label">Total</div>
                  </div>
                  <div class="stat-mini-card">
                     <div class="stat-mini-value" id="avgActivities"><?php
                                                                     $avg = count($analytics_7d['data']) > 0 ? round(array_sum($analytics_7d['data']) / count($analytics_7d['data']), 1) : 0;
                                                                     echo $avg;
                                                                     ?></div>
                     <div class="stat-mini-label">Daily Avg</div>
                  </div>
                  <div class="stat-mini-card">
                     <div class="stat-mini-value" id="peakActivities"><?php echo max($analytics_7d['data']); ?></div>
                     <div class="stat-mini-label">Peak</div>
                  </div>
                  <div class="stat-mini-card">
                     <div class="stat-mini-value" id="bestDay"><?php
                                                               $peak = max($analytics_7d['data']);
                                                               $peak_index = array_search($peak, $analytics_7d['data']);
                                                               echo $analytics_7d['labels'][$peak_index] ? substr($analytics_7d['labels'][$peak_index], 0, 3) : '-';
                                                               ?></div>
                     <div class="stat-mini-label">Best Day</div>
                  </div>
               </div>
            </div>

            <!-- Doughnut Chart - Activity Breakdown -->
            <div class="chart-container">
               <div class="chart-header">
                  <h3><i class="fas fa-chart-pie" style="color: var(--primary-purple);"></i> Activity Breakdown</h3>
               </div>
               <div class="chart-wrapper" style="height: 250px;">
                  <canvas id="breakdownChart"></canvas>
               </div>
               <div style="margin-top: 20px;">
                  <?php foreach ($activity_types as $index => $type): ?>
                     <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--light-purple);">
                        <span>
                           <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $activity_colors[$index % count($activity_colors)]; ?>; margin-right: 8px;"></span>
                           <?php echo $type; ?>
                        </span>
                        <span style="font-weight: 600;"><?php echo $activity_counts[$index]; ?></span>
                     </div>
                  <?php endforeach; ?>
               </div>
            </div>
         </div>

         <!-- Daily Activity Table -->
         <div class="chart-container fade-in">
            <div class="section-title">
               <i class="fas fa-calendar-alt"></i>
               <h2>Daily Activity Breakdown (Last 7 Days)</h2>
            </div>
            <table class="daily-table">
               <thead>
                  <tr>
                     <th>Date</th>
                     <th>Logins</th>
                     <th>Lessons</th>
                     <th>Generate</th>
                     <th>Favorites</th>
                     <th>Total</th>
                  </tr>
               </thead>
               <tbody>
                  <?php if ($daily->num_rows > 0): ?>
                     <?php while ($day = $daily->fetch_assoc()): ?>
                        <tr>
                           <td><strong><?php echo date('M d, Y', strtotime($day['activity_date'])); ?></strong></td>
                           <td><span class="activity-badge badge-login"><?php echo $day['logins']; ?></span></td>
                           <td><span class="activity-badge badge-lesson"><?php echo $day['lessons']; ?></span></td>
                           <td><span class="activity-badge badge-generate"><?php echo $day['generates']; ?></span></td>
                           <td><span class="activity-badge badge-favorite"><?php echo $day['favorites']; ?></span></td>
                           <td><strong><?php echo $day['total']; ?></strong></td>
                        </tr>
                     <?php endwhile; ?>
                  <?php else: ?>
                     <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: var(--gray);">
                           No activity data available for the last 7 days.
                        </td>
                     </tr>
                  <?php endif; ?>
               </tbody>
            </table>
         </div>

         <!-- Recent Activity -->
         <div class="recent-activity fade-in">
            <div class="section-title">
               <i class="fas fa-history"></i>
               <h2>Recent Activity</h2>
            </div>
            <?php if ($recent->num_rows > 0): ?>
               <?php while ($activity = $recent->fetch_assoc()): ?>
                  <div class="activity-item">
                     <div class="activity-icon">
                        <i class="fas fa-<?php
                                          echo $activity['activity_type'] == 'login' ? 'sign-in-alt' : ($activity['activity_type'] == 'lesson' ? 'book' : ($activity['activity_type'] == 'generate' ? 'magic' : 'heart'));
                                          ?>"></i>
                     </div>
                     <div class="activity-details">
                        <div class="activity-title">
                           <?php echo ucfirst($activity['activity_type']); ?> Activity
                        </div>
                        <div class="activity-meta">
                           <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                           <span class="badge"><?php echo $activity['count']; ?> actions</span>
                        </div>
                     </div>
                     <div class="activity-count">
                        +<?php echo $activity['count']; ?>
                     </div>
                  </div>
               <?php endwhile; ?>
            <?php else: ?>
               <p style="text-align: center; color: var(--gray); padding: 30px;">
                  <i class="fas fa-info-circle"></i> No recent activity. Start learning to see your activity here!
               </p>
            <?php endif; ?>
         </div>

         <!-- Student List -->
         <div class="table-container fade-in">
            <div class="table-header">
               <h3><i class="fas fa-users"></i> Top Students Leaderboard</h3>
               <a href="rankings.php" class="btn btn-secondary">View All</a>
            </div>
            <div class="table-responsive">
               <table>
                  <thead>
                     <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Points</th>
                        <th>Progress</th>
                        <th>Badge</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php
                     $rank = 1;
                     while ($student = $students->fetch_assoc()):
                     ?>
                        <tr>
                           <td>
                              <span class="rank-badge rank-<?php echo $rank; ?>">#<?php echo $rank; ?></span>
                           </td>
                           <td>
                              <div class="student-info">
                                 <div class="student-avatar">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                 </div>
                                 <?php echo htmlspecialchars($student['full_name']); ?>
                              </div>
                           </td>
                           <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                           <td><strong><?php echo $student['total_points']; ?></strong> pts</td>
                           <td>
                              <div class="progress-bar">
                                 <div class="progress-fill" style="width: <?php echo min(100, ($student['total_points'] / 300) * 100); ?>%"></div>
                              </div>
                           </td>
                           <td>
                              <?php if ($rank == 1): ?>
                                 <span class="badge" style="background: gold; color: #333;">🥇 Champion</span>
                              <?php elseif ($rank == 2): ?>
                                 <span class="badge" style="background: silver; color: #333;">🥈 Silver</span>
                              <?php elseif ($rank == 3): ?>
                                 <span class="badge" style="background: #cd7f32; color: #fff;">🥉 Bronze</span>
                              <?php else: ?>
                                 <span class="badge">⭐ Rising Star</span>
                              <?php endif; ?>
                           </td>
                        </tr>
                     <?php
                        $rank++;
                     endwhile;
                     ?>
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>

   <script>
      // Store all chart data
      const chartData = {
         '7': {
            labels: <?php echo json_encode($analytics_7d['labels']); ?>,
            data: <?php echo json_encode($analytics_7d['data']); ?>
         },
         '14': {
            labels: <?php echo json_encode($analytics_14d['labels']); ?>,
            data: <?php echo json_encode($analytics_14d['data']); ?>
         },
         '30': {
            labels: <?php echo json_encode($analytics_30d['labels']); ?>,
            data: <?php echo json_encode($analytics_30d['data']); ?>
         }
      };

      // Initialize activity chart
      const ctx = document.getElementById('activityChart').getContext('2d');
      let activityChart = new Chart(ctx, {
         type: 'line',
         data: {
            labels: <?php echo json_encode($analytics_7d['labels']); ?>,
            datasets: [{
               label: 'Daily Activities',
               data: <?php echo json_encode($analytics_7d['data']); ?>,
               borderColor: '#7b2cff',
               backgroundColor: 'rgba(123, 44, 255, 0.1)',
               borderWidth: 3,
               pointBackgroundColor: '#7b2cff',
               pointBorderColor: '#fff',
               pointBorderWidth: 2,
               pointRadius: 5,
               pointHoverRadius: 7,
               tension: 0.3,
               fill: true
            }]
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
               legend: {
                  display: false
               },
               tooltip: {
                  backgroundColor: '#fff',
                  titleColor: '#333',
                  bodyColor: '#666',
                  borderColor: '#7b2cff',
                  borderWidth: 1,
                  padding: 10,
                  displayColors: false
               }
            },
            scales: {
               y: {
                  beginAtZero: true,
                  grid: {
                     color: 'rgba(0,0,0,0.05)'
                  },
                  ticks: {
                     stepSize: 1
                  }
               },
               x: {
                  grid: {
                     display: false
                  }
               }
            }
         }
      });

      // Initialize breakdown chart
      const ctx2 = document.getElementById('breakdownChart').getContext('2d');
      new Chart(ctx2, {
         type: 'doughnut',
         data: {
            labels: <?php echo json_encode($activity_types); ?>,
            datasets: [{
               data: <?php echo json_encode($activity_counts); ?>,
               backgroundColor: ['#7b2cff', '#ff8c3a', '#00C851', '#ffbb33'],
               borderWidth: 0,
               hoverOffset: 5
            }]
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
               legend: {
                  position: 'bottom',
                  labels: {
                     usePointStyle: true,
                     padding: 20,
                     font: {
                        size: 12
                     }
                  }
               }
            },
            cutout: '60%'
         }
      });

      // Update chart function (no API call - uses preloaded data)
      function updateChart(days) {
         // Update active button
         document.querySelectorAll('.time-btn').forEach(btn => {
            btn.classList.remove('active');
         });
         event.target.classList.add('active');

         // Get data for selected days
         const data = chartData[days.toString()];

         // Update chart
         activityChart.data.labels = data.labels;
         activityChart.data.datasets[0].data = data.data;
         activityChart.update();

         // Update mini stats
         const total = data.data.reduce((a, b) => a + b, 0);
         const avg = (total / data.data.length).toFixed(1);
         const peak = Math.max(...data.data);
         const peakIndex = data.data.indexOf(peak);

         document.getElementById('totalActivities').textContent = total;
         document.getElementById('avgActivities').textContent = avg;
         document.getElementById('peakActivities').textContent = peak;
         document.getElementById('bestDay').textContent = data.labels[peakIndex] ? data.labels[peakIndex].substring(0, 3) : '-';
      }
   </script>
</body>

</html>