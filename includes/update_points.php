<?php
// includes/update_points.php - Calculate and update user points based on activities

function calculateUserPoints($conn, $user_id) {
    // Point values for different activities
    $points = [
        'login' => 5,
        'lesson' => 10,
        'generate' => 8,
        'favorite' => 3,
        'forum_post' => 15,
        'forum_comment' => 8,
        'forum_reply' => 5,
        'forum_reaction' => 2,
        'streak_bonus' => 10  // Per day of streak
    ];
    
    $total_points = 0;
    
    // 1. Calculate points from user_activity table
    $activity_query = $conn->prepare("
        SELECT activity_type, SUM(count) as total_count
        FROM user_activity
        WHERE user_id = ?
        GROUP BY activity_type
    ");
    $activity_query->bind_param("i", $user_id);
    $activity_query->execute();
    $activities = $activity_query->get_result();
    
    while ($activity = $activities->fetch_assoc()) {
        $type = $activity['activity_type'];
        $count = (int)$activity['total_count'];
        if (isset($points[$type])) {
            $total_points += $points[$type] * $count;
        }
    }
    
    // 2. Calculate points from forum activities
    // Forum posts
    $forum_posts = $conn->prepare("SELECT COUNT(*) as count FROM feed_posts WHERE user_id = ?");
    $forum_posts->bind_param("i", $user_id);
    $forum_posts->execute();
    $posts_count = (int)$forum_posts->get_result()->fetch_assoc()['count'];
    $total_points += $points['forum_post'] * $posts_count;
    
    // Forum comments
    $forum_comments = $conn->prepare("SELECT COUNT(*) as count FROM feed_comments WHERE user_id = ?");
    $forum_comments->bind_param("i", $user_id);
    $forum_comments->execute();
    $comments_count = (int)$forum_comments->get_result()->fetch_assoc()['count'];
    $total_points += $points['forum_comment'] * $comments_count;
    
    // Forum replies
    $forum_replies = $conn->prepare("SELECT COUNT(*) as count FROM feed_replies WHERE user_id = ?");
    $forum_replies->bind_param("i", $user_id);
    $forum_replies->execute();
    $replies_count = (int)$forum_replies->get_result()->fetch_assoc()['count'];
    $total_points += $points['forum_reply'] * $replies_count;
    
    // Forum reactions (post reactions)
    $post_reactions = $conn->prepare("SELECT COUNT(*) as count FROM feed_post_reactions WHERE user_id = ?");
    $post_reactions->bind_param("i", $user_id);
    $post_reactions->execute();
    $post_reactions_count = (int)$post_reactions->get_result()->fetch_assoc()['count'];
    $total_points += $points['forum_reaction'] * $post_reactions_count;
    
    // Forum reactions (reply reactions)
    $reply_reactions = $conn->prepare("SELECT COUNT(*) as count FROM feed_reply_reactions WHERE user_id = ?");
    $reply_reactions->bind_param("i", $user_id);
    $reply_reactions->execute();
    $reply_reactions_count = (int)$reply_reactions->get_result()->fetch_assoc()['count'];
    $total_points += $points['forum_reaction'] * $reply_reactions_count;
    
    // 3. Add streak bonus
    $user_query = $conn->prepare("SELECT login_streak FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $streak = (int)$user_query->get_result()->fetch_assoc()['login_streak'];
    $total_points += $points['streak_bonus'] * $streak;
    
    return $total_points;
}

function updateUserPoints($conn, $user_id) {
    $points = calculateUserPoints($conn, $user_id);
    
    // Update points in users table
    $update = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
    $update->bind_param("ii", $points, $user_id);
    $update->execute();
    
    // Update or create ranking entry
    $ranking_check = $conn->prepare("SELECT user_id FROM rankings WHERE user_id = ?");
    $ranking_check->bind_param("i", $user_id);
    $ranking_check->execute();
    
    if ($ranking_check->get_result()->num_rows > 0) {
        // Update existing ranking
        $update_ranking = $conn->prepare("
            UPDATE rankings 
            SET total_points = ?,
                lessons_completed = (SELECT COALESCE(SUM(count), 0) FROM user_activity WHERE user_id = ? AND activity_type = 'lesson'),
                generated_count = (SELECT COALESCE(SUM(count), 0) FROM user_activity WHERE user_id = ? AND activity_type = 'generate')
            WHERE user_id = ?
        ");
        $update_ranking->bind_param("iiii", $points, $user_id, $user_id, $user_id);
        $update_ranking->execute();
    } else {
        // Create new ranking entry
        $lessons = $conn->prepare("SELECT COALESCE(SUM(count), 0) as count FROM user_activity WHERE user_id = ? AND activity_type = 'lesson'");
        $lessons->bind_param("i", $user_id);
        $lessons->execute();
        $lessons_count = (int)$lessons->get_result()->fetch_assoc()['count'];
        
        $generated = $conn->prepare("SELECT COALESCE(SUM(count), 0) as count FROM user_activity WHERE user_id = ? AND activity_type = 'generate'");
        $generated->bind_param("i", $user_id);
        $generated->execute();
        $generated_count = (int)$generated->get_result()->fetch_assoc()['count'];
        
        $insert_ranking = $conn->prepare("
            INSERT INTO rankings (user_id, total_points, lessons_completed, generated_count)
            VALUES (?, ?, ?, ?)
        ");
        $insert_ranking->bind_param("iiii", $user_id, $points, $lessons_count, $generated_count);
        $insert_ranking->execute();
    }
    
    return $points;
}

// Function to update all users' points (for admin/cron use)
function updateAllUsersPoints($conn) {
    $users = $conn->query("SELECT id FROM users WHERE role = 'student'");
    $updated = 0;
    
    while ($user = $users->fetch_assoc()) {
        updateUserPoints($conn, $user['id']);
        $updated++;
    }
    
    return $updated;
}
?>
