<?php
// generate_hashes.php
echo "=== Password Hash Generator ===\n\n";

$passwords = [
    'admin123',
    'student123',
    'teacher123',
    'parent123'
];

foreach ($passwords as $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Password: $password\n";
    echo "Hash: $hash\n";
    echo "Length: " . strlen($hash) . " characters\n";
    echo "----------------------------------------\n\n";
}

echo "Copy these hashes and update your database!\n";
?>