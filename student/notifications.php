<?php
// student/notifications.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ── Handle AJAX: mark as read ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notif_id = (int)$_POST['notification_id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'delete_notif' && isset($_POST['notification_id'])) {
        $notif_id = (int)$_POST['notification_id'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// ── Fetch notifications with optional sender info ────────────────
// Uses LEFT JOIN on sender_id — works whether column exists or not
// because we check column existence first
$has_sender_col = false;
$col_check = $conn->query("
    SELECT COUNT(*) as c FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'notifications'
      AND COLUMN_NAME  = 'sender_id'
");
if ($col_check && $col_check->fetch_assoc()['c'] > 0) {
    $has_sender_col = true;
}

if ($has_sender_col) {
    $notif_stmt = $conn->prepare("
        SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at,
               n.sender_id, n.related_id, n.related_type,
               u.full_name  AS sender_name,
               COALESCE(u.avatar_color,'#4f46e5') AS sender_color
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 60
    ");
} else {
    $notif_stmt = $conn->prepare("
        SELECT id, title, message, type, is_read, created_at,
               NULL AS sender_id, NULL AS sender_name, NULL AS sender_color,
               NULL AS related_id, NULL AS related_type
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 60
    ");
}
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// ── Unread count ─────────────────────────────────────────────
$uq = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
$uq->bind_param("i", $user_id);
$uq->execute();
$unread_count = (int)$uq->get_result()->fetch_assoc()['c'];

// ── Total count ──────────────────────────────────────────────
$tq = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?");
$tq->bind_param("i", $user_id);
$tq->execute();
$total_count = (int)$tq->get_result()->fetch_assoc()['c'];

// ── timeAgo — use function_exists guard to avoid redeclare clash ─
if (!function_exists('notifTimeAgo')) {
    function notifTimeAgo($datetime) {
        $diff = time() - strtotime($datetime);
        if ($diff <     60) return 'just now';
        if ($diff <   3600) return floor($diff / 60) . 'm ago';
        if ($diff <  86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', strtotime($datetime));
    }
}

// ── Type → icon / color mapping ─────────────────────────────
function notifMeta($type) {
    // Covers both old schema types and new migration types
    if ($type === 'success')    return ['fas fa-circle-check',          'var(--emerald)', 'var(--emerald-dim)'];
    if ($type === 'warning')    return ['fas fa-triangle-exclamation',  'var(--amber)',   'var(--amber-dim)'];
    if ($type === 'lesson')     return ['fas fa-book-open',             'var(--violet)',  'var(--violet-dim)'];
    if ($type === 'assignment') return ['fas fa-file-lines',            'var(--blue)',    'var(--blue-dim)'];
    if ($type === 'reply')      return ['fas fa-reply',                 'var(--cyan)',    'var(--cyan-dim)'];
    if ($type === 'like')       return ['fas fa-heart',                 'var(--rose)',    'var(--rose-dim)'];
    if ($type === 'mention')    return ['fas fa-at',                    'var(--amber)',   'var(--amber-dim)'];
    if ($type === 'system')     return ['fas fa-gear',                  'var(--text2)',   'var(--surface2)'];
    if ($type === 'info')       return ['fas fa-circle-info',           'var(--cyan)',    'var(--cyan-dim)'];
    return ['fas fa-bell', 'var(--blue)', 'var(--blue-dim)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Reset & Root ── */
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
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ── Shell + Sidebar ── */
.shell{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200;transition:transform .3s var(--ease)}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--blue-dim),transparent);color:var(--blue);border-left:2px solid var(--blue);padding-left:12px}
.nav-link.active i{color:var(--blue)}
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
.user-pill{display:flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;border-radius:30px;background:var(--surface);border:1px solid var(--border);cursor:pointer;text-decoration:none;transition:border-color .18s var(--ease)}
.user-pill:hover{border-color:var(--border-hi)}
.pill-av{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet))}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ── Page ── */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:20px;max-width:780px;width:100%;margin:0 auto}

/* ── Page header ── */
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--blue-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--blue);flex-shrink:0;box-shadow:0 0 20px rgba(59,130,246,.2);position:relative}
.page-icon .notif-pip{position:absolute;top:-3px;right:-3px;width:14px;height:14px;border-radius:50%;background:var(--rose);border:2px solid var(--bg2);display:grid;place-items:center;font-size:.55rem;font-weight:800;color:#fff;font-family:'JetBrains Mono',monospace}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}

/* Header actions */
.header-actions{display:flex;align-items:center;gap:8px}
.btn-mark-all{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--blue);color:#fff;border:none;font-family:'Outfit',sans-serif;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease)}
.btn-mark-all:hover{background:#2563eb;transform:translateY(-1px)}
.btn-mark-all:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* ── Filter tabs ── */
.filter-tabs{display:flex;gap:4px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:5px;animation:slideUp .4s var(--ease) .04s both}
.filter-tab{flex:1;padding:8px 12px;border-radius:var(--radius-sm);border:none;background:transparent;font-family:'Outfit',sans-serif;font-size:.8rem;font-weight:600;color:var(--text3);cursor:pointer;transition:all .18s var(--ease);white-space:nowrap}
.filter-tab:hover{color:var(--text2)}
.filter-tab.active{background:var(--bg3);color:var(--text);border:1px solid var(--border-hi)}

/* ── Notifications list ── */
.notif-list{display:flex;flex-direction:column;gap:8px;animation:slideUp .4s var(--ease) .08s both}

/* ── Single notification ── */
.notif-item{
  display:flex;align-items:flex-start;gap:14px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:16px 18px;
  transition:all .18s var(--ease);position:relative;
  overflow:hidden;
}
.notif-item:hover{border-color:var(--border-hi);transform:translateY(-1px)}
.notif-item.unread{
  border-left:3px solid var(--blue);
  background:linear-gradient(100deg,rgba(59,130,246,.07),var(--surface) 60%);
}

/* Unread dot */
.unread-dot{
  position:absolute;top:16px;right:16px;
  width:8px;height:8px;border-radius:50%;
  background:var(--blue);box-shadow:0 0 8px rgba(59,130,246,.6);
  flex-shrink:0;
}

/* Icon box */
.notif-icon{
  width:42px;height:42px;border-radius:11px;
  display:grid;place-items:center;font-size:1rem;
  flex-shrink:0;
}

/* Content */
.notif-body{flex:1;min-width:0;padding-right:18px}
.notif-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:3px}
.notif-message{font-size:.83rem;color:var(--text2);line-height:1.55}
.notif-footer{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap}
.notif-time{font-size:.7rem;color:var(--text3);font-family:'JetBrains Mono',monospace;display:flex;align-items:center;gap:4px}
.notif-type-pill{font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em}

/* Delete button */
.notif-delete{
  position:absolute;top:12px;right:12px;
  width:24px;height:24px;border-radius:6px;
  background:transparent;border:none;
  color:var(--text3);font-size:.72rem;cursor:pointer;
  display:none;place-items:center;
  transition:all .15s var(--ease);
}
.notif-item:hover .notif-delete{display:grid}
.notif-delete:hover{background:var(--rose-dim);color:var(--rose)}
/* When unread, adjust delete button to not overlap the dot */
.notif-item.unread .notif-delete{top:10px;right:28px}

/* ── Empty state ── */
.empty-state{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;text-align:center;
  padding:72px 28px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);
  animation:slideUp .4s var(--ease) .08s both;
}
.empty-ico{
  width:76px;height:76px;border-radius:50%;
  background:var(--blue-dim);display:grid;place-items:center;
  font-size:1.9rem;color:var(--blue);
  margin-bottom:18px;
  animation:bellShake 4s ease-in-out infinite;
}
@keyframes bellShake{
  0%,100%{transform:rotate(0)}
  10%,30%{transform:rotate(-8deg)}
  20%,40%{transform:rotate(8deg)}
  50%{transform:rotate(0)}
}
.empty-title{font-size:1.1rem;font-weight:700;margin-bottom:7px}
.empty-sub{font-size:.86rem;color:var(--text3);line-height:1.65;max-width:340px}

/* ── Animations ── */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ── Responsive ── */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .filter-tabs{overflow-x:auto;scrollbar-width:none}
  .filter-tabs::-webkit-scrollbar{display:none}
}
</style>
</head>
<body>
<div class="shell">

<!-- ════════════════════════════════════
     SIDEBAR
════════════════════════════════════ -->
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
    <a href="forum.php"     class="nav-link"><i class="fas fa-comments"></i> Forum</a>
    <div class="nav-group-label">Account</div>
    <a href="profile.php"   class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="notifications.php" class="nav-link active"><i class="fas fa-bell"></i> Notifications
      <?php if ($unread_count > 0): ?>
      <span style="margin-left:auto;background:var(--rose);color:#fff;font-size:.62rem;font-weight:700;padding:1px 6px;border-radius:10px"><?php echo $unread_count; ?></span>
      <?php endif; ?>
    </a>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-chip-av"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
      <div>
        <div class="user-chip-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="user-chip-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="overlay" id="overlay"></div>

<!-- ════════════════════════════════════
     MAIN
════════════════════════════════════ -->
<div class="main">
  <header class="topbar">
    <button class="hamburger" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
      <div class="topbar-title">Notifications</div>
      <div class="topbar-sub">Stay updated with your activity</div>
    </div>
    <div class="topbar-right">
      <a href="profile.php" class="user-pill">
        <div class="pill-av"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
        <span class="pill-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
      </a>
    </div>
  </header>

  <div class="page">

    <!-- Page header -->
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon">
          <i class="fas fa-bell"></i>
          <?php if ($unread_count > 0): ?>
          <span class="notif-pip"><?php echo min($unread_count, 9); ?></span>
          <?php endif; ?>
        </div>
        <div>
          <div class="page-title">Notifications</div>
          <div class="page-sub" id="unreadLabel">
            <?php echo $unread_count > 0 ? $unread_count . ' unread' : 'All caught up!'; ?>
            · <?php echo $total_count; ?> total
          </div>
        </div>
      </div>
      <div class="header-actions">
        <?php if ($unread_count > 0): ?>
        <button class="btn-mark-all" id="markAllBtn" onclick="markAllRead()">
          <i class="fas fa-check-double"></i> Mark All Read
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
      <button class="filter-tab active" data-filter="all"    onclick="filterNotifs('all',this)">All <span id="count-all">(<?php echo $total_count; ?>)</span></button>
      <button class="filter-tab"        data-filter="unread" onclick="filterNotifs('unread',this)">Unread <span id="count-unread">(<?php echo $unread_count; ?>)</span></button>
      <button class="filter-tab"        data-filter="info"    onclick="filterNotifs('info',this)">Info</button>
      <button class="filter-tab"        data-filter="success" onclick="filterNotifs('success',this)">Success</button>
      <button class="filter-tab"        data-filter="lesson"  onclick="filterNotifs('lesson',this)">Lessons</button>
      <button class="filter-tab"        data-filter="warning" onclick="filterNotifs('warning',this)">Warnings</button>
    </div>

    <!-- Notifications -->
    <?php if ($total_count > 0): ?>
    <div class="notif-list" id="notifList">

      <?php
      $idx = 0;
      while ($notif = $notifications->fetch_assoc()):
        [$icon, $color, $bg] = notifMeta($notif['type']);
        $type = htmlspecialchars($notif['type']);
        $idx++;
      ?>
      <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
           id="notif-<?php echo $notif['id']; ?>"
           data-id="<?php echo $notif['id']; ?>"
           data-type="<?php echo $type; ?>"
           data-read="<?php echo $notif['is_read'] ? '1' : '0'; ?>"
           onclick="markAsRead(<?php echo $notif['id']; ?>)"
           style="animation-delay:<?php echo $idx * 0.04; ?>s">

        <?php if (!$notif['is_read']): ?>
        <span class="unread-dot"></span>
        <?php endif; ?>

        <!-- Icon: show sender avatar initial if sender exists, else type icon -->
        <?php if (!empty($notif['sender_name'])): ?>
        <div class="notif-icon" style="background:<?php echo htmlspecialchars(!empty($notif['sender_color']) ? $notif['sender_color'] : '#4f46e5'); ?>;color:#fff;font-weight:700;font-size:.9rem">
          <?php echo strtoupper(substr($notif['sender_name'], 0, 1)); ?>
        </div>
        <?php else: ?>
        <div class="notif-icon" style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>">
          <i class="<?php echo $icon; ?>"></i>
        </div>
        <?php endif; ?>

        <!-- Body -->
        <div class="notif-body">
          <div class="notif-title"><?php echo htmlspecialchars($notif['title'] ? $notif['title'] : ucfirst($notif['type']).' Notification'); ?></div>
          <div class="notif-message">
            <?php
            $msg = htmlspecialchars($notif['message']);
            // Replace {sender} placeholder if sender name is available
            if (!empty($notif['sender_name'])) {
                $msg = str_replace(
                    '{sender}',
                    '<strong style="color:var(--blue)">' . htmlspecialchars($notif['sender_name']) . '</strong>',
                    $msg
                );
            }
            echo $msg;
            ?>
          </div>
          <div class="notif-footer">
            <span class="notif-time">
              <i class="fas fa-clock" style="font-size:.6rem"></i>
              <?php echo notifTimeAgo($notif['created_at']); ?>
            </span>
            <span class="notif-type-pill" style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>">
              <?php echo ucfirst($type); ?>
            </span>
          </div>
        </div>

        <!-- Delete button -->
        <button class="notif-delete"
                onclick="event.stopPropagation();deleteNotif(<?php echo $notif['id']; ?>)"
                title="Delete">
          <i class="fas fa-xmark"></i>
        </button>

      </div>
      <?php endwhile; ?>

    </div>

    <?php else: ?>
    <!-- Empty state -->
    <div class="empty-state">
      <div class="empty-ico"><i class="fas fa-bell-slash"></i></div>
      <div class="empty-title">No Notifications Yet</div>
      <div class="empty-sub">When you complete lessons, earn points, or interact with the community, notifications will appear here.</div>
    </div>
    <?php endif; ?>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// ── Mark single as read ───────────────────────────────────
async function markAsRead(id) {
  const item = document.getElementById('notif-' + id);
  if (!item || item.dataset.read === '1') return;

  const fd = new FormData();
  fd.append('action', 'mark_read');
  fd.append('notification_id', id);

  try {
    const r = await fetch('notifications.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
      item.classList.remove('unread');
      item.dataset.read = '1';
      item.querySelector('.unread-dot')?.remove();
      updateCounts();
    }
  } catch(e) { console.error(e); }
}

// ── Mark all as read ──────────────────────────────────────
async function markAllRead() {
  const btn = document.getElementById('markAllBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking…'; }

  const fd = new FormData();
  fd.append('action', 'mark_all_read');

  try {
    const r = await fetch('notifications.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
      document.querySelectorAll('.notif-item.unread').forEach(el => {
        el.classList.remove('unread');
        el.dataset.read = '1';
        el.querySelector('.unread-dot')?.remove();
      });
      btn?.remove();
      updateCounts();
    }
  } catch(e) { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark All Read'; } }
}

// ── Delete single notification ────────────────────────────
async function deleteNotif(id) {
  const item = document.getElementById('notif-' + id);
  const fd   = new FormData();
  fd.append('action', 'delete_notif');
  fd.append('notification_id', id);

  try {
    const r = await fetch('notifications.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
      item.style.transition = 'opacity .25s, transform .25s';
      item.style.opacity    = '0';
      item.style.transform  = 'translateX(20px)';
      setTimeout(() => { item.remove(); updateCounts(); checkEmpty(); }, 260);
    }
  } catch(e) { console.error(e); }
}

// ── Update count labels ───────────────────────────────────
function updateCounts() {
  const unread = document.querySelectorAll('.notif-item.unread').length;
  const total  = document.querySelectorAll('.notif-item').length;

  document.getElementById('unreadLabel').textContent =
    (unread > 0 ? unread + ' unread' : 'All caught up!') + ' · ' + total + ' total';

  const cu = document.getElementById('count-unread');
  const ca = document.getElementById('count-all');
  if (cu) cu.textContent = '(' + unread + ')';
  if (ca) ca.textContent = '(' + total + ')';

  const pip = document.querySelector('.notif-pip');
  if (pip) {
    if (unread === 0) pip.remove();
    else pip.textContent = Math.min(unread, 9);
  }
}

// ── Show empty state if no items left ────────────────────
function checkEmpty() {
  const list = document.getElementById('notifList');
  if (list && list.querySelectorAll('.notif-item').length === 0) {
    list.innerHTML = `
      <div class="empty-state" style="animation:none">
        <div class="empty-ico"><i class="fas fa-bell-slash"></i></div>
        <div class="empty-title">No Notifications</div>
        <div class="empty-sub">You're all caught up!</div>
      </div>`;
  }
}

// ── Filter tabs ───────────────────────────────────────────
function filterNotifs(filter, btn) {
  document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  document.querySelectorAll('.notif-item').forEach(item => {
    const type   = item.dataset.type;
    const isRead = item.dataset.read === '1';
    let show = false;

    if (filter === 'all')              show = true;
    else if (filter === 'unread')      show = !isRead;
    else if (filter === type)          show = true;

    item.style.display = show ? '' : 'none';
  });
}

// ── Mobile sidebar ────────────────────────────────────────
document.getElementById('menuBtn')?.addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
});
document.getElementById('overlay').addEventListener('click', () => {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
});
</script>
</body>
</html>