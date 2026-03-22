<?php
// migrations/add_notifications_table.php
require_once '../config/database.php';

// Create notifications table
$sql = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NULL,
    type ENUM('reply', 'like', 'mention', 'system') NOT NULL DEFAULT 'reply',
    message TEXT NOT NULL,
    related_id INT NULL,
    related_type ENUM('post', 'comment', 'reply') NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Notifications table created successfully\n";
} else {
    echo "❌ Error creating notifications table: " . $conn->error . "\n";
}

// Add sample notifications for testing (optional)
$sample_sql = "
INSERT IGNORE INTO notifications (user_id, sender_id, type, message, related_id, related_type, is_read) VALUES
(1, 2, 'reply', '{sender} replied to your comment', 1, 'comment', 0),
(1, 3, 'like', '{sender} liked your post', 1, 'post', 0),
(2, 1, 'reply', '{sender} replied to your comment in the forum', 2, 'comment', 1)
";

if ($conn->query($sample_sql) === TRUE) {
    echo "✅ Sample notifications added\n";
} else {
    echo "❌ Error adding sample notifications: " . $conn->error . "\n";
}

echo "🎉 Notifications system setup complete!\n";
?>