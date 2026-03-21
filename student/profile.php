<?php
// student/profile.php
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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($conn, $_POST['full_name']);
        $email     = sanitize($conn, $_POST['email']);
        $phone     = sanitize($conn, $_POST['phone']   ?? '');
        $address   = sanitize($conn, $_POST['address'] ?? '');
        $bio       = sanitize($conn, $_POST['bio']     ?? '');
        $errors    = [];
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email))     $errors[] = "Email is required";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        $ce = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $ce->bind_param("si", $email, $user_id); $ce->execute(); $ce->store_result();
        if ($ce->num_rows > 0) $errors[] = "Email already exists";
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,address=?,bio=? WHERE id=?");
            $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $bio, $user_id);
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id); $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $_SESSION['full_name'] = $full_name;
            } else { $error = "Failed to update profile: " . $conn->error; }
        } else { $error = implode("<br>", $errors); }
    }
    if (isset($_POST['change_password'])) {
        $cur  = $_POST['current_password'];
        $new  = $_POST['new_password'];
        $conf = $_POST['confirm_password'];
        $errors = [];
        if (!password_verify($cur, $user['password'])) $errors[] = "Current password is incorrect";
        if (strlen($new) < 6) $errors[] = "New password must be at least 6 characters";
        if ($new !== $conf)   $errors[] = "New passwords do not match";
        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $user_id);
            if ($stmt->execute()) { $success = "Password changed successfully!"; }
            else { $error = "Failed to change password: " . $conn->error; }
        } else { $error = implode("<br>", $errors); }
    }
}

// Cover photo upload
if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
    $allowed = ['image/jpeg','image/png','image/gif'];
    if (!in_array($_FILES['cover_photo']['type'], $allowed)) { $error = "Only JPG, PNG and GIF allowed"; }
    elseif ($_FILES['cover_photo']['size'] > 5*1024*1024) { $error = "Max 5MB"; }
    else {
        $dir = '../assets/uploads/covers/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $ext  = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);
        $file = 'cover_'.$user_id.'_'.time().'.'.$ext;
        if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $dir.$file)) {
            if (!empty($user['cover_photo']) && file_exists($dir.$user['cover_photo'])) unlink($dir.$user['cover_photo']);
            $stmt = $conn->prepare("UPDATE users SET cover_photo=? WHERE id=?");
            $stmt->bind_param("si", $file, $user_id);
            if ($stmt->execute()) { $success = "Cover photo updated!"; $user['cover_photo'] = $file; }
            else { $error = "DB update failed"; }
        } else { $error = "Upload failed"; }
    }
}

// Profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed = ['image/jpeg','image/png','image/gif'];
    if (!in_array($_FILES['profile_picture']['type'], $allowed)) { $error = "Only JPG, PNG and GIF allowed"; }
    elseif ($_FILES['profile_picture']['size'] > 5*1024*1024) { $error = "Max 5MB"; }
    else {
        $dir = '../assets/uploads/profiles/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $ext  = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $file = 'profile_'.$user_id.'_'.time().'.'.$ext;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $dir.$file)) {
            if ($user['profile_picture'] != 'default-avatar.png') {
                $old = $dir.$user['profile_picture'];
                if (file_exists($old)) unlink($old);
            }
            $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
            $stmt->bind_param("si", $file, $user_id);
            if ($stmt->execute()) { $success = "Profile picture updated!"; $user['profile_picture'] = $file; }
            else { $error = "DB update failed"; }
        } else { $error = "Upload failed"; }
    }
}

// Stats
$stats = [];
$stats['total_lessons']   = $conn->query("SELECT COUNT(*) as c FROM user_activity WHERE user_id=$user_id AND activity_type='lesson'")->fetch_assoc()['c'];
$stats['total_generates'] = $conn->query("SELECT COUNT(*) as c FROM user_activity WHERE user_id=$user_id AND activity_type='generate'")->fetch_assoc()['c'];
$stats['total_favorites'] = $conn->query("SELECT COUNT(*) as c FROM favorites WHERE user_id=$user_id")->fetch_assoc()['c'];
$stats['join_date']  = date('F d, Y', strtotime($user['join_date'] ?? $user['created_at']));
$stats['last_login'] = $user['last_login'] ? date('F d, Y', strtotime($user['last_login'])) : 'Never';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   RESET & ROOT
═══════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:         #07080f;
  --bg2:        #0c0e1a;
  --bg3:        #111320;
  --surface:    #161929;
  --surface2:   #1c2135;
  --border:     rgba(255,255,255,.055);
  --border-hi:  rgba(255,255,255,.11);
  --blue:       #3b82f6;
  --blue-dim:   rgba(59,130,246,.14);
  --violet:     #8b5cf6;
  --violet-dim: rgba(139,92,246,.13);
  --emerald:    #10b981;
  --emerald-dim:rgba(16,185,129,.13);
  --amber:      #f59e0b;
  --amber-dim:  rgba(245,158,11,.12);
  --rose:       #f43f5e;
  --rose-dim:   rgba(244,63,94,.12);
  --text:       #dde2f0;
  --text2:      #8892aa;
  --text3:      #4a5270;
  --sidebar-w:  252px;
  --topbar-h:   66px;
  --radius:     14px;
  --radius-sm:  9px;
  --ease:       cubic-bezier(.4,0,.2,1);
}
html,body{height:100%;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;line-height:1.6}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:4px}

/* ═══════════════════════════════════════════════
   SHELL
═══════════════════════════════════════════════ */
.shell{display:flex;min-height:100vh}

/* ═══════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════ */
.sidebar{
  width:var(--sidebar-w);background:var(--bg2);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;inset:0 auto 0 0;z-index:200;
  transition:transform .3s var(--ease);
}
.sidebar-logo{
  padding:24px 20px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:11px;
}
.logo-text{font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.25}
.logo-text span{display:block;font-weight:400;color:var(--text3);font-size:.67rem;letter-spacing:.04em}
.nav-group-label{font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3);padding:18px 20px 6px}
.sidebar-nav{flex:1;overflow-y:auto;padding-bottom:12px}
.nav-link{
  display:flex;align-items:center;gap:11px;
  padding:10px 14px;margin:2px 8px;
  border-radius:var(--radius-sm);
  color:var(--text2);text-decoration:none;
  font-size:.875rem;font-weight:500;
  transition:all .18s var(--ease);
}
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

/* ═══════════════════════════════════════════════
   MAIN
═══════════════════════════════════════════════ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{
  height:var(--topbar-h);display:flex;align-items:center;
  padding:0 28px;gap:14px;
  background:var(--bg2);border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:100;
}
.hamburger{display:none;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);width:36px;height:36px;place-items:center;color:var(--text2);cursor:pointer;font-size:.95rem}
.topbar-title{font-size:1.05rem;font-weight:700;letter-spacing:-.02em}
.topbar-sub{font-size:.72rem;color:var(--text3)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.icon-btn{width:36px;height:36px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);display:grid;place-items:center;color:var(--text2);cursor:pointer;transition:all .18s var(--ease);text-decoration:none;font-size:.88rem;position:relative}
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

/* ═══════════════════════════════════════════════
   PROFILE HERO (cover + avatar)
═══════════════════════════════════════════════ */
.profile-hero{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:visible;
  animation:slideUp .45s var(--ease) both;
}

/* Cover */
.cover-wrap{
  position:relative;height:200px;
  background:linear-gradient(135deg,#1a1040 0%,#0f1a3d 50%,#0a1628 100%);
  border-radius:var(--radius) var(--radius) 0 0;overflow:hidden;
}
.cover-wrap::before{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(circle at 20% 50%,rgba(59,130,246,.15) 0%,transparent 55%),
    radial-gradient(circle at 80% 30%,rgba(139,92,246,.12) 0%,transparent 50%);
  pointer-events:none;
}
.cover-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1;pointer-events:none}
.cover-edit{
  position:absolute;bottom:14px;right:16px;z-index:100;
  display:flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:var(--radius-sm);
  background:rgba(0,0,0,.55);backdrop-filter:blur(6px);
  border:1px solid rgba(255,255,255,.12);
  color:rgba(255,255,255,.85);font-family:'Outfit',sans-serif;
  font-size:.78rem;font-weight:600;cursor:pointer;
  transition:background .2s var(--ease);
  user-select:none;pointer-events:auto;
}
.cover-edit:hover{background:rgba(0,0,0,.75)}
.cover-edit:active{transform:scale(0.98)}
#cover-input{display:none}

/* Avatar row */
.avatar-row{
  display:flex;align-items:flex-end;gap:20px;
  padding:0 28px 22px;
  margin-top:-56px;position:relative;z-index:10;
}
.avatar-wrap{position:relative;width:112px;height:112px;flex-shrink:0}
.avatar-wrap img{
  width:100%;height:100%;border-radius:50%;object-fit:cover;
  border:3px solid var(--bg2);
  box-shadow:0 0 0 1px var(--border),0 8px 24px rgba(0,0,0,.4);
}
.avatar-cam{
  position:absolute;bottom:4px;right:4px;
  width:32px;height:32px;border-radius:50%;
  background:var(--blue);border:2px solid var(--bg2);
  display:grid;place-items:center;font-size:.75rem;
  color:#fff;cursor:pointer;
  transition:background .2s var(--ease),transform .2s var(--ease);
  box-shadow:0 2px 8px rgba(59,130,246,.4);
}
.avatar-cam:hover{background:#2563eb;transform:scale(1.1)}
#file-input{display:none}

.profile-meta{padding-bottom:6px}
.profile-name{font-size:1.35rem;font-weight:800;letter-spacing:-.02em;color:var(--text);margin-bottom:4px}
.profile-email{font-size:.82rem;color:var(--text3);display:flex;align-items:center;gap:6px;margin-bottom:3px}
.profile-sid{font-size:.78rem;color:var(--text3);display:flex;align-items:center;gap:6px;font-family:'JetBrains Mono',monospace}
.profile-sid i,.profile-email i{color:var(--text3);font-size:.8rem}
.profile-actions{margin-left:auto;padding-bottom:8px;display:flex;gap:8px}
.btn-primary{
  display:inline-flex;align-items:center;gap:7px;
  padding:9px 18px;border-radius:var(--radius-sm);
  background:var(--blue);color:#fff;border:none;
  font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;
  cursor:pointer;transition:background .18s var(--ease),transform .15s var(--ease);
}
.btn-primary:hover{background:#2563eb;transform:translateY(-1px)}
.btn-ghost{
  display:inline-flex;align-items:center;gap:7px;
  padding:9px 18px;border-radius:var(--radius-sm);
  background:transparent;color:var(--text2);border:1px solid var(--border-hi);
  font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;
  cursor:pointer;transition:all .18s var(--ease);
}
.btn-ghost:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-dim)}

/* ═══════════════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════════════ */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;animation:slideUp .45s var(--ease) .06s both}
.stat-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:18px 20px;
  display:flex;align-items:center;gap:14px;
  position:relative;overflow:hidden;
  transition:transform .2s var(--ease),border-color .2s var(--ease);
}
.stat-card:hover{transform:translateY(-2px);border-color:var(--border-hi)}
.stat-card::after{
  content:'';position:absolute;top:-30px;right:-30px;
  width:100px;height:100px;border-radius:50%;pointer-events:none;opacity:.7;
}
.sc-blue::after  {background:radial-gradient(circle,var(--blue-dim),  transparent 70%)}
.sc-violet::after{background:radial-gradient(circle,var(--violet-dim),transparent 70%)}
.sc-emerald::after{background:radial-gradient(circle,var(--emerald-dim),transparent 70%)}
.sc-amber::after {background:radial-gradient(circle,var(--amber-dim), transparent 70%)}
.stat-ico{width:38px;height:38px;border-radius:9px;display:grid;place-items:center;font-size:.9rem;flex-shrink:0}
.sc-blue   .stat-ico{background:var(--blue-dim);   color:var(--blue)}
.sc-violet .stat-ico{background:var(--violet-dim); color:var(--violet)}
.sc-emerald .stat-ico{background:var(--emerald-dim);color:var(--emerald)}
.sc-amber  .stat-ico{background:var(--amber-dim);  color:var(--amber)}
.stat-val{font-size:1.6rem;font-weight:800;font-family:'JetBrains Mono',monospace;color:var(--text);line-height:1;margin-bottom:2px}
.stat-lbl{font-size:.7rem;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;font-weight:500}

/* ═══════════════════════════════════════════════
   ALERTS
═══════════════════════════════════════════════ */
.alert{
  display:flex;align-items:flex-start;gap:10px;
  padding:13px 16px;border-radius:var(--radius-sm);
  font-size:.85rem;line-height:1.5;animation:slideUp .3s var(--ease) both;
}
.alert i{font-size:.9rem;flex-shrink:0;margin-top:1px}
.alert-success{background:var(--emerald-dim);color:var(--emerald);border:1px solid rgba(16,185,129,.2)}
.alert-error  {background:var(--rose-dim);  color:var(--rose);   border:1px solid rgba(244,63,94,.2)}

/* ═══════════════════════════════════════════════
   PROFILE CONTENT (tabs + panels)
═══════════════════════════════════════════════ */
.profile-body{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  animation:slideUp .45s var(--ease) .12s both;
}

/* Tab bar */
.tab-bar{
  display:flex;gap:2px;padding:8px 8px 0;
  border-bottom:1px solid var(--border);
  background:var(--bg3);
  overflow-x:auto;scrollbar-width:none;
}
.tab-bar::-webkit-scrollbar{display:none}
.tab-btn{
  display:flex;align-items:center;gap:7px;
  padding:9px 16px;border-radius:var(--radius-sm) var(--radius-sm) 0 0;
  border:none;background:transparent;
  font-family:'Outfit',sans-serif;font-size:.825rem;font-weight:500;
  color:var(--text3);cursor:pointer;white-space:nowrap;
  transition:all .18s var(--ease);border-bottom:2px solid transparent;
  margin-bottom:-1px;
}
.tab-btn i{font-size:.8rem}
.tab-btn:hover{color:var(--text2);background:rgba(255,255,255,.04)}
.tab-btn.active{color:var(--blue);border-bottom-color:var(--blue);background:rgba(59,130,246,.07)}

/* Tab panels */
.tab-panel{display:none;padding:28px;animation:fadeIn .3s var(--ease)}
.tab-panel.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

.panel-title{font-size:.95rem;font-weight:700;letter-spacing:-.01em;margin-bottom:6px}
.panel-sub  {font-size:.78rem;color:var(--text3);margin-bottom:22px}

/* Info grid (view mode) */
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.info-cell{
  background:var(--bg3);border:1px solid var(--border);
  border-radius:var(--radius-sm);padding:14px 16px;
}
.info-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);font-weight:600;margin-bottom:5px}
.info-value{font-size:.9rem;font-weight:500;color:var(--text)}
.info-value.mono{font-family:'JetBrains Mono',monospace;font-size:.82rem}

/* Status dot */
.status-dot{display:inline-flex;align-items:center;gap:6px;font-size:.85rem;font-weight:500}
.status-dot::before{content:'';width:7px;height:7px;border-radius:50%;flex-shrink:0}
.sd-active::before{background:var(--emerald);box-shadow:0 0 6px var(--emerald)}
.sd-active{color:var(--emerald)}
.sd-inactive::before{background:var(--text3)}
.sd-inactive{color:var(--text3)}

/* Role badge */
.role-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:capitalize}
.rb-student{background:var(--blue-dim);  color:var(--blue)}
.rb-teacher{background:var(--violet-dim);color:var(--violet)}
.rb-admin  {background:var(--amber-dim); color:var(--amber)}

/* Form styles */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group label{font-size:.78rem;font-weight:600;color:var(--text2);letter-spacing:.01em}
.form-group input,
.form-group textarea,
.form-group select{
  width:100%;padding:10px 13px;
  background:var(--bg3);border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  font-family:'Outfit',sans-serif;font-size:.875rem;color:var(--text);
  outline:none;transition:border-color .18s var(--ease),box-shadow .18s var(--ease);
}
.form-group input::placeholder,
.form-group textarea::placeholder{color:rgba(136,146,170,.4)}
.form-group input:focus,
.form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.form-group input[readonly]{background:var(--surface2);color:var(--text3);cursor:not-allowed}
.form-group textarea{min-height:90px;resize:vertical}
.form-group small{font-size:.7rem;color:var(--text3)}
.form-actions{display:flex;gap:10px;margin-top:8px}
.btn-save{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 20px;border-radius:var(--radius-sm);
  background:var(--blue);color:#fff;border:none;
  font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;
  cursor:pointer;transition:background .18s var(--ease),transform .15s var(--ease);
}
.btn-save:hover{background:#2563eb;transform:translateY(-1px)}
.btn-cancel{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 20px;border-radius:var(--radius-sm);
  background:transparent;color:var(--text2);border:1px solid var(--border-hi);
  font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;
  cursor:pointer;transition:all .18s var(--ease);
}
.btn-cancel:hover{border-color:var(--rose);color:var(--rose);background:var(--rose-dim)}

/* Password strength hint */
.pw-hint{font-size:.7rem;color:var(--text3);margin-top:4px}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* ── Overlay ── */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:199;backdrop-filter:blur(2px)}

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1100px){
  .stats-row{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
  :root{--sidebar-w:280px}
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .overlay.open{display:block}
  .main{margin-left:0}
  .hamburger{display:grid}
  .page{padding:16px 14px;gap:16px}
  .topbar{padding:0 14px;height:auto;min-height:var(--topbar-h)}
  .topbar-title{font-size:.95rem}
  .topbar-sub{display:none}
  .avatar-row{flex-direction:column;align-items:center;text-align:center;margin-top:-44px;padding:0 16px 18px;gap:12px}
  .avatar-wrap{width:96px;height:96px}
  .profile-meta{padding-bottom:0}
  .profile-name{font-size:1.15rem}
  .profile-email,.profile-sid{font-size:.75rem;justify-content:center}
  .profile-actions{margin-left:0;width:100%;flex-direction:column}
  .btn-primary,.btn-ghost{width:100%;justify-content:center}
  .cover-wrap{height:140px}
  .cover-edit{bottom:10px;right:10px;padding:6px 12px;font-size:.72rem}
  .info-grid{grid-template-columns:1fr}
  .form-grid{grid-template-columns:1fr}
  .stats-row{grid-template-columns:1fr 1fr;gap:10px}
  .stat-card{padding:14px 16px}
  .stat-val{font-size:1.35rem}
  .stat-lbl{font-size:.65rem}
  .profile-body{border-radius:var(--radius-sm)}
  .tab-bar{padding:6px 6px 0;gap:0}
  .tab-btn{padding:8px 12px;font-size:.75rem;gap:5px}
  .tab-btn i{font-size:.75rem}
  .tab-panel{padding:20px 16px}
  .panel-title{font-size:.88rem}
  .panel-sub{font-size:.72rem;margin-bottom:16px}
  .form-actions{flex-direction:column}
  .btn-save,.btn-cancel{width:100%;justify-content:center}
  .user-pill .pill-name{display:none}
}
@media(max-width:480px){
  .stats-row{grid-template-columns:1fr;gap:8px}
  .stat-card{flex-direction:row;gap:12px}
  .stat-ico{width:34px;height:34px;font-size:.85rem}
  .avatar-row{margin-top:-36px}
  .avatar-wrap{width:80px;height:80px}
  .avatar-cam{width:28px;height:28px;font-size:.7rem}
  .cover-wrap{height:120px}
  .cover-edit{padding:5px 10px;font-size:.68rem;bottom:8px;right:8px}
  .cover-edit i{font-size:.7rem}
  .profile-name{font-size:1rem}
  .profile-email,.profile-sid{font-size:.7rem}
  .tab-btn{padding:7px 10px;font-size:.7rem}
  .tab-btn span{display:none}
  .tab-panel{padding:16px 12px}
  .form-group input,.form-group textarea,.form-group select{font-size:.8rem;padding:9px 11px}
  .form-group label{font-size:.72rem}
  .btn-primary,.btn-ghost,.btn-save,.btn-cancel{font-size:.8rem;padding:9px 16px}
  .info-cell{padding:12px 14px}
  .info-label{font-size:.62rem}
  .info-value{font-size:.82rem}
}
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
  border: 4px solid rgba(139, 92, 246, 0.2);
  border-top-color: #8b5cf6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}
.loading-text {
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.9rem;
  font-weight: 500;
  letter-spacing: 0.5px;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

</style>
</head>
<body>
<div class="loading-screen" id="loadingScreen">
  <div class="loader"></div>
  <div class="loading-text">Loading Profile...</div>
</div>
<div class="shell">


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
    <a href="profile.php"   class="nav-link active"><i class="fas fa-user-circle"></i> My Profile</a>
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

  <!-- Topbar -->
  <header class="topbar">
    <button class="hamburger" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
      <div class="topbar-title">My Profile</div>
      <div class="topbar-sub">Manage your account and settings</div>
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

  <!-- Page -->
  <div class="page">

    <!-- ── ALERTS ─────────────────────────────── -->
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ── PROFILE HERO ───────────────────────── -->
    <div class="profile-hero">

      <!-- Cover photo -->
      <div class="cover-wrap">
        <?php if (!empty($user['cover_photo'])): ?>
          <img src="../assets/uploads/covers/<?php echo htmlspecialchars($user['cover_photo']); ?>" alt="Cover" class="cover-img">
        <?php endif; ?>
        <div class="cover-edit" id="coverEditBtn">
          <i class="fas fa-camera"></i> Edit Cover
        </div>
      </div>
      <form id="coverForm" method="POST" enctype="multipart/form-data" style="display:none">
        <input type="file" id="cover-input" name="cover_photo" accept="image/*">
      </form>

      <!-- Avatar + name row -->
      <div class="avatar-row">
        <div class="avatar-wrap">
          <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" id="profileImage">
          <div class="avatar-cam" id="avatarCamBtn">
            <i class="fas fa-camera"></i>
          </div>
        </div>
        <form id="uploadForm" method="POST" enctype="multipart/form-data" style="display:none">
          <input type="file" id="file-input" name="profile_picture" accept="image/*">
        </form>

        <div class="profile-meta"><br><br>
          <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
          <div class="profile-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
          <div class="profile-sid"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></div>
        </div>

        <div class="profile-actions">
          <button class="btn-primary" onclick="switchTab('edit-profile')">
            <i class="fas fa-pen"></i> Edit Profile
          </button>
          <button class="btn-ghost" onclick="switchTab('change-password')">
            <i class="fas fa-key"></i> Password
          </button>
        </div>
      </div>
    </div>

    <!-- ── STAT CARDS ──────────────────────────── -->
    <div class="stats-row">
      <div class="stat-card sc-blue">
        <div class="stat-ico"><i class="fas fa-book-open"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo $stats['total_lessons']; ?>">0</div>
          <div class="stat-lbl">Lessons</div>
        </div>
      </div>
      <div class="stat-card sc-violet">
        <div class="stat-ico"><i class="fas fa-wand-magic-sparkles"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo $stats['total_generates']; ?>">0</div>
          <div class="stat-lbl">Generated</div>
        </div>
      </div>
      <div class="stat-card sc-emerald">
        <div class="stat-ico"><i class="fas fa-heart"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo $stats['total_favorites']; ?>">0</div>
          <div class="stat-lbl">Favorites</div>
        </div>
      </div>
      <div class="stat-card sc-amber">
        <div class="stat-ico"><i class="fas fa-fire"></i></div>
        <div>
          <div class="stat-val" data-count="<?php echo $user['login_streak']; ?>">0</div>
          <div class="stat-lbl">Day Streak</div>
        </div>
      </div>
    </div>

    <!-- ── TABS + PANELS ───────────────────────── -->
    <div class="profile-body">

      <!-- Tab bar -->
      <div class="tab-bar">
        <button class="tab-btn active" data-tab="personal-info" onclick="switchTab('personal-info')">
          <i class="fas fa-user"></i> <span>Personal Info</span>
        </button>
        <button class="tab-btn" data-tab="edit-profile" onclick="switchTab('edit-profile')">
          <i class="fas fa-pen-to-square"></i> <span>Edit Profile</span>
        </button>
        <button class="tab-btn" data-tab="change-password" onclick="switchTab('change-password')">
          <i class="fas fa-lock"></i> <span>Change Password</span>
        </button>
        <button class="tab-btn" data-tab="account-info" onclick="switchTab('account-info')">
          <i class="fas fa-circle-info"></i> <span>Account Info</span>
        </button>
      </div>

      <!-- ── Personal Info ── -->
      <div id="personal-info" class="tab-panel active">
        <div class="panel-title">Personal Information</div>
        <div class="panel-sub">Your current profile details</div>
        <div class="info-grid">
          <div class="info-cell">
            <div class="info-label">Full Name</div>
            <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Email Address</div>
            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Phone Number</div>
            <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Student ID</div>
            <div class="info-value mono"><?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Address</div>
            <div class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Bio</div>
            <div class="info-value"><?php echo htmlspecialchars($user['bio'] ?? 'No bio yet'); ?></div>
          </div>
        </div>
      </div>

      <!-- ── Edit Profile ── -->
      <div id="edit-profile" class="tab-panel">
        <div class="panel-title">Edit Profile</div>
        <div class="panel-sub">Update your personal information below</div>
        <form method="POST" action="" id="editForm">
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            <div class="form-group">
              <label>Email Address *</label>
              <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+63 912 345 6789">
            </div>
            <div class="form-group">
              <label>Student ID</label>
              <input type="text" value="<?php echo htmlspecialchars($user['student_id'] ?? ''); ?>" readonly>
            </div>
          </div>
          <div class="form-group">
            <label>Address</label>
            <textarea name="address" placeholder="Your address…"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
          </div>
          <div class="form-group">
            <label>Bio</label>
            <textarea name="bio" placeholder="Tell us a little about yourself…"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" name="update_profile" class="btn-save">
              <i class="fas fa-floppy-disk"></i> Save Changes
            </button>
            <button type="button" class="btn-cancel" onclick="switchTab('personal-info')">
              <i class="fas fa-xmark"></i> Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- ── Change Password ── -->
      <div id="change-password" class="tab-panel">
        <div class="panel-title">Change Password</div>
        <div class="panel-sub">Keep your account secure with a strong password</div>
        <form method="POST" action="" style="max-width:480px">
          <div class="form-group">
            <label>Current Password *</label>
            <input type="password" name="current_password" required placeholder="Enter current password">
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>New Password *</label>
              <input type="password" name="new_password" required placeholder="Min. 6 characters" id="newPw">
              <span class="pw-hint" id="pwStrength"></span>
            </div>
            <div class="form-group">
              <label>Confirm New Password *</label>
              <input type="password" name="confirm_password" required placeholder="Repeat new password" id="confPw">
              <span class="pw-hint" id="pwMatch"></span>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="change_password" class="btn-save">
              <i class="fas fa-key"></i> Change Password
            </button>
          </div>
        </form>
      </div>

      <!-- ── Account Info ── -->
      <div id="account-info" class="tab-panel">
        <div class="panel-title">Account Information</div>
        <div class="panel-sub">System-level details about your account</div>
        <div class="info-grid">
          <div class="info-cell">
            <div class="info-label">Username</div>
            <div class="info-value mono"><?php echo htmlspecialchars($user['username']); ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Role</div>
            <div class="info-value">
              <span class="role-badge rb-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
            </div>
          </div>
          <div class="info-cell">
            <div class="info-label">Join Date</div>
            <div class="info-value mono"><?php echo $stats['join_date']; ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Last Login</div>
            <div class="info-value mono"><?php echo $stats['last_login']; ?></div>
          </div>
          <div class="info-cell">
            <div class="info-label">Account Status</div>
            <div class="info-value">
              <?php if ($user['is_active']): ?>
                <span class="status-dot sd-active"><span></span>Active</span>
              <?php else: ?>
                <span class="status-dot sd-inactive"><span></span>Inactive</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="info-cell">
            <div class="info-label">Total Points</div>
            <div class="info-value mono"><?php echo number_format($user['points'] ?? 0); ?> pts</div>
          </div>
        </div>
      </div>

    </div><!-- /profile-body -->
  </div><!-- /page -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// ── Tab switching ────────────────────────────────────────
function switchTab(id) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.tab === id);
  });
  document.getElementById(id)?.classList.add('active');
}

// ── Count-up animation ───────────────────────────────────
document.querySelectorAll('.stat-val[data-count]').forEach(el => {
  const target = parseInt(el.dataset.count, 10);
  if (!target) { el.textContent = '0'; return; }
  let cur = 0;
  const step = Math.max(1, Math.ceil(target / 40));
  const t = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = cur;
    if (cur >= target) clearInterval(t);
  }, 28);
});

// ── Profile image live preview ───────────────────────────
const avatarCamBtn = document.getElementById('avatarCamBtn');
const fileInput = document.getElementById('file-input');
const uploadForm = document.getElementById('uploadForm');
const profileImage = document.getElementById('profileImage');

if (avatarCamBtn && fileInput && uploadForm) {
  avatarCamBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Avatar camera button clicked');
    fileInput.click();
  });
  
  fileInput.addEventListener('change', function(e) {
    if (e.target.files?.[0]) {
      const reader = new FileReader();
      reader.onload = e => profileImage.src = e.target.result;
      reader.readAsDataURL(e.target.files[0]);
      console.log('Profile picture selected:', e.target.files[0].name);
      uploadForm.submit();
    }
  });
}

// ── Password strength indicator ──────────────────────────
document.getElementById('newPw')?.addEventListener('input', function() {
  const v = this.value;
  const el = document.getElementById('pwStrength');
  if (!v) { el.textContent = ''; return; }
  const score = [v.length >= 8, /[A-Z]/.test(v), /[0-9]/.test(v), /[^A-Za-z0-9]/.test(v)].filter(Boolean).length;
  const labels = ['','Weak','Fair','Good','Strong'];
  const colors = ['','var(--rose)','var(--amber)','var(--blue)','var(--emerald)'];
  el.textContent = '● ' + (labels[score] || 'Weak');
  el.style.color = colors[score] || 'var(--rose)';
});

// ── Password match indicator ─────────────────────────────
function checkMatch() {
  const nv = document.getElementById('newPw')?.value;
  const cv = document.getElementById('confPw')?.value;
  const el = document.getElementById('pwMatch');
  if (!cv) { el.textContent = ''; return; }
  if (nv === cv) { el.textContent = '✓ Passwords match'; el.style.color = 'var(--emerald)'; }
  else           { el.textContent = '✗ Does not match';  el.style.color = 'var(--rose)'; }
}
document.getElementById('confPw')?.addEventListener('input', checkMatch);
document.getElementById('newPw')?.addEventListener('input', checkMatch);

// ── Unsaved changes warning ───────────────────────────────
let formChanged = false;
document.querySelectorAll('#edit-profile input, #edit-profile textarea')
  .forEach(el => el.addEventListener('input', () => formChanged = true));
window.addEventListener('beforeunload', e => { if (formChanged) { e.preventDefault(); e.returnValue = ''; } });
document.getElementById('editForm')?.addEventListener('submit', () => formChanged = false);

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

// ── Cover photo upload ────────────────────────────────────
console.log('=== Cover Photo Upload Script Starting ===');
const coverEditBtn = document.getElementById('coverEditBtn');
const coverInput = document.getElementById('cover-input');
const coverForm = document.getElementById('coverForm');

console.log('coverEditBtn:', coverEditBtn);
console.log('coverInput:', coverInput);
console.log('coverForm:', coverForm);

if (coverEditBtn && coverInput && coverForm) {
  console.log('All elements found! Attaching event listeners...');
  
  // Click handler for the edit button
  coverEditBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    console.log('✓ Cover edit button clicked!');
    console.log('Triggering file input click...');
    coverInput.click();
  });
  
  // File selection handler
  coverInput.addEventListener('change', function() {
    console.log('✓ File input change event fired');
    if (this.files && this.files[0]) {
      console.log('✓ File selected:', this.files[0].name);
      console.log('File size:', this.files[0].size, 'bytes');
      console.log('File type:', this.files[0].type);
      console.log('Submitting form...');
      coverForm.submit();
    } else {
      console.log('✗ No file selected');
    }
  });
  
  console.log('✓ Event listeners attached successfully');
} else {
  console.error('✗ ERROR: One or more elements not found!');
  if (!coverEditBtn) console.error('  - coverEditBtn is missing');
  if (!coverInput) console.error('  - coverInput is missing');
  if (!coverForm) console.error('  - coverForm is missing');
}

// ── Loading screen ────────────────────────────────────────
window.addEventListener('load', function() {
  setTimeout(function() {
    const loadingScreen = document.getElementById('loadingScreen');
    if (loadingScreen) {
      loadingScreen.classList.add('hidden');
    }
  }, 1100);
});
</script>
</body>
</html>