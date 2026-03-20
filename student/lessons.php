<?php
// student/lessons.php
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

// Get all lessons
$lessons = $conn->query("SELECT * FROM lessons ORDER BY difficulty, title");

// Get user's lesson progress
$progress = $conn->prepare("SELECT lesson_id, status, progress FROM user_lessons WHERE user_id = ?");
$progress->bind_param("i", $user_id);
$progress->execute();
$user_progress = $progress->get_result();

$progress_map = [];
while ($row = $user_progress->fetch_assoc()) {
    $progress_map[$row['lesson_id']] = $row;
}

// Handle start lesson
if (isset($_POST['start_lesson'])) {
    $lesson_id = $_POST['lesson_id'];
    
    // Check if already started
    $check = $conn->prepare("SELECT id FROM user_lessons WHERE user_id = ? AND lesson_id = ?");
    $check->bind_param("ii", $user_id, $lesson_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO user_lessons (user_id, lesson_id, status, progress) VALUES (?, ?, 'in_progress', 10)");
        $stmt->bind_param("ii", $user_id, $lesson_id);
        $stmt->execute();
        
        // Log activity
        $today = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count) 
                                VALUES (?, ?, 'lesson', 1) 
                                ON DUPLICATE KEY UPDATE count = count + 1");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
    }
    
    header("Location: lessons.php?started=" . $lesson_id);
    exit();
}

// Handle complete lesson
if (isset($_POST['complete_lesson'])) {
    $lesson_id = $_POST['lesson_id'];
    
    $stmt = $conn->prepare("UPDATE user_lessons SET status = 'completed', progress = 100, completed_at = NOW() WHERE user_id = ? AND lesson_id = ?");
    $stmt->bind_param("ii", $user_id, $lesson_id);
    $stmt->execute();
    
    // Update user points
    $lesson_points = $conn->query("SELECT points FROM lessons WHERE id = $lesson_id")->fetch_assoc()['points'];
    $conn->query("UPDATE users SET points = points + $lesson_points WHERE id = $user_id");
    
    // Update rankings
    $conn->query("UPDATE rankings SET total_points = total_points + $lesson_points, lessons_completed = lessons_completed + 1 WHERE user_id = $user_id");
    
    // Log activity
    $today = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count) 
                            VALUES (?, ?, 'lesson', 1) 
                            ON DUPLICATE KEY UPDATE count = count + 1");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    
    header("Location: lessons.php?completed=" . $lesson_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .lessons-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .lessons-header h2 {
            color: #333;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #f1e8ff;
            border-radius: 8px;
            background: white;
            color: #666;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: #7b2cff;
            color: white;
            border-color: #7b2cff;
        }
        
        .filter-btn:hover {
            background: #f1e8ff;
            color: #7b2cff;
        }
        
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .lesson-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .lesson-card:hover {
            transform: translateY(-5px);
        }
        
        .lesson-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #7b2cff, #9b4dff);
        }
        
        .lesson-difficulty {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .difficulty-beginner {
            background: #e6ffe9;
            color: #00C851;
        }
        
        .difficulty-intermediate {
            background: #fff3e0;
            color: #ffbb33;
        }
        
        .difficulty-advanced {
            background: #ffe6e6;
            color: #ff4444;
        }
        
        .lesson-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .lesson-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .lesson-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #666;
        }
        
        .meta-item i {
            color: #7b2cff;
        }
        
        .progress-section {
            margin-bottom: 20px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f1e8ff;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #7b2cff;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .lesson-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-lesson {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-start {
            background: #7b2cff;
            color: white;
        }
        
        .btn-start:hover {
            background: #6a1bb9;
        }
        
        .btn-continue {
            background: #ffbb33;
            color: #333;
        }
        
        .btn-continue:hover {
            background: #ffa000;
        }
        
        .btn-complete {
            background: #00C851;
            color: white;
        }
        
        .btn-complete:hover {
            background: #009933;
        }
        
        .btn-completed {
            background: #e0e0e0;
            color: #999;
            cursor: not-allowed;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-completed {
            background: #00C851;
            color: white;
        }
        
        .status-in-progress {
            background: #ffbb33;
            color: #333;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #7b2cff;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
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
                <li class="active"><a href="lessons.php"><i class="fas fa-book"></i> <span>Lessons</span></a></li>
                <li><a href="favorites.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
                <li><a href="rankings.php"><i class="fas fa-trophy"></i> <span>Rankings</span></a></li>
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
                    <a href="lessons.php" class="nav-item active">Lessons</a>
                    <a href="favorites.php" class="nav-item">Favorites</a>
                    <a href="rankings.php" class="nav-item">Rankings</a>
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
            
            <!-- Success Messages -->
            <?php if (isset($_GET['started'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Lesson started successfully! Keep learning.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['completed'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Congratulations! Lesson completed. Points earned!
                </div>
            <?php endif; ?>
            
            <!-- Lessons Header -->
            <div class="lessons-header">
                <h2>
                    <i class="fas fa-book-open" style="color: #7b2cff;"></i>
                    Available Lessons
                </h2>
                
                <div class="filter-bar">
                    <button class="filter-btn active" onclick="filterLessons('all')">All</button>
                    <button class="filter-btn" onclick="filterLessons('beginner')">Beginner</button>
                    <button class="filter-btn" onclick="filterLessons('intermediate')">Intermediate</button>
                    <button class="filter-btn" onclick="filterLessons('advanced')">Advanced</button>
                    <button class="filter-btn" onclick="filterLessons('in-progress')">In Progress</button>
                </div>
            </div>
            
            <!-- Lessons Grid -->
            <?php if ($lessons->num_rows > 0): ?>
                <div class="lessons-grid" id="lessonsGrid">
                    <?php while($lesson = $lessons->fetch_assoc()): 
                        $status = isset($progress_map[$lesson['id']]) ? $progress_map[$lesson['id']]['status'] : 'not_started';
                        $progress = isset($progress_map[$lesson['id']]) ? $progress_map[$lesson['id']]['progress'] : 0;
                        $difficulty_class = '';
                        switch($lesson['difficulty']) {
                            case 'beginner':
                                $difficulty_class = 'difficulty-beginner';
                                break;
                            case 'intermediate':
                                $difficulty_class = 'difficulty-intermediate';
                                break;
                            case 'advanced':
                                $difficulty_class = 'difficulty-advanced';
                                break;
                        }
                    ?>
                    <div class="lesson-card" data-difficulty="<?php echo $lesson['difficulty']; ?>" data-status="<?php echo $status; ?>">
                        <?php if ($status == 'completed'): ?>
                            <span class="status-badge status-completed"><i class="fas fa-check"></i> Completed</span>
                        <?php elseif ($status == 'in_progress'): ?>
                            <span class="status-badge status-in-progress"><i class="fas fa-spinner"></i> In Progress</span>
                        <?php endif; ?>
                        
                        <span class="lesson-difficulty <?php echo $difficulty_class; ?>">
                            <?php echo ucfirst($lesson['difficulty']); ?>
                        </span>
                        
                        <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                        <p class="lesson-description"><?php echo htmlspecialchars($lesson['description']); ?></p>
                        
                        <div class="lesson-meta">
                            <span class="meta-item">
                                <i class="fas fa-star"></i> <?php echo $lesson['points']; ?> points
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-clock"></i> 30 mins
                            </span>
                        </div>
                        
                        <?php if ($status == 'in_progress'): ?>
                            <div class="progress-section">
                                <div class="progress-header">
                                    <span>Progress</span>
                                    <span><?php echo $progress; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="lesson-actions">
                            <?php if ($status == 'not_started'): ?>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                    <button type="submit" name="start_lesson" class="btn-lesson btn-start">
                                        <i class="fas fa-play"></i> Start Lesson
                                    </button>
                                </form>
                            <?php elseif ($status == 'in_progress'): ?>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                    <button type="submit" name="continue_lesson" class="btn-lesson btn-continue">
                                        <i class="fas fa-play-circle"></i> Continue
                                    </button>
                                </form>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                    <button type="submit" name="complete_lesson" class="btn-lesson btn-complete">
                                        <i class="fas fa-check"></i> Complete
                                    </button>
                                </form>
                            <?php elseif ($status == 'completed'): ?>
                                <button class="btn-lesson btn-completed" disabled>
                                    <i class="fas fa-check-circle"></i> Completed
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Lessons Available</h3>
                    <p>Check back later for new lessons!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function filterLessons(filter) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter lessons
            const lessons = document.querySelectorAll('.lesson-card');
            
            lessons.forEach(lesson => {
                if (filter === 'all') {
                    lesson.style.display = 'block';
                } else if (filter === 'in-progress') {
                    const status = lesson.getAttribute('data-status');
                    if (status === 'in_progress') {
                        lesson.style.display = 'block';
                    } else {
                        lesson.style.display = 'none';
                    }
                } else {
                    const difficulty = lesson.getAttribute('data-difficulty');
                    if (difficulty === filter) {
                        lesson.style.display = 'block';
                    } else {
                        lesson.style.display = 'none';
                    }
                }
            });
        }
    </script>
</body>
</html>