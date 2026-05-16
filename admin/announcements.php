<?php
require_once '../config/database.php';
requireLogin();
if ($_SESSION['role'] !== 'admin') { header('Location: ../auth/login.php'); exit; }

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'create') {
        $title = sanitize($conn, trim($_POST['title']));
        $content = sanitize($conn, trim($_POST['content']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($title) || empty($content)) {
            echo json_encode(['ok' => false, 'error' => 'Title and content required']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, is_active) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $title, $content, $is_active);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Failed to create announcement']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'update') {
        $id = (int)$_POST['id'];
        $title = sanitize($conn, trim($_POST['title']));
        $content = sanitize($conn, trim($_POST['content']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($title) || empty($content)) {
            echo json_encode(['ok' => false, 'error' => 'Title and content required']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, is_active=? WHERE id=?");
        $stmt->bind_param("ssii", $title, $content, $is_active, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Failed to update announcement']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Failed to delete announcement']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Failed to toggle status']);
        }
        exit;
    }
}

// Fetch all announcements
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
  --text:#dde2f0;--text2:#8892aa;--text3:#4a5270;
  --sidebar-w:252px;--topbar-h:66px;
  --radius:14px;--radius-sm:9px;
  --ease:cubic-bezier(.4,0,.2,1);
}
html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

.shell{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 14px;margin:2px 8px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .18s var(--ease)}
.nav-link i{width:17px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:linear-gradient(90deg,var(--violet-dim),transparent);color:var(--violet);border-left:2px solid var(--violet);padding-left:12px}
.nav-link.active i{color:var(--violet)}

.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar-h);display:flex;align-items:center;padding:0 28px;gap:14px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}

.page{flex:1;padding:28px;max-width:1400px;width:100%}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:var(--radius-sm);background:var(--violet);color:#fff;border:none;font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease)}
.btn-primary:hover{background:#7c3aed;transform:translateY(-1px)}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.card-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-size:.95rem;font-weight:700}
.card-body{padding:20px}

.form-group{margin-bottom:18px}
.form-label{display:block;font-size:.82rem;font-weight:600;color:var(--text2);margin-bottom:8px}
.form-input,.form-textarea{width:100%;padding:10px 14px;background:var(--bg3);border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:'Outfit',sans-serif;font-size:.875rem;color:var(--text);outline:none;transition:border-color .18s var(--ease)}
.form-input:focus,.form-textarea:focus{border-color:var(--violet)}
.form-textarea{min-height:120px;resize:vertical}
.form-checkbox{display:flex;align-items:center;gap:8px;cursor:pointer}
.form-checkbox input{width:18px;height:18px;cursor:pointer}

.table{width:100%;border-collapse:collapse}
.table th{text-align:left;padding:12px 16px;font-size:.78rem;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
.table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.875rem}
.table tr:hover{background:var(--bg3)}

.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:12px;font-size:.72rem;font-weight:600}
.badge-success{background:var(--emerald-dim);color:var(--emerald)}
.badge-danger{background:var(--rose-dim);color:var(--rose)}

.btn-icon{width:32px;height:32px;border-radius:var(--radius-sm);background:var(--bg3);border:1px solid var(--border);display:grid;place-items:center;color:var(--text2);cursor:pointer;transition:all .18s var(--ease);font-size:.8rem}
.btn-icon:hover{border-color:var(--border-hi);color:var(--text)}
.btn-icon.edit:hover{background:var(--blue-dim);color:var(--blue);border-color:var(--blue)}
.btn-icon.delete:hover{background:var(--rose-dim);color:var(--rose);border-color:var(--rose)}

.modal{display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.7);align-items:center;justify-content:center;padding:20px}
.modal.open{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border-hi);border-radius:var(--radius);max-width:600px;width:100%;max-height:90vh;overflow-y:auto}
.modal-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:.95rem;font-weight:700}
.modal-close{background:none;border:none;color:var(--text3);font-size:1.2rem;cursor:pointer;transition:color .15s}
.modal-close:hover{color:var(--text)}
.modal-body{padding:20px}
.modal-footer{padding:18px 20px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}

.btn-secondary{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:var(--radius-sm);background:var(--bg3);color:var(--text2);border:1px solid var(--border);font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .18s var(--ease)}
.btn-secondary:hover{border-color:var(--border-hi);color:var(--text)}

.toast{position:fixed;bottom:28px;right:28px;z-index:9999;background:var(--surface2);border:1px solid var(--border-hi);border-radius:var(--radius-sm);padding:12px 18px;font-size:.84rem;color:var(--text);box-shadow:0 12px 32px rgba(0,0,0,.4);animation:toastIn .3s var(--ease);display:flex;align-items:center;gap:10px;max-width:300px}
.toast.error{border-color:rgba(244,63,94,.3);color:var(--rose)}
.toast.success{border-color:rgba(16,185,129,.25);color:var(--emerald)}
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

.empty-state{text-align:center;padding:60px 20px;color:var(--text3)}
.empty-state i{font-size:3rem;margin-bottom:16px;opacity:.5}
.empty-state p{font-size:.9rem}
</style>
</head>
<body>
<div class="shell">
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text"><?php echo SITE_NAME; ?><span>Admin Panel</span></div>
  </div>
  <div class="sidebar-nav">
    <div class="nav-group-label">Main</div>
    <a href="dashboard.php" class="nav-link"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <a href="users.php" class="nav-link"><i class="fas fa-users"></i> Users</a>
    <a href="lessons.php" class="nav-link"><i class="fas fa-book-open"></i> Lessons</a>
    <a href="announcements.php" class="nav-link active"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Analytics</a>
    <div class="nav-group-label">Account</div>
    <a href="../auth/logout.php" class="nav-link" style="color:var(--rose)"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div>
      <div class="topbar-title">Announcements</div>
      <div class="topbar-sub">Manage forum announcements</div>
    </div>
  </header>

  <div class="page">
    <div class="page-header">
      <h1 style="font-size:1.4rem;font-weight:700">Forum Announcements</h1>
      <button class="btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> New Announcement
      </button>
    </div>

    <div class="card">
      <div class="card-body" style="padding:0">
        <?php if (empty($announcements)): ?>
        <div class="empty-state">
          <i class="fas fa-bullhorn"></i>
          <p>No announcements yet. Create one to display in the forum.</p>
        </div>
        <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Content</th>
              <th>Status</th>
              <th>Created</th>
              <th style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($announcements as $ann): ?>
            <tr>
              <td style="font-weight:600"><?php echo htmlspecialchars($ann['title']); ?></td>
              <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text2)">
                <?php echo htmlspecialchars(substr($ann['content'], 0, 80)) . (strlen($ann['content']) > 80 ? '...' : ''); ?>
              </td>
              <td>
                <span class="badge <?php echo $ann['is_active'] ? 'badge-success' : 'badge-danger'; ?>" 
                      style="cursor:pointer" 
                      onclick="toggleStatus(<?php echo $ann['id']; ?>)">
                  <i class="fas fa-circle" style="font-size:.5rem"></i>
                  <?php echo $ann['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
              </td>
              <td style="color:var(--text3);font-size:.8rem">
                <?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
              </td>
              <td style="text-align:center">
                <div style="display:inline-flex;gap:6px">
                  <button class="btn-icon edit" onclick='openEditModal(<?php echo json_encode($ann); ?>)' title="Edit">
                    <i class="fas fa-pen"></i>
                  </button>
                  <button class="btn-icon delete" onclick="deleteAnnouncement(<?php echo $ann['id']; ?>)" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Create/Edit Modal -->
<div class="modal" id="announcementModal">
  <div class="modal-content">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">New Announcement</div>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <form id="announcementForm">
        <input type="hidden" id="announcementId">
        <div class="form-group">
          <label class="form-label">Title</label>
          <input type="text" class="form-input" id="announcementTitle" placeholder="Enter announcement title" required>
        </div>
        <div class="form-group">
          <label class="form-label">Content</label>
          <textarea class="form-textarea" id="announcementContent" placeholder="Enter announcement content" required></textarea>
        </div>
        <div class="form-group">
          <label class="form-checkbox">
            <input type="checkbox" id="announcementActive" checked>
            <span>Active (visible in forum)</span>
          </label>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn-primary" onclick="saveAnnouncement()">
        <i class="fas fa-check"></i> Save
      </button>
    </div>
  </div>
</div>

<script>
function toast(msg, type='success') {
  document.querySelectorAll('.toast').forEach(t => t.remove());
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = (type==='success'?'<i class="fas fa-circle-check"></i>':'<i class="fas fa-circle-exclamation"></i>') + msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

async function api(formData) {
  const r = await fetch('announcements.php', {
    method:'POST',
    headers:{'X-Requested-With':'XMLHttpRequest'},
    body: formData
  });
  return r.json();
}

function openCreateModal() {
  document.getElementById('modalTitle').textContent = 'New Announcement';
  document.getElementById('announcementId').value = '';
  document.getElementById('announcementTitle').value = '';
  document.getElementById('announcementContent').value = '';
  document.getElementById('announcementActive').checked = true;
  document.getElementById('announcementModal').classList.add('open');
}

function openEditModal(ann) {
  document.getElementById('modalTitle').textContent = 'Edit Announcement';
  document.getElementById('announcementId').value = ann.id;
  document.getElementById('announcementTitle').value = ann.title;
  document.getElementById('announcementContent').value = ann.content;
  document.getElementById('announcementActive').checked = ann.is_active == 1;
  document.getElementById('announcementModal').classList.add('open');
}

function closeModal() {
  document.getElementById('announcementModal').classList.remove('open');
}

async function saveAnnouncement() {
  const id = document.getElementById('announcementId').value;
  const title = document.getElementById('announcementTitle').value.trim();
  const content = document.getElementById('announcementContent').value.trim();
  const is_active = document.getElementById('announcementActive').checked;
  
  if (!title || !content) {
    toast('Title and content are required', 'error');
    return;
  }
  
  const fd = new FormData();
  fd.append('action', id ? 'update' : 'create');
  if (id) fd.append('id', id);
  fd.append('title', title);
  fd.append('content', content);
  if (is_active) fd.append('is_active', '1');
  
  const data = await api(fd);
  if (data.ok) {
    toast(id ? 'Announcement updated' : 'Announcement created');
    closeModal();
    setTimeout(() => location.reload(), 1000);
  } else {
    toast(data.error || 'Failed to save', 'error');
  }
}

async function deleteAnnouncement(id) {
  if (!confirm('Delete this announcement?')) return;
  
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  
  const data = await api(fd);
  if (data.ok) {
    toast('Announcement deleted');
    setTimeout(() => location.reload(), 1000);
  } else {
    toast(data.error || 'Failed to delete', 'error');
  }
}

async function toggleStatus(id) {
  const fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('id', id);
  
  const data = await api(fd);
  if (data.ok) {
    toast('Status updated');
    setTimeout(() => location.reload(), 1000);
  } else {
    toast(data.error || 'Failed to update', 'error');
  }
}

document.getElementById('announcementModal').addEventListener('click', e => {
  if (e.target.id === 'announcementModal') closeModal();
});
</script>
</body>
</html>
