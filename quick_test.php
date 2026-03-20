<?php
// quick_test.php
require_once 'config/database.php';

$test_username = 'admin';
$test_password = 'admin123';

echo "Testing login for: $test_username\n\n";

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $test_username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "User found: {$row['full_name']}\n";
    echo "Stored hash: {$row['password']}\n";
    echo "Hash length: " . strlen($row['password']) . " characters\n";
    
    if (password_verify($test_password, $row['password'])) {
        echo "✅ Password CORRECT!\n";
    } else {
        echo "❌ Password INCORRECT!\n";
        
        // Generate new hash for comparison
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "\nFor reference, a new hash for '$test_password' would be:\n";
        echo $new_hash . "\n";
        echo "Length: " . strlen($new_hash) . "\n";
    }
} else {
    echo "❌ User not found!\n";
}
?>