<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$_SESSION['full_name']       = $user['full_name'];
$_SESSION['profile_picture'] = $user['profile_picture'];
$streak = $user['login_streak'];

function getUserAnalytics($conn, $user_id, $days) {
    $data = []; $labels = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date     = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('D, M d', strtotime($date));
        $stmt = $conn->prepare("SELECT COALESCE(SUM(count),0) as total FROM user_activity WHERE user_id=? AND activity_date=?");
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $data[] = (int)$stmt->get_result()->fetch_assoc()['total'];
    }
    return ['labels' => $labels, 'data' => $data];
}

$analytics_7d  = getUserAnalytics($conn, $user_id, 7);
$analytics_14d = getUserAnalytics($conn, $user_id, 14);
$analytics_30d = getUserAnalytics($conn, $user_id, 30);

$breakdown_query = $conn->prepare("
    SELECT activity_type, SUM(count) as total
    FROM user_activity WHERE user_id=? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY activity_type");
$breakdown_query->bind_param("i", $user_id);
$breakdown_query->execute();
$breakdown = $breakdown_query->get_result();
$activity_types = []; $activity_counts = [];
$activity_colors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b'];
while ($row = $breakdown->fetch_assoc()) {
    $activity_types[]  = ucfirst($row['activity_type']);
    $activity_counts[] = $row['total'];
}
if (empty($activity_types)) {
    $activity_types  = ['Login','Lesson','Generate','Favorite'];
    $activity_counts = [0,0,0,0];
}

$cp = $conn->prepare("SELECT COUNT(*) as total FROM user_activity WHERE user_id=? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$cp->bind_param("i", $user_id); $cp->execute();
$current = $cp->get_result()->fetch_assoc()['total'];

$pp = $conn->prepare("SELECT COUNT(*) as total FROM user_activity WHERE user_id=? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND activity_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$pp->bind_param("i", $user_id); $pp->execute();
$previous = $pp->get_result()->fetch_assoc()['total'];
$trend = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 100;

$lt = $conn->prepare("SELECT COUNT(*) as count FROM user_activity WHERE user_id=? AND activity_type='lesson'");
$lt->bind_param("i", $user_id); $lt->execute();
$lessons_count = $lt->get_result()->fetch_assoc()['count'];

$gt = $conn->prepare("SELECT COUNT(*) as count FROM user_activity WHERE user_id=? AND activity_type='generate'");
$gt->bind_param("i", $user_id); $gt->execute();
$generated_count = $gt->get_result()->fetch_assoc()['count'];

$students = $conn->query("
    SELECT u.id, u.full_name, u.student_id, u.profile_picture,
           r.total_points,
           RANK() OVER (ORDER BY r.total_points DESC) as rank_position
    FROM users u JOIN rankings r ON u.id = r.user_id
    WHERE u.role = 'student'
    ORDER BY r.total_points DESC LIMIT 10");

$ra = $conn->prepare("SELECT activity_type, activity_date, count FROM user_activity WHERE user_id=? ORDER BY activity_date DESC, id DESC LIMIT 5");
$ra->bind_param("i", $user_id); $ra->execute();
$recent = $ra->get_result();

$da = $conn->prepare("
    SELECT activity_date,
           SUM(CASE WHEN activity_type='login'    THEN count ELSE 0 END) as logins,
           SUM(CASE WHEN activity_type='lesson'   THEN count ELSE 0 END) as lessons,
           SUM(CASE WHEN activity_type='generate' THEN count ELSE 0 END) as generates,
           SUM(CASE WHEN activity_type='favorite' THEN count ELSE 0 END) as favorites,
           SUM(count) as total
    FROM user_activity WHERE user_id=? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY activity_date ORDER BY activity_date DESC");
$da->bind_param("i", $user_id); $da->execute();
$daily = $da->get_result();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ═══════════════════════════════════════════════════
   RESET & VARIABLES
═══════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --bg:          #07080f;
  --bg2:         #0c0e1a;
  --bg3:         #111320;
  --surface:     #161929;
  --surface2:    #1c2135;
  --border:      rgba(255,255,255,.055);
  --border-hi:   rgba(255,255,255,.11);

  --blue:        #3b82f6;
  --blue-dim:    rgba(59,130,246,.14);
  --violet:      #8b5cf6;
  --violet-dim:  rgba(139,92,246,.13);
  --emerald:     #10b981;
  --emerald-dim: rgba(16,185,129,.13);
  --amber:       #f59e0b;
  --amber-dim:   rgba(245,158,11,.12);
  --rose:        #f43f5e;
  --rose-dim:    rgba(244,63,94,.12);
  --cyan:        #06b6d4;
  --cyan-dim:    rgba(6,182,212,.12);

  --text:        #dde2f0;
  --text2:       #8892aa;
  --text3:       #4a5270;

  --sidebar-w:   252px;
  --topbar-h:    66px;
  --radius:      14px;
  --radius-sm:   9px;
  --ease:        cubic-bezier(.4,0,.2,1);
}

html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;line-height:1.6}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ═══════════════════════════════════════════════════
   SHELL
═══════════════════════════════════════════════════ */
.shell{display:flex;min-height:100vh}

/* ═══════════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════════ */
.sidebar{
  width:var(--sidebar-w);
  background:var(--bg2);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;inset:0 auto 0 0;
  z-index:200;transition:transform .3s var(--ease);
}

.sidebar-logo{
  padding:24px 20px 20px;
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:11px;
}
.logo-mark{
  width:36px;height:36px;border-radius:9px;
  background:linear-gradient(135deg,var(--blue),var(--violet));
  display:grid;place-items:center;font-size:1rem;flex-shrink:0;
  box-shadow:0 0 18px rgba(59,130,246,.25);
}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem;letter-spacing:.04em}

.nav-group-label{
  font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--text3);padding:18px 20px 6px;
}

.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}

.nav-link{
  display:flex;align-items:center;gap:11px;
  padding:10px 14px;margin:2px 8px;
  border-radius:var(--radius-sm);
  color:var(--text2);text-decoration:none;
  font-size:.875rem;font-weight:500;
  transition:all .18s var(--ease);position:relative;
}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{
  background:linear-gradient(90deg,var(--blue-dim),transparent);
  color:var(--blue);border-left:2px solid var(--blue);padding-left:12px;
}
.nav-link.active i{color:var(--blue)}

.sidebar-footer{
  padding:14px 8px;border-top:1px solid var(--border);
}
.user-chip{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:var(--radius-sm);background:var(--surface);
}
.user-chip-avatar{
  width:32px;height:32px;border-radius:50%;
  display:grid;place-items:center;font-size:.78rem;font-weight:700;
  color:#fff;flex-shrink:0;
  background:linear-gradient(135deg,var(--blue),var(--violet));
}
.user-chip-name{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-chip-role{font-size:.66rem;color:var(--text3)}

/* ═══════════════════════════════════════════════════
   MAIN
═══════════════════════════════════════════════════ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* ── Topbar ── */
.topbar{
  height:var(--topbar-h);display:flex;align-items:center;
  padding:0 28px;gap:14px;
  background:var(--bg2);border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:100;
}
.hamburger{
  display:none;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius-sm);width:36px;height:36px;
  place-items:center;color:var(--text2);cursor:pointer;font-size:.95rem;
}
.topbar-info{}
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.top-nav-links{display:flex;align-items:center;gap:4px}
.top-nav-link{
  display:flex;align-items:center;gap:6px;padding:7px 13px;
  border-radius:var(--radius-sm);font-size:.8rem;font-weight:500;
  color:var(--text2);text-decoration:none;transition:all .18s var(--ease);
}
.top-nav-link:hover{background:var(--surface);color:var(--text)}
.top-nav-link.active{background:var(--blue-dim);color:var(--blue)}
.top-nav-link i{font-size:.8rem}

.icon-btn{
  width:36px;height:36px;border-radius:var(--radius-sm);
  background:var(--surface);border:1px solid var(--border);
  display:grid;place-items:center;color:var(--text2);
  cursor:pointer;transition:all .18s var(--ease);
  text-decoration:none;font-size:.88rem;position:relative;
}
.icon-btn:hover{border-color:var(--border-hi);color:var(--text)}
.notif-pip{
  position:absolute;top:7px;right:7px;width:6px;height:6px;
  background:var(--rose);border-radius:50%;border:2px solid var(--bg2);
}

.user-pill{
  display:flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;
  border-radius:30px;background:var(--surface);border:1px solid var(--border);
  cursor:pointer;text-decoration:none;transition:border-color .18s var(--ease);
}
.user-pill:hover{border-color:var(--border-hi)}
.pill-avatar{
  width:28px;height:28px;border-radius:50%;
  display:grid;place-items:center;font-size:.72rem;font-weight:700;
  color:#fff;flex-shrink:0;
  background:linear-gradient(135deg,var(--blue),var(--violet));
}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════════════════
   PAGE
═══════════════════════════════════════════════════ */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:22px}

/* ═══════════════════════════════════════════════════
   STREAK BANNER
═══════════════════════════════════════════════════ */
.streak-banner{
  background:linear-gradient(135deg,#1a1040 0%,#0f1a3d 50%,#0a1628 100%);
  border:1px solid rgba(139,92,246,.2);
  border-radius:var(--radius);
  padding:20px 26px;
  display:flex;align-items:center;gap:20px;
  position:relative;overflow:hidden;
  animation:slideUp .45s var(--ease) both;
}
.streak-banner::before{
  content:'';position:absolute;top:-60px;right:-40px;
  width:220px;height:220px;border-radius:50%;
  background:radial-gradient(circle,rgba(139,92,246,.18) 0%,transparent 70%);
  pointer-events:none;
}
.streak-flame{
  font-size:2.6rem;filter:drop-shadow(0 0 14px rgba(251,146,60,.6));
  animation:flicker 1.8s ease-in-out infinite alternate;
  flex-shrink:0;
}
@keyframes flicker{from{transform:scale(1) rotate(-3deg)}to{transform:scale(1.08) rotate(3deg)}}
.streak-body{}
.streak-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:2px}
.streak-count{
  font-size:1.8rem;font-weight:800;letter-spacing:-.03em;
  font-family:'JetBrains Mono',monospace;
  background:linear-gradient(90deg,#fb923c,#f97316);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.streak-sub{font-size:.8rem;color:var(--text2);margin-top:2px}
.streak-right{margin-left:auto;text-align:right}
.streak-best{font-size:.72rem;color:var(--text3)}
.streak-best span{color:var(--amber);font-weight:700;font-family:'JetBrains Mono',monospace}

/* ═══════════════════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════════════════ */
.stats-row{
  display:grid;grid-template-columns:repeat(4,1fr);gap:14px;
}
.stat-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:20px 22px;
  display:flex;flex-direction:column;gap:12px;
  position:relative;overflow:hidden;
  transition:transform .2s var(--ease),border-color .2s var(--ease);
  animation:slideUp .45s var(--ease) both;
}
.stat-card:nth-child(1){animation-delay:.06s}
.stat-card:nth-child(2){animation-delay:.10s}
.stat-card:nth-child(3){animation-delay:.14s}
.stat-card:nth-child(4){animation-delay:.18s}
.stat-card:hover{transform:translateY(-3px);border-color:var(--border-hi)}
.stat-card::after{
  content:'';position:absolute;top:-40px;right:-40px;
  width:130px;height:130px;border-radius:50%;pointer-events:none;opacity:.7;
}
.stat-card.c-blue::after   {background:radial-gradient(circle,var(--blue-dim),   transparent 70%)}
.stat-card.c-violet::after {background:radial-gradient(circle,var(--violet-dim), transparent 70%)}
.stat-card.c-emerald::after{background:radial-gradient(circle,var(--emerald-dim),transparent 70%)}
.stat-card.c-amber::after  {background:radial-gradient(circle,var(--amber-dim),  transparent 70%)}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between}
.stat-icon{
  width:40px;height:40px;border-radius:10px;
  display:grid;place-items:center;font-size:.95rem;flex-shrink:0;
}
.c-blue   .stat-icon{background:var(--blue-dim);   color:var(--blue)}
.c-violet .stat-icon{background:var(--violet-dim); color:var(--violet)}
.c-emerald .stat-icon{background:var(--emerald-dim);color:var(--emerald)}
.c-amber  .stat-icon{background:var(--amber-dim);  color:var(--amber)}
.trend-chip{
  font-size:.68rem;font-weight:700;padding:3px 8px;border-radius:20px;
  font-family:'JetBrains Mono',monospace;
}
.trend-pos{background:var(--emerald-dim);color:var(--emerald)}
.trend-neg{background:var(--rose-dim);   color:var(--rose)}
.trend-live{background:var(--rose-dim);  color:var(--rose);animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
.stat-label{font-size:.72rem;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;font-weight:500}
.stat-value{
  font-size:2rem;font-weight:800;letter-spacing:-.04em;line-height:1;
  font-family:'JetBrains Mono',monospace;color:var(--text);
}
.stat-desc{font-size:.7rem;color:var(--text3)}

/* ═══════════════════════════════════════════════════
   CHARTS ROW
═══════════════════════════════════════════════════ */
.charts-row{display:grid;grid-template-columns:1fr 300px;gap:16px}

.chart-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:22px 24px;
  animation:slideUp .5s var(--ease) .22s both;
}
.card-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px}
.card-title{font-size:.9rem;font-weight:700;letter-spacing:-.01em}
.card-sub{font-size:.72rem;color:var(--text3);margin-top:2px}

/* Time range selector */
.time-range{display:flex;gap:6px}
.time-btn{
  padding:5px 12px;border-radius:var(--radius-sm);
  background:transparent;border:1px solid var(--border);
  font-family:'Outfit',sans-serif;font-size:.75rem;font-weight:600;
  color:var(--text3);cursor:pointer;transition:all .18s var(--ease);
}
.time-btn:hover{border-color:var(--border-hi);color:var(--text2)}
.time-btn.active{background:var(--blue-dim);border-color:var(--blue);color:var(--blue)}

.chart-canvas-wrap{height:240px;position:relative;margin-bottom:18px}

/* Mini stats under chart */
.mini-row{
  display:grid;grid-template-columns:repeat(4,1fr);gap:10px;
  padding-top:16px;border-top:1px solid var(--border);
}
.mini-cell{
  background:var(--bg3);border-radius:var(--radius-sm);
  padding:12px 10px;text-align:center;
}
.mini-val{
  font-size:1.15rem;font-weight:700;color:var(--text);
  font-family:'JetBrains Mono',monospace;margin-bottom:3px;
}
.mini-label{font-size:.65rem;color:var(--text3);text-transform:uppercase;letter-spacing:.06em}

/* Breakdown donut card */
.donut-wrap{height:190px;position:relative;margin-bottom:16px}
.breakdown-legend{display:flex;flex-direction:column;gap:7px;margin-top:4px}
.legend-row{display:flex;align-items:center;justify-content:space-between;font-size:.8rem}
.legend-left{display:flex;align-items:center;gap:8px;color:var(--text2)}
.legend-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.legend-val{font-family:'JetBrains Mono',monospace;font-size:.8rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════════════════
   DAILY TABLE
═══════════════════════════════════════════════════ */
.table-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  animation:slideUp .5s var(--ease) .28s both;
}
.table-top{
  display:flex;align-items:center;justify-content:space-between;
  padding:18px 22px;border-bottom:1px solid var(--border);
}
table{width:100%;border-collapse:collapse}
thead th{
  padding:11px 22px;text-align:left;
  font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
  color:var(--text3);background:var(--bg3);border-bottom:1px solid var(--border);
  white-space:nowrap;
}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s var(--ease)}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--bg3)}
tbody td{padding:12px 22px;font-size:.84rem;color:var(--text2);vertical-align:middle}
.date-strong{font-weight:600;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:.78rem}

/* Activity pills */
.act-pill{
  display:inline-flex;align-items:center;padding:2px 9px;
  border-radius:20px;font-size:.7rem;font-weight:600;
  font-family:'JetBrains Mono',monospace;
}
.ap-login   {background:var(--blue-dim);   color:var(--blue)}
.ap-lesson  {background:var(--violet-dim); color:var(--violet)}
.ap-generate{background:var(--emerald-dim);color:var(--emerald)}
.ap-favorite{background:var(--amber-dim);  color:var(--amber)}
.ap-total   {background:var(--border);     color:var(--text);font-weight:700}

/* ═══════════════════════════════════════════════════
   RECENT ACTIVITY
═══════════════════════════════════════════════════ */
.activity-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:22px 24px;
  animation:slideUp .5s var(--ease) .32s both;
}
.activity-list{display:flex;flex-direction:column;gap:2px;margin-top:16px}
.activity-item{
  display:flex;align-items:center;gap:14px;
  padding:12px 14px;border-radius:var(--radius-sm);
  transition:background .15s var(--ease);
}
.activity-item:hover{background:var(--bg3)}
.act-icon{
  width:36px;height:36px;border-radius:9px;
  display:grid;place-items:center;font-size:.88rem;flex-shrink:0;
}
.act-body{flex:1;min-width:0}
.act-title{font-size:.84rem;font-weight:600;color:var(--text);margin-bottom:1px}
.act-meta{font-size:.7rem;color:var(--text3)}
.act-count{
  font-family:'JetBrains Mono',monospace;font-size:.8rem;
  font-weight:700;color:var(--emerald);
  background:var(--emerald-dim);padding:2px 8px;border-radius:20px;
}

/* ═══════════════════════════════════════════════════
   LEADERBOARD
═══════════════════════════════════════════════════ */
.leaderboard-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  animation:slideUp .5s var(--ease) .36s both;
}

.rank-medal{
  display:inline-flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:50%;
  font-size:.72rem;font-weight:800;font-family:'JetBrains Mono',monospace;
}
.rank-1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#1a1000}
.rank-2{background:linear-gradient(135deg,#cbd5e1,#94a3b8);color:#1a1a1a}
.rank-3{background:linear-gradient(135deg,#cd7f32,#a0522d);color:#fff}
.rank-n{background:var(--bg3);color:var(--text3)}

.student-cell{display:flex;align-items:center;gap:10px}
.stu-avatar{
  width:32px;height:32px;border-radius:50%;
  display:grid;place-items:center;font-size:.78rem;font-weight:700;
  color:#fff;flex-shrink:0;
}
.stu-name{font-size:.84rem;font-weight:600;color:var(--text)}
.stu-id{font-size:.7rem;color:var(--text3);font-family:'JetBrains Mono',monospace}

.pts-bar-wrap{width:80px}
.pts-bar{height:4px;background:var(--bg3);border-radius:2px;overflow:hidden}
.pts-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--blue),var(--violet))}
.pts-val{font-family:'JetBrains Mono',monospace;font-size:.78rem;font-weight:700;color:var(--text);margin-top:3px}

.badge-champ  {background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#1a1000}
.badge-silver {background:linear-gradient(90deg,#cbd5e1,#94a3b8);color:#1a1a1a}
.badge-bronze {background:linear-gradient(90deg,#cd7f32,#a0522d);color:#fff}
.badge-star   {background:var(--blue-dim);color:var(--blue)}

.rank-badge{
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 9px;border-radius:20px;font-size:.7rem;font-weight:700;
}

.btn-link{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:var(--radius-sm);
  border:1px solid var(--border-hi);background:transparent;
  color:var(--text2);font-family:'Outfit',sans-serif;
  font-size:.78rem;font-weight:600;text-decoration:none;
  cursor:pointer;transition:all .18s var(--ease);
}
.btn-link:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-dim)}

/* ═══════════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════════ */
@keyframes slideUp{
  from{opacity:0;transform:translateY(16px)}
  to{opacity:1;transform:translateY(0)}
}

/* ── Overlay ── */
.overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px);
}

/* ═══════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════ */
@media(max-width:1280px){
  .stats-row{grid-template-columns:repeat(2,1fr)}
  .mini-row{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:1024px){
  .charts-row{grid-template-columns:1fr}
  .top-nav-links{display:none}
}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:18px 14px}
  .topbar{padding:0 14px}
  .stats-row{grid-template-columns:1fr 1fr}
  thead th:nth-child(3),thead th:nth-child(4),
  tbody td:nth-child(3),tbody td:nth-child(4){display:none}
}
@media(max-width:480px){
  .stats-row{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="shell">

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <!-- <div class="logo-mark"></div> -->
    <div class="logo-text">
      <?php echo SITE_NAME; ?>
      <span>Student Portal</span>
    </div>
  </div>

  <div class="sidebar-nav">
    <div class="nav-group-label">Main</div>
    <a href="dashboard.php" class="nav-link <?= $current_page=='dashboard.php'?'active':'' ?>"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <a href="generate.php"  class="nav-link <?= $current_page=='generate.php' ?'active':'' ?>"><i class="fas fa-wand-magic-sparkles"></i> Generate</a>
    <a href="lessons.php"   class="nav-link <?= $current_page=='lessons.php'  ?'active':'' ?>"><i class="fas fa-book-open"></i> Lessons</a>
    <a href="favorites.php" class="nav-link <?= $current_page=='favorites.php'?'active':'' ?>"><i class="fas fa-heart"></i> Favorites</a>
    <div class="nav-group-label">Community</div>
    <a href="rankings.php"  class="nav-link <?= $current_page=='rankings.php' ?'active':'' ?>"><i class="fas fa-trophy"></i> Rankings</a>
    <a href="forum.php"     class="nav-link <?= $current_page=='forum.php'    ?'active':'' ?>"><i class="fas fa-comments"></i> Forum</a>
    <div class="nav-group-label">Account</div>
    <a href="profile.php"   class="nav-link <?= $current_page=='profile.php'  ?'active':'' ?>"><i class="fas fa-user-circle"></i> My Profile</a>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-chip-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      <div>
        <div class="user-chip-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="user-chip-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="overlay" id="overlay"></div>

<!-- ═══════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <header class="topbar">
    <button class="hamburger" id="menuBtn"><i class="fas fa-bars"></i></button>

    <div class="topbar-info">
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-sub">Welcome back, <?= htmlspecialchars(explode(' ',$user['full_name'])[0]) ?> 👋</div>
    </div>

    <nav class="top-nav-links">
      <a href="generate.php"  class="top-nav-link <?= $current_page=='generate.php' ?'active':'' ?>"><i class="fas fa-wand-magic-sparkles"></i> Generate</a>
      <a href="lessons.php"   class="top-nav-link <?= $current_page=='lessons.php'  ?'active':'' ?>"><i class="fas fa-book-open"></i> Lessons</a>
      <a href="favorites.php" class="top-nav-link <?= $current_page=='favorites.php'?'active':'' ?>"><i class="fas fa-heart"></i> Favorites</a>
      <a href="rankings.php"  class="top-nav-link <?= $current_page=='rankings.php' ?'active':'' ?>"><i class="fas fa-trophy"></i> Rankings</a>
    </nav>

    <div class="topbar-right">
      <a href="notifications.php" class="icon-btn"><i class="fas fa-bell"></i><span class="notif-pip"></span></a>
      <a href="profile.php" class="user-pill">
        <div class="pill-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <span class="pill-name"><?= htmlspecialchars($user['full_name']) ?></span>
      </a>
    </div>
  </header>

  <!-- Page -->
  <div class="page">

    <!-- ── STREAK BANNER ──────────────────────────── -->
    <div class="streak-banner">
      <div class="streak-flame">🔥</div>
      <div class="streak-body">
        <div class="streak-label">Login Streak</div>
        <div class="streak-count"><?= $streak ?> Days</div>
        <div class="streak-sub">Keep learning every day to maintain your streak!</div>
      </div>
      <div class="streak-right">
        <div class="streak-best">Best streak <span><?= $streak ?> 🏆</span></div>
      </div>
    </div>

    <!-- ── STAT CARDS ─────────────────────────────── -->
    <div class="stats-row">
      <div class="stat-card c-blue">
        <div class="stat-top">
          <div class="stat-icon"><i class="fas fa-bolt-lightning"></i></div>
          <span class="trend-chip <?= $trend>=0?'trend-pos':'trend-neg' ?>">
            <?= $trend>=0?'↑':'↓' ?> <?= abs($trend) ?>%
          </span>
        </div>
        <div>
          <div class="stat-label">Activities (7d)</div>
          <div class="stat-value" data-count="<?= $current ?>">0</div>
          <div class="stat-desc">vs previous period</div>
        </div>
      </div>

      <div class="stat-card c-violet">
        <div class="stat-top">
          <div class="stat-icon"><i class="fas fa-book-open"></i></div>
          <span class="trend-chip trend-pos">↑ Lessons</span>
        </div>
        <div>
          <div class="stat-label">Lessons Completed</div>
          <div class="stat-value" data-count="<?= $lessons_count ?>">0</div>
          <div class="stat-desc">Total lesson activities</div>
        </div>
      </div>

      <div class="stat-card c-emerald">
        <div class="stat-top">
          <div class="stat-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
          <span class="trend-chip trend-pos">↑ AI</span>
        </div>
        <div>
          <div class="stat-label">Content Generated</div>
          <div class="stat-value" data-count="<?= $generated_count ?>">0</div>
          <div class="stat-desc">AI generations used</div>
        </div>
      </div>

      <div class="stat-card c-amber">
        <div class="stat-top">
          <div class="stat-icon"><i class="fas fa-star"></i></div>
          <span class="trend-chip trend-live">● Live</span>
        </div>
        <div>
          <div class="stat-label">Total Points</div>
          <div class="stat-value" data-count="<?= $user['points'] ?? 0 ?>">0</div>
          <div class="stat-desc">Ranking score</div>
        </div>
      </div>
    </div>

    <!-- ── CHARTS ──────────────────────────────────── -->
    <div class="charts-row">

      <!-- Activity line chart -->
      <div class="chart-card">
        <div class="card-head">
          <div>
            <div class="card-title">Activity Analytics</div>
            <div class="card-sub">Daily actions over selected period</div>
          </div>
          <div class="time-range" id="timeRange">
            <button class="time-btn active" onclick="switchChart(7,this)">7D</button>
            <button class="time-btn"        onclick="switchChart(14,this)">14D</button>
            <button class="time-btn"        onclick="switchChart(30,this)">30D</button>
          </div>
        </div>
        <div class="chart-canvas-wrap">
          <canvas id="activityChart"></canvas>
        </div>
        <div class="mini-row">
          <div class="mini-cell">
            <div class="mini-val" id="miniTotal"><?= array_sum($analytics_7d['data']) ?></div>
            <div class="mini-label">Total</div>
          </div>
          <div class="mini-cell">
            <div class="mini-val" id="miniAvg"><?= count($analytics_7d['data'])>0 ? round(array_sum($analytics_7d['data'])/count($analytics_7d['data']),1) : 0 ?></div>
            <div class="mini-label">Daily Avg</div>
          </div>
          <div class="mini-cell">
            <div class="mini-val" id="miniPeak"><?= max($analytics_7d['data']) ?></div>
            <div class="mini-label">Peak</div>
          </div>
          <div class="mini-cell">
            <?php
              $pk = max($analytics_7d['data']);
              $pi = array_search($pk,$analytics_7d['data']);
              $bestDay = isset($analytics_7d['labels'][$pi]) ? substr($analytics_7d['labels'][$pi],0,3) : '—';
            ?>
            <div class="mini-val" id="miniBest"><?= $bestDay ?></div>
            <div class="mini-label">Best Day</div>
          </div>
        </div>
      </div>

      <!-- Breakdown donut -->
      <div class="chart-card">
        <div class="card-head">
          <div>
            <div class="card-title">Activity Breakdown</div>
            <div class="card-sub">Last 30 days by type</div>
          </div>
        </div>
        <div class="donut-wrap">
          <canvas id="breakdownChart"></canvas>
        </div>
        <div class="breakdown-legend">
          <?php foreach($activity_types as $i=>$type): ?>
          <div class="legend-row">
            <div class="legend-left">
              <span class="legend-dot" style="background:<?= $activity_colors[$i%count($activity_colors)] ?>"></span>
              <?= $type ?>
            </div>
            <span class="legend-val"><?= $activity_counts[$i] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── DAILY BREAKDOWN TABLE ───────────────────── -->
    <div class="table-card">
      <div class="table-top">
        <div>
          <div class="card-title">Daily Activity — Last 7 Days</div>
          <div class="card-sub">Breakdown by activity type per day</div>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Logins</th>
              <th>Lessons</th>
              <th>Generate</th>
              <th>Favorites</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if($daily->num_rows > 0): ?>
              <?php while($day = $daily->fetch_assoc()): ?>
              <tr>
                <td><span class="date-strong"><?= date('d M Y',strtotime($day['activity_date'])) ?></span></td>
                <td><span class="act-pill ap-login"><?= $day['logins'] ?></span></td>
                <td><span class="act-pill ap-lesson"><?= $day['lessons'] ?></span></td>
                <td><span class="act-pill ap-generate"><?= $day['generates'] ?></span></td>
                <td><span class="act-pill ap-favorite"><?= $day['favorites'] ?></span></td>
                <td><span class="act-pill ap-total"><?= $day['total'] ?></span></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text3)">
                <i class="fas fa-circle-info"></i> No activity in the last 7 days.
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── RECENT ACTIVITY ─────────────────────────── -->
    <div class="activity-card">
      <div class="card-head">
        <div>
          <div class="card-title">Recent Activity</div>
          <div class="card-sub">Your last 5 recorded actions</div>
        </div>
      </div>
      <div class="activity-list">
        <?php
        $actIcons  = ['login'=>'fa-arrow-right-to-bracket','lesson'=>'fa-book-open','generate'=>'fa-wand-magic-sparkles','favorite'=>'fa-heart'];
        $actColors = ['login'=>'var(--blue-dim)','lesson'=>'var(--violet-dim)','generate'=>'var(--emerald-dim)','favorite'=>'var(--amber-dim)'];
        $actText   = ['login'=>'var(--blue)','lesson'=>'var(--violet)','generate'=>'var(--emerald)','favorite'=>'var(--amber)'];
        if($recent->num_rows > 0):
          while($act = $recent->fetch_assoc()):
            $t = $act['activity_type'];
            $bg  = $actColors[$t] ?? 'var(--surface2)';
            $clr = $actText[$t]   ?? 'var(--text2)';
            $ico = $actIcons[$t]  ?? 'fa-circle';
        ?>
        <div class="activity-item">
          <div class="act-icon" style="background:<?=$bg?>;color:<?=$clr?>">
            <i class="fas <?=$ico?>"></i>
          </div>
          <div class="act-body">
            <div class="act-title"><?= ucfirst($t) ?> Activity</div>
            <div class="act-meta"><?= date('M d, Y',strtotime($act['activity_date'])) ?></div>
          </div>
          <span class="act-count">+<?= $act['count'] ?></span>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:32px;color:var(--text3);font-size:.875rem">
          <i class="fas fa-circle-info"></i> No recent activity. Start learning to see it here!
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── LEADERBOARD ────────────────────────────── -->
    <div class="leaderboard-card">
      <div class="table-top">
        <div>
          <div class="card-title">🏆 Top Students Leaderboard</div>
          <div class="card-sub">Ranked by total points earned</div>
        </div>
        <a href="rankings.php" class="btn-link"><i class="fas fa-arrow-up-right-from-square"></i> View All</a>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Student</th>
              <th>Student ID</th>
              <th>Points</th>
              <th>Progress</th>
              <th>Badge</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $avatarColors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#f43f5e','#06b6d4','#ec4899','#84cc16'];
            $rank=1;
            while($s = $students->fetch_assoc()):
              $rClass = $rank==1?'rank-1':($rank==2?'rank-2':($rank==3?'rank-3':'rank-n'));
              $bClass = $rank==1?'badge-champ':($rank==2?'badge-silver':($rank==3?'badge-bronze':'badge-star'));
              $bLabel = $rank==1?'🥇 Champion':($rank==2?'🥈 Silver':($rank==3?'🥉 Bronze':'⭐ Rising'));
              $barPct = min(100, ($s['total_points']/max(1,300))*100);
              $aClr   = $avatarColors[($rank-1)%count($avatarColors)];
            ?>
            <tr>
              <td><span class="rank-medal <?=$rClass?>"><?=$rank?></span></td>
              <td>
                <div class="student-cell">
                  <div class="stu-avatar" style="background:<?=$aClr?>"><?=strtoupper(substr($s['full_name'],0,1))?></div>
                  <div>
                    <div class="stu-name"><?=htmlspecialchars($s['full_name'])?></div>
                  </div>
                </div>
              </td>
              <td><span class="stu-id"><?=htmlspecialchars($s['student_id']??'—')?></span></td>
              <td>
                <div class="pts-bar-wrap">
                  <div style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:4px"><?=$s['total_points']?> <span style="color:var(--text3);font-weight:400">pts</span></div>
                  <div class="pts-bar"><div class="pts-fill" style="width:<?=$barPct?>%"></div></div>
                </div>
              </td>
              <td>
                <div class="pts-bar" style="width:80px">
                  <div class="pts-fill" style="width:<?=$barPct?>%"></div>
                </div>
              </td>
              <td><span class="rank-badge <?=$bClass?>"><?=$bLabel?></span></td>
            </tr>
            <?php $rank++; endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// ── Chart defaults ────────────────────────────────────────
Chart.defaults.color = '#4a5270';
Chart.defaults.font.family = 'Outfit';

// ── All period data (no API needed) ──────────────────────
const allData = {
  7:  { labels: <?= json_encode($analytics_7d['labels'])  ?>, data: <?= json_encode($analytics_7d['data'])  ?> },
  14: { labels: <?= json_encode($analytics_14d['labels']) ?>, data: <?= json_encode($analytics_14d['data']) ?> },
  30: { labels: <?= json_encode($analytics_30d['labels']) ?>, data: <?= json_encode($analytics_30d['data']) ?> },
};

// ── Activity line chart ───────────────────────────────────
const actCtx  = document.getElementById('activityChart').getContext('2d');
const actGrad = actCtx.createLinearGradient(0, 0, 0, 240);
actGrad.addColorStop(0, 'rgba(59,130,246,.22)');
actGrad.addColorStop(1, 'rgba(59,130,246,.00)');

const actChart = new Chart(actCtx, {
  type: 'line',
  data: {
    labels: allData[7].labels,
    datasets: [{
      label: 'Activities',
      data: allData[7].data,
      borderColor: '#3b82f6',
      backgroundColor: actGrad,
      borderWidth: 2.5,
      fill: true,
      tension: 0.42,
      pointBackgroundColor: '#3b82f6',
      pointBorderColor: '#07080f',
      pointBorderWidth: 2,
      pointRadius: 4,
      pointHoverRadius: 7,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1c2135',
        titleColor: '#dde2f0',
        bodyColor: '#8892aa',
        padding: 12, cornerRadius: 10,
        callbacks: { label: c => '  ' + c.parsed.y + ' actions' }
      }
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { font: { size: 10 }, maxRotation: 0, maxTicksLimit: 7 } },
      y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { font: { size: 10 }, stepSize: 1 }, beginAtZero: true }
    }
  }
});

// ── Time range switch ─────────────────────────────────────
function switchChart(days, btn) {
  document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const d = allData[days];
  actChart.data.labels              = d.labels;
  actChart.data.datasets[0].data   = d.data;
  actChart.update('active');

  const total = d.data.reduce((a,b) => a+b, 0);
  const avg   = d.data.length ? (total/d.data.length).toFixed(1) : 0;
  const peak  = Math.max(...d.data);
  const best  = d.labels[d.data.indexOf(peak)]?.substring(0,3) ?? '—';

  document.getElementById('miniTotal').textContent = total;
  document.getElementById('miniAvg').textContent   = avg;
  document.getElementById('miniPeak').textContent  = peak;
  document.getElementById('miniBest').textContent  = best;
}

// ── Breakdown donut ───────────────────────────────────────
new Chart(document.getElementById('breakdownChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($activity_types) ?>,
    datasets: [{
      data: <?= json_encode($activity_counts) ?>,
      backgroundColor: ['#3b82f6','#8b5cf6','#10b981','#f59e0b'],
      hoverBackgroundColor: ['#60a5fa','#a78bfa','#34d399','#fbbf24'],
      borderColor: '#161929',
      borderWidth: 3,
      hoverOffset: 6,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1c2135',
        titleColor: '#dde2f0',
        bodyColor: '#8892aa',
        padding: 12, cornerRadius: 10,
      }
    }
  }
});

// ── Count-up animation ────────────────────────────────────
document.querySelectorAll('.stat-value[data-count]').forEach(el => {
  const target = parseInt(el.dataset.count, 10);
  if (!target) { el.textContent = '0'; return; }
  let cur = 0;
  const step = Math.max(1, Math.ceil(target / 45));
  const t = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = cur;
    if (cur >= target) clearInterval(t);
  }, 28);
});

// ── Mobile sidebar ────────────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('overlay');
const menuBtn  = document.getElementById('menuBtn');
menuBtn?.addEventListener('click', () => {
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