<?php
// reset_all.php
require_once 'config/database.php';

echo "=================================\n";
echo "COMPLETE SYSTEM RESET\n";
echo "=================================\n\n";

// Drop and recreate tables
echo "1. Recreating database tables...\n";

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Drop tables in correct order
$tables = ['user_activity', 'rankings', 'favorites', 'user_lessons', 'generated_content', 'users'];
foreach ($tables as $table) {
    $conn->query("DROP TABLE IF EXISTS $table");
    echo "   Dropped table: $table\n";
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Create users table
$conn->query("
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'student',
    profile_picture VARCHAR(255) DEFAULT 'default-avatar.png',
    student_id VARCHAR(20) UNIQUE,
    join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATE,
    login_streak INT DEFAULT 0,
    points INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

echo "✅ Users table created\n";

// Create rankings table
$conn->query("
CREATE TABLE rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    lessons_completed INT DEFAULT 0,
    generated_count INT DEFAULT 0,
    rank_position INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_ranking (user_id)
)");

echo "✅ Rankings table created\n";

// Create user_activity table
$conn->query("
CREATE TABLE user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_date DATE NOT NULL,
    activity_type ENUM('login', 'lesson', 'generate', 'favorite') NOT NULL,
    count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily_activity (user_id, activity_date, activity_type)
)");

echo "✅ User activity table created\n";

// Create favorites table
$conn->query("
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_type ENUM('lesson', 'generated') NOT NULL,
    content_id INT,
    content_data TEXT,
    title VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

echo "✅ Favorites table created\n";

// Create generated_content table
$conn->query("
CREATE TABLE generated_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200),
    content TEXT,
    type VARCHAR(50),
    is_favorite BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

echo "✅ Generated content table created\n\n";

// Create admin user
echo "2. Creating admin user...\n";
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, student_id, is_active) VALUES (?, ?, ?, ?, 'admin', ?, 1)");
$username = 'admin';
$email = 'admin@school.com';
$full_name = 'System Administrator';
$student_id = 'ADMIN001';

$stmt->bind_param("sssss", $username, $email, $admin_password, $full_name, $student_id);

if ($stmt->execute()) {
    echo "✅ Admin user created successfully!\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n";
    echo "   Email: admin@school.com\n\n";
} else {
    echo "❌ Failed to create admin: " . $stmt->error . "\n\n";
}

// Create sample student
echo "3. Creating sample student...\n";
$student_password = password_hash('student123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, student_id, points, is_active) VALUES (?, ?, ?, ?, 'student', ?, ?, 1)");
$s_username = 'john.doe';
$s_email = 'john@example.com';
$s_full_name = 'John Doe';
$s_student_id = 'STU001';
$s_points = 150;

$stmt->bind_param("sssssi", $s_username, $s_email, $student_password, $s_full_name, $s_student_id, $s_points);

if ($stmt->execute()) {
    $student_id = $stmt->insert_id;
    echo "✅ Sample student created successfully!\n";
    echo "   Username: john.doe\n";
    echo "   Password: student123\n\n";
    
    // Add to rankings
    $rank_stmt = $conn->prepare("INSERT INTO rankings (user_id, total_points) VALUES (?, ?)");
    $rank_stmt->bind_param("ii", $student_id, $s_points);
    $rank_stmt->execute();
    echo "✅ Added to rankings\n";
} else {
    echo "❌ Failed to create student: " . $stmt->error . "\n\n";
}

echo "\n=================================\n";
echo "RESET COMPLETE!\n";
echo "=================================\n";
echo "You can now login with:\n";
echo "Admin: admin / admin123\n";
echo "Student: john.doe / student123\n";
echo "=================================\n";
?>