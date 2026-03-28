<?php
// student/forum.php  — Newsfeed-style community forum
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// ── Current user ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$_SESSION['full_name']       = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

// ── Upload directory ─────────────────────────────────────────
define('FEED_UPLOAD_DIR', '../assets/uploads/forum/');
define('FEED_UPLOAD_URL', '../assets/uploads/forum/');
if (!file_exists(FEED_UPLOAD_DIR)) mkdir(FEED_UPLOAD_DIR, 0755, true);

// ── Image upload helper ──────────────────────────────────────
function uploadFeedImage(array $file, string &$err): ?string {
    $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    $maxSize = 8 * 1024 * 1024;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($file['type'], $allowed))  { $err = 'Only JPG, PNG, GIF or WEBP allowed.'; return null; }
    if ($file['size'] > $maxSize)            { $err = 'Image must be under 8 MB.'; return null; }
    if (!@getimagesize($file['tmp_name']))   { $err = 'File is not a valid image.'; return null; }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = 'feed_' . uniqid() . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], FEED_UPLOAD_DIR . $name)) {
        $err = 'Upload failed — check server write permissions.';
        return null;
    }
    return $name;
}

// ── Time-ago helper ──────────────────────────────────────────
function ago(string $date): string {
    $d = time() - strtotime($date);
    if ($d <    60) return 'just now';
    if ($d <  3600) return floor($d/60).'m';
    if ($d < 86400) return floor($d/3600).'h';
    if ($d < 604800) return floor($d/86400).'d';
    return date('M j', strtotime($date));
}

// ── JSON response helper (AJAX) ──────────────────────────────
function jsonOut(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// ════════════════════════════════════════════════════════════
//  AJAX + POST HANDLERS
// ════════════════════════════════════════════════════════════
$ajax = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
$err  = '';

// ── 1. Create post ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create_post') {
    $caption  = sanitize($conn, trim((!empty($_POST['caption']) ? $_POST['caption'] : '')));
    $category = sanitize($conn, trim((!empty($_POST['category']) ? $_POST['category'] : 'General')));
    $images   = [];

    // Handle multiple image uploads
    if (!empty($_FILES['post_images']['name'][0])) {
        $fileCount = count($_FILES['post_images']['name']);
        if ($fileCount > 5) jsonOut(['ok'=>false,'error'=>'Maximum 5 images allowed.']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $_FILES['post_images']['name'][$i],
                'type' => $_FILES['post_images']['type'][$i],
                'tmp_name' => $_FILES['post_images']['tmp_name'][$i],
                'error' => $_FILES['post_images']['error'][$i],
                'size' => $_FILES['post_images']['size'][$i]
            ];
            $uploaded = uploadFeedImage($file, $err);
            if ($err) jsonOut(['ok'=>false,'error'=>$err]);
            if ($uploaded) $images[] = $uploaded;
        }
    }
    
    if (empty($caption) && empty($images)) jsonOut(['ok'=>false,'error'=>'Add a caption or attach images.']);
    
    $imagesJson = !empty($images) ? json_encode($images) : null;
    $st = $conn->prepare("INSERT INTO feed_posts (user_id,caption,image_path,category) VALUES (?,?,?,?)");
    $st->bind_param("isss", $user_id, $caption, $imagesJson, $category);
    if ($st->execute()) {
        $pid   = (int)$conn->insert_id;
        $today = date('Y-m-d');
        $al    = $conn->prepare("INSERT INTO user_activity (user_id,activity_date,activity_type,count) VALUES (?,?,'lesson_view',1) ON DUPLICATE KEY UPDATE count=count+1");
        $al->bind_param("is",$user_id,$today); $al->execute();
        jsonOut(['ok'=>true,'post_id'=>$pid]);
    }
    jsonOut(['ok'=>false,'error'=>'Failed to create post.']);
}

// ── 2. Toggle post reaction ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='toggle_post_reaction') {
    $pid = (int)$_POST['post_id'];
    $reaction = sanitize($conn, $_POST['reaction_type']);
    $allowed = ['like','haha','thumbs_up','angry','wow'];
    if (!in_array($reaction, $allowed)) jsonOut(['ok'=>false,'error'=>'Invalid reaction']);
    
    $post_owner = $conn->prepare("SELECT user_id FROM feed_posts WHERE id = ?");
    $post_owner->bind_param("i", $pid);
    $post_owner->execute();
    $post_owner_id = $post_owner->get_result()->fetch_assoc()['user_id'];
    
    $chk = $conn->prepare("SELECT reaction_type FROM feed_post_reactions WHERE post_id=? AND user_id=?");
    $chk->bind_param("ii",$pid,$user_id); $chk->execute(); $res = $chk->get_result();
    
    if ($res->num_rows > 0) {
        $old = $res->fetch_assoc()['reaction_type'];
        if ($old === $reaction) {
            $del = $conn->prepare("DELETE FROM feed_post_reactions WHERE post_id=? AND user_id=?");
            $del->bind_param("ii",$pid,$user_id); $del->execute();
            $query = "UPDATE feed_posts SET {$reaction}_count=GREATEST(0,{$reaction}_count-1) WHERE id=?";
            $upd = $conn->prepare($query);
            $upd->bind_param("i",$pid); $upd->execute();
        } else {
            $upd1 = $conn->prepare("UPDATE feed_post_reactions SET reaction_type=? WHERE post_id=? AND user_id=?");
            $upd1->bind_param("sii",$reaction,$pid,$user_id); $upd1->execute();
            $query2 = "UPDATE feed_posts SET {$old}_count=GREATEST(0,{$old}_count-1), {$reaction}_count={$reaction}_count+1 WHERE id=?";
            $upd2 = $conn->prepare($query2);
            $upd2->bind_param("i",$pid); $upd2->execute();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO feed_post_reactions (post_id,user_id,reaction_type) VALUES (?,?,?)");
        $ins->bind_param("iis",$pid,$user_id,$reaction); $ins->execute();
        $query = "UPDATE feed_posts SET {$reaction}_count={$reaction}_count+1 WHERE id=?";
        $upd = $conn->prepare($query);
        $upd->bind_param("i",$pid); $upd->execute();
        
        if ($post_owner_id && $post_owner_id != $user_id) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, message, related_id, related_type) VALUES (?, ?, 'reaction', '{sender} reacted to your post', ?, 'post')");
            $notif_stmt->bind_param("iii", $post_owner_id, $user_id, $pid);
            $notif_stmt->execute();
        }
    }
    
    // Fetch updated reaction data
    $fetch_post = $conn->prepare("SELECT like_count,haha_count,thumbs_up_count,angry_count,wow_count FROM feed_posts WHERE id=?");
    $fetch_post->bind_param("i",$pid); $fetch_post->execute();
    $post = $fetch_post->get_result()->fetch_assoc();
    $fetch_my = $conn->prepare("SELECT reaction_type FROM feed_post_reactions WHERE post_id=? AND user_id=?");
    $fetch_my->bind_param("ii",$pid,$user_id); $fetch_my->execute();
    $myReaction = $fetch_my->get_result()->fetch_assoc();
    
    jsonOut(['ok'=>true,'reactions'=>$post,'my_reaction'=>$myReaction['reaction_type']??null]);
}

// ── 3. Post comment ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='post_comment') {
    $pid     = (int)$_POST['post_id'];
    $content = sanitize($conn, trim((!empty($_POST['content']) ? $_POST['content'] : '')));
    if (empty($content)) jsonOut(['ok'=>false,'error'=>'Comment cannot be empty.']);
    
    // Get the post owner to notify them
    $post_owner = $conn->prepare("SELECT user_id FROM feed_posts WHERE id = ?");
    $post_owner->bind_param("i", $pid);
    $post_owner->execute();
    $post_owner_id = $post_owner->get_result()->fetch_assoc()['user_id'];
    
    $st = $conn->prepare("INSERT INTO feed_comments (post_id,user_id,content) VALUES (?,?,?)");
    $st->bind_param("iis",$pid,$user_id,$content);
    if ($st->execute()) {
        $cid = (int)$conn->insert_id;
        $upd = $conn->prepare("UPDATE feed_posts SET comment_count=comment_count+1 WHERE id=?");
        $upd->bind_param("i",$pid); $upd->execute();
        
        // Create notification if commenting on someone else's post
        if ($post_owner_id && $post_owner_id != $user_id) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, message, related_id, related_type) VALUES (?, ?, 'comment', '{sender} commented on your post', ?, 'post')");
            $notif_stmt->bind_param("iii", $post_owner_id, $user_id, $pid);
            $notif_stmt->execute();
        }
        
        // Fetch the newly created comment with user data
        $fetch_comment = $conn->prepare("
            SELECT fc.*, u.full_name, u.profile_picture, COALESCE(u.avatar_color,'#4f46e5') AS avatar_color, u.role
            FROM feed_comments fc
            JOIN users u ON fc.user_id=u.id
            WHERE fc.id=?
        ");
        $fetch_comment->bind_param("i", $cid);
        $fetch_comment->execute();
        $comment_data = $fetch_comment->get_result()->fetch_assoc();
        $comment_data['replies'] = [];
        
        // Return rendered HTML of new comment
        $fetch_cnt = $conn->prepare("SELECT comment_count FROM feed_posts WHERE id=?");
        $fetch_cnt->bind_param("i",$pid); $fetch_cnt->execute();
        $new_cnt = (int)$fetch_cnt->get_result()->fetch_assoc()['comment_count'];
        jsonOut(['ok'=>true,'comment_id'=>$cid,'comment_count'=>$new_cnt,
            'html'=>renderComment($comment_data, $user_id)]);
    }
    jsonOut(['ok'=>false,'error'=>'Failed to post comment.']);
}

// ── 4. Reply to comment ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='post_reply') {
    $cid     = (int)$_POST['comment_id'];
    $content = sanitize($conn, trim((!empty($_POST['content']) ? $_POST['content'] : '')));
    if (empty($content)) jsonOut(['ok'=>false,'error'=>'Reply cannot be empty.']);
    
    // Get the comment owner to notify them
    $comment_owner = $conn->prepare("SELECT user_id FROM feed_comments WHERE id = ?");
    $comment_owner->bind_param("i", $cid);
    $comment_owner->execute();
    $comment_owner_id = $comment_owner->get_result()->fetch_assoc()['user_id'];
    
    $st = $conn->prepare("INSERT INTO feed_replies (comment_id,user_id,content) VALUES (?,?,?)");
    $st->bind_param("iis",$cid,$user_id,$content);
    if ($st->execute()) {
        $rid = (int)$conn->insert_id;
        
        // Create notification if replying to someone else's comment
        if ($comment_owner_id && $comment_owner_id != $user_id) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, message, related_id, related_type) VALUES (?, ?, 'reply', '{sender} replied to your comment', ?, 'comment')");
            $notif_stmt->bind_param("iii", $comment_owner_id, $user_id, $cid);
            $notif_stmt->execute();
        }
        
        // Fetch the newly created reply with user data
        $fetch_reply = $conn->prepare("
            SELECT fr.*, u.full_name, u.profile_picture, COALESCE(u.avatar_color,'#4f46e5') AS avatar_color, u.role
            FROM feed_replies fr
            JOIN users u ON fr.user_id=u.id
            WHERE fr.id=?
        ");
        $fetch_reply->bind_param("i", $rid);
        $fetch_reply->execute();
        $reply_data = $fetch_reply->get_result()->fetch_assoc();
        
        jsonOut(['ok'=>true,'reply_id'=>$rid,
            'html'=>renderReply($reply_data, $user_id, $cid)]);
    }
    jsonOut(['ok'=>false,'error'=>'Failed to post reply.']);
}

// ── 5. Delete post ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_post') {
    $pid = (int)$_POST['post_id'];
    $row = $conn->prepare("SELECT image_path FROM feed_posts WHERE id=? AND user_id=?");
    $row->bind_param("ii",$pid,$user_id); $row->execute();
    $row = $row->get_result()->fetch_assoc();
    if ($row) {
        // Delete multiple images if they exist
        if ($row['image_path']) {
            $images = json_decode($row['image_path'], true);
            if (is_array($images)) {
                foreach ($images as $img) {
                    if (file_exists(FEED_UPLOAD_DIR.$img)) unlink(FEED_UPLOAD_DIR.$img);
                }
            } else if (file_exists(FEED_UPLOAD_DIR.$row['image_path'])) {
                unlink(FEED_UPLOAD_DIR.$row['image_path']);
            }
        }
        $del = $conn->prepare("DELETE FROM feed_posts WHERE id=? AND user_id=?");
        $del->bind_param("ii",$pid,$user_id); $del->execute();
        jsonOut(['ok'=>true]);
    }
    jsonOut(['ok'=>false,'error'=>'Not found or not your post.']);
}

// ── 6. Delete comment ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_comment') {
    $cid = (int)$_POST['comment_id'];
    $pid = (int)$_POST['post_id'];
    $del = $conn->prepare("DELETE FROM feed_comments WHERE id=? AND user_id=?");
    $del->bind_param("ii",$cid,$user_id); $del->execute();
    if ($del->affected_rows > 0) {
        $upd = $conn->prepare("UPDATE feed_posts SET comment_count=GREATEST(0,comment_count-1) WHERE id=?");
        $upd->bind_param("i",$pid); $upd->execute();
        $fetch = $conn->prepare("SELECT comment_count FROM feed_posts WHERE id=?");
        $fetch->bind_param("i",$pid); $fetch->execute();
        $new_cnt = (int)$fetch->get_result()->fetch_assoc()['comment_count'];
        jsonOut(['ok'=>true,'comment_count'=>$new_cnt]);
    }
    jsonOut(['ok'=>false]);
}

// ── 7. Delete reply ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_reply') {
    $rid = (int)$_POST['reply_id'];
    $del = $conn->prepare("DELETE FROM feed_replies WHERE id=? AND user_id=?");
    $del->bind_param("ii",$rid,$user_id); $del->execute();
    jsonOut(['ok'=>$del->affected_rows>0]);
}

// ── 8. Toggle reply like ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='toggle_reply_reaction') {
    $rid = (int)$_POST['reply_id'];
    $reaction = sanitize($conn, $_POST['reaction_type']);
    $allowed = ['like','haha','thumbs_up','angry','wow'];
    if (!in_array($reaction, $allowed)) jsonOut(['ok'=>false,'error'=>'Invalid reaction']);
    
    $reply_owner = $conn->prepare("SELECT user_id FROM feed_replies WHERE id = ?");
    $reply_owner->bind_param("i", $rid);
    $reply_owner->execute();
    $reply_owner_id = $reply_owner->get_result()->fetch_assoc()['user_id'];
    
    $chk = $conn->prepare("SELECT reaction_type FROM feed_reply_reactions WHERE reply_id=? AND user_id=?");
    $chk->bind_param("ii",$rid,$user_id); $chk->execute(); $res = $chk->get_result();
    
    if ($res->num_rows > 0) {
        $old = $res->fetch_assoc()['reaction_type'];
        if ($old === $reaction) {
            $del = $conn->prepare("DELETE FROM feed_reply_reactions WHERE reply_id=? AND user_id=?");
            $del->bind_param("ii",$rid,$user_id); $del->execute();
            $query = "UPDATE feed_replies SET {$reaction}_count=GREATEST(0,{$reaction}_count-1) WHERE id=?";
            $upd = $conn->prepare($query);
            $upd->bind_param("i",$rid); $upd->execute();
        } else {
            $upd1 = $conn->prepare("UPDATE feed_reply_reactions SET reaction_type=? WHERE reply_id=? AND user_id=?");
            $upd1->bind_param("sii",$reaction,$rid,$user_id); $upd1->execute();
            $query2 = "UPDATE feed_replies SET {$old}_count=GREATEST(0,{$old}_count-1), {$reaction}_count={$reaction}_count+1 WHERE id=?";
            $upd2 = $conn->prepare($query2);
            $upd2->bind_param("i",$rid); $upd2->execute();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO feed_reply_reactions (reply_id,user_id,reaction_type) VALUES (?,?,?)");
        $ins->bind_param("iis",$rid,$user_id,$reaction); $ins->execute();
        $query = "UPDATE feed_replies SET {$reaction}_count={$reaction}_count+1 WHERE id=?";
        $upd = $conn->prepare($query);
        $upd->bind_param("i",$rid); $upd->execute();
        
        if ($reply_owner_id && $reply_owner_id != $user_id) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, message, related_id, related_type) VALUES (?, ?, 'reaction', '{sender} reacted to your reply', ?, 'reply')");
            $notif_stmt->bind_param("iii", $reply_owner_id, $user_id, $rid);
            $notif_stmt->execute();
        }
    }
    
    // Fetch updated reaction data
    $fetch_reply = $conn->prepare("SELECT like_count,haha_count,thumbs_up_count,angry_count,wow_count FROM feed_replies WHERE id=?");
    $fetch_reply->bind_param("i",$rid); $fetch_reply->execute();
    $reply = $fetch_reply->get_result()->fetch_assoc();
    $fetch_my = $conn->prepare("SELECT reaction_type FROM feed_reply_reactions WHERE reply_id=? AND user_id=?");
    $fetch_my->bind_param("ii",$rid,$user_id); $fetch_my->execute();
    $myReaction = $fetch_my->get_result()->fetch_assoc();
    
    jsonOut(['ok'=>true,'reactions'=>$reply,'my_reaction'=>$myReaction['reaction_type']??null]);
}


// ── 9. Load more posts (pagination) ─────────────────────────
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['load_more'])) {
    $offset = (int)((isset($_GET['offset']) ? (int)$_GET['offset'] : 0));
    $posts  = fetchPosts($conn, $user_id, $offset, 5);
    $html   = '';
    foreach ($posts as $p) $html .= renderPost($p, $user_id);
    jsonOut(['ok'=>true,'html'=>$html,'has_more'=>count($posts)===5]);
}

// ════════════════════════════════════════════════════════════
//  RENDER HELPERS (return HTML strings for AJAX + initial load)
// ════════════════════════════════════════════════════════════
function fetchPosts(mysqli $conn, int $user_id, int $offset=0, int $limit=10): array {
    $stmt = $conn->prepare("
        SELECT fp.*,
               u.full_name, u.profile_picture, COALESCE(u.avatar_color,'#4f46e5') AS avatar_color, u.role,
               pr.reaction_type as my_reaction
        FROM feed_posts fp
        JOIN users u ON fp.user_id=u.id
        LEFT JOIN feed_post_reactions pr ON fp.id=pr.post_id AND pr.user_id=?
        ORDER BY fp.is_pinned DESC, fp.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii",$user_id,$limit,$offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $posts = [];
    while ($r = $res->fetch_assoc()) $posts[] = $r;
    return $posts;
}

function fetchComments(mysqli $conn, int $post_id, int $user_id): array {
    $stmt = $conn->prepare("
        SELECT fc.*, u.full_name, u.profile_picture, COALESCE(u.avatar_color,'#4f46e5') AS avatar_color, u.role
        FROM feed_comments fc
        JOIN users u ON fc.user_id=u.id
        WHERE fc.post_id=?
        ORDER BY fc.created_at ASC
    ");
    $stmt->bind_param("i",$post_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $comments = [];
    while ($r = $res->fetch_assoc()) {
        $r['replies'] = fetchReplies($conn, $r['id'], $user_id);
        $comments[] = $r;
    }
    return $comments;
}

function fetchReplies(mysqli $conn, int $comment_id, int $user_id): array {
    $stmt = $conn->prepare("
        SELECT fr.*, u.full_name, u.profile_picture, COALESCE(u.avatar_color,'#4f46e5') AS avatar_color, u.role,
               rr.reaction_type as my_reaction
        FROM feed_replies fr
        JOIN users u ON fr.user_id=u.id
        LEFT JOIN feed_reply_reactions rr ON fr.id=rr.reply_id AND rr.user_id=?
        WHERE fr.comment_id=?
        ORDER BY fr.created_at ASC
    ");
    $stmt->bind_param("ii",$user_id,$comment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $replies = [];
    while ($r = $res->fetch_assoc()) $replies[] = $r;
    return $replies;
}

function renderReply(array $r, int $me, int $comment_id): string {
    $aclr = htmlspecialchars((!empty($r['avatar_color']) ? $r['avatar_color'] : '#3b82f6'));
    $init = strtoupper(substr($r['full_name'],0,1));
    $name = htmlspecialchars($r['full_name']);
    $role = ucfirst((!empty($r['role']) ? $r['role'] : 'student'));
    $body = nl2br(htmlspecialchars($r['content']));
    $time = ago($r['created_at']);
    $rid  = (int)$r['id'];
    $mine = ((int)$r['user_id'] === $me);
    $del  = $mine ? "<button class='del-reply-btn action-micro' data-id='$rid' title='Delete'><i class='fas fa-trash'></i></button>" : '';
    
    $myReaction = $r['my_reaction'] ?? '';
    $reactions = [
        'like' => ['icon'=>'fa-heart', 'count'=>(int)($r['like_count']??0)],
        'haha' => ['icon'=>'fa-laugh', 'count'=>(int)($r['haha_count']??0)],
        'thumbs_up' => ['icon'=>'fa-thumbs-up', 'count'=>(int)($r['thumbs_up_count']??0)],
        'angry' => ['icon'=>'fa-angry', 'count'=>(int)($r['angry_count']??0)],
        'wow' => ['icon'=>'fa-surprise', 'count'=>(int)($r['wow_count']??0)]
    ];
    
    // Get top 3 reactions by count
    $sortedReactions = $reactions;
    uasort($sortedReactions, function($a, $b) { return $b['count'] - $a['count']; });
    $topReactions = array_slice($sortedReactions, 0, 3, true);
    
    $totalCount = array_sum(array_column($reactions, 'count'));
    $mainIcon = $myReaction ? $reactions[$myReaction]['icon'] : 'fa-heart';
    $mainActive = $myReaction ? 'active' : '';
    $countText = $totalCount > 0 ? $totalCount : '';
    
    // Build top reactions display
    $topReactionsHtml = '';
    if ($totalCount > 0) {
        foreach ($topReactions as $type => $data) {
            if ($data['count'] > 0) {
                $topReactionsHtml .= "<i class='fas {$data['icon']} top-reaction-icon' data-type='$type'></i>";
            }
        }
    }
    
    $avatarHtml = '';
    if (!empty($r['profile_picture'])) {
        $picPath = '../assets/uploads/profiles/' . htmlspecialchars($r['profile_picture']);
        $avatarHtml = "<div class='reply-av'><img src='$picPath' alt='$name' style='width:100%;height:100%;object-fit:cover;border-radius:50%'></div>";
    } else {
        $avatarHtml = "<div class='reply-av' style='background:$aclr'>$init</div>";
    }
    
    return "
    <div class='reply-item' id='reply-$rid'>
      $avatarHtml
      <div class='reply-right'>
        <div class='reply-bubble'>
          <div class='reply-meta'><span class='reply-name'>$name</span><span class='reply-role'>$role</span><span class='reply-time'>$time</span></div>
          <div class='reply-text'>$body</div>
        </div>
        <div class='reply-actions'>
          <div class='reaction-wrapper'>
            <button class='main-reaction-btn $mainActive' data-rid='$rid' data-current='$myReaction'>
              <span class='top-reactions'>$topReactionsHtml</span>
              <span class='reaction-count'>$countText</span>
            </button>
            <div class='reaction-picker' data-rid='$rid'>
              <button class='picker-reaction' data-reaction='like'><i class='fas fa-heart'></i></button>
              <button class='picker-reaction' data-reaction='haha'><i class='fas fa-laugh'></i></button>
              <button class='picker-reaction' data-reaction='thumbs_up'><i class='fas fa-thumbs-up'></i></button>
              <button class='picker-reaction' data-reaction='angry'><i class='fas fa-angry'></i></button>
              <button class='picker-reaction' data-reaction='wow'><i class='fas fa-surprise'></i></button>
            </div>
          </div>
          <button class='toggle-reply-btn action-link' data-cid='$comment_id'><i class='fas fa-reply'></i> Reply</button>
          $del
        </div>
      </div>
    </div>";
}

function renderComment(array $c, int $me): string {
    $aclr     = htmlspecialchars((!empty($c['avatar_color']) ? $c['avatar_color'] : '#3b82f6'));
    $init     = strtoupper(substr($c['full_name'],0,1));
    $name     = htmlspecialchars($c['full_name']);
    $role     = ucfirst((!empty($c['role']) ? $c['role'] : 'student'));
    $body     = nl2br(htmlspecialchars($c['content']));
    $time     = ago($c['created_at']);
    $cid      = (int)$c['id'];
    $mine     = ((int)$c['user_id'] === $me);
    $del      = $mine ? "<button class='del-comment-btn action-micro' data-id='$cid' data-post-id='{$c['post_id']}' title='Delete'><i class='fas fa-trash'></i></button>" : '';
    
    // Avatar: use profile picture if available, else colored initial
    $avatarHtml = '';
    if (!empty($c['profile_picture'])) {
        $picPath = '../assets/uploads/profiles/' . htmlspecialchars($c['profile_picture']);
        $avatarHtml = "<div class='comment-av'><img src='$picPath' alt='$name' style='width:100%;height:100%;object-fit:cover;border-radius:50%'></div>";
    } else {
        $avatarHtml = "<div class='comment-av' style='background:$aclr'>$init</div>";
    }
    
    $replies  = '';
    $replyCount = count($c['replies']);
    if (!empty($c['replies'])) foreach ($c['replies'] as $rep) $replies .= renderReply($rep, $me, $cid);
    
    // Show/hide replies button
    $toggleRepliesBtn = '';
    if ($replyCount > 0) {
        $toggleRepliesBtn = "<button class='toggle-replies-btn action-link' data-cid='$cid'><i class='fas fa-chevron-down'></i> <span class='replies-toggle-text'>Show $replyCount " . ($replyCount === 1 ? 'reply' : 'replies') . "</span></button>";
    }
    
    // Current user avatar for reply input
    $gUser = $GLOBALS['user'];
    $meReplyAvHtml = '';
    if (!empty($gUser['profile_picture'])) {
        $mePicPath = '../assets/uploads/profiles/' . htmlspecialchars($gUser['profile_picture']);
        $meReplyAvHtml = "<div class='reply-av'><img src='$mePicPath' alt='Me' style='width:100%;height:100%;object-fit:cover;border-radius:50%'></div>";
    } else {
        $meReplyAv = htmlspecialchars($gUser['avatar_color'] ? $gUser['avatar_color'] : '#3b82f6');
        $meReplyInit = strtoupper(substr($gUser['full_name'],0,1));
        $meReplyAvHtml = "<div class='reply-av' style='background:$meReplyAv'>$meReplyInit</div>";
    }
    
    return "
    <div class='comment-item' id='comment-$cid'>
      $avatarHtml
      <div class='comment-right'>
        <div class='comment-bubble'>
          <div class='comment-meta'><span class='comment-name'>$name</span><span class='comment-role'>$role</span><span class='comment-time'>$time</span></div>
          <div class='comment-text'>$body</div>
        </div>
        <div class='comment-actions'>
          <button class='toggle-reply-btn action-link' data-cid='$cid'><i class='fas fa-reply'></i> Reply</button>
          $toggleRepliesBtn
          $del
        </div>
        <div class='replies-wrap' id='replies-$cid' style='display:none'>$replies</div>
        <div class='reply-form-wrap' id='reply-form-$cid' style='display:none'>
          <div class='inline-reply-form'>
            $meReplyAvHtml
            <input class='reply-input' type='text' placeholder='Write a reply…' data-cid='$cid'>
            <button class='submit-reply-btn btn-send' data-cid='$cid'><i class='fas fa-paper-plane'></i></button>
          </div>
        </div>
      </div>
    </div>";
}

function renderPost(array $p, int $me): string {
    global $conn;
    $aclr    = htmlspecialchars((!empty($p['avatar_color']) ? $p['avatar_color'] : '#3b82f6'));
    $init    = strtoupper(substr($p['full_name'],0,1));
    $name    = htmlspecialchars($p['full_name']);
    $role    = ucfirst((!empty($p['role']) ? $p['role'] : 'student'));
    $time    = ago($p['created_at']);
    $pid     = (int)$p['id'];
    $caption = nl2br(htmlspecialchars((!empty($p['caption']) ? $p['caption'] : '')));
    $comments= (int)$p['comment_count'];
    $cat     = htmlspecialchars((!empty($p['category']) ? $p['category'] : 'General'));
    $mine    = ((int)$p['user_id'] === $me);
    $pinBadge= $p['is_pinned'] ? "<span class='pin-badge'><i class='fas fa-thumbtack'></i> Pinned</span>" : '';
    $delBtn  = $mine ? "<button class='del-post-btn post-menu-btn' data-id='$pid'><i class='fas fa-trash'></i> Delete</button>" : '';
    
    $myReaction = $p['my_reaction'] ?? '';
    $reactions = [
        'like' => ['icon'=>'fa-heart', 'count'=>(int)($p['like_count']??0)],
        'haha' => ['icon'=>'fa-laugh', 'count'=>(int)($p['haha_count']??0)],
        'thumbs_up' => ['icon'=>'fa-thumbs-up', 'count'=>(int)($p['thumbs_up_count']??0)],
        'angry' => ['icon'=>'fa-angry', 'count'=>(int)($p['angry_count']??0)],
        'wow' => ['icon'=>'fa-surprise', 'count'=>(int)($p['wow_count']??0)]
    ];
    
    $sortedReactions = $reactions;
    uasort($sortedReactions, function($a, $b) { return $b['count'] - $a['count']; });
    $topReactions = array_slice($sortedReactions, 0, 3, true);
    
    $totalCount = array_sum(array_column($reactions, 'count'));
    $mainActive = $myReaction ? 'active' : '';
    $countText = $totalCount > 0 ? $totalCount : '';
    
    $topReactionsHtml = '';
    if ($totalCount > 0) {
        foreach ($topReactions as $type => $data) {
            if ($data['count'] > 0) {
                $topReactionsHtml .= "<i class='fas {$data['icon']} top-reaction-icon' data-type='$type'></i>";
            }
        }
    } else {
        $topReactionsHtml = "<i class='fas fa-heart' style='color:var(--text3)'></i>";
    }
    
    // Avatar: use profile picture if available, else colored initial
    $avatarHtml = '';
    if (!empty($p['profile_picture'])) {
        $picPath = '../assets/uploads/profiles/' . htmlspecialchars($p['profile_picture']);
        $avatarHtml = "<div class='post-av'><img src='$picPath' alt='$name' style='width:100%;height:100%;object-fit:cover;border-radius:50%'></div>";
    } else {
        $avatarHtml = "<div class='post-av' style='background:$aclr'>$init</div>";
    }
    
    $imgHtml = '';
    if (!empty($p['image_path'])) {
        $images = json_decode($p['image_path'], true);
        if (is_array($images) && count($images) > 0) {
            $gridClass = count($images) === 1 ? 'grid-1' : (count($images) === 2 ? 'grid-2' : 'grid-multi');
            $imgHtml = "<div class='post-img-grid $gridClass'>";
            foreach ($images as $img) {
                $src = FEED_UPLOAD_URL . htmlspecialchars($img);
                $imgHtml .= "<img class='post-img' src='$src' alt='Image' onclick='openLightbox(this.src)'>";
            }
            $imgHtml .= "</div>";
        } else {
            // Backward compatibility for old single image format
            $src = FEED_UPLOAD_URL . htmlspecialchars($p['image_path']);
            $imgHtml = "<div class='post-img-wrap'><img class='post-img' src='$src' alt='Image' onclick='openLightbox(this.src)'></div>";
        }
    }

    // Fetch comments
    $comments_html = '';
    $crows = fetchComments($conn, $pid, $me);
    foreach ($crows as $c) $comments_html .= renderComment($c, $me);

    $gUser  = $GLOBALS['user'];
    
    // Current user avatar for comment input
    $meAvHtml = '';
    if (!empty($gUser['profile_picture'])) {
        $mePicPath = '../assets/uploads/profiles/' . htmlspecialchars($gUser['profile_picture']);
        $meAvHtml = "<div class='comment-av'><img src='$mePicPath' alt='Me' style='width:100%;height:100%;object-fit:cover;border-radius:50%'></div>";
    } else {
        $meAv = htmlspecialchars(!empty($gUser['avatar_color']) ? $gUser['avatar_color'] : '#3b82f6');
        $meInit = strtoupper(substr($gUser['full_name'],0,1));
        $meAvHtml = "<div class='comment-av' style='background:$meAv'>$meInit</div>";
    }

    return "
<div class='feed-post' id='post-$pid' data-pid='$pid'>
  <div class='post-header'>
    $avatarHtml
    <div class='post-header-info'>
      <div class='post-author'>$name <span class='post-role'>$role</span> $pinBadge</div>
      <div class='post-time'><span class='cat-pill'>$cat</span> · $time</div>
    </div>
    <div class='post-menu'>$delBtn</div>
  </div>
  " . (!empty($p['caption']) ? "<div class='post-caption'>$caption</div>" : '') . "
  $imgHtml
  <div class='post-footer'>
    <div class='reaction-wrapper'>
      <button class='main-reaction-btn post-react-btn $mainActive' data-pid='$pid' data-current='$myReaction'>
        <span class='top-reactions'>$topReactionsHtml</span>
        <span class='reaction-count'>$countText</span>
      </button>
      <div class='reaction-picker' data-pid='$pid'>
        <button class='picker-reaction' data-reaction='like'><i class='fas fa-heart'></i></button>
        <button class='picker-reaction' data-reaction='haha'><i class='fas fa-laugh'></i></button>
        <button class='picker-reaction' data-reaction='thumbs_up'><i class='fas fa-thumbs-up'></i></button>
        <button class='picker-reaction' data-reaction='angry'><i class='fas fa-angry'></i></button>
        <button class='picker-reaction' data-reaction='wow'><i class='fas fa-surprise'></i></button>
      </div>
    </div>
    <button class='toggle-comments-btn' data-pid='$pid'>
      <i class='far fa-comment'></i>
      <span class='comment-count'>$comments</span>
    </button>
  </div>
  <div class='post-comments' id='comments-$pid' style='display:none'>
    <div class='comments-list' id='comments-list-$pid'>$comments_html</div>
    <div class='comment-input-row'>
      $meAvHtml
      <input class='comment-input' type='text' placeholder='Write a comment…' data-pid='$pid'>
      <button class='submit-comment-btn btn-send' data-pid='$pid'><i class='fas fa-paper-plane'></i></button>
    </div>
  </div>
</div>";
}

// ── Initial page data ────────────────────────────────────────
$initial_posts = fetchPosts($conn, $user_id, 0, 10);
$has_more      = count($initial_posts) === 10;
$categories    = ['General','Mathematics','Science','Computer Science','History','Literature','Help & Support'];

// Fetch latest announcement from admin
$announcement = null;
try {
    $ann_query = $conn->query("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 1");
    if ($ann_query && $ann_query->num_rows > 0) {
        $announcement = $ann_query->fetch_assoc();
    }
} catch (Exception $e) {
    // Table doesn't exist - use static data for visuals
    $announcement = [
        'title' => 'Welcome to the Community Forum!',
        'content' => 'Feel free to ask questions, share your problems, and help each other. Remember to be respectful and follow the community guidelines. Happy learning!',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forum — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07080f;--bg2:#0c0e1a;--bg3:#111320;
  --surface:#161929;--surface2:#1c2135;
  --border:rgba(255,255,255,.06);--border-hi:rgba(255,255,255,.12);
  --blue:#3b82f6;--blue-dim:rgba(59,130,246,.14);
  --violet:#8b5cf6;--violet-dim:rgba(139,92,246,.13);
  --emerald:#10b981;--emerald-dim:rgba(16,185,129,.13);
  --amber:#f59e0b;--amber-dim:rgba(245,158,11,.12);
  --rose:#f43f5e;--rose-dim:rgba(244,63,94,.12);
  --cyan:#06b6d4;--cyan-dim:rgba(6,182,212,.12);
  --text:#dde2f0;--text2:#8892aa;--text3:#4a5270;
  --sidebar-w:252px;--topbar-h:66px;
  --radius:14px;--radius-sm:9px;
  --ease:cubic-bezier(.4,0,.2,1);
}
html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ── Shell ── */
.shell{display:flex;min-height:100vh}

/* ── Sidebar ── */
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200;transition:transform .3s var(--ease)}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--cyan-dim),transparent);color:var(--cyan);border-left:2px solid var(--cyan);padding-left:12px}
.nav-link.active i{color:var(--cyan)}
.sidebar-footer{padding:14px 8px;border-top:1px solid var(--border)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--surface)}
.user-chip-av{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet))}
.user-chip-name{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-chip-role{font-size:.66rem;color:var(--text3)}

/* ── Main + Topbar ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar-h);display:flex;align-items:center;padding:0 28px;gap:14px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.hamburger{display:none;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);width:36px;height:36px;place-items:center;color:var(--text2);cursor:pointer;font-size:.95rem}
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.icon-btn{width:36px;height:36px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);display:grid;place-items:center;color:var(--text2);cursor:pointer;transition:all .18s var(--ease);text-decoration:none;font-size:.88rem;position:relative}
.icon-btn:hover{border-color:var(--border-hi);color:var(--text)}
.icon-btn.active{background:var(--cyan-dim);color:var(--cyan);border-color:var(--cyan)}
#toggleFilterBtn{display:none}
.notif-badge{position:absolute;top:-4px;right:-4px;width:16px;height:16px;border-radius:50%;background:var(--rose);color:#fff;font-size:.6rem;font-weight:700;display:grid;place-items:center;border:2px solid var(--bg2);font-family:'JetBrains Mono',monospace}
.user-pill{display:flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;border-radius:30px;background:var(--surface);border:1px solid var(--border);cursor:pointer;text-decoration:none;transition:border-color .18s var(--ease)}
.user-pill:hover{border-color:var(--border-hi)}
.pill-av{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet))}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ── Page ── */
.page{flex:1;padding:24px 28px 24px 30px;display:grid;grid-template-columns:580px 280px 280px;gap:20px;align-items:start;justify-content:flex-start}

/* ════════════════════════════════════════
   FEED COLUMN
════════════════════════════════════════ */
.feed-col{display:flex;flex-direction:column;gap:14px}

/* ── Create-post card ── */
.create-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:18px 20px;
  animation:slideUp .4s var(--ease) both;
}
.create-top{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.create-av{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;font-size:.85rem;font-weight:700;color:#fff;flex-shrink:0}
.create-trigger{
  flex:1;padding:10px 14px;background:var(--bg3);
  border:1.5px solid var(--border);border-radius:25px;
  font-family:'Outfit',sans-serif;font-size:.875rem;color:var(--text2);
  cursor:pointer;text-align:left;transition:border-color .18s var(--ease);
}
.create-trigger:hover{border-color:var(--border-hi);color:var(--text)}

/* Create-post expanded form */
.create-form{display:none}
.create-form.open{display:block}
.create-textarea{
  width:100%;min-height:80px;padding:12px 14px;
  background:var(--bg3);border:1.5px solid var(--border);
  border-radius:var(--radius-sm);resize:vertical;
  font-family:'Outfit',sans-serif;font-size:.9rem;color:var(--text);
  outline:none;transition:border-color .18s var(--ease);
  margin-bottom:12px;
}
.create-textarea::placeholder{color:rgba(136,146,170,.45)}
.create-textarea:focus{border-color:var(--cyan)}

/* Image drop zone */
.drop-zone{
  border:2px dashed var(--border-hi);border-radius:var(--radius-sm);
  padding:18px;text-align:center;cursor:pointer;
  transition:all .2s var(--ease);position:relative;overflow:hidden;
  background:var(--bg3);margin-bottom:12px;
}
.drop-zone:hover,.drop-zone.drag-over{border-color:var(--cyan);background:var(--cyan-dim)}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.drop-icon{font-size:1.5rem;margin-bottom:6px}
.drop-text{font-size:.8rem;color:var(--text2)}<br>.drop-hint{font-size:.68rem;color:var(--text3);margin-top:3px}

/* Image preview grid */
.img-preview-grid{display:none;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:12px}
.img-preview-grid.show{display:grid}
.img-preview-item{position:relative;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);aspect-ratio:1}
.img-preview-item img{width:100%;height:100%;object-fit:cover;display:block}
.img-preview-item .remove-img{position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;background:rgba(244,63,94,.9);color:#fff;border:none;display:grid;place-items:center;cursor:pointer;font-size:.7rem;transition:transform .15s}
.img-preview-item .remove-img:hover{transform:scale(1.1);background:rgba(244,63,94,1)}

/* Post image grid */
.post-img-grid{display:grid;gap:4px;background:var(--bg3);overflow:hidden}
.post-img-grid.grid-1{grid-template-columns:1fr}
.post-img-grid.grid-2{grid-template-columns:repeat(2,1fr)}
.post-img-grid.grid-multi{grid-template-columns:repeat(3,1fr)}
.post-img-grid .post-img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in;transition:opacity .2s;aspect-ratio:1}
.post-img-grid .post-img:hover{opacity:.9}
.create-bottom{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.cat-select{padding:7px 12px;border-radius:var(--radius-sm);background:var(--bg3);border:1px solid var(--border);font-family:'Outfit',sans-serif;font-size:.8rem;color:var(--text2);outline:none;cursor:pointer}
.cat-select option{background:var(--bg2)}
.btn-post{
  margin-left:auto;display:inline-flex;align-items:center;gap:7px;
  padding:9px 20px;border-radius:var(--radius-sm);
  background:var(--cyan);color:#fff;border:none;
  font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:700;
  cursor:pointer;transition:all .18s var(--ease);
}
.btn-post:hover{background:#0891b2;transform:translateY(-1px)}
.btn-post:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* ── Feed post ── */
.feed-post{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  animation:slideUp .4s var(--ease) both;
  transition:border-color .2s var(--ease);
  margin-bottom: 10px;
}
.feed-post:hover{border-color:var(--border-hi)}

/* ── Announcement card ── */
.announcement-card{
  background:linear-gradient(135deg,var(--amber-dim),var(--rose-dim));
  border:1px solid var(--amber);
  border-radius:var(--radius);padding:18px 20px;
  animation:slideUp .4s var(--ease) .1s both;
  position:relative;overflow:hidden;
}
.announcement-header{
  display:flex;align-items:center;gap:10px;margin-bottom:10px;
}
.announcement-icon{
  width:32px;height:32px;border-radius:50%;
  background:var(--amber);color:#fff;
  display:grid;place-items:center;font-size:.9rem;
  flex-shrink:0;
}
.announcement-title{
  font-size:.95rem;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:8px;
}
.announcement-badge{
  font-size:.65rem;font-weight:700;padding:2px 8px;
  border-radius:10px;background:var(--amber);color:#fff;
  text-transform:uppercase;letter-spacing:.05em;
}
.announcement-body{
  font-size:.88rem;color:var(--text2);line-height:1.7;
  margin-bottom:8px;position:relative;z-index:1;
}
.announcement-footer{
  font-size:.72rem;color:var(--text3);
  display:flex;align-items:center;gap:6px;
}
.announcement-footer i{color:var(--amber)}

/* ── Feedback card ── */
.feedback-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:18px 20px;
  animation:slideUp .4s var(--ease) .15s both;
  max-height:320px;overflow-y:auto;
}
.feedback-header{
  display:flex;align-items:center;gap:10px;margin-bottom:14px;
  padding-top: 20px; margin-top: 0px;
  padding-bottom:12px;border-bottom:1px solid var(--border);
  position:sticky;top:0;background:var(--surface);z-index:1;
}
.feedback-icon{
  width:32px;height:32px;border-radius:50%;
  background:var(--blue);color:#fff;
  display:grid;place-items:center;font-size:.9rem;
  flex-shrink:0;
}
.feedback-title{
  font-size:.95rem;font-weight:700;color:var(--text);
}
.feedback-item{
  padding:12px 14px;background:var(--bg3);
  border-radius:var(--radius-sm);margin-bottom:10px;
  border-left:3px solid var(--blue);
}
.feedback-item:last-child{margin-bottom:0}
.feedback-meta{
  display:flex;align-items:center;gap:8px;margin-bottom:6px;
}
.feedback-author{
  font-size:.75rem;font-weight:600;color:var(--text3);
  display:flex;align-items:center;gap:5px;
}
.feedback-author i{color:var(--blue);font-size:.7rem}
.feedback-time{
  font-size:.68rem;color:var(--text3);margin-left:auto;
  font-family:'JetBrains Mono',monospace;
}
.feedback-text{
  font-size:.84rem;color:var(--text2);line-height:1.6;
}

/* Post header */
.post-header{display:flex;align-items:center;gap:12px;padding:16px 18px 12px}
.post-av{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;font-size:.85rem;font-weight:700;color:#fff;flex-shrink:0}
.post-author{font-size:.9rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.post-role{font-size:.68rem;color:var(--text3);text-transform:capitalize;font-weight:400}
.post-time{font-size:.72rem;color:var(--text3);display:flex;align-items:center;gap:6px;margin-top:2px}
.cat-pill{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:10px;background:var(--cyan-dim);color:var(--cyan)}
.pin-badge{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:10px;background:var(--amber-dim);color:var(--amber);display:inline-flex;align-items:center;gap:4px}
.post-menu{margin-left:auto;position:relative}
.post-menu-btn{background:none;border:none;cursor:pointer;color:var(--text3);font-size:.8rem;padding:4px 8px;border-radius:6px;transition:all .18s var(--ease);display:flex;align-items:center;gap:5px}
.post-menu-btn:hover{background:var(--rose-dim);color:var(--rose)}

/* Caption */
.post-caption{padding:0 18px 14px;font-size:.9rem;color:var(--text);line-height:1.7;white-space:pre-wrap;word-break:break-word}

/* Image */
.post-img-wrap{overflow:hidden;background:var(--bg3)}
.post-img{width:100%;max-height:420px;object-fit:cover;display:block;cursor:zoom-in;transition:transform .3s var(--ease)}
.post-img:hover{transform:scale(1.01)}

/* Footer (like + comment counts) */
.post-footer{display:flex;align-items:center;gap:6px;padding:10px 18px;border-top:1px solid var(--border)}
.post-react-btn{background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:6px 14px;font-size:.82rem;cursor:pointer;transition:all .18s var(--ease);display:flex;align-items:center;gap:6px;color:var(--text2);font-family:'Outfit',sans-serif;font-weight:600}
.post-react-btn:hover{background:var(--surface2);border-color:var(--border-hi)}
.post-react-btn.active{background:var(--violet-dim);color:var(--violet);border-color:var(--violet)}
.toggle-comments-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:25px;border:none;background:transparent;color:var(--text2);font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease)}
.toggle-comments-btn:hover{background:var(--blue-dim);color:var(--blue)}

/* ── Comments section ── */
.post-comments{border-top:1px solid var(--border);max-height:500px;display:flex;flex-direction:column;position:relative}
.comments-list{padding:12px 18px 0;display:flex;flex-direction:column;gap:10px;flex:1;overflow-y:auto;max-height:calc(500px - 58px)}

/* Comment */
.comment-item{display:flex;align-items:flex-start;gap:10px}
.comment-av{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0}
.comment-right{flex:1;min-width:0}
.comment-bubble{background:var(--bg3);border-radius:0 var(--radius-sm) var(--radius-sm) var(--radius-sm);padding:10px 13px;border:1px solid var(--border)}
.comment-meta{display:flex;align-items:center;gap:7px;margin-bottom:3px;flex-wrap:wrap}
.comment-name{font-size:.8rem;font-weight:700;color:var(--text)}
.comment-role{font-size:.65rem;color:var(--text3);text-transform:capitalize}
.comment-time{font-size:.65rem;color:var(--text3);margin-left:auto;font-family:'JetBrains Mono',monospace}
.comment-text{font-size:.84rem;color:var(--text2);line-height:1.6;white-space:pre-wrap;word-break:break-word}
.comment-actions{display:flex;align-items:center;gap:10px;margin-top:5px;padding-left:2px}
.action-link{background:none;border:none;color:var(--text3);font-size:.72rem;cursor:pointer;display:flex;align-items:center;gap:4px;transition:color .15s var(--ease);font-family:'Outfit',sans-serif;font-weight:600;padding:2px 0}
.action-link:hover{color:var(--blue)}
.action-link.liked{color:var(--rose)}
.action-link.liked i{animation:heartPop .3s var(--ease)}
.action-micro{background:none;border:none;color:var(--text3);font-size:.68rem;cursor:pointer;transition:color .15s var(--ease);padding:2px 5px;border-radius:4px}
.action-micro:hover{color:var(--rose);background:var(--rose-dim)}

/* Replies */
.replies-wrap{display:flex;flex-direction:column;gap:7px;margin-top:7px;padding-left:14px;border-left:2px solid var(--border)}
.reply-item{display:flex;align-items:flex-start;gap:8px}
.reply-av{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:.62rem;font-weight:700;color:#fff;flex-shrink:0}
.reply-right{flex:1;min-width:0}
.reply-bubble{background:var(--bg3);border-radius:0 var(--radius-sm) var(--radius-sm) var(--radius-sm);padding:8px 11px;border:1px solid var(--border)}
.reply-meta{display:flex;align-items:center;gap:6px;margin-bottom:2px;flex-wrap:wrap}
.reply-name{font-size:.76rem;font-weight:700;color:var(--text)}
.reply-role{font-size:.62rem;color:var(--text3);text-transform:capitalize}
.reply-time{font-size:.62rem;color:var(--text3);margin-left:auto;font-family:'JetBrains Mono',monospace}
.reply-text{font-size:.8rem;color:var(--text2);line-height:1.55;white-space:pre-wrap;word-break:break-word}
.reply-actions{display:flex;align-items:center;gap:10px;margin-top:5px;padding-left:2px;flex-wrap:wrap}
.reaction-wrapper{position:relative;display:inline-block}
.main-reaction-btn{background:var(--bg3);border:1px solid var(--border);border-radius:16px;padding:3px 10px;font-size:.72rem;cursor:pointer;transition:all .18s var(--ease);display:flex;align-items:center;gap:4px;color:var(--text3);font-family:'Outfit',sans-serif;font-weight:600;position:relative}
.main-reaction-btn:hover{background:var(--surface2);border-color:var(--border-hi);transform:scale(1.05)}
.main-reaction-btn.active{background:var(--violet);color:#fff;border-color:var(--violet)}
.main-reaction-btn.active i{animation:reactionPop .3s ease}
.top-reactions{display:flex;gap:2px;align-items:center}
.top-reaction-icon{font-size:.7rem}
.top-reaction-icon[data-type="like"]{color:#f43f5e}
.top-reaction-icon[data-type="haha"]{color:#f59e0b}
.top-reaction-icon[data-type="thumbs_up"]{color:#3b82f6}
.top-reaction-icon[data-type="angry"]{color:#f43f5e}
.top-reaction-icon[data-type="wow"]{color:#f59e0b}
.reaction-picker{position:absolute;bottom:calc(100% + 8px);left:0;transform:scale(0.8);background:var(--surface);border:1px solid var(--border-hi);border-radius:30px;padding:8px;display:flex;gap:6px;opacity:0;pointer-events:none;transition:all .2s var(--ease);box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:100;white-space:nowrap}
.reaction-picker.show{opacity:1;pointer-events:all;transform:scale(1)}
.picker-reaction{width:40px;height:40px;border-radius:50%;background:transparent;border:none;display:grid;place-items:center;font-size:1.2rem;cursor:pointer;transition:all .15s var(--ease);flex-shrink:0}
.picker-reaction:hover{background:var(--bg3);transform:scale(1.15)}
.picker-reaction[data-reaction="like"] i{color:#f43f5e}
.picker-reaction[data-reaction="haha"] i{color:#f59e0b}
.picker-reaction[data-reaction="thumbs_up"] i{color:#3b82f6}
.picker-reaction[data-reaction="angry"] i{color:#f43f5e}
.picker-reaction[data-reaction="wow"] i{color:#f59e0b}
@keyframes reactionPop{0%,100%{transform:scale(1)}50%{transform:scale(1.3)}}

/* Reply input */
.reply-form-wrap{margin-top:8px;padding-left:14px}
.inline-reply-form{display:flex;align-items:center;gap:8px}
.reply-input{flex:1;padding:7px 11px;background:var(--bg3);border:1.5px solid var(--border);border-radius:20px;font-family:'Outfit',sans-serif;font-size:.8rem;color:var(--text);outline:none;transition:border-color .18s var(--ease)}
.reply-input:focus{border-color:var(--cyan)}
.reply-input::placeholder{color:rgba(136,146,170,.4)}

/* Comment input row */
.comment-input-row{display:flex;align-items:center;gap:10px;padding:12px 18px 14px;position:sticky;bottom:0;background:var(--surface);border-top:1px solid var(--border);z-index:10}
.comment-av{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0}
.comment-input{flex:1;padding:9px 14px;background:var(--bg3);border:1.5px solid var(--border);border-radius:25px;font-family:'Outfit',sans-serif;font-size:.84rem;color:var(--text);outline:none;transition:border-color .18s var(--ease)}
.comment-input:focus{border-color:var(--cyan)}
.comment-input::placeholder{color:rgba(136,146,170,.4)}
.btn-send{width:34px;height:34px;border-radius:50%;background:var(--cyan);border:none;display:grid;place-items:center;color:#fff;cursor:pointer;font-size:.8rem;flex-shrink:0;transition:all .18s var(--ease)}
.btn-send:hover{background:#0891b2;transform:scale(1.08)}

/* Load more */
.load-more-btn{width:100%;padding:12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);color:var(--text2);font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease)}
.load-more-btn:hover{border-color:var(--border-hi);color:var(--text)}

/* Toast */
.toast{position:fixed;bottom:28px;right:28px;z-index:9999;background:var(--surface2);border:1px solid var(--border-hi);border-radius:var(--radius-sm);padding:12px 18px;font-size:.84rem;color:var(--text);box-shadow:0 12px 32px rgba(0,0,0,.4);animation:toastIn .3s var(--ease);display:flex;align-items:center;gap:10px;max-width:300px}
.toast.error{border-color:rgba(244,63,94,.3);color:var(--rose)}
.toast.success{border-color:rgba(16,185,129,.25);color:var(--emerald)}
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* Notification Dropdown */
.notif-dropdown{position:absolute;top:calc(100% + 8px);right:0;width:360px;max-height:480px;background:var(--surface);border:1px solid var(--border-hi);border-radius:var(--radius);box-shadow:0 12px 40px rgba(0,0,0,.5);z-index:1000;display:none;flex-direction:column;animation:dropIn .25s var(--ease)}
.notif-dropdown.open{display:flex}
@keyframes dropIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.notif-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.notif-header-title{font-size:.9rem;font-weight:700;color:var(--text)}
.notif-header-count{font-size:.72rem;color:var(--text3);margin-left:6px}
.notif-mark-all{background:none;border:none;color:var(--blue);font-size:.72rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:background .15s}
.notif-mark-all:hover{background:var(--blue-dim)}
.notif-body{flex:1;overflow-y:auto;max-height:380px}
.notif-item-small{display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .15s;position:relative}
.notif-item-small:hover{background:var(--bg3)}
.notif-item-small.unread{background:var(--blue-dim)}
.notif-item-small.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--blue)}
.notif-av-small{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-size:.8rem;font-weight:700;color:#fff;flex-shrink:0}
.notif-content-small{flex:1;min-width:0}
.notif-text-small{font-size:.8rem;color:var(--text);line-height:1.5;margin-bottom:3px}
.notif-text-small strong{color:var(--blue);font-weight:600}
.notif-time-small{font-size:.68rem;color:var(--text3);font-family:'JetBrains Mono',monospace}
.notif-footer{padding:10px 16px;border-top:1px solid var(--border);text-align:center}
.notif-view-all{display:inline-block;padding:6px 12px;font-size:.78rem;font-weight:600;color:var(--blue);text-decoration:none;border-radius:6px;transition:background .15s}
.notif-view-all:hover{background:var(--blue-dim)}
.notif-empty{padding:40px 20px;text-align:center;color:var(--text3)}
.notif-empty i{font-size:2rem;margin-bottom:12px;opacity:.5}

/* ════════════════════════════════════════
   ANNOUNCEMENT COLUMN
════════════════════════════════════════ */
.announcement-col{display:flex;flex-direction:column;gap:16px;position:fixed;top:calc(var(--topbar-h)+24px);left:calc(var(--sidebar-w) + 580px + 70px);width:280px}

/* ════════════════════════════════════════
   RIGHT SIDEBAR
════════════════════════════════════════ */
.right-col{display:flex;flex-direction:column;gap:16px;position:fixed;top:calc(var(--topbar-h)+20px);right:28px;width:280px;transition:transform .3s var(--ease)}
.right-col.mobile-hidden{transform:translateX(100%)}
.widget{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;animation:slideUp .4s var(--ease) .08s both}
.widget-head{padding:14px 18px;border-bottom:1px solid var(--border);font-size:.875rem;font-weight:700;display:flex;align-items:center;gap:8px}
.widget-head i{color:var(--cyan)}
.widget-body{padding:14px 18px}

/* Category filter */
.cat-filter-list{display:flex;flex-direction:column;gap:2px}
.cat-filter-item{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:var(--radius-sm);cursor:pointer;font-size:.83rem;font-weight:500;color:var(--text2);transition:all .18s var(--ease);border:none;background:none;width:100%;text-align:left}
.cat-filter-item:hover{background:var(--bg3);color:var(--text)}
.cat-filter-item.active{background:var(--cyan-dim);color:var(--cyan)}
.cfi-count{font-family:'JetBrains Mono',monospace;font-size:.7rem;background:var(--bg3);padding:1px 7px;border-radius:10px;color:var(--text3)}

/* Rules */
.rule-item{display:flex;gap:8px;font-size:.8rem;color:var(--text2);line-height:1.5;margin-bottom:8px}
.rule-item:last-child{margin-bottom:0}

/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.93);align-items:center;justify-content:center;padding:20px}
.lightbox.open{display:flex}
.lightbox img{max-width:90vw;max-height:88vh;border-radius:var(--radius-sm);box-shadow:0 20px 60px rgba(0,0,0,.6)}
.lightbox-close{position:absolute;top:20px;right:24px;background:none;border:none;color:rgba(255,255,255,.6);font-size:1.8rem;cursor:pointer;transition:color .15s}
.lightbox-close:hover{color:#fff}

/* Animations */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* Responsive */
@media(max-width:960px){.page{grid-template-columns:1fr;justify-items:center}.right-col,.announcement-col{width:100%;max-width:580px;position:static}}
@media(max-width:768px){
  #toggleFilterBtn{display:grid}
  .right-col{position:fixed;top:var(--topbar-h);right:0;bottom:0;width:280px;background:var(--bg2);border-left:1px solid var(--border);z-index:201;padding:20px;overflow-y:auto;transform:translateX(100%)}
  .right-col.mobile-open{transform:translateX(0)}
}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}.main{margin-left:0}.hamburger{display:grid}
  .page{padding:16px 14px}.topbar{padding:0 14px}
}
</style>
</head>
<body>
<div class="shell">

<!-- ════════════ SIDEBAR ════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text"><?php echo SITE_NAME; ?><span>Student Portal</span></div>
  </div>
  <div class="sidebar-nav">
    <div class="nav-group-label">Main</div>
    <a href="dashboard.php" class="nav-link"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <a href="generate.php"  class="nav-link"><i class="fas fa-wand-magic-sparkles"></i> Generate</a>
    <a href="lessons.php"   class="nav-link"><i class="fas fa-book-open"></i> Lessons</a>
    <a href="favorites.php" class="nav-link"><i class="fas fa-heart"></i> Favorites</a>
    <div class="nav-group-label">Community</div>
    <a href="rankings.php"  class="nav-link"><i class="fas fa-trophy"></i> Rankings</a>
    <a href="forum.php"     class="nav-link active"><i class="fas fa-comments"></i> Forum</a>
    <div class="nav-group-label">Account</div>
    <a href="profile.php"   class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>
  <div class="sidebar-footer">
    <div class="user-chip">
      <?php if (!empty($user['profile_picture'])): ?>
      <div class="user-chip-av"><img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
      <?php else: ?>
      <div class="user-chip-av"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
      <?php endif; ?>
      <div>
        <div class="user-chip-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="user-chip-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="overlay" id="overlay"></div>

<!-- ════════════ MAIN ════════════ -->
<div class="main">
  <header class="topbar">
    <button class="hamburger" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
      <div class="topbar-title">Community Forum</div>
      <div class="topbar-sub">Share problems, ask questions, help each other</div>
    </div>
    <div class="topbar-right">
      <button class="icon-btn" title="AI Assistant" style="background:linear-gradient(135deg,var(--violet),var(--blue));color:#fff;border:none">
        <i class="fas fa-robot"></i>
      </button>
      <button class="icon-btn" id="toggleFilterBtn" title="Toggle Filters"><i class="fas fa-filter"></i></button>
      <div style="position:relative">
        <button class="icon-btn" id="notifBtn" title="Notifications"><i class="fas fa-bell"></i><span class="notif-badge" id="notifBadge" style="display:none">0</span></button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">
            <div><span class="notif-header-title">Notifications</span><span class="notif-header-count" id="notifCount">(0)</span></div>
            <button class="notif-mark-all" id="markAllNotif" style="display:none" onclick="markAllNotificationsRead()">Mark all read</button>
          </div>
          <div class="notif-body" id="notifBody">
            <div class="notif-empty"><i class="fas fa-bell-slash"></i><div>No notifications</div></div>
          </div>
          <div class="notif-footer">
            <a href="notifications.php" class="notif-view-all">View all notifications</a>
          </div>
        </div>
      </div>
      <a href="profile.php" class="user-pill">
        <?php if (!empty($user['profile_picture'])): ?>
        <div class="pill-av"><img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
        <?php else: ?>
        <div class="pill-av"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
        <?php endif; ?>
        <span class="pill-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
      </a>
    </div>
  </header>

  <div class="page">

    <!-- ═══ FEED COLUMN ═══ -->
    <div class="feed-col" id="feedCol">

      <!-- Create post card -->
      <div class="create-card">
        <div class="create-top">
          <?php if (!empty($user['profile_picture'])): ?>
          <div class="create-av"><img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
          <?php else: ?>
          <div class="create-av" style="background:<?php echo htmlspecialchars((!empty($user['avatar_color']) ? $user['avatar_color'] : '#3b82f6')) ?>">
            <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
          </div>
          <?php endif; ?>
          <button class="create-trigger" id="createTrigger">
            What problem are you facing today, <?php echo htmlspecialchars(explode(' ',$user['full_name'])[0]); ?>?
          </button>
        </div>

        <div class="create-form" id="createForm">
          <textarea class="create-textarea" id="createCaption" placeholder="Describe your problem or question… (optional if you're uploading a screenshot)"></textarea>

          <!-- Drop zone -->
          <div class="drop-zone" id="dropZone">
            <input type="file" id="postImageFile" accept="image/*" multiple onchange="previewPostImages(this)">
            <!-- <div class="drop-icon">📸</div> -->
            <div class="drop-text">Click or drag & drop screenshots</div>
            <div class="drop-hint">JPG · PNG · GIF · WEBP &nbsp;|&nbsp; Max 5 images, 8 MB each</div>
          </div>

      
          <div class="img-preview-grid" id="imgPreviewGrid"></div>

          <div class="create-bottom">
            <select class="cat-select" id="postCategory">
              <?php foreach($categories as $cat): ?>
              <option value="<?php echo $cat ?>"><?php echo $cat ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="cancelPost" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:.8rem;font-family:inherit">Cancel</button>
            <button class="btn-post" id="submitPostBtn" onclick="submitPost()">
              <i class="fas fa-paper-plane"></i> Post
            </button>
          </div>
        </div>
      </div>

      <!-- Posts feed -->
      <div id="feedPosts">
        <?php foreach($initial_posts as $p) echo renderPost($p, $user_id); ?>
      </div>

      <!-- Load more -->
      <?php if($has_more): ?>
      <button class="load-more-btn" id="loadMoreBtn" data-offset="10" onclick="loadMore()">
        <i class="fas fa-chevron-down"></i> Load more posts
      </button>
      <?php endif; ?>

    </div>

    <!-- ═══ ANNOUNCEMENT COLUMN ═══ -->
    <div class="announcement-col">

      <!-- Announcement card -->
      <?php if ($announcement): ?>
      <div class="announcement-card">
        <div class="announcement-header">
          <div class="announcement-icon"><i class="fas fa-bullhorn"></i></div>
          <div class="announcement-title">
            <span><?php echo htmlspecialchars($announcement['title']); ?></span>
            <span class="announcement-badge">Admin</span>
          </div>
        </div>
        <div class="announcement-body">
          <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
        </div>
        <div class="announcement-footer">
          <i class="fas fa-clock"></i>
          <span><?php echo ago($announcement['created_at']); ?></span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Feedback card -->
      <div class="feedback-card">
        <div class="feedback-header">
          <div class="feedback-icon"><i class="fas fa-comment-dots"></i></div>
          <div class="feedback-title">Community Feedback</div>
        </div>
        <div class="feedback-list">
          <div class="feedback-item">
            <div class="feedback-meta">
              <div class="feedback-author"><i class="fas fa-user-secret"></i> Anonymous</div>
              <div class="feedback-time">2h</div>
            </div>
            <div class="feedback-text">The AI study planner has been incredibly helpful! It adapts to my learning pace perfectly.</div>
          </div>
          <div class="feedback-item">
            <div class="feedback-meta">
              <div class="feedback-author"><i class="fas fa-user-secret"></i> Anonymous</div>
              <div class="feedback-time">5h</div>
            </div>
            <div class="feedback-text">Would love to see more interactive quizzes in the lessons. Overall great platform!</div>
          </div>
          <div class="feedback-item">
            <div class="feedback-meta">
              <div class="feedback-author"><i class="fas fa-user-secret"></i> Anonymous</div>
              <div class="feedback-time">1d</div>
            </div>
            <div class="feedback-text">The forum community is very supportive. Thanks to everyone who helped me with calculus!</div>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══ RIGHT SIDEBAR ═══ -->
    <div class="right-col">

      <!-- Category filter -->
      <div class="widget">
        <div class="widget-head"><i class="fas fa-layer-group"></i> Filter by Category</div>
        <div class="widget-body">
          <div class="cat-filter-list">
            <button class="cat-filter-item active" data-cat="all" onclick="filterFeed('all',this)">
              <span><i class="fas fa-border-all" style="width:14px;text-align:center;margin-right:6px"></i>All Posts</span>
            </button>
            <?php foreach($categories as $cat): ?>
            <button class="cat-filter-item" data-cat="<?php echo $cat ?>" onclick="filterFeed('<?php echo $cat ?>',this)">
              <span><i class="fas fa-tag" style="width:14px;text-align:center;margin-right:6px"></i><?php echo $cat ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Tips -->
      <div class="widget">
        <div class="widget-head"><i class="fas fa-lightbulb"></i> Tips for Better Help</div>
        <div class="widget-body">
          <div class="rule-item"><span style="color:var(--emerald);flex-shrink:0">✓</span> Include a screenshot for clearer context</div>
          <div class="rule-item"><span style="color:var(--emerald);flex-shrink:0">✓</span> Describe what you've already tried</div>
          <div class="rule-item"><span style="color:var(--emerald);flex-shrink:0">✓</span> Be specific — more detail = better answers</div>
          <div class="rule-item"><span style="color:var(--emerald);flex-shrink:0">✓</span> Reply to thank those who help you</div>
        </div>
      </div>

    </div>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<?php include '../includes/ai_chat.php'; ?>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="if(event.target===this)closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-xmark"></i></button>
  <img id="lightboxImg" src="" alt="Screenshot">
</div>

<script>
const ME    = <?php echo $user_id; ?>;
const meAv  = <?php echo json_encode((!empty($user['avatar_color']) ? $user['avatar_color'] : '#3b82f6')); ?>;
const meInit= <?php echo json_encode(strtoupper(substr($user['full_name'],0,1))); ?>;

// ── Toast ──────────────────────────────────────────────────
function toast(msg, type='success') {
  document.querySelectorAll('.toast').forEach(t => t.remove());
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = (type==='success'?'<i class="fas fa-circle-check"></i>':'<i class="fas fa-circle-exclamation"></i>') + msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ── AJAX helper ────────────────────────────────────────────
async function api(formData) {
  const r = await fetch('forum.php', {
    method:'POST',
    headers:{'X-Requested-With':'XMLHttpRequest'},
    body: formData
  });
  return r.json();
}

// ── Create-post UI ─────────────────────────────────────────
const createForm    = document.getElementById('createForm');
const createTrigger = document.getElementById('createTrigger');
const cancelPost    = document.getElementById('cancelPost');

createTrigger.addEventListener('click', () => {
  createForm.classList.add('open');
  createTrigger.style.display = 'none';
  document.getElementById('createCaption').focus();
});
cancelPost.addEventListener('click', () => {
  createForm.classList.remove('open');
  createTrigger.style.display = '';
  clearPostImages();
  document.getElementById('createCaption').value = '';
});

// ── Drag-and-drop ──────────────────────────────────────────
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
  if (files.length > 0) {
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    document.getElementById('postImageFile').files = dt.files;
    previewPostImages(document.getElementById('postImageFile'));
  }
});

// ── Image preview (multiple) ────────────────────────────────
function previewPostImages(input) {
  const files = input.files;
  if (!files || files.length === 0) return;
  
  if (files.length > 5) {
    toast('Maximum 5 images allowed', 'error');
    input.value = '';
    return;
  }
  
  const grid = document.getElementById('imgPreviewGrid');
  grid.innerHTML = '';
  grid.classList.add('show');
  dropZone.style.display = 'none';
  
  Array.from(files).forEach((file, idx) => {
    const reader = new FileReader();
    reader.onload = e => {
      const item = document.createElement('div');
      item.className = 'img-preview-item';
      item.innerHTML = `
        <img src="${e.target.result}" alt="Preview ${idx+1}">
        <button type="button" class="remove-img" onclick="removePreviewImage(${idx})"><i class="fas fa-xmark"></i></button>
      `;
      grid.appendChild(item);
    };
    reader.readAsDataURL(file);
  });
}

function removePreviewImage(idx) {
  const input = document.getElementById('postImageFile');
  const dt = new DataTransfer();
  const files = Array.from(input.files);
  files.splice(idx, 1);
  files.forEach(f => dt.items.add(f));
  input.files = dt.files;
  
  if (files.length === 0) {
    clearPostImages();
  } else {
    previewPostImages(input);
  }
}

function clearPostImages() {
  document.getElementById('postImageFile').value = '';
  document.getElementById('imgPreviewGrid').innerHTML = '';
  document.getElementById('imgPreviewGrid').classList.remove('show');
  dropZone.style.display = '';
}

// ── Image preview ──────────────────────────────────────────
function previewPostImage(input) {
  const file = input.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('imgPreviewEl').src = e.target.result;
    document.getElementById('imgPreviewBox').classList.add('show');
    dropZone.style.display = 'none';
  };
  reader.readAsDataURL(file);
}
function clearPostImage() {
  document.getElementById('postImageFile').value = '';
  document.getElementById('imgPreviewEl').src = '';
  document.getElementById('imgPreviewBox').classList.remove('show');
  dropZone.style.display = '';
}

// ── Submit post ────────────────────────────────────────────
async function submitPost() {
  const btn     = document.getElementById('submitPostBtn');
  const caption = document.getElementById('createCaption').value.trim();
  const files   = document.getElementById('postImageFile').files;
  const cat     = document.getElementById('postCategory').value;

  if (!caption && files.length === 0) { toast('Add a caption or images.','error'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting…';

  const fd = new FormData();
  fd.append('action', 'create_post');
  fd.append('caption', caption);
  fd.append('category', cat);
  
  for (let i = 0; i < files.length; i++) {
    fd.append('post_images[]', files[i]);
  }

  const data = await api(fd);
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-paper-plane"></i> Post';

  if (data.ok) {
    location.reload();
  } else {
    toast(data.error || 'Failed to post.', 'error');
  }
}

document.addEventListener('mousedown', e => {
  const postBtn = e.target.closest('.post-react-btn');
  if (postBtn) {
    longPressTimer = setTimeout(() => {
      const pid = postBtn.dataset.pid;
      const picker = document.querySelector(`.reaction-picker[data-pid="${pid}"]`);
      if (picker) {
        document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
        picker.classList.add('show');
      }
    }, 500);
  }
});

document.addEventListener('touchstart', e => {
  const postBtn = e.target.closest('.post-react-btn');
  if (postBtn) {
    longPressTimer = setTimeout(() => {
      const pid = postBtn.dataset.pid;
      const picker = document.querySelector(`.reaction-picker[data-pid="${pid}"]`);
      if (picker) {
        document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
        picker.classList.add('show');
      }
    }, 500);
  }
});

document.addEventListener('click', async e => {
  const postBtn = e.target.closest('.post-react-btn');
  if (postBtn) {
    const pid = postBtn.dataset.pid;
    const picker = document.querySelector(`.reaction-picker[data-pid="${pid}"]`);
    if (picker && picker.classList.contains('show')) return;
    
    const currentReaction = postBtn.dataset.current || 'like';
    const fd = new FormData();
    fd.append('action','toggle_post_reaction');
    fd.append('post_id', pid);
    fd.append('reaction_type', currentReaction);
    
    const d = await api(fd);
    if (d.ok) updatePostReactionUI(pid, d.reactions, d.my_reaction);
  }
});

// ── Like ───────────────────────────────────────────────────

// ── Toggle comments ────────────────────────────────────────
document.addEventListener('click', e => {
  const btn = e.target.closest('.toggle-comments-btn');
  if (!btn) return;
  const pid = btn.dataset.pid;
  const sec = document.getElementById('comments-' + pid);
  if (sec) {
    const open = sec.style.display !== 'none' && sec.style.display !== '';
    sec.style.display = open ? 'none' : 'block';
    if (!open) sec.querySelector('.comment-input')?.focus();
  }
});

// ── Post comment ───────────────────────────────────────────
document.addEventListener('keydown', async e => {
  if (e.key !== 'Enter') return;
  const inp = e.target.closest('.comment-input');
  if (!inp) return;
  const pid     = inp.dataset.pid;
  const content = inp.value.trim();
  if (!content) return;
  inp.value = '';
  const fd = new FormData();
  fd.append('action','post_comment'); fd.append('post_id',pid); fd.append('content',content);
  const d = await api(fd);
  if (d.ok) {
    document.getElementById('comments-list-' + pid).insertAdjacentHTML('beforeend', d.html);
    document.querySelector('#post-' + pid + ' .comment-count').textContent = d.comment_count;
    bindNewElements();
  } else toast(d.error||'Error','error');
});

document.addEventListener('click', async e => {
  const btn = e.target.closest('.submit-comment-btn');
  if (!btn) return;
  const pid = btn.dataset.pid;
  const inp = document.querySelector(`.comment-input[data-pid="${pid}"]`);
  const content = inp?.value.trim();
  if (!content) return;
  inp.value = '';
  const fd = new FormData();
  fd.append('action','post_comment'); fd.append('post_id',pid); fd.append('content',content);
  const d = await api(fd);
  if (d.ok) {
    document.getElementById('comments-list-' + pid).insertAdjacentHTML('beforeend', d.html);
    document.querySelector('#post-' + pid + ' .comment-count').textContent = d.comment_count;
    bindNewElements();
  } else toast(d.error||'Error','error');
});

// ── Toggle replies visibility ──────────────────────────────
document.addEventListener('click', e => {
  const btn = e.target.closest('.toggle-replies-btn');
  if (!btn) return;
  const cid = btn.dataset.cid;
  const repliesWrap = document.getElementById('replies-' + cid);
  if (repliesWrap) {
    const isHidden = repliesWrap.style.display === 'none';
    repliesWrap.style.display = isHidden ? 'flex' : 'none';
    const icon = btn.querySelector('i');
    const text = btn.querySelector('.replies-toggle-text');
    if (isHidden) {
      icon.className = 'fas fa-chevron-up';
      text.textContent = 'Hide replies';
    } else {
      icon.className = 'fas fa-chevron-down';
      const count = repliesWrap.querySelectorAll('.reply-item').length;
      text.textContent = `Show ${count} ${count === 1 ? 'reply' : 'replies'}`;
    }
  }
});

// ── Toggle reply form ──────────────────────────────────────
document.addEventListener('click', e => {
  const btn = e.target.closest('.toggle-reply-btn');
  if (!btn) return;
  const cid  = btn.dataset.cid;
  const form = document.getElementById('reply-form-' + cid);
  if (form) {
    const open = form.style.display !== 'none';
    form.style.display = open ? 'none' : 'block';
    if (!open) form.querySelector('.reply-input')?.focus();
  }
});

// ── Post reply ─────────────────────────────────────────────
async function postReply(cid) {
  const inp     = document.querySelector(`.reply-input[data-cid="${cid}"]`);
  const content = inp?.value.trim();
  if (!content) return;
  inp.value = '';
  const fd = new FormData();
  fd.append('action','post_reply'); fd.append('comment_id',cid); fd.append('content',content);
  const d = await api(fd);
  if (d.ok) {
    const wrap = document.getElementById('replies-' + cid);
    if (wrap) {
      wrap.insertAdjacentHTML('beforeend', d.html);
      wrap.style.display = 'flex';
      // Update toggle button
      const toggleBtn = document.querySelector(`.toggle-replies-btn[data-cid="${cid}"]`);
      if (toggleBtn) {
        const count = wrap.querySelectorAll('.reply-item').length;
        toggleBtn.querySelector('i').className = 'fas fa-chevron-up';
        toggleBtn.querySelector('.replies-toggle-text').textContent = 'Hide replies';
      } else {
        // First reply - add toggle button
        const actionsDiv = document.querySelector(`#comment-${cid} .comment-actions`);
        const replyBtn = actionsDiv.querySelector('.toggle-reply-btn');
        const toggleHTML = `<button class='toggle-replies-btn action-link' data-cid='${cid}'><i class='fas fa-chevron-up'></i> <span class='replies-toggle-text'>Hide replies</span></button>`;
        replyBtn.insertAdjacentHTML('afterend', toggleHTML);
      }
    }
    document.getElementById('reply-form-' + cid).style.display = 'none';
    bindNewElements();
  } else toast(d.error||'Error','error');
}

document.addEventListener('keydown', e => {
  if (e.key !== 'Enter') return;
  const inp = e.target.closest('.reply-input');
  if (inp) postReply(inp.dataset.cid);
});
document.addEventListener('click', e => {
  const btn = e.target.closest('.submit-reply-btn');
  if (btn) postReply(btn.dataset.cid);
});

// ── Delete post ────────────────────────────────────────────
document.addEventListener('click', async e => {
  const btn = e.target.closest('.del-post-btn');
  if (!btn) return;
  if (!confirm('Delete this post?')) return;
  const pid = btn.dataset.id;
  const fd  = new FormData();
  fd.append('action','delete_post'); fd.append('post_id',pid);
  const d = await api(fd);
  if (d.ok) {
    document.getElementById('post-' + pid)?.remove();
    toast('Post deleted.');
  }
});

// ── Delete comment ─────────────────────────────────────────
document.addEventListener('click', async e => {
  const btn = e.target.closest('.del-comment-btn');
  if (!btn) return;
  if (!confirm('Delete this comment?')) return;
  const cid = btn.dataset.id;
  const pid = btn.dataset.postId;
  const fd  = new FormData();
  fd.append('action','delete_comment'); fd.append('comment_id',cid); fd.append('post_id',pid);
  const d = await api(fd);
  if (d.ok) {
    document.getElementById('comment-' + cid)?.remove();
    if (pid) document.querySelector('#post-' + pid + ' .comment-count').textContent = d.comment_count;
    toast('Comment removed.');
  }
});

// ── Delete reply ───────────────────────────────────────────
document.addEventListener('click', async e => {
  const btn = e.target.closest('.del-reply-btn');
  if (!btn) return;
  if (!confirm('Delete this reply?')) return;
  const rid = btn.dataset.id;
  const fd  = new FormData();
  fd.append('action','delete_reply'); fd.append('reply_id',rid);
  const d = await api(fd);
  if (d.ok) { document.getElementById('reply-' + rid)?.remove(); toast('Reply removed.'); }
});

// ── React to reply (long press) ───────────────────────────
let longPressTimer = null;

document.addEventListener('mousedown', e => {
  const btn = e.target.closest('.main-reaction-btn');
  if (!btn) return;
  longPressTimer = setTimeout(() => {
    const rid = btn.dataset.rid;
    const picker = document.querySelector(`.reaction-picker[data-rid="${rid}"]`);
    if (picker) {
      document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
      picker.classList.add('show');
    }
  }, 500);
});

document.addEventListener('mouseup', () => {
  if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }
});

document.addEventListener('touchstart', e => {
  const btn = e.target.closest('.main-reaction-btn');
  if (!btn) return;
  longPressTimer = setTimeout(() => {
    const rid = btn.dataset.rid;
    const picker = document.querySelector(`.reaction-picker[data-rid="${rid}"]`);
    if (picker) {
      document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
      picker.classList.add('show');
    }
  }, 500);
});

document.addEventListener('touchend', () => {
  if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }
});

// Quick click = toggle current/default reaction
document.addEventListener('click', async e => {
  const btn = e.target.closest('.main-reaction-btn');
  if (!btn) return;
  
  const rid = btn.dataset.rid;
  const picker = document.querySelector(`.reaction-picker[data-rid="${rid}"]`);
  if (picker && picker.classList.contains('show')) return;
  
  const currentReaction = btn.dataset.current || 'like';
  const fd = new FormData();
  fd.append('action','toggle_reply_reaction');
  fd.append('reply_id', rid);
  fd.append('reaction_type', currentReaction);
  
  const d = await api(fd);
  if (d.ok) {
    updateReplyReactionUI(rid, d);
  }
});

function updateReplyReactionUI(rid, data) {
  const btn = document.querySelector(`.main-reaction-btn[data-rid="${rid}"]`);
  if (!btn) return;
  
  const reactionIcons = {
    like: 'fa-heart',
    haha: 'fa-laugh',
    thumbs_up: 'fa-thumbs-up',
    angry: 'fa-angry',
    wow: 'fa-surprise'
  };
  
  const reactions = data.reactions;
  const myReaction = data.my_reaction;
  
  const reactionCounts = [
    {type: 'like', count: parseInt(reactions.like_count) || 0},
    {type: 'haha', count: parseInt(reactions.haha_count) || 0},
    {type: 'thumbs_up', count: parseInt(reactions.thumbs_up_count) || 0},
    {type: 'angry', count: parseInt(reactions.angry_count) || 0},
    {type: 'wow', count: parseInt(reactions.wow_count) || 0}
  ];
  
  reactionCounts.sort((a, b) => b.count - a.count);
  const topReactions = reactionCounts.slice(0, 3).filter(r => r.count > 0);
  const totalCount = reactionCounts.reduce((sum, r) => sum + r.count, 0);
  
  const topReactionsHtml = topReactions.length > 0 
    ? topReactions.map(r => `<i class='fas ${reactionIcons[r.type]} top-reaction-icon' data-type='${r.type}'></i>`).join('')
    : "<i class='fas fa-heart' style='color:var(--text3)'></i>";
  
  btn.querySelector('.top-reactions').innerHTML = topReactionsHtml;
  btn.querySelector('.reaction-count').textContent = totalCount > 0 ? totalCount : '';
  btn.dataset.current = myReaction || '';
  
  if (myReaction) {
    btn.classList.add('active');
  } else {
    btn.classList.remove('active');
  }
}

// Picker reaction selection
document.addEventListener('click', async e => {
  const pickerBtn = e.target.closest('.picker-reaction');
  if (!pickerBtn) {
    if (!e.target.closest('.reaction-picker') && !e.target.closest('.main-reaction-btn')) {
      document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
    }
    return;
  }
  
  const reaction = pickerBtn.dataset.reaction;
  const picker = pickerBtn.closest('.reaction-picker');
  const pid = picker.dataset.pid;
  const rid = picker.dataset.rid;
  
  if (pid) {
    const fd = new FormData();
    fd.append('action','toggle_post_reaction');
    fd.append('post_id', pid);
    fd.append('reaction_type', reaction);
    const d = await api(fd);
    if (d.ok) { picker.classList.remove('show'); updatePostReactionUI(pid, d.reactions, d.my_reaction); }
  } else if (rid) {
    const fd = new FormData();
    fd.append('action','toggle_reply_reaction');
    fd.append('reply_id', rid);
    fd.append('reaction_type', reaction);
    const d = await api(fd);
    if (d.ok) { 
      picker.classList.remove('show');
      updateReplyReactionUI(rid, d);
    }
  }
});

// ── Load more ──────────────────────────────────────────────
async function loadMore() {
  const btn    = document.getElementById('loadMoreBtn');
  const offset = parseInt(btn.dataset.offset);
  btn.textContent = 'Loading…';
  btn.disabled = true;
  const r = await fetch(`forum.php?load_more=1&offset=${offset}`, {
    headers:{'X-Requested-With':'XMLHttpRequest'}
  });
  const d = await r.json();
  if (d.ok) {
    document.getElementById('feedPosts').insertAdjacentHTML('beforeend', d.html);
    btn.dataset.offset = offset + 5;
    if (!d.has_more) btn.remove();
    else { btn.textContent = 'Load more posts'; btn.disabled = false; }
    bindNewElements();
  }
}

// ── Category filter ────────────────────────────────────────
function filterFeed(cat, btn) {
  document.querySelectorAll('.cat-filter-item').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.feed-post').forEach(p => {
    const postCat = p.querySelector('.cat-pill')?.textContent.trim();
    p.style.display = (cat === 'all' || postCat === cat) ? '' : 'none';
  });
}

// ── Lightbox ───────────────────────────────────────────────
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key==='Escape') closeLightbox(); });

// ── Mobile sidebar ─────────────────────────────────────────
document.getElementById('menuBtn')?.addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
});
document.getElementById('overlay').addEventListener('click', () => {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  rightCol.classList.remove('mobile-open');
});

// ── Mobile filter toggle ───────────────────────────────────
const toggleFilterBtn = document.getElementById('toggleFilterBtn');
const rightCol = document.querySelector('.right-col');

toggleFilterBtn?.addEventListener('click', () => {
  rightCol.classList.toggle('mobile-open');
  document.getElementById('overlay').classList.toggle('open');
  toggleFilterBtn.classList.toggle('active');
});

// ── Notification dropdown ──────────────────────────────────
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
let notifOpen = false;

notifBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  notifOpen = !notifOpen;
  notifDropdown.classList.toggle('open', notifOpen);
  if (notifOpen) loadNotifications();
});

document.addEventListener('click', (e) => {
  if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
    notifOpen = false;
    notifDropdown.classList.remove('open');
  }
});

async function loadNotifications() {
  try {
    const r = await fetch('get_notifications.php');
    const data = await r.json();
    
    const badge = document.getElementById('notifBadge');
    const count = document.getElementById('notifCount');
    const body = document.getElementById('notifBody');
    const markAll = document.getElementById('markAllNotif');
    
    if (data.unread > 0) {
      badge.textContent = data.unread > 9 ? '9+' : data.unread;
      badge.style.display = 'grid';
      markAll.style.display = 'block';
    } else {
      badge.style.display = 'none';
      markAll.style.display = 'none';
    }
    
    count.textContent = `(${data.total})`;
    
    if (data.notifications.length === 0) {
      body.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><div>No notifications</div></div>';
    } else {
      body.innerHTML = data.notifications.map(n => {
        let avatarHtml = '';
        if (n.sender_picture) {
          avatarHtml = `<div class="notif-av-small"><img src="../assets/uploads/profiles/${n.sender_picture}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>`;
        } else {
          avatarHtml = `<div class="notif-av-small" style="background:${n.sender_color || '#3b82f6'}">${n.sender_initial || '🔔'}</div>`;
        }
        return `
        <div class="notif-item-small ${n.is_read ? '' : 'unread'}" onclick="goToNotification(${n.id}, '${n.link}')">
          ${avatarHtml}
          <div class="notif-content-small">
            <div class="notif-text-small">${n.message_html}</div>
            <div class="notif-time-small">${n.time_ago}</div>
          </div>
        </div>
      `}).join('');
    }
  } catch(e) {
    console.error('Failed to load notifications:', e);
  }
}

async function goToNotification(id, link) {
  try {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('notification_id', id);
    await fetch('notifications.php', { method: 'POST', body: fd });
    window.location.href = link;
  } catch(e) {
    console.error(e);
    window.location.href = link;
  }
}

async function markNotifRead(id) {
  try {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('notification_id', id);
    await fetch('notifications.php', { method: 'POST', body: fd });
    loadNotifications();
  } catch(e) {
    console.error(e);
  }
}

async function markAllNotificationsRead() {
  try {
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    await fetch('notifications.php', { method: 'POST', body: fd });
    loadNotifications();
  } catch(e) {
    console.error(e);
  }
}

// Load notifications on page load
loadNotifications();

// Scroll to post/comment if URL has parameters
window.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const postId = urlParams.get('post');
  const commentId = urlParams.get('comment');
  
  if (postId) {
    const postEl = document.getElementById('post-' + postId);
    if (postEl) {
      // Open comments section
      const commentsSection = document.getElementById('comments-' + postId);
      if (commentsSection) commentsSection.style.display = 'block';
      
      // Scroll to post
      setTimeout(() => {
        postEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        postEl.style.border = '2px solid var(--cyan)';
        setTimeout(() => postEl.style.border = '', 2000);
      }, 300);
      
      // If comment specified, scroll to it
      if (commentId) {
        setTimeout(() => {
          const commentEl = document.getElementById('comment-' + commentId);
          if (commentEl) {
            commentEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            commentEl.style.background = 'var(--cyan-dim)';
            setTimeout(() => commentEl.style.background = '', 2000);
          }
        }, 800);
      }
    }
  }
});

// ── Update post reaction UI ────────────────────────────────
function updatePostReactionUI(pid, reactions, myReaction) {
  const postBtn = document.querySelector(`.post-react-btn[data-pid="${pid}"]`);
  if (!postBtn) return;
  
  const reactionIcons = {
    like: 'fa-heart',
    haha: 'fa-laugh',
    thumbs_up: 'fa-thumbs-up',
    angry: 'fa-angry',
    wow: 'fa-surprise'
  };
  
  const reactionCounts = [
    {type: 'like', count: parseInt(reactions.like_count) || 0},
    {type: 'haha', count: parseInt(reactions.haha_count) || 0},
    {type: 'thumbs_up', count: parseInt(reactions.thumbs_up_count) || 0},
    {type: 'angry', count: parseInt(reactions.angry_count) || 0},
    {type: 'wow', count: parseInt(reactions.wow_count) || 0}
  ];
  
  reactionCounts.sort((a, b) => b.count - a.count);
  const topReactions = reactionCounts.slice(0, 3).filter(r => r.count > 0);
  const totalCount = reactionCounts.reduce((sum, r) => sum + r.count, 0);
  
  const topReactionsHtml = topReactions.map(r => 
    `<i class='fas ${reactionIcons[r.type]} top-reaction-icon' data-type='${r.type}'></i>`
  ).join('');
  
  postBtn.querySelector('.top-reactions').innerHTML = topReactionsHtml || "<i class='fas fa-heart' style='color:var(--text3)'></i>";
  postBtn.querySelector('.reaction-count').textContent = totalCount > 0 ? totalCount : '';
  postBtn.dataset.current = myReaction || '';
  
  if (myReaction) {
    postBtn.classList.add('active');
  } else {
    postBtn.classList.remove('active');
  }
}

// ── Bind events on dynamic content ────────────────────────
function bindNewElements() { /* events are delegated — nothing extra needed */ }
</script>
</body>
</html>