<?php
// student/favorites.php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle remove favorite
if (isset($_POST['remove_favorite'])) {
    $favorite_id = $_POST['favorite_id'];
    $stmt = $conn->prepare("DELETE FROM favorites WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $favorite_id, $user_id);
    $stmt->execute();
}

// Get user's favorites
$favorites = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC");
$favorites->bind_param("i", $user_id);
$favorites->execute();
$favorites = $favorites->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites — <?php echo SITE_NAME; ?></title>
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
  border: 4px solid rgba(244, 63, 94, 0.2);
  border-top-color: #f43f5e;
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
.logo-mark{width:36px;height:36px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--blue),var(--violet));display:grid;place-items:center;font-size:1rem;box-shadow:0 0 18px rgba(59,130,246,.25)}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem;letter-spacing:.04em}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--rose-dim),transparent);color:var(--rose);border-left:2px solid var(--rose);padding-left:12px}
.nav-link.active i{color:var(--rose)}
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
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;animation:slideUp .4s var(--ease) both}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:46px;height:46px;border-radius:12px;background:var(--rose-dim);display:grid;place-items:center;font-size:1.2rem;color:var(--rose);flex-shrink:0;box-shadow:0 0 20px rgba(244,63,94,.2)}
.page-title{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
.page-sub{font-size:.78rem;color:var(--text3);margin-top:1px}
.fav-count{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:20px;background:var(--rose-dim);color:var(--rose);font-size:.72rem;font-weight:700;font-family:'JetBrains Mono',monospace;border:1px solid rgba(244,63,94,.18)}

/* ═══════════════════════════════════════════════
   FAVORITES GRID
═══════════════════════════════════════════════ */
.fav-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;animation:slideUp .4s var(--ease) .05s both}

.fav-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  display:flex;flex-direction:column;
  position:relative;
  transition:transform .2s var(--ease),border-color .2s var(--ease),box-shadow .2s var(--ease);
}
.fav-card:hover{transform:translateY(-3px);border-color:var(--border-hi);box-shadow:0 12px 32px rgba(0,0,0,.3)}

.fav-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--rose),var(--rose-dim))}

.card-body{padding:20px;flex:1;display:flex;flex-direction:column;gap:12px}

.card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.card-title{font-size:.975rem;font-weight:700;color:var(--text);letter-spacing:-.01em;line-height:1.35;flex:1}

.card-actions{display:flex;gap:6px}
.action-btn{width:28px;height:28px;border-radius:var(--radius-sm);background:var(--bg3);border:1px solid var(--border);display:grid;place-items:center;color:var(--text3);cursor:pointer;transition:all .18s var(--ease);font-size:.8rem}
.action-btn:hover{border-color:var(--border-hi);color:var(--text)}
.action-btn.delete:hover{background:var(--rose-dim);border-color:rgba(244,63,94,.3);color:var(--rose)}

.card-content{font-size:.82rem;color:var(--text2);line-height:1.7;flex:1;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;transition:all .3s var(--ease);cursor:pointer}
.card-content.expanded{display:block;-webkit-line-clamp:unset;max-height:none}

/* ═══════════════════════════════════════════════
   MODAL
═══════════════════════════════════════════════ */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;animation:fadeIn .3s var(--ease)}
.modal-overlay.open{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border-hi);border-radius:var(--radius);max-width:700px;width:100%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.6);animation:slideUpModal .3s var(--ease)}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border);gap:16px}
.modal-title{font-size:1.1rem;font-weight:700;color:var(--text);letter-spacing:-.01em;flex:1;line-height:1.4}
.modal-close{width:32px;height:32px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);display:grid;place-items:center;color:var(--text3);cursor:pointer;transition:all .18s var(--ease);flex-shrink:0}
.modal-close:hover{background:var(--rose-dim);border-color:rgba(244,63,94,.3);color:var(--rose)}
.modal-body{padding:24px;overflow-y:auto;flex:1;font-size:.88rem;color:var(--text2);line-height:1.8;white-space:pre-wrap;word-break:break-word}
.modal-footer{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-top:1px solid var(--border)}
.modal-type{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;background:var(--violet-dim);color:var(--violet)}
.modal-date{font-size:.75rem;color:var(--text3);font-family:'JetBrains Mono',monospace}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideUpModal{from{opacity:0;transform:translateY(30px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}

.card-footer{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--border);margin-top:auto}
.type-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;background:var(--violet-dim);color:var(--violet)}
.fav-date{font-size:.7rem;color:var(--text3);font-family:'JetBrains Mono',monospace}

/* ── Empty state ── */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:80px 28px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);animation:slideUp .4s var(--ease) .05s both}
.empty-ico{width:80px;height:80px;border-radius:50%;background:var(--rose-dim);display:grid;place-items:center;font-size:2rem;color:var(--rose);margin-bottom:20px;opacity:.8}
.empty-title{font-size:1.2rem;font-weight:700;margin-bottom:8px}
.empty-sub{font-size:.88rem;color:var(--text3);line-height:1.6;margin-bottom:24px;max-width:400px}
.btn-primary{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:var(--radius-sm);background:var(--rose);color:#fff;border:none;font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .18s var(--ease)}
.btn-primary:hover{background:#dc2626;transform:translateY(-1px);box-shadow:0 6px 20px rgba(244,63,94,.35)}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1200px){.fav-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px}
  .topbar{padding:0 14px}
  .fav-grid{grid-template-columns:1fr}
}
    </style>
</head>
<body>
<div class="loading-screen" id="loadingScreen">
  <div class="loader"></div>
  <div class="loading-text">Loading Favorites...</div>
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
    <a href="favorites.php" class="nav-link active"><i class="fas fa-heart"></i> Favorites</a>
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
      <div class="topbar-title">My Favorites</div>
      <div class="topbar-sub">Your saved content and lessons</div>
    </div>
    <div class="topbar-right">
      <button class="icon-btn" title="AI Assistant" style="background:linear-gradient(135deg,var(--violet),var(--blue));color:#fff;border:none">
        <i class="fas fa-robot"></i>
      </button>
      <?php include '../includes/notification_dropdown.php'; ?>
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
        <div class="page-icon"><i class="fas fa-heart"></i></div>
        <div>
          <div class="page-title">My Favorites</div>
          <div class="page-sub">Content you've saved for later</div>
        </div>
      </div>
      <span class="fav-count"><i class="fas fa-bookmark"></i> <?php echo $favorites->num_rows; ?> saved</span>
    </div>

    <!-- ── Favorites Grid ──────────────────── -->
    <?php if ($favorites->num_rows > 0): ?>
    <div class="fav-grid">
      <?php while($favorite = $favorites->fetch_assoc()): ?>
      <div class="fav-card">
        <div class="card-body">
          <div class="card-header">
            <h3 class="card-title"><?php echo htmlspecialchars($favorite['title']); ?></h3>
            <div class="card-actions">
              <form method="POST" style="display:inline">
                <input type="hidden" name="favorite_id" value="<?php echo $favorite['id']; ?>">
                <button type="submit" name="remove_favorite" class="action-btn delete" title="Remove from favorites">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </div>
          
          <div class="card-content" onclick="openModal('<?php echo htmlspecialchars(addslashes($favorite['title'])); ?>', `<?php echo htmlspecialchars(str_replace(['`', '\r\n', '\n', '\r'], ['\`', '\n', '\n', '\n'], $favorite['content_data'])); ?>`, '<?php echo ucfirst($favorite['content_type']); ?>', '<?php echo date('M d, Y', strtotime($favorite['created_at'])); ?>')">
            <?php echo nl2br(htmlspecialchars($favorite['content_data'])); ?>
          </div>
          
          <div class="card-footer">
            <span class="type-badge">
              <i class="fas fa-tag"></i>
              <?php echo ucfirst($favorite['content_type']); ?>
            </span>
            <span class="fav-date"><?php echo date('M d, Y', strtotime($favorite['created_at'])); ?></span>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php else: ?>
    <!-- ── Empty state ─────────────────────── -->
    <div class="empty-state">
      <div class="empty-ico"><i class="fas fa-heart-crack"></i></div>
      <div class="empty-title">No Favorites Yet</div>
      <div class="empty-sub">Start saving your favorite lessons and generated content to access them quickly later!</div>
      <a href="generate.php" class="btn-primary">
        <i class="fas fa-wand-magic-sparkles"></i> Generate Content
      </a>
    </div>
    <?php endif; ?>

  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title" id="modalTitle"></h2>
      <button class="modal-close" onclick="closeModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer">
      <span class="modal-type" id="modalType"></span>
      <span class="modal-date" id="modalDate"></span>
    </div>
  </div>
</div>

<script>
// Loading Screen
window.addEventListener('load', function() {
  setTimeout(() => {
    document.getElementById('loadingScreen').classList.add('hidden');
  }, 900);
});

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

// ── Modal functions ──────────────────────────────────────────
function openModal(title, content, type, date) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = content.replace(/\n/g, '<br>');
  document.getElementById('modalType').innerHTML = '<i class="fas fa-tag"></i> ' + type;
  document.getElementById('modalDate').textContent = date;
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeModal();
});
</script>

<?php include '../includes/ai_chat.php'; ?>

</body>
</html>