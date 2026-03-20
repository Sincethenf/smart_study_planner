<?php
// debug_login.php
require_once 'config/database.php';

echo "=================================\n";
echo "LOGIN DEBUGGING TOOL\n";
echo "=================================\n\n";

// Test 1: Database Connection
echo "1. Testing Database Connection...\n";
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Connected successfully\n\n";

// Test 2: Check if users table exists
echo "2. Checking Users Table...\n";
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    die("❌ Users table does not exist! Run database.sql first.\n");
}
echo "✅ Users table exists\n\n";

// Test 3: Count users
echo "3. Checking User Records...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$count = $result->fetch_assoc()['count'];
echo "Total users in database: $count\n\n";

// Test 4: Show all users
echo "4. Listing All Users:\n";
$users = $conn->query("SELECT id, username, email, role, is_active FROM users");
if ($users->num_rows > 0) {
    while ($user = $users->fetch_assoc()) {
        echo "   ID: {$user['id']} | Username: {$user['username']} | Email: {$user['email']} | Role: {$user['role']} | Active: {$user['is_active']}\n";
    }
} else {
    echo "   No users found!\n";
}
echo "\n";

// Test 5: Check admin user specifically
echo "5. Checking Admin User:\n";
$stmt = $conn->prepare("SELECT * FROM users WHERE username = 'admin' OR email = 'admin@school.com'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "❌ Admin user not found!\n";
    echo "   Let's create admin user...\n\n";
    
    // Create admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, student_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $username = 'admin';
    $email = 'admin@school.com';
    $full_name = 'Administrator';
    $role = 'admin';
    $student_id = 'ADMIN001';
    $active = 1;
    
    $insert->bind_param("ssssssi", $username, $email, $admin_password, $full_name, $role, $student_id, $active);
    
    if ($insert->execute()) {
        echo "✅ Admin user created successfully!\n";
        echo "   Username: admin\n";
        echo "   Password: admin123\n";
    } else {
        echo "❌ Failed to create admin: " . $insert->error . "\n";
    }
} else {
    $admin = $result->fetch_assoc();
    echo "✅ Admin user found:\n";
    echo "   ID: {$admin['id']}\n";
    echo "   Username: {$admin['username']}\n";
    echo "   Email: {$admin['email']}\n";
    echo "   Full Name: {$admin['full_name']}\n";
    echo "   Role: {$admin['role']}\n";
    echo "   Active: {$admin['is_active']}\n";
    echo "   Password Hash: {$admin['password']}\n";
    echo "   Hash Length: " . strlen($admin['password']) . " characters\n\n";
    
    // Test password
    echo "6. Testing Password 'admin123':\n";
    if (password_verify('admin123', $admin['password'])) {
        echo "✅ Password is CORRECT!\n";
    } else {
        echo "❌ Password is INCORRECT!\n";
        
        // Generate new hash
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        echo "   Current hash: {$admin['password']}\n";
        echo "   New hash should be: $new_hash\n";
        echo "   Length: " . strlen($new_hash) . " characters\n\n";
        
        // Offer to fix
        echo "7. Do you want to fix the password? (Run fix_admin.php)\n";
    }
}

// Test 6: Check login_streak column
echo "\n8. Checking database structure...\n";
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'login_streak'");
if ($result->num_rows == 0) {
    echo "⚠️  login_streak column missing. Adding it...\n";
    $conn->query("ALTER TABLE users ADD COLUMN login_streak INT DEFAULT 0 AFTER last_login");
    echo "✅ Added login_streak column\n";
}

// Test 7: Check session configuration
echo "\n9. Checking PHP Session Configuration...\n";
echo "   Session save path: " . session_save_path() . "\n";
echo "   Session name: " . session_name() . "\n";
echo "   Session status: " . session_status() . "\n";

echo "\n=================================\n";
echo "DEBUG COMPLETE\n";
echo "=================================\n";
?>