<?php
// fix_admin.php
require_once 'config/database.php';

echo "=================================\n";
echo "ADMIN PASSWORD FIX TOOL\n";
echo "=================================\n\n";

// First, let's check what's in the database
echo "Checking current admin users...\n";
$result = $conn->query("SELECT id, username, email, password FROM users WHERE role = 'admin' OR username = 'admin'");

if ($result->num_rows > 0) {
    while ($admin = $result->fetch_assoc()) {
        echo "\nFound admin account:\n";
        echo "ID: {$admin['id']}\n";
        echo "Username: {$admin['username']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Current hash: {$admin['password']}\n";
        echo "Hash length: " . strlen($admin['password']) . "\n";
        
        // Test if current password works
        if (password_verify('admin123', $admin['password'])) {
            echo "✅ Current password 'admin123' works!\n";
        } else {
            echo "❌ Current password 'admin123' does NOT work\n";
        }
    }
} else {
    echo "No admin user found!\n";
}

echo "\n--- Fixing admin password ---\n";

// Generate new password hash
$new_password = 'admin123';
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "New password: $new_password\n";
echo "New hash: $new_hash\n";
echo "Hash length: " . strlen($new_hash) . " characters\n\n";

// Update or insert admin
if ($result->num_rows > 0) {
    // Update existing admin
    $admin = $result->fetch_assoc(); // This might need adjustment
    $result->data_seek(0); // Reset pointer
    while ($admin = $result->fetch_assoc()) {
        $stmt = $conn->prepare("UPDATE users SET password = ?, full_name = ?, is_active = 1 WHERE id = ?");
        $full_name = "Administrator";
        $stmt->bind_param("ssi", $new_hash, $full_name, $admin['id']);
        
        if ($stmt->execute()) {
            echo "✅ Updated admin ID {$admin['id']} with new password\n";
        } else {
            echo "❌ Failed to update: " . $stmt->error . "\n";
        }
    }
} else {
    // Insert new admin
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, student_id, is_active) VALUES (?, ?, ?, ?, 'admin', ?, 1)");
    $username = 'admin';
    $email = 'admin@school.com';
    $full_name = 'Administrator';
    $student_id = 'ADMIN001';
    
    $stmt->bind_param("sssss", $username, $email, $new_hash, $full_name, $student_id);
    
    if ($stmt->execute()) {
        echo "✅ Created new admin user\n";
    } else {
        echo "❌ Failed to create: " . $stmt->error . "\n";
    }
}

// Verify the fix
echo "\n--- Verifying fix ---\n";
$check = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($check->num_rows > 0) {
    $admin = $check->fetch_assoc();
    echo "Admin user exists:\n";
    echo "Username: {$admin['username']}\n";
    echo "Password hash: {$admin['password']}\n";
    
    if (password_verify('admin123', $admin['password'])) {
        echo "✅ PASSWORD VERIFICATION SUCCESSFUL!\n";
        echo "You can now login with:\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "❌ PASSWORD VERIFICATION FAILED!\n";
        echo "There might be an issue with the password hashing function.\n";
    }
} else {
    echo "❌ Admin user still not found!\n";
}

echo "\n=================================\n";
echo "FIX COMPLETE\n";
echo "=================================\n";
?>