<?php
// config/database.php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_platform');
define('SITE_NAME', 'Smart Study Planner');
define('SITE_URL', 'http://localhost/smart_study_planner');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/auth/login.php");
        exit();
    }
}

// Function to redirect if not admin
function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: " . SITE_URL . "/student/dashboard.php");
        exit();
    }
}

// Function to sanitize input
function sanitize($conn, $data) {
    return $conn->real_escape_string(trim(htmlspecialchars($data)));
}

// Function to update login streak
function updateLoginStreak($conn, $user_id) {
    $today = date('Y-m-d');
    
    // Get last login
    $stmt = $conn->prepare("SELECT last_login, login_streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($user['last_login'] == $yesterday) {
        // Consecutive login
        $new_streak = $user['login_streak'] + 1;
    } elseif ($user['last_login'] == $today) {
        // Already logged in today
        $new_streak = $user['login_streak'];
    } else {
        // Streak broken
        $new_streak = 1;
    }
    
    // Update streak
    $stmt = $conn->prepare("UPDATE users SET last_login = ?, login_streak = ? WHERE id = ?");
    $stmt->bind_param("sii", $today, $new_streak, $user_id);
    $stmt->execute();
    
    // Log activity
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type) 
                            VALUES (?, ?, 'login') 
                            ON DUPLICATE KEY UPDATE count = count + 1");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    
    return $new_streak;
}

// Function to get leaderboard
function getLeaderboard($conn, $limit = 10) {
    $stmt = $conn->prepare("SELECT u.id, u.full_name, u.username, u.profile_picture, 
                                   r.total_points, r.lessons_completed, r.generated_count,
                                   RANK() OVER (ORDER BY r.total_points DESC) as rank_position
                            FROM rankings r
                            JOIN users u ON r.user_id = u.id
                            WHERE u.role = 'student'
                            ORDER BY r.total_points DESC
                            LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
    
    return $leaderboard;
}

// Function to get dashboard statistics
function getDashboardStats($conn) {
    $stats = [];
    
    // Get student count
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stats['students'] = $result->fetch_assoc()['count'];
    
    // Get teacher count
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    $stats['teachers'] = $result->fetch_assoc()['count'];
    
    // Get total lessons
    $result = $conn->query("SELECT COUNT(*) as count FROM lessons");
    $stats['lessons'] = $result->fetch_assoc()['count'];
    
    // Get active today
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_activity WHERE activity_date = '$today'");
    $stats['active_today'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

// Function to get time ago string
function timeAgo($date) {
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return "Just now";
    } elseif ($difference < 3600) {
        $mins = floor($difference / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($difference < 2592000) {
        $days = floor($difference / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("d M Y", $timestamp);
    }
}

// Error handling function
function logError($message) {
    $logFile = 'error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>