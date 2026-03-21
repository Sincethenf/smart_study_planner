<?php
// student/rankings.php
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

// Leaderboard
$leaderboard = $conn->query("
    SELECT u.id, u.full_name, u.username, u.profile_picture, u.points,
           r.lessons_completed, r.generated_count,
           RANK() OVER (ORDER BY u.points DESC) as rank_position
    FROM users u
    LEFT JOIN rankings r ON u.id = r.user_id
    WHERE u.role = 'student'
    ORDER BY u.points DESC
    LIMIT 50
");

// User's rank
$user_rank = $conn->query("
    SELECT COUNT(*) + 1 as rank
    FROM users
    WHERE role = 'student'
      AND points > (SELECT points FROM users WHERE id = $user_id)
")->fetch_assoc()['rank'];

// Top 3 podium
$top3 = $conn->query("
    SELECT full_name, points, profile_picture
    FROM users
    WHERE role = 'student'
    ORDER BY points DESC
    LIMIT 3
");
$podium = [];
while ($r = $top3->fetch_assoc()) $podium[] = $r;

// Stats
$total_students   = $conn->query("SELECT COUNT(*)   as v FROM users WHERE role='student'")->fetch_assoc()['v'];
$total_points_all = $conn->query("SELECT SUM(points) as v FROM users WHERE role='student'")->fetch_assoc()['v'];
$avg_points       = $conn->query("SELECT AVG(points) as v FROM users WHERE role='student'")->fetch_assoc()['v'];

// Badge helper
function rankBadge(int $pos, int $pts): array {
    if ($pos === 1) return ['🥇', 'Champion',   'var(--amber)',   'var(--amber-dim)'];
    if ($pos === 2) return ['🥈', 'Silver',     '#94a3b8',       'rgba(148,163,184,.15)'];
    if ($pos === 3) return ['🥉', 'Bronze',     '#cd7f32',       'rgba(205,127,50,.15)'];
    if ($pts > 500) return ['💎', 'Elite',      'var(--violet)', 'var(--violet-dim)'];
    if ($pts > 200) return ['⭐', 'Rising Star','var(--blue)',   'var(--blue-dim)'];
    return ['🌱', 'Beginner', 'var(--text3)', 'var(--surface2)'];
}

// Avatar colors
$avatarColors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#f43f5e','#06b6d4','#ec4899','#84cc16'];

// Collect all rows for JS
$rows = [];
while ($s = $leaderboard->fetch_assoc()) $rows[] = $s;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rankings — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   LOADING SCREEN
═══════════════════════════════════════════════ */
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
  border: 4px solid rgba(245, 158, 11, 0.2);
  border-top-color: #f59e0b;
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

/* ═══════════════════════════════════════════════
   RESET & ROOT
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
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem;letter-spacing:.04em}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--amber-dim),transparent);color:var(--amber);border-left:2px solid var(--amber);padding-left:12px}
.nav-link.active i{color:var(--amber)}
.sidebar-footer{padding:14px 8px;border-top:1px solid var(--border)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--surface)}
.user-chip-avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.user-chip-avatar img{width:100%;height:100%;object-fit:cover}
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
.pill-avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.pill-avatar img{width:100%;height:100%;object-fit:cover}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════════════
   PAGE
═══════════════════════════════════════════════ */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:22px}

/* ── Page header ── */
.page-header{display:flex;align-items:center;justify-content:space-between;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--amber-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--amber);flex-shrink:0;box-shadow:0 0 20px rgba(245,158,11,.2)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}

/* ═══════════════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════════════ */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;animation:slideUp .4s var(--ease) .05s both}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px 22px;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;transition:transform .2s var(--ease),border-color .2s var(--ease)}
.stat-card:hover{transform:translateY(-2px);border-color:var(--border-hi)}
.stat-card::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;pointer-events:none;opacity:.7}
.sc-blue::after  {background:radial-gradient(circle,var(--blue-dim),  transparent 70%)}
.sc-amber::after {background:radial-gradient(circle,var(--amber-dim), transparent 70%)}
.sc-violet::after{background:radial-gradient(circle,var(--violet-dim),transparent 70%)}
.stat-ico{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;font-size:.95rem;flex-shrink:0}
.sc-blue  .stat-ico{background:var(--blue-dim);  color:var(--blue)}
.sc-amber .stat-ico{background:var(--amber-dim); color:var(--amber)}
.sc-violet .stat-ico{background:var(--violet-dim);color:var(--violet)}
.stat-val{font-size:1.6rem;font-weight:800;font-family:'JetBrains Mono',monospace;color:var(--text);line-height:1;margin-bottom:2px}
.stat-lbl{font-size:.7rem;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;font-weight:500}

/* ═══════════════════════════════════════════════
   PODIUM
═══════════════════════════════════════════════ */
.podium-section{animation:slideUp .4s var(--ease) .1s both}
.podium-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:36px 28px 28px;
  position:relative;overflow:hidden;
}
.podium-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--blue),var(--violet),var(--amber));
}
.podium-card::after{
  content:'';position:absolute;top:-80px;left:50%;transform:translateX(-50%);
  width:400px;height:300px;border-radius:50%;
  background:radial-gradient(circle,rgba(245,158,11,.06) 0%,transparent 70%);
  pointer-events:none;
}
.podium-inner{display:flex;justify-content:center;align-items:flex-end;gap:0;position:relative;z-index:1}

/* Individual podium slots */
.podium-slot{display:flex;flex-direction:column;align-items:center;gap:10px;position:relative}

/* Order: 2nd | 1st | 3rd */
.p-2{order:1}
.p-1{order:2;margin: 0 -8px}
.p-3{order:3}

/* Floating avatar */
.podium-avatar-wrap{position:relative}
.podium-img{
  border-radius:50%;object-fit:cover;border:3px solid;
  display:block;box-shadow:0 6px 24px rgba(0,0,0,.4);
}
.p-1 .podium-img{width:96px;height:96px;border-color:var(--amber)}
.p-2 .podium-img{width:76px;height:76px;border-color:#94a3b8}
.p-3 .podium-img{width:76px;height:76px;border-color:#cd7f32}

.podium-crown{
  position:absolute;top:-18px;left:50%;transform:translateX(-50%);
  font-size:1.4rem;filter:drop-shadow(0 2px 6px rgba(0,0,0,.4));
  animation:crownFloat 3s ease-in-out infinite;
}
@keyframes crownFloat{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(-4px)}}

.podium-rank-num{
  position:absolute;bottom:-4px;right:-4px;
  width:24px;height:24px;border-radius:50%;
  display:grid;place-items:center;font-size:.68rem;font-weight:800;
  border:2px solid var(--bg2);
  font-family:'JetBrains Mono',monospace;
}
.p-1 .podium-rank-num{background:var(--amber);   color:#1a1000}
.p-2 .podium-rank-num{background:#94a3b8;        color:#111}
.p-3 .podium-rank-num{background:#cd7f32;        color:#fff}

/* Podium plinth */
.podium-plinth{
  width:110px;border-radius:10px 10px 0 0;
  display:flex;flex-direction:column;align-items:center;
  justify-content:flex-end;padding-bottom:12px;
}
.p-1 .podium-plinth{height:90px; background:linear-gradient(180deg,rgba(245,158,11,.2),rgba(245,158,11,.06));border:1px solid rgba(245,158,11,.18)}
.p-2 .podium-plinth{height:68px; background:linear-gradient(180deg,rgba(148,163,184,.15),rgba(148,163,184,.04));border:1px solid rgba(148,163,184,.15)}
.p-3 .podium-plinth{height:52px; background:linear-gradient(180deg,rgba(205,127,50,.15),rgba(205,127,50,.04));border:1px solid rgba(205,127,50,.15)}

.podium-name{font-size:.82rem;font-weight:700;color:var(--text);text-align:center;max-width:90px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.p-1 .podium-name{font-size:.9rem}
.podium-pts{font-size:.72rem;font-family:'JetBrains Mono',monospace;font-weight:600;text-align:center}
.p-1 .podium-pts{color:var(--amber)}
.p-2 .podium-pts{color:#94a3b8}
.p-3 .podium-pts{color:#cd7f32}

/* ═══════════════════════════════════════════════
   YOUR RANK BANNER
═══════════════════════════════════════════════ */
.my-rank-banner{
  background:linear-gradient(135deg,#1a1040 0%,#0f1a3d 50%,#0a1628 100%);
  border:1px solid rgba(245,158,11,.18);
  border-radius:var(--radius);padding:22px 26px;
  display:flex;align-items:center;gap:20px;flex-wrap:wrap;
  position:relative;overflow:hidden;
  animation:slideUp .4s var(--ease) .15s both;
}
.my-rank-banner::before{content:'';position:absolute;top:-60px;right:-40px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(245,158,11,.1) 0%,transparent 70%);pointer-events:none}
.mrb-avatar{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;font-size:1.1rem;font-weight:800;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));border:2px solid rgba(255,255,255,.1);box-shadow:0 4px 14px rgba(0,0,0,.3)}
.mrb-info{}
.mrb-name{font-size:1rem;font-weight:700;letter-spacing:-.01em;margin-bottom:2px}
.mrb-email{font-size:.76rem;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:5px}
.mrb-stats{display:flex;gap:14px;margin-left:auto}
.mrb-stat{
  text-align:center;padding:10px 20px;border-radius:var(--radius-sm);
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);
  min-width:80px;
}
.mrb-stat-val{font-size:1.5rem;font-weight:800;font-family:'JetBrains Mono',monospace;color:#fff;line-height:1}
.mrb-stat-lbl{font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em;margin-top:3px}
.p-1-rank .mrb-stat-val{color:var(--amber)}

/* ═══════════════════════════════════════════════
   LEADERBOARD TABLE CARD
═══════════════════════════════════════════════ */
.lb-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;animation:slideUp .4s var(--ease) .2s both}
.lb-top{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border)}
.lb-title{font-size:.95rem;font-weight:700}
.lb-sub{font-size:.72rem;color:var(--text3);margin-top:1px}
.lb-controls{display:flex;gap:8px;align-items:center}
.search-wrap{display:flex;align-items:center;gap:7px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:7px 12px}
.search-wrap input{background:none;border:none;outline:none;font-family:'Outfit',sans-serif;font-size:.82rem;color:var(--text);width:160px}
.search-wrap input::placeholder{color:var(--text3)}
.search-wrap i{color:var(--text3);font-size:.8rem}
.filter-select{padding:7px 12px;border-radius:var(--radius-sm);background:var(--bg3);border:1px solid var(--border);font-family:'Outfit',sans-serif;font-size:.78rem;font-weight:500;color:var(--text2);cursor:pointer;outline:none}
.filter-select option{background:var(--bg2)}

table{width:100%;border-collapse:collapse}
thead th{padding:11px 20px;text-align:left;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);background:var(--bg3);border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s var(--ease)}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--bg3)}
tbody tr.is-me{background:linear-gradient(90deg,rgba(245,158,11,.06),transparent);border-left:2px solid var(--amber)}
tbody td{padding:12px 20px;font-size:.84rem;color:var(--text2);vertical-align:middle}

/* Rank number */
.rank-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:.72rem;font-weight:800;font-family:'JetBrains Mono',monospace}
.rn-1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#1a1000}
.rn-2{background:linear-gradient(135deg,#cbd5e1,#94a3b8);color:#111}
.rn-3{background:linear-gradient(135deg,#cd7f32,#a0522d);color:#fff}
.rn-n{background:var(--bg3);color:var(--text3)}

/* Student name cell */
.stu-cell{display:flex;align-items:center;gap:10px}
.stu-av{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0}
.stu-name{font-size:.875rem;font-weight:600;color:var(--text)}
.stu-you{font-size:.68rem;background:var(--amber-dim);color:var(--amber);padding:1px 6px;border-radius:10px;font-weight:700;margin-left:4px}
.stu-user{font-size:.72rem;color:var(--text3);font-family:'JetBrains Mono',monospace}

/* Points cell */
.pts-cell{font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:700;color:var(--text)}
.pts-bar{width:60px;height:3px;background:var(--bg3);border-radius:2px;margin-top:4px;overflow:hidden}
.pts-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--blue),var(--violet))}

/* Badge */
.rank-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:.7rem;font-weight:700}

/* No results */
#noResults{display:none;padding:36px;text-align:center;color:var(--text3);font-size:.875rem}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1100px){
  .stats-row{grid-template-columns:1fr 1fr 1fr}
  .podium-inner{gap:4px}
}
@media(max-width:900px){
  .mrb-stats{flex-wrap:wrap;gap:8px}
  .lb-controls{flex-wrap:wrap;gap:6px}
  .search-wrap input{width:120px}
}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .stats-row{grid-template-columns:1fr 1fr}
  .p-1 .podium-img{width:72px;height:72px}
  .p-2 .podium-img,.p-3 .podium-img{width:58px;height:58px}
  .p-1 .podium-plinth{height:72px;width:90px}
  .p-2 .podium-plinth{height:54px;width:80px}
  .p-3 .podium-plinth{height:42px;width:80px}
  thead th:nth-child(3),tbody td:nth-child(3),
  thead th:nth-child(5),tbody td:nth-child(5),
  thead th:nth-child(6),tbody td:nth-child(6){display:none}
}
@media(max-width:480px){
  .stats-row{grid-template-columns:1fr}
  .mrb-stats{gap:6px}
  .mrb-stat{padding:8px 14px;min-width:60px}
}
</style>
</head>
<body>
<div class="loading-screen" id="loadingScreen">
  <div class="loader"></div>
  <div class="loading-text">Loading Rankings...</div>
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
    <a href="lessons.php"   class="nav-link"><i class="fas fa-book-open"></i> Lessons</a>
    <a href="favorites.php" class="nav-link"><i class="fas fa-heart"></i> Favorites</a>
    <div class="nav-group-label">Community</div>
    <a href="rankings.php"  class="nav-link active"><i class="fas fa-trophy"></i> Rankings</a>
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
      <div class="topbar-title">Rankings</div>
      <div class="topbar-sub">Top performing students</div>
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

    <!-- ── Page header ─────────────────────── -->
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon"><i class="fas fa-trophy"></i></div>
        <div>
          <div class="page-title">Leaderboard Rankings</div>
          <div class="page-sub">Top 50 students ranked by total points</div>
        </div>
      </div>
    </div>

    <!-- ── Stats row ────────────────────────── -->
    <div class="stats-row">
      <div class="stat-card sc-blue">
        <div class="stat-ico"><i class="fas fa-users"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo $total_students; ?>">0</div>
          <div class="stat-lbl">Total Students</div>
        </div>
      </div>
      <div class="stat-card sc-amber">
        <div class="stat-ico"><i class="fas fa-star"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo (int)$total_points_all; ?>">0</div>
          <div class="stat-lbl">Points Earned</div>
        </div>
      </div>
      <div class="stat-card sc-violet">
        <div class="stat-ico"><i class="fas fa-chart-line"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo (int)round($avg_points); ?>">0</div>
          <div class="stat-lbl">Average Points</div>
        </div>
      </div>
    </div>

    <!-- ── Podium ──────────────────────────── -->
    <?php if (!empty($podium)): ?>
    <div class="podium-section">
      <div class="podium-card">
        <div class="podium-inner">

          <?php /* 2nd place */ if (isset($podium[1])): ?>
          <div class="podium-slot p-2">
            <div class="podium-avatar-wrap">
              <img class="podium-img" src="../assets/uploads/profiles/<?php echo htmlspecialchars($podium[1]['profile_picture']); ?>" alt="2nd">
              <span class="podium-rank-num">2</span>
            </div>
            <div class="podium-name"><?php echo htmlspecialchars(explode(' ',$podium[1]['full_name'])[0]); ?></div>
            <div class="podium-pts"><?php echo number_format($podium[1]['points']); ?> pts</div>
            <div class="podium-plinth"></div>
          </div>
          <?php endif; ?>

          <?php /* 1st place */ if (isset($podium[0])): ?>
          <div class="podium-slot p-1">
            <div class="podium-crown">👑</div>
            <div class="podium-avatar-wrap" style="margin-top:22px">
              <img class="podium-img" src="../assets/uploads/profiles/<?php echo htmlspecialchars($podium[0]['profile_picture']); ?>" alt="1st">
              <span class="podium-rank-num">1</span>
            </div>
            <div class="podium-name"><?php echo htmlspecialchars(explode(' ',$podium[0]['full_name'])[0]); ?></div>
            <div class="podium-pts"><?php echo number_format($podium[0]['points']); ?> pts</div>
            <div class="podium-plinth"></div>
          </div>
          <?php endif; ?>

          <?php /* 3rd place */ if (isset($podium[2])): ?>
          <div class="podium-slot p-3">
            <div class="podium-avatar-wrap">
              <img class="podium-img" src="../assets/uploads/profiles/<?php echo htmlspecialchars($podium[2]['profile_picture']); ?>" alt="3rd">
              <span class="podium-rank-num">3</span>
            </div>
            <div class="podium-name"><?php echo htmlspecialchars(explode(' ',$podium[2]['full_name'])[0]); ?></div>
            <div class="podium-pts"><?php echo number_format($podium[2]['points']); ?> pts</div>
            <div class="podium-plinth"></div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Your rank banner ─────────────────── -->
    <div class="my-rank-banner">
      <div class="mrb-avatar"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
      <div class="mrb-info">
        <div class="mrb-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="mrb-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
      </div>
      <div class="mrb-stats">
        <div class="mrb-stat <?php echo $user_rank===1?'p-1-rank':'' ?>">
          <div class="mrb-stat-val">#<?php echo $user_rank; ?></div>
          <div class="mrb-stat-lbl">Your Rank</div>
        </div>
        <div class="mrb-stat">
          <div class="mrb-stat-val"><?php echo number_format($user['points'] ?? 0); ?></div>
          <div class="mrb-stat-lbl">Your Points</div>
        </div>
      </div>
    </div>

    <!-- ── Leaderboard table ────────────────── -->
    <div class="lb-card">
      <div class="lb-top">
        <div>
          <div class="lb-title">Full Leaderboard</div>
          <div class="lb-sub"><?php echo count($rows); ?> students ranked</div>
        </div>
        <div class="lb-controls">
          <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Search students…" oninput="applyFilter()">
          </div>
          <select class="filter-select" id="badgeFilter" onchange="applyFilter()">
            <option value="all">All Badges</option>
            <option value="Champion">🥇 Champion</option>
            <option value="Silver">🥈 Silver</option>
            <option value="Bronze">🥉 Bronze</option>
            <option value="Elite">💎 Elite</option>
            <option value="Rising Star">⭐ Rising Star</option>
            <option value="Beginner">🌱 Beginner</option>
          </select>
        </div>
      </div>

      <div style="overflow-x:auto">
        <table id="lbTable">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Student</th>
              <th>Username</th>
              <th>Points</th>
              <th>Lessons</th>
              <th>Generated</th>
              <th>Badge</th>
            </tr>
          </thead>
          <tbody id="lbBody">
            <?php
            $maxPts = max(1, (int)($rows[0]['points'] ?? 1));
            foreach ($rows as $i => $s):
              $isMe = ($s['id'] == $user_id);
              $pos  = (int)$s['rank_position'];
              $pts  = (int)$s['points'];
              [$bIcon, $bLabel, $bColor, $bBg] = rankBadge($pos, $pts);
              $rnClass = $pos===1?'rn-1':($pos===2?'rn-2':($pos===3?'rn-3':'rn-n'));
              $aClr    = $avatarColors[$i % count($avatarColors)];
              $barPct  = min(100, ($pts / $maxPts) * 100);
            ?>
            <tr class="<?php echo $isMe?'is-me':'' ?>"
                data-name="<?php echo htmlspecialchars(strtolower($s['full_name'])) ?>"
                data-badge="<?php echo htmlspecialchars($bLabel) ?>">
              <td><span class="rank-num <?php echo $rnClass ?>"><?php echo $pos ?></span></td>
              <td>
                <div class="stu-cell">
                  <div class="stu-av" style="background:<?php echo $aClr ?>"><?php echo strtoupper(substr($s['full_name'],0,1)) ?></div>
                  <div>
                    <div class="stu-name">
                      <?php echo htmlspecialchars($s['full_name']) ?>
                      <?php if ($isMe): ?><span class="stu-you">You</span><?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td><span class="stu-user">@<?php echo htmlspecialchars($s['username']) ?></span></td>
              <td>
                <div class="pts-cell"><?php echo number_format($pts) ?></div>
                <div class="pts-bar"><div class="pts-fill" style="width:<?php echo $barPct ?>%"></div></div>
              </td>
              <td style="font-family:'JetBrains Mono',monospace;font-size:.8rem"><?php echo $s['lessons_completed'] ?? 0 ?></td>
              <td style="font-family:'JetBrains Mono',monospace;font-size:.8rem"><?php echo $s['generated_count'] ?? 0 ?></td>
              <td>
                <span class="rank-badge" style="background:<?php echo $bBg ?>;color:<?php echo $bColor ?>">
                  <?php echo $bIcon ?> <?php echo $bLabel ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div id="noResults">
          <i class="fas fa-magnifying-glass" style="font-size:1.5rem;margin-bottom:8px;display:block"></i>
          No students match your search.
        </div>
      </div>
    </div>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// Loading Screen
window.addEventListener('load', function() {
  setTimeout(() => {
    document.getElementById('loadingScreen').classList.add('hidden');
  }, 1300);
});

// ── Count-up ──────────────────────────────────────────────
document.querySelectorAll('.stat-val[data-count]').forEach(el => {
  const target = parseInt(el.dataset.count, 10);
  if (!target) { el.textContent = '0'; return; }
  let cur = 0;
  const step = Math.max(1, Math.ceil(target / 50));
  const t = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = cur >= 1000 ? (cur/1000).toFixed(1) + 'k' : cur;
    if (cur >= target) { el.textContent = target >= 1000 ? (target/1000).toFixed(1)+'k' : target; clearInterval(t); }
  }, 25);
});

// ── Search + badge filter ─────────────────────────────────
function applyFilter() {
  const query  = document.getElementById('searchInput').value.toLowerCase().trim();
  const badge  = document.getElementById('badgeFilter').value;
  const rows   = document.querySelectorAll('#lbBody tr');
  let  visible = 0;

  rows.forEach(row => {
    const nameMatch  = !query || row.dataset.name.includes(query);
    const badgeMatch = badge === 'all' || row.dataset.badge === badge;
    const show = nameMatch && badgeMatch;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
}


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