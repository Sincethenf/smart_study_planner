<?php
// includes/notifications.php - Notification helper functions

function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['count'];
}

function createNotification($conn, $user_id, $sender_id, $type, $message, $related_id = null, $related_type = null) {
    // Don't create notification if user is notifying themselves
    if ($user_id == $sender_id) return false;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissis", $user_id, $sender_id, $type, $message, $related_id, $related_type);
    return $stmt->execute();
}

function renderNotificationBell($unread_count) {
    $pip = $unread_count > 0 ? '<span class="notif-pip">' . ($unread_count > 9 ? '9+' : $unread_count) . '</span>' : '';
    return '<a href="notifications.php" class="icon-btn"><i class="fas fa-bell"></i>' . $pip . '</a>';
}
?>