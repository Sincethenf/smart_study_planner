<?php
// student/lessons.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$_SESSION['full_name']       = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];

// Get all lessons
$lessons = $conn->query("SELECT * FROM lessons ORDER BY difficulty, title");

// Get user's lesson progress
$progress = $conn->prepare("SELECT lesson_id, status, progress FROM user_lessons WHERE user_id = ?");
$progress->bind_param("i", $user_id);
$progress->execute();
$user_progress = $progress->get_result();
$progress_map  = [];
while ($row = $user_progress->fetch_assoc()) {
    $progress_map[$row['lesson_id']] = $row;
}

// Handle start lesson
if (isset($_POST['start_lesson'])) {
    $lesson_id = (int)$_POST['lesson_id'];
    $check = $conn->prepare("SELECT id FROM user_lessons WHERE user_id = ? AND lesson_id = ?");
    $check->bind_param("ii", $user_id, $lesson_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO user_lessons (user_id, lesson_id, status, progress) VALUES (?, ?, 'in_progress', 10)");
        $stmt->bind_param("ii", $user_id, $lesson_id);
        $stmt->execute();
        $today = date('Y-m-d');
        $stmt  = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count) VALUES (?, ?, 'lesson', 1) ON DUPLICATE KEY UPDATE count = count + 1");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
    }
    header("Location: lessons.php?started=" . $lesson_id);
    exit();
}

// Handle complete lesson
if (isset($_POST['complete_lesson'])) {
    $lesson_id = (int)$_POST['lesson_id'];
    $stmt = $conn->prepare("UPDATE user_lessons SET status='completed', progress=100, completed_at=NOW() WHERE user_id=? AND lesson_id=?");
    $stmt->bind_param("ii", $user_id, $lesson_id);
    $stmt->execute();
    $lesson_points = (int)$conn->query("SELECT points FROM lessons WHERE id=$lesson_id")->fetch_assoc()['points'];
    $conn->query("UPDATE users SET points = points + $lesson_points WHERE id = $user_id");
    $conn->query("UPDATE rankings SET total_points = total_points + $lesson_points, lessons_completed = lessons_completed + 1 WHERE user_id = $user_id");
    $today = date('Y-m-d');
    $stmt  = $conn->prepare("INSERT INTO user_activity (user_id, activity_date, activity_type, count) VALUES (?, ?, 'lesson', 1) ON DUPLICATE KEY UPDATE count = count + 1");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    header("Location: lessons.php?completed=" . $lesson_id);
    exit();
}

// Quick stats
$totalLessons   = $lessons->num_rows;
$completedCount = count(array_filter($progress_map, fn($r) => $r['status'] === 'completed'));
$inProgCount    = count(array_filter($progress_map, fn($r) => $r['status'] === 'in_progress'));
$lessons->data_seek(0); // reset pointer after num_rows

// Difficulty icons / colors
$diffMeta = [
    'beginner'     => ['color'=>'var(--emerald)', 'bg'=>'var(--emerald-dim)', 'icon'=>'fa-seedling',   'label'=>'Beginner'],
    'intermediate' => ['color'=>'var(--amber)',   'bg'=>'var(--amber-dim)',   'icon'=>'fa-bolt',       'label'=>'Intermediate'],
    'advanced'     => ['color'=>'var(--rose)',     'bg'=>'var(--rose-dim)',    'icon'=>'fa-fire-flame-curved', 'label'=>'Advanced'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lessons — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════
   LOADING SCREEN
═══════════════════════════════════════ */
.loading-screen {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  transition: opacity 0.5s ease, visibility 0.5s ease;
}
.loading-screen.hidden {
  opacity: 0;
  visibility: hidden;
}
.loader {
  width: 60px;
  height: 60px;
  border: 4px solid rgba(59, 130, 246, 0.2);
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.loading-text {
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.9rem;
  font-weight: 500;
  letter-spacing: 0.5px;
}

/* ═══════════════════════════════════════
   RESET & ROOT
═══════════════════════════════════════ */
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

/* ═══════════════════════════════════════
   SHELL + SIDEBAR
═══════════════════════════════════════ */
.shell{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200;transition:transform .3s var(--ease)}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem;letter-spacing:.04em}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--blue-dim),transparent);color:var(--blue);border-left:2px solid var(--blue);padding-left:12px}
.nav-link.active i{color:var(--blue)}
.sidebar-footer{padding:14px 8px;border-top:1px solid var(--border)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--surface)}
.user-chip-avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.user-chip-avatar img{width:100%;height:100%;object-fit:cover}
.user-chip-name{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-chip-role{font-size:.66rem;color:var(--text3)}

/* ═══════════════════════════════════════
   MAIN + TOPBAR
═══════════════════════════════════════ */
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
.pill-avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.pill-avatar img{width:100%;height:100%;object-fit:cover}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════
   PAGE
═══════════════════════════════════════ */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:20px}

/* ── Alerts ── */
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--radius-sm);font-size:.84rem;line-height:1.5;animation:slideUp .3s var(--ease) both}
.alert i{font-size:.88rem;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--emerald-dim);color:var(--emerald);border:1px solid rgba(16,185,129,.2)}

/* ── Page header ── */
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--blue-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--blue);flex-shrink:0;box-shadow:0 0 20px rgba(59,130,246,.2)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}

/* ── Mini stats ── */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap}
.mini-stat{display:flex;align-items:center;gap:7px;padding:7px 13px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);font-size:.78rem}
.mini-stat-val{font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--text)}
.mini-stat-lbl{color:var(--text3)}

/* ── Search + filter bar ── */
.controls-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;animation:slideUp .4s var(--ease) .05s both}
.search-wrap{display:flex;align-items:center;gap:7px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 14px;flex:1;min-width:200px;max-width:300px}
.search-wrap input{background:none;border:none;outline:none;font-family:'Outfit',sans-serif;font-size:.84rem;color:var(--text);width:100%}
.search-wrap input::placeholder{color:var(--text3)}
.search-wrap i{color:var(--text3);font-size:.8rem;flex-shrink:0}
.filter-pills{display:flex;gap:6px;flex-wrap:wrap}
.fpill{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:20px;background:var(--surface);border:1px solid var(--border);font-size:.78rem;font-weight:600;color:var(--text3);cursor:pointer;transition:all .18s var(--ease);white-space:nowrap}
.fpill i{font-size:.72rem}
.fpill:hover{border-color:var(--border-hi);color:var(--text2)}
.fpill.active{background:var(--blue-dim);border-color:rgba(59,130,246,.3);color:var(--blue)}
.fpill.fp-beginner.active    {background:var(--emerald-dim);border-color:rgba(16,185,129,.3);color:var(--emerald)}
.fpill.fp-intermediate.active{background:var(--amber-dim);  border-color:rgba(245,158,11,.3);color:var(--amber)}
.fpill.fp-advanced.active    {background:var(--rose-dim);   border-color:rgba(244,63,94,.3); color:var(--rose)}
.fpill.fp-progress.active    {background:var(--violet-dim); border-color:rgba(139,92,246,.3);color:var(--violet)}
.spacer-flex{flex:1}
.sort-select{padding:8px 12px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);font-family:'Outfit',sans-serif;font-size:.78rem;font-weight:500;color:var(--text2);cursor:pointer;outline:none}
.sort-select option{background:var(--bg2)}

/* ═══════════════════════════════════════
   LESSONS GRID
═══════════════════════════════════════ */
.lessons-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;animation:slideUp .4s var(--ease) .1s both}

/* ── Lesson card ── */
.lesson-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  display:flex;flex-direction:column;
  position:relative;
  transition:transform .2s var(--ease),border-color .2s var(--ease),box-shadow .2s var(--ease);
}
.lesson-card:hover{transform:translateY(-3px);border-color:var(--border-hi);box-shadow:0 12px 32px rgba(0,0,0,.3)}

/* Colored top strip */
.card-strip{height:3px;width:100%}

/* Status badge */
.status-badge{
  position:absolute;top:16px;right:16px;
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 9px;border-radius:20px;
  font-size:.65rem;font-weight:700;
  z-index:2;
}
.sb-completed  {background:var(--emerald-dim);color:var(--emerald);border:1px solid rgba(16,185,129,.2)}
.sb-in_progress{background:var(--amber-dim);  color:var(--amber);  border:1px solid rgba(245,158,11,.2)}

/* Card body */
.card-body{padding:18px 20px;flex:1;display:flex;flex-direction:column;gap:10px}

/* Difficulty chip */
.diff-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;width:fit-content}

/* Title + desc */
.lesson-title{font-size:.975rem;font-weight:700;color:var(--text);letter-spacing:-.01em;line-height:1.35}
.lesson-desc{font-size:.8rem;color:var(--text2);line-height:1.6;flex:1}

/* Meta row */
.lesson-meta{display:flex;gap:14px}
.meta-item{display:flex;align-items:center;gap:5px;font-size:.74rem;color:var(--text3)}
.meta-item i{font-size:.7rem}

/* Progress bar */
.prog-wrap{margin-top:2px}
.prog-header{display:flex;justify-content:space-between;font-size:.68rem;color:var(--text3);margin-bottom:5px}
.prog-bar{height:4px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden}
.prog-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--blue),var(--violet));transition:width .4s var(--ease)}

/* Card footer */
.card-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px}

/* Buttons */
.lbtn{
  flex:1;display:flex;align-items:center;justify-content:center;gap:7px;
  padding:9px 12px;border-radius:var(--radius-sm);border:none;
  font-family:'Outfit',sans-serif;font-size:.8rem;font-weight:700;
  cursor:pointer;transition:all .18s var(--ease);
}
.lbtn-start   {background:var(--blue);   color:#fff}
.lbtn-start:hover{background:#2563eb;box-shadow:0 4px 14px rgba(59,130,246,.35)}
.lbtn-continue{background:var(--amber);  color:#1a1000}
.lbtn-continue:hover{background:#d97706}
.lbtn-complete{background:var(--emerald);color:#fff}
.lbtn-complete:hover{background:#059669;box-shadow:0 4px 14px rgba(16,185,129,.3)}
.lbtn-done    {background:rgba(255,255,255,.06);color:var(--text3);cursor:not-allowed;border:1px solid var(--border)}
form.lbtn-form{flex:1;display:flex}
form.lbtn-form .lbtn{width:100%}

/* ── Empty state ── */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:64px 28px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);animation:slideUp .4s var(--ease) .1s both}
.empty-ico{width:72px;height:72px;border-radius:50%;background:var(--blue-dim);display:grid;place-items:center;font-size:1.8rem;color:var(--blue);margin-bottom:18px;opacity:.7}
.empty-title{font-size:1.1rem;font-weight:700;margin-bottom:6px}
.empty-sub{font-size:.85rem;color:var(--text3);line-height:1.6}

/* No results */
#noResults{display:none}

/* ═══════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ═══════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════ */
@media(max-width:1200px){.lessons-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .lessons-grid{grid-template-columns:1fr}
  .search-wrap{max-width:100%}
}
</style>
</head>
<body>
<!-- ════════════════════════════════════
     LOADING SCREEN
════════════════════════════════════ -->
<div class="loading-screen" id="loadingScreen">
  <div class="loader"></div>
  <div class="loading-text">Loading Lessons...</div>
</div>

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
    <a href="lessons.php"   class="nav-link active"><i class="fas fa-book-open"></i> Lessons</a>
    <a href="favorites.php" class="nav-link"><i class="fas fa-heart"></i> Favorites</a>
    <div class="nav-group-label">Community</div>
    <a href="rankings.php"  class="nav-link"><i class="fas fa-trophy"></i> Rankings</a>
    <a href="forum.php"     class="nav-link"><i class="fas fa-comments"></i> Forum</a>
    <div class="nav-group-label">Account</div>
    <a href="profile.php"   class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-chip-avatar">
        <?php if (!empty($user['profile_picture'])): ?>
          <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
        <?php else: ?>
          <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
        <?php endif; ?>
      </div>
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
      <div class="topbar-title">Lessons</div>
      <div class="topbar-sub">Browse and track your learning progress</div>
    </div>
    <div class="topbar-right">
      <a href="notifications.php" class="icon-btn"><i class="fas fa-bell"></i></a>
      <a href="profile.php" class="user-pill">
        <div class="pill-avatar">
          <?php if (!empty($user['profile_picture'])): ?>
            <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
          <?php else: ?>
            <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
          <?php endif; ?>
        </div>
        <span class="pill-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
      </a>
    </div>
  </header>

  <div class="page">

    <!-- ── Alerts ──────────────────────────── -->
    <?php if (isset($_GET['started'])): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i> Lesson started! Keep up the momentum.</div>
    <?php endif; ?>
    <?php if (isset($_GET['completed'])): ?>
    <div class="alert alert-success"><i class="fas fa-trophy"></i> Lesson completed — points earned! 🎉</div>
    <?php endif; ?>

    <!-- ── Page header ─────────────────────── -->
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon"><i class="fas fa-book-open"></i></div>
        <div>
          <div class="page-title">Available Lessons</div>
          <div class="page-sub">Learn at your own pace, earn points as you go</div>
        </div>
      </div>
      <div class="mini-stats">
        <div class="mini-stat">
          <span class="mini-stat-val"><?php echo $totalLessons; ?></span>
          <span class="mini-stat-lbl">Total</span>
        </div>
        <div class="mini-stat" style="color:var(--amber)">
          <span class="mini-stat-val" style="color:var(--amber)"><?php echo $inProgCount; ?></span>
          <span class="mini-stat-lbl">In Progress</span>
        </div>
        <div class="mini-stat" style="color:var(--emerald)">
          <span class="mini-stat-val" style="color:var(--emerald)"><?php echo $completedCount; ?></span>
          <span class="mini-stat-lbl">Completed</span>
        </div>
      </div>
    </div>

    <!-- ── Controls bar ──────────────────────── -->
    <div class="controls-bar">
      <div class="search-wrap">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search lessons…" oninput="applyFilters()">
      </div>

      <div class="filter-pills">
        <button class="fpill active" data-filter="all" onclick="setFilter('all',this)">
          <i class="fas fa-border-all"></i> All
        </button>
        <button class="fpill fp-beginner" data-filter="beginner" onclick="setFilter('beginner',this)">
          <i class="fas fa-seedling"></i> Beginner
        </button>
        <button class="fpill fp-intermediate" data-filter="intermediate" onclick="setFilter('intermediate',this)">
          <i class="fas fa-bolt"></i> Intermediate
        </button>
        <button class="fpill fp-advanced" data-filter="advanced" onclick="setFilter('advanced',this)">
          <i class="fas fa-fire-flame-curved"></i> Advanced
        </button>
        <button class="fpill fp-progress" data-filter="in_progress" onclick="setFilter('in_progress',this)">
          <i class="fas fa-spinner"></i> In Progress
        </button>
        <button class="fpill fp-progress" data-filter="completed" onclick="setFilter('completed',this)" style="--active-bg:var(--emerald-dim);--active-border:rgba(16,185,129,.3);--active-color:var(--emerald)">
          <i class="fas fa-circle-check"></i> Completed
        </button>
      </div>

      <div class="spacer-flex"></div>
      <select class="sort-select" id="sortSelect" onchange="applyFilters()">
        <option value="default">Default order</option>
        <option value="az">A → Z</option>
        <option value="za">Z → A</option>
        <option value="points-hi">Points: High</option>
        <option value="points-lo">Points: Low</option>
      </select>
    </div>

    <!-- ── Lessons Grid ───────────────────────── -->
    <?php if ($lessons->num_rows > 0): ?>
    <div class="lessons-grid" id="lessonsGrid">
      <?php while($lesson = $lessons->fetch_assoc()):
        $status    = $progress_map[$lesson['id']]['status']   ?? 'not_started';
        $progPct   = $progress_map[$lesson['id']]['progress'] ?? 0;
        $dm        = $diffMeta[$lesson['difficulty']] ?? $diffMeta['beginner'];
        $pts       = (int)($lesson['points'] ?? 0);
        $stripMap  = ['beginner'=>'var(--emerald)','intermediate'=>'var(--amber)','advanced'=>'var(--rose)'];
        $stripClr  = $stripMap[$lesson['difficulty']] ?? 'var(--blue)';
      ?>
      <div class="lesson-card"
           data-difficulty="<?php echo $lesson['difficulty'] ?>"
           data-status="<?php echo $status ?>"
           data-title="<?php echo htmlspecialchars(strtolower($lesson['title'])) ?>"
           data-points="<?php echo $pts ?>">

        <!-- Colored top strip -->
        <div class="card-strip" style="background:<?php echo $stripClr ?>"></div>

        <!-- Status badge -->
        <?php if ($status === 'completed'): ?>
          <span class="status-badge sb-completed"><i class="fas fa-check"></i> Done</span>
        <?php elseif ($status === 'in_progress'): ?>
          <span class="status-badge sb-in_progress"><i class="fas fa-bolt"></i> Active</span>
        <?php endif; ?>

        <!-- Body -->
        <div class="card-body">
          <span class="diff-chip" style="background:<?php echo $dm['bg'] ?>;color:<?php echo $dm['color'] ?>">
            <i class="fas <?php echo $dm['icon'] ?>"></i>
            <?php echo $dm['label'] ?>
          </span>

          <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']) ?></div>
          <div class="lesson-desc"><?php echo htmlspecialchars($lesson['description'] ?? '') ?></div>

          <div class="lesson-meta">
            <span class="meta-item"><i class="fas fa-star" style="color:var(--amber)"></i> <?php echo $pts ?> pts</span>
            <span class="meta-item"><i class="fas fa-clock" style="color:var(--blue)"></i> 30 mins</span>
            <?php if ($status === 'completed'): ?>
            <span class="meta-item"><i class="fas fa-circle-check" style="color:var(--emerald)"></i> Finished</span>
            <?php endif; ?>
          </div>

          <?php if ($status === 'in_progress'): ?>
          <div class="prog-wrap">
            <div class="prog-header">
              <span>Progress</span>
              <span><?php echo $progPct ?>%</span>
            </div>
            <div class="prog-bar">
              <div class="prog-fill" style="width:<?php echo $progPct ?>%"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Footer actions -->
        <div class="card-foot">
          <?php if ($status === 'not_started'): ?>
            <form method="POST" class="lbtn-form">
              <input type="hidden" name="lesson_id" value="<?php echo $lesson['id'] ?>">
              <button type="submit" name="start_lesson" class="lbtn lbtn-start">
                <i class="fas fa-play"></i> Start Lesson
              </button>
            </form>

          <?php elseif ($status === 'in_progress'): ?>
            <form method="POST" class="lbtn-form">
              <input type="hidden" name="lesson_id" value="<?php echo $lesson['id'] ?>">
              <button type="submit" name="continue_lesson" class="lbtn lbtn-continue">
                <i class="fas fa-play-circle"></i> Continue
              </button>
            </form>
            <form method="POST" class="lbtn-form">
              <input type="hidden" name="lesson_id" value="<?php echo $lesson['id'] ?>">
              <button type="submit" name="complete_lesson" class="lbtn lbtn-complete">
                <i class="fas fa-check"></i> Complete
              </button>
            </form>

          <?php else: ?>
            <button class="lbtn lbtn-done" disabled>
              <i class="fas fa-circle-check"></i> Completed
            </button>
          <?php endif; ?>
        </div>

      </div>
      <?php endwhile; ?>
    </div>

    <!-- No results -->
    <div id="noResults" class="empty-state" style="display:none">
      <div class="empty-ico"><i class="fas fa-filter"></i></div>
      <div class="empty-title">No lessons match</div>
      <div class="empty-sub">Try a different filter or search term.</div>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-ico"><i class="fas fa-book-open"></i></div>
      <div class="empty-title">No Lessons Yet</div>
      <div class="empty-sub">Check back soon — new lessons are on the way!</div>
    </div>
    <?php endif; ?>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// ── Loading Screen ────────────────────────────────────────
window.addEventListener('load', function() {
  setTimeout(() => {
    document.getElementById('loadingScreen').classList.add('hidden');
  }, 1100);
});

// ── Filter state ──────────────────────────────────────────
let activeFilter = 'all';

function setFilter(filter, btn) {
  activeFilter = filter;
  document.querySelectorAll('.fpill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  // Special active colour for completed button
  if (filter === 'completed') {
    btn.style.cssText = 'background:var(--emerald-dim);border-color:rgba(16,185,129,.3);color:var(--emerald)';
  }
  applyFilters();
}

// ── Search + filter + sort ────────────────────────────────
function applyFilters() {
  const query   = document.getElementById('searchInput').value.toLowerCase().trim();
  const sortVal = document.getElementById('sortSelect').value;
  const grid    = document.getElementById('lessonsGrid');
  if (!grid) return;

  const cards   = [...grid.querySelectorAll('.lesson-card')];

  // Filter
  let visible = cards.filter(card => {
    const diff   = card.dataset.difficulty;
    const status = card.dataset.status;
    const title  = card.dataset.title;

    const filterOk = activeFilter === 'all'
      || diff === activeFilter
      || status === activeFilter;

    const searchOk = !query || title.includes(query);
    return filterOk && searchOk;
  });

  // Sort
  visible.sort((a, b) => {
    if (sortVal === 'az')         return a.dataset.title.localeCompare(b.dataset.title);
    if (sortVal === 'za')         return b.dataset.title.localeCompare(a.dataset.title);
    if (sortVal === 'points-hi')  return parseInt(b.dataset.points) - parseInt(a.dataset.points);
    if (sortVal === 'points-lo')  return parseInt(a.dataset.points) - parseInt(b.dataset.points);
    return 0;
  });

  // Re-render
  cards.forEach(c => c.style.display = 'none');
  visible.forEach((c, i) => {
    c.style.display    = '';
    c.style.animationDelay = (i * 0.03) + 's';
  });

  const noRes = document.getElementById('noResults');
  if (noRes) noRes.style.display = visible.length === 0 ? 'flex' : 'none';
  if (grid)  grid.style.display  = visible.length === 0 ? 'none' : '';
}

// ── Mobile sidebar ────────────────────────────────────────
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