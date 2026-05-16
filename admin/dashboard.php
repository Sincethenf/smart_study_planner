<?php
// admin/dashboard.php
require_once '../config/database.php';
requireAdmin();

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
$total_lessons = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
$active_today = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_activity WHERE activity_date = CURDATE()")->fetch_assoc()['count'];

// Get recent users
$recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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
.nav-link.active{background:linear-gradient(90deg,var(--violet-dim),transparent);color:var(--violet);border-left:2px solid var(--violet);padding-left:12px}
.nav-link.active i{color:var(--violet)}
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
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.user-pill{display:flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;border-radius:30px;background:var(--surface);border:1px solid var(--border);cursor:pointer;text-decoration:none;transition:border-color .18s var(--ease)}
.user-pill:hover{border-color:var(--border-hi)}
.pill-avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));overflow:hidden}
.pill-avatar img{width:100%;height:100%;object-fit:cover}
.pill-name{font-size:.82rem;font-weight:600;color:var(--text)}

/* ═══════════════════════════════════════════════
   PAGE
═══════════════════════════════════════════════ */
.page{flex:1;padding:26px 28px;display:flex;flex-direction:column;gap:22px}
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--violet-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--violet);flex-shrink:0;box-shadow:0 0 20px rgba(139,92,246,.2)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}

/* ═══════════════════════════════════════════════
   STATS GRID
═══════════════════════════════════════════════ */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;animation:slideUp .4s var(--ease) .05s both}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:16px;transition:transform .2s var(--ease),border-color .2s var(--ease)}
.stat-card:hover{transform:translateY(-3px);border-color:var(--border-hi)}
.stat-icon{width:50px;height:50px;border-radius:var(--radius-sm);display:grid;place-items:center;font-size:1.3rem;flex-shrink:0}
.stat-icon.blue{background:var(--blue-dim);color:var(--blue)}
.stat-icon.violet{background:var(--violet-dim);color:var(--violet)}
.stat-icon.emerald{background:var(--emerald-dim);color:var(--emerald)}
.stat-icon.amber{background:var(--amber-dim);color:var(--amber)}
.stat-info{flex:1}
.stat-info h3{font-size:.8rem;color:var(--text3);font-weight:500;margin-bottom:4px}
.stat-number{font-size:1.6rem;font-weight:700;color:var(--text)}

/* ═══════════════════════════════════════════════
   TABLE
═══════════════════════════════════════════════ */
.table-container{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;animation:slideUp .4s var(--ease) .1s both}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border)}
.table-header h3{font-size:1rem;font-weight:700;color:var(--text)}
.btn-secondary{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-sm);background:var(--violet-dim);border:1px solid rgba(139,92,246,.2);font-family:'Outfit',sans-serif;font-size:.78rem;font-weight:600;color:var(--violet);cursor:pointer;text-decoration:none;transition:all .18s var(--ease)}
.btn-secondary:hover{background:var(--violet);color:#fff}
.table-responsive{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:15px 24px;color:var(--text3);font-weight:600;font-size:.8rem;border-bottom:1px solid var(--border)}
td{padding:15px 24px;color:var(--text2);border-bottom:1px solid var(--border);font-size:.85rem}
tr:last-child td{border-bottom:none}
tr:hover{background:var(--bg3)}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:600;background:var(--violet-dim);color:var(--violet)}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .stats-grid{grid-template-columns:1fr}
}
    </style>
</head>
<body>
<div class="shell">

<!-- ════════════════════════════════════
     SIDEBAR
════════════════════════════════════ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text"><?php echo SITE_NAME; ?><span>Admin Panel</span></div>
  </div>
  <div class="sidebar-nav">
    <div class="nav-group-label">Main</div>
    <a href="dashboard.php" class="nav-link active"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <a href="users.php" class="nav-link"><i class="fas fa-users"></i> Users</a>
    <a href="lessons.php" class="nav-link"><i class="fas fa-book-open"></i> Lessons</a>
    <div class="nav-group-label">Analytics</div>
    <a href="analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Analytics</a>
    <div class="nav-group-label">Account</div>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-chip-avatar">
        <?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?>
      </div>
      <div>
        <div class="user-chip-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
        <div class="user-chip-role">Administrator</div>
      </div>
    </div>
  </div>
</aside>

<!-- ════════════════════════════════════
     MAIN
════════════════════════════════════ -->
<div class="main">
  <header class="topbar">
    <div>
      <div class="topbar-title">Admin Dashboard</div>
      <div class="topbar-sub">System overview and management</div>
    </div>
    <div class="topbar-right">
      <div class="user-pill">
        <div class="pill-avatar">
          <?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?>
        </div>
        <span class="pill-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      </div>
    </div>
  </header>

  <div class="page">

    <!-- ── Page header ─────────────────────── -->
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon"><i class="fas fa-chart-pie"></i></div>
        <div>
          <div class="page-title">Dashboard Overview</div>
          <div class="page-sub">Monitor system statistics and activity</div>
        </div>
      </div>
    </div>

    <!-- ── Stats Grid ──────────────────────── -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
          <h3>Total Students</h3>
          <div class="stat-number"><?php echo $total_students; ?></div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon violet">
          <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
          <h3>Total Teachers</h3>
          <div class="stat-number"><?php echo $total_teachers; ?></div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon emerald">
          <i class="fas fa-book-open"></i>
        </div>
        <div class="stat-info">
          <h3>Total Lessons</h3>
          <div class="stat-number"><?php echo $total_lessons; ?></div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon amber">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
          <h3>Active Today</h3>
          <div class="stat-number"><?php echo $active_today; ?></div>
        </div>
      </div>
    </div>

    <!-- ── Recent Users Table ──────────────── -->
    <div class="table-container">
      <div class="table-header">
        <h3>Recent Users</h3>
        <a href="users.php" class="btn-secondary">View All</a>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($user = $recent_users->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($user['full_name']); ?></td>
              <td><?php echo htmlspecialchars($user['email']); ?></td>
              <td><span class="badge"><?php echo ucfirst($user['role']); ?></span></td>
              <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
              <td>
                <?php if($user['is_active']): ?>
                  <span style="color:var(--emerald);font-weight:600">Active</span>
                <?php else: ?>
                  <span style="color:var(--rose);font-weight:600">Inactive</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->
</body>
</html>