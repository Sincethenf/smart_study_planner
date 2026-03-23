<?php
// student/get_notifications.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Time ago helper
if (!function_exists('timeAgoShort')) {
    function timeAgoShort($datetime) {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', strtotime($datetime));
    }
}

// Fetch recent notifications (limit 10)
$stmt = $conn->prepare("
    SELECT n.id, n.message, n.type, n.is_read, n.created_at,
           n.sender_id, n.related_id, n.related_type,
           u.full_name AS sender_name, u.profile_picture,
           COALESCE(u.avatar_color,'#3b82f6') AS sender_color
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $message = htmlspecialchars($row['message']);
    
    // Replace {sender} placeholder
    if (!empty($row['sender_name'])) {
        $message = str_replace(
            '{sender}',
            '<strong>' . htmlspecialchars($row['sender_name']) . '</strong>',
            $message
        );
    }
    
    // Generate link based on notification type and related_type
    $link = 'forum.php';
    if ($row['related_type'] === 'post' && $row['related_id']) {
        // For post-related notifications (comments, likes)
        $link = 'forum.php?post=' . $row['related_id'];
    } elseif ($row['related_type'] === 'comment' && $row['related_id']) {
        // For comment-related notifications (replies)
        // Get the post_id from the comment
        $comment_query = $conn->prepare("SELECT post_id FROM feed_comments WHERE id = ?");
        $comment_query->bind_param("i", $row['related_id']);
        $comment_query->execute();
        $comment_result = $comment_query->get_result();
        if ($comment_row = $comment_result->fetch_assoc()) {
            $link = 'forum.php?post=' . $comment_row['post_id'] . '&comment=' . $row['related_id'];
        }
    }
    
    $notifications[] = [
        'id' => $row['id'],
        'message_html' => $message,
        'type' => $row['type'],
        'is_read' => (bool)$row['is_read'],
        'time_ago' => timeAgoShort($row['created_at']),
        'sender_initial' => !empty($row['sender_name']) ? strtoupper(substr($row['sender_name'], 0, 1)) : null,
        'sender_color' => $row['sender_color'],
        'sender_picture' => $row['profile_picture'],
        'link' => $link
    ];
}

// Get unread count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = (int)$unread_stmt->get_result()->fetch_assoc()['c'];

// Get total count
$total_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?");
$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();
$total_count = (int)$total_stmt->get_result()->fetch_assoc()['c'];

echo json_encode([
    'notifications' => $notifications,
    'unread' => $unread_count,
    'total' => $total_count
]);
