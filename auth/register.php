<?php
// auth/register.php
require_once '../config/database.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name        = sanitize($conn, $_POST['full_name']);
    $username         = sanitize($conn, $_POST['username']);
    $email            = sanitize($conn, $_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    if (empty($full_name))                              $errors[] = "Full name is required";
    if (empty($username))                               $errors[] = "Username is required";
    if (strlen($username) < 3)                          $errors[] = "Username must be at least 3 characters";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = "Invalid email format";
    if (strlen($password) < 6)                          $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password)                $errors[] = "Passwords do not match";

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Username already exists";

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Email already registered";

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $student_id      = 'STU' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, student_id, role) VALUES (?, ?, ?, ?, ?, 'student')");
        $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $student_id);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt2   = $conn->prepare("INSERT INTO rankings (user_id) VALUES (?)");
            $stmt2->bind_param("i", $user_id); $stmt2->execute();

            $success = "Account created! Redirecting to login…";
            header("refresh:3;url=login.php");
        } else {
            $error = "Registration failed: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — <?php echo SITE_NAME; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://api.fontshare.com/v2/css?f[]=clash-display@400,500,600,700&f[]=cabinet-grotesk@300,400,500,700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Variables — same purple palette ── */
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
  --success-bg:    rgba(16,185,129,.14);
  --success-border:rgba(16,185,129,.3);
  --success-text:  #34d399;
  --error-bg:      rgba(255,68,68,.15);
  --error-border:  rgba(255,68,68,.3);
  --error-text:    #ff6b6b;
  --ease:          cubic-bezier(.4,0,.2,1);
}

html, body {
  min-height: 100%;
  font-family: 'Cabinet Grotesk', 'DM Sans', sans-serif;
  background: var(--purple-deep);
  color: var(--text);
  overflow-x: hidden;
}

/* ════════════════════════════════════
   BACKGROUND
════════════════════════════════════ */
.bg-scene {
  position: fixed;
  inset: 0;
  z-index: 0;
  overflow: hidden;
}
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
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(1px);
  animation: orbFloat linear infinite;
}
.orb-1 { width:520px;height:520px;top:-120px;left:-80px;background:radial-gradient(circle,rgba(123,44,255,.22) 0%,transparent 70%);animation-duration:18s; }
.orb-2 { width:380px;height:380px;bottom:-100px;right:-60px;background:radial-gradient(circle,rgba(155,77,255,.18) 0%,transparent 70%);animation-duration:14s;animation-direction:reverse; }
.orb-3 { width:200px;height:200px;top:40%;right:30%;background:radial-gradient(circle,rgba(123,44,255,.12) 0%,transparent 70%);animation-duration:10s;animation-delay:-5s; }
@keyframes orbFloat {
  0%,100%{ transform:translate(0,0) scale(1); }
  25%    { transform:translate(30px,-40px) scale(1.04); }
  50%    { transform:translate(-20px,30px) scale(.97); }
  75%    { transform:translate(40px,20px) scale(1.02); }
}
.bg-scene::after {
  content:'';position:absolute;inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.06'/%3E%3C/svg%3E");
  background-size:200px 200px;opacity:.5;pointer-events:none;mix-blend-mode:overlay;
}

/* ════════════════════════════════════
   PAGE WRAPPER
════════════════════════════════════ */
.page-wrap {
  position: relative;
  z-index: 1;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 28px 24px;
}

/* ════════════════════════════════════
   CARD
════════════════════════════════════ */
.register-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  width: 100%;
  max-width: 980px;
  border-radius: 28px;
  overflow: hidden;
  box-shadow:
    0 0 0 1px rgba(123,44,255,.25),
    0 40px 80px rgba(0,0,0,.55),
    0 0 120px rgba(123,44,255,.12);
  animation: cardIn .7s var(--ease) both;
}
@keyframes cardIn {
  from { opacity:0; transform:translateY(28px) scale(.97); }
  to   { opacity:1; transform:translateY(0)    scale(1); }
}

/* ════════════════════════════════════
   LEFT PANEL
════════════════════════════════════ */
.panel-left {
  background: linear-gradient(145deg, #1a0d3d 0%, #2d1b6e 40%, #1e1254 100%);
  padding: 52px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 32px;
  position: relative;
  overflow: hidden;
}
.panel-left::before {
  content:'';position:absolute;top:-80px;right:-80px;
  width:320px;height:320px;border-radius:50%;
  border:1px solid rgba(123,44,255,.25);pointer-events:none;
}
.panel-left::after {
  content:'';position:absolute;top:-40px;right:-40px;
  width:200px;height:200px;border-radius:50%;
  background:radial-gradient(circle,rgba(123,44,255,.2) 0%,transparent 70%);pointer-events:none;
}
.panel-deco {
  position:absolute;bottom:-60px;left:-60px;
  width:240px;height:240px;border-radius:50%;
  border:1px solid rgba(155,77,255,.2);pointer-events:none;
}
.panel-deco::after {
  content:'';position:absolute;inset:24px;border-radius:50%;
  border:1px solid rgba(155,77,255,.15);
}

/* Brand */
.brand {
  display:flex;align-items:center;gap:12px;
  position:relative;z-index:1;
  animation:fadeUp .6s var(--ease) .15s both;
}
.brand-icon {
  width:40px;height:40px;border-radius:11px;
  background:linear-gradient(135deg,var(--purple-core),var(--purple-light));
  display:grid;place-items:center;font-size:1rem;color:#fff;
  box-shadow:0 4px 14px rgba(123,44,255,.4);flex-shrink:0;
}
.brand-name {
  font-family:'Clash Display','Syne',sans-serif;
  font-size:1rem;font-weight:600;color:#fff;letter-spacing:.02em;
}

/* Steps list — mirrors reference image */
.steps-section {
  position:relative;z-index:1;flex:1;
  display:flex;flex-direction:column;justify-content:center;gap:20px;
  animation:fadeUp .6s var(--ease) .25s both;
}
.steps-headline h2 {
  font-family:'Clash Display','Syne',sans-serif;
  font-size:1.85rem;font-weight:700;line-height:1.2;color:#fff;
  margin-bottom:8px;
}
.steps-headline p {
  font-size:.875rem;color:rgba(255,255,255,.5);line-height:1.6;
}
.steps-list {
  list-style:none;display:flex;flex-direction:column;gap:10px;
  margin-top:8px;
}
.step-item {
  display:flex;align-items:center;gap:13px;
  padding:14px 17px;border-radius:13px;
  border:1px solid transparent;
  font-size:.875rem;font-weight:500;
  transition:all .22s var(--ease);cursor:default;
}
.step-item.active {
  background:rgba(255,255,255,.92);color:#111;font-weight:700;
}
.step-item:not(.active) {
  background:rgba(255,255,255,.07);
  border-color:rgba(255,255,255,.09);
  color:rgba(255,255,255,.45);
}
.step-item:not(.active):hover {
  background:rgba(255,255,255,.11);
  color:rgba(255,255,255,.65);
}
.step-num {
  width:24px;height:24px;border-radius:50%;
  display:grid;place-items:center;
  font-family:'Clash Display','Syne',sans-serif;
  font-size:.75rem;font-weight:700;flex-shrink:0;
}
.step-item.active .step-num { background:#111;color:#fff; }
.step-item:not(.active) .step-num { background:rgba(255,255,255,.12);color:rgba(255,255,255,.45); }

/* Stats strip */
.stats-strip {
  display:grid;grid-template-columns:repeat(3,1fr);gap:10px;
  position:relative;z-index:1;
  animation:fadeUp .6s var(--ease) .45s both;
}
.stat-cell {
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.08);
  border-radius:11px;padding:12px 10px;text-align:center;
}
.stat-num {
  font-family:'Clash Display','Syne',sans-serif;
  font-size:1.1rem;font-weight:700;color:#fff;line-height:1;margin-bottom:3px;
}
.stat-label {
  font-size:.62rem;color:rgba(255,255,255,.4);
  text-transform:uppercase;letter-spacing:.06em;
}

/* ════════════════════════════════════
   RIGHT PANEL — form
════════════════════════════════════ */
.panel-right {
  background: var(--surface);
  padding: 46px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
  overflow-y: auto;
}
.panel-right::before {
  content:'';position:absolute;top:-60px;right:-60px;
  width:200px;height:200px;border-radius:50%;
  background:radial-gradient(circle,rgba(123,44,255,.08) 0%,transparent 70%);
  pointer-events:none;
}

.form-inner {
  position:relative;z-index:1;
  animation:fadeUp .6s var(--ease) .2s both;
}

/* Form header */
.form-head { margin-bottom:26px; }
.form-eyebrow {
  display:inline-flex;align-items:center;gap:7px;
  font-size:.7rem;font-weight:600;text-transform:uppercase;
  letter-spacing:.1em;color:var(--purple-light);
  margin-bottom:10px;padding:4px 10px;
  background:rgba(155,77,255,.12);
  border:1px solid rgba(155,77,255,.22);border-radius:20px;
}
.form-head h2 {
  font-family:'Clash Display','Syne',sans-serif;
  font-size:1.7rem;font-weight:700;color:#fff;
  line-height:1.2;margin-bottom:6px;
}
.form-head p { font-size:.855rem;color:var(--muted);line-height:1.5; }

/* Alerts */
.alert {
  display:flex;align-items:flex-start;gap:10px;
  padding:13px 16px;border-radius:11px;
  margin-bottom:18px;font-size:.84rem;line-height:1.5;
  animation:fadeUp .3s var(--ease) both;
}
.alert i { flex-shrink:0;margin-top:1px; }
.alert-error   { background:var(--error-bg);  color:var(--error-text);  border:1px solid var(--error-border); }
.alert-success { background:var(--success-bg);color:var(--success-text);border:1px solid var(--success-border); }

/* Two-column name+username row */
.field-row {
  display:grid;grid-template-columns:1fr 1fr;gap:12px;
}

/* Form group */
.form-group { margin-bottom:15px; }
.form-group label {
  display:block;font-size:.79rem;font-weight:600;
  color:var(--text2);margin-bottom:7px;letter-spacing:.01em;
}
.input-wrap { position:relative; }
.input-icon {
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:var(--dimmed);font-size:.82rem;pointer-events:none;
  transition:color .18s var(--ease);
}
.form-control {
  width:100%;padding:12px 14px 12px 40px;
  border:1.5px solid var(--purple-border);border-radius:11px;
  font-size:.875rem;font-family:'Cabinet Grotesk',sans-serif;
  transition:all .2s var(--ease);
  background:var(--surface2);color:#fff;
}
.form-control::placeholder { color:#555; }
.form-control:focus {
  outline:none;border-color:var(--purple-core);
  box-shadow:0 0 0 3px rgba(123,44,255,.18);
  background:rgba(20,20,35,1);
}
.input-wrap:focus-within .input-icon { color:var(--purple-light); }

/* Password toggle */
.pw-toggle {
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;color:var(--dimmed);
  cursor:pointer;padding:4px;font-size:.82rem;
  transition:color .15s;line-height:1;
}
.pw-toggle:hover { color:var(--purple-light); }

/* Password strength bar */
.pw-strength-bar {
  display:grid;grid-template-columns:repeat(4,1fr);gap:4px;
  margin-top:8px;
}
.pw-seg {
  height:3px;border-radius:2px;
  background:rgba(255,255,255,.1);
  transition:background .3s var(--ease);
}
.pw-seg.active-1 { background:#f43f5e; }
.pw-seg.active-2 { background:#f59e0b; }
.pw-seg.active-3 { background:#3b82f6; }
.pw-seg.active-4 { background:#10b981; }
.pw-strength-text {
  font-size:.7rem;color:var(--dimmed);margin-top:4px;
  transition:color .3s var(--ease);
}

/* Submit */
.btn-primary {
  width:100%;padding:13px;
  background:linear-gradient(135deg,var(--purple-core) 0%,var(--purple-light) 100%);
  color:#fff;border:none;border-radius:11px;
  font-size:.925rem;font-family:'Cabinet Grotesk',sans-serif;font-weight:700;
  cursor:pointer;transition:all .25s var(--ease);
  box-shadow:0 4px 18px rgba(123,44,255,.35);
  margin-top:8px;display:flex;align-items:center;justify-content:center;
  gap:9px;letter-spacing:.01em;position:relative;overflow:hidden;
}
.btn-primary::after {
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.08) 0%,transparent 60%);
  opacity:0;transition:opacity .2s var(--ease);
}
.btn-primary:hover {
  background:linear-gradient(135deg,#6a1bb9 0%,#8a3de6 100%);
  transform:translateY(-2px);
  box-shadow:0 8px 28px rgba(123,44,255,.45);
}
.btn-primary:hover::after { opacity:1; }
.btn-primary:active { transform:translateY(0); }

/* Footer link */
.form-footer {
  text-align:center;margin-top:18px;
  font-size:.875rem;color:var(--muted);
}
.auth-link {
  color:var(--purple-light);text-decoration:none;font-weight:600;
  transition:color .15s var(--ease);
}
.auth-link:hover { color:#fff;text-decoration:underline; }

/* Divider */
.or-divider {
  display:flex;align-items:center;gap:12px;
  margin:14px 0;font-size:.78rem;color:#444;
}
.or-divider::before,.or-divider::after {
  content:'';flex:1;height:1px;background:rgba(123,44,255,.2);
}

/* ── Animations ── */
@keyframes fadeUp {
  from { opacity:0;transform:translateY(14px); }
  to   { opacity:1;transform:translateY(0); }
}

/* ════════════════════════════════════
   RESPONSIVE
════════════════════════════════════ */
@media(max-width:860px){
  .register-card{ grid-template-columns:1fr; border-radius:22px; max-width:500px; margin:0 auto; }
  .panel-left{ padding:36px 32px; gap:24px; }
  .stats-strip{ display:none; }
  .steps-headline h2{ font-size:1.5rem; }
  .panel-right{ padding:36px 32px; }
}
@media(max-width:520px){
  .panel-left,.panel-right{ padding:28px 20px; }
  .field-row{ grid-template-columns:1fr; }
  .form-head h2{ font-size:1.4rem; }
}
</style>
</head>
<body>

<div class="bg-scene">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<div class="page-wrap">
<div class="register-card">

  <!-- ══════════ LEFT PANEL ══════════ -->
  <div class="panel-left">
    <div class="panel-deco"></div>

    <div class="brand">
      <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
      <span class="brand-name"><?php echo SITE_NAME; ?></span>
    </div>

    <div class="steps-section">
      <div class="steps-headline">
        <h2>Get Started<br>with Us</h2>
        <p>Complete these easy steps to create your account.</p>
      </div>
      <ul class="steps-list">
        <li class="step-item active">
          <span class="step-num">1</span> Sign up your account
        </li>
        <li class="step-item">
          <span class="step-num">2</span> Set up your workspace
        </li>
        <li class="step-item">
          <span class="step-num">3</span> Set up your profile
        </li>
      </ul>
    </div>

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

  <!-- ══════════ RIGHT PANEL ══════════ -->
  <div class="panel-right">
    <div class="form-inner">

      <div class="form-head">
        <div class="form-eyebrow"><i class="fas fa-user-plus"></i> New Account</div>
        <h2>Create Account</h2>
        <p>Join our learning platform and start your journey</p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fas fa-circle-exclamation"></i>
        <div><?php echo $error; ?></div>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-circle-check"></i>
        <div><?php echo $success; ?></div>
      </div>
      <?php endif; ?>

      <form method="POST" action="" id="regForm">

        <!-- Full name + Username side by side -->
        <div class="field-row">
          <div class="form-group">
            <label>Full Name</label>
            <div class="input-wrap">
              <input type="text" name="full_name" class="form-control"
                     placeholder="Your full name" required
                     value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
              <i class="fas fa-id-card input-icon"></i>
            </div>
          </div>
          <div class="form-group">
            <label>Username</label>
            <div class="input-wrap">
              <input type="text" name="username" class="form-control"
                     placeholder="Choose a username" required
                     value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
              <i class="fas fa-at input-icon"></i>
            </div>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-wrap">
            <input type="email" name="email" class="form-control"
                   placeholder="Enter your email" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            <i class="fas fa-envelope input-icon"></i>
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="pw1" class="form-control"
                   placeholder="Min. 6 characters" required
                   oninput="checkStrength(this.value)">
            <i class="fas fa-lock input-icon"></i>
            <button type="button" class="pw-toggle" onclick="togglePw('pw1','eye1')">
              <i class="fas fa-eye" id="eye1"></i>
            </button>
          </div>
          <!-- Strength meter -->
          <div class="pw-strength-bar">
            <div class="pw-seg" id="seg1"></div>
            <div class="pw-seg" id="seg2"></div>
            <div class="pw-seg" id="seg3"></div>
            <div class="pw-seg" id="seg4"></div>
          </div>
          <div class="pw-strength-text" id="strengthText">Enter a password</div>
        </div>

        <!-- Confirm password -->
        <div class="form-group">
          <label>Confirm Password</label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="pw2" class="form-control"
                   placeholder="Repeat your password" required
                   oninput="checkMatch()">
            <i class="fas fa-lock input-icon"></i>
            <button type="button" class="pw-toggle" onclick="togglePw('pw2','eye2')">
              <i class="fas fa-eye" id="eye2"></i>
            </button>
          </div>
          <div class="pw-strength-text" id="matchText"></div>
        </div>

        <button type="submit" class="btn-primary">
          <i class="fas fa-user-plus"></i> Create Account
        </button>

      </form>

      <div class="or-divider">or</div>

      <div class="form-footer">
        Already have an account? <a href="login.php" class="auth-link">Log in here</a>
      </div>

    </div>
  </div>

</div>
</div>

<script>
// ── Password show/hide ────────────────────────
function togglePw(inputId, iconId) {
  const inp  = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  const show = inp.type === 'text';
  inp.type        = show ? 'password' : 'text';
  icon.className  = show ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── Password strength meter ───────────────────
function checkStrength(val) {
  const segs  = [1,2,3,4].map(i => document.getElementById('seg'+i));
  const label = document.getElementById('strengthText');
  let score   = 0;

  if (val.length >= 6)                       score++;
  if (val.length >= 10)                      score++;
  if (/[A-Z]/.test(val)&&/[a-z]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)||/\d/.test(val)&&val.length>=8) score++;

  const levels = [
    { text:'Too short',   color:'#f43f5e' },
    { text:'Weak',        color:'#f59e0b' },
    { text:'Good',        color:'#3b82f6' },
    { text:'Strong 💪',  color:'#10b981' },
  ];

  segs.forEach((s,i) => {
    s.className = 'pw-seg' + (i < score ? ` active-${score}` : '');
  });

  if (!val) {
    label.textContent = 'Enter a password';
    label.style.color = '';
  } else {
    const lvl = levels[Math.min(score,4)-1] || levels[0];
    label.textContent = lvl.text;
    label.style.color = lvl.color;
  }
}

// ── Confirm password match ────────────────────
function checkMatch() {
  const p1  = document.getElementById('pw1').value;
  const p2  = document.getElementById('pw2').value;
  const el  = document.getElementById('matchText');
  if (!p2) { el.textContent=''; return; }
  if (p1 === p2) {
    el.textContent = '✓ Passwords match';
    el.style.color = '#10b981';
  } else {
    el.textContent = '✗ Passwords do not match';
    el.style.color = '#f43f5e';
  }
}

// ── Step click interaction ────────────────────
document.querySelectorAll('.step-item').forEach((step, i) => {
  step.addEventListener('click', () => {
    document.querySelectorAll('.step-item').forEach(s => s.classList.remove('active'));
    step.classList.add('active');
  });
});
</script>
</body>
</html>