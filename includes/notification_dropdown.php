<!-- Notification Dropdown Component -->
<style>
.notif-badge{position:absolute;top:-4px;right:-4px;width:16px;height:16px;border-radius:50%;background:var(--rose);color:#fff;font-size:.6rem;font-weight:700;display:grid;place-items:center;border:2px solid var(--bg2);font-family:'JetBrains Mono',monospace}
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
</style>

<div style="position:relative">
  <button class="icon-btn" id="notifBtn" title="Notifications">
    <i class="fas fa-bell"></i>
    <span class="notif-badge" id="notifBadge" style="display:none">0</span>
  </button>
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

<script>
// Notification dropdown functionality
(function() {
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

  window.loadNotifications = async function() {
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
  };

  window.goToNotification = async function(id, link) {
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
  };

  window.markNotifRead = async function(id) {
    try {
      const fd = new FormData();
      fd.append('action', 'mark_read');
      fd.append('notification_id', id);
      await fetch('notifications.php', { method: 'POST', body: fd });
      loadNotifications();
    } catch(e) {
      console.error(e);
    }
  };

  window.markAllNotificationsRead = async function() {
    try {
      const fd = new FormData();
      fd.append('action', 'mark_all_read');
      await fetch('notifications.php', { method: 'POST', body: fd });
      loadNotifications();
    } catch(e) {
      console.error(e);
    }
  };

  // Load notifications on page load
  loadNotifications();
})();
</script>
