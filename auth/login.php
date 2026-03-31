<?php
// auth/login.php
require_once '../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../student/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($conn, $_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, profile_picture FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']         = $user['id'];
                $_SESSION['username']        = $user['username'];
                $_SESSION['full_name']       = $user['full_name'];
                $_SESSION['role']            = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'];

                updateLoginStreak($conn, $user['id']);

                if ($user['role'] == 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../student/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Cabinet+Grotesk:wght@300;400;500;700&display=swap" rel="stylesheet">
<!-- Fallback if Clash Display not available via Google -->
<link href="https://api.fontshare.com/v2/css?f[]=clash-display@400,500,600,700&f[]=cabinet-grotesk@300,400,500,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Variables — same purple palette as original ── */
:root {
  --purple-deep:   #0f0c29;
  --purple-mid:    #302b63;
  --purple-dark:   #24243e;
  --purple-core:   #7b2cff;
  --purple-light:  #9b4dff;
  --purple-pale:   rgba(123,44,255,.15);
  --purple-border: rgba(123,44,255,.35);
  --surface:       rgba(30,30,47,.98);
  --surface2:      rgba(20,20,35,.9);
  --text:          #ffffff;
  --text2:         #e0e0e0;
  --muted:         #b0b0b0;
  --dimmed:        #888888;
  --error-bg:      rgba(255,68,68,.15);
  --error-border:  rgba(255,68,68,.3);
  --error-text:    #ff6b6b;
  --ease:          cubic-bezier(.4,0,.2,1);
}

html, body {
  height: 100%;
  font-family: 'Cabinet Grotesk', 'DM Sans', sans-serif;
  background: var(--purple-deep);
  color: var(--text);
  overflow: hidden;
}

/* ════════════════════════════════════════════
   FULL-SCREEN BACKGROUND
════════════════════════════════════════════ */
.bg-scene {
  position: fixed;
  inset: 0;
  z-index: 0;
  overflow: hidden;
}

/* Gradient mesh matching original palette */
.bg-scene::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 60% 80% at 15% 50%, rgba(123,44,255,.28) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 85% 20%, rgba(155,77,255,.18) 0%, transparent 55%),
    radial-gradient(ellipse 80% 40% at 50% 100%, rgba(48,43,99,.6) 0%, transparent 60%),
    linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
}

/* Animated orbs — same floating effect as original */
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(1px);
  animation: orbFloat linear infinite;
}
.orb-1 {
  width: 520px; height: 520px;
  top: -120px; left: -80px;
  background: radial-gradient(circle, rgba(123,44,255,.22) 0%, transparent 70%);
  animation-duration: 18s;
}
.orb-2 {
  width: 380px; height: 380px;
  bottom: -100px; right: -60px;
  background: radial-gradient(circle, rgba(155,77,255,.18) 0%, transparent 70%);
  animation-duration: 14s;
  animation-direction: reverse;
}
.orb-3 {
  width: 200px; height: 200px;
  top: 40%; right: 30%;
  background: radial-gradient(circle, rgba(123,44,255,.12) 0%, transparent 70%);
  animation-duration: 10s;
  animation-delay: -5s;
}

@keyframes orbFloat {
  0%, 100% { transform: translate(0, 0) scale(1); }
  25%       { transform: translate(30px, -40px) scale(1.04); }
  50%       { transform: translate(-20px, 30px) scale(.97); }
  75%       { transform: translate(40px, 20px) scale(1.02); }
}

/* Grain texture overlay */
.bg-scene::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.06'/%3E%3C/svg%3E");
  background-size: 200px 200px;
  opacity: .5;
  pointer-events: none;
  mix-blend-mode: overlay;
}

/* ════════════════════════════════════════════
   OUTER WRAPPER — center the card
════════════════════════════════════════════ */
.page-wrap {
  position: relative;
  z-index: 1;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

/* ════════════════════════════════════════════
   LOGIN CARD
════════════════════════════════════════════ */
.login-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  width: 100%;
  max-width: 960px;
  min-height: 580px;
  border-radius: 28px;
  overflow: hidden;
  box-shadow:
    0 0 0 1px rgba(123,44,255,.25),
    0 40px 80px rgba(0,0,0,.55),
    0 0 120px rgba(123,44,255,.12);
  animation: cardIn .7s var(--ease) both;
}

@keyframes cardIn {
  from { opacity: 0; transform: translateY(28px) scale(.97); }
  to   { opacity: 1; transform: translateY(0)    scale(1); }
}

/* ════════════════════════════════════════════
   LEFT PANEL — info side
════════════════════════════════════════════ */
.panel-left {
  background: linear-gradient(145deg, #1a0d3d 0%, #2d1b6e 40%, #1e1254 100%);
  padding: 52px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}

/* Top-right decorative arc */
.panel-left::before {
  content: '';
  position: absolute;
  top: -80px; right: -80px;
  width: 320px; height: 320px;
  border-radius: 50%;
  border: 1px solid rgba(123,44,255,.25);
  pointer-events: none;
}
.panel-left::after {
  content: '';
  position: absolute;
  top: -40px; right: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(123,44,255,.2) 0%, transparent 70%);
  pointer-events: none;
}

/* Bottom-left decorative circle */
.panel-deco {
  position: absolute;
  bottom: -60px; left: -60px;
  width: 240px; height: 240px;
  border-radius: 50%;
  border: 1px solid rgba(155,77,255,.2);
  pointer-events: none;
}
.panel-deco::after {
  content: '';
  position: absolute;
  inset: 24px;
  border-radius: 50%;
  border: 1px solid rgba(155,77,255,.15);
}

/* Logo mark */
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
  animation: fadeUp .6s var(--ease) .15s both;
}
.brand-icon {
  width: 40px; height: 40px;
  border-radius: 11px;
  background: linear-gradient(135deg, var(--purple-core), var(--purple-light));
  display: grid; place-items: center;
  font-size: 1rem; color: #fff;
  box-shadow: 0 4px 14px rgba(123,44,255,.4);
  flex-shrink: 0;
}
.brand-name {
  font-family: 'Clash Display', 'Syne', sans-serif;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  letter-spacing: .02em;
}

/* Headline block */
.panel-headline {
  position: relative;
  z-index: 1;
  animation: fadeUp .6s var(--ease) .25s both;
}
.headline-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 12px;
}
.panel-headline h2 {
  font-family: 'Clash Display', 'Syne', sans-serif;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
  color: #fff;
  margin-bottom: 0;
}
.panel-headline p {
  font-size: .9rem;
  color: rgba(255,255,255,.55);
  line-height: 1.65;
}

/* Mobile login button - hidden by default */
.mobile-login-btn {
  display: none;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: rgba(255,255,255,.15);
  border: 1.5px solid rgba(255,255,255,.25);
  border-radius: 12px;
  color: #fff;
  font-size: .85rem;
  font-weight: 600;
  font-family: 'Cabinet Grotesk', sans-serif;
  cursor: pointer;
  transition: all .25s var(--ease);
  backdrop-filter: blur(10px);
  white-space: nowrap;
  flex-shrink: 0;
}
.mobile-login-btn:hover {
  background: rgba(123,44,255,.4);
  border-color: rgba(123,44,255,.6);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(123,44,255,.3);
}
.mobile-login-btn i {
  font-size: .75rem;
  animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(3px); }
}

/* Feature list */
.feature-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
  position: relative;
  z-index: 1;
  animation: fadeUp .6s var(--ease) .35s both;
}
.feature-row {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 13px 16px;
  border-radius: 13px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.08);
  transition: all .22s var(--ease);
  cursor: default;
}
.feature-row:hover {
  background: rgba(123,44,255,.18);
  border-color: rgba(123,44,255,.35);
  transform: translateX(4px);
}
.feat-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  background: rgba(123,44,255,.3);
  display: grid; place-items: center;
  font-size: .88rem;
  color: rgba(255,255,255,.9);
  flex-shrink: 0;
  transition: background .22s var(--ease);
}
.feature-row:hover .feat-icon {
  background: rgba(123,44,255,.5);
}
.feat-body {}
.feat-title {
  font-size: .85rem;
  font-weight: 600;
  color: #fff;
  margin-bottom: 1px;
}
.feat-sub {
  font-size: .75rem;
  color: rgba(255,255,255,.45);
}

/* ════════════════════════════════════════════
   RIGHT PANEL — form side
════════════════════════════════════════════ */
.panel-right {
  background: var(--surface);
  padding: 52px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

/* Subtle top-right glow */
.panel-right::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(123,44,255,.08) 0%, transparent 70%);
  pointer-events: none;
}

.form-inner {
  position: relative;
  z-index: 1;
  animation: fadeUp .6s var(--ease) .2s both;
}

/* Form header */
.form-head {
  margin-bottom: 32px;
}
.form-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: .72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--purple-light);
  margin-bottom: 10px;
  padding: 4px 10px;
  background: rgba(155,77,255,.12);
  border: 1px solid rgba(155,77,255,.22);
  border-radius: 20px;
}
.form-head h2 {
  font-family: 'Clash Display', 'Syne', sans-serif;
  font-size: 1.9rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.2;
  margin-bottom: 6px;
}
.form-head p {
  font-size: .875rem;
  color: var(--muted);
  line-height: 1.55;
}

/* Error alert */
.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 13px 16px;
  border-radius: 11px;
  margin-bottom: 20px;
  background: var(--error-bg);
  color: var(--error-text);
  border: 1px solid var(--error-border);
  font-size: .875rem;
  animation: fadeUp .3s var(--ease) both;
}
.alert i { flex-shrink: 0; }

/* Form groups */
.form-group {
  margin-bottom: 18px;
}
.form-group label {
  display: block;
  font-size: .8rem;
  font-weight: 600;
  color: var(--text2);
  margin-bottom: 8px;
  letter-spacing: .01em;
}

/* Input wrapper for icon */
.input-wrap {
  position: relative;
}
.input-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--dimmed);
  font-size: .85rem;
  pointer-events: none;
  transition: color .18s var(--ease);
}

.form-control {
  width: 100%;
  padding: 13px 15px 13px 42px;
  border: 1.5px solid var(--purple-border);
  border-radius: 11px;
  font-size: .9rem;
  font-family: 'Cabinet Grotesk', sans-serif;
  transition: all .2s var(--ease);
  background: var(--surface2);
  color: #ffffff;
}
.form-control::placeholder { color: #555; }
.form-control:focus {
  outline: none;
  border-color: var(--purple-core);
  box-shadow: 0 0 0 3px rgba(123,44,255,.18);
  background: rgba(20,20,35,1);
}
.form-control:focus ~ .input-icon,
.input-wrap:focus-within .input-icon {
  color: var(--purple-light);
}

/* Password toggle */
.pw-toggle {
  position: absolute;
  right: 13px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--dimmed);
  cursor: pointer;
  padding: 4px;
  font-size: .85rem;
  transition: color .15s;
  line-height: 1;
}
.pw-toggle:hover { color: var(--purple-light); }

/* Submit button */
.btn-primary {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--purple-core) 0%, var(--purple-light) 100%);
  color: white;
  border: none;
  border-radius: 11px;
  font-size: .95rem;
  font-family: 'Cabinet Grotesk', sans-serif;
  font-weight: 700;
  cursor: pointer;
  transition: all .25s var(--ease);
  box-shadow: 0 4px 18px rgba(123,44,255,.35);
  margin-top: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  letter-spacing: .01em;
  position: relative;
  overflow: hidden;
}
.btn-primary::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.08) 0%, transparent 60%);
  opacity: 0;
  transition: opacity .2s var(--ease);
}
.btn-primary:hover {
  background: linear-gradient(135deg, #6a1bb9 0%, #8a3de6 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(123,44,255,.45);
}
.btn-primary:hover::after { opacity: 1; }
.btn-primary:active { transform: translateY(0); }

/* Divider */
.or-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 22px 0;
  font-size: .78rem;
  color: #444;
}
.or-divider::before, .or-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(123,44,255,.2);
}

/* Register link row */
.form-footer {
  text-align: center;
  margin-top: 20px;
  font-size: .875rem;
  color: var(--muted);
}
.auth-link {
  color: var(--purple-light);
  text-decoration: none;
  font-weight: 600;
  transition: color .15s var(--ease);
}
.auth-link:hover {
  color: #fff;
  text-decoration: underline;
}

/* Stats strip at bottom of left panel */
.stats-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  position: relative;
  z-index: 1;
  animation: fadeUp .6s var(--ease) .45s both;
}
.stat-cell {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 11px;
  padding: 12px 10px;
  text-align: center;
}
.stat-num {
  font-family: 'Clash Display', 'Syne', sans-serif;
  font-size: 1.15rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
  margin-bottom: 3px;
}
.stat-label {
  font-size: .65rem;
  color: rgba(255,255,255,.4);
  text-transform: uppercase;
  letter-spacing: .06em;
}

/* ── Animations ── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════ */

/* Large tablets and small desktops */
@media (max-width: 1024px) {
  .login-card {
    max-width: 880px;
  }
  .panel-left {
    padding: 44px 36px;
  }
  .panel-right {
    padding: 44px 40px;
  }
  .panel-headline h2 {
    font-size: 1.75rem;
  }
  /* Show mobile login button */
  .mobile-login-btn {
    display: flex;
  }
}

/* Tablets */
@media (max-width: 820px) {
  html, body { overflow-y: auto; }
  .page-wrap { 
    align-items: flex-start; 
    padding: 20px 16px;
    min-height: 100vh;
  }
  .login-card {
    grid-template-columns: 1fr;
    max-width: 520px;
    margin: 0 auto;
    border-radius: 22px;
    min-height: unset;
  }
  .panel-left {
    padding: 40px 36px;
    gap: 28px;
  }
  .panel-headline h2 { 
    font-size: 1.65rem; 
  }
  .panel-headline p {
    font-size: .85rem;
  }
  .feature-list {
    gap: 12px;
  }
  .feature-row {
    padding: 12px 14px;
  }
  .stats-strip { 
    display: grid;
    gap: 8px;
  }
  .stat-cell {
    padding: 10px 8px;
  }
  .stat-num {
    font-size: 1rem;
  }
  .panel-right { 
    padding: 40px 36px; 
  }
  .form-head h2 {
    font-size: 1.7rem;
  }
}

/* Mobile landscape and small tablets */
@media (max-width: 640px) {
  .page-wrap {
    padding: 16px 12px;
  }
  .login-card {
    max-width: 100%;
    border-radius: 18px;
  }
  .panel-left {
    padding: 32px 28px;
  }
  .brand-icon {
    width: 36px;
    height: 36px;
    font-size: .9rem;
  }
  .brand-name {
    font-size: .9rem;
  }
  .panel-headline h2 {
    font-size: 1.5rem;
  }
  .panel-headline p {
    font-size: .8rem;
  }
  .feature-row {
    padding: 11px 13px;
    gap: 11px;
  }
  .feat-icon {
    width: 34px;
    height: 34px;
    font-size: .82rem;
  }
  .feat-title {
    font-size: .82rem;
  }
  .feat-sub {
    font-size: .72rem;
  }
  .stats-strip {
    gap: 6px;
  }
  .stat-cell {
    padding: 9px 6px;
  }
  .stat-num {
    font-size: .95rem;
  }
  .stat-label {
    font-size: .6rem;
  }
  .panel-right {
    padding: 32px 28px;
  }
  .form-head h2 {
    font-size: 1.6rem;
  }
  .form-head p {
    font-size: .82rem;
  }
  .form-control {
    padding: 12px 14px 12px 40px;
    font-size: .875rem;
  }
  .btn-primary {
    padding: 13px;
    font-size: .9rem;
  }
}

/* Mobile portrait */
@media (max-width: 480px) {
  .page-wrap {
    padding: 12px 8px;
  }
  .login-card {
    border-radius: 16px;
  }
  .panel-left {
    padding: 28px 24px;
    gap: 24px;
  }
  .brand {
    gap: 10px;
  }
  .brand-icon {
    width: 34px;
    height: 34px;
    font-size: .85rem;
  }
  .brand-name {
    font-size: .85rem;
  }
  .panel-headline h2 {
    font-size: 1.35rem;
    margin-bottom: 10px;
  }
  .panel-headline p {
    font-size: .78rem;
  }
  .feature-list {
    gap: 10px;
  }
  .feature-row {
    padding: 10px 12px;
    gap: 10px;
  }
  .feat-icon {
    width: 32px;
    height: 32px;
    font-size: .78rem;
  }
  .feat-title {
    font-size: .78rem;
  }
  .feat-sub {
    font-size: .7rem;
  }
  .stats-strip {
    gap: 6px;
  }
  .stat-cell {
    padding: 8px 6px;
  }
  .stat-num {
    font-size: .9rem;
  }
  .stat-label {
    font-size: .58rem;
  }
  .panel-right {
    padding: 28px 24px;
  }
  .form-eyebrow {
    font-size: .68rem;
    padding: 3px 9px;
  }
  .form-head h2 {
    font-size: 1.45rem;
  }
  .form-head p {
    font-size: .8rem;
  }
  .form-group {
    margin-bottom: 16px;
  }
  .form-group label {
    font-size: .78rem;
    margin-bottom: 7px;
  }
  .form-control {
    padding: 11px 13px 11px 38px;
    font-size: .85rem;
  }
  .input-icon {
    left: 13px;
    font-size: .8rem;
  }
  .pw-toggle {
    right: 11px;
    font-size: .8rem;
  }
  .btn-primary {
    padding: 12px;
    font-size: .875rem;
    gap: 8px;
  }
  .or-divider {
    margin: 18px 0;
    font-size: .75rem;
  }
  .form-footer {
    font-size: .82rem;
    margin-top: 16px;
  }
  .alert {
    padding: 11px 14px;
    font-size: .82rem;
  }
}

/* Extra small mobile */
@media (max-width: 360px) {
  .page-wrap {
    padding: 10px 6px;
  }
  .login-card {
    border-radius: 14px;
  }
  .panel-left {
    padding: 24px 20px;
    gap: 20px;
  }
  .brand-icon {
    width: 32px;
    height: 32px;
    font-size: .8rem;
  }
  .brand-name {
    font-size: .8rem;
  }
  .panel-headline h2 {
    font-size: 1.2rem;
  }
  .panel-headline p {
    font-size: .75rem;
  }
  .feature-row {
    padding: 9px 11px;
  }
  .feat-icon {
    width: 30px;
    height: 30px;
    font-size: .75rem;
  }
  .feat-title {
    font-size: .75rem;
  }
  .feat-sub {
    font-size: .68rem;
  }
  .stats-strip {
    gap: 5px;
  }
  .stat-cell {
    padding: 7px 5px;
  }
  .stat-num {
    font-size: .85rem;
  }
  .stat-label {
    font-size: .55rem;
  }
  .panel-right {
    padding: 24px 20px;
  }
  .form-head h2 {
    font-size: 1.3rem;
  }
  .form-head p {
    font-size: .78rem;
  }
  .form-control {
    padding: 10px 12px 10px 36px;
    font-size: .82rem;
  }
  .btn-primary {
    padding: 11px;
    font-size: .85rem;
  }
}
</style>
</head>
<body>

<!-- Animated background -->
<div class="bg-scene">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<div class="page-wrap">
  <div class="login-card">

    <!-- ════════════ LEFT PANEL ════════════ -->
    <div class="panel-left">
      <div class="panel-deco"></div>

      <!-- Brand -->
      <div class="brand">
        <div class="brand-icon">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <span class="brand-name"><?php echo SITE_NAME; ?></span>
      </div>

      <!-- Headline -->
      <div class="panel-headline">
        <div class="headline-row">
          <h2>Your Smart<br>Learning<br>Companion</h2>
          <button class="mobile-login-btn" onclick="scrollToLogin()" aria-label="Scroll to login form">
            <i class="fas fa-arrow-down"></i>
            Login
          </button>
        </div>
        <p>Everything you need to learn faster,<br>track progress, and level up.</p>
      </div>

      <!-- Features -->
      <ul class="feature-list">
        <li class="feature-row">
          <div class="feat-icon"><i class="fas fa-book-open"></i></div>
          <div class="feat-body">
            <div class="feat-title">Interactive Lessons</div>
            <div class="feat-sub">Learn with engaging content</div>
          </div>
        </li>
        <li class="feature-row">
          <div class="feat-icon"><i class="fas fa-chart-line"></i></div>
          <div class="feat-body">
            <div class="feat-title">Track Progress</div>
            <div class="feat-sub">Monitor your learning journey</div>
          </div>
        </li>
        <li class="feature-row">
          <div class="feat-icon"><i class="fas fa-trophy"></i></div>
          <div class="feat-body">
            <div class="feat-title">Earn Rewards</div>
            <div class="feat-sub">Get points and achievements</div>
          </div>
        </li>
        <li class="feature-row">
          <div class="feat-icon"><i class="fas fa-users"></i></div>
          <div class="feat-body">
            <div class="feat-title">Compete &amp; Collaborate</div>
            <div class="feat-sub">Join the learning community</div>
          </div>
        </li>
      </ul>

      <!-- Stats strip -->
      <div class="stats-strip">
        <div class="stat-cell">
          <div class="stat-num">1.2k+</div>
          <div class="stat-label">Students</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num">300+</div>
          <div class="stat-label">Lessons</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num">98%</div>
          <div class="stat-label">Satisfaction</div>
        </div>
      </div>
    </div>

    <!-- ════════════ RIGHT PANEL ════════════ -->
    <div class="panel-right">
      <div class="form-inner">

        <div class="form-head">
          <div class="form-eyebrow">
            <i class="fas fa-lock"></i> Secure Login
          </div>
          <h2>Welcome Back</h2>
          <p>Please log in to continue your learning journey</p>
        </div>

        <?php if ($error): ?>
        <div class="alert">
          <i class="fas fa-circle-exclamation"></i>
          <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">

          <div class="form-group">
            <label for="username">Username or Email</label>
            <div class="input-wrap">
              <input
                type="text"
                id="username"
                name="username"
                class="form-control"
                placeholder="Enter username or email"
                required
                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                autocomplete="username"
              >
              <i class="fas fa-user input-icon"></i>
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
              >
              <i class="fas fa-lock input-icon"></i>
              <button type="button" class="pw-toggle" onclick="togglePw()" title="Show / hide password" aria-label="Toggle password visibility">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-primary">
            <i class="fas fa-arrow-right-to-bracket"></i>
            Log In
          </button>

        </form>

        <div class="or-divider">or</div>

        <div class="form-footer">
          Don't have an account?
          <a href="register.php" class="auth-link" onclick="window.location.href='register.php'; return true;">Register here</a>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
function togglePw() {
  const input   = document.getElementById('password');
  const icon    = document.getElementById('eyeIcon');
  const showing = input.type === 'text';
  input.type    = showing ? 'password' : 'text';
  icon.className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
}

function scrollToLogin() {
  const loginForm = document.querySelector('.panel-right');
  if (loginForm) {
    loginForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
</script>
</body>
</html>