<?php
// test_login.php
require_once 'config/database.php';

echo "=== Login System Test ===\n\n";

// Test 1: Check database connection
echo "1. Testing database connection...\n";
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Database connected successfully!\n\n";

// Test 2: Check if users table exists and has data
echo "2. Checking users table...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "✅ Users table exists with $count records\n\n";
} else {
    die("❌ Users table not found: " . $conn->error);
}

// Test 3: List all users
echo "3. Users in database:\n";
$users = $conn->query("SELECT id, username, email, role, full_name FROM users");
while ($user = $users->fetch_assoc()) {
    echo "   - {$user['username']} ({$user['email']}) - Role: {$user['role']}\n";
}
echo "\n";

// Test 4: Verify password for admin
echo "4. Testing admin password verification...\n";
$test_username = 'admin';
$test_password = 'admin123';

$stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
$stmt->bind_param("s", $test_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    echo "   ✅ User found: {$user['username']}\n";
    
    if (password_verify($test_password, $user['password'])) {
        echo "   ✅ Password verification SUCCESSFUL!\n";
    } else {
        echo "   ❌ Password verification FAILED!\n";
        echo "      Stored hash: {$user['password']}\n";
        echo "      This hash should start with '$2y$10$' and be 60 characters long\n";
    }
} else {
    echo "   ❌ User 'admin' not found!\n";
}

echo "\n=== Debugging Tips ===\n";
echo "1. Make sure you've replaced the hash placeholders with actual password_hash() output\n";
echo "2. Check that the password column in database is VARCHAR(255)\n";
echo "3. Verify that no extra spaces or characters are in the stored hash\n";
echo "4. Try clearing your browser cookies and cache\n";
?>