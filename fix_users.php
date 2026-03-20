<?php
// fix_users.php
require_once 'config/database.php';

echo "=== Fixing User Accounts ===\n\n";

// Clear existing users (be careful!)
echo "Clearing existing users...\n";
$conn->query("DELETE FROM user_activity");
$conn->query("DELETE FROM rankings");
$conn->query("DELETE FROM favorites");
$conn->query("DELETE FROM user_lessons");
$conn->query("DELETE FROM users");

echo "Creating new users with proper password hashes...\n";

// Create admin user
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, student_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
$admin_username = 'admin';
$admin_email = 'admin@school.com';
$admin_name = 'Admin User';
$admin_role = 'admin';
$admin_id = 'ADM001';
$active = 1;
$stmt->bind_param("ssssssi", $admin_username, $admin_email, $admin_password, $admin_name, $admin_role, $admin_id, $active);
$stmt->execute();
echo "✅ Admin user created: admin / admin123\n";

// Create student users
$students = [
    ['john.doe', 'john@example.com', 'John Doe', 'STU001', 150],
    ['jane.smith', 'jane@example.com', 'Jane Smith', 'STU002', 200],
    ['bob.wilson', 'bob@example.com', 'Bob Wilson', 'STU003', 175]
];

foreach ($students as $student) {
    $student_password = password_hash('student123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, student_id, points, is_active) VALUES (?, ?, ?, ?, 'student', ?, ?, 1)");
    $stmt->bind_param("sssssi", $student[0], $student[1], $student_password, $student[2], $student[3], $student[4]);
    $stmt->execute();
    $user_id = $stmt->insert_id;
    
    // Add to rankings
    $rank_stmt = $conn->prepare("INSERT INTO rankings (user_id, total_points) VALUES (?, ?)");
    $rank_stmt->bind_param("ii", $user_id, $student[4]);
    $rank_stmt->execute();
    
    echo "✅ Student created: {$student[0]} / student123 (Points: {$student[4]})\n";
}

echo "\n=== All users fixed! ===\n";
echo "Try logging in now with:\n";
echo "Admin: admin / admin123\n";
echo "Student: john.doe / student123\n";
?>