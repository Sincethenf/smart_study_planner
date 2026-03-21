<?php
// student/forum.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$_SESSION['full_name']       = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

// ── Upload dir ───────────────────────────────────────────────
define('FORUM_UPLOAD_DIR', '../assets/uploads/forum/');
define('FORUM_UPLOAD_URL', '../assets/uploads/forum/');
if (!file_exists(FORUM_UPLOAD_DIR)) {
    mkdir(FORUM_UPLOAD_DIR, 0755, true);
}

// ── Image upload helper ──────────────────────────────────────
function uploadForumImage(array $file, &$error_ref): ?string {
    $allowed_types = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    $max_size      = 8 * 1024 * 1024; // 8 MB

    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($file['type'], $allowed_types)) {
        $error_ref = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        return null;
    }
    if ($file['size'] > $max_size) {
        $error_ref = 'Image must be smaller than 8 MB.';
        return null;
    }

    // Verify it's actually an image
    $img_info = @getimagesize($file['tmp_name']);
    if (!$img_info) {
        $error_ref = 'Uploaded file is not a valid image.';
        return null;
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'forum_' . uniqid() . '_' . time() . '.' . $ext;
    $target   = FORUM_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $error_ref = 'Failed to save image. Check server write permissions.';
        return null;
    }
    return $filename;
}

// ── View modes ───────────────────────────────────────────────
$view     = $_GET['view']  ?? 'list';
$post_id  = (int)($_GET['id'] ?? 0);
$category = $_GET['cat']   ?? 'all';

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

// ── Create new post ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $title   = sanitize($conn, trim($_POST['title']    ?? ''));
    $content = sanitize($conn, trim($_POST['content']  ?? ''));
    $cat     = sanitize($conn, trim($_POST['category'] ?? 'General'));
    $caption = sanitize($conn, trim($_POST['image_caption'] ?? ''));
    $img     = null;

    if (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
        $view  = 'new';
    } else {
        // Handle image upload
        if (!empty($_FILES['post_image']['name'])) {
            $img = uploadForumImage($_FILES['post_image'], $error);
            if ($error) { $view = 'new'; goto end_post; }
        }

        $stmt = $conn->prepare("
            INSERT INTO forum_posts (user_id, title, content, category, image_path, image_caption)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", $user_id, $title, $content, $cat, $img, $caption);

        if ($stmt->execute()) {
            $new_id = (int)$conn->insert_id;
            $today  = date('Y-m-d');
            $al     = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count)
                                      VALUES (?, ?, 'lesson_view', 1)
                                      ON DUPLICATE KEY UPDATE count = count + 1");
            $al->bind_param("is", $user_id, $today);
            $al->execute();
            header("Location: forum.php?view=post&id=$new_id&success=posted");
            exit();
        } else {
            $error = 'Failed to create post.';
            $view  = 'new';
        }
    }
    end_post:;
}

// ── Post reply (with optional image) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $reply_content = sanitize($conn, trim($_POST['reply_content'] ?? ''));
    $reply_caption = sanitize($conn, trim($_POST['reply_caption'] ?? ''));
    $reply_post_id = (int)$_POST['reply_post_id'];
    $img           = null;

    if (empty($reply_content) && empty($_FILES['reply_image']['name'])) {
        $error   = 'Reply cannot be empty.';
        $view    = 'post';
        $post_id = $reply_post_id;
    } else {
        if (!empty($_FILES['reply_image']['name'])) {
            $img = uploadForumImage($_FILES['reply_image'], $error);
            if ($error) { $view = 'post'; $post_id = $reply_post_id; goto end_reply; }
        }

        $stmt = $conn->prepare("
            INSERT INTO forum_replies (post_id, user_id, content, image_path, image_caption)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $reply_post_id, $user_id, $reply_content, $img, $reply_caption);

        if ($stmt->execute()) {
            header("Location: forum.php?view=post&id=$reply_post_id&success=replied#replies");
            exit();
        } else {
            $error   = 'Failed to post reply.';
            $view    = 'post';
            $post_id = $reply_post_id;
        }
    }
    end_reply:;
}

// ── Delete post (owner only) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $del_id = (int)$_POST['delete_post_id'];
    // Delete image file if present
    $imgrow = $conn->prepare("SELECT image_path FROM forum_posts WHERE id=? AND user_id=?");
    $imgrow->bind_param("ii", $del_id, $user_id);
    $imgrow->execute();
    $imgrow = $imgrow->get_result()->fetch_assoc();
    if ($imgrow && $imgrow['image_path'] && file_exists(FORUM_UPLOAD_DIR . $imgrow['image_path'])) {
        unlink(FORUM_UPLOAD_DIR . $imgrow['image_path']);
    }
    $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $del_id, $user_id);
    $stmt->execute();
    header("Location: forum.php?success=deleted");
    exit();
}

// ── Delete reply (owner only) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reply'])) {
    $del_rid = (int)$_POST['delete_reply_id'];
    $back_id = (int)$_POST['back_post_id'];
    $imgrow  = $conn->prepare("SELECT image_path FROM forum_replies WHERE id=? AND user_id=?");
    $imgrow->bind_param("ii", $del_rid, $user_id);
    $imgrow->execute();
    $imgrow = $imgrow->get_result()->fetch_assoc();
    if ($imgrow && $imgrow['image_path'] && file_exists(FORUM_UPLOAD_DIR . $imgrow['image_path'])) {
        unlink(FORUM_UPLOAD_DIR . $imgrow['image_path']);
    }
    $stmt = $conn->prepare("DELETE FROM forum_replies WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $del_rid, $user_id);
    $stmt->execute();
    header("Location: forum.php?view=post&id=$back_id&success=reply_deleted");
    exit();
}

// ════════════════════════════════════════════════════════════
// DATA FETCHING
// ════════════════════════════════════════════════════════════

// ── Single post ──────────────────────────────────────────────
$current_post = null;
$post_replies = null;
$reply_count  = 0;

if ($view === 'post' && $post_id > 0) {
    $conn->query("UPDATE forum_posts SET view_count = view_count + 1 WHERE id = $post_id");

    $stmt = $conn->prepare("
        SELECT fp.*, u.full_name, u.username, u.avatar_color, u.role
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.id = ?
    ");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $current_post = $stmt->get_result()->fetch_assoc();

    if (!$current_post) { header("Location: forum.php"); exit(); }

    $rq = $conn->prepare("
        SELECT fr.*, u.full_name, u.username, u.avatar_color, u.role
        FROM forum_replies fr
        JOIN users u ON fr.user_id = u.id
        WHERE fr.post_id = ?
        ORDER BY fr.created_at ASC
    ");
    $rq->bind_param("i", $post_id);
    $rq->execute();
    $post_replies = $rq->get_result();
    $reply_count  = $post_replies->num_rows;
}

// ── Post list ────────────────────────────────────────────────
$posts_result = null;
if ($view === 'list') {
    $where = $category !== 'all'
        ? "AND fp.category = '" . $conn->real_escape_string($category) . "'"
        : '';
    $posts_result = $conn->query("
        SELECT fp.*,
               u.full_name, u.username, u.avatar_color, u.role,
               (SELECT COUNT(*) FROM forum_replies fr WHERE fr.post_id = fp.id) AS reply_count,
               (SELECT MAX(fr2.created_at) FROM forum_replies fr2 WHERE fr2.post_id = fp.id) AS last_reply_at
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE 1=1 $where
        ORDER BY fp.is_pinned DESC, fp.created_at DESC
        LIMIT 60
    ");
}

// ── Categories ───────────────────────────────────────────────
$cats_result = $conn->query("SELECT DISTINCT category, COUNT(*) as cnt FROM forum_posts GROUP BY category ORDER BY cnt DESC");
$categories  = [];
while ($c = $cats_result->fetch_assoc()) $categories[] = $c;

// ── Stats ────────────────────────────────────────────────────
$stat_posts   = $conn->query("SELECT COUNT(*) as v FROM forum_posts")->fetch_assoc()['v'];
$stat_replies = $conn->query("SELECT COUNT(*) as v FROM forum_replies")->fetch_assoc()['v'];
$stat_users   = $conn->query("SELECT COUNT(DISTINCT user_id) as v FROM forum_posts")->fetch_assoc()['v'];

// ── Helpers ──────────────────────────────────────────────────
$avatarColors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#f43f5e','#06b6d4','#ec4899'];

function timeAgoForum(string $date): string {
    $diff = time() - strtotime($date);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M j', strtotime($date));
}

$success_msg = match($_GET['success'] ?? '') {
    'posted'        => '✓ Post published successfully!',
    'replied'       => '✓ Reply posted!',
    'deleted'       => '✓ Post deleted.',
    'reply_deleted' => '✓ Reply removed.',
    default         => ''
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forum — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   RESET & VARIABLES
═══════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07080f;--bg2:#0c0e1a;--bg3:#111320;
  --surface:#161929;--surface2:#1c2135;
  --border:rgba(255,255,255,.055);--border-hi:rgba(255,255,255,.11);
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
html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;line-height:1.6}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ═══════════════════════════════════════════════
   SHELL + SIDEBAR
═══════════════════════════════════════════════ */
.shell{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200;transition:transform .3s var(--ease)}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.logo-mark{width:36px;height:36px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));display:grid;place-items:center;font-size:1rem;box-shadow:0 0 18px rgba(59,130,246,.25)}
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
.user-chip-avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet))}
.user-chip-name{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-chip-role{font-size:.66rem;color:var(--text3)}

/* ═══════════════════════════════════════════════
   MAIN + TOPBAR
═══════════════════════════════════════════════ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar-h);display:flex;align-items:center;padding:0 28px;gap:14px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.hamburger{display:none;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);width:36px;height:36px;place-items:center;color:var(--text2);cursor:pointer;font-size:.95rem}
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.icon-btn{width:36px;height:36px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);display:grid;place-items:center;color:var(--text2);cursor:pointer;transition:all .18s var(--ease);text-decoration:none;font-size:.88rem}
.icon-btn:hover{border-color:var(--border-hi);color:var(--text)}
.user-pill{display:flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;border-radius:30px;background:var(--surface);border:1px solid var(--border);cursor:pointer;text-decoration:none;transition:border-color .18s var(--ease)}
.user-pill:hover{border-color:var(--border-hi)}
.pill-avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet))}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════════════
   PAGE
═══════════════════════════════════════════════ */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:20px}

/* Alerts */
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--radius-sm);font-size:.84rem;line-height:1.5;animation:slideUp .3s var(--ease) both}
.alert i{font-size:.88rem;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--emerald-dim);color:var(--emerald);border:1px solid rgba(16,185,129,.2)}
.alert-error  {background:var(--rose-dim);  color:var(--rose);   border:1px solid rgba(244,63,94,.2)}

/* Page header */
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--cyan-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--cyan);flex-shrink:0;box-shadow:0 0 20px rgba(6,182,212,.18)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}

/* Buttons */
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);background:var(--cyan);color:#fff;border:none;font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .18s var(--ease);text-decoration:none}
.btn-primary:hover{background:#0891b2;transform:translateY(-1px);box-shadow:0 5px 16px rgba(6,182,212,.3)}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);background:transparent;color:var(--text2);border:1px solid var(--border-hi);font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease);text-decoration:none}
.btn-ghost:hover{border-color:var(--border-hi);color:var(--text)}
.btn-danger-sm{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--radius-sm);border:1px solid rgba(244,63,94,.25);background:var(--rose-dim);color:var(--rose);font-family:'Outfit',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease)}
.btn-danger-sm:hover{background:var(--rose);color:#fff}

/* Breadcrumb */
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--text3);animation:slideUp .4s var(--ease) both}
.breadcrumb a{color:var(--cyan);text-decoration:none}
.breadcrumb a:hover{text-decoration:underline}
.breadcrumb i{font-size:.65rem}

/* ═══════════════════════════════════════════════
   FORUM LIST LAYOUT
═══════════════════════════════════════════════ */
.forum-layout{display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start}
.posts-col{display:flex;flex-direction:column;gap:0}

/* Stats + search bar */
.forum-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;animation:slideUp .4s var(--ease) .05s both}
.stat-chip{display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);font-size:.78rem}
.stat-chip-val{font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--text)}
.stat-chip-lbl{color:var(--text3)}
.flex-spacer{flex:1}
.forum-search{display:flex;align-items:center;gap:7px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:7px 13px}
.forum-search input{background:none;border:none;outline:none;font-family:'Outfit',sans-serif;font-size:.82rem;color:var(--text);width:160px}
.forum-search input::placeholder{color:var(--text3)}
.forum-search i{color:var(--text3);font-size:.8rem;flex-shrink:0}

/* Posts list */
.posts-list{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;animation:slideUp .4s var(--ease) .08s both}
.post-item{display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);transition:background .15s var(--ease);text-decoration:none}
.post-item:last-child{border-bottom:none}
.post-item:hover{background:var(--bg3)}
.post-item.pinned{border-left:3px solid var(--amber)}
.pin-icon{font-size:.68rem;color:var(--amber)}
.post-avatar{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-size:.82rem;font-weight:700;color:#fff;flex-shrink:0}
.post-info{flex:1;min-width:0}
.post-item-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:7px}
.post-item-preview{font-size:.78rem;color:var(--text2);line-height:1.5;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.post-meta-row{display:flex;align-items:center;gap:10px;margin-top:5px;flex-wrap:wrap}
.post-meta-chip{font-size:.68rem;color:var(--text3);display:flex;align-items:center;gap:4px}
.cat-pill{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:10px;background:var(--cyan-dim);color:var(--cyan)}
.has-img-badge{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:10px;background:var(--violet-dim);color:var(--violet);display:flex;align-items:center;gap:3px}
.post-stats{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.reply-count-badge{display:flex;align-items:center;gap:4px;font-size:.72rem;font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--text2)}
.post-time{font-size:.65rem;color:var(--text3);font-family:'JetBrains Mono',monospace}
.no-posts{padding:48px;text-align:center;color:var(--text3)}
.no-posts i{font-size:2rem;display:block;margin-bottom:12px;opacity:.5}

/* Sidebar widgets */
.forum-sidebar{display:flex;flex-direction:column;gap:16px;position:sticky;top:calc(var(--topbar-h) + 20px);animation:slideUp .4s var(--ease) .12s both}
.sidebar-widget{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.widget-head{padding:14px 18px;border-bottom:1px solid var(--border);font-size:.875rem;font-weight:700;display:flex;align-items:center;gap:8px}
.widget-head i{color:var(--cyan)}
.widget-body{padding:14px 18px}
.cat-list{display:flex;flex-direction:column;gap:2px}
.cat-item{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:var(--radius-sm);text-decoration:none;color:var(--text2);font-size:.83rem;font-weight:500;transition:all .18s var(--ease)}
.cat-item:hover{background:var(--bg3);color:var(--text)}
.cat-item.active{background:var(--cyan-dim);color:var(--cyan)}
.cat-count{font-family:'JetBrains Mono',monospace;font-size:.7rem;background:var(--bg3);padding:1px 7px;border-radius:10px;color:var(--text3)}
.cat-item.active .cat-count{background:rgba(6,182,212,.15);color:var(--cyan)}

/* ═══════════════════════════════════════════════
   SINGLE POST VIEW
═══════════════════════════════════════════════ */
.post-view-layout{display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start}

/* Post card */
.post-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;animation:slideUp .4s var(--ease) .04s both}
.post-card-head{padding:22px 24px 18px;border-bottom:1px solid var(--border)}
.post-card-title{font-size:1.2rem;font-weight:800;letter-spacing:-.02em;color:var(--text);margin-bottom:12px;line-height:1.35}
.post-author-row{display:flex;align-items:center;gap:12px}
.author-av{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-size:.9rem;font-weight:700;color:#fff;flex-shrink:0}
.author-name{font-size:.9rem;font-weight:600;color:var(--text)}
.author-role{font-size:.7rem;color:var(--text3);text-transform:capitalize}
.author-time{margin-left:auto;font-size:.72rem;color:var(--text3);font-family:'JetBrains Mono',monospace}
.post-card-body{padding:22px 24px;font-size:.9rem;color:var(--text2);line-height:1.8;white-space:pre-wrap;word-break:break-word}

/* ── Problem image attachment ── */
.attachment-block{
  margin:16px 24px;padding:16px;
  background:var(--bg3);border:1px solid var(--border);
  border-radius:var(--radius-sm);
}
.attachment-label{
  font-size:.72rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--violet);
  display:flex;align-items:center;gap:6px;margin-bottom:10px;
}
.attachment-img{
  width:100%;max-width:600px;height:auto;
  border-radius:var(--radius-sm);border:1px solid var(--border);
  display:block;cursor:pointer;
  transition:opacity .2s var(--ease);
}
.attachment-img:hover{opacity:.9}
.attachment-caption{
  margin-top:10px;padding:10px 12px;
  background:var(--surface2);border-radius:var(--radius-sm);
  font-size:.83rem;color:var(--text2);line-height:1.6;
  border-left:3px solid var(--violet);
  font-style:italic;
}

.post-card-foot{padding:14px 24px;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.meta-tag{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:600;background:var(--cyan-dim);color:var(--cyan)}
.view-count{display:flex;align-items:center;gap:5px;font-size:.72rem;color:var(--text3);font-family:'JetBrains Mono',monospace;margin-left:auto}

/* Replies */
.replies-section{margin-top:16px;animation:slideUp .4s var(--ease) .08s both}
.replies-header{font-size:.85rem;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.reply-count-chip{font-family:'JetBrains Mono',monospace;font-size:.7rem;background:var(--cyan-dim);color:var(--cyan);padding:2px 8px;border-radius:10px;font-weight:700}
.reply-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px;overflow:hidden;animation:slideUp .35s var(--ease) both}
.reply-head{display:flex;align-items:center;gap:10px;padding:14px 18px 10px}
.reply-av{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0}
.reply-name{font-size:.84rem;font-weight:600;color:var(--text)}
.reply-role{font-size:.68rem;color:var(--text3);text-transform:capitalize}
.reply-time{margin-left:auto;font-size:.7rem;color:var(--text3);font-family:'JetBrains Mono',monospace}
.reply-body{padding:0 18px 14px;font-size:.86rem;color:var(--text2);line-height:1.7;white-space:pre-wrap;word-break:break-word}
.reply-attachment{margin:0 18px 14px;padding:12px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm)}
.reply-attachment-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--violet);display:flex;align-items:center;gap:5px;margin-bottom:8px}
.reply-img{max-width:100%;height:auto;border-radius:var(--radius-sm);border:1px solid var(--border);display:block;cursor:pointer}
.reply-caption{margin-top:8px;padding:8px 10px;background:var(--surface2);border-radius:var(--radius-sm);font-size:.8rem;color:var(--text2);border-left:3px solid var(--violet);font-style:italic}
.reply-foot{padding:8px 18px 12px;display:flex;justify-content:flex-end}

/* ── Image uploader widget ── */
.img-upload-wrap{
  border:2px dashed var(--border-hi);border-radius:var(--radius-sm);
  padding:20px;text-align:center;cursor:pointer;
  transition:all .2s var(--ease);background:var(--bg3);
  position:relative;overflow:hidden;
}
.img-upload-wrap:hover,.img-upload-wrap.drag-over{
  border-color:var(--violet);background:var(--violet-dim);
}
.img-upload-wrap input[type=file]{
  position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;
}
.upload-icon{font-size:1.8rem;color:var(--text3);margin-bottom:8px}
.upload-text{font-size:.82rem;color:var(--text2);line-height:1.5}
.upload-text strong{color:var(--violet)}
.upload-hint{font-size:.72rem;color:var(--text3);margin-top:4px}

/* Preview box */
.img-preview-wrap{
  margin-top:12px;display:none;
  background:var(--bg3);border:1px solid var(--border);
  border-radius:var(--radius-sm);overflow:hidden;
}
.img-preview-wrap.show{display:block}
.img-preview-inner{position:relative;display:inline-block;width:100%}
.img-preview{width:100%;max-height:260px;object-fit:contain;display:block;padding:10px}
.img-preview-remove{
  position:absolute;top:8px;right:8px;
  width:26px;height:26px;border-radius:50%;
  background:rgba(244,63,94,.85);color:#fff;border:none;
  display:grid;place-items:center;cursor:pointer;font-size:.75rem;
  transition:background .15s;
}
.img-preview-remove:hover{background:var(--rose)}
.img-preview-name{padding:0 12px 10px;font-size:.72rem;color:var(--text3);font-family:'JetBrains Mono',monospace}

/* Caption input below uploader */
.caption-wrap{margin-top:10px}

/* ── New post / reply forms ── */
.form-section-label{font-size:.8rem;font-weight:700;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:7px}
.form-section-label i{color:var(--cyan)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:6px;letter-spacing:.01em}
.form-group input[type=text],
.form-group select,
.form-group textarea{
  width:100%;padding:10px 13px;
  background:var(--bg3);border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  font-family:'Outfit',sans-serif;font-size:.9rem;color:var(--text);
  outline:none;transition:border-color .18s var(--ease),box-shadow .18s var(--ease);
  resize:vertical;
}
.form-group input::placeholder,
.form-group textarea::placeholder{color:rgba(136,146,170,.4)}
.form-group input:focus,
.form-group textarea:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(6,182,212,.1)}
.form-group select option{background:var(--bg2)}
.form-group textarea{min-height:100px}
.form-actions{display:flex;gap:10px;margin-top:4px}

/* Post+reply form cards */
.reply-form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;animation:slideUp .4s var(--ease) .12s both;margin-top:16px}
.reply-form-title{font-size:.875rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.reply-form-title i{color:var(--cyan)}
.new-post-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;animation:slideUp .4s var(--ease) .04s both;max-width:820px}
.new-post-title{font-size:1rem;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:9px}
.new-post-title i{color:var(--cyan)}

/* Section divider in form */
.form-divider{height:1px;background:var(--border);margin:20px 0}
.problem-section-head{
  display:flex;align-items:center;gap:10px;margin-bottom:14px;
  padding:12px 14px;background:var(--violet-dim);
  border:1px solid rgba(139,92,246,.2);border-radius:var(--radius-sm);
}
.problem-section-head i{color:var(--violet);font-size:1rem}
.problem-section-head div{font-size:.84rem;font-weight:700;color:var(--text)}
.problem-section-head p{font-size:.76rem;color:var(--text3);margin-top:1px}

/* Post info sidebar widget */
.post-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:.82rem}
.post-info-row:last-child{border-bottom:none}
.post-info-label{color:var(--text2)}
.post-info-val{color:var(--text);font-family:'JetBrains Mono',monospace;font-size:.76rem;font-weight:600}

/* ── Lightbox ── */
.lightbox{
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.92);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;padding:20px;
}
.lightbox.open{display:flex}
.lightbox-img{max-width:90vw;max-height:85vh;border-radius:var(--radius-sm);box-shadow:0 20px 60px rgba(0,0,0,.6)}
.lightbox-close{position:absolute;top:20px;right:24px;color:rgba(255,255,255,.7);font-size:1.8rem;cursor:pointer;background:none;border:none;transition:color .15s}
.lightbox-close:hover{color:#fff}
.lightbox-caption{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.7);color:rgba(255,255,255,.85);padding:10px 20px;border-radius:8px;font-size:.84rem;max-width:70vw;text-align:center;backdrop-filter:blur(4px)}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1100px){
  .forum-layout,.post-view-layout{grid-template-columns:1fr}
  .forum-sidebar{position:static}
}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .forum-search input{width:110px}
  .attachment-block,.attachment-img{max-width:100%}
}
</style>
</head>
<body>
<div class="shell">

<!-- ════════════════════════════
     SIDEBAR
════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">🎓</div>
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
      <div class="user-chip-avatar"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
      <div>
        <div class="user-chip-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="user-chip-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="overlay" id="overlay"></div>

<!-- ════════════════════════════
     MAIN
════════════════════════════ -->
<div class="main">
  <header class="topbar">
    <button class="hamburger" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
      <div class="topbar-title">
        <?php if ($view==='post'&&$current_post): ?>
          Forum › <?php echo htmlspecialchars(substr($current_post['title'],0,40)).(strlen($current_post['title'])>40?'…':''); ?>
        <?php elseif ($view==='new'): ?>Forum › New Post
        <?php else: ?>Community Forum<?php endif; ?>
      </div>
      <div class="topbar-sub">Ask questions, share problems, help each other</div>
    </div>
    <div class="topbar-right">
      <a href="notifications.php" class="icon-btn"><i class="fas fa-bell"></i></a>
      <a href="profile.php" class="user-pill">
        <div class="pill-avatar"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
        <span class="pill-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
      </a>
    </div>
  </header>

  <div class="page">

    <!-- Alerts -->
    <?php if ($success_msg): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $success_msg; ?></div><?php endif; ?>
    <?php if ($error):       ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>

    <?php /* ═══════════════════════ LIST VIEW ═══════════════════════ */ if ($view==='list'): ?>

    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon"><i class="fas fa-comments"></i></div>
        <div>
          <div class="page-title">Community Forum</div>
          <div class="page-sub">Share problems, ask questions, learn together</div>
        </div>
      </div>
      <a href="forum.php?view=new" class="btn-primary"><i class="fas fa-pen-to-square"></i> Post a Problem</a>
    </div>

    <div class="forum-layout">
      <!-- Posts -->
      <div class="posts-col">
        <div class="forum-bar">
          <div class="stat-chip"><span class="stat-chip-val"><?php echo $stat_posts; ?></span><span class="stat-chip-lbl">Posts</span></div>
          <div class="stat-chip"><span class="stat-chip-val"><?php echo $stat_replies; ?></span><span class="stat-chip-lbl">Replies</span></div>
          <div class="stat-chip"><span class="stat-chip-val"><?php echo $stat_users; ?></span><span class="stat-chip-lbl">Contributors</span></div>
          <div class="flex-spacer"></div>
          <div class="forum-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="postSearch" placeholder="Search posts…" oninput="searchPosts()">
          </div>
        </div>

        <div class="posts-list" id="postsList">
          <?php if ($posts_result && $posts_result->num_rows > 0):
            $pidx=0; while($post = $posts_result->fetch_assoc()):
            $aclr = $post['avatar_color'] ?? $avatarColors[$pidx%count($avatarColors)]; $pidx++;
          ?>
          <a class="post-item <?php echo $post['is_pinned']?'pinned':'' ?>"
             href="forum.php?view=post&id=<?php echo $post['id'] ?>"
             data-title="<?php echo htmlspecialchars(strtolower($post['title'])) ?>">
            <div class="post-avatar" style="background:<?php echo htmlspecialchars($aclr) ?>"><?php echo strtoupper(substr($post['full_name'],0,1)) ?></div>
            <div class="post-info">
              <div class="post-item-title">
                <?php if($post['is_pinned']): ?><i class="fas fa-thumbtack pin-icon"></i><?php endif; ?>
                <?php echo htmlspecialchars($post['title']) ?>
              </div>
              <div class="post-item-preview"><?php echo htmlspecialchars(substr($post['content'],0,100)) ?></div>
              <div class="post-meta-row">
                <span class="post-meta-chip"><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['full_name']) ?></span>
                <span class="cat-pill"><?php echo htmlspecialchars($post['category']) ?></span>
                <?php if(!empty($post['image_path'])): ?>
                <span class="has-img-badge"><i class="fas fa-image"></i> Screenshot</span>
                <?php endif; ?>
                <span class="post-meta-chip"><i class="fas fa-clock"></i> <?php echo timeAgoForum($post['created_at']) ?></span>
              </div>
            </div>
            <div class="post-stats">
              <div class="reply-count-badge"><i class="fas fa-comment" style="font-size:.65rem;color:var(--text3)"></i> <?php echo $post['reply_count'] ?></div>
              <div class="post-time"><i class="fas fa-eye" style="font-size:.6rem"></i> <?php echo $post['view_count'] ?></div>
              <?php if($post['last_reply_at']): ?><div class="post-time"><?php echo timeAgoForum($post['last_reply_at']) ?></div><?php endif; ?>
            </div>
          </a>
          <?php endwhile; else: ?>
          <div class="no-posts"><i class="fas fa-comments"></i>No posts yet. <a href="forum.php?view=new" style="color:var(--cyan)">Be the first!</a></div>
          <?php endif; ?>
        </div>
        <div id="noPostsSearch" style="display:none;padding:32px;text-align:center;color:var(--text3);background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-top:-1px">
          <i class="fas fa-magnifying-glass" style="font-size:1.4rem;display:block;margin-bottom:8px"></i>No posts match your search.
        </div>
      </div>

      <!-- Right sidebar -->
      <div class="forum-sidebar">
        <div class="sidebar-widget">
          <div class="widget-head"><i class="fas fa-layer-group"></i> Categories</div>
          <div class="widget-body">
            <div class="cat-list">
              <a href="forum.php" class="cat-item <?php echo $category==='all'?'active':'' ?>"><span><i class="fas fa-border-all" style="width:14px;text-align:center;margin-right:6px"></i>All Posts</span><span class="cat-count"><?php echo $stat_posts ?></span></a>
              <?php foreach($categories as $cat): ?>
              <a href="forum.php?cat=<?php echo urlencode($cat['category']) ?>" class="cat-item <?php echo $category===$cat['category']?'active':'' ?>">
                <span><i class="fas fa-tag" style="width:14px;text-align:center;margin-right:6px"></i><?php echo htmlspecialchars($cat['category']) ?></span>
                <span class="cat-count"><?php echo $cat['cnt'] ?></span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="sidebar-widget">
          <div class="widget-head"><i class="fas fa-upload"></i> Post a Problem</div>
          <div class="widget-body" style="display:flex;flex-direction:column;gap:10px">
            <p style="font-size:.82rem;color:var(--text2);line-height:1.6">Upload a screenshot of your problem. Add a caption and get help from peers and teachers.</p>
            <a href="forum.php?view=new" class="btn-primary" style="justify-content:center"><i class="fas fa-image"></i> Upload Screenshot</a>
          </div>
        </div>
        <div class="sidebar-widget">
          <div class="widget-head"><i class="fas fa-shield-halved"></i> Community Rules</div>
          <div class="widget-body" style="display:flex;flex-direction:column;gap:8px;font-size:.8rem;color:var(--text2)">
            <div style="display:flex;gap:8px"><span style="color:var(--emerald);flex-shrink:0">✓</span> Be respectful and kind</div>
            <div style="display:flex;gap:8px"><span style="color:var(--emerald);flex-shrink:0">✓</span> Only upload your own screenshots</div>
            <div style="display:flex;gap:8px"><span style="color:var(--emerald);flex-shrink:0">✓</span> Add a clear caption to images</div>
            <div style="display:flex;gap:8px"><span style="color:var(--emerald);flex-shrink:0">✓</span> Help others solve their problems</div>
          </div>
        </div>
      </div>
    </div>

    <?php /* ═══════════════════════ SINGLE POST VIEW ═══════════════════════ */ elseif ($view==='post' && $current_post): ?>

    <div class="breadcrumb">
      <a href="forum.php"><i class="fas fa-comments"></i> Forum</a>
      <i class="fas fa-chevron-right"></i>
      <span><?php echo htmlspecialchars(substr($current_post['title'],0,50)).(strlen($current_post['title'])>50?'…':'') ?></span>
    </div>

    <div class="post-view-layout">
      <div>
        <!-- Post -->
        <div class="post-card">
          <div class="post-card-head">
            <div class="post-card-title"><?php echo htmlspecialchars($current_post['title']) ?></div>
            <div class="post-author-row">
              <div class="author-av" style="background:<?php echo htmlspecialchars($current_post['avatar_color']??'#3b82f6') ?>"><?php echo strtoupper(substr($current_post['full_name'],0,1)) ?></div>
              <div>
                <div class="author-name"><?php echo htmlspecialchars($current_post['full_name']) ?></div>
                <div class="author-role"><?php echo $current_post['role'] ?></div>
              </div>
              <div class="author-time"><?php echo timeAgoForum($current_post['created_at']) ?></div>
            </div>
          </div>

          <?php if (!empty($current_post['content'])): ?>
          <div class="post-card-body"><?php echo htmlspecialchars($current_post['content']) ?></div>
          <?php endif; ?>

          <!-- Problem screenshot attachment -->
          <?php if (!empty($current_post['image_path'])): ?>
          <div class="attachment-block">
            <div class="attachment-label"><i class="fas fa-image"></i> Problem Screenshot</div>
            <img class="attachment-img"
                 src="<?php echo FORUM_UPLOAD_URL . htmlspecialchars($current_post['image_path']) ?>"
                 alt="Problem screenshot"
                 onclick="openLightbox(this.src, <?php echo json_encode($current_post['image_caption']??'') ?>)">
            <?php if (!empty($current_post['image_caption'])): ?>
            <div class="attachment-caption">
              <i class="fas fa-quote-left" style="font-size:.7rem;margin-right:5px;opacity:.6"></i>
              <?php echo htmlspecialchars($current_post['image_caption']) ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="post-card-foot">
            <span class="meta-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($current_post['category']) ?></span>
            <?php if($current_post['is_pinned']): ?><span class="meta-tag" style="background:var(--amber-dim);color:var(--amber)"><i class="fas fa-thumbtack"></i> Pinned</span><?php endif; ?>
            <div class="view-count"><i class="fas fa-eye"></i> <?php echo $current_post['view_count'] ?> views</div>
            <?php if($current_post['user_id']==$user_id): ?>
            <form method="POST" onsubmit="return confirm('Delete this post and its image?')">
              <input type="hidden" name="delete_post_id" value="<?php echo $current_post['id'] ?>">
              <button type="submit" name="delete_post" class="btn-danger-sm"><i class="fas fa-trash"></i> Delete</button>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Replies -->
        <div class="replies-section" id="replies">
          <div class="replies-header"><i class="fas fa-comments" style="color:var(--cyan)"></i> Replies <span class="reply-count-chip"><?php echo $reply_count ?></span></div>
          <?php if ($reply_count > 0): $ridx=0; $post_replies->data_seek(0);
            while($reply = $post_replies->fetch_assoc()):
            $raclr = $reply['avatar_color'] ?? $avatarColors[$ridx%count($avatarColors)]; $ridx++;
          ?>
          <div class="reply-card" style="animation-delay:<?php echo $ridx*0.04 ?>s">
            <div class="reply-head">
              <div class="reply-av" style="background:<?php echo htmlspecialchars($raclr) ?>"><?php echo strtoupper(substr($reply['full_name'],0,1)) ?></div>
              <div>
                <div class="reply-name"><?php echo htmlspecialchars($reply['full_name']) ?></div>
                <div class="reply-role"><?php echo $reply['role'] ?></div>
              </div>
              <div class="reply-time"><?php echo timeAgoForum($reply['created_at']) ?></div>
            </div>
            <?php if (!empty($reply['content'])): ?>
            <div class="reply-body"><?php echo htmlspecialchars($reply['content']) ?></div>
            <?php endif; ?>
            <?php if (!empty($reply['image_path'])): ?>
            <div class="reply-attachment">
              <div class="reply-attachment-label"><i class="fas fa-image"></i> Attached Screenshot</div>
              <img class="reply-img"
                   src="<?php echo FORUM_UPLOAD_URL . htmlspecialchars($reply['image_path']) ?>"
                   alt="Reply screenshot"
                   onclick="openLightbox(this.src, <?php echo json_encode($reply['image_caption']??'') ?>)">
              <?php if (!empty($reply['image_caption'])): ?>
              <div class="reply-caption">
                <i class="fas fa-quote-left" style="font-size:.68rem;margin-right:4px;opacity:.6"></i>
                <?php echo htmlspecialchars($reply['image_caption']) ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if($reply['user_id']==$user_id): ?>
            <div class="reply-foot">
              <form method="POST" onsubmit="return confirm('Delete this reply?')">
                <input type="hidden" name="delete_reply_id" value="<?php echo $reply['id'] ?>">
                <input type="hidden" name="back_post_id"   value="<?php echo $post_id ?>">
                <button type="submit" name="delete_reply" class="btn-danger-sm"><i class="fas fa-trash"></i> Delete</button>
              </form>
            </div>
            <?php endif; ?>
          </div>
          <?php endwhile;
          else: ?>
          <div style="text-align:center;padding:28px;color:var(--text3);font-size:.85rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius)">
            <i class="fas fa-comment" style="font-size:1.4rem;display:block;margin-bottom:8px;opacity:.4"></i>No replies yet — be the first!
          </div>
          <?php endif; ?>
        </div>

        <!-- Reply form -->
        <div class="reply-form-card">
          <div class="reply-form-title"><i class="fas fa-reply"></i> Write a Reply</div>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="reply_post_id" value="<?php echo $post_id ?>">
            <div class="form-group">
              <label>Your Reply</label>
              <textarea name="reply_content" placeholder="Share your answer, suggestion, or follow-up…"></textarea>
            </div>

            <!-- Optional screenshot in reply -->
            <div class="form-group">
              <div class="form-section-label"><i class="fas fa-image"></i> Attach Screenshot (optional)</div>
              <div class="img-upload-wrap" id="replyUploadWrap">
                <input type="file" name="reply_image" id="replyImageInput" accept="image/*" onchange="previewImage(this,'replyPreview','replyPreviewWrap')">
                <div class="upload-icon">🖼️</div>
                <div class="upload-text">Click or drag an image here<br><strong>JPG, PNG, GIF, WEBP</strong></div>
                <div class="upload-hint">Max 8 MB</div>
              </div>
              <div class="img-preview-wrap" id="replyPreviewWrap">
                <div class="img-preview-inner">
                  <img id="replyPreview" class="img-preview" src="" alt="Preview">
                  <button type="button" class="img-preview-remove" onclick="clearImage('replyImageInput','replyPreview','replyPreviewWrap')"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="img-preview-name" id="replyPreviewName"></div>
              </div>
            </div>
            <div class="form-group caption-wrap">
              <label>Caption for your screenshot (optional)</label>
              <input type="text" name="reply_caption" placeholder="Briefly describe what your screenshot shows…" maxlength="500">
            </div>

            <div class="form-actions">
              <button type="submit" name="submit_reply" class="btn-primary"><i class="fas fa-paper-plane"></i> Post Reply</button>
              <a href="forum.php" class="btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
          </form>
        </div>
      </div>

      <!-- Right sidebar on post view -->
      <div class="forum-sidebar">
        <div class="sidebar-widget">
          <div class="widget-head"><i class="fas fa-circle-info"></i> Post Info</div>
          <div class="widget-body">
            <div class="post-info-row"><span class="post-info-label">Category</span><span class="meta-tag"><?php echo htmlspecialchars($current_post['category']) ?></span></div>
            <div class="post-info-row"><span class="post-info-label">Posted</span><span class="post-info-val"><?php echo date('M j, Y',strtotime($current_post['created_at'])) ?></span></div>
            <div class="post-info-row"><span class="post-info-label">Views</span><span class="post-info-val"><?php echo $current_post['view_count'] ?></span></div>
            <div class="post-info-row"><span class="post-info-label">Replies</span><span class="post-info-val" style="color:var(--cyan)"><?php echo $reply_count ?></span></div>
            <?php if(!empty($current_post['image_path'])): ?>
            <div class="post-info-row"><span class="post-info-label">Screenshot</span><span class="post-info-val" style="color:var(--violet)">✓ Attached</span></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="sidebar-widget">
          <div class="widget-head"><i class="fas fa-compass"></i> Navigation</div>
          <div class="widget-body" style="display:flex;flex-direction:column;gap:8px">
            <a href="forum.php" class="btn-ghost" style="justify-content:center"><i class="fas fa-list"></i> All Posts</a>
            <a href="forum.php?view=new" class="btn-primary" style="justify-content:center"><i class="fas fa-pen-to-square"></i> New Post</a>
          </div>
        </div>
      </div>
    </div>

    <?php /* ═══════════════════════ NEW POST FORM ═══════════════════════ */ elseif ($view==='new'): ?>

    <div class="breadcrumb">
      <a href="forum.php"><i class="fas fa-comments"></i> Forum</a>
      <i class="fas fa-chevron-right"></i><span>New Post</span>
    </div>

    <div class="page-header" style="margin-bottom:0">
      <div class="page-header-left">
        <div class="page-icon"><i class="fas fa-pen-to-square"></i></div>
        <div>
          <div class="page-title">Post a Problem</div>
          <div class="page-sub">Describe your issue and optionally attach a screenshot</div>
        </div>
      </div>
      <a href="forum.php" class="btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="new-post-card">
      <div class="new-post-title"><i class="fas fa-pen-to-square"></i> New Post</div>
      <form method="POST" action="forum.php?view=new" enctype="multipart/form-data">

        <div class="form-group">
          <label>Post Title *</label>
          <input type="text" name="title" placeholder="A clear, descriptive title…" required
                 value="<?php echo isset($_POST['title'])?htmlspecialchars($_POST['title']):'' ?>">
        </div>

        <div class="form-group">
          <label>Category</label>
          <select name="category">
            <?php foreach(['General','Mathematics','Science','Computer Science','History','Literature','Announcements','Help & Support'] as $opt): ?>
            <option value="<?php echo $opt ?>" <?php echo (($_POST['category']??'General')===$opt)?'selected':'' ?>><?php echo htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Description</label>
          <textarea name="content" rows="5" placeholder="Describe the problem in detail. What did you try? What went wrong?"><?php echo isset($_POST['content'])?htmlspecialchars($_POST['content']):'' ?></textarea>
        </div>

        <div class="form-divider"></div>

        <!-- Screenshot upload section -->
        <div class="problem-section-head">
          <i class="fas fa-image"></i>
          <div>
            <div>Upload Problem Screenshot</div>
            <p>Attach a screenshot so others can see exactly what you're encountering</p>
          </div>
        </div>

        <div class="form-group">
          <div class="img-upload-wrap" id="postUploadWrap">
            <input type="file" name="post_image" id="postImageInput" accept="image/*"
                   onchange="previewImage(this,'postPreview','postPreviewWrap')">
            <div class="upload-icon">📸</div>
            <div class="upload-text">Click to select or <strong>drag & drop</strong> your screenshot</div>
            <div class="upload-hint">JPG · PNG · GIF · WEBP &nbsp;|&nbsp; Max 8 MB</div>
          </div>
          <div class="img-preview-wrap" id="postPreviewWrap">
            <div class="img-preview-inner">
              <img id="postPreview" class="img-preview" src="" alt="Preview">
              <button type="button" class="img-preview-remove" onclick="clearImage('postImageInput','postPreview','postPreviewWrap')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="img-preview-name" id="postPreviewName"></div>
          </div>
        </div>

        <div class="form-group caption-wrap">
          <label>Screenshot Caption (optional)</label>
          <input type="text" name="image_caption"
                 placeholder="e.g. 'Error on line 14 when running the Python script'"
                 maxlength="500"
                 value="<?php echo isset($_POST['image_caption'])?htmlspecialchars($_POST['image_caption']):'' ?>">
        </div>

        <div class="form-divider"></div>

        <div class="form-actions">
          <button type="submit" name="submit_post" class="btn-primary"><i class="fas fa-paper-plane"></i> Publish Post</button>
          <a href="forum.php" class="btn-ghost"><i class="fas fa-xmark"></i> Cancel</a>
        </div>
      </form>
    </div>

    <?php endif; ?>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="if(event.target===this)closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-xmark"></i></button>
  <img class="lightbox-img" id="lightboxImg" src="" alt="Screenshot">
  <div class="lightbox-caption" id="lightboxCaption" style="display:none"></div>
</div>

<script>
// ── Image preview ──────────────────────────────────────────
function previewImage(input, imgId, wrapId) {
  const file = input.files?.[0];
  if (!file) return;

  const wrap = document.getElementById(wrapId);
  const img  = document.getElementById(imgId);
  const nameEl = document.getElementById(imgId + 'Name') || document.getElementById(wrapId.replace('Wrap','') + 'PreviewName');

  const reader = new FileReader();
  reader.onload = e => {
    img.src = e.target.result;
    wrap.classList.add('show');
    if (nameEl) nameEl.textContent = file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
  };
  reader.readAsDataURL(file);
}

function clearImage(inputId, imgId, wrapId) {
  document.getElementById(inputId).value = '';
  document.getElementById(imgId).src = '';
  document.getElementById(wrapId).classList.remove('show');
}

// ── Drag-and-drop highlight ────────────────────────────────
document.querySelectorAll('.img-upload-wrap').forEach(wrap => {
  wrap.addEventListener('dragover',  e => { e.preventDefault(); wrap.classList.add('drag-over'); });
  wrap.addEventListener('dragleave', () => wrap.classList.remove('drag-over'));
  wrap.addEventListener('drop',      e => { e.preventDefault(); wrap.classList.remove('drag-over'); });
});

// ── Lightbox ───────────────────────────────────────────────
function openLightbox(src, caption) {
  document.getElementById('lightboxImg').src = src;
  const cap = document.getElementById('lightboxCaption');
  if (caption) { cap.textContent = caption; cap.style.display = 'block'; }
  else         { cap.style.display = 'none'; }
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

// ── Post search ────────────────────────────────────────────
function searchPosts() {
  const q = document.getElementById('postSearch')?.value.toLowerCase().trim() ?? '';
  const items = document.querySelectorAll('#postsList .post-item');
  const noRes = document.getElementById('noPostsSearch');
  let vis = 0;
  items.forEach(item => {
    const show = !q || item.dataset.title.includes(q);
    item.style.display = show ? '' : 'none';
    if (show) vis++;
  });
  if (noRes) noRes.style.display = (q && vis === 0) ? 'block' : 'none';
}

// ── Mobile sidebar ─────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
document.getElementById('menuBtn')?.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
});
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('open');
});
</script>
</body>
</html>